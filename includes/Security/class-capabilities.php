<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Capabilities
{
    public static function all(): array
    {
        return [
            'v24_smh_manage_settings',
            'v24_smh_manage_accounts',
            'v24_smh_read_mail',
            'v24_smh_send_mail',
            'v24_smh_manage_mail',
            'v24_smh_read_contacts',
            'v24_smh_manage_contacts',
            'v24_smh_read_calendar',
            'v24_smh_manage_calendar',
            'v24_smh_read_tasks',
            'v24_smh_manage_tasks',
            'v24_smh_manage_rules',
            'v24_smh_view_logs',
            'v24_smh_manage_ai_suggestions',
            'v24_smh_review_ai_responses',
        ];
    }

    public static function add(): void
    {
        $admin = get_role('administrator');
        if ($admin) {
            foreach (self::all() as $capability) {
                $admin->add_cap($capability);
            }
        }
    }
}
