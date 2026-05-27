<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Calendar_Service
{
    private $calendars;
    private $events;
    private $attendees_repository;
    private $recurrence;

    public function __construct(
        ?V24_SMH_Calendars_Repository $calendars = null,
        ?V24_SMH_Events_Repository $events = null,
        ?V24_SMH_Event_Attendees_Repository $attendees_repository = null,
        ?V24_SMH_Calendar_Recurrence $recurrence = null
    ) {
        $this->calendars = $calendars ?: new V24_SMH_Calendars_Repository();
        $this->events = $events ?: new V24_SMH_Events_Repository();
        $this->attendees_repository = $attendees_repository ?: new V24_SMH_Event_Attendees_Repository();
        $this->recurrence = $recurrence ?: new V24_SMH_Calendar_Recurrence();
    }

    public function list_calendars(): array
    {
        $this->ensure_personal_calendar();
        $calendars = $this->calendars->list_visible_for_user(get_current_user_id());

        foreach ($calendars as &$calendar) {
            $calendar = $this->decorate_calendar($calendar);
        }

        return $calendars;
    }

    public function find_calendar(int $calendar_id)
    {
        $this->ensure_personal_calendar();
        $calendar = $this->calendars->find_visible($calendar_id, get_current_user_id());
        if (!$calendar) {
            return new WP_Error('calendar_not_found', 'Calendario non trovato.', ['status' => 404]);
        }

        return $this->decorate_calendar($calendar);
    }

    public function create_calendar(array $input)
    {
        $payload = $this->normalize_calendar($input);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $payload['owner_user_id'] = get_current_user_id();
        $id = $this->calendars->create($payload);

        V24_SMH_Audit_Log::record('calendar_created', ['entity_type' => 'calendar', 'entity_id' => $id]);
        return ['id' => $id];
    }

    public function update_calendar(int $calendar_id, array $input)
    {
        $calendar = $this->calendars->find_owned($calendar_id, get_current_user_id());
        if (!$calendar) {
            return new WP_Error('calendar_not_found', 'Calendario non trovato o non modificabile.', ['status' => 404]);
        }

        $payload = $this->normalize_calendar($input);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $this->calendars->update($calendar_id, $payload);
        V24_SMH_Audit_Log::record('calendar_updated', ['entity_type' => 'calendar', 'entity_id' => $calendar_id]);
        return ['id' => $calendar_id];
    }

    public function delete_calendar(int $calendar_id)
    {
        $calendar = $this->calendars->find_owned($calendar_id, get_current_user_id());
        if (!$calendar) {
            return new WP_Error('calendar_not_found', 'Calendario non trovato o non eliminabile.', ['status' => 404]);
        }

        if (!empty($calendar['is_default'])) {
            return new WP_Error('calendar_delete_forbidden', 'Il calendario predefinito non puo essere eliminato.', ['status' => 400]);
        }

        $default = $this->ensure_personal_calendar();
        $this->calendars->reassign_calendar_events($calendar_id, (int) $default['id']);
        $this->calendars->replace_shares($calendar_id, []);
        $this->calendars->delete($calendar_id);

        V24_SMH_Audit_Log::record('calendar_deleted', ['entity_type' => 'calendar', 'entity_id' => $calendar_id]);
        return ['id' => $calendar_id];
    }

    public function replace_calendar_shares(int $calendar_id, array $input)
    {
        $calendar = $this->calendars->find_owned($calendar_id, get_current_user_id());
        if (!$calendar) {
            return new WP_Error('calendar_not_found', 'Calendario non trovato o non condivisibile.', ['status' => 404]);
        }

        $shares = [];
        foreach ((array) ($input['shares'] ?? []) as $share) {
            $user_id = (int) ($share['user_id'] ?? 0);
            if ($user_id <= 0 || $user_id === get_current_user_id()) {
                continue;
            }

            $user = get_userdata($user_id);
            if (!$user) {
                continue;
            }

            $permission_level = sanitize_key($share['permission_level'] ?? 'read');
            if (!in_array($permission_level, ['read', 'edit'], true)) {
                $permission_level = 'read';
            }

            $shares[] = [
                'user_id' => $user_id,
                'permission_level' => $permission_level,
            ];
        }

        $this->calendars->replace_shares($calendar_id, $shares);
        V24_SMH_Audit_Log::record('calendar_shares_updated', ['entity_type' => 'calendar', 'entity_id' => $calendar_id]);
        return ['id' => $calendar_id];
    }

    public function available_users(): array
    {
        $users = get_users([
            'fields' => 'ids',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        $visible_user_ids = array_filter($users, static function ($user_id) {
            return user_can((int) $user_id, 'v24_smh_read_calendar');
        });

        $payload = array_map(static function ($user_id) {
            $user = get_userdata((int) $user_id);
            if (!$user) {
                return null;
            }

            return [
                'id' => (int) $user->ID,
                'display_name' => $user->display_name ?: $user->user_login,
                'email' => (string) $user->user_email,
            ];
        }, $visible_user_ids);

        return array_values(array_filter($payload));
    }

    public function create(array $input)
    {
        $calendar = $this->resolve_calendar_for_write((int) ($input['calendar_id'] ?? 0));
        if (is_wp_error($calendar)) {
            return $calendar;
        }

        $payload = $this->normalize_event($input, $calendar);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $attendees = $payload['attendees'];
        unset($payload['attendees']);

        $id = $this->events->create($payload);
        $this->attendees_repository->replace_for_event($id, $attendees);
        V24_SMH_Audit_Log::record('calendar_event_created', ['entity_type' => 'calendar_event', 'entity_id' => $id]);
        return ['id' => $id];
    }

    public function update(int $event_id, array $input)
    {
        $existing = $this->find_visible_event($event_id);
        if (!$existing) {
            return new WP_Error('calendar_event_not_found', 'Evento non trovato.', ['status' => 404]);
        }

        $existing_calendar = $this->require_calendar_write_access((int) $existing['calendar_id']);
        if (is_wp_error($existing_calendar)) {
            return $existing_calendar;
        }

        $scope = sanitize_key($input['scope'] ?? 'series');
        $occurrence_start = sanitize_text_field($input['occurrence_start'] ?? '');

        if ($scope === 'occurrence' && !empty($existing['recurrence_json']) && !$existing['parent_event_id'] && $occurrence_start !== '') {
            return $this->update_occurrence($existing, $occurrence_start, $input);
        }

        $target_calendar = $this->resolve_target_calendar_for_update($existing, $input);
        if (is_wp_error($target_calendar)) {
            return $target_calendar;
        }

        $payload = $this->normalize_event($input, $target_calendar, $existing);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $attendees = $payload['attendees'];
        unset($payload['attendees']);

        $this->events->update($event_id, $payload);
        $this->attendees_repository->replace_for_event($event_id, $attendees);
        V24_SMH_Audit_Log::record('calendar_event_updated', ['entity_type' => 'calendar_event', 'entity_id' => $event_id]);
        return ['id' => $event_id];
    }

    public function delete(int $event_id, array $input = [])
    {
        $existing = $this->find_visible_event($event_id);
        if (!$existing) {
            return new WP_Error('calendar_event_not_found', 'Evento non trovato.', ['status' => 404]);
        }

        $scope = sanitize_key($input['scope'] ?? 'series');
        $occurrence_start = sanitize_text_field($input['occurrence_start'] ?? '');

        if ($scope === 'occurrence' && !empty($existing['recurrence_json']) && !$existing['parent_event_id'] && $occurrence_start !== '') {
            $calendar = $this->require_calendar_write_access((int) $existing['calendar_id']);
            if (is_wp_error($calendar)) {
                return $calendar;
            }

            $override = $this->events->find_override($event_id, $occurrence_start, $this->visible_calendar_ids());
            if ($override) {
                $this->events->delete((int) $override['id']);
            }

            $this->events->add_exception($event_id, $occurrence_start);
            V24_SMH_Audit_Log::record('calendar_event_occurrence_deleted', ['entity_type' => 'calendar_event', 'entity_id' => $event_id]);
            return ['id' => $event_id, 'scope' => 'occurrence'];
        }

        $calendar = $this->require_calendar_write_access((int) $existing['calendar_id']);
        if (is_wp_error($calendar)) {
            return $calendar;
        }

        $this->events->delete($event_id);
        V24_SMH_Audit_Log::record('calendar_event_deleted', ['entity_type' => 'calendar_event', 'entity_id' => $event_id]);
        return ['id' => $event_id];
    }

    public function find(int $event_id, ?string $occurrence_start = null)
    {
        $event = $this->find_visible_event($event_id);
        if (!$event) {
            return new WP_Error('calendar_event_not_found', 'Evento non trovato.', ['status' => 404]);
        }

        $calendar_lookup = $this->calendar_lookup();
        if ($occurrence_start && !empty($event['recurrence_json']) && empty($event['parent_event_id'])) {
            $occurrence = $this->build_occurrence_record($event, $occurrence_start, $calendar_lookup, true);
            if (!$occurrence) {
                return new WP_Error('calendar_occurrence_not_found', 'Occorrenza non trovata.', ['status' => 404]);
            }

            return $occurrence;
        }

        return $this->hydrate_event($event, $calendar_lookup);
    }

    public function list(string $start, string $end, array $filters = []): array
    {
        $this->ensure_personal_calendar();
        $calendar_lookup = $this->calendar_lookup();
        $allowed_calendar_ids = array_keys($calendar_lookup);

        if (!empty($filters['calendar_ids'])) {
            $requested = array_values(array_filter(array_map('intval', (array) $filters['calendar_ids'])));
            $allowed_calendar_ids = array_values(array_intersect($allowed_calendar_ids, $requested));
        }

        $events = $this->events->list_visible($allowed_calendar_ids, $start, $end);
        $masters = [];
        $detached = [];

        foreach ($events as $event) {
            if (!empty($event['parent_event_id'])) {
                $detached[] = $event;
                continue;
            }

            $masters[] = $event;
        }

        $detached_by_parent = [];
        foreach ($detached as $event) {
            $key = (int) $event['parent_event_id'] . '|' . (string) ($event['recurrence_instance_start'] ?? '');
            $detached_by_parent[$key] = $event;
        }

        $items = [];
        $used_detached_ids = [];

        foreach ($masters as $master) {
            $expansions = $this->recurrence->expand($master, $start, $end);
            foreach ($expansions as $expansion) {
                $occurrence = $this->build_occurrence_record(
                    $master,
                    (string) $expansion['occurrence_start'],
                    $calendar_lookup,
                    false,
                    (string) $expansion['occurrence_end'],
                    $detached_by_parent,
                    $used_detached_ids
                );

                if ($occurrence) {
                    $items[] = $occurrence;
                }
            }
        }

        foreach ($detached as $event) {
            if (in_array((int) $event['id'], $used_detached_ids, true)) {
                continue;
            }

            $items[] = $this->hydrate_event($event, $calendar_lookup, [
                'is_occurrence' => true,
                'is_detached_instance' => true,
                'series_id' => (int) ($event['parent_event_id'] ?? 0),
                'occurrence_start' => $event['recurrence_instance_start'] ?: $event['start_at'],
                'occurrence_key' => 'event-' . (int) $event['id'],
            ]);
        }

        usort($items, static function (array $left, array $right) {
            return strcmp((string) ($left['start_at'] ?? ''), (string) ($right['start_at'] ?? ''));
        });

        return $items;
    }

    private function update_occurrence(array $master, string $occurrence_start, array $input)
    {
        $calendar = $this->require_calendar_write_access((int) $master['calendar_id']);
        if (is_wp_error($calendar)) {
            return $calendar;
        }

        $target_calendar = $this->resolve_calendar_for_write((int) ($input['calendar_id'] ?? (int) $master['calendar_id']));
        if (is_wp_error($target_calendar)) {
            return $target_calendar;
        }

        $generated = $this->build_occurrence_record($master, $occurrence_start, $this->calendar_lookup(), true);
        if (!$generated) {
            return new WP_Error('calendar_occurrence_not_found', 'Occorrenza non trovata.', ['status' => 404]);
        }

        $override = $this->events->find_override((int) $master['id'], $occurrence_start, $this->visible_calendar_ids());
        $base = $override ?: $generated;
        $payload = $this->normalize_event($input, $target_calendar, $base, [
            'calendar_id' => (int) $target_calendar['id'],
            'parent_event_id' => (int) $master['id'],
            'recurrence_instance_start' => $occurrence_start,
            'recurrence' => null,
            'recurrence_exceptions' => [],
        ]);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $attendees = $payload['attendees'];
        unset($payload['attendees']);
        $payload['parent_event_id'] = (int) $master['id'];
        $payload['recurrence_instance_start'] = $occurrence_start;
        $payload['recurrence'] = null;
        $payload['recurrence_exceptions'] = [];

        $this->events->add_exception((int) $master['id'], $occurrence_start);

        if ($override) {
            $this->events->update((int) $override['id'], $payload);
            $this->attendees_repository->replace_for_event((int) $override['id'], $attendees);
            $override_id = (int) $override['id'];
        } else {
            $payload['owner_id'] = (int) $master['owner_id'];
            $override_id = $this->events->create($payload);
            $this->attendees_repository->replace_for_event($override_id, $attendees);
        }

        V24_SMH_Audit_Log::record('calendar_event_occurrence_updated', ['entity_type' => 'calendar_event', 'entity_id' => $override_id]);
        return ['id' => $override_id, 'scope' => 'occurrence', 'series_id' => (int) $master['id']];
    }

    private function normalize_calendar(array $input)
    {
        $name = sanitize_text_field($input['name'] ?? '');
        if ($name === '') {
            return new WP_Error('validation_error', 'Nome calendario obbligatorio.', ['status' => 400]);
        }

        $color = sanitize_text_field($input['color'] ?? '#0d5c63');
        if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#0d5c63';
        }

        return [
            'name' => $name,
            'slug' => sanitize_title($input['slug'] ?? $name),
            'color' => $color,
            'description' => sanitize_textarea_field($input['description'] ?? ''),
        ];
    }

    private function normalize_event(array $input, array $calendar, ?array $existing = null, array $forced = [])
    {
        $title = sanitize_text_field($input['title'] ?? ($existing['title'] ?? ''));
        $start_at = sanitize_text_field($input['start_at'] ?? ($existing['start_at'] ?? ''));
        $end_at = sanitize_text_field($input['end_at'] ?? ($existing['end_at'] ?? ''));
        $timezone = sanitize_text_field($input['timezone'] ?? ($existing['timezone'] ?? wp_timezone_string()));
        $contacts = new V24_SMH_Contacts_Repository();

        if ($title === '') {
            return new WP_Error('validation_error', 'Titolo evento obbligatorio.', ['status' => 400]);
        }

        if ($start_at === '' || $end_at === '') {
            return new WP_Error('validation_error', 'Data inizio e fine obbligatorie.', ['status' => 400]);
        }

        if (strtotime($end_at) < strtotime($start_at)) {
            return new WP_Error('validation_error', 'La fine evento deve essere successiva all inizio.', ['status' => 400]);
        }

        $attendees = [];
        foreach ((array) ($input['attendees'] ?? ($existing['attendees'] ?? [])) as $attendee) {
            $email = sanitize_email($attendee['email'] ?? '');
            if ($email === '') {
                continue;
            }

            $matched_contact = $contacts->find_by_email($email);
            $matched_contact_id = !empty($matched_contact['id']) ? (int) $matched_contact['id'] : null;

            $attendees[] = [
                'contact_id' => !empty($attendee['contact_id']) ? (int) $attendee['contact_id'] : $matched_contact_id,
                'email' => $email,
                'name' => sanitize_text_field($attendee['name'] ?? ($matched_contact['display_name'] ?? '')),
                'role' => sanitize_key($attendee['role'] ?? 'required'),
                'response_status' => sanitize_key($attendee['response_status'] ?? 'needs_action'),
            ];
        }

        $recurrence_input = $forced['recurrence'] ?? ($input['recurrence'] ?? null);
        if ($recurrence_input === null && !empty($existing['recurrence_json'])) {
            $recurrence_input = json_decode((string) $existing['recurrence_json'], true);
        }
        $recurrence = $this->recurrence->normalize((array) $recurrence_input, $start_at, $timezone);
        if (is_wp_error($recurrence)) {
            return $recurrence;
        }

        if (array_key_exists('recurrence', $forced) && $forced['recurrence'] === null) {
            $recurrence = null;
        }

        $exceptions = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) ($forced['recurrence_exceptions'] ?? ($recurrence['exceptions'] ?? json_decode((string) ($existing['recurrence_exceptions_json'] ?? '[]'), true) ?: []))))));

        return [
            'owner_id' => (int) ($existing['owner_id'] ?? get_current_user_id()),
            'account_id' => !empty($existing['account_id']) ? (int) $existing['account_id'] : null,
            'calendar_id' => (int) ($forced['calendar_id'] ?? $calendar['id']),
            'parent_event_id' => !empty($forced['parent_event_id']) ? (int) $forced['parent_event_id'] : (!empty($existing['parent_event_id']) ? (int) $existing['parent_event_id'] : null),
            'title' => $title,
            'description' => sanitize_textarea_field($input['description'] ?? ($existing['description'] ?? '')),
            'location' => sanitize_text_field($input['location'] ?? ($existing['location'] ?? '')),
            'start_at' => $start_at,
            'end_at' => $end_at,
            'timezone' => $timezone,
            'is_all_day' => array_key_exists('is_all_day', $input) ? !empty($input['is_all_day']) : !empty($existing['is_all_day']),
            'status' => sanitize_key($input['status'] ?? ($existing['status'] ?? 'confirmed')),
            'busy_status' => sanitize_key($input['busy_status'] ?? ($existing['busy_status'] ?? 'busy')),
            'reminder_minutes' => array_key_exists('reminder_minutes', $input) ? $input['reminder_minutes'] : ($existing['reminder_minutes'] ?? null),
            'categories' => array_values(array_filter(array_map('sanitize_text_field', (array) ($input['categories'] ?? json_decode((string) ($existing['categories_json'] ?? '[]'), true) ?: [])))),
            'recurrence' => $recurrence,
            'recurrence_exceptions' => $exceptions,
            'recurrence_instance_start' => $forced['recurrence_instance_start'] ?? ($existing['recurrence_instance_start'] ?? null),
            'attendees' => $attendees,
        ];
    }

    private function decorate_calendar(array $calendar): array
    {
        $owner = get_userdata((int) ($calendar['owner_user_id'] ?? 0));
        $calendar['shares'] = $calendar['can_manage_shares'] ? array_values(array_map(static function ($share) {
            $user = get_userdata((int) $share['user_id']);
            return [
                'user_id' => (int) $share['user_id'],
                'permission_level' => $share['permission_level'],
                'display_name' => $user ? ($user->display_name ?: $user->user_login) : 'Utente',
            ];
        }, $this->calendars->list_shares((int) $calendar['id']))) : [];
        $calendar['owner_name'] = $owner ? ($owner->display_name ?: $owner->user_login) : '';
        return $calendar;
    }

    private function hydrate_event(array $event, array $calendar_lookup, array $overrides = []): array
    {
        $event['categories'] = json_decode((string) ($event['categories_json'] ?? '[]'), true) ?: [];
        $event['recurrence'] = $this->recurrence->decode($event['recurrence_json'] ?? null);
        $event['recurrence_exceptions'] = json_decode((string) ($event['recurrence_exceptions_json'] ?? '[]'), true) ?: [];
        $event['attendees'] = $this->attendees_repository->list_by_event((int) $event['id']);
        $calendar = $calendar_lookup[(int) ($event['calendar_id'] ?? 0)] ?? null;
        $event['calendar_name'] = $event['calendar_name'] ?? ($calendar['name'] ?? '');
        $event['calendar_color'] = $event['calendar_color'] ?? ($calendar['color'] ?? '#0d5c63');
        $event['can_edit'] = !empty($calendar['can_edit']);
        $event['is_series'] = !empty($event['recurrence']);
        $event['is_occurrence'] = !empty($overrides['is_occurrence']);
        $event['is_detached_instance'] = !empty($overrides['is_detached_instance']);
        $event['is_virtual_occurrence'] = !empty($overrides['is_virtual_occurrence']);
        $event['series_id'] = $overrides['series_id'] ?? (!empty($event['parent_event_id']) ? (int) $event['parent_event_id'] : (int) $event['id']);
        $event['occurrence_start'] = $overrides['occurrence_start'] ?? ($event['recurrence_instance_start'] ?? $event['start_at']);
        $event['occurrence_key'] = $overrides['occurrence_key'] ?? ('event-' . (int) $event['id']);

        return array_merge($event, $overrides);
    }

    private function build_occurrence_record(
        array $master,
        string $occurrence_start,
        array $calendar_lookup,
        bool $include_missing = false,
        ?string $occurrence_end = null,
        ?array $detached_by_parent = null,
        ?array &$used_detached_ids = null
    ): ?array {
        $key = (int) $master['id'] . '|' . $occurrence_start;
        $exception_list = json_decode((string) ($master['recurrence_exceptions_json'] ?? '[]'), true) ?: [];
        $override = $detached_by_parent[$key] ?? $this->events->find_override((int) $master['id'], $occurrence_start, $this->visible_calendar_ids());

        if ($override) {
            if (is_array($used_detached_ids)) {
                $used_detached_ids[] = (int) $override['id'];
            }

            return $this->hydrate_event($override, $calendar_lookup, [
                'is_occurrence' => true,
                'is_detached_instance' => true,
                'series_id' => (int) $master['id'],
                'occurrence_start' => $occurrence_start,
                'occurrence_key' => 'event-' . (int) $override['id'],
            ]);
        }

        if (in_array($occurrence_start, $exception_list, true)) {
            return $include_missing ? null : null;
        }

        if ($occurrence_end === null) {
            $generated = $this->recurrence->expand($master, $occurrence_start, $occurrence_start);
            $occurrence_end = $generated[0]['occurrence_end'] ?? null;
        }

        if ($occurrence_end === null) {
            return null;
        }

        return $this->hydrate_event($master, $calendar_lookup, [
            'id' => (int) $master['id'],
            'start_at' => $occurrence_start,
            'end_at' => $occurrence_end,
            'is_occurrence' => true,
            'is_virtual_occurrence' => true,
            'series_id' => (int) $master['id'],
            'occurrence_start' => $occurrence_start,
            'occurrence_key' => 'series-' . (int) $master['id'] . '-' . md5($occurrence_start),
        ]);
    }

    private function resolve_calendar_for_write(int $calendar_id)
    {
        $this->ensure_personal_calendar();
        if ($calendar_id <= 0) {
            return $this->ensure_personal_calendar();
        }

        return $this->require_calendar_write_access($calendar_id);
    }

    private function resolve_target_calendar_for_update(array $existing, array $input)
    {
        $target_calendar_id = (int) ($input['calendar_id'] ?? (int) ($existing['calendar_id'] ?? 0));
        return $this->require_calendar_write_access($target_calendar_id);
    }

    private function require_calendar_write_access(int $calendar_id)
    {
        $calendar = $this->calendars->find_visible($calendar_id, get_current_user_id());
        if (!$calendar || empty($calendar['can_edit'])) {
            return new WP_Error('calendar_forbidden', 'Permessi insufficienti sul calendario selezionato.', ['status' => 403]);
        }

        return $calendar;
    }

    private function visible_calendar_ids(): array
    {
        return array_map('intval', array_keys($this->calendar_lookup()));
    }

    private function calendar_lookup(): array
    {
        $this->ensure_personal_calendar();
        $lookup = [];
        foreach ($this->calendars->list_visible_for_user(get_current_user_id()) as $calendar) {
            $lookup[(int) $calendar['id']] = $calendar;
        }

        return $lookup;
    }

    private function find_visible_event(int $event_id): ?array
    {
        return $this->events->find_visible($event_id, $this->visible_calendar_ids());
    }

    private function ensure_personal_calendar(): array
    {
        $calendar = $this->calendars->ensure_default_for_user(get_current_user_id());
        $this->calendars->assign_legacy_events_to_default(get_current_user_id(), (int) $calendar['id']);
        return $calendar;
    }
}
