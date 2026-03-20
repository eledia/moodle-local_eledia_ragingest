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
 * Unit tests for the H5P text extractor utility.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ragingest\h5p_text_extractor
 */
final class h5p_text_extractor_test extends \advanced_testcase {
    /**
     * Test extraction from a flat Fill-in-the-Blanks style JSON.
     */
    public function test_flat_fill_in_blanks(): void {
        $json = json_encode([
            'params' => [
                'text' => '<p>The capital of France is *Paris*.</p>',
                'questions' => '<p>Fill in the blank above.</p>',
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('The capital of France is *Paris*.', $result);
        $this->assertStringContainsString('Fill in the blank above.', $result);
    }

    /**
     * Test extraction from a Multiple Choice style JSON with nested answer arrays.
     */
    public function test_multiple_choice_answers(): void {
        $json = json_encode([
            'params' => [
                'question' => '<p>What is the largest planet in our solar system?</p>',
                'answers' => [
                    ['text' => '<p>Jupiter is the correct answer</p>', 'correct' => true],
                    ['text' => '<p>Saturn is a very large planet too</p>', 'correct' => false],
                    ['text' => '<p>Mars is actually quite small</p>', 'correct' => false],
                ],
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('What is the largest planet', $result);
        $this->assertStringContainsString('Jupiter is the correct answer', $result);
        $this->assertStringContainsString('Saturn is a very large planet too', $result);
        $this->assertStringContainsString('Mars is actually quite small', $result);
    }

    /**
     * Test deeply nested Course Presentation style JSON.
     */
    public function test_deeply_nested_course_presentation(): void {
        $json = json_encode([
            'params' => [
                'presentation' => [
                    'slides' => [
                        [
                            'elements' => [
                                [
                                    'params' => [
                                        'text' => '<p>Welcome to the introduction slide.</p>',
                                    ],
                                ],
                                [
                                    'params' => [
                                        'text' => '<p>This module covers quantum physics.</p>',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'elements' => [
                                [
                                    'params' => [
                                        'text' => '<p>Slide two discusses wave-particle duality.</p>',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('Welcome to the introduction slide.', $result);
        $this->assertStringContainsString('This module covers quantum physics.', $result);
        $this->assertStringContainsString('Slide two discusses wave-particle duality.', $result);
    }

    /**
     * Test that blocklisted keys are skipped.
     */
    public function test_blocklist_filtering(): void {
        $json = json_encode([
            'params' => [
                'text' => '<p>This educational text should appear.</p>',
                'subContentId' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                'library' => 'H5P.MultiChoice 1.14',
                'metadata' => [
                    'title' => 'Should not appear from metadata block',
                    'license' => 'CC BY',
                ],
                'behaviour' => [
                    'enableRetry' => true,
                    'enableSolutionsButton' => true,
                ],
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('This educational text should appear.', $result);
        $this->assertStringNotContainsString('aaaaaaaa-bbbb', $result);
        $this->assertStringNotContainsString('H5P.MultiChoice', $result);
        $this->assertStringNotContainsString('Should not appear from metadata block', $result);
        $this->assertStringNotContainsString('CC BY', $result);
    }

    /**
     * Test that short strings (< 10 chars) are filtered out.
     */
    public function test_short_strings_filtered(): void {
        $json = json_encode([
            'params' => [
                'text' => '<p>This is a meaningful educational sentence.</p>',
                'shortval' => 'tiny',
                'number' => '42',
                'id' => 'abc',
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('This is a meaningful educational sentence.', $result);
        $this->assertStringNotContainsString('tiny', $result);
        $this->assertStringNotContainsString('42', $result);
    }

    /**
     * Test that URLs and file paths are excluded.
     */
    public function test_urls_and_paths_excluded(): void {
        $json = json_encode([
            'params' => [
                'text' => '<p>This content discusses web technologies in detail.</p>',
                'imagepath' => 'https://example.com/images/photo.jpg',
                'filepath' => '/content/images/diagram.png',
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('This content discusses web technologies in detail.', $result);
        $this->assertStringNotContainsString('example.com', $result);
        $this->assertStringNotContainsString('/content/images', $result);
    }

    /**
     * Test that HTML tags are stripped from extracted text.
     */
    public function test_html_tags_stripped(): void {
        $json = json_encode([
            'params' => [
                'text' => '<p>Text with <strong>bold</strong> and <em>italic</em> formatting.</p>',
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('Text with bold and italic formatting.', $result);
        $this->assertStringNotContainsString('<strong>', $result);
        $this->assertStringNotContainsString('<em>', $result);
    }

    /**
     * Test empty and invalid JSON inputs.
     */
    public function test_empty_and_invalid_input(): void {
        $this->assertEquals('', h5p_text_extractor::extract_text_from_json(''));
        $this->assertEquals('', h5p_text_extractor::extract_text_from_json('not-json'));
        $this->assertEquals('', h5p_text_extractor::extract_text_from_json('null'));
        $this->assertEquals('', h5p_text_extractor::extract_text_from_json('{}'));
    }

    /**
     * Test that duplicate text values are deduplicated.
     */
    public function test_deduplication(): void {
        $json = json_encode([
            'params' => [
                'questionA' => '<p>This same question appears twice in the content.</p>',
                'questionB' => '<p>This same question appears twice in the content.</p>',
                'different' => '<p>This is a completely different sentence for variety.</p>',
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        // Should appear only once despite being in two fields.
        $count = substr_count($result, 'This same question appears twice in the content.');
        $this->assertEquals(1, $count);
        $this->assertStringContainsString('This is a completely different sentence for variety.', $result);
    }

    /**
     * Test Accordion-style H5P content with panels.
     */
    public function test_accordion_panels(): void {
        $json = json_encode([
            'params' => [
                'panels' => [
                    [
                        'title' => 'Introduction to Biology',
                        'content' => [
                            'params' => [
                                'text' => '<p>Biology is the study of living organisms.</p>',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Cell Structure and Function',
                        'content' => [
                            'params' => [
                                'text' => '<p>Cells are the basic unit of life on Earth.</p>',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('Introduction to Biology', $result);
        $this->assertStringContainsString('Biology is the study of living organisms.', $result);
        $this->assertStringContainsString('Cell Structure and Function', $result);
        $this->assertStringContainsString('Cells are the basic unit of life on Earth.', $result);
    }

    /**
     * Test extraction from JSON without the 'params' wrapper.
     */
    public function test_json_without_params_wrapper(): void {
        $json = json_encode([
            'text' => '<p>Direct content without a params wrapper element.</p>',
            'questions' => '<p>A test question placed at the top level.</p>',
        ]);

        $result = h5p_text_extractor::extract_text_from_json($json);

        $this->assertStringContainsString('Direct content without a params wrapper element.', $result);
        $this->assertStringContainsString('A test question placed at the top level.', $result);
    }
}
