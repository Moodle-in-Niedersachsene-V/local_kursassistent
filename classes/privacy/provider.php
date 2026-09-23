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

namespace local_kursassistent\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Provider für local_kursassistent.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Beschreibt die gespeicherten personenbezogenen Daten.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_kursassistent_log', [
            'userid' => 'privacy:metadata:local_kursassistent_log:userid',
            'courseid' => 'privacy:metadata:local_kursassistent_log:courseid',
            'typeid' => 'privacy:metadata:local_kursassistent_log:typeid',
            'timecreated' => 'privacy:metadata:local_kursassistent_log:timecreated',
        ], 'privacy:metadata:local_kursassistent_log');

        $collection->add_database_table('local_kursassistent_comment', [
            'studentid' => 'privacy:metadata:local_kursassistent_comment:studentid',
            'teacherid' => 'privacy:metadata:local_kursassistent_comment:teacherid',
            'commenttext' => 'privacy:metadata:local_kursassistent_comment:commenttext',
            'timecreated' => 'privacy:metadata:local_kursassistent_comment:timecreated',
        ], 'privacy:metadata:local_kursassistent_comment');

        $collection->add_database_table('local_kursassistent_sectpl', [
            'userid' => 'privacy:metadata:local_kursassistent_sectpl:userid',
            'name' => 'privacy:metadata:local_kursassistent_sectpl:name',
            'description' => 'privacy:metadata:local_kursassistent_sectpl:description',
            'timecreated' => 'privacy:metadata:local_kursassistent_sectpl:timecreated',
        ], 'privacy:metadata:local_kursassistent_sectpl');

        return $collection;
    }

    /**
     * Liefert die Kontexte, in denen Daten zu einer Nutzerin/einem Nutzer existieren.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {local_kursassistent_log} l
                  JOIN {context} ctx ON ctx.instanceid = l.courseid AND ctx.contextlevel = :contextlevel
                 WHERE l.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        // Kommentare: sowohl als Student als auch als Lehrkraft.
        $sql = "SELECT ctx.id
                  FROM {local_kursassistent_comment} c
                  JOIN {context} ctx ON ctx.instanceid = c.courseid AND ctx.contextlevel = :contextlevel
                 WHERE c.studentid = :studentid OR c.teacherid = :teacherid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'studentid' => $userid,
            'teacherid' => $userid,
        ]);

        // Abschnittsvorlagen haben keinen Kursbezug, sie gehoeren der anlegenden Person.
        $sql = "SELECT ctx.id
                  FROM {local_kursassistent_sectpl} t
                  JOIN {context} ctx ON ctx.instanceid = t.userid AND ctx.contextlevel = :contextlevel
                 WHERE t.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_USER,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Liefert alle Nutzer-IDs mit Daten in einem gegebenen Kontext.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context instanceof \context_user) {
            $sql = "SELECT userid FROM {local_kursassistent_sectpl} WHERE userid = :userid";
            $userlist->add_from_sql('userid', $sql, ['userid' => $context->instanceid]);
            return;
        }

        if (!$context instanceof \context_course) {
            return;
        }

        $sql = "SELECT userid FROM {local_kursassistent_log} WHERE courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);

        $sql = "SELECT studentid AS userid FROM {local_kursassistent_comment} WHERE courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
        $sql = "SELECT teacherid AS userid FROM {local_kursassistent_comment} WHERE courseid = :courseid2";
        $userlist->add_from_sql('userid', $sql, ['courseid2' => $context->instanceid]);
    }

    /**
     * Exportiert die Daten einer Nutzerin/eines Nutzers für die gegebenen Kontexte.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user && (int) $context->instanceid === (int) $userid) {
                $vorlagen = $DB->get_records('local_kursassistent_sectpl', ['userid' => $userid]);
                if (!empty($vorlagen)) {
                    $vdaten = array_map(function ($v) {
                        return [
                            'name' => $v->name,
                            'description' => $v->description,
                            'timecreated' => \core_privacy\local\request\transform::datetime($v->timecreated),
                        ];
                    }, array_values($vorlagen));
                    writer::with_context($context)->export_data(
                        [get_string('pluginname', 'local_kursassistent')],
                        (object) ['abschnittsvorlagen' => $vdaten]
                    );
                }
                continue;
            }

            if (!$context instanceof \context_course) {
                continue;
            }

            $records = $DB->get_records('local_kursassistent_log', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);

            if (empty($records)) {
                continue;
            }

            $data = array_map(function ($r) {
                return [
                    'typeid' => $r->typeid,
                    'cmid' => $r->cmid,
                    'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated),
                ];
            }, array_values($records));

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_kursassistent')],
                (object) ['eintraege' => $data]
            );

            // Kommentare exportieren (als Student oder Lehrkraft).
            $comments = $DB->get_records_select(
                'local_kursassistent_comment',
                'courseid = :courseid AND (studentid = :studentid OR teacherid = :teacherid)',
                ['courseid' => $context->instanceid, 'studentid' => $userid, 'teacherid' => $userid]
            );

            if (!empty($comments)) {
                $commentdata = array_map(function ($c) {
                    return [
                        'studentid' => $c->studentid,
                        'teacherid' => $c->teacherid,
                        'commenttext' => $c->commenttext,
                        'timecreated' => \core_privacy\local\request\transform::datetime($c->timecreated),
                        'timemodified' => \core_privacy\local\request\transform::datetime($c->timemodified),
                    ];
                }, array_values($comments));

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_kursassistent'), 'kommentare'],
                    (object) ['kommentare' => $commentdata]
                );
            }
        }
    }

    /**
     * Löscht alle Daten in einem gegebenen Kontext.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context instanceof \context_user) {
            $DB->delete_records('local_kursassistent_sectpl', ['userid' => $context->instanceid]);
            return;
        }

        if (!$context instanceof \context_course) {
            return;
        }

        $DB->delete_records('local_kursassistent_log', ['courseid' => $context->instanceid]);
        $DB->delete_records('local_kursassistent_comment', ['courseid' => $context->instanceid]);
    }

    /**
     * Löscht die Daten einer Nutzerin/eines Nutzers für die gegebenen Kontexte.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user && (int) $context->instanceid === (int) $userid) {
                $DB->delete_records('local_kursassistent_sectpl', ['userid' => $userid]);
                continue;
            }

            if (!$context instanceof \context_course) {
                continue;
            }
            $DB->delete_records('local_kursassistent_log', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);
            // Kommentare löschen, bei denen der Nutzer Student oder Lehrkraft ist.
            $DB->delete_records_select(
                'local_kursassistent_comment',
                'courseid = :courseid AND (studentid = :studentid OR teacherid = :teacherid)',
                ['courseid' => $context->instanceid, 'studentid' => $userid, 'teacherid' => $userid]
            );
        }
    }

    /**
     * Löscht die Daten mehrerer Nutzer für einen gegebenen Kontext.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if ($context instanceof \context_user) {
            foreach ($userlist->get_userids() as $userid) {
                $DB->delete_records('local_kursassistent_sectpl', ['userid' => $userid]);
            }
            return;
        }

        if (!$context instanceof \context_course) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('local_kursassistent_log', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);
            $DB->delete_records_select(
                'local_kursassistent_comment',
                'courseid = :courseid AND (studentid = :studentid OR teacherid = :teacherid)',
                ['courseid' => $context->instanceid, 'studentid' => $userid, 'teacherid' => $userid]
            );
        }
    }
}
