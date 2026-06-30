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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the api_client class.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_ragingest\api_client::class)]
final class api_client_test extends \advanced_testcase {
    /**
     * Test that the client reports not configured when settings are empty.
     */
    public function test_is_configured_returns_false_when_empty(): void {
        $this->resetAfterTest();

        set_config('rag_endpoint_url', '', 'local_ragingest');
        set_config('rag_api_key', '', 'local_ragingest');

        $client = new api_client();
        $this->assertFalse($client->is_configured());
    }

    /**
     * Test that the client reports not configured when only URL is set.
     */
    public function test_is_configured_returns_false_without_api_key(): void {
        $this->resetAfterTest();

        set_config('rag_endpoint_url', 'http://localhost:8001/documents/upsert', 'local_ragingest');
        set_config('rag_api_key', '', 'local_ragingest');

        $client = new api_client();
        $this->assertFalse($client->is_configured());
    }

    /**
     * Test that the client reports not configured when only API key is set.
     */
    public function test_is_configured_returns_false_without_url(): void {
        $this->resetAfterTest();

        set_config('rag_endpoint_url', '', 'local_ragingest');
        set_config('rag_api_key', 'secret-key-123', 'local_ragingest');

        $client = new api_client();
        $this->assertFalse($client->is_configured());
    }

    /**
     * Test that the client reports configured when both settings are set.
     */
    public function test_is_configured_returns_true_when_configured(): void {
        $this->resetAfterTest();

        set_config('rag_endpoint_url', 'http://localhost:8001/documents/upsert', 'local_ragingest');
        set_config('rag_api_key', 'secret-key-123', 'local_ragingest');

        $client = new api_client();
        $this->assertTrue($client->is_configured());
    }

    /**
     * Test upsert with a mocked successful response.
     */
    public function test_upsert_success_with_mock(): void {
        $this->resetAfterTest();

        set_config('rag_endpoint_url', 'http://localhost:8001/documents/upsert', 'local_ragingest');
        set_config('rag_api_key', 'test-key', 'local_ragingest');

        // Use curl mock response.
        \curl::mock_response('{"status": "ok"}');

        $client = new api_client();
        $result = $client->upsert([
            'source_id' => 'test:course1:cmid1',
            'content' => base64_encode('Hello'),
            'content_type' => 'text/plain',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['http_code']);
    }

    /**
     * Test delete with a mocked successful response.
     */
    public function test_delete_success_with_mock(): void {
        $this->resetAfterTest();

        set_config('rag_endpoint_url', 'http://localhost:8001/documents/upsert', 'local_ragingest');
        set_config('rag_api_key', 'test-key', 'local_ragingest');

        \curl::mock_response('{"status": "ok"}');

        $client = new api_client();
        $result = $client->delete('test:course1:cmid1');

        $this->assertTrue($result['success']);
    }
}
