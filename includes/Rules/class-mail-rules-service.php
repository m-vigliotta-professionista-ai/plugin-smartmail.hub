<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Mail_Rules_Service
{
    private $repository;

    public function __construct(?V24_SMH_Mail_Rules_Repository $repository = null)
    {
        $this->repository = $repository ?: new V24_SMH_Mail_Rules_Repository();
    }

    public function list(int $account_id)
    {
        if (!V24_SMH_Permissions::current_user_can_account($account_id, 'v24_smh_manage_mail')) {
            return new WP_Error('account_forbidden', 'Permessi insufficienti sull account selezionato.', ['status' => 403]);
        }

        return array_map([$this, 'decorate'], $this->repository->list_for_user(get_current_user_id(), $account_id));
    }

    public function find(int $rule_id)
    {
        $rule = $this->repository->find_visible($rule_id, get_current_user_id());
        if (!$rule || !V24_SMH_Permissions::current_user_can_account((int) $rule['account_id'], 'v24_smh_manage_mail')) {
            return new WP_Error('rule_not_found', 'Regola non trovata.', ['status' => 404]);
        }

        return $this->decorate($rule);
    }

    public function create(array $input)
    {
        $payload = $this->normalize($input);
        if (is_wp_error($payload)) {
            return $payload;
        }

        if (!V24_SMH_Permissions::current_user_can_account((int) $payload['account_id'], 'v24_smh_manage_mail')) {
            return new WP_Error('account_forbidden', 'Permessi insufficienti sull account selezionato.', ['status' => 403]);
        }

        $payload['owner_user_id'] = get_current_user_id();
        $id = $this->repository->create($payload);

        V24_SMH_Audit_Log::record('mail_rule_created', ['account_id' => (int) $payload['account_id'], 'entity_type' => 'mail_rule', 'entity_id' => $id]);
        return ['id' => $id];
    }

    public function update(int $rule_id, array $input)
    {
        $existing = $this->repository->find_visible($rule_id, get_current_user_id());
        if (!$existing || !V24_SMH_Permissions::current_user_can_account((int) $existing['account_id'], 'v24_smh_manage_mail')) {
            return new WP_Error('rule_not_found', 'Regola non trovata.', ['status' => 404]);
        }

        $payload = $this->normalize($input, $existing);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $this->repository->update($rule_id, $payload);
        V24_SMH_Audit_Log::record('mail_rule_updated', ['account_id' => (int) $existing['account_id'], 'entity_type' => 'mail_rule', 'entity_id' => $rule_id]);
        return ['id' => $rule_id];
    }

    public function delete(int $rule_id)
    {
        $existing = $this->repository->find_visible($rule_id, get_current_user_id());
        if (!$existing || !V24_SMH_Permissions::current_user_can_account((int) $existing['account_id'], 'v24_smh_manage_mail')) {
            return new WP_Error('rule_not_found', 'Regola non trovata.', ['status' => 404]);
        }

        $this->repository->delete($rule_id);
        V24_SMH_Audit_Log::record('mail_rule_deleted', ['account_id' => (int) $existing['account_id'], 'entity_type' => 'mail_rule', 'entity_id' => $rule_id]);
        return ['id' => $rule_id];
    }

    public function run(int $account_id, ?int $rule_id = null)
    {
        if (!V24_SMH_Permissions::current_user_can_account($account_id, 'v24_smh_manage_mail')) {
            return new WP_Error('account_forbidden', 'Permessi insufficienti sull account selezionato.', ['status' => 403]);
        }

        return (new V24_SMH_Mail_Rules_Engine())->apply_account($account_id, [
            'rule_id' => $rule_id,
        ]);
    }

    private function normalize(array $input, ?array $existing = null)
    {
        $account_id = (int) ($input['account_id'] ?? ($existing['account_id'] ?? 0));
        if ($account_id <= 0) {
            return new WP_Error('validation_error', 'Account regola obbligatorio.', ['status' => 400]);
        }

        $name = sanitize_text_field($input['name'] ?? ($existing['name'] ?? ''));
        if ($name === '') {
            return new WP_Error('validation_error', 'Nome regola obbligatorio.', ['status' => 400]);
        }

        $folder_repo = new V24_SMH_Folder_Repository();
        $condition_folder_id = !empty($input['conditions']['folder_id']) ? (int) $input['conditions']['folder_id'] : (!empty($existing['conditions_json']) ? ((json_decode((string) $existing['conditions_json'], true)['folder_id'] ?? 0)) : 0);
        if ($condition_folder_id > 0) {
            $folder = $folder_repo->find($condition_folder_id);
            if (!$folder || (int) $folder['account_id'] !== $account_id) {
                return new WP_Error('validation_error', 'Cartella condizione non valida.', ['status' => 400]);
            }
        }

        $move_to_folder_id = !empty($input['actions']['move_to_folder_id']) ? (int) $input['actions']['move_to_folder_id'] : (!empty($existing['actions_json']) ? ((json_decode((string) $existing['actions_json'], true)['move_to_folder_id'] ?? 0)) : 0);
        if ($move_to_folder_id > 0) {
            $target_folder = $folder_repo->find($move_to_folder_id);
            if (!$target_folder || (int) $target_folder['account_id'] !== $account_id) {
                return new WP_Error('validation_error', 'Cartella azione non valida.', ['status' => 400]);
            }
        }

        $existing_conditions = json_decode((string) ($existing['conditions_json'] ?? '{}'), true) ?: [];
        $existing_actions = json_decode((string) ($existing['actions_json'] ?? '{}'), true) ?: [];

        $conditions = [
            'match_all' => !empty($input['conditions']['match_all']) || !empty($existing_conditions['match_all']),
            'from_contains' => sanitize_text_field($input['conditions']['from_contains'] ?? ($existing_conditions['from_contains'] ?? '')),
            'subject_contains' => sanitize_text_field($input['conditions']['subject_contains'] ?? ($existing_conditions['subject_contains'] ?? '')),
            'preview_contains' => sanitize_text_field($input['conditions']['preview_contains'] ?? ($existing_conditions['preview_contains'] ?? '')),
            'recipients_contains' => sanitize_text_field($input['conditions']['recipients_contains'] ?? ($existing_conditions['recipients_contains'] ?? '')),
            'folder_id' => $condition_folder_id ?: null,
            'has_attachments' => $this->normalize_nullable_bool($input['conditions'] ?? [], 'has_attachments', $existing_conditions),
            'is_seen' => $this->normalize_nullable_bool($input['conditions'] ?? [], 'is_seen', $existing_conditions),
            'is_flagged' => $this->normalize_nullable_bool($input['conditions'] ?? [], 'is_flagged', $existing_conditions),
        ];

        $actions = [
            'move_to_folder_id' => $move_to_folder_id ?: null,
            'mark_seen' => $this->normalize_nullable_bool($input['actions'] ?? [], 'mark_seen', $existing_actions),
            'set_flagged' => $this->normalize_nullable_bool($input['actions'] ?? [], 'set_flagged', $existing_actions),
            'delete_message' => !empty($input['actions']['delete_message']) || !empty($existing_actions['delete_message']),
        ];

        $has_condition = !empty($conditions['match_all'])
            || $conditions['from_contains'] !== ''
            || $conditions['subject_contains'] !== ''
            || $conditions['preview_contains'] !== ''
            || $conditions['recipients_contains'] !== ''
            || $conditions['folder_id'] !== null
            || $conditions['has_attachments'] !== null
            || $conditions['is_seen'] !== null
            || $conditions['is_flagged'] !== null;

        if (!$has_condition) {
            return new WP_Error('validation_error', 'Inserisci almeno una condizione o abilita Applica a tutti i messaggi.', ['status' => 400]);
        }

        $has_action = $actions['move_to_folder_id'] !== null
            || $actions['mark_seen'] !== null
            || $actions['set_flagged'] !== null
            || !empty($actions['delete_message']);

        if (!$has_action) {
            return new WP_Error('validation_error', 'Inserisci almeno un azione per la regola.', ['status' => 400]);
        }

        return [
            'account_id' => $account_id,
            'name' => $name,
            'is_active' => array_key_exists('is_active', $input) ? !empty($input['is_active']) : !empty($existing['is_active']),
            'sort_order' => isset($input['sort_order']) ? max(0, (int) $input['sort_order']) : (int) ($existing['sort_order'] ?? 0),
            'match_mode' => sanitize_key($input['match_mode'] ?? ($existing['match_mode'] ?? 'all')),
            'stop_processing' => array_key_exists('stop_processing', $input) ? !empty($input['stop_processing']) : !empty($existing['stop_processing']),
            'conditions' => $conditions,
            'actions' => $actions,
        ];
    }

    private function decorate(array $rule): array
    {
        $rule['conditions'] = json_decode((string) ($rule['conditions_json'] ?? '{}'), true) ?: [];
        $rule['actions'] = json_decode((string) ($rule['actions_json'] ?? '{}'), true) ?: [];
        $folder_repo = new V24_SMH_Folder_Repository();

        if (!empty($rule['conditions']['folder_id'])) {
            $folder = $folder_repo->find((int) $rule['conditions']['folder_id']);
            if ($folder && (int) $folder['account_id'] === (int) $rule['account_id']) {
                $rule['conditions']['folder_label'] = $this->folder_label($folder);
            }
        }

        if (!empty($rule['actions']['move_to_folder_id'])) {
            $folder = $folder_repo->find((int) $rule['actions']['move_to_folder_id']);
            if ($folder && (int) $folder['account_id'] === (int) $rule['account_id']) {
                $rule['actions']['move_to_folder_label'] = $this->folder_label($folder);
            }
        }

        return $rule;
    }

    private function normalize_nullable_bool(array $input, string $key, array $existing): ?bool
    {
        if (array_key_exists($key, $input)) {
            if ($input[$key] === null || $input[$key] === '') {
                return null;
            }

            return (bool) $input[$key];
        }

        if (!array_key_exists($key, $existing) || $existing[$key] === null || $existing[$key] === '') {
            return null;
        }

        return (bool) $existing[$key];
    }

    private function folder_label(array $folder): string
    {
        $full_name = trim((string) ($folder['full_name'] ?? ''));
        if ($full_name === '') {
            return 'Cartella';
        }

        if (strcasecmp($full_name, 'INBOX') === 0 || (string) ($folder['special_use'] ?? '') === 'inbox') {
            return 'INBOX';
        }

        $special_use = strtolower((string) ($folder['special_use'] ?? ''));
        $aliases = [
            'trash' => 'Cestino',
            'sent' => 'Posta inviata',
            'drafts' => 'Bozze',
            'archive' => 'Archivio',
            'junk' => 'Posta indesiderata',
        ];

        if (isset($aliases[$special_use])) {
            return $aliases[$special_use];
        }

        $delimiter = trim((string) ($folder['delimiter'] ?? '/')) ?: '/';
        $parts = array_values(array_filter(explode($delimiter, $full_name), 'strlen'));
        $label = end($parts);
        if ($label === false || $label === '') {
            $label = $full_name;
        }

        return $label;
    }
}
