# local_ragingest - eLeDia.ai RagIngest fuer Moodle

[English](README.md) | Deutsch

`local_ragingest` ist ein Moodle-Local-Plugin, das ausgewaehlte Kursinhalte
extrahiert und an einen externen Retrieval-Augmented-Generation- oder
eLeDia.ai RagIngest-Service sendet. Das Plugin ist bewusst als Opt-in gebaut: Ein Kurs
wird nur indexiert, wenn er ueber die Pilotkursliste, eine Kategorie-Allowlist
oder ein Kursfeld freigegeben ist.

Das Plugin ist Teil des eLeDia.ai Tutor / LiteRAG Admin-Flows. Settings,
Statuskarte, Reindex-Seite und DevFlow-Dokumentation werden in der gemeinsamen
Plugin-Shell angezeigt, wenn diese Shell in Moodle vorhanden ist.

## Status

| Punkt | Aktueller Stand |
|---|---|
| Moodle-Komponente | `local_ragingest` |
| Plugin-Typ | Local plugin unter `local/ragingest` |
| Offiziell unterstuetzte Moodle-Versionen | `4.5` bis `5.1` in `version.php` |
| Lokaler Kompatibilitaetscheck | Moodle `5.2.1` besteht die PHPUnit-Suite |
| PHP | Moodle-unterstuetzte PHP-Version fuer die Zielversion |
| Reifegrad | Beta |
| Arbeitsbranch | `review_johannes` |

Moodle 5.2.1 wird lokal bereits fuer Entwicklung und Tests genutzt. Die
offizielle `supported`-Angabe bleibt aber noch `[405, 501]`, bis der finale
Kompatibilitaetscheck abgeschlossen ist.

## Features

- **Opt-in-Indexierung:** Kurse werden nur indexiert, wenn sie zentral oder am
  Kurs freigegeben sind.
- **Suchbare Kursauswahl:** Pilotkurse und Kategorien nutzen Moodle-
  Autocomplete mit Suche, Chips und Mehrfachauswahl.
- **Sperre fuer Kursmarkierung:** Im Pilotbetrieb kann das Kursfeld
  read-only/inert gesetzt werden, sodass nur zentrale Admin-Settings gelten.
- **Automatische Reconciliation:** Aenderungen an Verbindung, Limits,
  Kategorien, Pilotkursen oder Lock-Status planen divergente Kurse neu ein.
- **Indexstatus:** Die Settings-Seite zeigt, ob freigegebene Kurse noch auf
  Indexierung warten, und bietet eine prominente Sammelaktion.
- **Automatische Ingestion:** Moodle-Events legen Ad-hoc-Tasks fuer Modul-
  Erstellung, Aktualisierung, Loeschung und unterstuetzte Subcontent-Aenderungen
  an.
- **Manueller Reindex:** Admins koennen alle ausstehenden freigegebenen Kurse
  einplanen oder einen Kurs gezielt per ID reindexieren.
- **Healthchecks:** Der API-Client leitet fuer lokale LiteRAG-Routen einen
  Health-Endpunkt ab.
- **Extractor-Subplugins:** Aktivitaetsspezifische Extraktion lebt in
  `ragingestextractor_*` Subplugins.
- **Multi-Dokument-Module:** Ordner und aehnliche Module koennen mehrere
  Dokumente mit Suffix-Source-IDs senden.
- **Deterministische Source IDs:** IDs enthalten Tenant, Kurs-ID, Modul-ID und
  optional ein Dokument-Suffix.
- **Tenant aus Site-URL:** Die Tenant-ID wird aus `$CFG->wwwroot` abgeleitet.
  Es gibt absichtlich kein frei editierbares Tenant-Setting.
- **Privacy-Metadaten:** Der Privacy-Provider deklariert die externe
  Uebermittlung von Kurs-/Modul-Metadaten und extrahiertem Inhalt an den
  RAG-Service.

## Installation

1. Plugin-Code in die Moodle-Codebasis kopieren:

   ```text
   local/ragingest
   ```

2. Moodle-Upgrade ueber Website-Administration oder CLI ausfuehren:

   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```

3. Settings-Seite oeffnen:

   ```text
   /admin/settings.php?section=local_ragingest_settings
   ```

4. RAG-Endpunkt und API-Key konfigurieren.

5. Einen oder mehrere Pilotkurse freigeben und Moodle-Cron laufen lassen, damit
   die Ad-hoc-Tasks die Indexierung verarbeiten koennen.

## Admin-Settings

Alle Einstellungen liegen auf einer Seite:

```text
/admin/settings.php?section=local_ragingest_settings
```

Wenn die eLeDia.ai Tutor Shell vorhanden ist, erscheint diese Seite in der
gemeinsamen Navigation mit aktivem Menuepunkt **eLeDia.ai RagIngest**. Das Setup ist
nicht mehr auf mehrere Moodle-Admin-Menues verteilt.

### Connection

| Setting | Beschreibung | Default |
|---|---|---|
| RAG Endpoint URL | Upsert-Endpunkt oder LiteeLeDia.ai RagIngest-Route | `http://rag-service:8001/documents/upsert` |
| API Key | Secret, das als `X-API-Key` gesendet wird | leer |
| Allow private target | Aktiviert Moodle-cURL `ignoresecurity` fuer lokale/private Ziele | aus |

Unterstuetzte Endpoint-Formen:

- `http://rag-service:8001/documents/upsert`
- `http://localhost:8080/local/literag/ingest.php`
- `http://localhost:8080/local/literag/ingest.php/upsert`
- `http://localhost:8080/local/literag/ingest.php?action=upsert`

Fuer LiteRAG-Routen werden Health-, Upsert- und Delete-URLs automatisch
abgeleitet. Fuer `/documents/upsert` wird Delete durch Ersetzen von `/upsert`
durch `/delete` abgeleitet.

`allow_private_target` ist noetig, weil Moodle cURL-Aufrufe auf private,
Loopback- oder blockierte Ziele normalerweise schuetzt. Fuer oeffentliche
Endpunkte sollte es aus bleiben; fuer lokale Docker-/Service-Name-Ziele muss es
bewusst aktiviert werden.

### Course Selection

| Setting | Beschreibung |
|---|---|
| Ingested course categories | Suchbare Mehrfachauswahl von Kategorien. Kurse in ausgewaehlten Kategorien oder Unterkategorien sind freigegeben. |
| Pilot courses | Suchbare Mehrfachauswahl konkreter Kurse fuer Pilotphasen. |
| Lock course marking | Macht das Kursfeld inert/read-only, sodass nur zentrale Settings entscheiden. |

Das Kursfeld **eLeDia.ai RagIngest** wird bei der Installation angelegt. Wenn die
Kursmarkierung nicht gesperrt ist, gibt es:

- `Default`: zentrale Pilot-/Kategorie-Regeln entscheiden.
- `Include`: Kurs freigeben, auch wenn zentrale Regeln ihn nicht freigeben.
- `Exclude`: Kurs ausschliessen, auch wenn zentrale Regeln ihn freigeben.

Wenn die Markierung gesperrt ist, bleiben bestehende Feldwerte erhalten, werden
aber ignoriert, bis die Sperre wieder deaktiviert wird.

### Limits

| Setting | Beschreibung | Default |
|---|---|---|
| Max document size (MB) | Text/HTML oberhalb des Limits wird gekuerzt; zu grosse Binaerdateien werden uebersprungen | `20` |
| Request timeout (seconds) | Timeout pro HTTP-Request-Versuch | `30` |

## Indexstatus und Reindex

Oben auf der Settings-Seite erscheint eine Statuskarte:

- **Released courses are indexed:** aktuell warten keine freigegebenen Kurse auf
  Indexierung.
- **Released courses are waiting for indexing:** mindestens ein freigegebener
  Kurs wurde noch nicht erfolgreich indexiert.

Wenn Kurse warten, plant die primaere Aktion **Index released courses now** die
ausstehenden Kurse als Moodle-Ad-hoc-Tasks ein. Die zweite Aktion oeffnet:

```text
/local/ragingest/reindex.php
```

Die Reindex-Seite bietet:

- Sammel-Queueing fuer freigegebene, noch nicht indexierte Kurse
- manuelle Reindexierung eines Kurses per numerischer Moodle-Kurs-ID

Der Kurszustand wird in `local_ragingest_course` gespeichert. Ein Kurs bekommt
`ingested = 1` nur, wenn ein Reindex-Lauf ohne Fehler-Resultate abgeschlossen
wurde. `skipped`-Module gelten nicht als Fehler, damit leere oder nicht
unterstuetzte Kurse nicht endlos eingeplant werden.

## Was wird indexiert?

Das Plugin indexiert nur Inhalte aus freigegebenen Kursen. Nicht indexiert
werden:

- der Site Course
- geloeschte oder unsichtbare Course Modules
- Kurse ohne Opt-in-Freigabe
- nicht unterstuetzte Modultypen
- leere Aktivitaeten
- zu grosse Binaerdateien
- persoenliche Lernendenantworten in Feedbacks und aehnlichen Extractors, wo
  diese Inhalte bewusst ausgeschlossen sind

Unterstuetzte Event-Ausloeser:

- Course Module erstellt, aktualisiert oder geloescht
- Book-Kapitel geaendert
- Glossary-Eintrag geaendert
- Lesson-Seite geaendert
- Wiki-Seite geaendert
- Database-Record geaendert
- Quiz-Struktur geaendert
- Question-Bank-Aenderungen, die Quizzes betreffen

## Mitgelieferte Extractors

Das Plugin bringt Extractors fuer verbreitete Moodle-Core-Aktivitaeten und
ausgewaehlte Paket-/Interaktionsmodule mit.

| Subplugin | Aktivitaet | Hinweise |
|---|---|---|
| `assign` | Assignment | Intro, Aktivitaetsanweisungen, Bewertungskriterien; keine Abgaben |
| `book` | Book | Sichtbare Kapitel/Subkapitel in Lesereihenfolge |
| `data` | Database | Felddefinitionen und freigegebene Records |
| `feedback` | Feedback | Fragen/Item-Definitionen; keine abgegebenen Antworten |
| `folder` | Folder | Ein Dokument pro unterstuetzter Datei, PDF bleibt PDF |
| `glossary` | Glossary | Beschreibung, freigegebene Eintraege, Aliase/Synonyme |
| `h5pactivity` | H5P Activity | Gelabelter Lerntext aus H5P JSON/Paketinhalt |
| `imscp` | IMS Content Package | Manifest-Struktur und HTML-Body-Inhalte |
| `label` | Text/media area | Intro-Inhalt |
| `lesson` | Lesson | Seitenfolge, Antworten, Feedback wo relevant |
| `page` | Page | Seiteninhalt |
| `quiz` | Quiz | Fragen, Antworten, Feedback, Hinweise und Gesamtfeedback |
| `resource` | File | Unterstuetzte Text-, HTML- und PDF-Dateien |
| `scorm` | SCORM | Intro, SCO-Titel und lokale HTML-Launch-Seiten |
| `videotime` | Video Time | Intro und VTT-Captions/Transkript |
| `wiki` | Wiki | Intro und Subwiki-Seiten |
| `workshop` | Workshop | Intro, Anweisungen, Abschluss, Bewertungsdimensionen |

Von Extractors erzeugtes HTML wird zentral fuer eingebettete H5P-Platzhalter
nachbearbeitet, soweit diese aufloesbar sind.

## API Contract

### Upsert

```http
POST /documents/upsert
X-API-Key: <configured key>
Content-Type: application/json
```

```json
{
    "source_id": "localhost:course42:cmid99",
    "content": "<base64-encoded content>",
    "content_type": "text/html",
    "qdrant_metadata": {
        "tenant_id": "localhost",
        "site_url": "http://localhost:8080",
        "course_id": 42,
        "cmid": 99,
        "module_url": "http://localhost:8080/mod/page/view.php?id=99"
    },
    "parser_options": null
}
```

### Delete

```http
POST /documents/delete
X-API-Key: <configured key>
Content-Type: application/json
```

```json
{
    "source_id": "localhost:course42:cmid99"
}
```

Multi-Dokument-Module nutzen Suffixe wie:

```text
localhost:course42:cmid99:file1
```

Vor einem Multi-Dokument-Upsert loescht der Manager die bisherige Dokumentmenge
per Prefix-Delete.

Erlaubte Content Types:

- `text/plain`
- `text/html`
- `application/pdf`

## Architektur

```text
Moodle event
  -> local_ragingest\observer
  -> Moodle ad-hoc task
  -> local_ragingest\ingestion_manager
  -> ragingestextractor_* subplugin
  -> document normalization
  -> local_ragingest\api_client
  -> external RAG service
```

Wichtige Klassen:

| Klasse | Aufgabe |
|---|---|
| `content_extractor` | Interface fuer Aktivitaets-Extractors |
| `multi_document_extractor` | Optionales Interface fuer mehrere Dokumente pro Modul |
| `ingestion_manager` | Findet Extractors, validiert/normalisiert Dokumente, ruft die API auf |
| `api_client` | HTTP-Client, Endpoint-Ableitung, Health/Upsert/Delete |
| `course_gate` | Berechnet, ob ein Kurs indexiert werden soll |
| `course_state` | Speichert Indexzustand und queued Reconciliation |
| `observer` | Wandelt Moodle-Events in Hintergrundtasks um |
| `source_id_helper` | Baut deterministische Source IDs |
| `tenant` | Leitet die Tenant-ID aus `$CFG->wwwroot` ab |
| `h5p_embed_helper` | Loest eingebettete H5P-Platzhalter in HTML auf |
| `h5p_text_extractor` | Extrahiert gelabelten Text aus H5P JSON/Paketen |
| `output\shell` | Bindet Plugin-Seiten in die eLeDia.ai Tutor Shell ein |

Tasks:

| Task | Aufgabe |
|---|---|
| `ingest_module_task` | Ein Course Module extrahieren und upserten |
| `delete_module_task` | Dokumentmenge eines Course Modules loeschen |
| `reconcile_course_task` | Einen Kurs in den gewuenschten Index-/Purge-Zustand bringen |
| `reconcile_all_task` | Regelmaessiger Safety-Net-Check fuer divergente Kurse |

## Einen Extractor schreiben

Ein Subplugin liegt unter:

```text
subplugins/{name}/
├── version.php
├── classes/extractor.php
└── lang/en/ragingestextractor_{name}.php
```

Die Extractor-Klasse heisst:

```php
namespace ragingestextractor_{name};

use local_ragingest\content_extractor;

final class extractor implements content_extractor {
    public function supports(\cm_info $cm): bool {
        return $cm->modname === '{name}';
    }

    public function extract(\cm_info $cm): ?array {
        return [
            'content' => '<p>Extracted content</p>',
            'content_type' => 'text/html',
            'title' => $cm->name,
        ];
    }
}
```

Leitlinien:

- `null` zurueckgeben, wenn kein sinnvoller Inhalt vorhanden ist
- nur erlaubte Content Types verwenden
- User-/Editor-Inhalte escapen, bevor HTML gebaut wird
- persoenliche Abgaben nicht indexieren, ausser ein spaeteres Feature ist
  explizit dafuer freigegeben und dokumentiert
- fuer Dateien oder wiederholte Subdokumente `multi_document_extractor` nutzen
- H5P-Platzhalter in HTML zentral nachbearbeiten lassen

## Lokale Entwicklung

### Deployment in das lokale eledia.ai Moodle

Das begleitende Docker-Setup im eledia.ai-Projekt stellt ein lokales Moodle
bereit unter:

```text
http://localhost:8080
```

Typischer Ablauf:

```bash
cd /Users/moskaliuk/Documents/Code/eledia.ai
./scripts/local-deploy.sh deploy
```

Wenn dieser Plugin-Checkout nicht ins Image eingebaut ist, muss er nach:

```text
/var/www/html/public/local/ragingest
```

kopiert oder synchronisiert werden. Danach Moodle-Upgrade und Cache-Purge
ausfuehren.

### Debug Server

Fuer lokale API-Tests gibt es einen kleinen Python-Mock-Server:

```bash
python3 local/ragingest/debug_server.py
python3 local/ragingest/debug_server.py --port 9000
python3 local/ragingest/debug_server.py --fail
python3 local/ragingest/debug_server.py --delay 5
```

`debug_server.py` wird ueber `.gitattributes` aus Release-Archiven
ausgeschlossen.

## Tests und Coding Style

### PHPUnit

Das lokale Docker-Setup kann Moodle-PHPUnit initialisieren und die Plugin-Suite
ausfuehren:

```bash
cd /Users/moskaliuk/Documents/Code/eledia.ai
./scripts/local-deploy.sh phpunit-init
./scripts/local-deploy.sh phpunit
PHPUNIT_TESTSUITE=local_ragingest_testsuite ./scripts/local-deploy.sh phpunit
```

Aktuelles lokales Ergebnis:

```text
Tests: 164
Assertions: 373
Failures: 0
Errors: 0
Skipped: 5
PHPUnit Deprecations: 27
Notices: 1
```

In einem Moodle-Checkout mit bereits initialisierter PHPUnit-Umgebung:

```bash
vendor/bin/phpunit --testsuite local_ragingest_testsuite
```

### PHP-Syntax

```bash
find public/local/ragingest -name '*.php' -print0 | xargs -0 -n1 php -l
```

### Moodle Coding Style

`moodlehq/moodle-cs` ausserhalb des Plugin-Checkouts installieren und PHPCS
ausfuehren:

```bash
rm -rf /tmp/local-ragingest-moodle-cs
mkdir -p /tmp/local-ragingest-moodle-cs
cd /tmp/local-ragingest-moodle-cs
composer init --no-interaction --name=local-ragingest/moodle-cs-tools
composer config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer require --dev moodlehq/moodle-cs

cd /Users/moskaliuk/Documents/Code/local_ragingest
/tmp/local-ragingest-moodle-cs/vendor/bin/phpcs \
    --standard=moodle \
    --extensions=php \
    '--ignore=public/local/ragingest/tests/fixtures/*' \
    public/local/ragingest
```

Style-only-Probleme automatisch beheben:

```bash
/tmp/local-ragingest-moodle-cs/vendor/bin/phpcbf \
    --standard=moodle \
    --extensions=php \
    '--ignore=public/local/ragingest/tests/fixtures/*' \
    public/local/ragingest
```

Der aktuelle Branch besteht `phpcs --standard=moodle`.

### Frontend-Checks

Moodles Grunt-Tooling laeuft in einem Moodle-Checkout mit Node `>=22.11 <23`.
Aus dem Plugin-Verzeichnis innerhalb dieses Checkouts:

```bash
npx grunt amd --no-color
npx grunt rawcss --no-color
```

`amd` fuehrt `ignorefiles`, `eslint:amd` und `rollup` aus und erzeugt
`amd/build/*.min.js` neu. `rawcss` fuehrt Stylelint fuer CSS-Dateien aus.

Dieses Plugin hat aktuell keine Mustache-Templates und keine gebuendelten
Third-Party-Libraries. Mustache- und Third-Party-Library-Checks sind daher im
Moment nicht anwendbar. Wenn spaeter Templates oder gebuendelte Libraries
hinzukommen, gehoeren die entsprechenden Moodle-Prechecks vor die Submission.

## DevFlow

Der Arbeits-DevFlow liegt in:

```text
docs/00-master.md
docs/01-features.md
docs/02-user-doc.md
docs/03-dev-doc.md
docs/04-tasks.md
docs/05-quality.md
```

Startpunkt ist `docs/00-master.md`; danach offene Punkte in `docs/04-tasks.md`
pruefen. DevFlow wird aktualisiert, wenn sich Verhalten, sichtbare UX,
Implementierung oder Testnachweise aendern.

## Privacy

Das Plugin sendet extrahierte Kursinhalte und Modul-Metadaten an einen externen
RAG-Service. Der Privacy-Provider deklariert diese externe Location inklusive:

- Site URL
- Course ID
- Course Module ID
- extrahierter Inhalt

Das Plugin speichert selbst keine personenbezogenen Inhaltsdatensaetze.
Extractors sind dafuer verantwortlich, persoenliche Lernendenabgaben zu
vermeiden, sofern kein spaeteres Feature dieses Verhalten explizit einfuehrt und
dokumentiert.

## Capabilities

| Capability | Zweck |
|---|---|
| `local/ragingest:reindex` | Erlaubt Zugriff auf manuelle Reindex-Operationen |

Manager erhalten diese Capability standardmaessig.

## Lizenz

GNU GPL v3 oder spaeter.

## Autor

Christopher Reimann, eLeDia GmbH.
