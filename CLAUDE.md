# CLAUDE.md

WordPress-Plugin „A/R Website für Claude“ (`ar-claude-website/`) plus Claude-Skill (`skill/ar-website-pflege/`), mit denen A/R Architekten ararchitekten.de per MCP im Chat pflegen. Einrichtung, Werkzeug-Referenz und Anpassung an andere Websites: siehe **README.md** (nicht hier wiederholen).

## Achtung: Live-Website

Der Connector `ar-website` (Werkzeuge `ar-website-*`) in Claude hängt an der **echten Website**. Beim Arbeiten am Code nicht benutzen – nur, wenn ausdrücklich darum gebeten wird.

## Regeln für den Code

- PHP 7.4 kompatibel bleiben (keine Union Types, kein `match`, keine Named Arguments). WordPress 6.9+ (Abilities API).
- Alle Funktionen mit Präfix `arcp_`. Theme-/Site-spezifisches nur über die `ARCP_*`-Konstanten in `ar-claude-website.php`, `arcp_field_map()` (`includes/helpers.php`) und `includes/wpbakery.php`.
- Alle sichtbaren Texte (Werkzeugbeschreibungen, Admin, Vorschau-/Upload-Seiten, Fehlermeldungen) auf Deutsch, Du-Form.
- Grundprinzip nicht aufweichen: Änderungen an veröffentlichten Inhalten laufen immer über einen Vorschlag (`arcp_stage` in `includes/changes.php`); live nur über `vorschlag-freigeben`. Der Claude-Benutzer bleibt gesperrt (`includes/security.php`).

## Was eine Änderung nach sich zieht

- **Release:** Version an **zwei** Stellen erhöhen – Header `Version:` und `ARCP_VERSION` in `ar-claude-website.php`. ZIPs bauen: `.\scripts\build-zips.ps1` bzw. `bash scripts/build-zips.sh` → `dist/`.
- **Werkzeug in `includes/abilities.php` hinzugefügt/entfernt:** README anpassen (Werkzeug-Tabelle, Anzahl „13 Werkzeuge“), ggf. `SKILL.md`. Nutzer müssen den Connector neu verbinden.
- **Werkzeug-Schema oder -Verhalten geändert:** prüfen, ob `SKILL.md` noch dazu passt.
- **`SKILL.md` geändert:** Skill-ZIP muss in Claude neu hochgeladen werden.

## Testen

Keine automatischen Tests, kein CI. Prüfung manuell nach README „Teil C – Erster Test“ auf einer WordPress-Installation; `php -l` für Syntaxchecks.
