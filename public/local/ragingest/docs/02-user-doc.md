# Benutzer-Dokumentation

## Zweck

`local_ragingest` indexiert Moodle-Kursinhalte fuer einen externen RAG-Service. Aus Admin-Sicht geht es vor allem um drei Dinge:

1. RAG-Endpunkt und API-Key konfigurieren.
2. Festlegen, welche Kurse indexiert werden duerfen.
3. Kurse oder Module bei Bedarf neu indexieren.

Das Plugin arbeitet im Hintergrund. Lehrende muessen fuer normale Kursaenderungen keinen Export starten.

## Zielgruppen

- **Site-Admins:** installieren und konfigurieren das Plugin.
- **Manager / berechtigte Rollen:** koennen Kurse fuer RAG-Ingestion markieren, sofern die Markierung nicht gesperrt ist.
- **Lehrende:** aendern Kursinhalte wie gewohnt; die Ingestion reagiert im Hintergrund, wenn der Kurs markiert ist.

## Installation

1. Plugin-Code nach `local/ragingest` kopieren.
2. Moodle-Upgrade ausfuehren, z. B. ueber Website-Administration oder CLI.
3. Einstellungen unter **Website-Administration > Plugins > Lokale Plugins > RAG Content Ingestion** pruefen.

## Grundkonfiguration

### RAG Endpoint URL

Die URL des Upsert-Endpunkts, z. B.:

```text
http://rag-service:8001/documents/upsert
```

Der Delete-Endpunkt wird automatisch abgeleitet, indem `/upsert` durch `/delete` ersetzt wird.
Fuer lokale LiteRAG-Installationen kann auch ein Endpoint wie
`http://localhost:8080/local/literag/ingest.php` oder
`.../ingest.php/upsert` genutzt werden. Das Plugin leitet daraus Health,
Upsert und Delete automatisch ab.

### API Key

Der API-Key wird serverseitig als Header `X-API-Key` gesendet. Ohne API-Key gilt die API als nicht konfiguriert und Ingestion-Tasks senden keine Dokumente.

### Max Document Size

Maximale Dokumentgroesse in MB. Text und HTML werden bei Ueberschreitung gekuerzt, PDF/Binaerdaten werden uebersprungen.

### Request Timeout

Timeout fuer API-Requests. Netzwerkfehler und Serverfehler werden kurz erneut
versucht. Laengere Ausfaelle sollen ueber Moodles Task-Retry abgefangen werden,
damit Cron-Worker nicht lange blockieren.

## Settings- und Statusseite

Die RAG-Ingest-Settings sind in die eLeDia.ai-Tutor-Navigation eingebunden und
liegen auf einer einzelnen Seite:

```text
http://localhost:8080/admin/settings.php?section=local_ragingest_settings
```

Oben zeigt die Seite den aktuellen Indexierungsstatus:

- **Freigegebene Kurse warten auf die Indexierung:** Es gibt Kurse, die per
  Pilotliste, Kategorie oder Kursfeld freigegeben sind, aber noch nicht
  erfolgreich indexiert wurden.
- **Freigegebene Kurse sind indexiert:** Aktuell wartet kein freigegebener Kurs
  auf einen Reindex-Lauf.

Wenn Kurse warten, kann der Button **Freigegebene Kurse jetzt indexieren** alle
offenen Kurse als Moodle-Ad-hoc-Tasks einplanen.

## Kurse fuer RAG markieren

Die Ingestion ist opt-in. Ohne Markierung wird nichts gesendet.

### Pilotkursliste

Admins koennen einzelne Kurse zentral als Pilotkurse auswaehlen. Das Feld ist eine Mehrfachauswahl mit Suche: Kursnamen suchen, Treffer anklicken, ausgewaehlte Kurse erscheinen als Chips. Mehrere Kurse koennen gleichzeitig aktiv sein.

### Kategorie-Allowlist

Admins koennen Kurskategorien ueber eine Mehrfachauswahl mit Suche aktivieren. Ausgewaehlte Kategorien erscheinen als Chips. Kurse in diesen Kategorien und deren Unterkategorien sind dann fuer RAG-Ingestion vorgesehen.

### Kursfeld "RAG ingestion"

Wenn die Kursmarkierung nicht gesperrt ist, kann ein Kurs ueber das Custom Field gesteuert werden:

- **Default:** zentrale Pilot-/Kategorie-Regel entscheidet.
- **Include:** Kurs wird indexiert.
- **Exclude:** Kurs wird nicht indexiert.

### Lock course marking

Im Pilotbetrieb kann die Kursmarkierung gesperrt werden. Dann hat das Kursfeld keine Wirkung und wird im Kursformular entfernt. Nur Pilotkursliste und Kategorie-Allowlist zaehlen.

## Typischer Admin-Workflow

1. RAG-Endpunkt und API-Key eintragen.
2. Einen oder mehrere Pilotkurse eintragen.
3. Moodle-Cron laufen lassen, damit Ad-hoc Tasks verarbeitet werden.
4. Bei Bedarf freigegebene Kurse ueber die Statuskarte gesammelt indexieren
   oder einen Pilotkurs manuell neu indexieren.
5. Ergebnisse im RAG-Service pruefen.
6. Nach erfolgreichem Pilot Kategorien aktivieren oder weitere Kurse aufnehmen.

## Automatische Ingestion

Wenn ein markierter Kurs Inhalte enthaelt und eine unterstuetzte Aktivitaet erstellt oder aktualisiert wird, legt Moodle eine Hintergrundaufgabe an. Die Nutzeraktion selbst wartet nicht auf den externen RAG-Service.

Unterstuetzte Aenderungen umfassen u. a.:

- Course Module erstellt, aktualisiert oder geloescht
- Book-Kapitel geaendert
- Glossary-Eintrag geaendert
- Lesson-Seite geaendert
- Wiki-Seite geaendert
- Database-Record geaendert
- Quiz-Struktur oder verwendete Frage geaendert

## Manuelle Reindexierung

Das Plugin stellt eine Reindex-Funktion fuer berechtigte Nutzer bereit. Sie dient dazu, alle unterstuetzten Module eines Kurses erneut an den RAG-Service zu senden.

Die Reindex-Seite unterscheidet zwei Aktionen:

- **Freigegebene Kurse jetzt indexieren:** plant alle freigegebenen, noch nicht
  indexierten Kurse als Hintergrundaufgaben ein.
- **Manuelle Kurs-Reindexierung:** reindexiert gezielt einen Kurs anhand seiner
  numerischen Moodle-Kurs-ID.

Erwartetes Ergebnis:

- unterstuetzte Aktivitaeten werden neu extrahiert
- nicht unterstuetzte oder leere Aktivitaeten werden uebersprungen
- Fehler werden pro Modul gemeldet, stoppen aber nicht den gesamten Kurslauf
- Wenn Fehler auftreten, bleibt der Kurs als nicht indexiert markiert, damit er
  spaeter erneut reconciled werden kann.

## Was wird nicht indexiert?

- Kurse ohne Opt-in-Markierung
- Site course
- geloeschte oder fuer Nutzer nicht sichtbare Course Modules
- nicht unterstuetzte Modultypen
- leere Inhalte
- Binaerdokumente oberhalb der konfigurierten Groessengrenze
- Nutzerantworten in Feedbacks oder andere persoenliche Abgaben, soweit die Extractors dies explizit ausschliessen
