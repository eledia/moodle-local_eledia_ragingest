<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_ragingest;

/**
 * Ingestion manager — orchestrates content extraction and RAG API submission.
 *
 * Discovers subplugin extractors, validates documents, enforces size and
 * MIME-type limits, builds payloads, and delegates HTTP calls to the API client.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ingestion_manager {
    /** @var string[] Allowed MIME types for ingestion. */
    private const ALLOWED_CONTENT_TYPES = [
        'text/plain',
        'text/html',
        'application/pdf',
    ];

    /** @var api_client The HTTP client instance. */
    private api_client $client;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->client = new api_client();
    }

    /**
     * Reindex all supported modules in a course.
     *
     * Iterates through every course module, finds a matching extractor,
     * extracts content, and sends it to the RAG API. Errors are logged
     * but never stop the loop.
     *
     * @param int $courseid The course ID to reindex.
     * @return array List of result arrays, one per module attempted.
     */
    public function reindex_course(int $courseid): array {
        $results = [];

        if (!$this->client->is_configured()) {
            $results[] = [
                'cmid' => 0,
                'module_name' => '-',
                'success' => false,
                'status' => 'error',
                'message' => get_string('apinotconfigured', 'local_ragingest'),
            ];
            return $results;
        }

        $modinfo = get_fast_modinfo($courseid);

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || $cm->deletioninprogress) {
                continue;
            }

            $result = $this->ingest_module_from_cm($cm);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Ingest a single course module by course ID and cmid.
     *
     * Used by ad-hoc tasks triggered from event observers.
     *
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID.
     * @return array Result array with keys 'cmid', 'module_name', 'success', 'status', 'message'.
     */
    public function ingest_module(int $courseid, int $cmid): array {
        if (!$this->client->is_configured()) {
            return [
                'cmid' => $cmid,
                'module_name' => '',
                'success' => false,
                'status' => 'error',
                'message' => get_string('apinotconfigured', 'local_ragingest'),
            ];
        }

        try {
            $modinfo = get_fast_modinfo($courseid);
            $cm = $modinfo->get_cm($cmid);
        } catch (\Exception $e) {
            return [
                'cmid' => $cmid,
                'module_name' => '',
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        return $this->ingest_module_from_cm($cm);
    }

    /**
     * Delete a module's document from the RAG index.
     *
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID.
     * @return array Result array.
     */
    public function delete_module(int $courseid, int $cmid): array {
        if (!$this->client->is_configured()) {
            return [
                'cmid' => $cmid,
                'success' => false,
                'status' => 'error',
                'message' => get_string('apinotconfigured', 'local_ragingest'),
            ];
        }

        $sourceid = source_id_helper::build_from_ids($courseid, $cmid);

        // Prefix scope removes the module-level document AND any sub-documents
        // (e.g. per-file Folder documents) in one call.
        $apiresult = $this->client->delete($sourceid, 'prefix');

        if ($apiresult['success']) {
            mtrace(get_string('deletionsuccess', 'local_ragingest', $sourceid));
            return [
                'cmid' => $cmid,
                'success' => true,
                'status' => 'success',
                'message' => get_string('deletionsuccess', 'local_ragingest', $sourceid),
            ];
        }

        $errorinfo = (object) ['source_id' => $sourceid, 'http_code' => $apiresult['http_code']];
        mtrace(get_string('deletionfailed', 'local_ragingest', $errorinfo));
        return [
            'cmid' => $cmid,
            'success' => false,
            'status' => 'error',
            'message' => get_string('deletionfailed', 'local_ragingest', $errorinfo),
        ];
    }

    /**
     * Remove every document of a course from the RAG index.
     *
     * Used when a course is un-marked for ingestion. Each module is removed
     * with a prefix-scoped delete, so module-level and per-file sub-documents
     * are all cleared. Idempotent: deleting absent documents is a no-op.
     *
     * @param int $courseid The course id.
     * @return array<int, array> Per-module result rows.
     */
    public function purge_course(int $courseid): array {
        if (!$this->client->is_configured()) {
            return [[
                'cmid' => 0,
                'success' => false,
                'status' => 'error',
                'message' => get_string('apinotconfigured', 'local_ragingest'),
            ]];
        }

        try {
            $modinfo = get_fast_modinfo($courseid);
        } catch (\Exception $e) {
            return [[
                'cmid' => 0,
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ]];
        }

        $results = [];
        foreach ($modinfo->get_cms() as $cm) {
            $sourceid = source_id_helper::build($cm);
            $apiresult = $this->client->delete($sourceid, 'prefix');
            $results[] = [
                'cmid' => $cm->id,
                'success' => (bool) $apiresult['success'],
                'status' => $apiresult['success'] ? 'success' : 'error',
                'message' => $sourceid,
            ];
            if ($apiresult['success']) {
                mtrace(get_string('deletionsuccess', 'local_ragingest', $sourceid));
            }
        }
        return $results;
    }

    /**
     * Ingest content from a cm_info object.
     *
     * @param \cm_info $cm The course module.
     * @return array Result array.
     */
    private function ingest_module_from_cm(\cm_info $cm): array {
        $modulename = $cm->get_formatted_name();

        // Opt-in gate: only ingest content from courses marked for ingestion.
        if (!course_gate::should_ingest((int) $cm->course)) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => get_string('coursenotmarked', 'local_ragingest'),
            ];
        }

        // Find a matching extractor.
        $extractor = $this->find_extractor($cm);
        if ($extractor === null) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => get_string('noextractor', 'local_ragingest'),
            ];
        }

        // Multi-document extractors (e.g. a Folder with several files) emit one
        // document per sub-item; everything else maps to a single document.
        if ($extractor instanceof multi_document_extractor) {
            return $this->ingest_multi($cm, $extractor, $modulename);
        }

        // Extract content (single document).
        try {
            $document = $extractor->extract($cm);
        } catch (\Exception $e) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        if ($document === null || empty($document['content'])) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => get_string('nocontent', 'local_ragingest'),
            ];
        }

        return $this->process_document($cm, $document, source_id_helper::build($cm), $modulename);
    }

    /**
     * Ingest a module that produces several documents (one per sub-item).
     *
     * The module's previous document set is cleared with a prefix-scoped delete
     * before the current set is sent, so added/removed/renamed sub-items never
     * leave orphaned vectors in the index.
     *
     * @param \cm_info $cm The course module.
     * @param multi_document_extractor $extractor The extractor.
     * @param string $modulename The formatted module name.
     * @return array Aggregated result array.
     */
    private function ingest_multi(\cm_info $cm, multi_document_extractor $extractor, string $modulename): array {
        try {
            $documents = $extractor->extract_documents($cm);
        } catch (\Exception $e) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        $documents = array_values(array_filter(
            $documents,
            static fn($d) => !empty($d['content']) && !empty($d['suffix'])
        ));

        if (empty($documents)) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => get_string('nocontent', 'local_ragingest'),
            ];
        }

        // Clear the previous document set for this module first.
        $this->client->delete(source_id_helper::build($cm), 'prefix');

        $sent = 0;
        $failed = 0;
        foreach ($documents as $doc) {
            $sourceid = source_id_helper::build_sub($cm, (string) $doc['suffix']);
            $res = $this->process_document($cm, $doc, $sourceid, $modulename);
            if (!empty($res['success'])) {
                $sent++;
            } else if (($res['status'] ?? '') === 'error') {
                $failed++;
            }
        }

        if ($sent === 0 && $failed === 0) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => get_string('nocontent', 'local_ragingest'),
            ];
        }

        $ok = $failed === 0 && $sent > 0;
        return [
            'cmid' => $cm->id,
            'module_name' => $modulename,
            'success' => $ok,
            'status' => $ok ? 'success' : 'error',
            'message' => get_string(
                'ingestionmultisummary',
                'local_ragingest',
                (object) ['sent' => $sent, 'failed' => $failed]
            ),
        ];
    }

    /**
     * Prepare and send one document (single- or sub-document).
     *
     * Resolves embedded H5P, validates the content type, prepends the activity
     * heading, enforces the size limit (truncating text, skipping binary), and
     * upserts under the given source id.
     *
     * @param \cm_info $cm The course module.
     * @param array $document The document data ('content', 'content_type', 'title').
     * @param string $sourceid The source id to upsert under.
     * @param string $modulename The formatted module name (heading).
     * @return array Per-document result array.
     */
    private function process_document(\cm_info $cm, array $document, string $sourceid, string $modulename): array {
        // Resolve embedded H5P placeholders in HTML so the RAG service receives
        // the actual H5P text instead of bare placeholder divs.
        if ($document['content_type'] === 'text/html') {
            $document['content'] = h5p_embed_helper::resolve_h5p_placeholders(
                $document['content'],
                (int) $cm->course
            );
        }

        // Validate content type.
        if (!in_array($document['content_type'], self::ALLOWED_CONTENT_TYPES, true)) {
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => false,
                'status' => 'skipped',
                'message' => get_string('unsupportedcontenttype', 'local_ragingest', $document['content_type']),
            ];
        }

        // Prepend the activity name as a heading so every chunk the RAG service
        // derives is attributable to its activity. No-op for binary content or
        // when the extractor already supplied its own leading heading/title.
        $document['content'] = document::with_heading(
            $document['content'],
            $document['content_type'],
            $modulename,
        );

        // Enforce the size limit. Oversized TEXT content is truncated and still
        // sent (partial indexing beats none); oversized binary content (e.g.
        // PDF) cannot be safely truncated and is skipped.
        $maxsizemb = (int) (get_config('local_ragingest', 'max_document_size_mb') ?: 20);
        $maxsizebytes = $maxsizemb * 1024 * 1024;

        if (strlen($document['content']) > $maxsizebytes) {
            [$document['content'], $wastruncated] = document::truncate(
                $document['content'],
                $document['content_type'],
                $maxsizebytes
            );

            if (!$wastruncated) {
                $sizemb = round(strlen($document['content']) / (1024 * 1024), 2);
                $sizeinfo = (object) ['size' => $sizemb, 'max' => $maxsizemb];
                return [
                    'cmid' => $cm->id,
                    'module_name' => $modulename,
                    'success' => false,
                    'status' => 'skipped',
                    'message' => get_string('documentsizeexceeded', 'local_ragingest', $sizeinfo),
                ];
            }

            mtrace(get_string(
                'contenttruncatedlog',
                'local_ragingest',
                (object) ['cmid' => $cm->id, 'max' => $maxsizemb]
            ));
        }

        $sizebytes = strlen($document['content']);

        $payload = $this->build_payload($cm, $document, $sourceid);
        $apiresult = $this->client->upsert($payload);

        $loginfo = (object) [
            'source_id' => $sourceid,
            'content_type' => $document['content_type'],
            'size' => round($sizebytes / 1024, 1) . ' KB',
            'http_code' => $apiresult['http_code'],
        ];

        if ($apiresult['success']) {
            mtrace(get_string('ingestionsuccess', 'local_ragingest', $loginfo));
            return [
                'cmid' => $cm->id,
                'module_name' => $modulename,
                'success' => true,
                'status' => 'success',
                'message' => get_string('ingestionsuccess', 'local_ragingest', $loginfo),
            ];
        }

        mtrace(get_string('ingestionfailed', 'local_ragingest', $loginfo));
        return [
            'cmid' => $cm->id,
            'module_name' => $modulename,
            'success' => false,
            'status' => 'error',
            'message' => get_string('ingestionfailed', 'local_ragingest', $loginfo),
        ];
    }

    /**
     * Discover and return the first extractor subplugin that supports the given module.
     *
     * @param \cm_info $cm The course module.
     * @return content_extractor|null The matching extractor, or null if none found.
     */
    private function find_extractor(\cm_info $cm): ?content_extractor {
        $plugins = \core_component::get_plugin_list('ragingestextractor');

        foreach ($plugins as $name => $dir) {
            $classname = "\\ragingestextractor_{$name}\\extractor";
            if (!class_exists($classname)) {
                continue;
            }

            $extractor = new $classname();
            if (!($extractor instanceof content_extractor)) {
                debugging("Extractor {$classname} does not implement content_extractor interface.", DEBUG_DEVELOPER);
                continue;
            }

            if ($extractor->supports($cm)) {
                return $extractor;
            }
        }

        return null;
    }

    /**
     * Build the ingestion API payload for a document.
     *
     * @param \cm_info $cm The course module.
     * @param array $document The extracted document data.
     * @param string $sourceid The deterministic source ID.
     * @return array The payload array ready for JSON encoding.
     */
    private function build_payload(\cm_info $cm, array $document, string $sourceid): array {
        global $CFG;
        $moduleurl = new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);

        return [
            'source_id' => $sourceid,
            'content' => base64_encode($document['content']),
            'content_type' => $document['content_type'],
            'qdrant_metadata' => [
                // Derived from wwwroot — the same canonical identity the RAG
                // server resolves at query time from the verified site.url.
                'tenant_id' => tenant::id(),
                'site_url' => (string) $CFG->wwwroot,
                'course_id' => (string) $cm->course,
                'cmid' => (string) $cm->id,
                'module_url' => $moduleurl->out(false),
            ],
            'parser_options' => null,
        ];
    }
}
