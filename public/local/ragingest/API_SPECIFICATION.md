# RAG Ingestion API Specification

> **Version:** 1.0  
> **Date:** March 2026  
> **Audience:** RAG service developers  
> **Consumer:** `local_ragingest` Moodle plugin

This document describes the HTTP API that the RAG service **must** implement for the Moodle `local_ragingest` plugin to function. The plugin acts as the sole client and calls exactly two endpoints.

---

## General

| Property | Value |
|---|---|
| Transport | HTTP/HTTPS |
| Content-Type | `application/json` (request and response) |
| Authentication | API key via `X-API-Key` header |
| Base URL (example) | `http://rag-service:8001` |

### Authentication

Every request includes the header:

```
X-API-Key: <shared secret>
```

The service **must** reject requests with a missing or invalid key with `401 Unauthorized` or `403 Forbidden`.

### Retry Behaviour (client-side)

The plugin retries failed requests up to **3 times** with exponential backoff (1 s, 2 s). Retries are triggered on:

- Network errors / timeouts
- `5xx` server errors

The plugin does **not** retry on `4xx` client errors.

### Timeout

The plugin enforces a configurable per-request timeout (default: **30 seconds**, connect timeout: 10 seconds).

---

## Endpoints

### 1. Upsert Document

Inserts a new document or updates an existing one (identified by `source_id`).

```
POST /documents/upsert
```

#### Request Headers

| Header | Value |
|---|---|
| `Content-Type` | `application/json` |
| `X-API-Key` | `<api key>` |

#### Request Body

```json
{
    "source_id": "my-tenant:course42:cmid99",
    "content": "<base64-encoded string>",
    "content_type": "text/html",
    "qdrant_metadata": {
        "tenant_id": "my-tenant",
        "course_id": "42",
        "cmid": "99",
        "module_url": "https://moodle.example.com/mod/page/view.php?id=99"
    },
    "parser_options": null
}
```

#### Field Reference

| Field | Type | Required | Description |
|---|---|---|---|
| `source_id` | string | ✅ | Unique, deterministic document identifier. Format: `{tenant_id}:course{course_id}:cmid{cmid}`. Used for idempotent upserts and deletes. |
| `content` | string | ✅ | The document content, **base64-encoded**. The service must decode before processing. |
| `content_type` | string | ✅ | MIME type of the decoded content. One of the values listed below. |
| `qdrant_metadata` | object | ✅ | Metadata to store alongside the document vectors (see sub-fields). |
| `qdrant_metadata.tenant_id` | string | ✅ | Identifies the Moodle instance in multi-tenant deployments. Defaults to `"default"` if unconfigured. |
| `qdrant_metadata.course_id` | string | ✅ | Moodle course ID (sent as string). |
| `qdrant_metadata.cmid` | string | ✅ | Moodle course module ID (sent as string). |
| `qdrant_metadata.module_url` | string | ✅ | Direct URL to the activity in Moodle (for citation/linking in RAG responses). |
| `parser_options` | null | ✅ | Always sent as `null`. Reserved for future use. |

#### Allowed `content_type` Values

The plugin only sends one of these three MIME types:

| MIME Type | Description |
|---|---|
| `text/plain` | Plain text files |
| `text/html` | HTML content (pages, labels, glossaries, books) |
| `application/pdf` | PDF files uploaded as resources |

The service must be able to parse, chunk, and embed all three.

#### `source_id` Format

```
{tenant_id}:course{course_id}:cmid{cmid}
```

| Component | Example | Description |
|---|---|---|
| `tenant_id` | `uni-heidelberg` | Alphanumeric + hyphens/underscores. Falls back to `default`. |
| `course_id` | `42` | Integer, Moodle course ID |
| `cmid` | `99` | Integer, Moodle course module ID |

Full example: `uni-heidelberg:course42:cmid99`

#### Expected Response

**Success:**

```
HTTP/1.1 200 OK
Content-Type: application/json

{ "status": "ok" }
```

Any `2xx` status code is treated as success. The response body is not parsed by the plugin.

**Error:**

| Status | Meaning | Plugin Behaviour |
|---|---|---|
| `400` | Bad request (malformed payload) | Logged, **no retry** |
| `401` / `403` | Authentication failure | Logged, **no retry** |
| `404` | Endpoint not found | Logged, **no retry** |
| `413` | Payload too large | Logged, **no retry** |
| `500` | Internal server error | **Retried** up to 3× |
| `502` / `503` / `504` | Gateway / availability errors | **Retried** up to 3× |

---

### 2. Delete Document

Removes a previously upserted document and its associated vectors from the index.

```
POST /documents/delete
```

> **URL derivation:** The plugin derives this URL by replacing `/upsert` with `/delete` in the configured endpoint. If the upsert URL is `http://rag-service:8001/documents/upsert`, deletes go to `http://rag-service:8001/documents/delete`.

#### Request Headers

| Header | Value |
|---|---|
| `Content-Type` | `application/json` |
| `X-API-Key` | `<api key>` |

#### Request Body

```json
{
    "source_id": "my-tenant:course42:cmid99"
}
```

#### Field Reference

| Field | Type | Required | Description |
|---|---|---|---|
| `source_id` | string | ✅ | The same `source_id` that was used during upsert. The service must delete all vectors/chunks associated with this ID. |

#### Expected Response

**Success:**

```
HTTP/1.1 200 OK
Content-Type: application/json

{ "status": "ok" }
```

Any `2xx` status code is treated as success.

**Document not found:** The service should return `200 OK` (idempotent delete). If it returns `404`, the plugin treats it as a client error and logs a failure without retrying.

**Error handling:** Same retry behaviour as the upsert endpoint (retry on `5xx`, no retry on `4xx`).

---

## Data Flow Summary

```
┌─────────────────────────────────────────────────────────────────────┐
│                          MOODLE                                     │
│                                                                     │
│  Teacher creates/edits/deletes an activity                         │
│       │                                                             │
│       ▼                                                             │
│  Event observer queues an ad-hoc task                              │
│       │                                                             │
│       ▼                                                             │
│  Cron executes the task                                            │
│       │                                                             │
│       ▼                                                             │
│  Extractor pulls content from the activity                         │
│       │                                                             │
│       ▼                                                             │
│  Content is base64-encoded, payload is assembled                   │
│       │                                                             │
│       ▼                                                             │
│  POST /documents/upsert   ─── or ───   POST /documents/delete     │
│       │                                          │                  │
└───────┼──────────────────────────────────────────┼──────────────────┘
        │                                          │
        ▼                                          ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       RAG SERVICE                                   │
│                                                                     │
│  1. Authenticate via X-API-Key                                     │
│  2. Decode base64 content                                          │
│  3. Parse based on content_type (text, HTML, PDF)                  │
│  4. Chunk the document                                             │
│  5. Generate embeddings                                            │
│  6. Store vectors in Qdrant with qdrant_metadata                   │
│     ─── or ───                                                     │
│  6. Delete all vectors matching source_id                          │
│  7. Return 200 OK                                                  │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Health Check (Optional)

The plugin does not call a health-check endpoint, but for operational purposes it is recommended that the service expose:

```
GET /health
```

Response:

```json
{
    "status": "ok"
}
```

---

## Example cURL Commands

### Upsert

```bash
curl -X POST http://localhost:8001/documents/upsert \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{
    "source_id": "default:course2:cmid15",
    "content": "PGgxPkhlbGxvIFdvcmxkPC9oMT4=",
    "content_type": "text/html",
    "qdrant_metadata": {
      "tenant_id": "default",
      "course_id": "2",
      "cmid": "15",
      "module_url": "https://moodle.example.com/mod/page/view.php?id=15"
    },
    "parser_options": null
  }'
```

### Delete

```bash
curl -X POST http://localhost:8001/documents/delete \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{
    "source_id": "default:course2:cmid15"
  }'
```

---

## Notes for Implementers

1. **Idempotency** — Upsert must be idempotent. Repeated calls with the same `source_id` should replace (not duplicate) the document. Delete of a non-existent `source_id` should return `200`.

2. **Content decoding** — The `content` field is always base64-encoded. Decode it before parsing.

3. **No `activity_type` field** — The plugin does not send the Moodle activity type (page, book, glossary, etc.). The service should infer document structure from `content_type` alone.

4. **Metadata filtering** — The `qdrant_metadata` fields (`tenant_id`, `course_id`, `cmid`) should be indexed to support filtered vector search (e.g., "search only within course 42").

5. **Multi-tenant isolation** — Use `tenant_id` to ensure queries from one Moodle instance cannot retrieve documents from another.

6. **Large documents** — The plugin enforces a configurable size limit (default 20 MB) before sending. The service may impose its own limits and respond with `413`.
