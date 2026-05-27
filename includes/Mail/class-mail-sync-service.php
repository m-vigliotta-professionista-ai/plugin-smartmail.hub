<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Mail_Sync_Service
{
    public function sync_account(int $account_id, ?int $job_id = null)
    {
        $started = microtime(true);
        $accounts = new V24_SMH_Account_Repository();
        $folders = new V24_SMH_Folder_Repository();
        $mail = new V24_SMH_Mail_Repository();
        $attachments = new V24_SMH_Attachment_Repository();
        $imap = new V24_SMH_IMAP_Client();
        $account = $accounts->find($account_id);
        $settings = get_option('v24_smh_settings', []);
        $max_messages = max(1, (int) ((is_array($settings) ? ($settings['max_messages_per_sync'] ?? 50) : 50)));

        if (!$account) {
            return new WP_Error('invalid_account', 'Account non trovato.');
        }

        $remote_folders = $imap->list_folders($account);
        if (is_wp_error($remote_folders)) {
            V24_SMH_Sync_Log::record([
                'account_id' => $account_id,
                'job_id' => $job_id,
                'sync_type' => 'mail_sync',
                'status' => 'failed',
                'error_message' => $remote_folders->get_error_message(),
            ]);
            return $remote_folders;
        }

        $items_seen = 0;
        $items_created = 0;
        $items_updated = 0;

        foreach ($remote_folders as $remote_folder) {
            $folder_id = $folders->upsert($account_id, $remote_folder);
            $folder = $folders->find($folder_id);
            if (!$folder) {
                continue;
            }

            $headers = $imap->fetch_headers($account, $folder['full_name'], ((int) $folder['highest_uid']) + 1, $max_messages);
            if (is_wp_error($headers)) {
                continue;
            }

            $highest = (int) $folder['highest_uid'];
            $changed_message_ids = [];

            foreach ($headers as $header) {
                $existing = $mail->find((int) $mail->upsert_message($account_id, $folder_id, $header));
                $message_id = (int) ($existing['id'] ?? 0);
                if ($message_id <= 0) {
                    continue;
                }

                $mail->replace_recipients($message_id, $header['recipients'] ?? []);
                $attachments->replace_for_message($message_id, $account_id, $header['attachments'] ?? []);

                $highest = max($highest, (int) $header['uid']);
                $items_seen++;
                $changed_message_ids[] = $message_id;

                if (!empty($existing['created_at']) && $existing['created_at'] === $existing['updated_at']) {
                    $items_created++;
                } else {
                    $items_updated++;
                }
            }

            if ($changed_message_ids) {
                (new V24_SMH_Mail_Rules_Engine())->apply_account($account_id, [
                    'folder_id' => $folder_id,
                    'message_ids' => $changed_message_ids,
                ]);
            }

            $counts = $mail->folder_counts($folder_id);
            $folders->update_sync_state($folder_id, $highest, $counts['total'], $counts['unseen']);
        }

        V24_SMH_Sync_Log::record([
            'account_id' => $account_id,
            'job_id' => $job_id,
            'sync_type' => 'mail_sync',
            'status' => 'done',
            'items_seen' => $items_seen,
            'items_created' => $items_created,
            'items_updated' => $items_updated,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);

        return [
            'seen' => $items_seen,
            'created' => $items_created,
            'updated' => $items_updated,
        ];
    }
}
