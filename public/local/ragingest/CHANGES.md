# Changelog

## 0.12.0 - 2026-06-27

- Added the eLeDia.ai Tutor shell integration for RAG-Ingest settings, reindex,
  and DevFlow pages.
- Reworked the settings page into one consolidated RAG-Ingest page.
- Added searchable multi-select controls for ingested course categories and
  pilot courses.
- Added indexing status for released courses and a bulk action to queue pending
  released courses.
- Queue reconciliation automatically when RAG-Ingest settings change.
- Added Privacy API metadata for the external RAG service transfer.
- Added explicit opt-in handling for private/local RAG targets.
- Improved reindex UX and course-state handling so failed reindex runs remain
  visible.
- Added and updated DevFlow documentation.
- Updated README documentation and added a German `README.de.md`.
- Cleaned PHP coding style with `moodlehq/moodle-cs`.
