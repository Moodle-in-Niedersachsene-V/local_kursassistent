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

namespace local_kursassistent;

/**
 * Kapselt die Kursmodul-Aktionen, die sich mit Moodle 5.2 geändert haben.
 *
 * Ab Moodle 5.2 ersetzt core_courseformat\local\cmactions die Funktionen
 * duplicate_module(), moveto_module() und set_coursemodule_visible(). Die alten
 * Funktionen arbeiten zwar weiterhin, geben aber einen Veraltungshinweis aus.
 * Bei eingeschaltetem Debugging landet dieser Hinweis mitten in der Antwort eines
 * Webservice und macht sie unbrauchbar. Diese Klasse wählt daher den jeweils
 * passenden Weg, damit das Plugin auf Moodle 5.1 und neuer funktioniert.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cm_aktionen {
    /**
     * Prüft, ob die ab Moodle 5.2 vorgesehene Klasse zur Verfügung steht.
     *
     * @return bool
     */
    protected static function neue_api_vorhanden(): bool {
        return class_exists('\core_courseformat\local\cmactions');
    }

    /**
     * Erzeugt die Aktionsklasse für einen Kurs.
     *
     * @param \stdClass $course
     * @return \core_courseformat\local\cmactions
     */
    protected static function aktionen(\stdClass $course) {
        $klasse = '\core_courseformat\local\cmactions';
        return new $klasse($course);
    }

    /**
     * Ermittelt die Abschnitts-ID zu einer Abschnittsnummer.
     *
     * Die neue Schnittstelle arbeitet mit der Datensatz-ID des Abschnitts,
     * nicht mit dessen laufender Nummer im Kurs.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @return int
     */
    public static function abschnitt_id(int $courseid, int $sectionnum): int {
        global $DB;

        return (int) $DB->get_field('course_sections', 'id', [
            'course' => $courseid,
            'section' => $sectionnum,
        ], MUST_EXIST);
    }

    /**
     * Dupliziert ein Kursmodul innerhalb seines Kurses.
     *
     * @param \stdClass $course Kurs, in dem das Modul liegt
     * @param int $cmid Zu duplizierendes Kursmodul
     * @param int|null $zielabschnittid Abschnitts-ID für die Kopie; null für den Abschnitt des Originals
     * @return int Kursmodul-ID der Kopie, oder 0 wenn sie nicht ermittelt werden konnte
     */
    public static function duplizieren(\stdClass $course, int $cmid, ?int $zielabschnittid = null): int {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        // Das Duplizieren läuft intern über Sicherung und Wiederherstellung. Bei
        // umfangreichen Aktivitäten wie einem Test mit vielen Fragen reicht das
        // übliche Zeitlimit des Webservers dafür nicht aus.
        \core_php_time_limit::raise(600);
        raise_memory_limit(MEMORY_EXTRA);

        if (self::neue_api_vorhanden()) {
            // Die Abschnitts-ID wird immer ausdrücklich übergeben. cmactions::duplicate()
            // führt zwar null auf den Abschnitt des Originals zurück, prüft danach aber
            // gegen den unveränderten Parameter. Bei Modulen, die nicht auf der Kursseite
            // erscheinen, schlägt diese Prüfung mit null deshalb immer fehl.
            if ($zielabschnittid === null) {
                $modinfo = get_fast_modinfo($course);
                $zielabschnittid = (int) $modinfo->get_cm($cmid)->get_section_info()->id;
            }

            $kopie = self::aktionen($course)->duplicate($cmid, $zielabschnittid);
            return $kopie === null ? 0 : (int) $kopie->id;
        }

        // Moodle 5.1: bisheriger Weg.
        $cm = get_coursemodule_from_id('', $cmid, $course->id, false, MUST_EXIST);
        $kopie = duplicate_module($course, $cm);
        $neuecmid = $kopie === null ? 0 : (int) $kopie->id;

        if ($neuecmid && $zielabschnittid !== null) {
            $abschnitt = $DB->get_record('course_sections', ['id' => $zielabschnittid], '*', MUST_EXIST);
            $modul = $DB->get_record('course_modules', ['id' => $neuecmid], '*', MUST_EXIST);
            moveto_module($modul, $abschnitt);
        }

        return $neuecmid;
    }

    /**
     * Verschiebt ein Kursmodul an eine andere Position.
     *
     * @param \stdClass $course Kurs, in dem verschoben wird
     * @param int $cmid Zu verschiebendes Kursmodul
     * @param int $zielabschnittid Abschnitts-ID des Zielabschnitts
     * @param int $vorcmid Kursmodul, vor das einsortiert wird; 0 für ans Ende des Abschnitts
     * @return void
     */
    public static function verschieben(\stdClass $course, int $cmid, int $zielabschnittid, int $vorcmid = 0): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        if (self::neue_api_vorhanden()) {
            $aktionen = self::aktionen($course);
            if ($vorcmid) {
                $aktionen->move_before($cmid, $vorcmid);
            } else {
                $aktionen->move_end_section($cmid, $zielabschnittid);
            }
            return;
        }

        // Moodle 5.1: bisheriger Weg.
        $modul = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
        $abschnitt = $DB->get_record('course_sections', ['id' => $zielabschnittid], '*', MUST_EXIST);
        $vormodul = null;
        if ($vorcmid) {
            $vormodul = $DB->get_record('course_modules', ['id' => $vorcmid], '*', MUST_EXIST);
        }
        moveto_module($modul, $abschnitt, $vormodul);
    }

    /**
     * Setzt die Sichtbarkeit eines Kursmoduls.
     *
     * @param \stdClass $course Kurs, in dem das Modul liegt
     * @param int $cmid Kursmodul
     * @param int $sichtbar 1 für sichtbar, 0 für verborgen
     * @return void
     */
    public static function sichtbarkeit(\stdClass $course, int $cmid, int $sichtbar): void {
        global $CFG;

        require_once($CFG->dirroot . '/course/lib.php');

        if (self::neue_api_vorhanden()) {
            self::aktionen($course)->set_visibility($cmid, $sichtbar);
            return;
        }

        // Moodle 5.1: bisheriger Weg.
        set_coursemodule_visible($cmid, $sichtbar);
    }
}
