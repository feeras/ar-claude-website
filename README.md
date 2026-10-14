# A/R Website für Claude

WordPress-Plugin und Claude-Skill, mit denen das Büro A/R Architekten die Website
[ararchitekten.de](https://ararchitekten.de) direkt aus Claude pflegt: Projekte anlegen und ändern,
Texte und Bilder auf Seiten ändern – immer erst als Vorschlag mit Vorschau, live erst nach Freigabe im Chat.

## Inhalt

| Ordner | Was |
|---|---|
| `ar-claude-website/` | WordPress-Plugin (als ZIP dieses Ordners installieren) |
| `skill/ar-website-pflege/` | Claude-Skill: Dialog-Ablauf, Freigabe-Regeln, Textstil |

## Wie es zusammenspielt

```
Claude (Web / Desktop / Handy)
   │  eigener Connector, OAuth-Anmeldung
   ▼
https://ararchitekten.de/wp-json/ar-website/mcp   ← MCP-Server (WordPress MCP Adapter)
   │  13 Werkzeuge (Abilities API)
   ▼
Plugin „A/R Website für Claude“
   ├─ Projekte (Post-Typ „projects“, PixFields, Theme-Optionen „prague_post_options“)
   ├─ Seiten (WPBakery-Bausteine, gezielte Text-/Bildänderung)
   ├─ Änderungsvorschläge + Vorschau-Link (ohne Login, mit Schlüssel)
   ├─ Upload-Link für Bilder (ohne Login, 2 h gültig)
   └─ Freigabe nur nach ausdrücklichem Ja im Chat
```

## Werkzeuge

| Werkzeug | Zweck |
|---|---|
| `projekte-auflisten`, `seiten-auflisten` | Inhalte finden |
| `inhalt-lesen` | Änderbare Elemente (Texte, Bilder) einer Seite/eines Projekts |
| `medien-suchen` | Bilder in der Mediathek finden |
| `upload-link`, `upload-status` | Bilder per Einmal-Link hochladen |
| `bild-hochladen` | Bild per direktem https-Link übernehmen |
| `projekt-anlegen`, `projekt-aendern` | Projekte mit Pflicht-Stammdaten |
| `inhalt-aendern` | Texte/Bilder in WPBakery-Bausteinen ändern |
| `aenderungen-auflisten` | Offene Vorschläge |
| `vorschlag-freigeben`, `vorschlag-zuruecknehmen` | Live schalten / verwerfen |

## Voraussetzungen

- WordPress ≥ 6.9 (Abilities API), PHP ≥ 7.4
- [MCP Adapter](https://github.com/WordPress/mcp-adapter) (eigenes Plugin oder von WooCommerce mitgebracht – das Plugin startet ihn selbst)
- Theme „Prague“ mit „Prague Plugins“, PixFields, WPBakery
- WordPress-Benutzer „Claude“ mit Rolle **Redakteur**

## Installation

1. Ordner `ar-claude-website/` zippen und unter Plugins → Installieren → Hochladen installieren.
2. WordPress → **Claude** → Claude-Benutzer wählen; Systemcheck muss komplett ✅ sein.
3. Claude → Anpassen → Konnektoren → Benutzerdefinierten Connector hinzufügen:
   URL `https://ararchitekten.de/wp-json/ar-website/mcp` → Verbinden → auf der Anmeldeseite „Erlauben“.
4. Skill `skill/ar-website-pflege/` in Claude hochladen bzw. aktualisieren.

**Wichtig:** Kommen neue Werkzeuge hinzu, den Connector in Claude einmal trennen und neu verbinden –
Claude lädt die Werkzeugliste sonst nicht neu.

## Sicherheit

- Der Claude-Benutzer darf nur den MCP-Server nutzen (übrige REST-API gesperrt), kommt nicht ins Backend
  und kann nur über `vorschlag-freigeben` veröffentlichen.
- OAuth 2.1 mit PKCE (S256) und Dynamic Client Registration; nur Rücksprung zu claude.ai/claude.com bzw. localhost.
  Tokens werden nur als Hash gespeichert; „Alle Verbindungen trennen“ unter WordPress → Claude.
- Vorschau- und Upload-Links: zufällige Schlüssel, befristet, `noindex`. Uploads: nur echte JPG/PNG/WebP.

## Versionen

Die Plugin-Version steht in `ar-claude-website/ar-claude-website.php`. Vor dem Hochladen auf den Server
die Versionsnummer erhöhen, damit WordPress „Installiertes durch Hochgeladenes ersetzen“ anbietet.
