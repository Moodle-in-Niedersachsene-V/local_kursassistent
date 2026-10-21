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
 * Competency status of the learners of a course, for the teacher view in the dashboard.
 *
 * A competency counts as achieved for a person if the person has completed all activities the
 * competency is assigned to, or if a teacher has assessed it in Exabis with a value above zero.
 * The first rule is the same as for the automatic award, so the view shows the result at once,
 * even if Exabis has not been written yet.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class kompetenzstand {
    /**
     * Collects the competencies assigned to activities and who has achieved them.
     *
     * The right to use the assistant has to be checked by the caller.
     *
     * @param \stdClass $course Course
     * @param \context_course $context Course context
     * @return array available, competencies (descriptorid, title, activities, achieved userids), users
     */
    public static function collect(\stdClass $course, \context_course $context): array {
        $leer = ['available' => false, 'competencies' => [], 'users' => []];
        if (!exacomp_bruecke::verfuegbar()) {
            return $leer;
        }

        $zuordnungen = exacomp_bruecke::get_zuordnungen((int) $course->id);
        if (!$zuordnungen) {
            return ['available' => true] + $leer;
        }

        $stand = statistics::collect($course, $context);
        if (empty($stand['completionenabled'])) {
            return ['available' => true] + $leer;
        }

        // Which user has completed which activity.
        $erledigt = [];
        $gezaehlt = [];
        foreach ($stand['activities'] as $aktivitaet) {
            $gezaehlt[$aktivitaet['cmid']] = true;
            foreach ($aktivitaet['usercompletions'] as $eintrag) {
                if ($eintrag['completed']) {
                    $erledigt[$aktivitaet['cmid']][$eintrag['userid']] = true;
                }
            }
        }

        // Activities per competency, only those with completion tracking.
        $je = [];
        foreach ($zuordnungen as $cmid => $descriptorids) {
            if (!isset($gezaehlt[$cmid])) {
                continue;
            }
            foreach ($descriptorids as $descriptorid) {
                $je[$descriptorid][] = (int) $cmid;
            }
        }
        if (!$je) {
            return ['available' => true] + $leer;
        }

        $titel = exacomp_bruecke::get_titel(array_keys($je), (int) $course->id, 0);
        $bewertet = exacomp_bruecke::get_bewertungen((int) $course->id);

        $userids = array_column($stand['users'], 'userid');
        $kompetenzen = [];
        foreach ($je as $descriptorid => $cmids) {
            if (!isset($titel[$descriptorid])) {
                continue;
            }
            $erreicht = [];
            foreach ($userids as $userid) {
                $alle = true;
                foreach ($cmids as $cmid) {
                    if (empty($erledigt[$cmid][$userid])) {
                        $alle = false;
                        break;
                    }
                }
                if ($alle || !empty($bewertet[$userid][$descriptorid])) {
                    $erreicht[] = (int) $userid;
                }
            }
            $kompetenzen[] = [
                'descriptorid' => (int) $descriptorid,
                'title' => $titel[$descriptorid],
                'activities' => count($cmids),
                'achieved' => $erreicht,
            ];
        }

        usort($kompetenzen, function ($a, $b) {
            return strnatcasecmp($a['title'], $b['title']);
        });

        return ['available' => true, 'competencies' => $kompetenzen, 'users' => $stand['users']];
    }
}
