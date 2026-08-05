<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External-Services für local_kursassistent.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_kursassistent_get_bausteine' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'get_bausteine',
        'description' => 'Liefert aktive Bausteine, Kursabschnitte und Video-Status',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_insert_bausteine' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'insert_bausteine',
        'description' => 'Fügt ausgewählte Bausteine als Labels in den Kurs ein',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_get_peertube_videos' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'get_peertube_videos',
        'description' => 'Liefert die PeerTube-Videoliste für den Verlinken-Tab',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_rename_section' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'rename_section',
        'description' => 'Benennt einen Kursabschnitt um',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_create_section' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'create_section',
        'description' => 'Erzeugt einen neuen Kursabschnitt',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
];
