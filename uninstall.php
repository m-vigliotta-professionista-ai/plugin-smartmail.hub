<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Data removal is intentionally conservative in the MVP.
// A future setting can allow full cleanup of plugin tables on uninstall.
