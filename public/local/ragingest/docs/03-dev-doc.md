# Entwickler-Dokumentation

## Plugin-Struktur

```text
local/ragingest/
├── version.php
├── settings.php
├── reindex.php
├── API_SPECIFICATION.md
├── classes/
│   ├── api_client.php
│   ├── content_extractor.php
│   ├── course_gate.php
│   ├── course_state.php
│   ├── document.php
│   ├── h5p_embed_helper.php
│   ├── h5p_text_extractor.php
│   ├── hook_callbacks.php
│   ├── ingestion_manager.php
│   ├── multi_document_extractor.php
│   ├── observer.php
│   ├── source_id_helper.php
│   ├── tenant.php
│   ├── plugininfo/ragingestextractor.php
│   └── task/
├── db/
│   ├── access.php
│   ├── events.php
│   ├── hooks.php
│   ├── install.php
│   ├── install.xml
│   ├── subplugins.json
│   ├── tasks.php
│   └── upgrade.php
├── lang/en/local_ragingest.php
├── subplugins/
└── tests/
```

## Komponente

- Frankenstyle: `local_ragingest`
- Namespace: `local_ragingest`
- Subplugin-Typ: `ragingestextractor`
- Capability fuer manuelle Reindexierung: siehe `db/access.php`
- Persistente Tabelle: `local_ragingest_course`

## Datenfluss

```text
Moodle Event
  -> local_ragingest\observer
  -> Ad-hoc Task
  -> local_ragingest\ingestion_manager
  -> ragingestextractor_* Subplugin
  -> document normalization
  -> local_ragingest\api_client
  -> external RAG API
```

## Event- und Task-Modell

`observer.php` reagiert auf Moodle-Core-Events und legt Tasks an. Events fuehren keine API-Calls direkt aus.

- `course_module_created` / `course_module_updated` -> `ingest_module_task`
- `course_module_deleted` -> `delete_module_task`
- Subcontent-Events fuer Book, Glossary, Lesson, Wiki, Database und Quiz -> Re-Ingestion des ganzen Moduls
- Question-Bank-Aenderungen -> Rueckwaertsauflösung auf referenzierende Quizzes
- Course created/updated -> `reconcile_course_task`
- Course deleted -> `course_state::forget()`

`reconcile_all_task` prueft regelmaessig, ob gewuenschter Kursstatus und gespeicherter Indexstatus auseinanderlaufen.

## Ingestion Manager

`ingestion_manager` ist der zentrale Orchestrator.

Wichtige Schritte:

1. API-Konfiguration pruefen.
2. Course Module laden.
3. `course_gate::should_ingest()` pruefen.
4. Passenden Extractor ueber `core_component::get_plugin_list('ragingestextractor')` suchen.
5. Einzel- oder Multi-Dokument extrahieren.
6. H5P-Platzhalter in HTML aufloesen, begrenzt auf den aktuellen Kurskontext.
7. Content-Type gegen Allowlist pruefen.
8. Aktivitaetsueberschrift ergaenzen.
9. Groessenlimit anwenden.
10. Payload bauen und per `api_client` upserten.

## Dokumentmodell

Ein Extractor liefert fuer Einzel-Dokumente:

```php
[
    'content' => $content,
    'content_type' => 'text/html',
    'title' => $title,
]
```

Multi-Dokument-Extractors implementieren zusaetzlich `multi_document_extractor` und liefern:

```php
[
    [
        'content' => $content,
        'content_type' => 'application/pdf',
        'title' => $title,
        'suffix' => 'file1',
    ],
]
```

Der `suffix` wird an die Modul-Source-ID angehaengt. Vor Multi-Dokument-Upserts loescht der Manager die bisherige Dokumentmenge per Prefix-Delete.

## Source IDs und Tenant

`source_id_helper` baut stabile IDs:

```text
{tenant}:course{courseid}:cmid{cmid}
{tenant}:course{courseid}:cmid{cmid}:{suffix}
```

`tenant::id()` leitet den Tenant aus `CFG->wwwroot` ab. Dadurch muss kein Tenant-Setting gepflegt werden.

## API Client

`api_client` liest diese Settings:

- `rag_endpoint_url`
- `rag_api_key`
- `request_timeout_seconds`

Der Client:

- sendet JSON mit `Content-Type: application/json`
- sendet `X-API-Key`
- nutzt Moodle `curl`; private/interne Ziele sind nur ueber `allow_private_target` explizit erlaubt
- unterstuetzt Service-URLs wie `.../documents/upsert` sowie lokale LiteRAG-Routen
  `.../ingest.php`, `.../ingest.php/upsert` und `.../ingest.php?action=upsert`
- prueft Health ueber die abgeleitete Health-Route
- wiederholt Netzwerkfehler und HTTP-5xx nur kurz, damit Moodle-Cron nicht lange blockiert
- wiederholt HTTP-4xx nicht

## Course Gate und Reconciliation

`course_gate` entscheidet den gewuenschten Zustand:

1. Site course und ungueltige IDs sind immer aus.
2. Wenn Markierung nicht gesperrt ist, gewinnt das Kursfeld `Include` / `Exclude`.
3. Danach greifen Pilotkursliste und Kategorie-Allowlist.

Das Admin-Setting `local_ragingest/pilotcourses` wird durch
`core_admin\local\settings\autocomplete` gerendert und speichert neue Werte als
kommaseparierte Kurs-IDs. `course_gate::pilot_course_ids()` akzeptiert
weiterhin alte newline-separierte Shortname/ID-Werte, damit bestehende
Konfigurationen bis zum naechsten Speichern wirksam bleiben.

`course_state` speichert den Ist-Zustand. Reconcile handelt nur auf Zustandswechsel:

- desired false -> current true: Kurs purgen
- desired true -> current false: Kurs reindexieren
- gleich: nichts tun

Ein Reindex-Lauf setzt den Kurs nur dann auf `ingested = 1`, wenn keine
Fehler-Resultate zurueckkamen. `skipped`-Resultate gelten nicht als Fehler, damit
leere oder nicht unterstuetzte Kurse nicht endlos queued werden. Die Methode
`set_ingested()` faengt parallele Insert-Races ab und aktualisiert den Zustand
anschliessend atomnah per `set_field()`.

`pending_ingestion_count()` zaehlt freigegebene, aber noch nicht indexierte
Kurse. `queue_pending_ingestions()` queued nur diese Kurse; `queue_divergent_reconciles()`
queued auch Purges fuer Kurse, die nicht mehr freigegeben sind.

## Sicherheits- und Review-Haertungen

- Rubrik-Level werden nur fuer die Kriterien der aktuellen Definition geladen,
  nicht systemweit.
- Grading-Kriterien und Workshop-Rubrics werden als Text escaped.
- Quiz-, Database- und Feedback-Extractor bereinigen Editor-/User-Inhalte, bevor
  sie HTML fuer den RAG-Service zusammensetzen.
- H5P-Placeholder-Resolving akzeptiert bei Ingestion nur Dateien aus dem
  aktuellen Kurskontext oder dessen Kindkontexten.
- SCORM/IMSCP vermeiden Body-Regex-Extraktion fuer HTML-Dateien groesser als
  2 MB.
- `question_changed()` im Observer faengt unerwartete DML-/Runtime-Fehler ab und
  loggt sie mit `debugging()`.
- `reconcile_course_task` laesst Exceptions bewusst wieder hochgehen, damit
  Moodle den Ad-hoc-Task als fehlgeschlagen markieren und wiederholen kann.

## Extractor-Entwicklung

Ein neuer Extractor liegt unter:

```text
subplugins/{name}/
├── version.php
├── classes/extractor.php
└── lang/en/ragingestextractor_{name}.php
```

Die Klasse heisst:

```php
\ragingestextractor_{name}\extractor
```

Sie implementiert `local_ragingest\content_extractor`.

Leitlinien:

- keine personenbezogenen Abgaben indexieren, wenn das Modul Nutzerantworten enthaelt
- Moodle-Formattexte sauber ueber Core-APIs formatieren
- H5P-Platzhalter nicht selbst duplizieren; HTML wird zentral bereinigt
- `content_type` nur aus der erlaubten Liste verwenden
- leere Inhalte als `null` oder leere Liste zurueckgeben
- fuer mehrere Dateien/Subobjekte `multi_document_extractor` nutzen

## Tests

Die PHPUnit-Suite liegt unter `tests/` und deckt zentrale Klassen sowie Extractors ab:

- API Client
- Ingestion Manager
- Course Gate / Course State
- Source IDs / Tenant
- H5P-Extraktion
- einzelne Activity-Extractors

Nach Code-Aenderungen mindestens ausfuehren:

```text
php -l <geaenderte PHP-Dateien>
vendor/bin/phpunit local_ragingest_testsuite
```

Je nach lokaler Moodle-Installation kann der konkrete PHPUnit-Befehl abweichen.
