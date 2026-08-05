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
 * lib.php für local_kursassistent.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Hängt den Kursassistenten-Button in die Kurs-Navigationsleiste ein
 * (Reiterzeile "Kurs, Einstellungen, Teilnehmer/innen ..." neben "Mehr").
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_kursassistent_extend_navigation_course($navigation, $course, $context) {
    if ($course->id == SITEID) {
        return;
    }

    if (!has_capability('local/kursassistent:use', $context)) {
        return;
    }

    $node = navigation_node::create(
        get_string('assistentbutton', 'local_kursassistent'),
        new moodle_url('#'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_kursassistent_open',
        new pix_icon('i/settings', '')
    );
    $node->add_class('local-kursassistent-navlink');
    $navigation->add_node($node);

    $anleitungnode = navigation_node::create(
        get_string('anleitungtitel', 'local_kursassistent'),
        new moodle_url('/local/kursassistent/anleitung.php', ['courseid' => $course->id]),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_kursassistent_anleitung',
        new pix_icon('i/help', '')
    );
    $navigation->add_node($anleitungnode);

    // CSS und JavaScript werden bewusst nicht hier geladen.
    // Die Einbindung erfolgt ueber den Hook in classes/hook_callbacks.
    // Sonst droht ein Aufruf nach Ausgabe des Kopfbereichs.
    // Das wuerde zu einem Fehler fuehren.
}
