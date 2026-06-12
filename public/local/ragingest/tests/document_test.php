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
 * Unit tests for the document content helper.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ragingest\document
 */
final class document_test extends \advanced_testcase {
    /**
     * An HTML document with no leading heading gains an escaped <h1> title.
     */
    public function test_html_gets_h1_heading(): void {
        $out = document::with_heading('<p>Body text.</p>', 'text/html', 'Cell Biology & You');

        $this->assertStringStartsWith('<h1>Cell Biology &amp; You</h1>', $out);
        $this->assertStringContainsString('<p>Body text.</p>', $out);
    }

    /**
     * An HTML document already opening with an <h1> is left unchanged
     * (the extractor supplied its own title).
     */
    public function test_html_with_existing_h1_is_untouched(): void {
        $content = '<h1>Existing Title</h1>' . "\n" . '<dl><dt>Term</dt></dl>';
        $out = document::with_heading($content, 'text/html', 'Activity Name');

        $this->assertSame($content, $out);
        $this->assertStringNotContainsString('Activity Name', $out);
    }

    /**
     * Plain text gains the title as a leading line.
     */
    public function test_plaintext_gets_title_line(): void {
        $out = document::with_heading("Transcript line one.", 'text/plain', 'My Lecture');

        $this->assertSame("My Lecture\n\nTranscript line one.", $out);
    }

    /**
     * Plain text already beginning with the title is not doubled.
     */
    public function test_plaintext_not_doubled(): void {
        $content = "My Lecture\n\nAlready titled.";
        $out = document::with_heading($content, 'text/plain', 'My Lecture');

        $this->assertSame($content, $out);
    }

    /**
     * Binary content (PDF) is never modified — base64 bytes must stay intact.
     */
    public function test_pdf_is_never_modified(): void {
        $pdf = "%PDF-1.7\nbinary-bytes";
        $out = document::with_heading($pdf, 'application/pdf', 'Reading.pdf');

        $this->assertSame($pdf, $out);
    }

    /**
     * An empty title is a no-op for any content type.
     */
    public function test_empty_title_is_noop(): void {
        $this->assertSame('<p>x</p>', document::with_heading('<p>x</p>', 'text/html', '   '));
        $this->assertSame('plain', document::with_heading('plain', 'text/plain', ''));
    }
}
