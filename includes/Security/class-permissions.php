<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Permissions
{
    public static function current_user_can_account(int $account_id, string $capability = 'v24_smh_read_mail'): bool
    {
        if (!is_user_logged_in() || !current_user_can($capability)) {
            return false;
        }
        if (current_user_can('v24_smh_manage_accounts')) {
            return true;
        }

        $repo = new V24_SMH_Account_Repository();
        $account = $repo->find($account_id);
        if (!$account) {
            return false;
        }

        $user = wp_get_current_user();
        if ($account['owner_type'] === 'user' && (int) $account['owner_id'] === (int) $user->ID) {
            return true;
        }
        if ($account['owner_type'] === 'role') {
            return in_array((string) $account['owner_id'], $user->roles, true);
        }
        return $account['owner_type'] === 'shared';
    }
}
