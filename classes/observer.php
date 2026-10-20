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
}
