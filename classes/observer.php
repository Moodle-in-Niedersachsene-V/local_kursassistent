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
 * Event-Beobachter für local_kursassistent.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kursassistent;

/**
 * Event-Beobachter: räumt Anmerkungen zu gelöschten Aktivitäten auf.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Löscht die Anmerkungen zu einer gelöschten Aktivität.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;

        $DB->delete_records('local_kursassistent_comment', [
            'courseid' => $event->courseid,
            'cmid' => $event->objectid,
        ]);
    }

    /**
     * Removes the course infos and notes when a course is deleted.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;

        $courseid = (int) $event->objectid;
        \local_kursassistent\course_info::delete_for_course($courseid);
        $DB->delete_records('local_kursassistent_comment', ['courseid' => $courseid]);
        $DB->delete_records('local_kursassistent_log', ['courseid' => $courseid]);
    }

    /**
     * Awards a competency when all activities assigned to it are completed.
     *
     * Runs when the completion of an activity changes. Whether the activity is now completed is
     * part of the check for all assigned activities. Activities the person cannot see or that have no completion tracking are
     * not counted. Revoked completion does not take a competency away, the teacher decides about that.
     *
     * @param \core\event\course_module_completion_updated $event
     */
    public static function course_module_completion_updated(\core\event\course_module_completion_updated $event): void {
        $setting = get_config('local_kursassistent', 'awardcompetencies');
        if ($setting !== false && $setting !== null && !(int) $setting) {
            return;
        }
        if (!\local_kursassistent\exacomp_bruecke::verfuegbar()) {
            return;
        }

        $courseid = (int) $event->courseid;
        $userid = (int) $event->relateduserid;
        $cmid = (int) $event->contextinstanceid;
        $zuordnungen = \local_kursassistent\exacomp_bruecke::get_zuordnungen($courseid);
        if (empty($zuordnungen[$cmid])) {
            return;
        }

        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course, $userid);
        $completioninfo = new \completion_info($course);
        foreach ($zuordnungen[$cmid] as $descriptorid) {
            if (self::all_completed($zuordnungen, $descriptorid, $modinfo, $completioninfo, $userid)) {
                \local_kursassistent\exacomp_bruecke::vergebe_kompetenz($courseid, $userid, $descriptorid);
            }
        }
    }

    /**
     * Checks whether the person has completed all visible activities assigned to a competency.
     *
     * @param array $zuordnungen Descriptor ids per course module id
     * @param int $descriptorid Competency
     * @param \course_modinfo $modinfo Course modules as the person sees them
     * @param \completion_info $completioninfo
     * @param int $userid
     * @return bool
     */
    protected static function all_completed(
        array $zuordnungen,
        int $descriptorid,
        \course_modinfo $modinfo,
        \completion_info $completioninfo,
        int $userid
    ): bool {
        $counted = 0;
        foreach ($zuordnungen as $cmid => $descriptorids) {
            if (!in_array($descriptorid, $descriptorids)) {
                continue;
            }
            $cm = $modinfo->cms[$cmid] ?? null;
            if (!$cm || !$cm->uservisible || $cm->deletioninprogress) {
                continue;
            }
            if ($completioninfo->is_enabled($cm) == COMPLETION_TRACKING_NONE) {
                continue;
            }
            $data = $completioninfo->get_data($cm, false, $userid);
            if (!in_array((int) $data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS])) {
                return false;
            }
            $counted++;
        }
        return $counted > 0;
    }
}
