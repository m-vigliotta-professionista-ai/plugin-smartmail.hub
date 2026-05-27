<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Tasks_Service
{
    private $repository;

    public function __construct(?V24_SMH_Tasks_Repository $repository = null)
    {
        $this->repository = $repository ?: new V24_SMH_Tasks_Repository();
    }

    public function create(array $input)
    {
        $payload = $this->normalize($input);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $payload['owner_user_id'] = get_current_user_id();
        $payload['created_by'] = get_current_user_id();
        $id = $this->repository->create($payload);

        V24_SMH_Audit_Log::record('task_created', ['entity_type' => 'task', 'entity_id' => $id]);
        return ['id' => $id];
    }

    public function update(int $task_id, array $input)
    {
        $existing = $this->repository->find_visible($task_id, get_current_user_id());
        if (!$existing) {
            return new WP_Error('task_not_found', 'Task non trovato.', ['status' => 404]);
        }

        if (!$this->can_edit($existing)) {
            return new WP_Error('task_forbidden', 'Permessi insufficienti sul task.', ['status' => 403]);
        }

        $payload = $this->normalize($input, $existing);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $this->repository->update($task_id, $payload);
        V24_SMH_Audit_Log::record('task_updated', ['entity_type' => 'task', 'entity_id' => $task_id]);
        return ['id' => $task_id];
    }

    public function delete(int $task_id)
    {
        $existing = $this->repository->find_visible($task_id, get_current_user_id());
        if (!$existing) {
            return new WP_Error('task_not_found', 'Task non trovato.', ['status' => 404]);
        }

        if ((int) $existing['owner_user_id'] !== get_current_user_id()) {
            return new WP_Error('task_forbidden', 'Solo il proprietario puo eliminare il task.', ['status' => 403]);
        }

        $this->repository->delete($task_id);
        V24_SMH_Audit_Log::record('task_deleted', ['entity_type' => 'task', 'entity_id' => $task_id]);
        return ['id' => $task_id];
    }

    public function find(int $task_id)
    {
        $task = $this->repository->find_visible($task_id, get_current_user_id());
        if (!$task) {
            return new WP_Error('task_not_found', 'Task non trovato.', ['status' => 404]);
        }

        return $this->decorate($task);
    }

    public function list(array $filters = []): array
    {
        return array_map([$this, 'decorate'], $this->repository->list_visible(get_current_user_id(), $filters));
    }

    private function normalize(array $input, ?array $existing = null)
    {
        $title = sanitize_text_field($input['title'] ?? ($existing['title'] ?? ''));
        if ($title === '') {
            return new WP_Error('validation_error', 'Titolo task obbligatorio.', ['status' => 400]);
        }

        $status = sanitize_key($input['status'] ?? ($existing['status'] ?? 'not_started'));
        if (!in_array($status, ['not_started', 'in_progress', 'waiting', 'completed', 'deferred'], true)) {
            $status = 'not_started';
        }

        $priority = sanitize_key($input['priority'] ?? ($existing['priority'] ?? 'normal'));
        if (!in_array($priority, ['low', 'normal', 'high'], true)) {
            $priority = 'normal';
        }

        $assigned_user_id = isset($input['assigned_user_id']) ? (int) $input['assigned_user_id'] : (int) ($existing['assigned_user_id'] ?? 0);
        if ($assigned_user_id > 0 && !get_userdata($assigned_user_id)) {
            return new WP_Error('validation_error', 'Utente assegnato non valido.', ['status' => 400]);
        }

        $percent_complete = isset($input['percent_complete']) ? (int) $input['percent_complete'] : (int) ($existing['percent_complete'] ?? 0);
        $percent_complete = max(0, min(100, $percent_complete));

        $completed_at = sanitize_text_field($input['completed_at'] ?? ($existing['completed_at'] ?? ''));
        if ($status === 'completed' && $completed_at === '') {
            $completed_at = current_time('mysql');
        } elseif ($status !== 'completed') {
            $completed_at = '';
        }

        $related_message_id = !empty($input['related_message_id'])
            ? (int) $input['related_message_id']
            : (!empty($existing['related_message_id']) ? (int) $existing['related_message_id'] : null);
        if ($related_message_id) {
            $related_message_id = $this->validate_related_message_id((int) $related_message_id);
            if (is_wp_error($related_message_id)) {
                return $related_message_id;
            }
        }

        $related_contact_id = !empty($input['related_contact_id'])
            ? (int) $input['related_contact_id']
            : (!empty($existing['related_contact_id']) ? (int) $existing['related_contact_id'] : null);
        if ($related_contact_id) {
            $related_contact_id = $this->validate_related_contact_id((int) $related_contact_id);
            if (is_wp_error($related_contact_id)) {
                return $related_contact_id;
            }
        }

        $related_event_id = !empty($input['related_event_id'])
            ? (int) $input['related_event_id']
            : (!empty($existing['related_event_id']) ? (int) $existing['related_event_id'] : null);
        if ($related_event_id) {
            $related_event_id = $this->validate_related_event_id((int) $related_event_id);
            if (is_wp_error($related_event_id)) {
                return $related_event_id;
            }
        }

        return [
            'assigned_user_id' => $assigned_user_id ?: null,
            'title' => $title,
            'description' => sanitize_textarea_field($input['description'] ?? ($existing['description'] ?? '')),
            'status' => $status,
            'priority' => $priority,
            'start_at' => sanitize_text_field($input['start_at'] ?? ($existing['start_at'] ?? '')),
            'due_at' => sanitize_text_field($input['due_at'] ?? ($existing['due_at'] ?? '')),
            'reminder_at' => sanitize_text_field($input['reminder_at'] ?? ($existing['reminder_at'] ?? '')),
            'completed_at' => $completed_at ?: null,
            'percent_complete' => $status === 'completed' ? 100 : $percent_complete,
            'related_message_id' => $related_message_id ?: null,
            'related_contact_id' => $related_contact_id ?: null,
            'related_event_id' => $related_event_id ?: null,
            'categories' => array_values(array_filter(array_map('sanitize_text_field', (array) ($input['categories'] ?? (json_decode((string) ($existing['categories_json'] ?? '[]'), true) ?: []))))),
        ];
    }

    private function decorate(array $task): array
    {
        $assigned_user = !empty($task['assigned_user_id']) ? get_userdata((int) $task['assigned_user_id']) : null;
        $owner_user = !empty($task['owner_user_id']) ? get_userdata((int) $task['owner_user_id']) : null;
        $task['categories'] = json_decode((string) ($task['categories_json'] ?? '[]'), true) ?: [];
        $task['assigned_user_name'] = $assigned_user ? ($assigned_user->display_name ?: $assigned_user->user_login) : '';
        $task['owner_user_name'] = $owner_user ? ($owner_user->display_name ?: $owner_user->user_login) : '';
        $task['related_message_label'] = $this->related_message_label((int) ($task['related_message_id'] ?? 0));
        $task['related_contact_label'] = $this->related_contact_label((int) ($task['related_contact_id'] ?? 0));
        $task['related_event_label'] = $this->related_event_label((int) ($task['related_event_id'] ?? 0));
        $task['can_edit'] = $this->can_edit($task);
        $task['can_delete'] = (int) $task['owner_user_id'] === get_current_user_id();
        return $task;
    }

    private function can_edit(array $task): bool
    {
        return (int) $task['owner_user_id'] === get_current_user_id() || (int) ($task['assigned_user_id'] ?? 0) === get_current_user_id();
    }

    private function validate_related_message_id(int $message_id)
    {
        $message = (new V24_SMH_Mail_Repository())->find($message_id);
        if (!$message || !empty($message['is_deleted']) || !V24_SMH_Permissions::current_user_can_account((int) $message['account_id'], 'v24_smh_read_mail')) {
            return new WP_Error('validation_error', 'Messaggio collegato non valido.', ['status' => 400]);
        }

        return $message_id;
    }

    private function validate_related_contact_id(int $contact_id)
    {
        $contact = (new V24_SMH_Contacts_Repository())->find($contact_id);
        if (!$contact) {
            return new WP_Error('validation_error', 'Contatto collegato non valido.', ['status' => 400]);
        }

        return $contact_id;
    }

    private function validate_related_event_id(int $event_id)
    {
        $event = (new V24_SMH_Calendar_Service())->find($event_id);
        if (is_wp_error($event) || !$event) {
            return new WP_Error('validation_error', 'Evento collegato non valido.', ['status' => 400]);
        }

        return $event_id;
    }

    private function related_message_label(int $message_id): string
    {
        if ($message_id <= 0) {
            return '';
        }

        $message = (new V24_SMH_Mail_Repository())->find($message_id);
        if (!$message || !empty($message['is_deleted']) || !V24_SMH_Permissions::current_user_can_account((int) $message['account_id'], 'v24_smh_read_mail')) {
            return 'Messaggio non disponibile';
        }

        $subject = trim((string) ($message['subject'] ?? ''));
        if ($subject !== '') {
            return $subject;
        }

        $from = trim((string) ($message['from_name'] ?? ''));
        if ($from !== '') {
            return $from;
        }

        return trim((string) ($message['from_email'] ?? '')) ?: 'Messaggio #' . $message_id;
    }

    private function related_contact_label(int $contact_id): string
    {
        if ($contact_id <= 0) {
            return '';
        }

        $contact = (new V24_SMH_Contacts_Repository())->find($contact_id);
        if (!$contact) {
            return 'Contatto non disponibile';
        }

        return trim((string) ($contact['display_name'] ?? '')) ?: (trim((string) ($contact['primary_email'] ?? '')) ?: 'Contatto #' . $contact_id);
    }

    private function related_event_label(int $event_id): string
    {
        if ($event_id <= 0) {
            return '';
        }

        $event = (new V24_SMH_Calendar_Service())->find($event_id);
        if (is_wp_error($event) || !$event) {
            return 'Evento non disponibile';
        }

        $title = trim((string) ($event['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return 'Evento #' . $event_id;
    }
}
