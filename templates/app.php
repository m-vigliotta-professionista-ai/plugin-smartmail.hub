<div
    class="v24-smh-app"
    data-v24-smh-app
    data-theme="<?php echo esc_attr($theme_preference ?? 'dark'); ?>"
    data-theme-storage-key="<?php echo esc_attr($theme_storage_key ?? 'v24-smh-theme:guest'); ?>"
    data-current-user-id="<?php echo esc_attr(get_current_user_id()); ?>"
    data-rest-root="<?php echo esc_url(rest_url(V24_SMH_REST_Bootstrap::NAMESPACE)); ?>"
    data-rest-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>"
>
    <header class="v24-smh-header">
        <div class="v24-smh-brand-block" aria-label="Valore 24 AI Office">
            <strong class="v24-smh-brand-mark">VALORE<span>24</span><em>AI</em></strong>
            <div class="v24-smh-brand-rail">
                <span class="v24-smh-brand-submark">Office</span>
                <span class="v24-smh-brand-app-name" data-region="app-brand-title">SmartMail Hub</span>
                <span class="v24-smh-visually-hidden" data-region="app-brand-subtitle">Valore 24 AI Office</span>
            </div>
        </div>
        <nav class="v24-smh-module-nav">
            <button type="button" class="v24-smh-module-tab is-active" data-action="switch-module" data-module="mail">Posta</button>
            <button type="button" class="v24-smh-module-tab" data-action="switch-module" data-module="calendar">Calendario</button>
            <button type="button" class="v24-smh-module-tab" data-action="switch-module" data-module="contacts">Contatti</button>
            <button type="button" class="v24-smh-module-tab" data-action="switch-module" data-module="tasks">Task</button>
            <button type="button" class="v24-smh-module-tab" data-action="switch-module" data-module="rules">Regole</button>
        </nav>
        <div class="v24-smh-header-actions">
            <button
                type="button"
                class="v24-smh-button v24-smh-theme-toggle"
                data-action="toggle-theme"
                data-theme-toggle
                data-theme="<?php echo esc_attr(($theme_preference ?? 'dark') === 'light' ? 'light' : 'dark'); ?>"
                aria-label="<?php echo esc_attr(($theme_preference ?? 'dark') === 'light' ? 'Attiva tema scuro' : 'Attiva tema chiaro'); ?>"
                aria-pressed="<?php echo esc_attr(($theme_preference ?? 'dark') === 'light' ? 'true' : 'false'); ?>"
            >
                <span class="v24-smh-theme-toggle-icons" aria-hidden="true">
                    <span class="v24-smh-theme-icon v24-smh-theme-icon-sun">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="4.25"></circle>
                            <path d="M12 2.5v2.5M12 19v2.5M4.93 4.93l1.77 1.77M17.3 17.3l1.77 1.77M2.5 12H5M19 12h2.5M4.93 19.07l1.77-1.77M17.3 6.7l1.77-1.77"></path>
                        </svg>
                    </span>
                    <span class="v24-smh-theme-icon v24-smh-theme-icon-moon">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M20.2 14.58A8.5 8.5 0 0 1 9.42 3.8a8.5 8.5 0 1 0 10.78 10.78Z"></path>
                        </svg>
                    </span>
                </span>
                <span class="v24-smh-theme-toggle-label" data-region="theme-label"><?php echo esc_html(($theme_preference ?? 'dark') === 'light' ? 'Tema chiaro' : 'Tema scuro'); ?></span>
            </button>
            <div class="v24-smh-header-meta">
                <span class="v24-smh-user">Utente: <?php echo esc_html(wp_get_current_user()->display_name ?: wp_get_current_user()->user_login); ?></span>
                <span class="v24-smh-status" data-region="status">Pronto.</span>
            </div>
            <a class="v24-smh-button" id="esci-button" href="<?php echo esc_url(wp_logout_url(get_permalink() ?: home_url('/'))); ?>">Esci</a>
        </div>
    </header>

    <section class="v24-smh-module" data-module-panel="mail">
        <div class="v24-smh-module-toolbar">
            <button type="button" class="v24-smh-button" data-action="sync">Sincronizza</button>
            <button type="button" class="v24-smh-button v24-smh-button-primary" data-action="compose">Nuova email</button>
        </div>
        <main class="v24-smh-shell">
            <aside class="v24-smh-sidebar">
                <h2>Account</h2>
                <div data-region="accounts"></div>
                <h2>Cartelle</h2>
                <div data-region="folders"></div>
            </aside>
            <section class="v24-smh-list v24-smh-mail-list">
                <div class="v24-smh-list-header">
                    <div class="v24-smh-toolbar-heading">
                        <strong>Messaggi</strong>
                        <span data-region="messages-caption">Seleziona una cartella</span>
                    </div>
                    <span class="v24-smh-list-counter" data-region="messages-count">0</span>
                </div>
                <div class="v24-smh-toolbar v24-smh-mail-toolbar">
                    <input type="search" data-region="search" placeholder="Cerca messaggi">
                    <button type="button" data-action="search">Cerca</button>
                </div>
                <div data-region="messages"></div>
            </section>
            <section class="v24-smh-reader">
                <div data-region="reader">
                    <p>Seleziona un messaggio per leggere il contenuto e gestire allegati, risposta e inoltro.</p>
                </div>
            </section>
        </main>
        <div class="v24-smh-compose" data-region="compose" hidden>
            <form data-form="compose">
                <div class="v24-smh-compose-header">
                    <h2 data-region="compose-title">Nuova email</h2>
                    <button type="button" class="v24-smh-link" data-action="close-compose">Chiudi</button>
                </div>
                <div class="v24-smh-compose-layout">
                    <section class="v24-smh-compose-main">
                        <div class="v24-smh-field" data-compose-field="to">
                            <input type="text" name="to" data-field="to" placeholder="A">
                        </div>
                        <div class="v24-smh-field" data-compose-field="cc">
                            <input type="text" name="cc" data-field="cc" placeholder="CC">
                        </div>
                        <div class="v24-smh-field" data-compose-field="bcc">
                            <input type="text" name="bcc" data-field="bcc" placeholder="BCC">
                        </div>
                        <div class="v24-smh-field" data-compose-field="subject">
                            <input name="subject" data-field="subject" type="text" placeholder="Oggetto">
                        </div>
                        <div class="v24-smh-compose-toolbar">
                            <button type="button" class="v24-smh-button" data-ai-action="focus-assistant">Assistente AI</button>
                            <button type="button" class="v24-smh-button" data-ai-action="focus-dictation">Detta risposta</button>
                            <span class="v24-smh-compose-toolbar-hint">Inserimento intelligente sopra firma, reply e draft.</span>
                        </div>
                        <div class="v24-smh-compose-context" data-region="compose-context" hidden></div>
                        <div class="v24-smh-field" data-compose-field="body">
                            <div class="v24-smh-compose-editor" data-field="body-editor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Corpo del messaggio"></div>
                            <textarea name="body" data-field="body" rows="10" hidden></textarea>
                        </div>
                        <div class="v24-smh-actions">
                            <button type="submit" class="v24-smh-button-primary">Invia</button>
                            <button type="button" data-action="close-compose">Annulla</button>
                        </div>
                    </section>
                    <aside class="v24-smh-ai-dock" data-region="ai-dock">
                        <div class="v24-smh-ai-brand" data-region="ai-brand">
                            <strong>Secure E-mail AI</strong>
                            <span>Workspace di risposta, dettatura, export e preview.</span>
                        </div>

                        <section class="v24-smh-ai-section">
                            <div class="v24-smh-ai-section-head">
                                <h3>Assistente AI</h3>
                                <div class="v24-smh-ai-inline-actions">
                                    <button type="button" class="v24-smh-button" data-ai-action="new-chat">Nuova chat</button>
                                    <button type="button" class="v24-smh-button v24-smh-button-primary" data-ai-action="generate">Genera</button>
                                </div>
                            </div>
                            <label>
                                <span>Prompt</span>
                                <div class="v24-smh-ai-prompt-wrap">
                                    <textarea rows="4" data-ai-field="prompt" placeholder="Descrivi la mail che vuoi scrivere"></textarea>
                                    <div class="v24-smh-ai-inline-actions">
                                        <button type="button" class="v24-smh-button" data-ai-action="start-prompt-recording">Detta prompt</button>
                                        <button type="button" class="v24-smh-button" data-ai-action="stop-recording">Ferma</button>
                                    </div>
                                </div>
                            </label>
                            <div class="v24-smh-form-grid v24-smh-ai-grid">
                                <label>
                                    <span>Lingua</span>
                                    <select data-ai-field="language"></select>
                                </label>
                                <label>
                                    <span>Tono</span>
                                    <select data-ai-field="tone"></select>
                                </label>
                                <label>
                                    <span>Stile</span>
                                    <select data-ai-field="writing"></select>
                                </label>
                                <label>
                                    <span>Formato</span>
                                    <select data-ai-field="format"></select>
                                </label>
                                <label>
                                    <span>Come mi rivolgo</span>
                                    <select data-ai-field="salutation"></select>
                                </label>
                                <label>
                                    <span>Come mi firmo</span>
                                    <select data-ai-field="signoff"></select>
                                </label>
                                <label class="v24-smh-ai-grid-wide">
                                    <span>Assistente specialistico</span>
                                    <select data-ai-field="assistant"></select>
                                </label>
                            </div>
                            <div class="v24-smh-pill-list v24-smh-ai-prefs">
                                <label class="v24-smh-checkbox">
                                    <input type="checkbox" value="1" data-ai-field="privacy">
                                    <span>Privacy rafforzata</span>
                                </label>
                                <label class="v24-smh-checkbox">
                                    <input type="checkbox" value="1" data-ai-field="concise">
                                    <span>Testo sintetico</span>
                                </label>
                            </div>
                            <div class="v24-smh-ai-scope">
                                <span>Persistenza parametri</span>
                                <label><input type="radio" name="v24-smh-ai-scope" data-ai-field="scope" value="next_change" checked> Prossima modifica</label>
                                <label><input type="radio" name="v24-smh-ai-scope" data-ai-field="scope" value="new_mail"> Nuova mail</label>
                                <label><input type="radio" name="v24-smh-ai-scope" data-ai-field="scope" value="logout"> Logout</label>
                            </div>
                            <div class="v24-smh-ai-inline-actions">
                                <button type="button" class="v24-smh-button" data-ai-action="save-preferences">Salva parametri</button>
                                <button type="button" class="v24-smh-button" data-ai-action="restore-defaults">Default</button>
                            </div>
                        </section>

                        <section class="v24-smh-ai-section">
                            <div class="v24-smh-ai-section-head">
                                <h3>Contesto reply/draft</h3>
                            </div>
                            <div class="v24-smh-ai-context" data-region="ai-context">
                                <p>Apri una risposta per usare il corpo della mail originale come contesto automatico.</p>
                            </div>
                        </section>

                        <section class="v24-smh-ai-section">
                            <div class="v24-smh-ai-section-head">
                                <h3>Dettatura diretta</h3>
                                <div class="v24-smh-ai-inline-actions">
                                    <button type="button" class="v24-smh-button" data-ai-action="start-direct-recording">Registra</button>
                                    <button type="button" class="v24-smh-button" data-ai-action="stop-recording">Ferma</button>
                                </div>
                            </div>
                            <div class="v24-smh-ai-direct-editor" data-ai-field="direct-dictation" contenteditable="true" role="textbox" aria-multiline="true"></div>
                            <p class="v24-smh-ai-note">Migliora risposta e parametri scrittura si gestiscono dal pannello Assistente AI, come nel flusso originale.</p>
                            <div class="v24-smh-ai-inline-actions">
                                <button type="button" class="v24-smh-button" data-ai-action="dictation-to-compose">Usa in mail</button>
                                <button type="button" class="v24-smh-button" data-ai-action="dictation-to-prompt">Usa come prompt</button>
                            </div>
                            <span class="v24-smh-ai-rec-status" data-region="ai-recording-status">Microfono pronto.</span>
                        </section>

                        <section class="v24-smh-ai-section">
                            <div class="v24-smh-ai-section-head">
                                <h3>Workspace risposte</h3>
                                <span class="v24-smh-ai-selection" data-region="ai-selection-meta">Nessuna risposta selezionata.</span>
                            </div>
                            <div class="v24-smh-ai-responses" data-region="ai-responses">
                                <p>Le risposte AI appariranno qui con rating, ignore/show, modifica inline e riuso nel composer.</p>
                            </div>
                        </section>

                        <section class="v24-smh-ai-section">
                            <div class="v24-smh-ai-section-head">
                                <h3>Migliora risposta</h3>
                            </div>
                            <label>
                                <span>Istruzioni libere</span>
                                <textarea rows="3" data-ai-field="improve-instructions" placeholder="Opzionale: indica come migliorare la risposta selezionata"></textarea>
                            </label>
                            <div class="v24-smh-form-grid v24-smh-ai-grid">
                                <label>
                                    <span>Passaggi</span>
                                    <select data-ai-field="improve-repeat">
                                        <option value="1">Standard 1x</option>
                                        <option value="3">Avanzato 3x</option>
                                        <option value="6">Massimo 6x</option>
                                        <option value="10">Estremo 10x</option>
                                    </select>
                                </label>
                                <label>
                                    <span>Assistente per miglioria</span>
                                    <select data-ai-field="improve-assistant"></select>
                                </label>
                            </div>
                            <div class="v24-smh-pill-list v24-smh-ai-prefs">
                                <label class="v24-smh-checkbox">
                                    <input type="checkbox" value="1" data-ai-field="improve-advanced">
                                    <span>Modello AI avanzato</span>
                                </label>
                                <label class="v24-smh-checkbox">
                                    <input type="checkbox" value="1" data-ai-field="improve-internet">
                                    <span>Ricerca internet</span>
                                </label>
                            </div>
                            <button type="button" class="v24-smh-button v24-smh-button-primary" data-ai-action="improve-selected">Esegui miglioria</button>
                        </section>

                        <section class="v24-smh-ai-section">
                            <div class="v24-smh-ai-section-head">
                                <h3>Traduzione</h3>
                            </div>
                            <div class="v24-smh-form-grid v24-smh-ai-grid">
                                <label>
                                    <span>Lingua target</span>
                                    <select data-ai-field="translate-language"></select>
                                </label>
                                <label>
                                    <span>Ambito</span>
                                    <select data-ai-field="translate-scope">
                                        <option value="selected">Risposta selezionata</option>
                                        <option value="last">Ultima risposta della chat</option>
                                        <option value="all">Tutta la chat</option>
                                    </select>
                                </label>
                            </div>
                            <label class="v24-smh-checkbox">
                                <input type="checkbox" value="1" data-ai-field="translate-remove-questions">
                                <span>Rimuovi domande</span>
                            </label>
                            <button type="button" class="v24-smh-button" data-ai-action="translate-selected">Traduci</button>
                        </section>

                        <section class="v24-smh-ai-section">
                            <div class="v24-smh-ai-section-head">
                                <h3>Export, preview e invio</h3>
                            </div>
                            <div class="v24-smh-form-grid v24-smh-ai-grid">
                                <label>
                                    <span>Output</span>
                                    <select data-ai-field="export-output">
                                        <option value="verticale">Verticale</option>
                                        <option value="orizzontale">Orizzontale</option>
                                    </select>
                                </label>
                                <label>
                                    <span>Invia come</span>
                                    <select data-ai-field="delivery-attach-type">
                                        <option value="comeallegato">Testo in mail</option>
                                        <option value="comeallegato1">Come allegato</option>
                                        <option value="1">Allegato DOC</option>
                                        <option value="2">Allegato PDF</option>
                                    </select>
                                </label>
                            </div>
                            <div class="v24-smh-pill-list v24-smh-ai-prefs">
                                <label class="v24-smh-checkbox">
                                    <input type="checkbox" value="1" data-ai-field="export-remove-questions">
                                    <span>Rimuovi domande</span>
                                </label>
                                <label class="v24-smh-checkbox">
                                    <input type="checkbox" value="1" data-ai-field="export-remove-background">
                                    <span>Rimuovi background</span>
                                </label>
                            </div>
                            <div class="v24-smh-ai-inline-actions">
                                <button type="button" class="v24-smh-button" data-ai-action="export-doc">DOC</button>
                                <button type="button" class="v24-smh-button" data-ai-action="export-pdf">PDF</button>
                                <button type="button" class="v24-smh-button" data-ai-action="export-print">Stampa</button>
                                <button type="button" class="v24-smh-button" data-ai-action="tts-selected">Audio</button>
                                <button type="button" class="v24-smh-button" data-ai-action="use-selected-in-compose">Usa in mail</button>
                            </div>
                            <label>
                                <span>Oggetto invio risultato</span>
                                <input type="text" data-ai-field="delivery-subject" placeholder="Oggetto della mail di invio risultato">
                            </label>
                            <label>
                                <span>Email destinatario preview/invio</span>
                                <input type="email" data-ai-field="delivery-email" placeholder="Lascia vuoto per inviare a te stesso">
                            </label>
                            <div class="v24-smh-ai-inline-actions">
                                <button type="button" class="v24-smh-button" data-ai-action="preview-selected-email">Preview email</button>
                                <button type="button" class="v24-smh-button v24-smh-button-primary" data-ai-action="send-selected-email">Invia risultato</button>
                            </div>
                            <div class="v24-smh-ai-preview" data-region="ai-email-preview" hidden>
                                <div class="v24-smh-ai-preview-body" data-region="ai-email-preview-body"></div>
                                <div class="v24-smh-ai-inline-actions">
                                    <a href="#" data-region="ai-email-preview-download" hidden>Visualizza allegato</a>
                                </div>
                            </div>
                            <div class="v24-smh-ai-audio" data-region="ai-audio-player" hidden>
                                <audio controls data-region="ai-audio-element"></audio>
                            </div>
                        </section>
                    </aside>
                </div>
            </form>
        </div>
    </section>

    <section class="v24-smh-module" data-module-panel="calendar" hidden>
        <main class="v24-smh-calendar-shell">
            <aside class="v24-smh-calendar-sidebar">
                <div class="v24-smh-calendar-sidebar-head">
                    <h2>Calendari</h2>
                    <button type="button" class="v24-smh-button" data-action="new-calendar">Nuovo</button>
                </div>
                <div class="v24-smh-calendar-search">
                    <input type="search" data-region="calendar-filter" placeholder="Trova calendari...">
                </div>
                <div data-region="calendar-list"></div>
                <div class="v24-smh-card-stack" data-region="calendar-manager">
                    <p>Seleziona un calendario per vedere condivisioni e permessi.</p>
                </div>
                <div class="v24-smh-calendar-mini-card">
                    <div class="v24-smh-calendar-mini-head">
                        <strong data-region="calendar-mini-label">Mese corrente</strong>
                    </div>
                    <div data-region="calendar-mini-month"></div>
                </div>
            </aside>
            <section class="v24-smh-calendar-main">
                <div class="v24-smh-calendar-topbar">
                    <div class="v24-smh-calendar-topbar-left">
                        <div class="v24-smh-calendar-search v24-smh-calendar-search-events">
                            <input type="search" data-region="calendar-search" placeholder="Cerca eventi...">
                        </div>
                        <div class="v24-smh-calendar-view-switch">
                            <button type="button" class="v24-smh-calendar-view-button" data-action="switch-calendar-view" data-view="day">Giorno</button>
                            <button type="button" class="v24-smh-calendar-view-button is-active" data-action="switch-calendar-view" data-view="week">Settimana</button>
                            <button type="button" class="v24-smh-calendar-view-button" data-action="switch-calendar-view" data-view="month">Mese</button>
                            <button type="button" class="v24-smh-calendar-view-button" data-action="switch-calendar-view" data-view="agenda">Agenda</button>
                        </div>
                    </div>
                    <div class="v24-smh-calendar-topbar-right">
                        <button type="button" class="v24-smh-button" data-action="calendar-prev">Prec</button>
                        <button type="button" class="v24-smh-button" data-action="calendar-today">Oggi</button>
                        <button type="button" class="v24-smh-button" data-action="calendar-next">Succ</button>
                        <button type="button" class="v24-smh-button" data-action="load-calendar">Aggiorna</button>
                        <button type="button" class="v24-smh-button v24-smh-button-primary" data-action="new-event">Nuovo evento</button>
                    </div>
                </div>
                <div class="v24-smh-calendar-range-bar">
                    <div class="v24-smh-calendar-range-copy">
                        <h2 data-region="calendar-period-label">Calendario</h2>
                        <span data-region="calendar-period-subtitle"><?php echo esc_html(wp_timezone_string() ?: 'Europe/Rome'); ?></span>
                    </div>
                </div>
                <div class="v24-smh-calendar-hidden-range" hidden>
                    <input type="date" data-region="calendar-start">
                    <input type="date" data-region="calendar-end">
                </div>
                <div class="v24-smh-calendar-board">
                    <div data-region="calendar-grid"></div>
                    <div class="v24-smh-calendar-agenda" data-region="events" hidden></div>
                </div>
                <section class="v24-smh-reader v24-smh-calendar-detail">
                    <div data-region="event-reader">
                        <p>Seleziona un evento per vedere dettagli, partecipanti e ricorrenze.</p>
                    </div>
                </section>
            </section>
        </main>
        <div class="v24-smh-sheet" data-region="event-form-sheet" hidden>
            <form data-form="event">
                <div class="v24-smh-compose-header">
                    <div>
                        <h2 data-region="event-form-title">Nuovo evento</h2>
                        <p data-region="event-form-scope">Salvataggio sul singolo evento o sull intera serie.</p>
                    </div>
                    <button type="button" class="v24-smh-link" data-action="close-event-form">Chiudi</button>
                </div>
                <div class="v24-smh-form-grid">
                    <label>
                        <span>Calendario</span>
                        <select name="calendar_id" data-field="event-calendar"></select>
                    </label>
                    <label>
                        <span>Titolo</span>
                        <input type="text" name="title" data-field="event-title" required>
                    </label>
                    <label>
                        <span>Luogo</span>
                        <input type="text" name="location" data-field="event-location">
                    </label>
                    <label>
                        <span>Inizio</span>
                        <input type="datetime-local" name="start_at" data-field="event-start" required>
                    </label>
                    <label>
                        <span>Fine</span>
                        <input type="datetime-local" name="end_at" data-field="event-end" required>
                    </label>
                    <label>
                        <span>Timezone</span>
                        <input type="text" name="timezone" data-field="event-timezone" value="<?php echo esc_attr(wp_timezone_string() ?: 'Europe/Rome'); ?>">
                    </label>
                    <label>
                        <span>Stato</span>
                        <select name="status" data-field="event-status">
                            <option value="confirmed">Confermato</option>
                            <option value="tentative">Tentativo</option>
                            <option value="cancelled">Annullato</option>
                        </select>
                    </label>
                    <label>
                        <span>Disponibilita</span>
                        <select name="busy_status" data-field="event-busy-status">
                            <option value="busy">Occupato</option>
                            <option value="free">Libero</option>
                            <option value="tentative">Tentativo</option>
                            <option value="out_of_office">Fuori sede</option>
                        </select>
                    </label>
                    <label>
                        <span>Promemoria (minuti)</span>
                        <input type="number" min="0" step="5" name="reminder_minutes" data-field="event-reminder-minutes" placeholder="15">
                    </label>
                </div>
                <label class="v24-smh-checkbox">
                    <input type="checkbox" name="is_all_day" data-field="event-all-day" value="1">
                    <span>Tutto il giorno</span>
                </label>
                <label>
                    <span>Partecipanti (email o Nome &lt;email&gt; separati da virgola)</span>
                    <input type="text" name="attendees_csv" data-field="event-attendees" placeholder="Mario Rossi &lt;mario@azienda.it&gt;, team@azienda.it">
                </label>
                <label>
                    <span>Categorie (separate da virgola)</span>
                    <input type="text" name="categories_csv" data-field="event-categories" placeholder="Commerciale, Demo, Follow-up">
                </label>
                <div class="v24-smh-card-stack">
                    <h3>Ricorrenza</h3>
                    <div class="v24-smh-form-grid">
                        <label>
                            <span>Frequenza</span>
                            <select name="recurrence_frequency" data-field="event-recurrence-frequency">
                                <option value="none">Nessuna</option>
                                <option value="daily">Giornaliera</option>
                                <option value="weekly">Settimanale</option>
                                <option value="monthly">Mensile</option>
                                <option value="yearly">Annuale</option>
                            </select>
                        </label>
                        <label>
                            <span>Ogni</span>
                            <input type="number" min="1" max="365" name="recurrence_interval" data-field="event-recurrence-interval" value="1">
                        </label>
                        <label>
                            <span>Giorni settimana</span>
                            <input type="text" name="recurrence_weekdays" data-field="event-recurrence-weekdays" placeholder="MO,TU,WE">
                        </label>
                        <label>
                            <span>Modalita mensile</span>
                            <select name="recurrence_monthly_mode" data-field="event-recurrence-monthly-mode">
                                <option value="day_of_month">Giorno del mese</option>
                                <option value="nth_weekday">N-esimo giorno settimana</option>
                            </select>
                        </label>
                        <label>
                            <span>Giorno del mese</span>
                            <input type="number" min="1" max="31" name="recurrence_month_day" data-field="event-recurrence-month-day" value="1">
                        </label>
                        <label>
                            <span>Settimana N</span>
                            <select name="recurrence_nth_week" data-field="event-recurrence-nth-week">
                                <option value="1">Prima</option>
                                <option value="2">Seconda</option>
                                <option value="3">Terza</option>
                                <option value="4">Quarta</option>
                                <option value="-1">Ultima</option>
                            </select>
                        </label>
                        <label>
                            <span>Giorno N</span>
                            <select name="recurrence_nth_weekday" data-field="event-recurrence-nth-weekday">
                                <option value="MO">Lunedi</option>
                                <option value="TU">Martedi</option>
                                <option value="WE">Mercoledi</option>
                                <option value="TH">Giovedi</option>
                                <option value="FR">Venerdi</option>
                                <option value="SA">Sabato</option>
                                <option value="SU">Domenica</option>
                            </select>
                        </label>
                        <label>
                            <span>Mesi annuali</span>
                            <input type="text" name="recurrence_months" data-field="event-recurrence-months" placeholder="1,6,12">
                        </label>
                        <label>
                            <span>Valida fino al</span>
                            <input type="datetime-local" name="recurrence_until" data-field="event-recurrence-until">
                        </label>
                        <label>
                            <span>Numero occorrenze</span>
                            <input type="number" min="1" max="999" name="recurrence_count" data-field="event-recurrence-count">
                        </label>
                    </div>
                </div>
                <label>
                    <span>Descrizione</span>
                    <textarea name="description" data-field="event-description" rows="8"></textarea>
                </label>
                <div class="v24-smh-actions">
                    <button type="submit" class="v24-smh-button-primary">Salva evento</button>
                    <button type="button" data-action="close-event-form">Annulla</button>
                </div>
            </form>
        </div>
        <div class="v24-smh-sheet" data-region="calendar-form-sheet" hidden>
            <form data-form="calendar">
                <div class="v24-smh-compose-header">
                    <h2 data-region="calendar-form-title">Nuovo calendario</h2>
                    <button type="button" class="v24-smh-link" data-action="close-calendar-form">Chiudi</button>
                </div>
                <div class="v24-smh-form-grid">
                    <label>
                        <span>Nome</span>
                        <input type="text" name="name" data-field="calendar-name" required>
                    </label>
                    <label>
                        <span>Colore</span>
                        <input type="color" name="color" data-field="calendar-color" value="#0d5c63">
                    </label>
                </div>
                <label>
                    <span>Descrizione</span>
                    <textarea name="description" data-field="calendar-description" rows="4"></textarea>
                </label>
                <div class="v24-smh-card-stack">
                    <h3>Condivisioni</h3>
                    <div data-region="calendar-share-options">
                        <p>I collaboratori compariranno qui quando il modulo carica gli utenti WordPress.</p>
                    </div>
                </div>
                <div class="v24-smh-actions">
                    <button type="submit" class="v24-smh-button-primary">Salva calendario</button>
                    <button type="button" data-action="close-calendar-form">Annulla</button>
                </div>
            </form>
        </div>
    </section>

    <section class="v24-smh-module" data-module-panel="contacts" hidden>
        <div class="v24-smh-module-toolbar v24-smh-module-toolbar-contacts">
            <div class="v24-smh-toolbar-heading">
                <strong>Contatti</strong>
                <span>Gestisci gruppi, schede e recapiti operativi.</span>
            </div>
            <div class="v24-smh-actions">
                <button type="button" class="v24-smh-button" data-action="search-contacts">Aggiorna elenco</button>
                <button type="button" class="v24-smh-button v24-smh-button-primary" data-action="new-contact">Nuovo contatto</button>
            </div>
        </div>
        <div class="v24-smh-contacts-shell">
            <aside class="v24-smh-contacts-groups">
                <div class="v24-smh-contacts-pane-header">
                    <h2>Gruppi</h2>
                </div>
                <label class="v24-smh-pane-search">
                    <span class="v24-smh-visually-hidden">Cerca gruppi</span>
                    <input type="search" data-region="contact-groups-search" placeholder="Trova gruppi...">
                </label>
                <div data-region="contact-groups">
                    <p>Caricamento gruppi...</p>
                </div>
            </aside>
            <section class="v24-smh-contacts-directory">
                <div class="v24-smh-contacts-pane-header">
                    <div>
                        <h2 data-region="contact-group-label">Contatti personali</h2>
                        <p>Vista elenco e filtro rapido dei contatti disponibili.</p>
                    </div>
                    <span class="v24-smh-contacts-counter" data-region="contacts-count">0 contatti</span>
                </div>
                <label class="v24-smh-pane-search">
                    <span class="v24-smh-visually-hidden">Cerca contatti</span>
                    <input type="search" data-region="contacts-search" placeholder="Cerca...">
                </label>
                <div class="v24-smh-contacts-scroll" data-region="contacts">
                    <p>Nessun contatto caricato.</p>
                </div>
            </section>
            <section class="v24-smh-contacts-detail">
                <div data-region="contact-reader-panel">
                    <div data-region="contact-reader">
                        <p>Seleziona un contatto per vedere dati, email, telefoni e note operative.</p>
                    </div>
                </div>
                <div class="v24-smh-contact-sheet" data-region="contact-form-sheet" hidden>
                    <form data-form="contact" class="v24-smh-contact-editor-form">
                        <div class="v24-smh-compose-header">
                            <h2 data-region="contact-form-title">Nuovo contatto</h2>
                            <button type="button" class="v24-smh-link" data-action="close-contact-form">Chiudi</button>
                        </div>
                        <div class="v24-smh-contact-editor-hero">
                            <div class="v24-smh-contact-avatar v24-smh-contact-avatar-xl v24-smh-contact-avatar-ghost">+</div>
                            <div class="v24-smh-contact-editor-summary">
                                <div class="v24-smh-form-grid">
                                    <label>
                                        <span>Nome visualizzato</span>
                                        <input type="text" name="display_name" data-field="contact-display-name">
                                    </label>
                                    <label>
                                        <span>Azienda</span>
                                        <input type="text" name="company" data-field="contact-company">
                                    </label>
                                    <label>
                                        <span>Nome</span>
                                        <input type="text" name="first_name" data-field="contact-first-name">
                                    </label>
                                    <label>
                                        <span>Cognome</span>
                                        <input type="text" name="last_name" data-field="contact-last-name">
                                    </label>
                                    <label>
                                        <span>Reparto</span>
                                        <input type="text" name="department" data-field="contact-department">
                                    </label>
                                    <label>
                                        <span>Ruolo</span>
                                        <input type="text" name="job_title" data-field="contact-job-title">
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="v24-smh-contact-tabbar">
                            <button type="button" class="is-active" aria-pressed="true">Proprieta</button>
                            <button type="button" tabindex="-1">Personale</button>
                            <button type="button" tabindex="-1">Note</button>
                        </div>
                        <div class="v24-smh-contact-editor-section">
                            <h3>E-Mail</h3>
                            <div class="v24-smh-form-grid">
                                <label>
                                    <span>Email principale</span>
                                    <input type="email" name="primary_email" data-field="contact-primary-email">
                                </label>
                                <label>
                                    <span>Email aggiuntive (separate da virgola)</span>
                                    <input type="text" name="emails_csv" data-field="contact-emails">
                                </label>
                                <label>
                                    <span>Sito web</span>
                                    <input type="url" name="website_url" data-field="contact-website-url" placeholder="https://azienda.it">
                                </label>
                                <label>
                                    <span>Categorie (separate da virgola)</span>
                                    <input type="text" name="categories_csv" data-field="contact-categories" placeholder="Cliente, VIP, Fornitore">
                                </label>
                            </div>
                        </div>
                        <div class="v24-smh-contact-editor-section">
                            <h3>Telefono</h3>
                            <div class="v24-smh-form-grid">
                                <label>
                                    <span>Cellulare</span>
                                    <input type="text" name="mobile_phone" data-field="contact-mobile-phone">
                                </label>
                                <label>
                                    <span>Telefono ufficio</span>
                                    <input type="text" name="business_phone" data-field="contact-business-phone">
                                </label>
                                <label class="v24-smh-field-span-2">
                                    <span>Telefoni aggiuntivi (separati da virgola)</span>
                                    <input type="text" name="phones_csv" data-field="contact-phones">
                                </label>
                            </div>
                        </div>
                        <div class="v24-smh-contact-editor-section">
                            <h3>Indirizzo</h3>
                            <label>
                                <span>Indirizzi / note logistiche (separati da virgola)</span>
                                <input type="text" name="addresses_csv" data-field="contact-addresses">
                            </label>
                        </div>
                        <div class="v24-smh-contact-editor-section">
                            <h3>Note</h3>
                            <label>
                                <span>Note operative</span>
                                <textarea name="notes" data-field="contact-notes" rows="8"></textarea>
                            </label>
                        </div>
                        <div class="v24-smh-actions">
                            <button type="submit" class="v24-smh-button-primary">Salva contatto</button>
                            <button type="button" data-action="close-contact-form">Annulla</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </section>

    <section class="v24-smh-module" data-module-panel="tasks" hidden>
        <div class="v24-smh-module-toolbar">
            <input type="search" data-region="tasks-search" placeholder="Cerca task">
            <select data-region="tasks-status-filter">
                <option value="">Tutti gli stati</option>
                <option value="not_started">Non iniziato</option>
                <option value="in_progress">In corso</option>
                <option value="waiting">In attesa</option>
                <option value="completed">Completato</option>
                <option value="deferred">Rimandato</option>
            </select>
            <button type="button" class="v24-smh-button" data-action="load-tasks">Aggiorna</button>
            <button type="button" class="v24-smh-button v24-smh-button-primary" data-action="new-task">Nuovo task</button>
        </div>
        <div class="v24-smh-dual">
            <section class="v24-smh-list">
                <div data-region="tasks"></div>
            </section>
            <section class="v24-smh-reader">
                <div data-region="task-reader">
                    <p>Seleziona un task per vedere scadenze, assegnatario e collegamenti operativi.</p>
                </div>
            </section>
        </div>
        <div class="v24-smh-sheet" data-region="task-form-sheet" hidden>
            <form data-form="task">
                <div class="v24-smh-compose-header">
                    <h2 data-region="task-form-title">Nuovo task</h2>
                    <button type="button" class="v24-smh-link" data-action="close-task-form">Chiudi</button>
                </div>
                <div class="v24-smh-form-grid">
                    <label>
                        <span>Titolo</span>
                        <input type="text" name="title" data-field="task-title" required>
                    </label>
                    <label>
                        <span>Stato</span>
                        <select name="status" data-field="task-status">
                            <option value="not_started">Non iniziato</option>
                            <option value="in_progress">In corso</option>
                            <option value="waiting">In attesa</option>
                            <option value="completed">Completato</option>
                            <option value="deferred">Rimandato</option>
                        </select>
                    </label>
                    <label>
                        <span>Priorita</span>
                        <select name="priority" data-field="task-priority">
                            <option value="low">Bassa</option>
                            <option value="normal">Normale</option>
                            <option value="high">Alta</option>
                        </select>
                    </label>
                    <label>
                        <span>Assegnato a</span>
                        <select name="assigned_user_id" data-field="task-assigned-user"></select>
                    </label>
                    <label>
                        <span>Inizio</span>
                        <input type="datetime-local" name="start_at" data-field="task-start">
                    </label>
                    <label>
                        <span>Scadenza</span>
                        <input type="datetime-local" name="due_at" data-field="task-due">
                    </label>
                    <label>
                        <span>Promemoria</span>
                        <input type="datetime-local" name="reminder_at" data-field="task-reminder">
                    </label>
                    <label>
                        <span>Completamento %</span>
                        <input type="number" min="0" max="100" step="5" name="percent_complete" data-field="task-percent" value="0">
                    </label>
                    <label>
                        <span>Messaggio collegato ID</span>
                        <input type="number" min="0" name="related_message_id" data-field="task-related-message-id">
                    </label>
                    <label>
                        <span>Contatto collegato ID</span>
                        <input type="number" min="0" name="related_contact_id" data-field="task-related-contact-id">
                    </label>
                    <label>
                        <span>Evento collegato ID</span>
                        <input type="number" min="0" name="related_event_id" data-field="task-related-event-id">
                    </label>
                </div>
                <label>
                    <span>Categorie (separate da virgola)</span>
                    <input type="text" name="categories_csv" data-field="task-categories" placeholder="Vendite, Follow-up, Priorita">
                </label>
                <label>
                    <span>Descrizione</span>
                    <textarea name="description" data-field="task-description" rows="8"></textarea>
                </label>
                <div class="v24-smh-actions">
                    <button type="submit" class="v24-smh-button-primary">Salva task</button>
                    <button type="button" data-action="close-task-form">Annulla</button>
                </div>
            </form>
        </div>
    </section>

    <section class="v24-smh-module" data-module-panel="rules" hidden>
        <div class="v24-smh-module-toolbar">
            <select data-region="rules-account"></select>
            <button type="button" class="v24-smh-button" data-action="load-rules">Aggiorna regole</button>
            <button type="button" class="v24-smh-button" data-action="run-rules">Esegui ora</button>
            <button type="button" class="v24-smh-button v24-smh-button-primary" data-action="new-rule">Nuova regola</button>
        </div>
        <div class="v24-smh-dual">
            <section class="v24-smh-list">
                <div data-region="rules"></div>
            </section>
            <section class="v24-smh-reader">
                <div data-region="rule-reader">
                    <p>Seleziona una regola per vedere condizioni, azioni e ordine di applicazione.</p>
                </div>
            </section>
        </div>
        <div class="v24-smh-sheet" data-region="rule-form-sheet" hidden>
            <form data-form="rule">
                <div class="v24-smh-compose-header">
                    <h2 data-region="rule-form-title">Nuova regola</h2>
                    <button type="button" class="v24-smh-link" data-action="close-rule-form">Chiudi</button>
                </div>
                <div class="v24-smh-form-grid">
                    <label>
                        <span>Nome</span>
                        <input type="text" name="name" data-field="rule-name" required>
                    </label>
                    <label>
                        <span>Attiva</span>
                        <select name="is_active" data-field="rule-active">
                            <option value="1">Si</option>
                            <option value="0">No</option>
                        </select>
                    </label>
                    <label>
                        <span>Ordine</span>
                        <input type="number" min="0" step="1" name="sort_order" data-field="rule-sort-order" value="0">
                    </label>
                    <label>
                        <span>Logica condizioni</span>
                        <select name="match_mode" data-field="rule-match-mode">
                            <option value="all">Tutte</option>
                            <option value="any">Almeno una</option>
                        </select>
                    </label>
                </div>
                <label class="v24-smh-checkbox">
                    <input type="checkbox" name="stop_processing" data-field="rule-stop-processing" value="1">
                    <span>Ferma le regole successive dopo il match</span>
                </label>
                <div class="v24-smh-card-stack">
                    <h3>Condizioni</h3>
                    <label class="v24-smh-checkbox">
                        <input type="checkbox" name="match_all" data-field="rule-match-all" value="1">
                        <span>Applica a tutti i messaggi del perimetro selezionato</span>
                    </label>
                    <div class="v24-smh-form-grid">
                        <label>
                            <span>Cartella</span>
                            <select name="folder_id" data-field="rule-folder-id"></select>
                        </label>
                        <label>
                            <span>Da contiene</span>
                            <input type="text" name="from_contains" data-field="rule-from-contains" placeholder="cliente@azienda.it">
                        </label>
                        <label>
                            <span>Oggetto contiene</span>
                            <input type="text" name="subject_contains" data-field="rule-subject-contains" placeholder="Fattura, Urgente">
                        </label>
                        <label>
                            <span>Anteprima contiene</span>
                            <input type="text" name="preview_contains" data-field="rule-preview-contains" placeholder="follow-up, appuntamento">
                        </label>
                        <label>
                            <span>Destinatari contengono</span>
                            <input type="text" name="recipients_contains" data-field="rule-recipients-contains" placeholder="sales@azienda.it">
                        </label>
                        <label>
                            <span>Ha allegati</span>
                            <select name="has_attachments" data-field="rule-has-attachments">
                                <option value="">Qualsiasi</option>
                                <option value="1">Si</option>
                                <option value="0">No</option>
                            </select>
                        </label>
                        <label>
                            <span>Stato lettura</span>
                            <select name="is_seen" data-field="rule-is-seen">
                                <option value="">Qualsiasi</option>
                                <option value="0">Non letto</option>
                                <option value="1">Letto</option>
                            </select>
                        </label>
                        <label>
                            <span>Flag</span>
                            <select name="is_flagged" data-field="rule-is-flagged">
                                <option value="">Qualsiasi</option>
                                <option value="1">Contrassegnato</option>
                                <option value="0">Non contrassegnato</option>
                            </select>
                        </label>
                    </div>
                </div>
                <div class="v24-smh-card-stack">
                    <h3>Azioni</h3>
                    <div class="v24-smh-form-grid">
                        <label>
                            <span>Sposta in cartella</span>
                            <select name="move_to_folder_id" data-field="rule-move-to-folder-id"></select>
                        </label>
                        <label>
                            <span>Segna come letto</span>
                            <select name="mark_seen" data-field="rule-mark-seen">
                                <option value="">Nessuna modifica</option>
                                <option value="1">Si</option>
                                <option value="0">No</option>
                            </select>
                        </label>
                        <label>
                            <span>Imposta flag</span>
                            <select name="set_flagged" data-field="rule-set-flagged">
                                <option value="">Nessuna modifica</option>
                                <option value="1">Aggiungi flag</option>
                                <option value="0">Rimuovi flag</option>
                            </select>
                        </label>
                    </div>
                    <label class="v24-smh-checkbox">
                        <input type="checkbox" name="delete_message" data-field="rule-delete-message" value="1">
                        <span>Elimina il messaggio</span>
                    </label>
                </div>
                <div class="v24-smh-actions">
                    <button type="submit" class="v24-smh-button-primary">Salva regola</button>
                    <button type="button" data-action="close-rule-form">Annulla</button>
                </div>
            </form>
        </div>
    </section>
</div>
