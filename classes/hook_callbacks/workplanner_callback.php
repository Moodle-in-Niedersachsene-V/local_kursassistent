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

use local_kursassistent\course_info;
use local_kursassistent\exacomp_bruecke;

/**
 * Feeds the work planner (block_workplanner) with course infos, teacher notes and competencies.
 *
 * The block does not know this plugin. It fires the hook \block_workplanner\hook\extend_planner whenever
 * it builds the planner for one person, and this callback adds what the person may see. If the block is not
 * installed the hook is never fired and the registration does no harm.
 *
 * The hook runs on every page view of the planner, so each kind of data costs one query per course.
 * All texts are passed as plain text, the block escapes them itself.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workplanner_callback {
    /**
     * Adds infos, notes and competencies for the person and the courses of the planner.
     *
     * @param \block_workplanner\hook\extend_planner $hook
     */
    public static function extend_planner(\block_workplanner\hook\extend_planner $hook): void {
        $userid = (int) $hook->userid;

        foreach ($hook->courseids as $courseid) {
            $courseid = (int) $courseid;
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            // Same right as get_own_progress: without it the course is skipped.
            if (!$context || !has_capability('local/kursassistent:viewownprogress', $context, $userid)) {
                continue;
            }

            self::add_infos($hook, $userid, $courseid);
            self::add_notes($hook, $userid, $courseid);
            self::add_competencies($hook, $userid, $courseid);
        }
    }

    /**
     * Converts a stored text to plain text.
     *
     * @param string $text
     * @return string
     */
    protected static function plain(string $text): string {
        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Adds the course infos the person may see.
     *
     * @param \block_workplanner\hook\extend_planner $hook
     * @param int $userid
     * @param int $courseid
     */
    protected static function add_infos(\block_workplanner\hook\extend_planner $hook, int $userid, int $courseid): void {
        foreach (course_info::get_for_user($userid, $courseid) as $info) {
            $text = self::plain($info->infotext);
            if ($text !== '') {
                $hook->add_info($courseid, $text, $info->authorname, (int) $info->timecreated);
            }
        }
    }

    /**
     * Adds the notes of the teachers per activity. Only notes released for the learner are used.
     *
     * General notes (cmid 0) are not supported by the hook yet.
     *
     * @param \block_workplanner\hook\extend_planner $hook
     * @param int $userid
     * @param int $courseid
     */
    protected static function add_notes(\block_workplanner\hook\extend_planner $hook, int $userid, int $courseid): void {
        global $DB;

        $rows = $DB->get_records('local_kursassistent_comment', [
            'courseid' => $courseid,
            'studentid' => $userid,
            'authorrole' => 'teacher',
            'visibility' => 'student',
        ]);
        foreach ($rows as $row) {
            if ((int) $row->cmid <= 0) {
                continue;
            }
            $text = self::plain($row->commenttext);
            if ($text !== '') {
                $hook->add_note((int) $row->cmid, $text);
            }
        }
    }

    /**
     * Adds the competencies assigned to the activities of the course, if Exabis is available.
     *
     * @param \block_workplanner\hook\extend_planner $hook
     * @param int $userid
     * @param int $courseid
     */
    protected static function add_competencies(\block_workplanner\hook\extend_planner $hook, int $userid, int $courseid): void {
        if (!exacomp_bruecke::verfuegbar()) {
            return;
        }
        $zuordnungen = exacomp_bruecke::get_zuordnungen($courseid);
        if (!$zuordnungen) {
            return;
        }

        $ids = [];
        foreach ($zuordnungen as $descriptorids) {
            $ids = array_merge($ids, $descriptorids);
        }
        $titel = exacomp_bruecke::get_titel(array_unique($ids), $courseid, $userid);

        foreach ($zuordnungen as $cmid => $descriptorids) {
            foreach ($descriptorids as $descriptorid) {
                if (isset($titel[$descriptorid])) {
                    $hook->add_competency((int) $cmid, (int) $descriptorid, $titel[$descriptorid]);
                }
            }
        }
    }
}
