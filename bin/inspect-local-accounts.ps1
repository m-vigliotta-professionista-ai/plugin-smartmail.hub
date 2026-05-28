[CmdletBinding()]
param(
    [string]$RepoRoot = ""
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if ($RepoRoot -eq "") {
    $scriptRoot = if ($PSScriptRoot) {
        $PSScriptRoot
    } else {
        Split-Path -Path $MyInvocation.MyCommand.Path -Parent
    }
    $RepoRoot = Split-Path -Path $scriptRoot -Parent
}

$resolvedRepoRoot = (Resolve-Path -LiteralPath $RepoRoot).Path
$localWpRoot = Join-Path $resolvedRepoRoot "local-wp"

if (-not (Test-Path -LiteralPath $localWpRoot)) {
    throw "Cartella local-wp non trovata in '$resolvedRepoRoot'."
}

$probePhp = @'
<?php
$settings = get_option('v24_smh_settings', []);
$transport = is_array($settings) ? (string) ($settings['outbound_transport'] ?? 'auto') : 'auto';
global $wpdb;
$table = $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'accounts';
$accounts = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A) ?: [];
$rows = [];

foreach ($accounts as $account) {
    $rows[] = [
        'id' => (int) ($account['id'] ?? 0),
        'label' => (string) ($account['label'] ?? ''),
        'email_address' => (string) ($account['email_address'] ?? ''),
        'status' => (string) ($account['status'] ?? ''),
        'owner_type' => (string) ($account['owner_type'] ?? ''),
        'owner_id' => (int) ($account['owner_id'] ?? 0),
        'imap_host' => (string) ($account['imap_host'] ?? ''),
        'imap_port' => (int) ($account['imap_port'] ?? 0),
        'imap_encryption' => (string) ($account['imap_encryption'] ?? ''),
        'smtp_host' => (string) ($account['smtp_host'] ?? ''),
        'smtp_port' => (int) ($account['smtp_port'] ?? 0),
        'smtp_encryption' => (string) ($account['smtp_encryption'] ?? ''),
        'username_decryptable' => V24_SMH_Encryption::decrypt($account['encrypted_username'] ?? '') !== '',
        'secret_decryptable' => V24_SMH_Encryption::decrypt($account['encrypted_secret'] ?? '') !== '',
        'last_error' => (string) ($account['last_error'] ?? ''),
        'last_connected_at' => (string) ($account['last_connected_at'] ?? ''),
        'updated_at' => (string) ($account['updated_at'] ?? ''),
    ];
}

echo wp_json_encode([
    'siteurl' => (string) get_option('siteurl'),
    'home' => (string) home_url('/'),
    'outbound_transport' => sanitize_key($transport),
    'accounts' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
'@

$tempEvalFile = Join-Path $localWpRoot ".smh-inspect-local-accounts.php"

Push-Location $localWpRoot

try {
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($tempEvalFile, $probePhp, $utf8NoBom)
    $output = & ddev wp eval-file ".smh-inspect-local-accounts.php"

    if ($LASTEXITCODE -ne 0) {
        throw "Comando ddev fallito: ddev wp eval-file .smh-inspect-local-accounts.php"
    }

    if ($output) {
        Write-Output $output
    }
} finally {
    if (Test-Path -LiteralPath $tempEvalFile) {
        Remove-Item -LiteralPath $tempEvalFile -Force
    }
    Pop-Location
}
