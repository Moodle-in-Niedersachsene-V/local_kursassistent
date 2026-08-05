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

namespace local_kursassistent\hook_callbacks;

use core\hook\output\before_http_headers;

/**
 * Lädt CSS (immer) und AMD-JS (nur für Berechtigte) auf Kursseiten.
 *
 * Bewusst über before_http_headers statt extend_navigation_course, da
 * Letzteres je nach Aktivität/Theme erst NACH Ausgabe des <head>-Bereichs
 * aufgerufen werden kann (z. B. bei mod_interactivevideo beobachtet) -
 * $PAGE->requires->css() wirft in diesem Fall eine coding_exception.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation_callback {
    /**
     * Prüft, ob die aktuelle Seite eine Kurs-Ansicht ist.
     *
     * @return \stdClass|null
     */
    protected static function get_relevant_course(): ?\stdClass {
        global $PAGE, $COURSE;

        if (!$PAGE->has_set_url()) {
            return null;
        }

        $path = $PAGE->url->get_path();
        if (strpos($path, '/course/view.php') === false) {
            return null;
        }

        if (empty($COURSE) || empty($COURSE->id) || $COURSE->id == SITEID) {
            return null;
        }

        return $COURSE;
    }

    /**
     * Bindet CSS (immer, für alle Betrachtenden) und AMD-JS (nur für Berechtigte) ein.
     *
     * @param before_http_headers $hook
     */
    public static function add_assets(before_http_headers $hook): void {
        global $PAGE;

        $course = self::get_relevant_course();
        if (!$course) {
            return;
        }

        // Das CSS wird immer geladen und gilt auch ohne eigene Berechtigung.
        // Es steuert unter anderem die Groessenbegrenzung von Videos.
        $PAGE->requires->css('/local/kursassistent/styles.css');

        $context = \context_course::instance($course->id);
        if (!has_capability('local/kursassistent:use', $context)) {
            return;
        }

        $PAGE->requires->js_call_amd('local_kursassistent/assistent', 'init', [(int) $course->id]);
    }
}
