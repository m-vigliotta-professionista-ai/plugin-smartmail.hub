[CmdletBinding()]
param(
    [string]$RepoRoot = "",
    [int]$AccountId = 0,
    [string]$Label = "",
    [string]$EmailAddress = "",
    [string]$DisplayName = "",
    [string]$ImapHost = "",
    [int]$ImapPort = 0,
    [ValidateSet("ssl_tls", "tls", "none")]
    [string]$ImapEncryption = "",
    [string]$SmtpHost = "",
    [int]$SmtpPort = 0,
    [ValidateSet("ssl_tls", "tls", "none")]
    [string]$SmtpEncryption = "",
    [string]$Username = "",
    [string]$Secret = "",
    [ValidateSet("user", "shared")]
    [string]$OwnerType = "",
    [int]$ActingUserId = 1,
    [switch]$UpsertLatestByEmail,
    [switch]$DeleteOtherMatches,
    [switch]$RunTest,
    [switch]$RunSync
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

$payload = @{}

if ($AccountId -gt 0) {
    $payload["account_id"] = $AccountId
}
if ($Label -ne "") {
    $payload["label"] = $Label
}
if ($EmailAddress -ne "") {
    $payload["email_address"] = $EmailAddress
}
if ($DisplayName -ne "") {
    $payload["display_name"] = $DisplayName
}
if ($ImapHost -ne "") {
    $payload["imap_host"] = $ImapHost
}
if ($ImapPort -gt 0) {
    $payload["imap_port"] = $ImapPort
}
if ($ImapEncryption -ne "") {
    $payload["imap_encryption"] = $ImapEncryption
}
if ($SmtpHost -ne "") {
    $payload["smtp_host"] = $SmtpHost
}
if ($SmtpPort -gt 0) {
    $payload["smtp_port"] = $SmtpPort
}
if ($SmtpEncryption -ne "") {
    $payload["smtp_encryption"] = $SmtpEncryption
}
if ($Username -ne "") {
    $payload["username"] = $Username
}
if ($Secret -ne "") {
    $payload["secret"] = $Secret
}
if ($OwnerType -ne "") {
    $payload["owner_type"] = $OwnerType
}

$payloadJson = $payload | ConvertTo-Json -Compress -Depth 6
$payloadBase64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($payloadJson))

$savePhp = @'
<?php
wp_set_current_user(__ACTING_USER_ID__);

$payload = json_decode(base64_decode('__PAYLOAD_BASE64__'), true);
if (!is_array($payload)) {
    $payload = [];
}

$upsertLatestByEmail = __UPSERT_LATEST_BY_EMAIL__;
$deleteOtherMatches = __DELETE_OTHER_MATCHES__;
$runTest = __RUN_TEST__;
$runSync = __RUN_SYNC__;

global $wpdb;
$table = $wpdb->prefix . V24_SMH_TABLE_PREFIX . 'accounts';
$email = sanitize_email($payload['email_address'] ?? '');
$matchedIds = [];

if ($upsertLatestByEmail && empty($payload['account_id']) && $email !== '') {
    $matchedIds = array_map('intval', $wpdb->get_col(
        $wpdb->prepare("SELECT id FROM {$table} WHERE email_address = %s ORDER BY id DESC", $email)
    ) ?: []);

    if ($matchedIds) {
        $payload['account_id'] = $matchedIds[0];
    }
}

$service = new V24_SMH_Account_Service();
$result = $service->save($payload);

if (is_wp_error($result)) {
    echo wp_json_encode([
        'saved' => false,
        'error_code' => $result->get_error_code(),
        'error_message' => $result->get_error_message(),
    ], JSON_UNESCAPED_SLASHES);
    exit(1);
}

$accountId = (int) ($result['id'] ?? 0);
if ($accountId <= 0) {
    echo wp_json_encode([
        'saved' => false,
        'error_code' => 'account_save_failed',
        'error_message' => 'Salvataggio account non riuscito.',
    ], JSON_UNESCAPED_SLASHES);
    exit(1);
}

if ($deleteOtherMatches && $email !== '') {
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table} WHERE email_address = %s AND id <> %d",
        $email,
        $accountId
    ));
}

$repository = new V24_SMH_Account_Repository();
$account = $repository->find($accountId);

$response = [
    'saved' => true,
    'mode' => !empty($payload['account_id']) ? 'update' : 'create',
    'account_id' => $accountId,
    'email_address' => (string) ($account['email_address'] ?? ''),
    'label' => (string) ($account['label'] ?? ''),
    'status' => (string) ($account['status'] ?? ''),
    'owner_type' => (string) ($account['owner_type'] ?? ''),
    'owner_id' => (int) ($account['owner_id'] ?? 0),
    'matched_ids' => $matchedIds,
    'username_decryptable' => V24_SMH_Encryption::decrypt($account['encrypted_username'] ?? '') !== '',
    'secret_decryptable' => V24_SMH_Encryption::decrypt($account['encrypted_secret'] ?? '') !== '',
];

if ($runTest) {
    $test = $service->test($accountId);
    $response['test'] = is_wp_error($test)
        ? [
            'ok' => false,
            'error_code' => $test->get_error_code(),
            'error_message' => $test->get_error_message(),
        ]
        : [
            'ok' => true,
            'result' => $test,
        ];
}

if ($runSync) {
    $sync = (new V24_SMH_Mail_Sync_Service())->sync_account($accountId);
    $response['sync'] = is_wp_error($sync)
        ? [
            'ok' => false,
            'error_code' => $sync->get_error_code(),
            'error_message' => $sync->get_error_message(),
        ]
        : [
            'ok' => true,
            'result' => $sync,
        ];
}

echo wp_json_encode($response, JSON_UNESCAPED_SLASHES);
?>
'@

$savePhp = $savePhp.Replace('__ACTING_USER_ID__', [string]$ActingUserId)
$savePhp = $savePhp.Replace('__PAYLOAD_BASE64__', $payloadBase64)
$savePhp = $savePhp.Replace('__UPSERT_LATEST_BY_EMAIL__', $UpsertLatestByEmail.IsPresent.ToString().ToLowerInvariant())
$savePhp = $savePhp.Replace('__DELETE_OTHER_MATCHES__', $DeleteOtherMatches.IsPresent.ToString().ToLowerInvariant())
$savePhp = $savePhp.Replace('__RUN_TEST__', $RunTest.IsPresent.ToString().ToLowerInvariant())
$savePhp = $savePhp.Replace('__RUN_SYNC__', $RunSync.IsPresent.ToString().ToLowerInvariant())

$tempEvalFile = Join-Path $localWpRoot ".smh-save-local-account.php"
$stdoutFile = Join-Path $localWpRoot ".smh-save-local-account.stdout.txt"
$stderrFile = Join-Path $localWpRoot ".smh-save-local-account.stderr.txt"

Push-Location $localWpRoot

try {
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($tempEvalFile, $savePhp, $utf8NoBom)
    $process = Start-Process -FilePath "ddev" `
        -ArgumentList @("wp", "eval-file", ".smh-save-local-account.php") `
        -WorkingDirectory $localWpRoot `
        -RedirectStandardOutput $stdoutFile `
        -RedirectStandardError $stderrFile `
        -NoNewWindow `
        -Wait `
        -PassThru
    $exitCode = $process.ExitCode
    $stdoutContent = if (Test-Path -LiteralPath $stdoutFile) {
        Get-Content -LiteralPath $stdoutFile -ErrorAction SilentlyContinue
    } else {
        @()
    }
    $stderrContent = if (Test-Path -LiteralPath $stderrFile) {
        Get-Content -LiteralPath $stderrFile -ErrorAction SilentlyContinue
    } else {
        @()
    }

    if (-not $stdoutContent) {
        $stderrMessage = if ($stderrContent) { ($stderrContent -join [Environment]::NewLine) } else { "" }
        if ($stderrMessage -ne "") {
            throw "Nessun output JSON restituito da ddev wp eval-file.`n$stderrMessage"
        }
        throw "Nessun output restituito da ddev wp eval-file."
    }

    $jsonLine = (($stdoutContent | Where-Object { "$_".Trim() -ne "" } | Select-Object -Last 1).ToString()).Trim()
    $jsonLine = $jsonLine.Trim([char]0xFEFF)
    $result = $jsonLine | ConvertFrom-Json

    if ($exitCode -ne 0 -or -not $result.saved) {
        $message = if ($result.error_message) { $result.error_message } else { "Salvataggio account fallito." }
        throw $message
    }

    Write-Output "Account salvato: ID $($result.account_id) [$($result.mode)]"
    Write-Output "Email: $($result.email_address)"
    Write-Output "Owner: $($result.owner_type) ($($result.owner_id))"
    Write-Output "Decrittazione username: $($result.username_decryptable)"
    Write-Output "Decrittazione secret: $($result.secret_decryptable)"

    if ($result.matched_ids -and $result.matched_ids.Count -gt 0) {
        Write-Output "Righe locali trovate per questa email: $($result.matched_ids -join ', ')"
    }

    if ($result.PSObject.Properties.Name -contains "test") {
        if ($result.test.ok) {
            Write-Output "Test account: OK"
        } else {
            Write-Output "Test account: ERRORE - $($result.test.error_message)"
        }
    }

    if ($result.PSObject.Properties.Name -contains "sync") {
        if ($result.sync.ok) {
            Write-Output "Sync account: OK"
        } else {
            Write-Output "Sync account: ERRORE - $($result.sync.error_message)"
        }
    }
} finally {
    if (Test-Path -LiteralPath $tempEvalFile) {
        Remove-Item -LiteralPath $tempEvalFile -Force
    }
    if (Test-Path -LiteralPath $stdoutFile) {
        Remove-Item -LiteralPath $stdoutFile -Force
    }
    if (Test-Path -LiteralPath $stderrFile) {
        Remove-Item -LiteralPath $stderrFile -Force
    }
    Pop-Location
}
