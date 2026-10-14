# Windows-Variante von build-zips.sh
# Aufruf im Hauptordner des Repos (PowerShell):  .\scripts\build-zips.ps1
$ErrorActionPreference = "Stop"
Set-Location (Join-Path $PSScriptRoot "..")
if (Test-Path dist) { Remove-Item dist -Recurse -Force }
New-Item -ItemType Directory dist | Out-Null
Compress-Archive -Path ar-claude-website -DestinationPath dist\ar-claude-website.zip
Compress-Archive -Path skill\ar-website-pflege -DestinationPath dist\ar-website-pflege.zip
Write-Host "Fertig:"
Get-ChildItem dist
