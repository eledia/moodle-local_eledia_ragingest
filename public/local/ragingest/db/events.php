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
 * Event observer definitions for the RAG ingestion plugin.
 *
 * @package    local_ragingest
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\local_ragingest\observer::course_module_created',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\local_ragingest\observer::course_module_updated',
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\local_ragingest\observer::course_module_deleted',
    ],

    // Sub-content events: book chapters.
    [
        'eventname' => '\mod_book\event\chapter_created',
        'callback' => '\local_ragingest\observer::book_chapter_changed',
    ],
    [
        'eventname' => '\mod_book\event\chapter_updated',
        'callback' => '\local_ragingest\observer::book_chapter_changed',
    ],
    [
        'eventname' => '\mod_book\event\chapter_deleted',
        'callback' => '\local_ragingest\observer::book_chapter_changed',
    ],

    // Sub-content events: glossary entries.
    [
        'eventname' => '\mod_glossary\event\entry_created',
        'callback' => '\local_ragingest\observer::glossary_entry_changed',
    ],
    [
        'eventname' => '\mod_glossary\event\entry_updated',
        'callback' => '\local_ragingest\observer::glossary_entry_changed',
    ],
    [
        'eventname' => '\mod_glossary\event\entry_deleted',
        'callback' => '\local_ragingest\observer::glossary_entry_changed',
    ],

    // Sub-content events: lesson pages.
    [
        'eventname' => '\mod_lesson\event\page_created',
        'callback' => '\local_ragingest\observer::lesson_page_changed',
    ],
    [
        'eventname' => '\mod_lesson\event\page_updated',
        'callback' => '\local_ragingest\observer::lesson_page_changed',
    ],
    [
        'eventname' => '\mod_lesson\event\page_deleted',
        'callback' => '\local_ragingest\observer::lesson_page_changed',
    ],

    // Sub-content events: wiki pages.
    [
        'eventname' => '\mod_wiki\event\page_created',
        'callback' => '\local_ragingest\observer::wiki_page_changed',
    ],
    [
        'eventname' => '\mod_wiki\event\page_updated',
        'callback' => '\local_ragingest\observer::wiki_page_changed',
    ],
    [
        'eventname' => '\mod_wiki\event\page_deleted',
        'callback' => '\local_ragingest\observer::wiki_page_changed',
    ],
];
