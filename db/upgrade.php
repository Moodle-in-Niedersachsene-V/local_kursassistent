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
 * Upgrade-Routine für local_kursassistent.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade-Schritte.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_kursassistent_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026071506) {
        // Alte Tabler-Icon-Klassennamen (ti-*) auf die neuen gebündelten SVG-Icon-Namen migrieren.
        $mapping = [
            'ti-target-arrow' => 'target-arrow',
            'target-arrow' => 'target-arrow',
            'ti-key' => 'key',
            'ti-file' => 'file',
            'ti-photo' => 'image',
            'ti-image' => 'image',
            'ti-video' => 'video',
            'ti-checklist' => 'checklist',
            'ti-file-text' => 'file-text',
        ];

        foreach ($mapping as $alt => $neu) {
            $DB->set_field('local_kursassistent_types', 'icon', $neu, ['icon' => $alt]);
        }

        // Alles, was danach immer noch nicht zu einem gebündelten Icon passt, auf Fallback setzen.
        $gueltig = ['target-arrow', 'key', 'file', 'image', 'video', 'checklist', 'file-text'];
        [$insql, $inparams] = $DB->get_in_or_equal($gueltig, SQL_PARAMS_NAMED, 'param', false);
        $DB->set_field_select('local_kursassistent_types', 'icon', 'file-text', "icon $insql", $inparams);

        upgrade_plugin_savepoint(true, 2026071506, 'local', 'kursassistent');
    }

    if ($oldversion < 2026071509) {
        // Alte 3-Optionen-Einstellung "videomodus" auf den neuen Ein/Aus-Schalter "videoaktiv" migrieren.
        $altermodus = get_config('local_kursassistent', 'videomodus');
        if ($altermodus !== false) {
            set_config('videoaktiv', $altermodus === 'aus' ? 0 : 1, 'local_kursassistent');
            unset_config('videomodus', 'local_kursassistent');
        } else if (get_config('local_kursassistent', 'videoaktiv') === false) {
            set_config('videoaktiv', 1, 'local_kursassistent');
        }

        upgrade_plugin_savepoint(true, 2026071509, 'local', 'kursassistent');
    }

    if ($oldversion < 2026071523) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_kursassistent_types');
        $field = new xmldb_field('modname', XMLDB_TYPE_CHAR, '100', null, false, null, null, 'typ');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $now = time();
        $max = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {local_kursassistent_types}');

        $neue = [
            [
                'titel' => 'Aufgabe', 'icon' => 'clipboard-check', 'typ' => 'aktivitaet',
                'modname' => 'assign', 'sortorder' => $max + 1,
            ],
            [
                'titel' => 'Test', 'icon' => 'checklist', 'typ' => 'aktivitaet',
                'modname' => 'quiz', 'sortorder' => $max + 2,
            ],
        ];

        foreach ($neue as $baustein) {
            $exists = $DB->record_exists('local_kursassistent_types', [
                'typ' => 'aktivitaet', 'modname' => $baustein['modname'],
            ]);
            if ($exists) {
                continue;
            }
            $record = (object) $baustein;
            $record->platzhalter = null;
            $record->aktiv = 1;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_kursassistent_types', $record);
        }

        upgrade_plugin_savepoint(true, 2026071523, 'local', 'kursassistent');
    }

    if ($oldversion < 2026071534) {
        // Eigene Vorlagenkategorie-Einstellung entfernt zugunsten der Integration mit
        // local_coursetemplatewizard (das bereits eine eigene, ausgereiftere Lösung bietet).
        unset_config('vorlagenkategorie', 'local_kursassistent');

        upgrade_plugin_savepoint(true, 2026071534, 'local', 'kursassistent');
    }

    if ($oldversion < 2026092101) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_kursassistent_sectpl');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('definition', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026092101, 'local', 'kursassistent');
    }

    if ($oldversion < 2026092204) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_kursassistent_comment');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('commenttext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('courseid_studentid_idx', XMLDB_INDEX_UNIQUE, ['courseid', 'studentid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026092204, 'local', 'kursassistent');
    }

    if ($oldversion < 2026100122) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_kursassistent_comment');

        // Neue Felder: Aktivitaet, Rolle der Autorin oder des Autors, Sichtbarkeit.
        $field = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'teacherid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('authorrole', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'teacher', 'cmid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('visibility', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'student', 'authorrole');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Alter Index (ein Kommentar je Teilnehmer und Kurs) wird durch den erweiterten ersetzt.
        $oldindex = new xmldb_index('courseid_studentid_idx', XMLDB_INDEX_UNIQUE, ['courseid', 'studentid']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        $newindex = new xmldb_index(
            'course_student_cm_role_idx',
            XMLDB_INDEX_UNIQUE,
            ['courseid', 'studentid', 'cmid', 'authorrole']
        );
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }
        $cmindex = new xmldb_index('cmid_idx', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
        if (!$dbman->index_exists($table, $cmindex)) {
            $dbman->add_index($table, $cmindex);
        }

        upgrade_plugin_savepoint(true, 2026100122, 'local', 'kursassistent');
    }

    if ($oldversion < 2026100133) {
        $dbman = $DB->get_manager();

        // Kursinfos der Lehrkraft.
        $table = new xmldb_table('local_kursassistent_info');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('authorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('toall', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('infotext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('authorid_idx', XMLDB_INDEX_NOTUNIQUE, ['authorid']);
            $dbman->create_table($table);
        }

        // Empfaenger einzelner Kursinfos.
        $table = new xmldb_table('local_kursassistent_info_user');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('infoid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('infoid_userid_idx', XMLDB_INDEX_UNIQUE, ['infoid', 'userid']);
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026100133, 'local', 'kursassistent');
    }

    return true;
}
