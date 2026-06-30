# local_ragingest - eLeDia.ai RagIngest for Moodle

English | [Deutsch](README.de.md)

`local_ragingest` is a Moodle local plugin that extracts selected course content
and sends it to an external Retrieval-Augmented Generation (RAG) ingestion
service. It is designed for controlled, opt-in indexing: no course is ingested
unless it is explicitly released through a pilot-course list, a category
allow-list, or a course-level override.

The plugin is part of the eLeDia.ai Tutor / LiteRAG admin flow. Its settings,
status panel, reindex page, and DevFlow documentation are rendered in the shared
plugin shell when that shell is available.

## Status

| Item | Current state |
|---|---|
| Moodle component | `local_ragingest` |
| Plugin type | Local plugin installed at `local/ragingest` |
| Supported Moodle versions | `4.5` to `5.1` in `version.php` |
| Local compatibility check | Moodle `5.2.1` passes the PHPUnit suite |
| PHP | Moodle-supported PHP for the target Moodle version |
| Maturity | Beta |
| Working branch | `review_johannes` |

Moodle 5.2.1 is already used locally for development and tests, but the official
`supported` metadata is still `[405, 501]`. Raise that only after the final
compatibility review is complete.

## Features

- **Opt-in indexing:** courses are ingested only when released through the
  central pilot-course list, category allow-list, or course custom field.
- **Searchable course selection:** pilot courses and categories use Moodle
  autocomplete controls with search, chips, and multi-select.
- **Course marking lock:** during pilot phases the course custom field can be
  made read-only/inert so only central admin settings decide.
- **Automatic reconciliation:** changes to connection, limits, category, pilot,
  or lock settings queue reconciliation for divergent courses.
- **Indexing status:** the settings page shows when released courses are still
  waiting for indexing and offers a prominent bulk-index action.
- **Automatic ingestion:** Moodle events queue ad-hoc tasks for module create,
  update, delete, and supported subcontent changes.
- **Manual reindex:** admins can index all pending released courses or reindex
  one course by ID.
- **Health checks:** the API client derives and checks a health endpoint for
  LiteRAG-style local routes.
- **Subplugin extractors:** activity-specific extraction lives in
  `ragingestextractor_*` subplugins.
- **Multi-document modules:** folders and similar modules can send multiple
  documents using suffix-based source IDs and prefix deletes.
- **Deterministic source IDs:** IDs include tenant, course ID, module ID, and
  optional document suffix.
- **Tenant from site URL:** tenant identity is derived from `$CFG->wwwroot`.
  There is deliberately no free-text tenant setting.
- **Privacy metadata:** the privacy provider declares the external transfer of
  course/module metadata and extracted content to the RAG service.

## Installation

1. Copy the plugin directory to the Moodle codebase:

   ```text
   local/ragingest
   ```

2. Run Moodle upgrade through Site administration or CLI:

   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```

3. Open the settings page:

   ```text
   /admin/settings.php?section=local_ragingest_settings
   ```

4. Configure the RAG endpoint and API key.

5. Release one or more pilot courses, then run Moodle cron so queued ad-hoc
   tasks can process the indexing work.

## Admin Settings

All settings live on one page:

```text
/admin/settings.php?section=local_ragingest_settings
```

When the eLeDia.ai Tutor shell is present, this page appears in the shared
navigation with the active menu item **eLeDia.ai RagIngest**. The plugin no longer splits
setup across several Moodle admin menus.

### Connection

| Setting | Description | Default |
|---|---|---|
| RAG Endpoint URL | Upsert endpoint or LiteRAG ingest route | `http://rag-service:8001/documents/upsert` |
| API Key | Secret sent as `X-API-Key` | empty |
| Allow private target | Enables Moodle cURL `ignoresecurity` for local/private targets | off |

Supported endpoint shapes include:

- `http://rag-service:8001/documents/upsert`
- `http://localhost:8080/local/literag/ingest.php`
- `http://localhost:8080/local/literag/ingest.php/upsert`
- `http://localhost:8080/local/literag/ingest.php?action=upsert`

For LiteRAG routes, health, upsert, and delete URLs are derived automatically.
For `/documents/upsert`, delete is derived by replacing `/upsert` with
`/delete`.

`allow_private_target` exists because Moodle normally protects cURL calls from
private, loopback, and otherwise blocked targets. Keep it off for public
endpoints and enable it only intentionally for local Docker/service-name
targets.

### Course Selection

| Setting | Description |
|---|---|
| Ingested course categories | Searchable multi-select of categories. Courses in selected categories or subcategories are released. |
| Pilot courses | Searchable multi-select of specific courses. Intended for controlled pilots. |
| Lock course marking | Makes the course custom field inert/read-only so only central settings decide. |

The course custom field **eLeDia.ai RagIngest** is created on install. When course
marking is not locked, it supports:

- `Default`: central pilot/category rules decide.
- `Include`: release this course even when central rules do not.
- `Exclude`: prevent indexing even when central rules would release it.

When marking is locked, existing field values are kept but ignored until the
lock is disabled again.

### Limits

| Setting | Description | Default |
|---|---|---|
| Max document size (MB) | Text/HTML above the limit is truncated; oversized binary files are skipped | `20` |
| Request timeout (seconds) | Timeout per HTTP request attempt | `30` |

## Indexing Status and Reindex

The top of the settings page shows a status panel:

- **Released courses are indexed:** no released courses are waiting for
  indexing.
- **Released courses are waiting for indexing:** one or more released courses
  have not yet been indexed successfully.

When courses are waiting, the primary action **Index released courses now**
queues the pending courses as Moodle ad-hoc tasks. The secondary action opens:

```text
/local/ragingest/reindex.php
```

The reindex page offers:

- bulk queuing for released courses that are not indexed yet
- manual reindex of one course by numeric Moodle course ID

Course state is persisted in `local_ragingest_course`. A course is marked
`ingested = 1` only when a reindex run completes without error results. Skipped
modules do not count as errors, so empty or unsupported courses do not get
queued forever.

## What Gets Indexed

The plugin indexes content from released courses only. It never indexes:

- the site course
- deleted or invisible course modules
- courses without opt-in release
- unsupported module types
- empty activities
- oversized binary files
- personal learner responses in Feedback and similar extractors where those
  responses are intentionally excluded

Supported event triggers include:

- course module created, updated, deleted
- book chapter changes
- glossary entry changes
- lesson page changes
- wiki page changes
- database record changes
- quiz structure changes
- question-bank changes that affect quizzes

## Bundled Extractors

The plugin ships extractors for the common core activity types plus selected
package/interactive modules.

| Subplugin | Activity | Notes |
|---|---|---|
| `assign` | Assignment | Intro, activity instructions, grading criteria; no student submissions |
| `book` | Book | Visible chapters/subchapters in reading order |
| `data` | Database | Field definitions and approved records; user responses are scoped to record content |
| `feedback` | Feedback | Question/item definitions; no submitted responses |
| `folder` | Folder | One document per supported file, preserving PDF MIME type |
| `glossary` | Glossary | Description, approved entries, aliases/synonyms |
| `h5pactivity` | H5P Activity | Labelled educational text from H5P JSON/package content |
| `imscp` | IMS content package | Manifest structure and HTML body content |
| `label` | Text/media area | Intro content |
| `lesson` | Lesson | Page sequence, answers, feedback where relevant |
| `page` | Page | Page content |
| `quiz` | Quiz | Questions, answers, feedback, hints, and overall feedback |
| `resource` | File | Supported text, HTML, and PDF files |
| `scorm` | SCORM | Intro, SCO titles, and local HTML launch pages |
| `videotime` | Video Time | Intro and VTT captions/transcript |
| `wiki` | Wiki | Intro and subwiki pages |
| `workshop` | Workshop | Intro, instructions, conclusion, grading dimensions |

HTML produced by extractors is centrally post-processed for embedded H5P
placeholders where possible.

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

Multi-document modules use suffixes such as:

```text
localhost:course42:cmid99:file1
```

Before a multi-document upsert, the manager clears the previous document set
with a prefix-scoped delete.

Allowed content types:

- `text/plain`
- `text/html`
- `application/pdf`

## Architecture

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

Key classes:

| Class | Responsibility |
|---|---|
| `content_extractor` | Interface for activity extractors |
| `multi_document_extractor` | Optional interface for modules that emit several documents |
| `ingestion_manager` | Discovers extractors, validates/normalizes documents, calls the API |
| `api_client` | HTTP client, endpoint derivation, health/upsert/delete calls |
| `course_gate` | Computes whether a course should be ingested |
| `course_state` | Persists current index state and queues reconciliation |
| `observer` | Converts Moodle events into background tasks |
| `source_id_helper` | Builds deterministic source IDs |
| `tenant` | Derives tenant identity from `$CFG->wwwroot` |
| `h5p_embed_helper` | Resolves embedded H5P placeholders in HTML |
| `h5p_text_extractor` | Extracts labelled text from H5P content JSON/packages |
| `output\shell` | Adapts plugin pages to the eLeDia.ai Tutor shell |

Tasks:

| Task | Purpose |
|---|---|
| `ingest_module_task` | Extract and upsert one course module |
| `delete_module_task` | Delete one course module document set |
| `reconcile_course_task` | Bring one course into the desired indexed/purged state |
| `reconcile_all_task` | Periodic safety net for divergent courses |

## Writing an Extractor

Create a subplugin under:

```text
subplugins/{name}/
├── version.php
├── classes/extractor.php
└── lang/en/ragingestextractor_{name}.php
```

The extractor class is:

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

Guidelines:

- return `null` when there is no useful content
- use only allowed content types
- escape user/editor content before building HTML
- avoid indexing personal submissions unless explicitly intended and reviewed
- use `multi_document_extractor` for files or repeated subdocuments
- let central post-processing handle H5P placeholders in HTML

## Local Development

### Deploy to the local eledia.ai Moodle

The companion Docker setup in the eledia.ai project provides a local Moodle at:

```text
http://localhost:8080
```

Typical flow:

```bash
cd /path/to/eledia.ai
./scripts/local-deploy.sh deploy
```

If the plugin checkout is not baked into that image, copy or sync this plugin to:

```text
/var/www/html/public/local/ragingest
```

and run Moodle upgrade/purge caches.

### Debug Server

A small Python mock server is included for local API testing:

```bash
python3 local/ragingest/debug_server.py
python3 local/ragingest/debug_server.py --port 9000
python3 local/ragingest/debug_server.py --fail
python3 local/ragingest/debug_server.py --delay 5
```

`debug_server.py` is excluded from release archives through `.gitattributes`.

## Testing and Code Style

### PHPUnit

The local Docker setup can initialise Moodle PHPUnit and run this plugin's
testsuite:

```bash
cd /path/to/eledia.ai
./scripts/local-deploy.sh phpunit-init
./scripts/local-deploy.sh phpunit
PHPUNIT_TESTSUITE=local_ragingest_testsuite ./scripts/local-deploy.sh phpunit
```

The current local result is:

```text
Tests: 164
Assertions: 373
Failures: 0
Errors: 0
Skipped: 5
PHPUnit Deprecations: 27
Notices: 1
```

For a direct Moodle checkout with an already initialised PHPUnit environment:

```bash
vendor/bin/phpunit --testsuite local_ragingest_testsuite
```

### PHP Syntax

```bash
find public/local/ragingest -name '*.php' -print0 | xargs -0 -n1 php -l
```

### Moodle Coding Style

Install `moodlehq/moodle-cs` outside the plugin checkout and run PHPCS:

```bash
rm -rf /tmp/local-ragingest-moodle-cs
mkdir -p /tmp/local-ragingest-moodle-cs
cd /tmp/local-ragingest-moodle-cs
composer init --no-interaction --name=local-ragingest/moodle-cs-tools
composer config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer require --dev moodlehq/moodle-cs

cd /path/to/local_ragingest
/tmp/local-ragingest-moodle-cs/vendor/bin/phpcs \
    --standard=moodle \
    --extensions=php \
    '--ignore=public/local/ragingest/tests/fixtures/*' \
    public/local/ragingest
```

Auto-fix style-only issues with:

```bash
/tmp/local-ragingest-moodle-cs/vendor/bin/phpcbf \
    --standard=moodle \
    --extensions=php \
    '--ignore=public/local/ragingest/tests/fixtures/*' \
    public/local/ragingest
```

The current branch passes `phpcs --standard=moodle`.

### Frontend Checks

Moodle's Grunt tooling runs from a Moodle checkout with Node `>=22.11 <23`.
From the plugin directory inside that checkout:

```bash
npx grunt amd --no-color
npx grunt rawcss --no-color
```

`amd` runs `ignorefiles`, `eslint:amd`, and `rollup`; it also regenerates
`amd/build/*.min.js`. `rawcss` runs Stylelint for plain CSS files.

This plugin currently has no Mustache templates and no bundled third-party
libraries, so the Mustache and third-party-library checks are not applicable.
If templates or bundled libraries are added later, include the corresponding
Moodle precheck before submission.

## DevFlow

The working DevFlow lives in:

```text
docs/00-master.md
docs/01-features.md
docs/02-user-doc.md
docs/03-dev-doc.md
docs/04-tasks.md
docs/05-quality.md
```

Start with `docs/00-master.md`, then check open items in `docs/04-tasks.md`.
Update DevFlow when behaviour, user-facing UX, implementation details, or test
evidence changes.

## Privacy

The plugin sends extracted course content and module metadata to an external RAG
service. The privacy provider declares this external location, including:

- site URL
- course ID
- course module ID
- extracted content

The plugin does not maintain per-user content records of its own. Extractors are
responsible for avoiding personal learner submissions unless a future feature
explicitly introduces and documents that behaviour.

## Capabilities

| Capability | Purpose |
|---|---|
| `local/ragingest:reindex` | Allows access to manual reindex operations |

Managers receive this capability by default.

## License

GNU GPL v3 or later.

## Author

Christopher Reimann, eLeDia GmbH.
