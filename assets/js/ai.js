(function () {
    const bridge = window.V24SMHBridge;
    if (!bridge || !bridge.app) {
        return;
    }

    const app = bridge.app;
    const restRoot = (app.dataset.restRoot || '').replace(/\/$/, '');
    const restNonce = app.dataset.restNonce || '';
    const region = (name) => app.querySelector(`[data-region="${name}"]`);
    const aiField = (name) => app.querySelector(`[data-ai-field="${name}"]`);

    const state = {
        chatId: '',
        tenant: null,
        configured: false,
        options: {},
        preferences: {},
        context: null,
        responses: [],
        selectedResponseId: null,
        nextChangePreferences: null,
        recordingTarget: '',
        mediaRecorder: null,
        mediaStream: null,
        audioChunks: [],
        timerId: null,
        timerStartedAt: 0
    };

    const DEFAULT_PREFERENCES = {
        language: 'Italiano',
        tone: 'Professionale',
        writing: 'Argomentativo',
        format: '11'
    };

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function selectedResponse() {
        return state.responses.find((item) => Number(item.id) === Number(state.selectedResponseId)) || null;
    }

    function accountScopedKey(kind) {
        const core = bridge.getState();
        return `v24-smh-ai-${kind}-${app.dataset.currentUserId || '0'}-${core.accountId || '0'}`;
    }

    function normalizePreferences(input) {
        return {
            language: String(input.language || input.languageSelect || DEFAULT_PREFERENCES.language),
            tone: String(input.tone || input.toneSelect || DEFAULT_PREFERENCES.tone),
            writing: String(input.writing || input.writingSelect || DEFAULT_PREFERENCES.writing),
            format: String(input.format || input.formatSelect || DEFAULT_PREFERENCES.format)
        };
    }

    function postJson(path, payload) {
        return bridge.api(path, {
            method: 'POST',
            body: JSON.stringify(payload)
        });
    }

    function collectPreferences() {
        return normalizePreferences({
            language: aiField('language') ? aiField('language').value : '',
            tone: aiField('tone') ? aiField('tone').value : '',
            writing: aiField('writing') ? aiField('writing').value : '',
            format: aiField('format') ? aiField('format').value : ''
        });
    }

    function currentScope() {
        const selected = app.querySelector('[data-ai-field="scope"]:checked');
        return selected ? selected.value : 'next_change';
    }

    function readSessionPreferences() {
        try {
            const stored = sessionStorage.getItem(accountScopedKey('logout'));
            return stored ? normalizePreferences(JSON.parse(stored)) : null;
        } catch (error) {
            return null;
        }
    }

    function persistScope(scope, preferences) {
        if (scope === 'next_change') {
            state.nextChangePreferences = { ...preferences };
            sessionStorage.removeItem(accountScopedKey('logout'));
            return;
        }

        state.nextChangePreferences = null;
        if (scope === 'logout') {
            sessionStorage.setItem(accountScopedKey('logout'), JSON.stringify(preferences));
            return;
        }

        sessionStorage.removeItem(accountScopedKey('logout'));
    }

    function applyBranding(profile) {
        if (!profile) {
            return;
        }

        if (profile.accent) {
            app.style.setProperty('--v24-accent', profile.accent);
            app.style.setProperty('--v24-accent-strong', profile.accent);
        }

        if (profile.surface) {
            app.style.setProperty('--v24-accent-soft', profile.surface);
        }

        const appTitle = region('app-brand-title');
        const appSubtitle = region('app-brand-subtitle');
        const brand = region('ai-brand');

        if (appTitle) {
            appTitle.textContent = profile.display_name || 'SmartMail Hub';
        }
        if (appSubtitle) {
            appSubtitle.textContent = profile.product_name || 'Valore 24 AI Office';
        }
        document.title = `${profile.display_name || 'SmartMail Hub'} | ${profile.product_name || 'Valore 24 AI Office'}`;

        if (brand) {
            brand.innerHTML = `
                <strong>${escapeHtml(profile.display_name || 'Secure E-mail AI')}</strong>
                <span>${escapeHtml(profile.product_name || 'Valore 24 AI Office')}</span>
                <em>${escapeHtml(profile.badge || 'Workspace AI')}</em>
            `;
        }
    }

    function fillSelect(target, items, preferredValue) {
        if (!target) {
            return;
        }

        const list = Array.isArray(items) ? items : [];
        target.innerHTML = list.map((item) => {
            const value = String(item.value ?? '');
            const label = String(item.label ?? value);
            const description = item.description ? ` title="${escapeHtml(item.description)}"` : '';
            const selected = String(preferredValue ?? '') === value ? ' selected' : '';
            return `<option value="${escapeHtml(value)}"${description}${selected}>${escapeHtml(label)}</option>`;
        }).join('');
    }

    function applyPreferences(preferences) {
        const merged = normalizePreferences(preferences || {});
        state.preferences = merged;

        if (aiField('language')) {
            aiField('language').value = merged.language;
        }
        if (aiField('tone')) {
            aiField('tone').value = merged.tone;
        }
        if (aiField('writing')) {
            aiField('writing').value = merged.writing;
        }
        if (aiField('format')) {
            aiField('format').value = merged.format;
        }
    }

    function setScopeValue(scope) {
        const selected = app.querySelector(`[data-ai-field="scope"][value="${scope}"]`);
        if (selected) {
            selected.checked = true;
        }
    }

    function renderContext(context) {
        state.context = context || null;
        const target = region('ai-context');
        if (!target) {
            return;
        }

        if (!context || !context.original_body_text) {
            target.innerHTML = '<p>Nessun contesto reply o draft disponibile.</p>';
        } else {
            target.innerHTML = `
                <strong>${escapeHtml(context.original_subject || 'Messaggio originale')}</strong>
                <p>${escapeHtml((context.original_body_text || '').slice(0, 900))}</p>
            `;
        }

        if (aiField('prompt')) {
            aiField('prompt').placeholder = context && context.placeholder
                ? context.placeholder
                : 'Descrivi la mail che vuoi scrivere';
        }
    }

    function updateSelectionMeta() {
        const target = region('ai-selection-meta');
        const response = selectedResponse();
        if (!target) {
            return;
        }

        if (!response) {
            target.textContent = 'Nessuna risposta selezionata.';
            return;
        }

        const metrics = response.metrics || {};
        const modeLabel = {
            generate: 'Generata',
            improve: 'Migliorata',
            translate: 'Tradotta'
        }[response.response_mode] || 'Risposta';

        target.textContent = `${modeLabel} | token: ${metrics.usage_token || '-'} | parole: ${metrics.words || '-'}`;
        if (aiField('delivery-subject') && !aiField('delivery-subject').value) {
            aiField('delivery-subject').value = response.generated_subject || response.prompt_text || '';
        }
    }

    function responseCardMarkup(response) {
        const selected = Number(response.id) === Number(state.selectedResponseId);
        const ignored = response.is_ignored;
        const metrics = response.metrics || {};
        const rating = Number(response.rating_value || 0);
        const stars = [1, 2, 3, 4, 5].map((value) => {
            const filled = value <= rating ? 'is-active' : '';
            return `<button type="button" class="v24-smh-ai-star ${filled}" data-ai-action="rate-response" data-response-id="${response.id}" data-rating="${value}">${value}</button>`;
        }).join('');

        return `
            <article class="v24-smh-ai-card ${selected ? 'is-selected' : ''}" data-response-id="${response.id}">
                <header class="v24-smh-ai-card-head">
                    <button type="button" class="v24-smh-ai-card-title" data-ai-action="select-response" data-response-id="${response.id}">
                        <strong>${escapeHtml(response.generated_subject || response.prompt_text || 'Risposta AI')}</strong>
                        <span>${escapeHtml(response.assistant_name || response.response_mode || 'AI')}</span>
                    </button>
                    <div class="v24-smh-ai-card-actions">
                        <button type="button" class="v24-smh-button" data-ai-action="use-response" data-response-id="${response.id}">Usa</button>
                        <button type="button" class="v24-smh-button" data-ai-action="toggle-ignore" data-response-id="${response.id}">
                            ${ignored ? 'Mostra' : 'Ignora'}
                        </button>
                        <button type="button" class="v24-smh-button" data-ai-action="edit-response" data-response-id="${response.id}">Modifica</button>
                        <button type="button" class="v24-smh-button" data-ai-action="save-response" data-response-id="${response.id}" hidden>Salva</button>
                    </div>
                </header>
                <div class="v24-smh-ai-card-body ${ignored ? 'is-ignored' : ''}">
                    ${ignored ? '<p>Risposta ignorata come da istruzioni.</p>' : `<div class="v24-smh-ai-response-html" data-ai-response-body="${response.id}" contenteditable="false">${response.generated_html || ''}</div>`}
                </div>
                <footer class="v24-smh-ai-card-foot">
                    <div class="v24-smh-ai-stars">${stars}</div>
                    <div class="v24-smh-ai-metrics">
                        <span>Tempo: ${escapeHtml(metrics.tempo || '-')}</span>
                        <span>Caratteri: ${escapeHtml(metrics.characters || '-')}</span>
                        <span>Parole: ${escapeHtml(metrics.words || '-')}</span>
                    </div>
                </footer>
            </article>
        `;
    }

    function renderResponses() {
        const target = region('ai-responses');
        if (!target) {
            return;
        }

        if (!state.responses.length) {
            target.innerHTML = '<p>Le risposte AI appariranno qui con rating, ignore/show e modifica inline.</p>';
            updateSelectionMeta();
            return;
        }

        target.innerHTML = state.responses.map(responseCardMarkup).join('');
        updateSelectionMeta();
    }

    function upsertResponse(response) {
        const existingIndex = state.responses.findIndex((item) => Number(item.id) === Number(response.id));
        if (existingIndex >= 0) {
            state.responses.splice(existingIndex, 1, { ...state.responses[existingIndex], ...response });
        } else {
            state.responses.push(response);
        }

        state.selectedResponseId = response.id;
        state.chatId = response.chat_id || state.chatId;
        renderResponses();
    }

    function ensureSelected(actionLabel) {
        const response = selectedResponse();
        if (!response) {
            bridge.setStatus(`Seleziona prima una risposta AI per ${actionLabel}.`);
            return null;
        }
        return response;
    }

    function pickBootstrapPreferences(serverPrefs) {
        const sessionPrefs = readSessionPreferences();
        return normalizePreferences({
            ...serverPrefs,
            ...(sessionPrefs || {}),
            ...(state.nextChangePreferences || {})
        });
    }

    async function bootstrapCompose(forceNewChat) {
        const core = bridge.getState();
        const compose = region('compose');
        if (!compose || compose.hidden || !core.accountId) {
            return;
        }

        bridge.setStatus('Caricamento workspace AI...');
        const params = new URLSearchParams({
            account_id: String(core.accountId || 0),
            message_id: String((core.currentMessage && core.currentMessage.id) || 0),
            compose_mode: String(core.composeMode || 'new')
        });

        if (forceNewChat) {
            params.set('force_new_chat', '1');
        }

        const result = await bridge.api(`/ai/bootstrap?${params.toString()}`);
        const payload = result.data || {};
        state.configured = Boolean(payload.configured);
        state.tenant = payload.tenant || null;
        state.options = payload.options || {};
        state.chatId = payload.chat_id || '';
        state.responses = Array.isArray(payload.responses) ? payload.responses : [];
        state.selectedResponseId = state.responses.length ? state.responses[state.responses.length - 1].id : null;

        applyBranding(payload.tenant && payload.tenant.profile ? payload.tenant.profile : null);
        fillSelect(aiField('language'), state.options.languages, '');
        fillSelect(aiField('tone'), state.options.tones, '');
        fillSelect(aiField('writing'), state.options.writings, '');
        fillSelect(aiField('format'), state.options.formats, '');
        fillSelect(aiField('salutation'), state.options.salutations, '');
        fillSelect(aiField('signoff'), state.options.signoffs, '');
        fillSelect(aiField('assistant'), state.options.assistants, '');
        fillSelect(aiField('improve-assistant'), state.options.assistants, '');
        fillSelect(aiField('translate-language'), state.options.languages, '');

        applyPreferences(pickBootstrapPreferences(payload.preferences || DEFAULT_PREFERENCES));
        renderContext(payload.context || null);
        renderResponses();

        if (forceNewChat && aiField('prompt')) {
            aiField('prompt').value = '';
        }

        bridge.setStatus(state.configured
            ? 'Workspace AI pronto.'
            : 'Workspace AI caricato. Configura token ed endpoint per attivare le chiamate AI.');
    }

    function actionPayloadBase(response) {
        return {
            response_id: response.id,
            chat_id: response.chat_id || state.chatId,
            output_type: aiField('export-output') ? aiField('export-output').value : 'verticale',
            remove_questions: !!(aiField('export-remove-questions') && aiField('export-remove-questions').checked),
            remove_background: !!(aiField('export-remove-background') && aiField('export-remove-background').checked)
        };
    }

    async function generateResponse() {
        const core = bridge.getState();
        const payload = {
            account_id: core.accountId,
            message_id: core.currentMessage ? core.currentMessage.id : 0,
            compose_mode: core.composeMode,
            chat_id: state.chatId,
            prompt: aiField('prompt') ? aiField('prompt').value.trim() : '',
            assistant_id: aiField('assistant') ? aiField('assistant').value : '',
            salutation: aiField('salutation') ? aiField('salutation').value : '',
            signoff: aiField('signoff') ? aiField('signoff').value : '',
            privacy: !!(aiField('privacy') && aiField('privacy').checked),
            concise: !!(aiField('concise') && aiField('concise').checked),
            ...collectPreferences()
        };

        bridge.setStatus('Generazione risposta AI...');
        const result = await postJson('/ai/generate', payload);
        upsertResponse(result.data);
        if (aiField('delivery-subject')) {
            aiField('delivery-subject').value = result.data.generated_subject || '';
        }
        if (aiField('prompt')) {
            aiField('prompt').value = '';
        }
        bridge.setStatus('Risposta AI generata.');
    }

    async function improveSelectedResponse() {
        const response = ensureSelected('migliorare');
        if (!response) {
            return;
        }

        const payload = {
            response_id: response.id,
            chat_id: response.chat_id || state.chatId,
            instructions: aiField('improve-instructions') ? aiField('improve-instructions').value.trim() : '',
            repeat: aiField('improve-repeat') ? aiField('improve-repeat').value : '1',
            assistant_id: aiField('improve-assistant') ? aiField('improve-assistant').value : '',
            advanced_model: !!(aiField('improve-advanced') && aiField('improve-advanced').checked),
            internet: !!(aiField('improve-internet') && aiField('improve-internet').checked),
            ...collectPreferences()
        };

        bridge.setStatus(`Miglioria AI ${payload.repeat}x in corso...`);
        const result = await postJson('/ai/improve', payload);
        upsertResponse(result.data);
        bridge.setStatus('Miglioria completata.');
    }

    async function translateSelectedResponse() {
        const response = ensureSelected('tradurre');
        if (!response) {
            return;
        }

        const payload = {
            response_id: response.id,
            chat_id: response.chat_id || state.chatId,
            target_language: aiField('translate-language') ? aiField('translate-language').value : '',
            scope: aiField('translate-scope') ? aiField('translate-scope').value : 'selected',
            remove_questions: !!(aiField('translate-remove-questions') && aiField('translate-remove-questions').checked)
        };

        bridge.setStatus('Traduzione risposta AI...');
        const result = await postJson('/ai/translate', payload);
        upsertResponse(result.data);
        bridge.setStatus('Traduzione completata.');
    }

    async function exportSelectedResponse(doctype) {
        const response = ensureSelected('esportare');
        if (!response) {
            return;
        }

        bridge.setStatus(`Export ${doctype.toUpperCase()} in corso...`);
        const result = await postJson('/ai/export', {
            ...actionPayloadBase(response),
            doctype
        });

        const payload = result.data || {};
        if (doctype === 'print') {
            const printWindow = window.open('', '_blank', 'width=960,height=720');
            if (printWindow) {
                printWindow.document.write(payload.data || '');
                printWindow.document.close();
                printWindow.focus();
                printWindow.print();
            }
        } else if (payload.data) {
            window.open(payload.data, '_blank', 'noopener');
        }

        bridge.setStatus(`Export ${doctype.toUpperCase()} pronto.`);
    }

    async function previewSelectedEmail() {
        const response = ensureSelected('visualizzare l anteprima email');
        if (!response) {
            return;
        }

        bridge.setStatus('Generazione anteprima email...');
        const result = await postJson('/ai/preview-email', {
            ...actionPayloadBase(response),
            attach_type: aiField('delivery-attach-type') ? aiField('delivery-attach-type').value : 'comeallegato'
        });

        const payload = result.data || {};
        const preview = region('ai-email-preview');
        const body = region('ai-email-preview-body');
        const download = region('ai-email-preview-download');
        if (preview && body) {
            preview.hidden = false;
            body.innerHTML = payload.html || '';
        }
        if (download) {
            if (payload.download_url) {
                download.hidden = false;
                download.href = payload.download_url;
                download.target = '_blank';
                download.rel = 'noopener';
            } else {
                download.hidden = true;
                download.removeAttribute('href');
            }
        }

        bridge.setStatus('Anteprima email pronta.');
    }

    async function sendSelectedEmail() {
        const response = ensureSelected('inviare il risultato');
        if (!response) {
            return;
        }

        const subject = aiField('delivery-subject') ? aiField('delivery-subject').value.trim() : '';
        bridge.setStatus('Invio risultato AI in corso...');
        await postJson('/ai/send-email', {
            ...actionPayloadBase(response),
            attach_type: aiField('delivery-attach-type') ? aiField('delivery-attach-type').value : 'comeallegato',
            subject,
            email: aiField('delivery-email') ? aiField('delivery-email').value.trim() : ''
        });
        bridge.setStatus('Risultato AI inviato.');
    }

    async function playSelectedAudio() {
        const response = ensureSelected('abilitare la lettura audio');
        if (!response) {
            return;
        }

        bridge.setStatus('Generazione audio in corso...');
        const result = await postJson('/ai/tts', { response_id: response.id });
        const audioUrl = (result.data && result.data.audio_url) || '';
        const wrapper = region('ai-audio-player');
        const element = region('ai-audio-element');
        if (wrapper && element && audioUrl) {
            element.src = audioUrl;
            wrapper.hidden = false;
            element.play().catch(() => null);
        }
        bridge.setStatus('Audio pronto.');
    }

    async function updateResponseRating(responseId, rating) {
        const response = state.responses.find((item) => Number(item.id) === Number(responseId));
        if (!response) {
            return;
        }

        let comment = '';
        if (rating <= 4) {
            if (bridge.openTextDialog) {
                comment = await bridge.openTextDialog({
                    title: 'Feedback risposta AI',
                    description: 'Commento opzionale per migliorare il risultato generato.',
                    label: 'Feedback',
                    value: response.rating_comment || '',
                    placeholder: 'Scrivi qui cosa vuoi migliorare',
                    confirmLabel: 'Salva',
                    cancelLabel: 'Annulla'
                }) || '';
            } else {
                comment = window.prompt('Feedback opzionale sulla risposta AI:', response.rating_comment || '') || '';
            }
        }

        await postJson(`/ai/responses/${responseId}/rate`, {
            rating,
            comment
        });

        upsertResponse({ ...response, rating_value: rating, rating_comment: comment });
        bridge.setStatus('Rating salvato.');
    }

    async function toggleIgnored(responseId) {
        const response = state.responses.find((item) => Number(item.id) === Number(responseId));
        if (!response) {
            return;
        }

        const result = await postJson(`/ai/responses/${responseId}/ignore`, {
            ignored: !response.is_ignored
        });

        upsertResponse({ ...response, is_ignored: !!(result.data && result.data.ignored) });
        bridge.setStatus(result.data && result.data.ignored ? 'Risposta ignorata.' : 'Risposta ripristinata.');
    }

    function setEditable(responseId, editable) {
        const body = app.querySelector(`[data-ai-response-body="${responseId}"]`);
        const editButton = app.querySelector(`[data-ai-action="edit-response"][data-response-id="${responseId}"]`);
        const saveButton = app.querySelector(`[data-ai-action="save-response"][data-response-id="${responseId}"]`);
        if (!body) {
            return;
        }

        body.contentEditable = editable ? 'true' : 'false';
        body.classList.toggle('is-editing', editable);
        if (editButton) {
            editButton.hidden = editable;
        }
        if (saveButton) {
            saveButton.hidden = !editable;
        }
        if (editable) {
            body.focus();
        }
    }

    async function saveEditedResponse(responseId) {
        const body = app.querySelector(`[data-ai-response-body="${responseId}"]`);
        const response = state.responses.find((item) => Number(item.id) === Number(responseId));
        if (!body || !response) {
            return;
        }

        const html = body.innerHTML;
        const result = await postJson(`/ai/responses/${responseId}/update`, { html });
        upsertResponse({
            ...response,
            generated_html: result.data.html,
            generated_text: result.data.text
        });
        setEditable(responseId, false);
        bridge.setStatus('Risposta aggiornata.');
    }

    function plainTextToHtml(text) {
        return String(text || '')
            .split(/\r?\n/)
            .map((line) => escapeHtml(line))
            .join('<br>');
    }

    function directDictationHtml() {
        const target = aiField('direct-dictation');
        return target ? target.innerHTML.trim() : '';
    }

    function setRecordingStatus(text) {
        const target = region('ai-recording-status');
        if (target) {
            target.textContent = text;
        }
    }

    function stopTimer() {
        if (state.timerId) {
            window.clearInterval(state.timerId);
            state.timerId = null;
        }
    }

    function startTimer() {
        stopTimer();
        state.timerStartedAt = Date.now();
        state.timerId = window.setInterval(() => {
            const elapsed = Math.max(0, Math.floor((Date.now() - state.timerStartedAt) / 1000));
            const minutes = String(Math.floor(elapsed / 60)).padStart(2, '0');
            const seconds = String(elapsed % 60).padStart(2, '0');
            setRecordingStatus(`Registrazione ${minutes}:${seconds}`);
        }, 1000);
    }

    async function startRecording(target) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            bridge.setStatus('Microfono non supportato da questo browser.');
            return;
        }

        if (state.mediaRecorder) {
            bridge.setStatus('Esiste gia una registrazione in corso.');
            return;
        }

        state.recordingTarget = target;
        state.audioChunks = [];
        state.mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        state.mediaRecorder = new MediaRecorder(state.mediaStream);
        state.mediaRecorder.addEventListener('dataavailable', (event) => {
            if (event.data && event.data.size > 0) {
                state.audioChunks.push(event.data);
            }
        });
        state.mediaRecorder.start();
        startTimer();
        setRecordingStatus('Registrazione avviata.');
    }

    async function stopRecording() {
        if (!state.mediaRecorder) {
            return;
        }

        const recorder = state.mediaRecorder;
        const stream = state.mediaStream;
        const target = state.recordingTarget;

        const blob = await new Promise((resolve, reject) => {
            recorder.addEventListener('stop', () => {
                resolve(new Blob(state.audioChunks, { type: recorder.mimeType || 'audio/webm' }));
            }, { once: true });
            recorder.addEventListener('error', (event) => reject(event.error || new Error('Registrazione non riuscita.')), { once: true });
            recorder.stop();
        });

        stopTimer();
        state.mediaRecorder = null;
        state.mediaStream = null;
        state.recordingTarget = '';
        state.audioChunks = [];
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
        }

        const core = bridge.getState();
        const formData = new FormData();
        formData.append('file', blob, 'dictation.webm');
        formData.append('account_id', String(core.accountId || 0));
        const response = await fetch(`${restRoot}/ai/stt`, {
            method: 'POST',
            headers: {
                'X-WP-Nonce': restNonce
            },
            body: formData
        });

        const json = await response.json();
        if (!response.ok) {
            throw new Error(json.message || 'Trascrizione non riuscita.');
        }

        const text = (json.data && json.data.text) || '';
        if (target === 'prompt' && aiField('prompt')) {
            aiField('prompt').value = text;
        } else if (target === 'direct' && aiField('direct-dictation')) {
            aiField('direct-dictation').innerHTML = plainTextToHtml(text);
        }

        setRecordingStatus('Trascrizione completata.');
        bridge.setStatus('Dettatura trascritta.');
    }

    function selectResponseById(responseId) {
        state.selectedResponseId = Number(responseId);
        renderResponses();
    }

    app.addEventListener('v24-smh-compose-opened', () => {
        bootstrapCompose(false).catch((error) => bridge.setStatus(error.message || 'Bootstrap AI non riuscito.'));
    });

    app.addEventListener('v24-smh-compose-closed', () => {
        stopTimer();
        const preview = region('ai-email-preview');
        const audio = region('ai-audio-player');
        if (preview) {
            preview.hidden = true;
        }
        if (audio) {
            audio.hidden = true;
        }
    });

    app.addEventListener('click', (event) => {
        const target = event.target.closest('[data-ai-action]');
        if (!target) {
            return;
        }

        const action = target.getAttribute('data-ai-action');
        const responseId = Number(target.getAttribute('data-response-id') || 0);

        Promise.resolve().then(async () => {
            if (action === 'focus-assistant') {
                aiField('prompt').focus();
            } else if (action === 'focus-dictation') {
                aiField('direct-dictation').focus();
            } else if (action === 'new-chat') {
                await bootstrapCompose(true);
            } else if (action === 'save-preferences') {
                const scope = currentScope();
                const preferences = collectPreferences();
                persistScope(scope, preferences);
                setScopeValue(scope);
                if (scope === 'new_mail') {
                    await postJson('/ai/preferences', { ...preferences, scope });
                }
                bridge.setStatus('Parametri di scrittura aggiornati.');
            } else if (action === 'restore-defaults') {
                applyPreferences(DEFAULT_PREFERENCES);
                setScopeValue('next_change');
                state.nextChangePreferences = { ...DEFAULT_PREFERENCES };
                bridge.setStatus('Parametri ripristinati.');
            } else if (action === 'generate') {
                await generateResponse();
            } else if (action === 'improve-selected') {
                await improveSelectedResponse();
            } else if (action === 'translate-selected') {
                await translateSelectedResponse();
            } else if (action === 'export-doc') {
                await exportSelectedResponse('doc');
            } else if (action === 'export-pdf') {
                await exportSelectedResponse('pdf');
            } else if (action === 'export-print') {
                await exportSelectedResponse('print');
            } else if (action === 'preview-selected-email') {
                await previewSelectedEmail();
            } else if (action === 'send-selected-email') {
                await sendSelectedEmail();
            } else if (action === 'tts-selected') {
                await playSelectedAudio();
            } else if (action === 'use-selected-in-compose' || action === 'use-response') {
                const response = action === 'use-selected-in-compose'
                    ? ensureSelected('riusare nel composer')
                    : state.responses.find((item) => Number(item.id) === responseId);
                if (response) {
                    bridge.insertIntoCompose(response.generated_html, response.generated_subject);
                    bridge.setStatus('Risposta inserita nel composer.');
                }
            } else if (action === 'dictation-to-compose') {
                const html = directDictationHtml();
                if (html) {
                    bridge.insertIntoCompose(html, '');
                    bridge.setStatus('Dettatura inserita nella mail.');
                }
            } else if (action === 'dictation-to-prompt') {
                if (aiField('prompt') && aiField('direct-dictation')) {
                    aiField('prompt').value = aiField('direct-dictation').innerText.trim();
                    bridge.setStatus('Dettatura spostata nel prompt AI.');
                }
            } else if (action === 'start-prompt-recording') {
                await startRecording('prompt');
            } else if (action === 'start-direct-recording') {
                await startRecording('direct');
            } else if (action === 'stop-recording') {
                await stopRecording();
            } else if (action === 'select-response') {
                selectResponseById(responseId);
            } else if (action === 'toggle-ignore') {
                await toggleIgnored(responseId);
            } else if (action === 'edit-response') {
                selectResponseById(responseId);
                setEditable(responseId, true);
            } else if (action === 'save-response') {
                await saveEditedResponse(responseId);
            } else if (action === 'rate-response') {
                await updateResponseRating(responseId, Number(target.getAttribute('data-rating') || 0));
            }
        }).catch((error) => {
            bridge.setStatus(error.message || 'Operazione AI non riuscita.');
            setRecordingStatus('Microfono pronto.');
        });
    });
})();
