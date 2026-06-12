# local_ragingest — RAG Content Ingestion for Moodle

A Moodle local plugin that extracts course content from activity modules and sends it to an external **Retrieval-Augmented Generation (RAG)** ingestion API. Designed for Moodle 5.x (requires Moodle 4.5+).

## Overview

`local_ragingest` bridges Moodle's LMS content with an external vector-database-backed RAG service. When course modules are created, updated, or deleted, the plugin automatically queues ad-hoc tasks that extract content and push it to the configured API endpoint. A manual **Reindex** admin page is also provided for bulk operations.

The plugin uses a **subplugin architecture** (`ragingestextractor`) to support per-activity-type content extraction. Seventeen extractors ship out of the box, covering all major core activity modules; adding new ones requires only three files.

## Features

- **Automatic ingestion** — event observers react to `course_module_created`, `course_module_updated`, and `course_module_deleted` events
- **Bulk reindex** — admin page to re-ingest all modules in a course at once
- **Retry logic** — HTTP client retries failed requests up to 3 times with exponential backoff (1 s, 2 s)
- **Size limit enforcement** — configurable maximum document size; oversized content is skipped
- **Deterministic source IDs** — format `{tenant}:course{id}:cmid{id}` ensures idempotent upserts
- **Multi-tenant support** — tenant ID is included in every payload and source ID
- **Structure-aware H5P extraction** — recognises the common H5P shapes (multiple/single choice, true/false, fill-in-the-blanks, drag text, mark the words, summary, dialog/flash cards, accordion, and the `action`-nested interactive types: Course Presentation, Interactive Video, Branching Scenario, Interactive Book) and emits **labelled** text (`Question:` / `Correct answer:` / `Answer:` / `Cloze:` / `Section:`) so the question↔answer relationship survives into the embeddings; unknown types fall back to a generic content walk
- **Deployment-independent H5P** — content is read straight from the `.h5p` package (`content/content.json`) when the activity has not been deployed/viewed yet, so it is indexable immediately (the deployed record is still used when present)
- **H5P placeholder resolution** — detects H5P embedded in rich-text fields and inlines the extracted, labelled text (each block as its own paragraph)
- **Subplugin extensibility** — add support for any activity module without modifying core plugin code
- **Debug server** — zero-dependency Python mock server for local development and testing

## Requirements

| Requirement | Version |
|---|---|
| Moodle | 4.5, 5.0, or 5.1 |
| PHP | 8.1+ |
| RAG API | Any service implementing the `/documents/upsert` and `/documents/delete` endpoints (see [API Contract](#api-contract)) |

## Installation

1. Copy the `local/ragingest/` directory to your Moodle installation at `{moodleroot}/local/ragingest/`.

2. Visit **Site Administration → Notifications** to trigger the plugin install.

3. Navigate to **Site Administration → Plugins → Local plugins → RAG Content Ingestion** and configure:

| Setting | Description | Default |
|---|---|---|
| **RAG Endpoint URL** | Full URL to the upsert endpoint | `http://rag-service:8001/documents/upsert` |
| **API Key** | Bearer key sent as `X-API-Key` header | *(empty — required)* |
| **Tenant ID** | Identifies the Moodle instance in multi-tenant setups | *(empty → `default`)* |
| **Max Document Size (MB)** | Documents exceeding this size are skipped | `20` |
| **Request Timeout (seconds)** | HTTP timeout per request attempt | `30` |

> **Note:** The delete endpoint URL is derived automatically by replacing `/upsert` with `/delete` in the configured endpoint URL.

## Architecture

```
local/ragingest/
├── classes/
│   ├── content_extractor.php      # Interface for subplugin extractors
│   ├── api_client.php             # HTTP client with retry logic
│   ├── ingestion_manager.php      # Central orchestrator
│   ├── observer.php               # Event observer (queues ad-hoc tasks)
│   ├── source_id_helper.php       # Deterministic source ID builder
│   ├── h5p_embed_helper.php       # Resolves H5P placeholders in HTML
│   ├── h5p_text_extractor.php     # Extracts text from H5P JSON content
│   ├── plugininfo/
│   │   └── ragingestextractor.php # Subplugin type info class
│   └── task/
│       ├── ingest_module_task.php  # Ad-hoc task: extract + upsert
│       └── delete_module_task.php  # Ad-hoc task: delete from RAG
├── db/
│   ├── access.php                 # Capability: local/ragingest:reindex
│   ├── events.php                 # Event observer registrations
│   └── subplugins.json            # Declares ragingestextractor type
├── lang/en/local_ragingest.php    # Language strings
├── subplugins/                    # Extractor subplugins (see below)
│   ├── assign/
│   ├── book/
│   ├── data/
│   ├── feedback/
│   ├── folder/
│   ├── glossary/
│   ├── h5pactivity/
│   ├── imscp/
│   ├── label/
│   ├── lesson/
│   ├── page/
│   ├── quiz/
│   ├── resource/
│   ├── scorm/
│   ├── videotime/
│   ├── wiki/
│   └── workshop/
├── tests/                         # PHPUnit test suite
├── debug_server.py                # Python mock RAG server
├── reindex.php                    # Admin bulk-reindex page
├── settings.php                   # Admin settings page
└── version.php                    # Plugin metadata
```

### Data Flow

```
┌──────────────┐     event      ┌──────────┐    queue     ┌─────────────────┐
│ Moodle Core  │ ──────────────▶│ Observer  │ ──────────▶  │ Ad-hoc Task     │
│ (CRUD on CM) │                └──────────┘              │ (ingest/delete) │
└──────────────┘                                          └────────┬────────┘
                                                                   │
                                                                   ▼
                                                       ┌──────────────────────┐
                                                       │  Ingestion Manager   │
                                                       │  1. Find extractor   │
                                                       │  2. Extract content  │
                                                       │  3. Validate + size  │
                                                       │  4. Build payload    │
                                                       └──────────┬───────────┘
                                                                  │
                                                                  ▼
                                                        ┌─────────────────┐
                                                        │   API Client    │
                                                        │ POST /upsert    │
                                                        │ (retry ×3)      │
                                                        └────────┬────────┘
                                                                 │
                                                                 ▼
                                                       ┌──────────────────┐
                                                       │  External RAG    │
                                                       │  Service         │
                                                       └──────────────────┘
```

### Key Classes

| Class | Responsibility |
|---|---|
| `\local_ragingest\content_extractor` | **Interface** — all subplugin extractors implement `supports(\cm_info)` and `extract(\cm_info)` |
| `\local_ragingest\ingestion_manager` | Discovers extractors via `\core_component`, orchestrates extraction, validates payloads, enforces size limits, calls API client |
| `\local_ragingest\api_client` | Sends HTTP requests with `X-API-Key` auth, retry logic (max 3 attempts, exponential backoff), and `ignoresecurity` flag to bypass Moodle's cURL URL blocker for non-standard ports |
| `\local_ragingest\observer` | Handles `course_module_created/updated/deleted` events by queuing ad-hoc tasks |
| `\local_ragingest\source_id_helper` | Builds deterministic source IDs in the format `{tenant}:course{id}:cmid{id}` |
| `\local_ragingest\task\ingest_module_task` | Ad-hoc task that calls `ingestion_manager::ingest_module()` |
| `\local_ragingest\task\delete_module_task` | Ad-hoc task that calls `ingestion_manager::delete_module()` |
| `\local_ragingest\h5p_embed_helper` | Detects `<div class="h5p-placeholder">` in HTML and replaces them with extracted H5P text, or strips them if unresolvable |
| `\local_ragingest\h5p_text_extractor` | Structure-aware extraction of labelled text from H5P content JSON (questions, correct/incorrect answers, cloze, cards, summaries, accordion, `action`-nested interactive types); resolves the content JSON from the deployed record or directly from the `.h5p` package zip |
| `\local_ragingest\plugininfo\ragingestextractor` | Tells Moodle's plugin manager how to handle the `ragingestextractor` subplugin type |

## Bundled Extractors

### Simple Content Modules

| Subplugin | Activity | Content Type | Extraction Strategy |
|---|---|---|---|
| `ragingestextractor_page` | Page | `text/html` | Returns the page's `content` field |
| `ragingestextractor_label` | Label | `text/html` | Returns the label's `intro` field |
| `ragingestextractor_assign` | Assignment | `text/html` | Extracts intro + activity instructions (no student submissions) |
| `ragingestextractor_workshop` | Workshop | `text/html` | Extracts intro, author/reviewer instructions, and conclusion |

### Structured Content Modules

| Subplugin | Activity | Content Type | Extraction Strategy |
|---|---|---|---|
| `ragingestextractor_book` | Book | `text/html` | Combines all visible chapters with `<h2>` (chapters) and `<h3>` (subchapters) headings |
| `ragingestextractor_glossary` | Glossary | `text/html` | Combines all approved entries into an HTML `<dl>` document |
| `ragingestextractor_lesson` | Lesson | `text/html` | Walks the page linked-list in navigation order; includes answer options and feedback. Structural pages (cluster, end-of-branch) are skipped |
| `ragingestextractor_wiki` | Wiki | `text/html` | Extracts intro + all sub-wiki pages' cached HTML content ordered by title |
| `ragingestextractor_quiz` | Quiz | `text/html` | Resolves quiz slots through the question bank reference chain; extracts question text, answer options, feedback, and overall feedback bands |
| `ragingestextractor_data` | Database | `text/html` | Extracts intro + all approved records' text-type field values (`text`, `textarea`, `url`, `menu`, etc.) with field labels |
| `ragingestextractor_feedback` | Feedback | `text/html` | Extracts intro + question/item definitions with multichoice options parsed from the presentation field. User responses are **never** included |

### File-based Modules

| Subplugin | Activity | Content Type | Extraction Strategy |
|---|---|---|---|
| `ragingestextractor_resource` | File (resource) | auto-detected | Reads the main file from Moodle file storage; only sends `text/plain`, `text/html`, and `application/pdf` |
| `ragingestextractor_folder` | Folder | auto-detected | Extracts intro + all supported files (PDF, text, HTML). Single file preserves native MIME type; multiple files are wrapped in HTML |

### Interactive / Package Modules

| Subplugin | Activity | Content Type | Extraction Strategy |
|---|---|---|---|
| `ragingestextractor_h5pactivity` | H5P Activity | `text/plain` | Labelled educational text from the H5P content (questions, correct/incorrect answers, cloze, cards, summaries, accordion, nested interactive types), prefixed with the activity name. Works without prior deployment by reading the package directly. |
| `ragingestextractor_imscp` | IMS Content Package | `text/html` | Parses the manifest structure for page ordering and extracts `<body>` content from all HTML pages in the deployed package |
| `ragingestextractor_scorm` | SCORM | `text/html` | Extracts intro + SCO titles as table of contents. For locally-stored packages, also reads text from HTML launch pages |
| `ragingestextractor_videotime` | Video Time | `text/plain` | Extracts and concatenates VTT subtitle/caption track text, stripping timestamps and formatting tags |

> **Note:** H5P placeholders embedded in any rich-text field (e.g., a label or page intro) are automatically resolved by the `h5p_embed_helper` during ingestion, regardless of which extractor produced the HTML.

## API Contract

### Upsert

```
POST /documents/upsert
X-API-Key: <configured key>
Content-Type: application/json

{
    "source_id": "my-tenant:course42:cmid99",
    "content": "<base64-encoded content>",
    "content_type": "text/html",
    "qdrant_metadata": {
        "tenant_id": "my-tenant",
        "course_id": 42,
        "cmid": 99,
        "module_url": "https://moodle.example.com/mod/page/view.php?id=99"
    },
    "parser_options": null
}
```

### Delete

```
POST /documents/delete
X-API-Key: <configured key>
Content-Type: application/json

{
    "source_id": "my-tenant:course42:cmid99"
}
```

### Allowed Content Types

- `text/plain`
- `text/html`
- `application/pdf`

The RAG service is expected to handle parsing and chunking based on `content_type`. No `activity_type` field is sent — the service infers document structure from the content itself.

## Writing a New Extractor

To add support for a new activity module (e.g., `mod_wiki`), create a subplugin with three files:

### 1. `subplugins/wiki/version.php`

```php
<?php
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026031100;
$plugin->requires  = 2025100600;
$plugin->component = 'ragingestextractor_wiki';
```

### 2. `subplugins/wiki/lang/en/ragingestextractor_wiki.php`

```php
<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Wiki content extractor';
```

### 3. `subplugins/wiki/classes/extractor.php`

```php
<?php
namespace ragingestextractor_wiki;

use local_ragingest\content_extractor;

class extractor implements content_extractor {

    public function supports(\cm_info $cm): bool {
        return $cm->modname === 'wiki';
    }

    public function extract(\cm_info $cm): ?array {
        global $DB;

        $wiki = $DB->get_record('wiki', ['id' => $cm->instance], '*', MUST_EXIST);

        // Your extraction logic here...
        $content = $this->build_wiki_html($wiki);

        if (empty($content)) {
            return null;
        }

        return [
            'content'      => $content,
            'content_type' => 'text/html',
            'title'        => $wiki->name,
        ];
    }
}
```

The `extract()` method must return an associative array with three keys (`content`, `content_type`, `title`) or `null` if the module has no extractable content. Only content types listed in `ingestion_manager::ALLOWED_CONTENT_TYPES` will be accepted.

## Manual Reindex

Navigate to **Site Administration → Plugins → Local plugins → Reindex course content** and enter a course ID. The page will:

1. Iterate all visible, non-deleted course modules
2. Attempt to extract and upsert each one
3. Display a results table with **success**, **skipped**, and **error** badges per module

This operation runs synchronously (not via the task queue) and requires the `local/ragingest:reindex` capability (granted to managers by default).

## Debug Server

A zero-dependency Python 3 mock server is included for local development:

```bash
# Start with defaults (port 8001, 200 OK responses)
python3 local/ragingest/debug_server.py

# Custom port
python3 local/ragingest/debug_server.py --port 9000

# Simulate server errors (500) to test retry logic
python3 local/ragingest/debug_server.py --fail

# Add response delay to test timeout handling
python3 local/ragingest/debug_server.py --delay 5
```

The server logs every request with colorized output: headers, decoded payload (base64 content preview), timing, and response status. Configure the Moodle plugin to point at `http://localhost:8001/documents/upsert`.

## Testing

### PHPUnit

The plugin includes a comprehensive test suite covering all core classes, H5P helpers, and all extractors.

```bash
# Run all plugin tests
vendor/bin/phpunit --testsuite local_ragingest_testsuite

# Run a specific test class
vendor/bin/phpunit public/local/ragingest/tests/source_id_helper_test.php
vendor/bin/phpunit public/local/ragingest/tests/api_client_test.php
vendor/bin/phpunit public/local/ragingest/tests/ingestion_manager_test.php
vendor/bin/phpunit public/local/ragingest/tests/observer_test.php

# Run H5P helper tests
vendor/bin/phpunit public/local/ragingest/tests/h5p_embed_helper_test.php
vendor/bin/phpunit public/local/ragingest/tests/h5p_text_extractor_test.php

# Run extractor tests (examples)
vendor/bin/phpunit public/local/ragingest/tests/extractor_page_test.php
vendor/bin/phpunit public/local/ragingest/tests/extractor_quiz_test.php
vendor/bin/phpunit public/local/ragingest/tests/extractor_scorm_test.php
```

> **Note:** You must have a valid `phpunit.xml` configuration with a `config.php` for a test database (see Moodle's PHPUnit documentation). The `local_ragingest_testsuite` must be registered in `phpunit.xml.dist` or your local `phpunit.xml`.
> 
> The `extractor_videotime_test` tests are automatically skipped when `mod_videotime` is not installed (the plugin's test generator is required).

### Test Coverage

| Test File | What It Covers |
|---|---|
| `source_id_helper_test` | Deterministic ID format, tenant config fallback to `default`, `build()` from `\cm_info` |
| `api_client_test` | `is_configured()` with all config permutations, mocked upsert/delete responses |
| `ingestion_manager_test` | Unconfigured client error, page ingestion end-to-end, unsupported module skipping, delete success, size limit enforcement |
| `observer_test` | Verifies that create/update/delete events queue the correct ad-hoc task types |
| `h5p_embed_helper_test` | Placeholder detection, resolution via file storage + H5P table, unresolvable/empty/non-H5P URL stripping |
| `h5p_text_extractor_test` | Flat and nested JSON, blocklist filtering, deduplication, HTML stripping, accordion panels, semantic labelling (multichoice/true-false/drag-text/summary/cards), `action`-nested interactive content, the blocks API |
| `extractor_page_test` | `supports()` filtering, content extraction, empty page → null |
| `extractor_label_test` | `supports()` filtering, intro extraction, empty label → null |
| `extractor_resource_test` | File storage integration, MIME type filtering (text/plain, text/html, image/png → null) |
| `extractor_glossary_test` | Multi-entry `<dl>` HTML generation, empty glossary → null, unapproved entries excluded |
| `extractor_book_test` | Chapter/subchapter hierarchy (`<h2>`/`<h3>`), hidden chapters excluded, empty book → null |
| `extractor_h5pactivity_test` | Deployed H5P content extraction, undeployed → null, empty JSON → null |
| `extractor_videotime_test` | VTT parsing (timestamps, formatting tags, voice tags, NOTE/STYLE blocks), multi-track concatenation |
| `extractor_data_test` | Text-field extraction with field labels, unapproved record exclusion, empty database → null |
| `extractor_feedback_test` | Multichoice option parsing, pagebreak/captcha skip, label items, empty feedback → null |
| `extractor_folder_test` | Single file native MIME, multi-file HTML wrapping, unsupported MIME skip, empty folder → null |
| `extractor_imscp_test` | Manifest structure parsing, nested subitems, HTML body extraction, empty package → null |
| `extractor_scorm_test` | SCO titles, HTML launch page extraction, intro-only fallback, local vs external packages |

## Capabilities

| Capability | Type | Context | Default Archetypes |
|---|---|---|---|
| `local/ragingest:reindex` | write | CONTEXT_SYSTEM | manager |

## License

GNU GPL v3 or later — see [COPYING.txt](../../COPYING.txt).

## Author

Christopher Reimann, [eLeDia GmbH](https://www.eledia.de) — `christopher.reimann@eledia.de`
