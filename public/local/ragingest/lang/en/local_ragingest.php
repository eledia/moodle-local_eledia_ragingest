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

/**
 * Language strings for the eLeDia.ai RagIngest plugin.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'eLeDia.ai RagIngest';

// Settings.
$string['rag_endpoint_url'] = 'eLeDia.ai RagIngest Endpoint URL';
$string['rag_endpoint_url_desc'] = 'The URL of the eLeDia.ai RagIngest API endpoint (e.g. http://rag-service:8001/documents/upsert).';
$string['rag_api_key'] = 'API Key';
$string['rag_api_key_desc'] = 'The API key for authenticating with eLeDia.ai RagIngest. Sent as X-API-Key header.';
$string['allow_private_target'] = 'Allow private eLeDia.ai RagIngest target';
$string['allow_private_target_desc'] = 'Allow the configured eLeDia.ai RagIngest endpoint to use private hosts, internal service names or non-standard ports. Enable only when the service runs inside a trusted internal network, such as Docker or Kubernetes.';
$string['max_document_size_mb'] = 'Max Document Size (MB)';
$string['max_document_size_mb_desc'] = 'Maximum allowed document size in megabytes. Documents exceeding this limit will be skipped.';
$string['request_timeout_seconds'] = 'Request Timeout (seconds)';
$string['request_timeout_seconds_desc'] = 'HTTP request timeout in seconds for RAG API calls.';
$string['head_connection'] = 'Connection';
$string['head_connection_desc'] = 'Endpoint and authentication for eLeDia.ai RagIngest.';
$string['head_courses'] = 'Course selection';
$string['head_courses_desc'] = 'Opt-in rules that decide which courses may be sent to the RAG service.';
$string['head_limits'] = 'Limits';
$string['head_limits_desc'] = 'Payload size and request runtime limits for ingestion tasks.';
$string['settings_hub_desc'] = 'Choose one eLeDia.ai RagIngest settings area.';
$string['settings_section_connection_desc'] = 'eLeDia.ai RagIngest endpoint URL and API key.';
$string['settings_section_courses_desc'] = 'Pilot courses, category allow-list and test-phase lock.';
$string['settings_section_limits_desc'] = 'Document size and request timeout.';
$string['shell_tagline'] = 'eLeDia.ai RagIngest';
$string['shell_subtitle'] = 'Course content ingestion for external retrieval-augmented generation services.';
$string['shell_help_label'] = 'Help for eLeDia.ai RagIngest';
$string['nav_label'] = 'eLeDia.ai RagIngest sections';
$string['nav_settings'] = 'Settings';
$string['nav_reindex'] = 'Reindex';
$string['nav_docs'] = 'DevFlow';
$string['devflowdocs'] = 'DevFlow documentation';
$string['doc_master'] = 'Overview';
$string['doc_features'] = 'Features';
$string['doc_user'] = 'User documentation';
$string['doc_developer'] = 'Developer documentation';
$string['doc_tasks'] = 'Tasks';
$string['doc_quality'] = 'Quality';

// Reindex page.
$string['reindex'] = 'Reindex Course Content';
$string['reindexcourse'] = 'Reindex Course';
$string['reindex_btn'] = 'Reindex Course Content';
$string['selectcourse'] = 'Select a course to reindex';
$string['reindexintro'] = 'Queue released courses for indexing or reindex one course manually by Moodle course ID.';
$string['manualreindex'] = 'Manual course reindex';
$string['manualreindex_desc'] = 'Use this for a targeted reindex of one course. Released courses can be queued together above.';
$string['courseid'] = 'Course ID';
$string['courseid_help'] = 'Enter the numeric ID of the course to reindex.';
$string['reindexresults'] = 'Reindex Results';
$string['reindexsuccess'] = 'Successfully ingested {$a} document(s).';
$string['reindexcomplete'] = 'Course reindex complete.';
$string['ingesting'] = 'Ingesting content for course: {$a}';
$string['unknownmodule'] = 'Module (cmid {$a})';
$string['pendingindexingtitle'] = 'Released courses waiting for indexing';
$string['pendingindexingcount'] = '{$a} course(s) are released for eLeDia.ai RagIngest but not indexed yet.';
$string['indexreleasedcourses'] = 'Index released courses now';
$string['pendingindexingqueued'] = 'Queued {$a} released course(s) for indexing.';
$string['indexingreadytitle'] = 'Released courses are indexed';
$string['indexingreadybody'] = 'There are currently no released courses waiting for indexing.';
$string['openreindex'] = 'Open reindex';

// Results table.
$string['modulename'] = 'Module';
$string['status'] = 'Status';
$string['details'] = 'Details';
$string['statussuccess'] = 'Success';
$string['statusskipped'] = 'Skipped';
$string['statuserror'] = 'Error';

// Messages.
$string['noextractor'] = 'No extractor available for this module type.';
$string['nocontent'] = 'No content to ingest.';
$string['documentsizeexceeded'] = 'Document size ({$a->size} MB) exceeds maximum ({$a->max} MB).';
$string['unsupportedcontenttype'] = 'Unsupported content type: {$a}';
$string['apiclienterror'] = 'RAG API error: {$a}';
$string['apinotconfigured'] = 'eLeDia.ai RagIngest is not configured. Please set the endpoint URL and API key.';
$string['invalidcourseid'] = 'Invalid course ID.';
$string['coursenotfound'] = 'Course not found.';
$string['deletemodule'] = 'Deleting module from RAG index: cmid {$a}';
$string['ingestionsuccess'] = 'Ingested: source_id={$a->source_id}, type={$a->content_type}, size={$a->size}';
$string['ingestionfailed'] = 'Ingestion failed: source_id={$a->source_id}, HTTP {$a->http_code}';
$string['ingestionmultisummary'] = 'Ingested {$a->sent} document(s), {$a->failed} failed';
$string['deletionsuccess'] = 'Deleted from index: source_id={$a}';
$string['deletionfailed'] = 'Deletion failed: source_id={$a->source_id}, HTTP {$a->http_code}';
$string['taskingestion'] = 'eLeDia.ai RagIngest content indexing';
$string['taskdeletion'] = 'eLeDia.ai RagIngest content deletion';

// Course marking (opt-in ingestion).
$string['enabledcategories'] = 'Ingested course categories';
$string['enabledcategories_desc'] = 'Only courses in the selected categories (or their subcategories) are sent to the RAG service. Ingestion is opt-in: with nothing selected, no course is ingested unless individually marked "Include" via the course\'s "eLeDia.ai RagIngest" setting.';
$string['searchcategories'] = 'Search categories';
$string['cfcategory'] = 'AI tutor';
$string['cffieldname'] = 'eLeDia.ai RagIngest';
$string['cffielddesc'] = 'Whether this course\'s content is sent to the AI tutor\'s knowledge base. "Default" follows the site\'s category settings; "Include" always sends; "Exclude" never sends.';
$string['coursenotmarked'] = 'Course is not marked for ingestion.';
$string['task_reconcile_all'] = 'Reconcile eLeDia.ai RagIngest course marking';
$string['pilotcourses'] = 'Pilot courses';
$string['pilotcourses_desc'] = 'Specific courses to ingest regardless of the category allow-list. Use the search field to select one or more courses for a controlled test/pilot phase.';
$string['searchcourses'] = 'Search courses';
$string['lockcoursemarking'] = 'Lock course marking (test phase)';
$string['lockcoursemarking_desc'] = 'When enabled, the per-course "eLeDia.ai RagIngest" setting has no effect at all — only the pilot-course list and the category allow-list decide what is ingested, and the course field is locked against teacher editing (visible read-only). Use this during a test phase so the set of ingested courses is controlled exclusively in this admin page. Existing per-course values are kept and become effective again when the lock is disabled.';

// Extractor content labels.
$string['alsoknownas'] = 'Also known as:';
$string['questionhint'] = 'Hint:';
$string['gradingcriteria'] = 'Grading criteria';
$string['contenttruncated'] = '[content truncated to fit the size limit]';
$string['contenttruncatedlog'] = 'Content truncated to the {$a->max} MB limit before sending: cmid {$a->cmid}';

// Capabilities.
$string['ragingest:reindex'] = 'Reindex course content for eLeDia.ai RagIngest';

// Privacy.
$string['privacy:metadata'] = 'The eLeDia.ai RagIngest plugin does not store user-scoped personal data in Moodle.';
$string['privacy:metadata:rag_service'] = 'Course content and module metadata are sent to the configured eLeDia.ai RagIngest service.';
$string['privacy:metadata:rag_service:site_url'] = 'The Moodle site URL is sent so the RAG service can verify the tenant.';
$string['privacy:metadata:rag_service:course_id'] = 'The Moodle course ID is sent to associate content with its course.';
$string['privacy:metadata:rag_service:cmid'] = 'The Moodle course module ID is sent to identify the activity.';
$string['privacy:metadata:rag_service:module_url'] = 'The Moodle module URL is sent for later citations and source links.';
$string['privacy:metadata:rag_service:content'] = 'Extracted course activity content is sent for parsing, chunking and indexing.';

// Subplugin types.
$string['subplugintype_ragingestextractor'] = 'Content extractor';
$string['subplugintype_ragingestextractor_plural'] = 'Content extractors';
