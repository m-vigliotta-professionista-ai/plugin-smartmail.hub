[CmdletBinding()]
param(
    [string]$RepoRoot = (Split-Path -Path $PSScriptRoot -Parent),
    [switch]$SkipSync,
    [switch]$SkipPage
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$resolvedRepoRoot = (Resolve-Path -LiteralPath $RepoRoot).Path
$localWpRoot = Join-Path $resolvedRepoRoot "local-wp"
$syncScript = Join-Path $resolvedRepoRoot "bin\sync-plugin.ps1"
$pluginSlug = "valore24-smartmail-hub"
$pageSlug = "smartmail-hub"
$pageTitle = "SmartMail Hub"
$pageShortcode = "[valore24_smartmail_hub]"

if (-not (Test-Path -LiteralPath $localWpRoot)) {
    throw "Cartella local-wp non trovata in '$resolvedRepoRoot'."
}

if (-not (Test-Path -LiteralPath $syncScript)) {
    throw "Script di sync non trovato in '$syncScript'."
}

function Invoke-Ddev {
    param(
        [Parameter(Mandatory = $true)]
        [string[]]$Arguments
    )

    Push-Location $localWpRoot

    try {
        $output = & ddev @Arguments
    } finally {
        Pop-Location
    }

    if ($LASTEXITCODE -ne 0) {
        throw "Comando ddev fallito: ddev $($Arguments -join ' ')"
    }

    if ($null -ne $output) {
        Write-Output $output
    }
}

Write-Output "Verifica ambiente DDEV in $localWpRoot"
Invoke-Ddev -Arguments @("describe")

if (-not $SkipSync) {
    Write-Output "Sincronizzazione plugin locale"
    & $syncScript -RepoRoot $resolvedRepoRoot

    if ($LASTEXITCODE -ne 0) {
        throw "Sincronizzazione plugin fallita."
    }
}

Write-Output "Attivazione plugin $pluginSlug"
Invoke-Ddev -Arguments @("wp", "plugin", "activate", $pluginSlug)

if (-not $SkipPage) {
    Write-Output "Creazione o aggiornamento pagina test $pageSlug"
    $pageBootstrap = @'
<?php
$existingPage = get_page_by_path('__PAGE_SLUG__', OBJECT, 'page');
$postData = array(
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => '__PAGE_TITLE__',
    'post_name' => '__PAGE_SLUG__',
    'post_content' => '__PAGE_SHORTCODE__',
);
if ($existingPage instanceof WP_Post) {
    $postData['ID'] = $existingPage->ID;
    wp_update_post($postData, true);
    echo 'updated';
} else {
    wp_insert_post($postData, true);
    echo 'created';
}
'@
    $pageBootstrap = $pageBootstrap.Replace('__PAGE_SLUG__', $pageSlug)
    $pageBootstrap = $pageBootstrap.Replace('__PAGE_TITLE__', $pageTitle.Replace("'", "\'"))
    $pageBootstrap = $pageBootstrap.Replace('__PAGE_SHORTCODE__', $pageShortcode.Replace("'", "\'"))
    $tempEvalFile = Join-Path $localWpRoot ".smh-page-bootstrap.php"

    try {
        Set-Content -LiteralPath $tempEvalFile -Value $pageBootstrap -Encoding UTF8
        Invoke-Ddev -Arguments @("wp", "eval-file", ".smh-page-bootstrap.php")
    } finally {
        if (Test-Path -LiteralPath $tempEvalFile) {
            Remove-Item -LiteralPath $tempEvalFile -Force
        }
    }
}

Write-Output "Pagina locale configurata"
Invoke-Ddev -Arguments @("wp", "post", "list", "--post_type=page", "--name=$pageSlug", "--fields=ID,post_title,post_name,post_status", "--format=table")

Write-Output "Apri poi il path locale /$pageSlug/ sul progetto DDEV corrente."
