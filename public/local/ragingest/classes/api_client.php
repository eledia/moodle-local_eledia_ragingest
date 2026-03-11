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
 * HTTP client for communicating with the RAG ingestion API.
 *
 * Handles upsert and delete requests with retry logic and error handling.
 * The API key is only used server-side and never exposed to clients.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_client {

    /** @var int Maximum number of retry attempts for failed requests. */
    private const MAX_RETRIES = 3;

    /** @var string The RAG API endpoint URL. */
    private string $endpoint;

    /** @var string The API key for authentication. */
    private string $apikey;

    /** @var int Request timeout in seconds. */
    private int $timeout;

    /**
     * Constructor. Reads configuration from plugin settings.
     */
    public function __construct() {
        $this->endpoint = get_config('local_ragingest', 'rag_endpoint_url') ?: '';
        $this->apikey = get_config('local_ragingest', 'rag_api_key') ?: '';
        $this->timeout = (int) (get_config('local_ragingest', 'request_timeout_seconds') ?: 30);
    }

    /**
     * Check whether the API client is properly configured.
     *
     * @return bool True if endpoint and API key are set.
     */
    public function is_configured(): bool {
        return !empty($this->endpoint) && !empty($this->apikey);
    }

    /**
     * Send an upsert request to the RAG API.
     *
     * @param array $payload The document payload.
     * @return array Result with keys 'success', 'http_code', 'response', 'error'.
     */
    public function upsert(array $payload): array {
        return $this->send_request($this->endpoint, $payload);
    }

    /**
     * Send a delete request to the RAG API.
     *
     * Derives the delete URL from the configured upsert endpoint
     * by replacing /upsert with /delete.
     *
     * @param string $sourceid The source_id of the document to delete.
     * @return array Result with keys 'success', 'http_code', 'response', 'error'.
     */
    public function delete(string $sourceid): array {
        $deleteurl = $this->get_delete_url();
        return $this->send_request($deleteurl, ['source_id' => $sourceid]);
    }

    /**
     * Derive the delete endpoint URL from the upsert endpoint.
     *
     * @return string The delete endpoint URL.
     */
    private function get_delete_url(): string {
        return preg_replace('/\/upsert$/', '/delete', $this->endpoint);
    }

    /**
     * Send an HTTP POST request with retry logic.
     *
     * Retries on network errors and 5xx server errors with exponential backoff.
     * Does not retry on 4xx client errors.
     *
     * @param string $url The request URL.
     * @param array $payload The JSON payload.
     * @return array Result with keys 'success', 'http_code', 'response', 'error'.
     */
    private function send_request(string $url, array $payload): array {
        $jsonpayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $lastresult = [
            'success' => false,
            'http_code' => 0,
            'response' => '',
            'error' => '',
        ];

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            // The endpoint URL is explicitly configured by a site admin, so we
            // bypass Moodle's cURL security blocklist (which blocks non-standard
            // ports and private IPs by default).
            $curl = new \curl(['ignoresecurity' => true]);
            $curl->setHeader([
                'Content-Type: application/json',
                'X-API-Key: ' . $this->apikey,
            ]);

            $response = $curl->post($url, $jsonpayload, [
                'CURLOPT_TIMEOUT' => $this->timeout,
                'CURLOPT_CONNECTTIMEOUT' => min(10, $this->timeout),
            ]);

            $info = $curl->get_info();
            $httpcode = (int) ($info['http_code'] ?? 0);
            $errno = $curl->get_errno();

            $lastresult = [
                'success' => ($httpcode >= 200 && $httpcode < 300),
                'http_code' => $httpcode,
                'response' => $response,
                'error' => $errno ? $curl->error : '',
            ];

            // Success — return immediately.
            if ($lastresult['success']) {
                return $lastresult;
            }

            // Do not retry client errors (4xx).
            if ($httpcode >= 400 && $httpcode < 500) {
                return $lastresult;
            }

            // Exponential backoff before retry: 1s, 2s.
            if ($attempt < self::MAX_RETRIES) {
                sleep(pow(2, $attempt - 1));
            }
        }

        debugging(
            "RAG API request to {$url} failed after " . self::MAX_RETRIES
                . " attempts. HTTP {$lastresult['http_code']}: {$lastresult['error']}",
            DEBUG_DEVELOPER
        );

        return $lastresult;
    }
}
