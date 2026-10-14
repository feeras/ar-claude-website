#!/usr/bin/env bash
# Baut die beiden ZIP-Dateien zum Hochladen:
#   dist/ar-claude-website.zip  → WordPress: Plugins → Installieren → Plugin hochladen
#   dist/ar-website-pflege.zip  → Claude: Anpassen → Skills → hochladen
# Aufruf im Hauptordner des Repos:  bash scripts/build-zips.sh
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf dist && mkdir -p dist
zip -rq dist/ar-claude-website.zip ar-claude-website -x '*.DS_Store'
( cd skill && zip -rq ../dist/ar-website-pflege.zip ar-website-pflege -x '*.DS_Store' )
echo "Fertig:"
ls -lh dist
