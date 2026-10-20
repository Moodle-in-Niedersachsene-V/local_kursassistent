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
 * Abschlussstand der Teilnehmenden eines Kurses, gemeinsam genutzt von Webservice und Dashboard.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class statistics {
    /**
     * Ermittelt den Anzeigestatus einer Aktivitaet fuer eine Person.
     *
     * Ueberfaellig hat Vorrang, wenn der Abschluss bis zu einem Termin erwartet war.
     * In Arbeit heisst: es gibt bereits Fortschritt (Aufruf oder nicht bestandener Abschluss), aber noch kein Abschluss.
     *
     * @param \stdClass $data Ergebnis von completion_info::get_data
     * @param int $expected Erwarteter Abschluss (Zeitstempel), 0 ohne Termin
     * @return string done, overdue, progress oder open
     */
    public static function activity_status(\stdClass $data, int $expected): string {
        $state = (int) $data->completionstate;
        if (in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS])) {
            return 'done';
        }
        if ($expected > 0 && $expected < time()) {
            return 'overdue';
        }
        if ($state === COMPLETION_COMPLETE_FAIL || !empty($data->timemodified)) {
            return 'progress';
        }
        return 'open';
    }

    /**
     * Sammelt Aktivitäten, Teilnehmende und den Stand je Person und Aktivität.
     *
     * Die Berechtigung muss die aufrufende Stelle vorher geprüft haben.
     *
     * @param \stdClass $course Kurs
     * @param \context_course $context Kurskontext
     * @return array Struktur wie get_course_statistics
     */
    public static function collect(\stdClass $course, \context_course $context): array {
        global $CFG;

        $completioninfo = new \completion_info($course);

        if (!$completioninfo->is_enabled()) {
            return [
                'completionenabled' => false,
                'activities' => [],
                'users' => [],
            ];
        }

        require_once($CFG->libdir . '/gradelib.php');

        $modinfo = get_fast_modinfo($course);

        // Eingeschriebene Teilnehmer mit Studentenrolle holen.
        $users = get_enrolled_users(
            $context,
            'mod/assign:submit',
            0,
            'u.id, u.firstname, u.lastname',
            'u.lastname ASC, u.firstname ASC'
        );

        // Alle Aktivitäten sammeln, die Abschlussverfolgung haben.
        $activities = [];
        $sections = $modinfo->get_section_info_all();

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || $cm->deletioninprogress) {
                continue;
            }

            $completiondata = $completioninfo->get_data($cm, false);
            $tracking = $completioninfo->is_enabled($cm);
            if ($tracking == COMPLETION_TRACKING_NONE) {
                continue;
            }

            // Bewertungen der Aktivitaet, soweit sie eine Bewertung hat und der Nutzer sie sehen darf.
            $grades = [];
            $gradeinfo = grade_get_grades($course->id, 'mod', $cm->modname, $cm->instance, array_keys($users));
            if (!empty($gradeinfo->items[0]->grades)) {
                foreach ($gradeinfo->items[0]->grades as $gradeuserid => $gradeobj) {
                    if ($gradeobj->grade !== null && empty($gradeobj->hidden)) {
                        $grades[(int) $gradeuserid] = trim(strip_tags((string) $gradeobj->str_grade));
                    }
                }
            }

            // Abschluss-Daten pro Teilnehmer ermitteln.
            $completedcount = 0;
            $usercompletions = [];
            foreach ($users as $user) {
                $data = $completioninfo->get_data($cm, false, $user->id);
                $iscomplete = in_array((int) $data->completionstate, [
                    COMPLETION_COMPLETE,
                    COMPLETION_COMPLETE_PASS,
                ]);
                if ($iscomplete) {
                    $completedcount++;
                }
                $usercompletions[] = [
                    'userid' => (int) $user->id,
                    'completed' => $iscomplete,
                    'completionstate' => (int) $data->completionstate,
                    'status' => self::activity_status($data, (int) $cm->completionexpected),
                    'grade' => $grades[(int) $user->id] ?? '',
                ];
            }

            // Abschnittsnamen ermitteln.
            $sectionname = '';
            if (isset($sections[$cm->sectionnum])) {
                $secinfo = $sections[$cm->sectionnum];
                $sectionname = !empty($secinfo->name) ? $secinfo->name : get_string('section') . ' ' . $cm->sectionnum;
            }

            $activities[] = [
                'cmid' => (int) $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
                'sectionnum' => (int) $cm->sectionnum,
                'sectionname' => $sectionname,
                'totalusers' => count($users),
                'completedusers' => $completedcount,
                'due' => (int) $cm->completionexpected,
                'usercompletions' => $usercompletions,
            ];
        }

        // Teilnehmer-Liste für die Tabelle.
        $userlist = [];
        foreach ($users as $user) {
            $userlist[] = [
                'userid' => (int) $user->id,
                'fullname' => fullname($user),
            ];
        }

        return [
            'completionenabled' => true,
            'activities' => $activities,
            'users' => $userlist,
        ];
    }
}
