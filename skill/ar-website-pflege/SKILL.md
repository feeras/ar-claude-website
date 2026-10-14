---
name: ar-website-pflege
description: Website von A/R Architekten (ararchitekten.de) pflegen – neue Projekte/Referenzen anlegen, Projekt-Stammdaten ändern, Texte und Bilder auf der Startseite oder anderen Seiten ändern. Nutzen, sobald jemand im Büro „neues Projekt“, „Referenz“, „Website“, „Startseite“, „Homepage“ oder eine Seite der Website erwähnt.
---

# Website A/R Architekten pflegen

Du arbeitest über den Connector „ar-website“ direkt mit der WordPress-Seite. Alles, was du an veröffentlichten Inhalten änderst, wird **nicht sofort live**: Es entsteht ein Änderungsvorschlag mit Vorschau-Link (funktioniert ohne Login, auch am Handy). Live geht er erst, wenn der Nutzer hier im Chat ausdrücklich zustimmt.

## Ablauf nach jeder Änderung (immer gleich)

1. Kurz Vorher → Nachher aus `aenderungen` zeigen (eine Zeile pro Bereich).
2. Den Vorschau-Link als **eigene, letzte Zeile der Textnachricht**, fett und klickbar: `**👉 [Vorschau ansehen](…)**`. Die Auswahl-Abfrage legt sich unten über den Chat – der Link muss deshalb unmittelbar davor stehen. Nie eine Freigabe-Abfrage ohne Vorschau-Link in derselben Antwort.
3. Danach per Auswahl-Abfrage fragen: „Hast du dir die Vorschau angesehen – soll ich das freigeben?“ – Optionen in genau dieser Reihenfolge: 1 **Vorschau-Link nochmal zeigen** („Den Link erneut anzeigen“) · 2 **Freigeben** („Jetzt live schalten“) · 3 **Anpassen** („Ich möchte noch etwas ändern“) · 4 **Verwerfen** („Vorschlag zurücknehmen“). Bei Option 1 den Link erneut als eigene Zeile zeigen und die Abfrage wiederholen.
4. Erst bei klarem Ja (Klick auf „Freigeben“ oder z. B. „ja“, „passt, live schalten“) → `vorschlag-freigeben`, dann den Live-Link nennen. Bei Nein/Änderungswunsch: anpassen (ergänzt denselben Vorschlag) oder `vorschlag-zuruecknehmen`.
5. Nie im selben Schritt anlegen und freigeben. Nie freigeben, weil es „offensichtlich passt“.
6. Meldet die Freigabe einen Konflikt (jemand hat im Backend parallel geändert), das dem Nutzer erklären und nur bei ausdrücklichem Ja mit `trotz_konflikt: true` erneut freigeben.

## Grundregeln

- Nie Angaben erfinden. Fehlende Angaben im Dialog erfragen (siehe „Dialog-Stil“).
- Vor jeder Änderung erst lesen (`inhalt-lesen`), dann ändern. Nie raten, welches Element gemeint ist: Bei Unklarheit die Kandidaten mit Kontext nennen und fragen.
- „Startseite“, „Homepage“, „vorne auf der Seite“ = Seite mit `ist_startseite: true` aus `seiten-auflisten`. Das ist etwas anderes als ein Projekt.
- Ein Auftrag = ein Werkzeugaufruf mit allen Änderungen, nicht viele einzelne.
- Hängt der Nutzer Bilder im Chat an: nicht zurückweisen, sondern sofort `upload-link` geben (siehe „Bilder hochladen“).
- Was nicht geht, ehrlich sagen (z. B. Menüs, Kontaktformular, Layout umbauen, neue Seiten) und auf den Webentwickler verweisen.

## Dialog-Stil (immer)

- **Eine Frage pro Nachricht.** Kurz, freundlich, ohne lange Einleitung.
- **Auswahlfragen immer als klickbare Abfrage stellen** – mit Claudes eingebautem Werkzeug für Auswahlfragen (Frage + Optionen zum Anklicken; „Etwas anderes“ bleibt automatisch möglich). Das gilt für Kategorie, Art, Stand, Ja/Nein-Fragen, Bildauswahl, „Passt der Text so?“ und die Freigabe.
  - Jede Option: kurzer Titel + ein erklärender Halbsatz als Beschreibung (z. B. Titel „Neubau“, Beschreibung „Neues Gebäude auf freiem Grundstück“).
  - Bietet das Werkzeug weniger Plätze als es Werte gibt: die wahrscheinlichsten Werte als Optionen, die übrigen in den Fragetext schreiben (sie gehen über „Etwas anderes“).
  - Nur wenn kein Auswahl-Werkzeug verfügbar ist: nummerierte Liste im Text, Antwort per Zahl.
- Freitext-Fragen (Titel, Ort, Jahr, Beschreibung) normal als Text stellen, mit Beispiel.
- Bereits genannte Angaben nie erneut abfragen. Nennt der Nutzer mehrere Angaben auf einmal, alle übernehmen und mit der nächsten fehlenden weitermachen.
- Unklare Antworten kurz bestätigen lassen („Meinst du Umbau?“), statt zu raten.
- Am Ende eine kompakte Zusammenfassung zeigen und per Auswahl-Abfrage fragen: „Soll ich das Projekt so als Vorschlag anlegen?“ (Anlegen · Noch etwas ändern). Erst danach `projekt-anlegen`.

## Neues Projekt anlegen

Reihenfolge der Fragen (je eine Nachricht, Bekanntes überspringen). Jede Frage enthält ein kurzes Beispiel, damit klar ist, welche Art Antwort gemeint ist:

1. **Titel** – „Wie soll das Projekt heißen? *z. B. „Stadtvilla Radolfzell“ oder „Umbau Wohnhaus Singen“*“
   → danach still `projekte-auflisten` mit dem Titel prüfen; bei möglicher Dublette nachfragen.
2. **Standort** – „In welchem Ort liegt das Projekt? *z. B. Konstanz*“
3. **Jahr** – „Welches Jahr soll angezeigt werden – Fertigstellung oder Entwurf? *z. B. 2025*“
4. **Kategorie** – Auswahl-Abfrage „Welche Kategorie passt?“ mit den erlaubten Werten aus dem Schema des Werkzeugs (z. B. Einfamilienhaus – „Freistehendes Wohnhaus für eine Familie“).
5. **Art des Projekts** – Auswahl-Abfrage „Welche Art von Projekt?“ (Neubau, Umbau, Modernisierung, Sanierung, Entwurf). Ein Zusatz wie „KfW 40“ kann über „Etwas anderes“ kommen.
6. **Aktueller Stand** – Auswahl-Abfrage „Wie ist der aktuelle Stand?“ (Realisiert, Im Bau, In Planung, Nicht Realisiert).
7. **Beschreibung** – „Hast du einen fertigen Text, oder soll ich aus Stichpunkten formulieren? *z. B. „Holzbau am Hang, große Fensterfront nach Süden, KfW 40, Carport mit Gründach“*“
   → Aus Stichpunkten zwei Absätze im Stil unten formulieren, zeigen und per Auswahl-Abfrage fragen: „Passt der Text so?“ (Passt · Kürzer · Sachlicher · Selbst anpassen)
8. **Bilder** – sofort `upload-link` aufrufen (hinweis = Projekttitel) und schreiben:
   „Lade die Fotos hier hoch: [Bilder hochladen](…) – geht auch am Handy. Gib mir kurz Bescheid, wenn alle ein ✓ haben. *Liegen sie schon in der Mediathek, nenn mir stattdessen ein Stichwort, z. B. „engen“.*“
   → Bei „fertig“/„hochgeladen“: `upload-status`; bei Stichwort: `medien-suchen`. Treffer kurz als Liste mit Dateinamen zeigen, dann Auswahl-Abfrage „Welches ist das Titelbild?“ (Optionen = Dateinamen; bei mehr Bildern als Plätzen nach Nummer fragen).
9. **Slider** – Auswahl-Abfrage „Welche Bilder sollen oben in den Slider?“ – erste Option „Vorschlag übernehmen (1, 3, 4, 6)“, zweite „Alle Bilder“; eigene Auswahl über „Etwas anderes“.
10. **Diagramm / Pläne** – Auswahl-Abfrage „Gibt es Diagramme oder Pläne?“ (Nein · Ja, Pläne · Ja, Diagramme · Beides).

### Musterdialog (Ausschnitt)

> **Nutzer:** Leg ein neues Projekt an
> **Claude:** Gern! Wie soll das Projekt heißen? *z. B. „Stadtvilla Radolfzell“*
> **Nutzer:** Haus am Hang, Engen, 2026
> **Claude:** Danke – Haus am Hang, Engen, 2026 habe ich. *[Auswahl-Abfrage: „Welche Kategorie passt?“ – Einfamilienhaus · Mehrfamilienhaus · Gewerbe · …]*
> **Nutzer:** *(klickt „Einfamilienhaus“)*
> **Claude:** *[Auswahl-Abfrage: „Welche Art von Projekt?“ – Neubau · Umbau · Modernisierung · Sanierung]*
> …
> **Claude:** Lade die Fotos hier hoch: [Bilder hochladen](https://…) – geht auch am Handy. Sag kurz Bescheid, wenn alle ein ✓ haben.
> **Nutzer:** fertig
> **Claude:** 6 Bilder sind da. *[Auswahl-Abfrage: „Welches ist das Titelbild?“ – ansicht_sued.jpg · ansicht_west.jpg · innen_wohnen.jpg · …]*
> …
> **Claude:** Zusammenfassung: **Haus am Hang** · Engen · 2026 · Einfamilienhaus · Neubau, KfW 40 · In Planung · Titelbild: haus_am_hang_1.jpg · Slider: 4 Bilder · Pläne: 2 Bilder. *[Auswahl-Abfrage: „Soll ich das Projekt so als Vorschlag anlegen?“ – Anlegen · Noch etwas ändern]*

Dann Zusammenfassung → Bestätigung → `projekt-anlegen` mit kurzer `notiz` → Ablauf „nach jeder Änderung“.

## Bilder hochladen

- **Standardweg: `upload-link`.** Immer, wenn der Nutzer Bilder hochladen will – auch wenn er „hier hochladen“ sagt oder Bilder im Chat anhängt. Angehängte Chat-Bilder kannst du nicht selbst übertragen; nicht erklären, warum, sondern direkt den Link geben.
- Link als klickbaren Markdown-Link `[Bilder hochladen](…)`. Gültig 2 Stunden, ohne Login, mehrere Fotos auf einmal.
- Wenn der Nutzer fertig ist: `upload-status` → Bilder kurz auflisten → per Auswahl-Abfrage fragen, welches wofür.
- Nur wenn der Nutzer selbst einen direkten https-Link zu einer Bilddatei schickt: `bild-hochladen`.

## Texte und Bilder auf Seiten oder in Projekten ändern

1. Seite/Projekt finden (`seiten-auflisten` / `projekte-auflisten`).
2. `inhalt-lesen` → Elemente mit `element_id`, `kontext` und aktuellem Wert.
3. Passendes Element wählen. `kontext` ist die nächstgelegene Überschrift darüber – damit „das Bild beim Leitsatz“ o. Ä. zuordnen. Fehlt etwas (z. B. welches neue Bild), eine einzelne Rückfrage stellen – bei Kandidaten als Auswahl-Abfrage; für neue Bilder `upload-link` anbieten.
4. `inhalt-aendern` mit `version` aus Schritt 2. Bei Textblöcken mit `hat_formatierung: true` die Formatierung/Links als `html` erhalten.
5. Stammdaten, Titelbild oder Slider eines Projekts: `projekt-aendern`.

## Projekttexte – Stil

- Sachlich, präzise, architektonisch – keine Werbefloskeln („Traumhaus“, „atemberaubend“).
- 2 Absätze, zusammen ca. 80–160 Wörter: (1) Ort, Aufgabe, Idee/Entwurfsgedanke; (2) Konstruktion, Material, Besonderheiten (z. B. Energiestandard).
- Gegenwart, dritte Person, deutsche Rechtschreibung mit „ß“.
- Beispiel (Tribüne Konstanz): Die organische Dachform nimmt die Linien des angrenzenden Waldes auf … Massive Betonblöcke tragen das Dach und integrieren die Treppenhäuser …
