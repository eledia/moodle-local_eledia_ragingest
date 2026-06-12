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
 * Language strings for the RAG ingestion plugin.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'RAG Content Ingestion';

// Settings.
$string['rag_endpoint_url'] = 'RAG Endpoint URL';
$string['rag_endpoint_url_desc'] = 'The URL of the RAG ingestion API endpoint (e.g. http://rag-service:8001/documents/upsert).';
$string['rag_api_key'] = 'API Key';
$string['rag_api_key_desc'] = 'The API key for authenticating with the RAG ingestion service. Sent as X-API-Key header.';
$string['tenant_id'] = 'Tenant ID';
$string['tenant_id_desc'] = 'The tenant identifier used in source IDs and metadata for multi-tenant RAG service.';
$string['max_document_size_mb'] = 'Max Document Size (MB)';
$string['max_document_size_mb_desc'] = 'Maximum allowed document size in megabytes. Documents exceeding this limit will be skipped.';
$string['request_timeout_seconds'] = 'Request Timeout (seconds)';
$string['request_timeout_seconds_desc'] = 'HTTP request timeout in seconds for RAG API calls.';

// Reindex page.
$string['reindex'] = 'Reindex Course Content';
$string['reindexcourse'] = 'Reindex Course';
$string['reindex_btn'] = 'Reindex Course Content';
$string['selectcourse'] = 'Select a course to reindex';
$string['courseid'] = 'Course ID';
$string['courseid_help'] = 'Enter the numeric ID of the course to reindex.';
$string['reindexresults'] = 'Reindex Results';
$string['reindexsuccess'] = 'Successfully ingested {$a} document(s).';
$string['reindexcomplete'] = 'Course reindex complete.';
$string['ingesting'] = 'Ingesting content for course: {$a}';

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
$string['apinotconfigured'] = 'RAG ingestion is not configured. Please set the endpoint URL and API key.';
$string['invalidcourseid'] = 'Invalid course ID.';
$string['coursenotfound'] = 'Course not found.';
$string['deletemodule'] = 'Deleting module from RAG index: cmid {$a}';
$string['ingestionsuccess'] = 'Ingested: source_id={$a->source_id}, type={$a->content_type}, size={$a->size}';
$string['ingestionfailed'] = 'Ingestion failed: source_id={$a->source_id}, HTTP {$a->http_code}';
$string['ingestionmultisummary'] = 'Ingested {$a->sent} document(s), {$a->failed} failed';
$string['deletionsuccess'] = 'Deleted from index: source_id={$a}';
$string['deletionfailed'] = 'Deletion failed: source_id={$a->source_id}, HTTP {$a->http_code}';
$string['taskingestion'] = 'RAG content ingestion';
$string['taskdeletion'] = 'RAG content deletion';

// Extractor content labels.
$string['alsoknownas'] = 'Also known as:';
$string['questionhint'] = 'Hint:';
$string['gradingcriteria'] = 'Grading criteria';
$string['contenttruncated'] = '[content truncated to fit the size limit]';
$string['contenttruncatedlog'] = 'Content truncated to the {$a->max} MB limit before sending: cmid {$a->cmid}';

// Capabilities.
$string['ragingest:reindex'] = 'Reindex course content for RAG ingestion';

// Privacy.
$string['privacy:metadata'] = 'The RAG Content Ingestion plugin does not store any personal data.';

// Subplugin types.
$string['subplugintype_ragingestextractor'] = 'Content extractor';
$string['subplugintype_ragingestextractor_plural'] = 'Content extractors';
