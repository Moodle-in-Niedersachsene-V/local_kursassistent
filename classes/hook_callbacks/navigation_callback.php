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

namespace local_kursassistent\hook_callbacks;

use core\hook\output\before_http_headers;

/**
 * Lädt CSS (immer) und AMD-JS (nur für Berechtigte) auf Kursseiten.
 *
 * Auf anderen Seiten eines Kurses, etwa dem Formular einer Aktivität, wird für Berechtigte
 * nur das kleine Modul für die Rückkehr in den Assistenten geladen.
 *
 * Bewusst über before_http_headers statt extend_navigation_course, da
 * Letzteres je nach Aktivität/Theme erst NACH Ausgabe des <head>-Bereichs
 * aufgerufen werden kann (z. B. bei mod_interactivevideo beobachtet) -
 * $PAGE->requires->css() wirft in diesem Fall eine coding_exception.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * Liefert den Kurs, wenn die Seite zu einem Kurs gehört, aber nicht die Kursansicht ist.
     *
     * @return \stdClass|null
     */
    protected static function get_other_course_page(): ?\stdClass {
        global $PAGE, $COURSE;

        if (!$PAGE->has_set_url()) {
            return null;
        }

        if (strpos($PAGE->url->get_path(), '/course/view.php') !== false) {
            return null;
        }

        if (empty($COURSE) || empty($COURSE->id) || $COURSE->id == SITEID) {
            return null;
        }

        return $COURSE;
    }

    /**
     * Bindet auf anderen Kursseiten das Modul für die Rückkehr in den Assistenten ein.
     *
     * Das Modul tut nur etwas, wenn der Assistent vorher einen Wechsel zum Formular einer
     * Aktivität vermerkt hat. Es wird deshalb nur für Berechtigte geladen.
     */
    protected static function add_rueckkehr_hinweis(): void {
        global $PAGE;

        $course = self::get_other_course_page();
        if (!$course) {
            return;
        }

        $context = \context_course::instance($course->id);
        if (has_capability('local/kursassistent:use', $context)) {
            $PAGE->requires->js_call_amd('local_kursassistent/rueckkehr', 'init', [(int) $course->id]);
        }
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
            self::add_rueckkehr_hinweis();
            return;
        }

        // CSS immer laden - betrifft auch die Darstellung generierter Inhalte
        // (z. B. Video-Größenbegrenzung) für Betrachtende ohne eigene Berechtigung.
        $PAGE->requires->css('/local/kursassistent/styles.css');

        $context = \context_course::instance($course->id);

        if (has_capability('local/kursassistent:use', $context)) {
            $PAGE->requires->js_call_amd('local_kursassistent/assistent', 'init', [(int) $course->id]);
        } else if (has_capability('local/kursassistent:viewownprogress', $context)) {
            $PAGE->requires->js_call_amd('local_kursassistent/fortschritt', 'init', [(int) $course->id]);
        }
    }

    /**
     * Adds the entry "Course assistant" to the primary navigation for persons who may use it in a course.
     *
     * The result is kept in the session for ten minutes so the course list is not searched on every page.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        global $SESSION;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $cache = $SESSION->local_kursassistent_nav ?? null;
        if (!is_array($cache) || ($cache['time'] ?? 0) < time() - 600) {
            $allowed = is_siteadmin();
            if (!$allowed) {
                foreach (enrol_get_my_courses(['id']) as $course) {
                    if (has_capability('local/kursassistent:use', \context_course::instance($course->id))) {
                        $allowed = true;
                        break;
                    }
                }
            }
            $cache = ['time' => time(), 'allowed' => $allowed];
            $SESSION->local_kursassistent_nav = $cache;
        }
        if (empty($cache['allowed'])) {
            return;
        }

        $hook->get_primaryview()->add(
            get_string('pluginname', 'local_kursassistent'),
            new \moodle_url('/local/kursassistent/dashboard.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_kursassistent_primary'
        );
    }
}
