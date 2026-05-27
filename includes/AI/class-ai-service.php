<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_AI_Service
{
    private const USER_META_KEY = 'v24_smh_ai_preferences';

    public function bootstrap(array $input)
    {
        $account = $this->load_account((int) ($input['account_id'] ?? 0), 'v24_smh_read_mail');
        if (is_wp_error($account)) {
            return $account;
        }

        $message_id = (int) ($input['message_id'] ?? 0);
        $compose_mode = sanitize_key($input['compose_mode'] ?? 'new');
        $settings = $this->settings();
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        $remote = $this->remote_bootstrap($settings, $account);
        $options = $this->normalize_bootstrap_options($remote);
        $preferences = $this->merge_preferences($this->stored_preferences(), $remote['defaults'] ?? []);
        $context = $this->load_compose_context($message_id);
        $repository = new V24_SMH_AI_Response_Repository();
        $latest_local_response = !empty($input['force_new_chat'])
            ? null
            : $repository->latest_for_context((int) $account['id'], $message_id, $compose_mode, get_current_user_id());
        $chat_id = !empty($input['force_new_chat'])
            ? $this->request_chat_id($settings, $account)
            : (($latest_local_response['remote_chat_id'] ?? '') ?: $remote['chat_id'] ?: $this->request_chat_id($settings, $account));
        $responses = [];

        if ($chat_id !== '') {
            $responses = array_map([$this, 'present_response'], $repository->list_for_chat((int) $account['id'], $chat_id, get_current_user_id()));
        }

        return [
            'configured' => $this->is_ai_ready($settings, $tenant),
            'tenant' => [
                'key' => $tenant['tenant_key'],
                'matched' => $tenant['matched'],
                'host' => $tenant['host'],
                'profile' => $tenant['profile'],
            ],
            'options' => $options,
            'preferences' => $preferences,
            'chat_id' => $chat_id,
            'responses' => $responses,
            'context' => [
                'compose_mode' => $compose_mode,
                'original_subject' => $context['subject'],
                'original_body_html' => $context['body_html'],
                'original_body_text' => $context['body_text'],
                'placeholder' => in_array($compose_mode, ['reply', 'draft'], true)
                    ? 'Descrivi la mail che vuoi scrivere oppure lancia la risposta automatica senza indicazioni'
                    : 'Descrivi la mail che vuoi scrivere',
            ],
        ];
    }

    public function save_preferences(array $input): array
    {
        $scope = sanitize_key($input['scope'] ?? 'next_change');
        $preferences = $this->sanitize_preferences($input);

        if ($scope === 'new_mail') {
            update_user_meta(get_current_user_id(), self::USER_META_KEY, $preferences);
        }

        return [
            'saved' => true,
            'scope' => $scope,
            'preferences' => $preferences,
        ];
    }

    public function generate(array $input)
    {
        $account = $this->load_account((int) ($input['account_id'] ?? 0), 'v24_smh_send_mail');
        if (is_wp_error($account)) {
            return $account;
        }

        $settings = $this->settings();
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        if (!$this->is_ai_ready($settings, $tenant)) {
            return new WP_Error('ai_not_configured', 'Configura endpoint e token AI nelle impostazioni del plugin.', ['status' => 400]);
        }

        $message_id = (int) ($input['message_id'] ?? 0);
        $compose_mode = sanitize_key($input['compose_mode'] ?? 'new');
        $context = $this->load_compose_context($message_id);
        $prompt = trim((string) ($input['prompt'] ?? ''));
        $final_prompt = $prompt;
        $mail_type = 'new';
        $original_text = $context['body_text'];

        if (in_array($compose_mode, ['reply', 'draft'], true) && $original_text !== '') {
            $mail_type = 'reply';
            if ($final_prompt === '') {
                $final_prompt = 'rispondi alla seguente mail';
            }
        }

        if ($final_prompt === '') {
            return new WP_Error('validation_error', 'Inserisci un prompt oppure apri una risposta su una mail esistente.', ['status' => 400]);
        }

        $chat_id = sanitize_text_field($input['chat_id'] ?? '');
        if ($chat_id === '') {
            $chat_id = $this->request_chat_id($settings, $account);
        }

        $preferences = $this->sanitize_preferences($input);
        $payload = [
            'text' => $final_prompt,
            'text1' => $prompt,
            'languageSelect' => $preferences['language'],
            'toneSelect' => $preferences['tone'],
            'writingSelect' => $preferences['writing'],
            'formatSelect' => $preferences['format'],
            'searchrevolgo' => sanitize_text_field($input['salutation'] ?? ''),
            'searchfirmo' => sanitize_text_field($input['signoff'] ?? ''),
            'assist_id' => sanitize_text_field($input['assistant_id'] ?? ''),
            'privacy' => !empty($input['privacy']) ? 1 : 0,
            'testosintetico' => !empty($input['concise']) ? 1 : 0,
            'useremail' => $account['email_address'],
            'chat_id' => $chat_id,
            'mailtype' => $mail_type,
            'composetext' => $original_text !== '' ? base64_encode($original_text) : '',
        ];

        $remote = $this->remote_request('search-email-assistance-ai', $payload, $settings, $tenant);
        if (is_wp_error($remote)) {
            return $remote;
        }

        return $this->store_remote_response($remote, [
            'account_id' => (int) $account['id'],
            'message_id' => $message_id,
            'compose_mode' => $compose_mode,
            'response_mode' => 'generate',
            'tenant_key' => $tenant['tenant_key'],
            'prompt_text' => $final_prompt,
            'original_subject' => $context['subject'],
            'original_body_text' => $original_text,
            'assistant_id' => sanitize_text_field($input['assistant_id'] ?? ''),
            'privacy_mode' => !empty($input['privacy']),
            'is_concise' => !empty($input['concise']),
            'preferences_json' => $preferences,
        ]);
    }

    public function improve(array $input)
    {
        $response = $this->load_response((int) ($input['response_id'] ?? 0));
        if (is_wp_error($response)) {
            return $response;
        }

        $account = $this->load_account((int) $response['account_id'], 'v24_smh_send_mail');
        if (is_wp_error($account)) {
            return $account;
        }

        $settings = $this->settings();
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        if (!$this->is_ai_ready($settings, $tenant)) {
            return new WP_Error('ai_not_configured', 'Configura endpoint e token AI nelle impostazioni del plugin.', ['status' => 400]);
        }

        $repeat = max(1, min(10, (int) ($input['repeat'] ?? 1)));
        $instructions = trim((string) ($input['instructions'] ?? ''));
        $no_indications = $instructions === '';
        $advanced_model = !empty($input['advanced_model']) ? 1 : 0;
        $internet = !empty($input['internet']) ? 1 : 0;
        $preferences = $this->sanitize_preferences($input);
        $chat_id = sanitize_text_field($input['chat_id'] ?? ($response['remote_chat_id'] ?? ''));

        if ($chat_id === '') {
            $chat_id = $this->request_chat_id($settings, $account);
        }

        $endpoint = $repeat > 1
            ? 'search-email-assistance-migliorarisposta-loop-ai'
            : ($internet ? 'search-email-assistance-migliorarisposta-internet-ai' : 'search-email-assistance-migliorarisposta-ai');

        $payload = [
            'regentextarea' => $instructions,
            'miglioraassistente' => sanitize_text_field($input['assistant_id'] ?? ($response['assistant_id'] ?? '')),
            'migliorainternet' => $internet,
            'gptinusefour' => $advanced_model,
            'noidication' => $no_indications ? 1 : 0,
            'idication' => $no_indications ? 0 : 1,
            'language' => $preferences['language'],
            'toneSelect' => $preferences['tone'],
            'writingSelect' => $preferences['writing'],
            'formatSelect' => $preferences['format'],
            'useremail' => $account['email_address'],
            'chat_id' => $chat_id,
        ];

        if ($repeat > 1) {
            $payload['repeatanswer'] = $repeat;
            $payload['repeatanswerArr'] = range(1, $repeat);
            $payload['repeatanswertext'] = sprintf('%sx', $repeat);
        }

        $remote = $this->remote_request($endpoint, $payload, $settings, $tenant);
        if (is_wp_error($remote)) {
            return $remote;
        }

        return $this->store_remote_response($remote, [
            'account_id' => (int) $response['account_id'],
            'message_id' => !empty($response['message_id']) ? (int) $response['message_id'] : 0,
            'source_response_id' => (int) $response['id'],
            'compose_mode' => sanitize_key($response['compose_mode'] ?? 'new'),
            'response_mode' => 'improve',
            'tenant_key' => $tenant['tenant_key'],
            'prompt_text' => $instructions === '' ? 'Migliora risposta' : $instructions,
            'original_subject' => $response['original_subject'] ?? '',
            'original_body_text' => $response['original_body_text'] ?? '',
            'assistant_id' => sanitize_text_field($input['assistant_id'] ?? ($response['assistant_id'] ?? '')),
            'privacy_mode' => !empty($response['privacy_mode']),
            'is_concise' => !empty($response['is_concise']),
            'preferences_json' => array_merge($preferences, [
                'repeat' => $repeat,
                'internet' => $internet,
                'advanced_model' => $advanced_model,
            ]),
        ]);
    }

    public function translate(array $input)
    {
        $response = $this->load_response((int) ($input['response_id'] ?? 0));
        if (is_wp_error($response)) {
            return $response;
        }

        $account = $this->load_account((int) $response['account_id'], 'v24_smh_send_mail');
        if (is_wp_error($account)) {
            return $account;
        }

        $settings = $this->settings();
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        if (!$this->is_ai_ready($settings, $tenant)) {
            return new WP_Error('ai_not_configured', 'Configura endpoint e token AI nelle impostazioni del plugin.', ['status' => 400]);
        }

        $target_language = sanitize_text_field($input['target_language'] ?? '');
        if ($target_language === '') {
            return new WP_Error('validation_error', 'Seleziona la lingua di destinazione.', ['status' => 400]);
        }

        $scope = sanitize_key($input['scope'] ?? 'selected');
        $payload = [
            'allsearchtraduci' => $scope === 'all' ? 1 : 0,
            'allsearchlasttraduci' => $scope === 'last' ? 1 : 0,
            'allsearchremovequestiontraduci' => !empty($input['remove_questions']) ? 1 : 0,
            'languageSelect' => $target_language,
            'chat_id' => sanitize_text_field($input['chat_id'] ?? ($response['remote_chat_id'] ?? '')),
            'useremail' => $account['email_address'],
        ];

        $remote = $this->remote_request('search-email-assistance-traduci-ai', $payload, $settings, $tenant);
        if (is_wp_error($remote)) {
            return $remote;
        }

        return $this->store_remote_response($remote, [
            'account_id' => (int) $response['account_id'],
            'message_id' => !empty($response['message_id']) ? (int) $response['message_id'] : 0,
            'source_response_id' => (int) $response['id'],
            'compose_mode' => sanitize_key($response['compose_mode'] ?? 'new'),
            'response_mode' => 'translate',
            'tenant_key' => $tenant['tenant_key'],
            'prompt_text' => sprintf('Traduci in %s', $target_language),
            'original_subject' => $response['original_subject'] ?? '',
            'original_body_text' => $response['original_body_text'] ?? '',
            'assistant_id' => $response['assistant_id'] ?? '',
            'privacy_mode' => !empty($response['privacy_mode']),
            'is_concise' => !empty($response['is_concise']),
            'preferences_json' => ['target_language' => $target_language, 'scope' => $scope],
        ]);
    }

    public function export(array $input)
    {
        $response = $this->load_response((int) ($input['response_id'] ?? 0));
        if (is_wp_error($response)) {
            return $response;
        }

        $account = $this->load_account((int) $response['account_id'], 'v24_smh_send_mail');
        if (is_wp_error($account)) {
            return $account;
        }

        $settings = $this->settings();
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        if (!$this->is_ai_ready($settings, $tenant)) {
            return new WP_Error('ai_not_configured', 'Configura endpoint e token AI nelle impostazioni del plugin.', ['status' => 400]);
        }

        $doctype = sanitize_key($input['doctype'] ?? 'print');
        if (!in_array($doctype, ['doc', 'pdf', 'print'], true)) {
            return new WP_Error('validation_error', 'Formato export non valido.', ['status' => 400]);
        }

        $remote = $this->remote_request('search-email-assistance-docpdf-ai', [
            'idcount' => $response['remote_generation_id'],
            'outputtype' => sanitize_key($input['output_type'] ?? 'verticale'),
            'doctype' => $doctype,
            'removebackground' => !empty($input['remove_background']) ? 1 : 0,
            'removequestion' => !empty($input['remove_questions']) ? 1 : 0,
        ], $settings, $tenant);

        if (is_wp_error($remote)) {
            return $remote;
        }

        $data = $remote['data'] ?? '';
        if ($doctype === 'print' && !$this->has_meaningful_html((string) $data)) {
            $data = $this->build_local_preview_html($response);
        }
        if ($doctype !== 'print' && is_string($data) && !preg_match('#^https?://#i', $data)) {
            $data = $this->uploads_url_from_endpoint($tenant['endpoint']) . ltrim($data, '/');
        }

        return [
            'doctype' => $doctype,
            'data' => $data,
        ];
    }

    public function preview_email(array $input)
    {
        $response = $this->load_response((int) ($input['response_id'] ?? 0));
        if (is_wp_error($response)) {
            return $response;
        }

        $account = $this->load_account((int) $response['account_id'], 'v24_smh_send_mail');
        if (is_wp_error($account)) {
            return $account;
        }

        $settings = $this->settings();
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        if (!$this->is_ai_ready($settings, $tenant)) {
            return new WP_Error('ai_not_configured', 'Configura endpoint e token AI nelle impostazioni del plugin.', ['status' => 400]);
        }

        $remote = $this->remote_request('search-email-assistance-visualizza-email-ai', [
            'idcount' => $response['remote_generation_id'],
            'attachtype' => sanitize_text_field($input['attach_type'] ?? 'comeallegato'),
            'outputtype' => sanitize_key($input['output_type'] ?? 'verticale'),
            'remquetion' => !empty($input['remove_questions']) ? 1 : 0,
            'remback' => !empty($input['remove_background']) ? 1 : 0,
            'useraiemail' => 'preview',
        ], $settings, $tenant);

        if (is_wp_error($remote)) {
            return $remote;
        }

        $download = $remote['downlaodfilename'] ?? '';
        if ($download !== '' && !preg_match('#^https?://#i', $download)) {
            $download = $this->uploads_url_from_endpoint($tenant['endpoint']) . ltrim($download, '/');
        }

        $html = (string) ($remote['htmlmain'] ?? '');
        if (!$this->has_meaningful_html($html)) {
            $html = $this->build_local_preview_html($response);
        }

        return [
            'status' => (int) ($remote['status'] ?? 200),
            'html' => $html,
            'download_url' => $download,
        ];
    }

    public function send_result_email(array $input)
    {
        $response = $this->load_response((int) ($input['response_id'] ?? 0));
        if (is_wp_error($response)) {
            return $response;
        }

        $account = $this->load_account((int) $response['account_id'], 'v24_smh_send_mail');
        if (is_wp_error($account)) {
            return $account;
        }

        $settings = $this->settings();
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        if (!$this->is_ai_ready($settings, $tenant)) {
            return new WP_Error('ai_not_configured', 'Configura endpoint e token AI nelle impostazioni del plugin.', ['status' => 400]);
        }

        $subject = sanitize_text_field($input['subject'] ?? '');
        if ($subject === '') {
            return new WP_Error('validation_error', 'Inserisci l oggetto della mail.', ['status' => 400]);
        }

        $recipient = sanitize_email($input['email'] ?? '');
        if ($recipient === '') {
            $recipient = sanitize_email($account['email_address'] ?? '');
        }

        if ($this->should_fallback_to_local_delivery($response)) {
            return $this->send_local_response_email($account, $recipient, $subject, $response);
        }

        $remote = $this->remote_request('search-email-assistance-sendemail-ai', [
            'idcount' => $response['remote_generation_id'],
            'attachtype' => sanitize_text_field($input['attach_type'] ?? 'comeallegato'),
            'useraiemail' => $recipient,
            'outputtype' => sanitize_key($input['output_type'] ?? 'verticale'),
            'remquetion' => !empty($input['remove_questions']) ? 1 : 0,
            'remback' => !empty($input['remove_background']) ? 1 : 0,
            'modalsubject' => $subject,
        ], $settings, $tenant);

        if (is_wp_error($remote)) {
            return $this->send_local_response_email($account, $recipient, $subject, $response, $remote);
        }

        if (empty($remote['status']) && empty($remote['success'])) {
            return $this->send_local_response_email($account, $recipient, $subject, $response);
        }

        return [
            'sent' => true,
            'remote' => $remote,
            'delivery_mode' => 'remote',
        ];
    }

    public function text_to_speech(array $input)
    {
        $response = $this->load_response((int) ($input['response_id'] ?? 0));
        if (is_wp_error($response)) {
            return $response;
        }

        $account = $this->load_account((int) $response['account_id'], 'v24_smh_send_mail');
        if (is_wp_error($account)) {
            return $account;
        }

        $settings = $this->settings();
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        if (!$this->is_ai_ready($settings, $tenant)) {
            return new WP_Error('ai_not_configured', 'Configura endpoint e token AI nelle impostazioni del plugin.', ['status' => 400]);
        }

        $remote = $this->remote_request('set_text_to_audio_email_ai', [
            'idArr' => [(string) $response['remote_generation_id']],
            'useremail' => $account['email_address'],
        ], $settings, $tenant);

        if (is_wp_error($remote)) {
            return $remote;
        }

        $audio_url = esc_url_raw($remote['msg'] ?? '');
        (new V24_SMH_AI_Response_Repository())->update((int) $response['id'], ['audio_url' => $audio_url]);

        return ['audio_url' => $audio_url];
    }

    public function audio_to_text(array $files, array $input)
    {
        $account = $this->load_account((int) ($input['account_id'] ?? 0), 'v24_smh_send_mail');
        if (is_wp_error($account)) {
            return $account;
        }

        $file = $files['file'] ?? null;
        if (!$file || empty($file['tmp_name'])) {
            return new WP_Error('validation_error', 'File audio non ricevuto.', ['status' => 400]);
        }

        $settings = $this->settings();
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        $text = $this->remote_upload_audio($tenant['endpoint'], $settings, $account, $file);
        if (is_wp_error($text)) {
            return $text;
        }

        return ['text' => $text];
    }

    public function rate_response(int $response_id, array $input)
    {
        $response = $this->load_response($response_id);
        if (is_wp_error($response)) {
            return $response;
        }

        $account = $this->load_account((int) $response['account_id'], 'v24_smh_send_mail');
        if (is_wp_error($account)) {
            return $account;
        }

        $rating = max(1, min(5, (int) ($input['rating'] ?? 0)));
        if ($rating < 1) {
            return new WP_Error('validation_error', 'Valutazione non valida.', ['status' => 400]);
        }

        $comment = sanitize_textarea_field($input['comment'] ?? '');
        (new V24_SMH_AI_Response_Repository())->update($response_id, [
            'rating_value' => $rating,
            'rating_comment' => $comment,
        ]);

        $settings = $this->settings();
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        if ($this->is_ai_ready($settings, $tenant) && !empty($response['remote_generation_id'])) {
            $this->remote_request('saveanseremailairating', [
                'ids' => $response['remote_generation_id'],
                'starcount' => $rating,
                'text' => $comment,
                'useremail' => $account['email_address'],
            ], $settings, $tenant);
        }

        return ['saved' => true];
    }

    public function ignore_response(int $response_id, array $input)
    {
        $response = $this->load_response($response_id);
        if (is_wp_error($response)) {
            return $response;
        }

        $account = $this->load_account((int) $response['account_id'], 'v24_smh_send_mail');
        if (is_wp_error($account)) {
            return $account;
        }

        $ignored = !empty($input['ignored']);
        (new V24_SMH_AI_Response_Repository())->update($response_id, ['is_ignored' => $ignored]);

        $settings = $this->settings();
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        if ($this->is_ai_ready($settings, $tenant) && !empty($response['remote_generation_id'])) {
            $remote = $this->remote_request('search-email-assistance-ignoresearch-ai', [
                'searchid' => $response['remote_generation_id'],
                'useremail' => $account['email_address'],
            ], $settings, $tenant);

            if (!is_wp_error($remote) && !empty($remote['status1'])) {
                $ignored = $remote['status1'] === 'hide';
                (new V24_SMH_AI_Response_Repository())->update($response_id, ['is_ignored' => $ignored]);
            }
        }

        return ['ignored' => $ignored];
    }

    public function update_response(int $response_id, array $input)
    {
        $response = $this->load_response($response_id);
        if (is_wp_error($response)) {
            return $response;
        }

        $account = $this->load_account((int) $response['account_id'], 'v24_smh_send_mail');
        if (is_wp_error($account)) {
            return $account;
        }

        $html = wp_kses_post($input['html'] ?? '');
        if ($html === '') {
            return new WP_Error('validation_error', 'Il contenuto aggiornato e obbligatorio.', ['status' => 400]);
        }

        $text = trim(wp_strip_all_tags($html));
        (new V24_SMH_AI_Response_Repository())->update($response_id, [
            'generated_html' => $html,
            'generated_text' => $text,
        ]);

        $settings = $this->settings();
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        if ($this->is_ai_ready($settings, $tenant) && !empty($response['remote_generation_id'])) {
            $remote = $this->remote_request('search-email-assistance-update-ckrditor-text-ai', [
                'resdata' => $html,
                'idcount' => $response['remote_generation_id'],
                'useremail' => $account['email_address'],
            ], $settings, $tenant);

            if (!is_wp_error($remote) && !empty($remote['msg'])) {
                $html = wp_kses_post($remote['msg']);
                $text = trim(wp_strip_all_tags($html));
                (new V24_SMH_AI_Response_Repository())->update($response_id, [
                    'generated_html' => $html,
                    'generated_text' => $text,
                ]);
            }
        }

        return [
            'id' => $response_id,
            'html' => $html,
            'text' => $text,
        ];
    }

    private function settings(): array
    {
        $defaults = [
            'ai_enabled' => true,
            'ai_proxy_token' => '',
            'ai_endpoint_default' => 'https://secureserverai.professionista-ai.com/wp-json/proff_ai/v1/',
            'ai_endpoint_tenant' => 'https://securserveraioffice.valore24.ilsole24ore.com/wp-json/proff_ai/v1/',
            'ai_allowed_tenants' => implode(',', V24_SMH_AI_Tenant_Resolver::default_allowed_tenants()),
            'ai_display_name' => 'Secure E-mail AI',
            'ai_branding_profiles' => V24_SMH_AI_Tenant_Resolver::default_profiles_json(),
        ];

        $settings = get_option('v24_smh_settings', []);
        return wp_parse_args(is_array($settings) ? $settings : [], $defaults);
    }

    private function stored_preferences(): array
    {
        $stored = get_user_meta(get_current_user_id(), self::USER_META_KEY, true);
        return is_array($stored) ? $this->sanitize_preferences($stored) : $this->default_preferences();
    }

    private function merge_preferences(array $stored, array $remote): array
    {
        return $this->sanitize_preferences(array_merge($stored, array_filter([
            'language' => $remote['language'] ?? '',
            'tone' => $remote['tone'] ?? '',
            'writing' => $remote['writing'] ?? '',
            'format' => $remote['format'] ?? '',
        ])));
    }

    private function default_preferences(): array
    {
        return [
            'language' => 'Italiano',
            'tone' => 'Professionale',
            'writing' => 'Argomentativo',
            'format' => '11',
        ];
    }

    private function sanitize_preferences(array $input): array
    {
        return array_merge($this->default_preferences(), [
            'language' => sanitize_text_field($input['language'] ?? $input['languageSelect'] ?? $this->default_preferences()['language']),
            'tone' => sanitize_text_field($input['tone'] ?? $input['toneSelect'] ?? $this->default_preferences()['tone']),
            'writing' => sanitize_text_field($input['writing'] ?? $input['writingSelect'] ?? $this->default_preferences()['writing']),
            'format' => sanitize_text_field($input['format'] ?? $input['formatSelect'] ?? $this->default_preferences()['format']),
        ]);
    }

    private function load_account(int $account_id, string $capability)
    {
        if (!V24_SMH_Permissions::current_user_can_account($account_id, $capability)) {
            return new WP_Error('rest_forbidden', 'Permesso insufficiente.', ['status' => 403]);
        }

        $account = (new V24_SMH_Account_Repository())->find($account_id);
        if (!$account) {
            return new WP_Error('invalid_account', 'Account non trovato.', ['status' => 404]);
        }

        return $account;
    }

    private function load_response(int $response_id)
    {
        $response = (new V24_SMH_AI_Response_Repository())->find($response_id);
        if (!$response) {
            return new WP_Error('ai_response_not_found', 'Risposta AI non trovata.', ['status' => 404]);
        }

        if ((int) ($response['user_id'] ?? 0) !== get_current_user_id() && !current_user_can('v24_smh_review_ai_responses')) {
            return new WP_Error('rest_forbidden', 'Permesso insufficiente.', ['status' => 403]);
        }

        return $response;
    }

    private function load_compose_context(int $message_id): array
    {
        if ($message_id <= 0) {
            return ['subject' => '', 'body_html' => '', 'body_text' => ''];
        }

        $message = (new V24_SMH_Mail_Repository())->find($message_id);
        if (!$message || !V24_SMH_Permissions::current_user_can_account((int) $message['account_id'], 'v24_smh_read_mail')) {
            return ['subject' => '', 'body_html' => '', 'body_text' => ''];
        }

        $account = (new V24_SMH_Account_Repository())->find((int) $message['account_id']);
        $body = (new V24_SMH_Mail_Repository())->get_body($message_id);
        if ((!$body || (($body['body_html_sanitized'] ?? '') === '' && ($body['body_plain'] ?? '') === '')) && $account) {
            $folder = (new V24_SMH_Folder_Repository())->find((int) $message['folder_id']);
            if ($folder) {
                $payload = (new V24_SMH_IMAP_Client())->fetch_message_payload($account, $folder['full_name'], (int) $message['uid']);
                if (!is_wp_error($payload)) {
                    (new V24_SMH_Mail_Repository())->save_body($message_id, $payload['body']);
                    $body = (new V24_SMH_Mail_Repository())->get_body($message_id);
                }
            }
        }

        $body_html = $body['body_html_sanitized'] ?? '';
        $body_plain = trim((string) ($body['body_plain'] ?? ''));
        $body_text = $body_plain !== '' ? $body_plain : trim(wp_strip_all_tags($body_html));

        return [
            'subject' => (string) ($message['subject'] ?? ''),
            'body_html' => $body_html,
            'body_text' => $body_text,
        ];
    }

    private function normalize_bootstrap_options(array $remote): array
    {
        if (!empty($remote['options'])) {
            return $remote['options'];
        }

        return [
            'languages' => [
                ['value' => 'Italiano', 'label' => 'Italiano'],
                ['value' => 'English', 'label' => 'English'],
                ['value' => 'Francais', 'label' => 'Francais'],
                ['value' => 'Deutsch', 'label' => 'Deutsch'],
                ['value' => 'Espanol', 'label' => 'Espanol'],
            ],
            'tones' => [
                ['value' => 'Professionale', 'label' => 'Professionale'],
                ['value' => 'Cordiale', 'label' => 'Cordiale'],
                ['value' => 'Diretto', 'label' => 'Diretto'],
                ['value' => 'Empatico', 'label' => 'Empatico'],
                ['value' => 'Persuasivo', 'label' => 'Persuasivo'],
            ],
            'writings' => [
                ['value' => 'Argomentativo', 'label' => 'Argomentativo'],
                ['value' => 'Sintetico', 'label' => 'Sintetico'],
                ['value' => 'Commerciale', 'label' => 'Commerciale'],
                ['value' => 'Tecnico', 'label' => 'Tecnico'],
                ['value' => 'Formale', 'label' => 'Formale'],
            ],
            'formats' => [
                ['value' => '11', 'label' => 'Email strutturata'],
                ['value' => 'brief', 'label' => 'Breve follow-up'],
                ['value' => 'points', 'label' => 'Elenco puntato'],
                ['value' => 'expanded', 'label' => 'Risposta estesa'],
            ],
            'salutations' => [
                ['value' => 'Gentile', 'label' => 'Gentile'],
                ['value' => 'Buongiorno', 'label' => 'Buongiorno'],
                ['value' => 'Ciao', 'label' => 'Ciao'],
                ['value' => 'Spett.le', 'label' => 'Spett.le'],
            ],
            'signoffs' => [
                ['value' => 'Cordialmente', 'label' => 'Cordialmente'],
                ['value' => 'Grazie', 'label' => 'Grazie'],
                ['value' => 'A disposizione', 'label' => 'A disposizione'],
                ['value' => 'Un saluto', 'label' => 'Un saluto'],
            ],
            'assistants' => [
                ['value' => '', 'label' => 'Nessuno', 'description' => 'Nessun assistente specialistico'],
                ['value' => 'sales', 'label' => 'Commerciale', 'description' => 'Supporto per email commerciali'],
                ['value' => 'legal', 'label' => 'Legale', 'description' => 'Supporto per testi piu formali e cauti'],
                ['value' => 'technical', 'label' => 'Tecnico', 'description' => 'Supporto per spiegazioni operative e tecniche'],
            ],
        ];
    }

    private function remote_bootstrap(array $settings, array $account): array
    {
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        if (!$this->is_ai_ready($settings, $tenant)) {
            return ['chat_id' => '', 'options' => [], 'defaults' => []];
        }

        $remote = $this->remote_request('getlanguagetonestylePrompt', [
            'useremail' => $account['email_address'],
        ], $settings, $tenant);

        if (is_wp_error($remote)) {
            return ['chat_id' => '', 'options' => [], 'defaults' => []];
        }

        $data = $remote['data'] ?? [];
        if (!is_array($data)) {
            return ['chat_id' => '', 'options' => [], 'defaults' => []];
        }

        return [
            'chat_id' => sanitize_text_field($data['chat_id'] ?? ''),
            'defaults' => [
                'language' => sanitize_text_field($data['sellang'] ?? ''),
                'tone' => sanitize_text_field($data['seltone'] ?? ''),
                'writing' => sanitize_text_field($data['selwriting'] ?? ''),
                'format' => sanitize_text_field($data['selformat'] ?? ''),
            ],
            'options' => [
                'languages' => $this->normalize_items($data['lng'] ?? [], 'lang', 'lang'),
                'tones' => $this->normalize_items($data['tone'] ?? [], 'prompt_name', 'prompt_name', 'prompt_description'),
                'writings' => $this->normalize_items($data['tonestyle'] ?? [], 'prompt_name', 'prompt_name', 'prompt_description'),
                'formats' => $this->normalize_items($data['formatArr'] ?? [], 'id', 'prompt_name', 'prompt_name'),
                'salutations' => $this->normalize_items($data['rivolgoArr'] ?? [], 'name', 'name'),
                'signoffs' => $this->normalize_items($data['firmoArr'] ?? [], 'name', 'name'),
                'assistants' => $this->normalize_assistants($data['assistArr'] ?? []),
            ],
        ];
    }

    private function normalize_items(array $items, string $value_key, string $label_key, string $description_key = ''): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $value = isset($item[$value_key]) ? (string) $item[$value_key] : '';
            $label = isset($item[$label_key]) ? (string) $item[$label_key] : $value;
            if ($value === '' && $label === '') {
                continue;
            }

            $row = [
                'value' => sanitize_text_field($value),
                'label' => sanitize_text_field($label),
            ];
            if ($description_key !== '' && isset($item[$description_key])) {
                $row['description'] = sanitize_text_field((string) $item[$description_key]);
            }
            $normalized[] = $row;
        }

        return $normalized;
    }

    private function normalize_assistants(array $items): array
    {
        $normalized = [['value' => '', 'label' => 'Nessuno', 'description' => 'Nessun assistente specialistico']];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalized[] = [
                'value' => sanitize_text_field($item['assist_id'] ?? ''),
                'label' => sanitize_text_field($item['name'] ?? ''),
                'description' => sanitize_text_field($item['description'] ?? ''),
                'icon' => esc_url_raw($item['icon'] ?? ''),
            ];
        }

        return $normalized;
    }

    private function request_chat_id(array $settings, array $account): string
    {
        $tenant = V24_SMH_AI_Tenant_Resolver::resolve($settings);
        if (!$this->is_ai_ready($settings, $tenant)) {
            return '';
        }

        $remote = $this->remote_request('getnewchatidusingemailai', [
            'useremail' => $account['email_address'],
        ], $settings, $tenant);

        if (is_wp_error($remote)) {
            return '';
        }

        return sanitize_text_field($remote['chat_id'] ?? '');
    }

    private function store_remote_response(array $remote, array $context): array
    {
        $generated_html = $this->response_html($remote);
        $generated_text = trim(wp_strip_all_tags($generated_html));
        $hidden_html = wp_kses_post($remote['msg_hidden1'] ?? ($remote['msg_hidden'] ?? ''));
        $remote_chat_id = sanitize_text_field($remote['chat_id'] ?? ($remote['chatid'] ?? ''));
        $remote_generation_id = sanitize_text_field($remote['gen_id'] ?? '');
        if ($remote_generation_id === '' || $remote_generation_id === '0') {
            $remote_generation_id = sanitize_text_field($remote['res_id'] ?? $remote_generation_id);
        }

        $repository = new V24_SMH_AI_Response_Repository();
        $id = $repository->create(array_merge($context, [
            'user_id' => get_current_user_id(),
            'remote_chat_id' => $remote_chat_id,
            'remote_generation_id' => $remote_generation_id,
            'generated_subject' => sanitize_text_field($remote['ogetto'] ?? ($remote['subject'] ?? '')),
            'generated_html' => $generated_html,
            'generated_text' => $generated_text,
            'hidden_html' => $hidden_html,
            'assistant_name' => sanitize_text_field($remote['assistname'] ?? ''),
            'metrics_json' => [
                'usage_token' => $remote['usage_token'] ?? '',
                'total_usage_token' => $remote['total_usage_token'] ?? '',
                'tempo' => $remote['tempo'] ?? '',
                'characters' => $remote['Caratteri'] ?? '',
                'words' => $remote['Parole'] ?? '',
            ],
            'raw_response_json' => $remote,
        ]));

        $stored = $repository->find($id);
        return $this->present_response($stored ?: []);
    }

    private function response_html(array $remote): string
    {
        $html = $remote['msgraworiginal'] ?? '';
        if ($html === '') {
            $html = nl2br(esc_html((string) ($remote['msg'] ?? '')));
        }

        return wp_kses_post($html);
    }

    private function has_meaningful_html(string $html): bool
    {
        return trim(wp_strip_all_tags($html)) !== '';
    }

    private function build_local_preview_html(array $response): string
    {
        $body = trim((string) ($response['generated_html'] ?? ''));
        if ($body === '') {
            $body = '<p>Nessun contenuto disponibile.</p>';
        }

        return '<html><body><div>' . $body . '</div></body></html>';
    }

    private function should_fallback_to_local_delivery(array $response): bool
    {
        $remote_generation_id = trim((string) ($response['remote_generation_id'] ?? ''));
        return $remote_generation_id === '' || $remote_generation_id === '0' || str_starts_with($remote_generation_id, 'message_');
    }

    private function send_local_response_email(array $account, string $recipient, string $subject, array $response, $previous_error = null)
    {
        if ($recipient === '') {
            return new WP_Error('validation_error', 'Destinatario non valido.', ['status' => 400]);
        }

        $body_html = trim((string) ($response['generated_html'] ?? ''));
        if ($body_html === '') {
            $body_html = wpautop(esc_html((string) ($response['generated_text'] ?? '')));
        }

        if (!$this->has_meaningful_html($body_html)) {
            return new WP_Error('validation_error', 'Il contenuto della risposta AI e vuoto.', ['status' => 400]);
        }

        $result = (new V24_SMH_SMTP_Client())->send($account, [
            'to' => [$recipient],
            'cc' => [],
            'bcc' => [],
            'subject' => $subject,
            'body' => $body_html,
            'body_plain' => trim(wp_strip_all_tags($body_html)),
            'body_format' => 'html',
        ]);

        if (is_wp_error($result)) {
            return $previous_error instanceof WP_Error ? $previous_error : $result;
        }

        return [
            'sent' => true,
            'delivery_mode' => 'local_fallback',
            'message_id' => $result['message_id'] ?? '',
        ];
    }

    private function present_response(array $response): array
    {
        $metrics = !empty($response['metrics_json']) ? json_decode((string) $response['metrics_json'], true) : [];
        $preferences = !empty($response['preferences_json']) ? json_decode((string) $response['preferences_json'], true) : [];

        return [
            'id' => (int) ($response['id'] ?? 0),
            'account_id' => (int) ($response['account_id'] ?? 0),
            'message_id' => !empty($response['message_id']) ? (int) $response['message_id'] : 0,
            'source_response_id' => !empty($response['source_response_id']) ? (int) $response['source_response_id'] : 0,
            'compose_mode' => $response['compose_mode'] ?? 'new',
            'response_mode' => $response['response_mode'] ?? 'generate',
            'tenant_key' => $response['tenant_key'] ?? 'default',
            'chat_id' => $response['remote_chat_id'] ?? '',
            'remote_generation_id' => $response['remote_generation_id'] ?? '',
            'prompt_text' => $response['prompt_text'] ?? '',
            'generated_subject' => $response['generated_subject'] ?? '',
            'generated_html' => $response['generated_html'] ?? '',
            'generated_text' => $response['generated_text'] ?? '',
            'hidden_html' => $response['hidden_html'] ?? '',
            'assistant_id' => $response['assistant_id'] ?? '',
            'assistant_name' => $response['assistant_name'] ?? '',
            'privacy_mode' => !empty($response['privacy_mode']),
            'is_concise' => !empty($response['is_concise']),
            'is_ignored' => !empty($response['is_ignored']),
            'audio_url' => $response['audio_url'] ?? '',
            'rating_value' => !empty($response['rating_value']) ? (int) $response['rating_value'] : 0,
            'rating_comment' => $response['rating_comment'] ?? '',
            'preferences' => is_array($preferences) ? $preferences : [],
            'metrics' => is_array($metrics) ? $metrics : [],
            'created_at' => $response['created_at'] ?? '',
        ];
    }

    private function is_ai_ready(array $settings, array $tenant): bool
    {
        return !empty($settings['ai_enabled']) && trim((string) ($settings['ai_proxy_token'] ?? '')) !== '' && trim((string) ($tenant['endpoint'] ?? '')) !== '';
    }

    private function remote_request(string $path, array $payload, array $settings, array $tenant)
    {
        $endpoint = trim((string) ($tenant['endpoint'] ?? ''));
        if ($endpoint === '') {
            return new WP_Error('ai_not_configured', 'Endpoint AI non configurato.', ['status' => 400]);
        }

        $token = trim((string) ($settings['ai_proxy_token'] ?? ''));
        if ($token !== '') {
            $payload['token'] = $token;
        }

        $response = wp_remote_post(trailingslashit($endpoint) . ltrim($path, '/'), [
            'timeout' => 120,
            'body' => $payload,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return new WP_Error('ai_remote_invalid', 'Risposta AI non valida.', ['status' => 502, 'body' => $body]);
        }

        return $decoded;
    }

    private function remote_upload_audio(string $endpoint, array $settings, array $account, array $file)
    {
        if ($endpoint === '') {
            return new WP_Error('ai_not_configured', 'Endpoint AI non configurato.', ['status' => 400]);
        }

        if (!function_exists('curl_init')) {
            return new WP_Error('ai_upload_unavailable', 'cURL non disponibile per la trascrizione audio.', ['status' => 500]);
        }

        $payload = [
            'url' => 'set_audio_to_text_email_ai',
            'action' => 'curl_request_set_audio_to_text_email_ai',
            'useremail' => $account['email_address'],
            'autocall' => false,
        ];

        $token = trim((string) ($settings['ai_proxy_token'] ?? ''));
        if ($token !== '') {
            $payload['token'] = $token;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, trailingslashit($endpoint) . 'set_audio_to_text_email_ai');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'arr' => $payload,
            'files' => curl_file_create($file['tmp_name'], $file['type'] ?: 'audio/webm', $file['name'] ?: 'audio.webm'),
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return new WP_Error('ai_upload_failed', $error ?: 'Trascrizione non riuscita.', ['status' => 502]);
        }

        curl_close($ch);
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return new WP_Error('ai_upload_invalid', 'Risposta trascrizione non valida.', ['status' => 502]);
        }

        return sanitize_textarea_field($decoded['msg'] ?? '');
    }

    private function uploads_url_from_endpoint(string $endpoint): string
    {
        $parts = wp_parse_url($endpoint);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        return sprintf('%s://%s/wp-content/uploads/', $parts['scheme'], $parts['host']);
    }
}
