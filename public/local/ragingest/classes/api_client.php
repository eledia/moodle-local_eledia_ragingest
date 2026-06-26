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
    private const MAX_RETRIES = 2;

    /** @var string The RAG API endpoint URL. */
    private string $endpoint;

    /** @var string The API key for authentication. */
    private string $apikey;

    /** @var int Request timeout in seconds. */
    private int $timeout;

    /** @var bool Whether private/internal RAG targets may bypass Moodle cURL restrictions. */
    private bool $allowprivatetarget;

    /** @var string[] Additional headers needed for local loopback aliases. */
    private array $extraheaders = [];

    /**
     * Constructor. Reads configuration from plugin settings.
     */
    public function __construct() {
        $this->endpoint = get_config('local_ragingest', 'rag_endpoint_url') ?: '';
        $this->apikey = get_config('local_ragingest', 'rag_api_key') ?: '';
        $this->timeout = (int) (get_config('local_ragingest', 'request_timeout_seconds') ?: 30);
        $this->allowprivatetarget = !empty(get_config('local_ragingest', 'allow_private_target'));
        $this->normalise_local_loopback_endpoint();
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
     * Check whether the configured RAG ingestion service is reachable.
     *
     * The health URL is derived from the configured upsert endpoint. For the
     * standard endpoint `.../documents/upsert` this calls `.../health`.
     *
     * @return array Result with keys 'success', 'http_code', 'response', 'error'.
     */
    public function healthcheck(): array {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'http_code' => 0,
                'response' => '',
                'error' => 'missing_config',
            ];
        }

        $curl = $this->create_curl();
        $headers = [
            'Accept: application/json',
            'X-API-Key: ' . $this->apikey,
        ];
        $curl->setHeader(array_merge($headers, $this->extraheaders));

        $response = $curl->get($this->get_health_url(), [], [
            'CURLOPT_TIMEOUT' => min(5, max(1, $this->timeout)),
            'CURLOPT_CONNECTTIMEOUT' => min(3, max(1, $this->timeout)),
        ]);

        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        $errno = $curl->get_errno();

        return [
            'success' => ($httpcode >= 200 && $httpcode < 300),
            'http_code' => $httpcode,
            'response' => $response,
            'error' => $errno ? $curl->error : '',
        ];
    }

    /**
     * Send an upsert request to the RAG API.
     *
     * @param array $payload The document payload.
     * @return array Result with keys 'success', 'http_code', 'response', 'error'.
     */
    public function upsert(array $payload): array {
        return $this->send_request($this->get_upsert_url(), $payload);
    }

    /**
     * Send a delete request to the RAG API.
     *
     * Derives the delete URL from the configured upsert endpoint
     * by replacing /upsert with /delete.
     *
     * @param string $sourceid The source_id of the document to delete.
     * @param string $scope Deletion scope: 'exact' (default) deletes the single
     *                      matching document; 'prefix' also deletes every
     *                      sub-document whose id begins with "{sourceid}:".
     * @return array Result with keys 'success', 'http_code', 'response', 'error'.
     */
    public function delete(string $sourceid, string $scope = 'exact'): array {
        $deleteurl = $this->get_delete_url();
        return $this->send_request($deleteurl, ['source_id' => $sourceid, 'scope' => $scope]);
    }

    /**
     * Derive the delete endpoint URL from the upsert endpoint.
     *
     * @return string The delete endpoint URL.
     */
    private function get_delete_url(): string {
        return $this->get_action_url('delete');
    }

    /**
     * Derive the health endpoint URL from the upsert endpoint.
     *
     * @return string The health endpoint URL.
     */
    private function get_health_url(): string {
        return $this->get_action_url('health');
    }

    /**
     * Derive the upsert endpoint URL from the configured base/action endpoint.
     *
     * @return string The upsert endpoint URL.
     */
    private function get_upsert_url(): string {
        return $this->get_action_url('upsert');
    }

    /**
     * Derive one action URL from the configured endpoint.
     *
     * This accepts both external service-style URLs (`.../documents/upsert`) and
     * local LiteRAG URLs (`.../ingest.php`, `.../ingest.php/upsert` or
     * `.../ingest.php?action=upsert`).
     *
     * @param string $action One of 'health', 'upsert', 'delete'.
     * @return string Action-specific endpoint URL.
     */
    private function get_action_url(string $action): string {
        if (preg_match('#/ingest\.php$#', $this->endpoint)) {
            return $this->endpoint . '?action=' . $action;
        }
        if (preg_match('#/ingest\.php/(health|upsert|delete)/?$#', $this->endpoint)) {
            return preg_replace('#/(health|upsert|delete)/?$#', '/' . $action, $this->endpoint);
        }
        if (preg_match('/([?&]action=)(health|upsert|delete)\b/', $this->endpoint)) {
            return preg_replace('/([?&]action=)(health|upsert|delete)\b/', '$1' . $action, $this->endpoint);
        }
        if (preg_match('#/documents/(health|upsert|delete)$#', $this->endpoint)) {
            return preg_replace('#/(health|upsert|delete)$#', '/' . $action, $this->endpoint);
        }
        if (preg_match('#/(health|upsert|delete)$#', $this->endpoint)) {
            return preg_replace('#/(health|upsert|delete)$#', '/' . $action, $this->endpoint);
        }

        return rtrim($this->endpoint, '/') . '/' . $action;
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
            $curl = $this->create_curl();
            $headers = [
                'Content-Type: application/json',
                'X-API-Key: ' . $this->apikey,
            ];
            $curl->setHeader(array_merge($headers, $this->extraheaders));

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

            // Keep inline backoff short; Moodle task retry handles longer outages.
            if ($attempt < self::MAX_RETRIES) {
                sleep(1);
            }
        }

        debugging(
            "RAG API request to {$url} failed after " . self::MAX_RETRIES
                . " attempts. HTTP {$lastresult['http_code']}: {$lastresult['error']}",
            DEBUG_DEVELOPER
        );

        return $lastresult;
    }

    /**
     * Create the Moodle cURL wrapper for RAG calls.
     *
     * Some installations use an internal Docker/Kubernetes service name such
     * as `rag-service:8001`. Moodle blocks private hosts and non-standard ports
     * by default, so bypassing that protection is an explicit admin opt-in.
     *
     * @return \curl
     */
    private function create_curl(): \curl {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        return new \curl($this->allowprivatetarget ? ['ignoresecurity' => true] : []);
    }

    /**
     * Route local Docker callbacks through the host while preserving Moodle's public host.
     *
     * In local Docker setups Moodle's public wwwroot is often localhost:8080.
     * From inside the PHP container that address points at the container itself,
     * so calls to an in-Moodle LiteRAG endpoint must go through
     * host.docker.internal while keeping Moodle's configured Host header.
     */
    private function normalise_local_loopback_endpoint(): void {
        global $CFG;

        if ($this->endpoint === '') {
            return;
        }

        $wwwroot = rtrim((string) $CFG->wwwroot, '/');
        if (!str_starts_with($this->endpoint, $wwwroot . '/')) {
            return;
        }

        $parts = parse_url($wwwroot);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return;
        }

        $scheme = (string) ($parts['scheme'] ?? 'http');
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $suffix = substr($this->endpoint, strlen($wwwroot));

        $this->endpoint = $scheme . '://host.docker.internal' . $port . $path . $suffix;
        $this->extraheaders[] = 'Host: ' . $host . $port;
        $this->allowprivatetarget = true;
    }
}
