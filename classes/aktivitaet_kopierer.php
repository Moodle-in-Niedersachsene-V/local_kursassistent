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

use backup;
use backup_controller;
use restore_controller;

/**
 * Kopiert eine einzelne Aktivität in einen anderen Kurs.
 *
 * Arbeitet wie Moodles Kursimport: Sicherung und Wiederherstellung in einem Zug,
 * ohne Nutzerdaten und ohne dass eine Sicherungsdatei abgelegt wird.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aktivitaet_kopierer {
    /**
     * Kopiert ein Kursmodul in einen anderen Kurs.
     *
     * Die Berechtigungen werden bewusst nicht hier geprüft, sondern in der
     * aufrufenden Webservice-Funktion, die beide beteiligten Kurse kennt.
     *
     * @param int $cmid Zu kopierendes Kursmodul
     * @param int $zielkursid Zielkurs
     * @param int $zielabschnitt Abschnittsnummer im Zielkurs; kleiner 0 für den ersten Abschnitt
     * @return int Kursmodul-ID der Kopie, oder 0 wenn sie nicht ermittelt werden konnte
     */
    public static function in_anderen_kurs(int $cmid, int $zielkursid, int $zielabschnitt = -1): int {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');

        \core_php_time_limit::raise(600);
        raise_memory_limit(MEMORY_EXTRA);

        $vorher = $DB->get_fieldset_select(
            'course_modules',
            'id',
            'course = :course',
            ['course' => $zielkursid]
        );

        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $cmid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $arbeitsverzeichnis = $bc->get_plan()->get_basepath();
        $rc = null;

        // Abgesichert mit finally, damit die Controller auch bei einem Abbruch freigegeben
        // und das Arbeitsverzeichnis entfernt wird. Sonst bleiben unter moodledata/temp/backup
        // bei jedem fehlgeschlagenen Versuch Verzeichnisse liegen.
        try {
            $bc->execute_plan();

            $rc = new restore_controller(
                $backupid,
                $zielkursid,
                backup::INTERACTIVE_NO,
                backup::MODE_IMPORT,
                $USER->id,
                backup::TARGET_EXISTING_ADDING
            );
            $rc->execute_precheck();
            $rc->execute_plan();
        } finally {
            $bc->destroy();
            if ($rc !== null) {
                $rc->destroy();
            }
            if (empty($CFG->keeptempdirectoriesonbackup)) {
                fulldelete($arbeitsverzeichnis);
            }
        }

        // Die neu entstandene Kursmodul-ID ermitteln, um die Kopie anschliessend
        // in den gewünschten Abschnitt verschieben zu können.
        $nachher = $DB->get_fieldset_select(
            'course_modules',
            'id',
            'course = :course',
            ['course' => $zielkursid]
        );
        $neue = array_values(array_diff($nachher, $vorher));
        $neuecmid = empty($neue) ? 0 : (int) max($neue);

        if ($neuecmid && $zielabschnitt >= 0) {
            $abschnittid = $DB->get_field('course_sections', 'id', [
                'course' => $zielkursid,
                'section' => $zielabschnitt,
            ]);
            if ($abschnittid) {
                cm_aktionen::verschieben(
                    get_course($zielkursid),
                    $neuecmid,
                    (int) $abschnittid
                );
            }
        }

        rebuild_course_cache($zielkursid, true);

        return $neuecmid;
    }
}
