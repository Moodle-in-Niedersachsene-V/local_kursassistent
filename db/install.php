<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Installationsroutine lokal_kursassistent: legt Standardbausteine an.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Standardbausteine nach Installation anlegen.
 *
 * @return bool
 */
function xmldb_local_kursassistent_install() {
    global $DB;

    $now = time();

    $default = [
        [
            'titel' => 'Lernziele',
            'icon' => 'target-arrow',
            'typ' => 'text',
            'platzhalter' => '<p>Nach diesem Kursabschnitt können die Teilnehmenden ...</p>',
            'sortorder' => 1,
        ],
        [
            'titel' => 'Voraussetzungen',
            'icon' => 'key',
            'typ' => 'text',
            'platzhalter' => '<p>Für diesen Kursabschnitt werden folgende Vorkenntnisse benötigt: ...</p>',
            'sortorder' => 2,
        ],
        [
            'titel' => 'Materialien',
            'icon' => 'file',
            'typ' => 'datei',
            'platzhalter' => null,
            'sortorder' => 3,
        ],
        [
            'titel' => 'Bild',
            'icon' => 'image',
            'typ' => 'bild',
            'platzhalter' => null,
            'sortorder' => 4,
        ],
        [
            'titel' => 'Video',
            'icon' => 'video',
            'typ' => 'video',
            'platzhalter' => null,
            'sortorder' => 5,
        ],
        [
            'titel' => 'Bewertungskriterien',
            'icon' => 'checklist',
            'typ' => 'text',
            'platzhalter' => '<p>Die Bewertung erfolgt anhand folgender Kriterien: ...</p>',
            'sortorder' => 6,
        ],
        [
            'titel' => 'Aufgabe',
            'icon' => 'clipboard-check',
            'typ' => 'aktivitaet',
            'modname' => 'assign',
            'platzhalter' => null,
            'sortorder' => 7,
        ],
        [
            'titel' => 'Test',
            'icon' => 'checklist',
            'typ' => 'aktivitaet',
            'modname' => 'quiz',
            'platzhalter' => null,
            'sortorder' => 8,
        ],
    ];

    foreach ($default as $baustein) {
        $record = (object) $baustein;
        $record->aktiv = 1;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $DB->insert_record('local_kursassistent_types', $record);
    }

    set_config('videoaktiv', 1, 'local_kursassistent');

    return true;
}
