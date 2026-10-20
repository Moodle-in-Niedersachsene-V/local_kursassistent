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
 * Course infos: short messages of a teacher to all or to selected learners of a course.
 *
 * The class is also the PHP interface for other plugins, for example a work planner:
 * {@see course_info::get_for_user()} returns the infos a learner may see.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_info {
    /**
     * Returns the infos a learner may see in a course, newest first.
     *
     * @param int $userid Learner
     * @param int $courseid Course
     * @return \stdClass[] Records with id, authorid, authorname, infotext, timecreated, timemodified
     */
    public static function get_for_user(int $userid, int $courseid): array {
        global $DB;

        $sql = "SELECT i.id, i.authorid, i.infotext, i.timecreated, i.timemodified
                  FROM {local_kursassistent_info} i
                 WHERE i.courseid = :courseid
                       AND (i.toall = 1
                            OR EXISTS (SELECT 1
                                         FROM {local_kursassistent_info_user} iu
                                        WHERE iu.infoid = i.id AND iu.userid = :userid))
              ORDER BY i.timecreated DESC, i.id DESC";
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
        foreach ($records as $record) {
            $author = \core_user::get_user($record->authorid);
            $record->authorname = $author ? fullname($author) : '';
        }
        return array_values($records);
    }

    /**
     * Returns all infos of a course for the teacher view, newest first, with the recipients.
     *
     * @param int $courseid Course
     * @return array[] Each with id, toall, infotext, timecreated, timemodified, authorname, userids, recipients
     */
    public static function get_for_course(int $courseid): array {
        global $DB;

        $infos = $DB->get_records('local_kursassistent_info', ['courseid' => $courseid], 'timecreated DESC, id DESC');
        $result = [];
        foreach ($infos as $info) {
            $userids = $info->toall ? [] : $DB->get_fieldset_select(
                'local_kursassistent_info_user',
                'userid',
                'infoid = :infoid',
                ['infoid' => $info->id]
            );
            $names = [];
            foreach ($userids as $userid) {
                $user = \core_user::get_user($userid);
                if ($user && !$user->deleted) {
                    $names[] = fullname($user);
                }
            }
            sort($names);
            $author = \core_user::get_user($info->authorid);
            $result[] = [
                'id' => (int) $info->id,
                'toall' => (bool) $info->toall,
                'infotext' => $info->infotext,
                'timecreated' => (int) $info->timecreated,
                'timemodified' => (int) $info->timemodified,
                'authorname' => $author ? fullname($author) : '',
                'userids' => array_map('intval', $userids),
                'recipients' => $names,
            ];
        }
        return $result;
    }

    /**
     * Creates or updates an info.
     *
     * @param int $courseid Course
     * @param int $authorid Teacher
     * @param string $text Text, plain text
     * @param bool $toall True for all learners
     * @param int[] $userids Recipients if not for all
     * @param int $infoid Existing info to update, 0 to create
     * @return int Id of the info
     */
    public static function save(int $courseid, int $authorid, string $text, bool $toall, array $userids, int $infoid = 0): int {
        global $DB;

        $now = time();
        $transaction = $DB->start_delegated_transaction();
        if ($infoid) {
            $info = $DB->get_record('local_kursassistent_info', ['id' => $infoid, 'courseid' => $courseid], '*', MUST_EXIST);
            $info->toall = $toall ? 1 : 0;
            $info->infotext = $text;
            $info->timemodified = $now;
            $DB->update_record('local_kursassistent_info', $info);
            $DB->delete_records('local_kursassistent_info_user', ['infoid' => $infoid]);
        } else {
            $infoid = $DB->insert_record('local_kursassistent_info', (object) [
                'courseid' => $courseid,
                'authorid' => $authorid,
                'toall' => $toall ? 1 : 0,
                'infotext' => $text,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        if (!$toall) {
            foreach (array_unique($userids) as $userid) {
                $DB->insert_record('local_kursassistent_info_user', (object) ['infoid' => $infoid, 'userid' => $userid]);
            }
        }
        $transaction->allow_commit();
        return (int) $infoid;
    }

    /**
     * Deletes an info with its recipients.
     *
     * @param int $courseid Course
     * @param int $infoid Info
     */
    public static function delete(int $courseid, int $infoid): void {
        global $DB;

        $DB->get_record('local_kursassistent_info', ['id' => $infoid, 'courseid' => $courseid], 'id', MUST_EXIST);
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_kursassistent_info_user', ['infoid' => $infoid]);
        $DB->delete_records('local_kursassistent_info', ['id' => $infoid]);
        $transaction->allow_commit();
    }

    /**
     * Deletes all infos of a course.
     *
     * @param int $courseid Course
     */
    public static function delete_for_course(int $courseid): void {
        global $DB;

        $ids = $DB->get_fieldset_select('local_kursassistent_info', 'id', 'courseid = :c', ['c' => $courseid]);
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids);
            $DB->delete_records_select('local_kursassistent_info_user', "infoid $insql", $params);
        }
        $DB->delete_records('local_kursassistent_info', ['courseid' => $courseid]);
    }

    /**
     * Removes a user: their received-info links and the infos they wrote.
     *
     * @param int $courseid Course
     * @param int $userid User
     */
    public static function delete_for_user(int $courseid, int $userid): void {
        global $DB;

        $ids = $DB->get_fieldset_select('local_kursassistent_info', 'id', 'courseid = :c', ['c' => $courseid]);
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $params['userid'] = $userid;
            $DB->delete_records_select('local_kursassistent_info_user', "userid = :userid AND infoid $insql", $params);
        }
        $authored = $DB->get_fieldset_select(
            'local_kursassistent_info',
            'id',
            'courseid = :c AND authorid = :a',
            ['c' => $courseid, 'a' => $userid]
        );
        foreach ($authored as $id) {
            self::delete($courseid, (int) $id);
        }
    }
}
