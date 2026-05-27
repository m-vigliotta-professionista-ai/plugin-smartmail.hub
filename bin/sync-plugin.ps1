[CmdletBinding()]
param(
    [string]$RepoRoot = (Split-Path -Path $PSScriptRoot -Parent)
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$resolvedRepoRoot = (Resolve-Path -LiteralPath $RepoRoot).Path
$localWpRoot = Join-Path $resolvedRepoRoot "local-wp"
$pluginSlug = "valore24-smartmail-hub"
$pluginPath = Join-Path $localWpRoot "wp-content\\plugins\\$pluginSlug"
$pluginItems = @(
    "admin",
    "assets",
    "includes",
    "public",
    "templates",
    ".gitignore",
    "ARCHITETTURA-PLUGIN.md",
    "README.md",
    "README.remote.md",
    "uninstall.php",
    "valore24-smartmail-hub.php"
)

if (-not (Test-Path -LiteralPath $localWpRoot)) {
    throw "Cartella local-wp non trovata in '$resolvedRepoRoot'."
}

$missingItems = foreach ($item in $pluginItems) {
    $sourcePath = Join-Path $resolvedRepoRoot $item
    if (-not (Test-Path -LiteralPath $sourcePath)) {
        $sourcePath
    }
}

if ($missingItems) {
    throw "Elementi mancanti nella root del repo:`n$($missingItems -join "`n")"
}

$pluginParent = Split-Path -Path $pluginPath -Parent
New-Item -ItemType Directory -Path $pluginParent -Force | Out-Null

if (Test-Path -LiteralPath $pluginPath) {
    Remove-Item -LiteralPath $pluginPath -Recurse -Force
}

New-Item -ItemType Directory -Path $pluginPath -Force | Out-Null

foreach ($item in $pluginItems) {
    $sourcePath = Join-Path $resolvedRepoRoot $item
    Copy-Item -LiteralPath $sourcePath -Destination $pluginPath -Recurse -Force
}

Write-Output "Plugin sincronizzato in: $pluginPath"
