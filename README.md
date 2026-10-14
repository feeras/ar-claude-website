# Website-Pflege mit Claude (WordPress + MCP)

Ein WordPress-Plugin und eine Claude-Skill, mit denen ein Büro seine Website direkt im Chat mit Claude pflegt:

- **Projekte/Referenzen anlegen und ändern**: mit festen Pflicht-Stammdaten (Jahr, Ort, Kategorie, Art, Stand), Beschreibung, Titelbild, Slider, Plänen
- **Texte und Bilder auf Seiten ändern**, z. B. auf der Startseite, gezielt pro Element, ohne das Layout anzufassen
- **Bilder hochladen** über einen Upload-Link (auch am Handy)
- **Nichts geht ungefragt live**: Jede Änderung ist zuerst ein Vorschlag mit Vorschau-Link. Live geht sie erst nach ausdrücklichem „Freigeben“ im Chat.

Entwickelt für **ararchitekten.de** (Theme „Prague“, PixFields, WPBakery). Für eine andere Website siehe [Teil D – An eine andere Website anpassen](#teil-d--an-eine-andere-website-anpassen).

---

## Inhalt

- [Wie es funktioniert](#wie-es-funktioniert)
- [Was du brauchst](#was-du-brauchst)
- [Teil A – WordPress einrichten (einmalig)](#teil-a--wordpress-einrichten-einmalig)
- [Teil B – Claude einrichten (pro Person)](#teil-b--claude-einrichten-pro-person)
- [Teil C – Erster Test](#teil-c--erster-test)
- [Teil D – An eine andere Website anpassen](#teil-d--an-eine-andere-website-anpassen)
- [Teil E – Updates einspielen](#teil-e--updates-einspielen)
- [Fehlerbehebung](#fehlerbehebung)
- [Sicherheit](#sicherheit)
- [Referenz: Werkzeuge](#referenz-werkzeuge)

---

## Wie es funktioniert

```
Claude (Web, Desktop, Handy)
   │  eigener Connector, Anmeldung per OAuth (WordPress-Login + „Erlauben“)
   ▼
https://DEINE-DOMAIN/wp-json/ar-website/mcp      ← MCP-Server im Plugin
   │  13 Werkzeuge
   ▼
WordPress-Plugin „A/R Website für Claude“
   ├─ Projekte: eigener Beitragstyp + Stammdatenfelder + Theme-Optionen (Slider)
   ├─ Seiten: Texte/Bilder in WPBakery-Bausteinen
   ├─ Änderungsvorschläge mit Vorschau-Link (ohne Login, befristet)
   ├─ Upload-Link für Bilder (ohne Login, 2 Stunden)
   └─ Freigabe nur auf ausdrückliches Ja im Chat

Claude-Skill „ar-website-pflege“
   └─ legt fest, WIE Claude fragt: eine Frage pro Nachricht, Auswahl-Buttons,
      Vorschau-Link vor jeder Freigabe, Textstil der Projektbeschreibungen
```

**Zwei Teile, zwei Orte:**

| Teil | Wo installiert | Wie oft |
|---|---|---|
| Plugin `ar-claude-website/` | auf der WordPress-Website | **einmal** pro Website |
| Connector + Skill `skill/ar-website-pflege/` | im Claude-Konto | **pro Person**, die die Website pflegt |

---

## Was du brauchst

**Website**
- WordPress **6.9 oder neuer** (bringt die Abilities API mit), PHP 7.4+
- HTTPS (Pflicht für die Anmeldung)
- Admin-Zugang zum WordPress-Backend
- Für die Projekt-Funktionen in der aktuellen Form: Theme **Prague** mit **Prague Plugins**, **PixFields** und **WPBakery Page Builder**. Andere Themes → [Teil D](#teil-d--an-eine-andere-website-anpassen).

**Claude**
- Ein Claude-Konto pro Person, getestet mit **Pro**. Eigene Connectors müssen im Plan verfügbar sein.
- **Claude Desktop** ([claude.ai/download](https://claude.ai/download)) oder einfach claude.ai im Browser. Connector und Skill gelten für das ganze Konto, also automatisch auch am Handy.

**Zum Bauen der ZIP-Dateien**
- macOS/Linux: Terminal (`zip` ist vorhanden)
- Windows: PowerShell

---

## Teil A – WordPress einrichten (einmalig)

### A1. Sicherung

Beim Hoster eine **Sicherung** von Dateien und Datenbank anlegen. Bei Strato: Kundenbereich → WordPress → Backups.

### A2. Code holen und ZIP-Dateien bauen

1. Repository herunterladen: auf GitHub **Code → Download ZIP**, entpacken. Oder `git clone`.
2. Im entpackten Hauptordner ausführen:
   - **macOS/Linux:** `bash scripts/build-zips.sh`
   - **Windows (PowerShell):** `.\scripts\build-zips.ps1`
3. Im Ordner `dist/` liegen jetzt:
   - `ar-claude-website.zip`: das Plugin
   - `ar-website-pflege.zip`: die Skill

> Ohne Skript: den Ordner `ar-claude-website` selbst zippen (Rechtsklick → Komprimieren bzw. „Senden an → ZIP-komprimierter Ordner“). Wichtig: Im ZIP muss **der Ordner** liegen, nicht nur sein Inhalt.

### A3. MCP Adapter sicherstellen

Das Plugin baut auf dem offiziellen [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) auf.

- Ist **WooCommerce** installiert, bringt es den Adapter meist schon mit. Das Plugin startet ihn dann selbst, du musst nichts tun.
- Sonst: `mcp-adapter.zip` von [github.com/WordPress/mcp-adapter/releases/latest](https://github.com/WordPress/mcp-adapter/releases/latest) herunterladen → **Plugins → Installieren → Plugin hochladen** → aktivieren.

Ob es klappt, zeigt der Systemcheck in Schritt A6.

### A4. WordPress-Benutzer für Claude anlegen

**Benutzer → Neu hinzufügen**
- Benutzername: `Claude`
- E-Mail: eine eigene Adresse, z. B. `claude@deine-domain.de` (muss nicht existieren, darf aber nicht doppelt sein)
- Passwort: zufällig erzeugen lassen und **nicht notieren**, man meldet sich nie damit an
- Rolle: **Redakteur**. Nicht Administrator, nicht Autor, nicht Abonnent.

Über diesen Benutzer arbeitet Claude. Das Plugin sperrt ihn zusätzlich: Er kommt nicht ins Backend, darf nur die Claude-Schnittstelle nutzen und kann nichts selbst veröffentlichen.

> Ein **Anwendungspasswort** brauchst du **nicht**. Die Anmeldung läuft über OAuth (Teil B).

### A5. Plugin installieren

**Plugins → Installieren → Plugin hochladen** → `ar-claude-website.zip` → **Jetzt installieren** → **Aktivieren**.

Links im Menü erscheint der Punkt **„Claude“**.

### A6. Einstellungen und Systemcheck

**WordPress → Claude**

1. **Claude-Benutzer**: den Benutzer aus A4 wählen → **Speichern**.
2. **Vorlage-Projekt (ID)**: die ID eines bestehenden Projekts, dessen Darstellung (Slider-Stil usw.) neue Projekte übernehmen sollen. Die ID steht in der Adresszeile beim Bearbeiten eines Projekts (`post.php?post=3864`). Für ararchitekten.de: `3864`.
3. **Kategorien / Art des Projekts / Aktueller Stand**: ein Wert pro Zeile. Claude kann **nur** diese Werte verwenden.
4. **Systemcheck** oben: **alle Punkte müssen ✅ sein.**

| Prüfpunkt | Wenn ❌ |
|---|---|
| Abilities API | WordPress auf 6.9+ aktualisieren |
| MCP Adapter vorhanden / Endpunkt erreichbar | Schritt A3 |
| Projekt-Typ / Projekt-Kategorien | „Prague Plugins“ aktivieren, sonst [Teil D](#teil-d--an-eine-andere-website-anpassen) |
| PixFields-Felder | PixFields aktivieren bzw. Felder anlegen, sonst [Teil D](#teil-d--an-eine-andere-website-anpassen) |
| Vorlage-Projekt hat Theme-Optionen | andere Vorlage-ID wählen (ein Projekt mit Slider) |
| Claude-Benutzer gewählt / kein Administrator | Schritt A4 bzw. A6.1 |

5. **Authorization-Header vom Server**: sollte „✅ kommt an“ zeigen. Bei ❌ einmal **„Authorization-Header aktivieren (.htaccess)“** klicken und die Seite neu laden.
6. Optional, **Kategorien abgleichen**: setzt bei allen bestehenden Projekten die Projekt-Kategorie passend zum Feld „Kategorie“. Die Tabelle zeigt vorher genau, was passiert.

### A7. Caching beachten

Bei Cache-Plugins (z. B. WP Fastest Cache) muss nichts eingestellt werden. Vorschau- und Upload-Seiten senden „nicht cachen“. Wird eine Vorschau trotzdem ohne Änderung angezeigt: im Cache-Plugin URLs mit `arcp_vorschau` vom Cache ausnehmen.

**WordPress ist fertig.** Die folgenden Teile macht jede Person für sich.

---

## Teil B – Claude einrichten (pro Person)

Jede Person, die die Website pflegen soll, braucht ein eigenes Claude-Konto und macht diese Schritte einmal. Die Freigabe auf der Anmeldeseite (B2) braucht einen **WordPress-Zugang mit mindestens der Rolle Redakteur**. Hat die Person keinen, klickt jemand mit Zugang einmal mit ihr zusammen auf „Erlauben“.

### B1. Claude Desktop installieren und anmelden

[claude.ai/download](https://claude.ai/download) → installieren → mit dem Claude-Konto anmelden.

> Alles Folgende geht genauso im Browser auf claude.ai. Was du dort einrichtest, gilt auch in Desktop und Handy-App.

### B2. Connector hinzufügen

1. Claude öffnen → unten links auf den eigenen Namen → **Einstellungen**
2. Links unter **Anpassungen** → **Konnektoren**
3. Oben rechts **Hinzufügen → Benutzerdefinierten Connector hinzufügen**
4. Eintragen:
   - **Name:** `ar-website` (frei wählbar)
   - **MCP-Server-URL:** `https://DEINE-DOMAIN/wp-json/ar-website/mcp`

     für A/R: `https://ararchitekten.de/wp-json/ar-website/mcp`
5. **Hinzufügen** → beim Connector auf **Verbinden**
6. Es öffnet sich ein Browserfenster mit der WordPress-Anmeldung → mit dem **eigenen** WordPress-Konto anmelden (nicht mit „Claude“)
7. Seite **„Claude verbinden“** → **Erlauben**
8. Zurück in Claude: Der Connector steht auf **Verbunden**. Beim Anklicken sollten **13 Werkzeuge** aufgelistet sein.

> Die Frage nach einer „OAuth Client ID“ in den erweiterten Einstellungen einfach leer lassen. Claude registriert sich automatisch.

### B3. Skill hochladen

1. **Einstellungen → Anpassungen → Skills**
2. **Hinzufügen / Skill hochladen** → `ar-website-pflege.zip` aus `dist/` wählen
3. Prüfen, dass die Skill **aktiviert** ist

> **Team- oder Enterprise-Plan:** Ein Admin kann die Skill für alle im Team freigeben. Dann entfällt B3 für die einzelnen Personen.

### B4. Im Chat einschalten

Neuen Chat öffnen → unten im Eingabefeld das Werkzeug-/Connector-Menü → sicherstellen, dass **ar-website** eingeschaltet ist. Je nach Einstellung ist er in neuen Chats automatisch aktiv.

---

## Teil C – Erster Test

In einem **neuen** Chat nacheinander (die Skill nicht ausdrücklich aufrufen, Claude soll sie selbst erkennen):

| # | Eingabe | Erwartet |
|---|---|---|
| 1 | „Liste die Seiten der Website auf.“ | Liste, die Startseite ist erkennbar |
| 2 | „Ändere auf der Startseite WILLKOMMEN in HERZLICH WILLKOMMEN“ (einen echten kurzen Text der Startseite nehmen) | Vorher → Nachher, **👉 Vorschau ansehen**, Abfrage mit *Vorschau-Link nochmal zeigen · Freigeben · Anpassen · Verwerfen* |
| 3 | Vorschau-Link öffnen | echte Seite mit Änderung und Balken „VORSCHAU – noch nicht live“, die Live-Seite ist unverändert |
| 4 | **Verwerfen** klicken | Vorschlag zurückgenommen |
| 5 | „Leg ein neues Projekt an“ | eine Frage pro Nachricht, Auswahl-Buttons bei Kategorie/Art/Stand, bei Bildern ein **Upload-Link** |
| 6 | Am Ende **Verwerfen** | das Testprojekt landet im Papierkorb |

Wenn alles so läuft, ist die Einrichtung abgeschlossen.

---

## Teil D – An eine andere Website anpassen

Das Plugin ist **auf ararchitekten.de zugeschnitten**. Folgende Teile hängen am Theme bzw. an der Seite und müssen bei einem anderen Kunden geprüft und angepasst werden.

### D1. Was ohne Änderung funktioniert

- Anmeldung (OAuth), Sicherheit, Claude-Benutzer-Sperre
- Vorschläge, Vorschau-Links, Freigabe, Zurücknehmen
- Upload-Link und Mediathek-Suche
- **Text- und Bildänderungen auf Seiten**, sofern die Seiten mit **WPBakery** gebaut sind

### D2. Was angepasst werden muss

| Was | Datei | Für A/R | Wie herausfinden |
|---|---|---|---|
| Beitragstyp der Projekte | `ar-claude-website.php` → `ARCP_POST_TYPE` | `projects` | Im Backend Projekte öffnen, die Adresszeile zeigt `edit.php?post_type=…` |
| Kategorie-Taxonomie | `ar-claude-website.php` → `ARCP_TAXONOMY` | `projects-category` | „Kategorien“ der Projekte öffnen, die Adresszeile zeigt `taxonomy=…` |
| Theme-Optionsfeld (Slider) | `ar-claude-website.php` → `ARCP_THEME_META` | `prague_post_options` | theme-spezifisch; das Feld enthält den Schlüssel `gallery` |
| Stammdatenfelder | `includes/helpers.php` → `arcp_field_map()` | `pixfield_year`, `pixfield_location`, … | Beim Bearbeiten eines Projekts die Feldnamen (`name="…"`) der Stammdaten-Box ansehen |
| Inhaltsaufbau neuer Projekte | `includes/wpbakery.php` → `arcp_wpb_build_project()` | Textblock + Abschnitte „Diagramm“/„Pläne“ mit je 2 Bildern | Den Code eines Musterprojekts im WPBakery-Editor (Klassischer Modus) ansehen |
| Werte für Kategorie/Art/Stand | im Backend unter **Claude** | A/R-Werte | mit dem Kunden festlegen |
| Vorlage-Projekt | im Backend unter **Claude** | `3864` | ein gut gepflegtes Projekt des Kunden |
| Namen und Texte | `ar-claude-website.php` (Plugin Name), `includes/oauth.php` (Anmeldeseite), `includes/uploads.php` (Upload-Seite „A/R Architekten“) | „A/R …“ | Kundennamen eintragen |
| Server-Name | `includes/mcp-server.php` | „A/R Architekten Website“ | Kundennamen eintragen |

**Andere Page-Builder** (Elementor, Gutenberg/Blöcke, Divi): Die Text-/Bildänderung auf Seiten (`includes/wpbakery.php`) liest nur WPBakery-Shortcodes. Für andere Builder muss dieser Teil neu geschrieben werden. Alles andere bleibt gleich.

**Kein Projekt-Beitragstyp / andere Stammdaten:** `projekt-anlegen` und `projekt-aendern` in `includes/abilities.php` (Eingabe-Schema und Pflichtfelder) sowie `arcp_field_map()` anpassen.

### D3. Skill anpassen

In `skill/ar-website-pflege/SKILL.md`:
- `name` und `description` (Kopfbereich) auf den Kunden ändern, z. B. `kunde-website-pflege`. Den Ordnernamen gleich benennen.
- Domain und Büro-Namen im Text ersetzen
- Abschnitt **„Projekttexte – Stil“** mit 1–2 echten Beispielen des Kunden füllen
- Fragenreihenfolge unter **„Neues Projekt anlegen“** an die Stammdaten des Kunden anpassen

### D4. Namespace (optional)

Laufen mehrere Kunden-Plugins auf **derselben** WordPress-Installation, muss `ARCP_NS` je Plugin eindeutig sein. Der Wert steckt auch in der Connector-URL `/wp-json/<ARCP_NS>/mcp`. Bei getrennten Websites kann er so bleiben.

---

## Teil E – Updates einspielen

1. Code ändern und in `ar-claude-website.php` **beide** Versionsnummern erhöhen (`Version:` und `ARCP_VERSION`)
2. ZIP neu bauen (`scripts/build-zips.*`)
3. **Plugins → Installieren → Plugin hochladen** → ZIP → **„Installiertes durch Hochgeladenes ersetzen“**
4. **WordPress → Claude**: Systemcheck kontrollieren
5. **Wurden Werkzeuge hinzugefügt oder entfernt?** Dann in Claude unter **Konnektoren** den Connector **trennen und neu verbinden**, sonst sieht Claude die neuen Werkzeuge nicht. Änderungen an bestehenden Werkzeugen wirken sofort.
6. **Skill geändert?** In Claude unter **Skills** die neue ZIP hochladen bzw. die bestehende ersetzen.
7. Danach immer in einem **neuen Chat** testen. Ein laufender Chat behält Werkzeuge und Skill vom Start.

---

## Fehlerbehebung

| Symptom | Ursache | Lösung |
|---|---|---|
| „Dieser Konnektor hat keine Tools verfügbar“ | Anmeldung fehlt oder ist abgelaufen | Connector trennen → neu verbinden → „Erlauben“ |
| „Registrierung beim Anmeldedienst fehlgeschlagen“ | Plugin-Version ohne OAuth oder Anmelde-Adressen nicht erreichbar | Plugin aktuell? `https://DOMAIN/.well-known/oauth-authorization-server` im Browser öffnen, es muss JSON erscheinen. Sonst Permalinks speichern (Einstellungen → Permalinks → Speichern). |
| Anmeldeseite sagt „Keine Berechtigung“ | mit dem Claude-Benutzer oder einem Abonnenten angemeldet | mit eigenem Konto (mind. Redakteur) anmelden |
| Claude kennt ein Werkzeug nicht (z. B. Upload-Link) | Werkzeugliste in Claude veraltet | Connector trennen und neu verbinden, neuen Chat öffnen |
| Claude fragt nicht mit Buttons / zeigt keinen Vorschau-Link | Skill nicht geladen oder veraltet | Skill prüfen/ersetzen, neuen Chat öffnen |
| Systemcheck: „MCP-Endpunkt erreichbar“ ❌ | MCP Adapter fehlt oder startet nicht | Schritt A3 |
| Authorization-Header ❌ | Hosting filtert den Header | Knopf „Authorization-Header aktivieren (.htaccess)“ |
| Vorschau zeigt alten Stand | Seiten-Cache | Abschnitt A7 |
| Upload: „Datei zu groß für den Server“ | PHP-Upload-Limit des Hosters | Limit beim Hoster erhöhen oder Bilder verkleinern |
| Projekt ohne Titelbild/Slider | verwendete Bilder wurden aus der Mediathek gelöscht | Bilder nicht löschen, solange ein Vorschlag offen ist |

---

## Sicherheit

- **Claude-Benutzer** (Redakteur): kommt nicht ins Backend, darf nur die Claude-Schnittstelle nutzen, kann nur über das Werkzeug `vorschlag-freigeben` veröffentlichen.
- **Anmeldung:** OAuth 2.1 mit PKCE (S256) und dynamischer Client-Registrierung. Rücksprung nur zu claude.ai/claude.com oder localhost. Erlauben darf nur ein WordPress-Konto mit mindestens Redakteur-Rechten. Tokens werden nur als Hash gespeichert (Zugang 1 Stunde, Erneuerung 90 Tage).
- **Alle Verbindungen trennen:** WordPress → Claude → „Alle Verbindungen trennen“.
- **Vorschau-Links:** zufälliger Schlüssel, gültig solange der Vorschlag offen ist (max. 14 Tage), für Suchmaschinen gesperrt.
- **Upload-Links:** zufälliger Schlüssel, 2 Stunden gültig, max. 40 Bilder, nur echte JPG/PNG/WebP (Inhalt wird geprüft).
- **Revisionen:** Jede Freigabe erzeugt eine normale WordPress-Revision, ein Zurück ist also jederzeit möglich.
- **Deaktivieren:** Plugin deaktivieren genügt. Die .htaccess-Ergänzung wird dabei automatisch entfernt.

---

## Referenz: Werkzeuge

| Werkzeug | Zweck | Ändert etwas? |
|---|---|---|
| `projekte-auflisten` | Projekte mit ID und Stammdaten | nein |
| `seiten-auflisten` | Seiten mit ID, Startseite markiert | nein |
| `inhalt-lesen` | änderbare Texte/Bilder einer Seite oder eines Projekts | nein |
| `medien-suchen` | Bilder in der Mediathek | nein |
| `aenderungen-auflisten` | offene Vorschläge mit Vorschau-Link | nein |
| `upload-link` | Upload-Seite für Bilder erzeugen | nur Mediathek |
| `upload-status` | hochgeladene Bilder abrufen | nein |
| `bild-hochladen` | Bild per https-Link in die Mediathek | nur Mediathek |
| `projekt-anlegen` | neues Projekt (nicht öffentlich, wartet auf Freigabe) | Vorschlag |
| `projekt-aendern` | Stammdaten, Titelbild, Slider | Vorschlag |
| `inhalt-aendern` | Texte/Bilder in Seiten und Projekten | Vorschlag |
| `vorschlag-zuruecknehmen` | Vorschlag verwerfen | nein (live bleibt gleich) |
| `vorschlag-freigeben` | **live schalten**, nur nach Ja im Chat | **ja** |

### Dateien

```
ar-claude-website/
├─ ar-claude-website.php     Hauptdatei, Konstanten (Beitragstyp, Taxonomie, Namespace)
└─ includes/
   ├─ settings.php           Standardwerte der Einstellungen
   ├─ helpers.php            Feldzuordnung (PixFields), Bilder, Kategorien
   ├─ wpbakery.php           WPBakery lesen/ändern, Aufbau neuer Projekte
   ├─ changes.php            Vorschläge, Vorschau-Links, Freigabe
   ├─ abilities.php          die Werkzeuge (Beschreibung + Eingabe-Schema für Claude)
   ├─ uploads.php            Upload-Link und Upload-Seite
   ├─ mcp-server.php         eigener MCP-Server (nur diese Werkzeuge)
   ├─ oauth.php              Anmeldung für den Claude-Connector
   ├─ security.php           Sperren für den Claude-Benutzer
   └─ admin.php              Einstellungsseite „Claude“
skill/ar-website-pflege/SKILL.md   Verhalten von Claude im Chat
scripts/build-zips.sh|.ps1         ZIP-Dateien bauen
```
