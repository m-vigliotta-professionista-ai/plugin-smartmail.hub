<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Contacts_Service
{
    private $repository;

    public function __construct(?V24_SMH_Contacts_Repository $repository = null)
    {
        $this->repository = $repository ?: new V24_SMH_Contacts_Repository();
    }

    public function create(array $input)
    {
        $payload = $this->normalize($input);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $id = $this->repository->create($payload);
        V24_SMH_Audit_Log::record('contact_created', ['entity_type' => 'contact', 'entity_id' => $id]);
        return ['id' => $id];
    }

    public function update(int $contact_id, array $input)
    {
        $existing = $this->repository->find($contact_id);
        if (!$existing) {
            return new WP_Error('contact_not_found', 'Contatto non trovato.', ['status' => 404]);
        }

        $payload = $this->normalize($input);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $this->repository->update($contact_id, $payload);
        V24_SMH_Audit_Log::record('contact_updated', ['entity_type' => 'contact', 'entity_id' => $contact_id]);
        return ['id' => $contact_id];
    }

    public function delete(int $contact_id)
    {
        $existing = $this->repository->find($contact_id);
        if (!$existing) {
            return new WP_Error('contact_not_found', 'Contatto non trovato.', ['status' => 404]);
        }

        $this->repository->delete($contact_id);
        V24_SMH_Audit_Log::record('contact_deleted', ['entity_type' => 'contact', 'entity_id' => $contact_id]);
        return ['id' => $contact_id];
    }

    public function find(int $contact_id)
    {
        $contact = $this->repository->find($contact_id);
        if (!$contact) {
            return new WP_Error('contact_not_found', 'Contatto non trovato.', ['status' => 404]);
        }

        return $contact;
    }

    public function search(string $query = ''): array
    {
        return $this->repository->search($query);
    }

    private function normalize(array $input)
    {
        $first_name = sanitize_text_field($input['first_name'] ?? '');
        $last_name = sanitize_text_field($input['last_name'] ?? '');
        $display_name = sanitize_text_field($input['display_name'] ?? trim($first_name . ' ' . $last_name));
        $primary_email = sanitize_email($input['primary_email'] ?? '');
        $emails = array_values(array_filter(array_map('sanitize_email', (array) ($input['emails'] ?? []))));
        $phones = array_values(array_filter(array_map('sanitize_text_field', (array) ($input['phones'] ?? []))));
        $addresses = array_values(array_filter(array_map('sanitize_text_field', (array) ($input['addresses'] ?? []))));

        if ($primary_email && !in_array($primary_email, $emails, true)) {
            array_unshift($emails, $primary_email);
        } elseif (!$primary_email && !empty($emails[0])) {
            $primary_email = $emails[0];
        }

        if ($display_name === '' && $primary_email === '') {
            return new WP_Error('validation_error', 'Nome contatto o email obbligatori.', ['status' => 400]);
        }

        return [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $display_name ?: $primary_email,
            'company' => sanitize_text_field($input['company'] ?? ''),
            'department' => sanitize_text_field($input['department'] ?? ''),
            'job_title' => sanitize_text_field($input['job_title'] ?? ''),
            'primary_email' => $primary_email,
            'website_url' => esc_url_raw($input['website_url'] ?? ''),
            'mobile_phone' => sanitize_text_field($input['mobile_phone'] ?? ''),
            'business_phone' => sanitize_text_field($input['business_phone'] ?? ''),
            'emails' => $emails,
            'phones' => array_values(array_unique(array_filter(array_merge(
                array_filter([
                    sanitize_text_field($input['mobile_phone'] ?? ''),
                    sanitize_text_field($input['business_phone'] ?? ''),
                ]),
                $phones
            )))),
            'addresses' => $addresses,
            'categories' => array_values(array_filter(array_map('sanitize_text_field', (array) ($input['categories'] ?? [])))),
            'notes' => sanitize_textarea_field($input['notes'] ?? ''),
        ];
    }
}
