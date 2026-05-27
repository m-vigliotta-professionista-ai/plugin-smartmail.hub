<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_REST_Mail_Controller
{
    public function register_routes(): void
    {
        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/folders', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'folders'],
            'permission_callback' => [$this, 'can_read_account'],
            'args' => [
                'account_id' => V24_SMH_REST_Bootstrap::arg_id('ID account email.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/folders/create', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'create_folder'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_read_mail');
            },
            'args' => [
                'account_id' => V24_SMH_REST_Bootstrap::arg_id('ID account email.'),
                'parent_folder_id' => V24_SMH_REST_Bootstrap::arg_optional_id('ID cartella padre.'),
                'name' => V24_SMH_REST_Bootstrap::arg_text('Nome nuova cartella.', true),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/folders/(?P<id>\d+)/rename', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rename_folder'],
            'permission_callback' => [$this, 'can_manage_folder'],
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID cartella.'),
                'name' => V24_SMH_REST_Bootstrap::arg_text('Nuovo nome cartella.', true),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/folders/(?P<id>\d+)/copy', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'copy_folder'],
            'permission_callback' => [$this, 'can_manage_folder'],
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID cartella.'),
                'name' => V24_SMH_REST_Bootstrap::arg_text('Nome cartella duplicata.', true),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/folders/(?P<id>\d+)/move', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'move_folder'],
            'permission_callback' => [$this, 'can_manage_folder'],
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID cartella.'),
                'target_full_name' => V24_SMH_REST_Bootstrap::arg_text('Percorso destinazione cartella.', true),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/folders/(?P<id>\d+)/delete', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'delete_folder'],
            'permission_callback' => [$this, 'can_manage_folder'],
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID cartella.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/folders/(?P<id>\d+)/mark-read', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'mark_folder_read'],
            'permission_callback' => [$this, 'can_manage_folder'],
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID cartella.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/folders/(?P<id>\d+)/empty', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'empty_folder'],
            'permission_callback' => [$this, 'can_manage_folder'],
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID cartella.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/folders/(?P<id>\d+)/purge', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'purge_folder'],
            'permission_callback' => [$this, 'can_manage_folder'],
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID cartella.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/messages', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'messages'],
            'permission_callback' => [$this, 'can_read_account'],
            'args' => array_merge(V24_SMH_REST_Bootstrap::pagination_args(), [
                'account_id' => V24_SMH_REST_Bootstrap::arg_id('ID account email.'),
                'folder_id' => V24_SMH_REST_Bootstrap::arg_optional_id('ID cartella email.'),
                'search' => V24_SMH_REST_Bootstrap::arg_search(),
                'seen' => V24_SMH_REST_Bootstrap::arg_bool('Filtro messaggi letti/non letti.'),
            ]),
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/messages/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'message'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_read_mail');
            },
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID messaggio.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/send', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'send'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
            'args' => [
                'account_id' => V24_SMH_REST_Bootstrap::arg_id('ID account mittente.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/messages/(?P<id>\d+)/reply', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'reply'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID messaggio.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/messages/(?P<id>\d+)/forward', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'forward'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_send_mail');
            },
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID messaggio.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/messages/(?P<id>\d+)/seen', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'seen'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_read_mail');
            },
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID messaggio.'),
                'is_seen' => V24_SMH_REST_Bootstrap::arg_bool('Stato letto.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/messages/(?P<id>\d+)/flag', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'flag'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_read_mail');
            },
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID messaggio.'),
                'is_flagged' => V24_SMH_REST_Bootstrap::arg_bool('Stato contrassegnato.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/messages/(?P<id>\d+)/move', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'move'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_read_mail');
            },
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID messaggio.'),
                'target_folder_id' => V24_SMH_REST_Bootstrap::arg_id('ID cartella destinazione.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/messages/(?P<id>\d+)/delete', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'delete'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_read_mail');
            },
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID messaggio.'),
            ],
        ]);

        register_rest_route(V24_SMH_REST_Bootstrap::NAMESPACE, '/mail/attachments/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'attachment'],
            'permission_callback' => static function () {
                return current_user_can('v24_smh_read_mail');
            },
            'args' => [
                'id' => V24_SMH_REST_Bootstrap::arg_id('ID allegato.'),
            ],
        ]);
    }

    public function can_read_account(WP_REST_Request $request): bool
    {
        return V24_SMH_Permissions::current_user_can_account((int) $request->get_param('account_id'));
    }

    public function can_manage_folder(WP_REST_Request $request): bool
    {
        $folder = (new V24_SMH_Folder_Repository())->find((int) $request['id']);
        if (!$folder) {
            return current_user_can('v24_smh_read_mail');
        }

        return V24_SMH_Permissions::current_user_can_account((int) $folder['account_id']);
    }

    public function folders(WP_REST_Request $request): WP_REST_Response
    {
        $data = (new V24_SMH_Folder_Repository())->list_by_account((int) $request->get_param('account_id'));
        return V24_SMH_REST_Bootstrap::success($data);
    }

    public function create_folder(WP_REST_Request $request)
    {
        $account = (new V24_SMH_Account_Repository())->find((int) $request->get_param('account_id'));
        if (!$account || !V24_SMH_Permissions::current_user_can_account((int) $account['id'])) {
            return new WP_Error('account_not_found', 'Account non trovato.', ['status' => 404]);
        }

        $name = $this->sanitize_folder_segment((string) $request->get_param('name'));
        if ($name === '') {
            return new WP_Error('invalid_folder_name', 'Nome cartella non valido.', ['status' => 400]);
        }

        $parent_folder = null;
        $parent_folder_id = (int) $request->get_param('parent_folder_id');
        if ($parent_folder_id > 0) {
            $parent_folder = (new V24_SMH_Folder_Repository())->find($parent_folder_id);
            if (!$parent_folder || (int) $parent_folder['account_id'] !== (int) $account['id']) {
                return new WP_Error('folder_not_found', 'Cartella padre non trovata.', ['status' => 404]);
            }
        }

        $delimiter = (string) ($parent_folder['delimiter'] ?? $this->default_folder_delimiter((int) $account['id']));
        $parent_full_name = (string) ($parent_folder['full_name'] ?? '');
        if ($parent_full_name === '') {
            $parent_full_name = $this->root_namespace_prefix((int) $account['id'], $delimiter);
        }

        $full_name = $this->build_folder_full_name($name, $parent_full_name, $delimiter);
        $result = (new V24_SMH_IMAP_Client())->create_folder($account, $full_name);
        if (is_wp_error($result)) {
            return $result;
        }

        $folders = $this->refresh_remote_folders($account);
        if (is_wp_error($folders)) {
            return $folders;
        }

        return V24_SMH_REST_Bootstrap::success([
            'folder' => $this->find_folder_from_list($folders, $full_name),
            'folders' => $folders,
        ]);
    }

    public function rename_folder(WP_REST_Request $request)
    {
        $context = $this->folder_context((int) $request['id']);
        if (is_wp_error($context)) {
            return $context;
        }

        if ($this->is_protected_folder($context['folder'])) {
            return new WP_Error('folder_locked', 'Questa cartella non puo essere rinominata.', ['status' => 400]);
        }

        $name = $this->sanitize_folder_segment((string) $request->get_param('name'));
        if ($name === '') {
            return new WP_Error('invalid_folder_name', 'Nome cartella non valido.', ['status' => 400]);
        }

        $delimiter = (string) ($context['folder']['delimiter'] ?: '/');
        $target_full_name = $this->build_folder_full_name($name, $this->folder_parent_path($context['folder']), $delimiter);
        if ($target_full_name === (string) $context['folder']['full_name']) {
            return V24_SMH_REST_Bootstrap::success(['folder' => $context['folder']]);
        }

        $result = (new V24_SMH_IMAP_Client())->rename_folder(
            $context['account'],
            (string) $context['folder']['full_name'],
            $target_full_name
        );
        if (is_wp_error($result)) {
            return $result;
        }

        $folder_repo = new V24_SMH_Folder_Repository();
        $folder_repo->update_folder((int) $context['folder']['id'], [
            'remote_id' => $target_full_name,
            'name' => $target_full_name,
            'full_name' => $target_full_name,
            'delimiter' => $delimiter,
            'special_use' => $context['folder']['special_use'] ?? null,
        ]);

        return V24_SMH_REST_Bootstrap::success([
            'folder' => $folder_repo->find((int) $context['folder']['id']),
        ]);
    }

    public function copy_folder(WP_REST_Request $request)
    {
        $context = $this->folder_context((int) $request['id']);
        if (is_wp_error($context)) {
            return $context;
        }

        $name = $this->sanitize_folder_segment((string) $request->get_param('name'));
        if ($name === '') {
            return new WP_Error('invalid_folder_name', 'Nome cartella non valido.', ['status' => 400]);
        }

        $delimiter = (string) ($context['folder']['delimiter'] ?: '/');
        $target_full_name = $this->build_folder_full_name($name, $this->folder_parent_path($context['folder']), $delimiter);
        $result = (new V24_SMH_IMAP_Client())->copy_folder(
            $context['account'],
            (string) $context['folder']['full_name'],
            $target_full_name
        );
        if (is_wp_error($result)) {
            return $result;
        }

        $folders = $this->refresh_remote_folders($context['account']);
        if (is_wp_error($folders)) {
            return $folders;
        }

        return V24_SMH_REST_Bootstrap::success([
            'folder' => $this->find_folder_from_list($folders, $target_full_name),
            'folders' => $folders,
            'requires_sync' => true,
        ]);
    }

    public function move_folder(WP_REST_Request $request)
    {
        $context = $this->folder_context((int) $request['id']);
        if (is_wp_error($context)) {
            return $context;
        }

        if ($this->is_protected_folder($context['folder'])) {
            return new WP_Error('folder_locked', 'Questa cartella non puo essere spostata.', ['status' => 400]);
        }

        $target_full_name = $this->sanitize_folder_path((string) $request->get_param('target_full_name'));
        if ($target_full_name === '') {
            return new WP_Error('invalid_folder_name', 'Percorso cartella non valido.', ['status' => 400]);
        }

        $root_prefix = $this->root_namespace_prefix((int) $context['account']['id'], (string) ($context['folder']['delimiter'] ?: '/'));
        if ($root_prefix !== '') {
            $target_full_name = $this->apply_root_namespace(
                $target_full_name,
                $root_prefix,
                (string) ($context['folder']['delimiter'] ?: '/')
            );
        }

        if ($target_full_name === (string) $context['folder']['full_name']) {
            return V24_SMH_REST_Bootstrap::success(['folder' => $context['folder']]);
        }

        $result = (new V24_SMH_IMAP_Client())->rename_folder(
            $context['account'],
            (string) $context['folder']['full_name'],
            $target_full_name
        );
        if (is_wp_error($result)) {
            return $result;
        }

        $folder_repo = new V24_SMH_Folder_Repository();
        $folder_repo->update_folder((int) $context['folder']['id'], [
            'remote_id' => $target_full_name,
            'name' => $target_full_name,
            'full_name' => $target_full_name,
            'delimiter' => $context['folder']['delimiter'] ?: '/',
            'special_use' => $context['folder']['special_use'] ?? null,
        ]);

        return V24_SMH_REST_Bootstrap::success([
            'folder' => $folder_repo->find((int) $context['folder']['id']),
        ]);
    }

    public function delete_folder(WP_REST_Request $request)
    {
        $context = $this->folder_context((int) $request['id']);
        if (is_wp_error($context)) {
            return $context;
        }

        if ($this->is_protected_folder($context['folder'])) {
            return new WP_Error('folder_locked', 'Questa cartella non puo essere eliminata.', ['status' => 400]);
        }

        $result = (new V24_SMH_IMAP_Client())->delete_folder($context['account'], (string) $context['folder']['full_name']);
        if (is_wp_error($result)) {
            return $result;
        }

        (new V24_SMH_Mail_Repository())->mark_folder_deleted((int) $context['folder']['id']);
        (new V24_SMH_Folder_Repository())->delete((int) $context['folder']['id']);
        $folders = $this->refresh_remote_folders($context['account'], true);
        if (is_wp_error($folders)) {
            return $folders;
        }

        return V24_SMH_REST_Bootstrap::success([
            'deleted' => true,
            'folders' => $folders,
        ]);
    }

    public function mark_folder_read(WP_REST_Request $request)
    {
        $context = $this->folder_context((int) $request['id']);
        if (is_wp_error($context)) {
            return $context;
        }

        $result = (new V24_SMH_IMAP_Client())->mark_folder_seen($context['account'], (string) $context['folder']['full_name']);
        if (is_wp_error($result)) {
            return $result;
        }

        $mail_repo = new V24_SMH_Mail_Repository();
        $mail_repo->mark_folder_seen((int) $context['folder']['id']);
        $counts = $mail_repo->folder_counts((int) $context['folder']['id']);
        (new V24_SMH_Folder_Repository())->update_counts((int) $context['folder']['id'], $counts['total'], $counts['unseen']);

        return V24_SMH_REST_Bootstrap::success([
            'folder_id' => (int) $context['folder']['id'],
            'unseen_count' => 0,
        ]);
    }

    public function empty_folder(WP_REST_Request $request)
    {
        $context = $this->folder_context((int) $request['id']);
        if (is_wp_error($context)) {
            return $context;
        }

        $trash_folder = $this->trash_folder_for_account((int) $context['account']['id']);
        $trash_full_name = $trash_folder && (int) $trash_folder['id'] !== (int) $context['folder']['id']
            ? (string) $trash_folder['full_name']
            : null;

        $result = (new V24_SMH_IMAP_Client())->empty_folder(
            $context['account'],
            (string) $context['folder']['full_name'],
            $trash_full_name
        );
        if (is_wp_error($result)) {
            return $result;
        }

        (new V24_SMH_Mail_Repository())->mark_folder_deleted((int) $context['folder']['id']);
        (new V24_SMH_Folder_Repository())->update_counts((int) $context['folder']['id'], 0, 0);

        return V24_SMH_REST_Bootstrap::success([
            'folder_id' => (int) $context['folder']['id'],
            'moved_to_trash' => (bool) $trash_full_name,
            'requires_sync' => (bool) $trash_full_name,
        ]);
    }

    public function purge_folder(WP_REST_Request $request)
    {
        $context = $this->folder_context((int) $request['id']);
        if (is_wp_error($context)) {
            return $context;
        }

        $result = (new V24_SMH_IMAP_Client())->purge_folder($context['account'], (string) $context['folder']['full_name']);
        if (is_wp_error($result)) {
            return $result;
        }

        (new V24_SMH_Mail_Repository())->mark_folder_deleted((int) $context['folder']['id']);
        (new V24_SMH_Folder_Repository())->update_counts((int) $context['folder']['id'], 0, 0);

        return V24_SMH_REST_Bootstrap::success([
            'folder_id' => (int) $context['folder']['id'],
            'purged' => true,
        ]);
    }

    public function messages(WP_REST_Request $request): WP_REST_Response
    {
        $page = max(1, (int) ($request->get_param('page') ?: 1));
        $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?: 25)));
        $search = sanitize_text_field((string) ($request->get_param('search') ?: ''));
        $seen_param = $request->get_param('seen');
        $seen = null;
        if ($seen_param !== null && $seen_param !== '') {
            $seen = filter_var($seen_param, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $result = (new V24_SMH_Mail_Repository())->list_messages(
            (int) $request->get_param('account_id'),
            (int) $request->get_param('folder_id'),
            $page,
            $per_page,
            $search,
            $seen
        );

        return V24_SMH_REST_Bootstrap::success($result['items'], [
            'pagination' => [
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
            ],
        ]);
    }

    public function message(WP_REST_Request $request)
    {
        $repo = new V24_SMH_Mail_Repository();
        $attachment_repo = new V24_SMH_Attachment_Repository();
        $message = $repo->find((int) $request['id']);

        if (!$message || !V24_SMH_Permissions::current_user_can_account((int) $message['account_id'])) {
            return new WP_Error('message_not_found', 'Messaggio non trovato.', ['status' => 404]);
        }

        $body = $repo->get_body((int) $message['id']);
        $recipients = $repo->get_recipients((int) $message['id']);
        $attachments = $attachment_repo->list_by_message((int) $message['id']);

        if (!$body || !$recipients || (!$attachments && !empty($message['has_attachments']))) {
            $hydrated = $this->hydrate_message($message);
            if (is_wp_error($hydrated)) {
                return $hydrated;
            }

            $body = $repo->get_body((int) $message['id']);
            $recipients = $repo->get_recipients((int) $message['id']);
            $attachments = $attachment_repo->list_by_message((int) $message['id']);
            $message = $repo->find((int) $message['id']) ?: $message;
        }

        return V24_SMH_REST_Bootstrap::success([
            'id' => (int) $message['id'],
            'account_id' => (int) $message['account_id'],
            'folder_id' => (int) $message['folder_id'],
            'subject' => $message['subject'] ?? '',
            'from_name' => $message['from_name'] ?? '',
            'from_email' => $message['from_email'] ?? '',
            'reply_to_email' => $message['reply_to_email'] ?? '',
            'date_received' => $message['date_received'] ?? '',
            'is_seen' => !empty($message['is_seen']),
            'is_flagged' => !empty($message['is_flagged']),
            'has_attachments' => !empty($message['has_attachments']),
            'body_plain' => $body['body_plain'] ?? '',
            'body_html' => $body['body_html_sanitized'] ?? '',
            'recipients' => $recipients,
            'attachments' => $attachments,
        ]);
    }

    public function send(WP_REST_Request $request)
    {
        $result = (new V24_SMH_Compose_Service())->send($request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function reply(WP_REST_Request $request)
    {
        $result = (new V24_SMH_Compose_Service())->reply((int) $request['id'], $request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function forward(WP_REST_Request $request)
    {
        $result = (new V24_SMH_Compose_Service())->forward((int) $request['id'], $request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : V24_SMH_REST_Bootstrap::success($result);
    }

    public function seen(WP_REST_Request $request)
    {
        $repo = new V24_SMH_Mail_Repository();
        $context = $this->message_context((int) $request['id']);
        if (is_wp_error($context)) {
            return $context;
        }

        $is_seen = filter_var($request->get_param('is_seen'), FILTER_VALIDATE_BOOLEAN);
        $result = (new V24_SMH_IMAP_Client())->set_seen(
            $context['account'],
            $context['folder']['full_name'],
            (int) $context['message']['uid'],
            $is_seen
        );
        if (is_wp_error($result)) {
            return $result;
        }

        $repo->update_seen((int) $context['message']['id'], $is_seen);
        $counts = $repo->folder_counts((int) $context['message']['folder_id']);
        (new V24_SMH_Folder_Repository())->update_counts((int) $context['message']['folder_id'], $counts['total'], $counts['unseen']);
        return V24_SMH_REST_Bootstrap::success(['is_seen' => $is_seen]);
    }

    public function flag(WP_REST_Request $request)
    {
        $repo = new V24_SMH_Mail_Repository();
        $context = $this->message_context((int) $request['id']);
        if (is_wp_error($context)) {
            return $context;
        }

        $is_flagged = filter_var($request->get_param('is_flagged'), FILTER_VALIDATE_BOOLEAN);
        $result = (new V24_SMH_IMAP_Client())->set_flagged(
            $context['account'],
            $context['folder']['full_name'],
            (int) $context['message']['uid'],
            $is_flagged
        );
        if (is_wp_error($result)) {
            return $result;
        }

        $repo->update_flagged((int) $context['message']['id'], $is_flagged);
        return V24_SMH_REST_Bootstrap::success(['is_flagged' => $is_flagged]);
    }

    public function move(WP_REST_Request $request)
    {
        $repo = new V24_SMH_Mail_Repository();
        $folder_repo = new V24_SMH_Folder_Repository();
        $context = $this->message_context((int) $request['id']);
        if (is_wp_error($context)) {
            return $context;
        }

        $target_folder_id = (int) $request->get_param('target_folder_id');
        $target_folder = $folder_repo->find($target_folder_id);
        if (!$target_folder || (int) $target_folder['account_id'] !== (int) $context['account']['id']) {
            return new WP_Error('folder_not_found', 'Cartella di destinazione non trovata.', ['status' => 404]);
        }

        if ((int) $target_folder['id'] === (int) $context['folder']['id']) {
            return V24_SMH_REST_Bootstrap::success([
                'message_id' => (int) $context['message']['id'],
                'target_folder_id' => (int) $target_folder['id'],
            ]);
        }

        $result = (new V24_SMH_IMAP_Client())->move_message(
            $context['account'],
            $context['folder']['full_name'],
            (int) $context['message']['uid'],
            $target_folder['full_name']
        );
        if (is_wp_error($result)) {
            return $result;
        }

        $repo->mark_deleted((int) $context['message']['id']);
        $source_counts = $repo->folder_counts((int) $context['folder']['id']);
        $folder_repo->update_counts((int) $context['folder']['id'], $source_counts['total'], $source_counts['unseen']);

        return V24_SMH_REST_Bootstrap::success([
            'message_id' => (int) $context['message']['id'],
            'target_folder_id' => (int) $target_folder['id'],
            'requires_sync' => true,
        ]);
    }

    public function delete(WP_REST_Request $request)
    {
        $repo = new V24_SMH_Mail_Repository();
        $folder_repo = new V24_SMH_Folder_Repository();
        $context = $this->message_context((int) $request['id']);
        if (is_wp_error($context)) {
            return $context;
        }

        $trash_folder = $this->trash_folder_for_account((int) $context['account']['id']);

        if ($trash_folder && (int) $trash_folder['id'] !== (int) $context['folder']['id']) {
            $result = (new V24_SMH_IMAP_Client())->move_message(
                $context['account'],
                $context['folder']['full_name'],
                (int) $context['message']['uid'],
                $trash_folder['full_name']
            );
        } else {
            $result = (new V24_SMH_IMAP_Client())->delete_message(
                $context['account'],
                $context['folder']['full_name'],
                (int) $context['message']['uid']
            );
        }

        if (is_wp_error($result)) {
            return $result;
        }

        $repo->mark_deleted((int) $context['message']['id']);
        $source_counts = $repo->folder_counts((int) $context['folder']['id']);
        $folder_repo->update_counts((int) $context['folder']['id'], $source_counts['total'], $source_counts['unseen']);

        return V24_SMH_REST_Bootstrap::success([
            'message_id' => (int) $context['message']['id'],
            'deleted' => true,
            'moved_to_trash' => $trash_folder && (int) $trash_folder['id'] !== (int) $context['folder']['id'],
            'requires_sync' => (bool) $trash_folder,
        ]);
    }

    public function attachment(WP_REST_Request $request)
    {
        $attachment_repo = new V24_SMH_Attachment_Repository();
        $attachment = $attachment_repo->find((int) $request['id']);
        if (!$attachment) {
            return new WP_Error('attachment_not_found', 'Allegato non trovato.', ['status' => 404]);
        }

        $message = (new V24_SMH_Mail_Repository())->find((int) $attachment['message_id']);
        if (!$message || !V24_SMH_Permissions::current_user_can_account((int) $message['account_id'])) {
            return new WP_Error('attachment_not_found', 'Allegato non trovato.', ['status' => 404]);
        }

        $account = (new V24_SMH_Account_Repository())->find((int) $message['account_id']);
        $folder = (new V24_SMH_Folder_Repository())->find((int) $message['folder_id']);
        if (!$account || !$folder) {
            return new WP_Error('attachment_not_found', 'Contesto allegato non disponibile.', ['status' => 404]);
        }

        $result = (new V24_SMH_IMAP_Client())->fetch_attachment_content(
            $account,
            $folder['full_name'],
            (int) $message['uid'],
            (string) $attachment['part_id']
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $attachment_repo->mark_downloaded((int) $attachment['id']);
        V24_SMH_Audit_Log::record('attachment_downloaded', [
            'account_id' => (int) $message['account_id'],
            'entity_type' => 'attachment',
            'entity_id' => (int) $attachment['id'],
        ]);

        return V24_SMH_REST_Bootstrap::success([
            'filename' => $result['filename'],
            'mime_type' => $result['mime_type'],
            'size_bytes' => $result['size_bytes'],
            'content_base64' => base64_encode($result['content']),
        ]);
    }

    private function hydrate_message(array $message)
    {
        $account = (new V24_SMH_Account_Repository())->find((int) $message['account_id']);
        $folder = (new V24_SMH_Folder_Repository())->find((int) $message['folder_id']);
        if (!$account || !$folder) {
            return new WP_Error('message_not_found', 'Contesto messaggio non disponibile.', ['status' => 404]);
        }

        $payload = (new V24_SMH_IMAP_Client())->fetch_message_payload($account, $folder['full_name'], (int) $message['uid']);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $repo = new V24_SMH_Mail_Repository();
        $repo->save_body((int) $message['id'], $payload['body']);
        $repo->replace_recipients((int) $message['id'], $payload['recipients'] ?? []);
        (new V24_SMH_Attachment_Repository())->replace_for_message((int) $message['id'], (int) $message['account_id'], $payload['attachments'] ?? []);

        return true;
    }

    private function message_context(int $message_id)
    {
        $message = (new V24_SMH_Mail_Repository())->find($message_id);
        if (!$message || !V24_SMH_Permissions::current_user_can_account((int) $message['account_id'])) {
            return new WP_Error('message_not_found', 'Messaggio non trovato.', ['status' => 404]);
        }

        $account = (new V24_SMH_Account_Repository())->find((int) $message['account_id']);
        $folder = (new V24_SMH_Folder_Repository())->find((int) $message['folder_id']);
        if (!$account || !$folder) {
            return new WP_Error('message_not_found', 'Contesto messaggio non disponibile.', ['status' => 404]);
        }

        return [
            'message' => $message,
            'account' => $account,
            'folder' => $folder,
        ];
    }

    private function folder_context(int $folder_id)
    {
        $folder = (new V24_SMH_Folder_Repository())->find($folder_id);
        if (!$folder || !V24_SMH_Permissions::current_user_can_account((int) $folder['account_id'])) {
            return new WP_Error('folder_not_found', 'Cartella non trovata.', ['status' => 404]);
        }

        $account = (new V24_SMH_Account_Repository())->find((int) $folder['account_id']);
        if (!$account) {
            return new WP_Error('account_not_found', 'Account non trovato.', ['status' => 404]);
        }

        return [
            'folder' => $folder,
            'account' => $account,
        ];
    }

    private function refresh_remote_folders(array $account, bool $delete_missing = false)
    {
        $repo = new V24_SMH_Folder_Repository();
        $remote_folders = (new V24_SMH_IMAP_Client())->list_folders($account);
        if (is_wp_error($remote_folders)) {
            return $remote_folders;
        }

        $remote_ids = [];
        foreach ($remote_folders as $remote_folder) {
            $repo->upsert((int) $account['id'], $remote_folder);
            $remote_ids[] = (string) ($remote_folder['remote_id'] ?? '');
        }

        if ($delete_missing) {
            $repo->delete_missing((int) $account['id'], $remote_ids);
        }

        return $repo->list_by_account((int) $account['id']);
    }

    private function find_folder_from_list(array $folders, string $full_name): ?array
    {
        foreach ($folders as $folder) {
            if (strcasecmp((string) ($folder['full_name'] ?? ''), $full_name) === 0) {
                return $folder;
            }
        }

        return null;
    }

    private function is_protected_folder(array $folder): bool
    {
        $special_use = (string) ($folder['special_use'] ?? '');
        $full_name = strtoupper((string) ($folder['full_name'] ?? ''));
        return $special_use !== '' || $full_name === 'INBOX';
    }

    private function folder_parent_path(array $folder): string
    {
        $delimiter = (string) ($folder['delimiter'] ?: '/');
        $full_name = (string) ($folder['full_name'] ?? '');
        $position = strrpos($full_name, $delimiter);
        if ($position === false) {
            return '';
        }

        return substr($full_name, 0, $position);
    }

    private function build_folder_full_name(string $name, string $parent_full_name = '', string $delimiter = '/'): string
    {
        $name = trim($name);
        $parent_full_name = trim($parent_full_name);
        $delimiter = $delimiter !== '' ? $delimiter : '/';

        if ($parent_full_name === '') {
            return trim($name, " \t\n\r\0\x0B" . $delimiter);
        }

        return rtrim($parent_full_name, $delimiter) . $delimiter . trim($name, " \t\n\r\0\x0B" . $delimiter);
    }

    private function sanitize_folder_segment(string $value): string
    {
        $value = trim(str_replace(["\r", "\n", "\t"], ' ', $value));
        $value = preg_replace('/[\/\\\\]+/', ' ', $value) ?: $value;
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;
        return trim($value);
    }

    private function sanitize_folder_path(string $value): string
    {
        $value = trim(str_replace(["\r", "\n", "\t", '{', '}'], ' ', $value));
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;
        return trim($value);
    }

    private function root_namespace_prefix(int $account_id, string $delimiter = '/'): string
    {
        $delimiter = $delimiter !== '' ? $delimiter : '/';
        $folders = (new V24_SMH_Folder_Repository())->list_by_account($account_id);
        if (!$folders) {
            return '';
        }

        $has_inbox = false;
        $all_under_inbox = true;
        $prefix = 'INBOX' . $delimiter;

        foreach ($folders as $folder) {
            $full_name = trim((string) ($folder['full_name'] ?? ''));
            if ($full_name === '') {
                continue;
            }

            if (strcasecmp($full_name, 'INBOX') === 0) {
                $has_inbox = true;
                continue;
            }

            if (stripos($full_name, $prefix) !== 0) {
                $all_under_inbox = false;
            }
        }

        return ($has_inbox && $all_under_inbox) ? 'INBOX' : '';
    }

    private function default_folder_delimiter(int $account_id): string
    {
        $folders = (new V24_SMH_Folder_Repository())->list_by_account($account_id);
        foreach ($folders as $folder) {
            $delimiter = (string) ($folder['delimiter'] ?? '');
            if ($delimiter !== '') {
                return $delimiter;
            }
        }

        return '/';
    }

    private function apply_root_namespace(string $full_name, string $root_prefix, string $delimiter = '/'): string
    {
        $full_name = trim($full_name);
        $root_prefix = trim($root_prefix);
        $delimiter = $delimiter !== '' ? $delimiter : '/';

        if ($full_name === '' || $root_prefix === '') {
            return $full_name;
        }

        if (strcasecmp($full_name, $root_prefix) === 0 || stripos($full_name, $root_prefix . $delimiter) === 0) {
            return $full_name;
        }

        return $root_prefix . $delimiter . trim($full_name, " \t\n\r\0\x0B" . $delimiter);
    }

    private function trash_folder_for_account(int $account_id): ?array
    {
        $folders = (new V24_SMH_Folder_Repository())->list_by_account($account_id);
        foreach ($folders as $folder) {
            if (($folder['special_use'] ?? '') === 'trash') {
                return $folder;
            }
        }

        return null;
    }
}
