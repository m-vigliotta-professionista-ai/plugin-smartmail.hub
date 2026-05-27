<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Mail_Rules_Engine
{
    public function apply_account(int $account_id, array $options = [])
    {
        $account = (new V24_SMH_Account_Repository())->find($account_id);
        if (!$account) {
            return new WP_Error('account_not_found', 'Account non trovato.', ['status' => 404]);
        }

        $rules_repo = new V24_SMH_Mail_Rules_Repository();
        $rules = $rules_repo->active_for_account($account_id, !empty($options['rule_id']) ? (int) $options['rule_id'] : null);
        if (!$rules) {
            return ['processed' => 0, 'matched' => 0, 'rules' => 0];
        }

        $mail_repo = new V24_SMH_Mail_Repository();
        $folder_repo = new V24_SMH_Folder_Repository();
        $folders = [];
        foreach ($folder_repo->list_by_account($account_id) as $folder) {
            $folders[(int) $folder['id']] = $folder;
        }

        $messages = $mail_repo->list_for_rules(
            $account_id,
            !empty($options['folder_id']) ? (int) $options['folder_id'] : null,
            !empty($options['message_ids']) ? array_map('intval', (array) $options['message_ids']) : []
        );

        $processed = 0;
        $matched = 0;
        $imap = new V24_SMH_IMAP_Client();

        foreach ($messages as $message) {
            $processed++;
            $message_matched = false;
            $current_folder = $folders[(int) $message['folder_id']] ?? null;
            if (!$current_folder) {
                continue;
            }

            foreach ($rules as $rule) {
                $conditions = json_decode((string) ($rule['conditions_json'] ?? '{}'), true) ?: [];
                $actions = json_decode((string) ($rule['actions_json'] ?? '{}'), true) ?: [];

                if (!$this->matches($message, $conditions, $rule['match_mode'] ?? 'all', $mail_repo)) {
                    $rules_repo->touch_run((int) $rule['id'], false);
                    continue;
                }

                $message_matched = true;
                $matched++;
                $result = $this->apply_actions($account, $current_folder, $message, $actions, $imap, $mail_repo, $folder_repo, $folders);
                $rules_repo->touch_run((int) $rule['id'], true);

                if (!empty($result['stop'])) {
                    break;
                }

                if (!empty($rule['stop_processing'])) {
                    break;
                }
            }

            if ($message_matched) {
                V24_SMH_Audit_Log::record('mail_rules_applied', [
                    'account_id' => $account_id,
                    'entity_type' => 'message',
                    'entity_id' => (int) $message['id'],
                ]);
            }
        }

        return [
            'processed' => $processed,
            'matched' => $matched,
            'rules' => count($rules),
        ];
    }

    private function matches(array $message, array $conditions, string $match_mode, V24_SMH_Mail_Repository $mail_repo): bool
    {
        $checks = [];
        $has_real_condition = false;

        if (!empty($conditions['match_all'])) {
            $checks[] = true;
            $has_real_condition = true;
        }

        if (!empty($conditions['from_contains'])) {
            $needle = mb_strtolower((string) $conditions['from_contains']);
            $haystack = mb_strtolower(trim((string) ($message['from_name'] ?? '') . ' ' . (string) ($message['from_email'] ?? '')));
            $checks[] = strpos($haystack, $needle) !== false;
            $has_real_condition = true;
        }

        if (!empty($conditions['subject_contains'])) {
            $checks[] = strpos(mb_strtolower((string) ($message['subject'] ?? '')), mb_strtolower((string) $conditions['subject_contains'])) !== false;
            $has_real_condition = true;
        }

        if (!empty($conditions['preview_contains'])) {
            $checks[] = strpos(mb_strtolower((string) ($message['preview_text'] ?? '')), mb_strtolower((string) $conditions['preview_contains'])) !== false;
            $has_real_condition = true;
        }

        if (!empty($conditions['recipients_contains'])) {
            $recipients = $mail_repo->get_recipients((int) $message['id']);
            $haystack = mb_strtolower(implode(' ', array_map(static function ($recipient) {
                return trim((string) ($recipient['name'] ?? '') . ' ' . (string) ($recipient['email'] ?? ''));
            }, $recipients)));
            $checks[] = strpos($haystack, mb_strtolower((string) $conditions['recipients_contains'])) !== false;
            $has_real_condition = true;
        }

        if (!empty($conditions['folder_id'])) {
            $checks[] = (int) $message['folder_id'] === (int) $conditions['folder_id'];
            $has_real_condition = true;
        }

        foreach (['has_attachments' => 'has_attachments', 'is_seen' => 'is_seen', 'is_flagged' => 'is_flagged'] as $condition_key => $message_key) {
            if (array_key_exists($condition_key, $conditions) && $conditions[$condition_key] !== null) {
                $checks[] = (bool) $message[$message_key] === (bool) $conditions[$condition_key];
                $has_real_condition = true;
            }
        }

        if (!$has_real_condition) {
            return false;
        }

        if ($match_mode === 'any') {
            return in_array(true, $checks, true);
        }

        return !in_array(false, $checks, true);
    }

    private function apply_actions(
        array $account,
        array $current_folder,
        array $message,
        array $actions,
        V24_SMH_IMAP_Client $imap,
        V24_SMH_Mail_Repository $mail_repo,
        V24_SMH_Folder_Repository $folder_repo,
        array $folders
    ): array {
        $stop = false;

        if (array_key_exists('mark_seen', $actions) && $actions['mark_seen'] !== null) {
            $imap->set_seen($account, $current_folder['full_name'], (int) $message['uid'], (bool) $actions['mark_seen']);
            $mail_repo->update_seen((int) $message['id'], (bool) $actions['mark_seen']);
        }

        if (array_key_exists('set_flagged', $actions) && $actions['set_flagged'] !== null) {
            $imap->set_flagged($account, $current_folder['full_name'], (int) $message['uid'], (bool) $actions['set_flagged']);
            $mail_repo->update_flagged((int) $message['id'], (bool) $actions['set_flagged']);
        }

        if (!empty($actions['move_to_folder_id'])) {
            $target_folder = $folders[(int) $actions['move_to_folder_id']] ?? null;
            if ($target_folder && (int) $target_folder['id'] !== (int) $current_folder['id']) {
                $imap->move_message($account, $current_folder['full_name'], (int) $message['uid'], $target_folder['full_name']);
                $mail_repo->mark_deleted((int) $message['id']);
                $counts = $mail_repo->folder_counts((int) $current_folder['id']);
                $folder_repo->update_counts((int) $current_folder['id'], $counts['total'], $counts['unseen']);
                $stop = true;
            }
        }

        if (!empty($actions['delete_message'])) {
            $imap->delete_message($account, $current_folder['full_name'], (int) $message['uid']);
            $mail_repo->mark_deleted((int) $message['id']);
            $counts = $mail_repo->folder_counts((int) $current_folder['id']);
            $folder_repo->update_counts((int) $current_folder['id'], $counts['total'], $counts['unseen']);
            $stop = true;
        }

        return ['stop' => $stop];
    }
}
