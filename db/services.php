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
 * External-Services für local_kursassistent.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_kursassistent_get_bausteine' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'get_bausteine',
        'description' => 'Liefert aktive Bausteine, Kursabschnitte und Video-Status',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_insert_bausteine' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'insert_bausteine',
        'description' => 'Fügt ausgewählte Bausteine als Labels in den Kurs ein',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_get_peertube_videos' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'get_peertube_videos',
        'description' => 'Liefert die PeerTube-Videoliste für den Verlinken-Tab',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_rename_section' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'rename_section',
        'description' => 'Benennt einen Kursabschnitt um',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_create_section' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'create_section',
        'description' => 'Erzeugt einen neuen Kursabschnitt',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_get_course_activities' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'get_course_activities',
        'description' => 'Liefert Aktivitäten mit Abschluss- und Voraussetzungsinformationen',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_set_completion' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'set_completion',
        'description' => 'Setzt die Abschlussverfolgung für Aktivitäten',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_set_restriction' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'set_restriction',
        'description' => 'Setzt eine Abschluss-Voraussetzung für eine Aktivität',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_create_lernpfad' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'create_lernpfad',
        'description' => 'Erzeugt einen linearen Lernpfad mit verketteten Voraussetzungen',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_set_visibility' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'set_visibility',
        'description' => 'Setzt die Sichtbarkeit von Aktivitäten und Abschnitten',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_get_section_templates' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'get_section_templates',
        'description' => 'Liefert verfügbare Abschnittsvorlagen (global + persönlich)',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_save_section_template' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'save_section_template',
        'description' => 'Speichert eine Abschnittsvorlage (neu oder aktualisieren)',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_delete_section_template' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'delete_section_template',
        'description' => 'Löscht eine Abschnittsvorlage',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_apply_section_template' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'apply_section_template',
        'description' => 'Wendet eine Abschnittsvorlage auf einen Kursabschnitt an',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_get_section_content' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'get_section_content',
        'description' => 'Liest den Inhalt eines Kursabschnitts für Vorlagen-Erstellung',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_get_activity_types' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'get_activity_types',
        'description' => 'Liefert verfügbare Aktivitätstypen für Vorlagen',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_get_course_statistics' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'get_course_statistics',
        'description' => 'Liefert Abschluss-Statistiken pro Aktivität mit Teilnehmer-Details',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_get_own_progress' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'get_own_progress',
        'description' => 'Liefert den eigenen Abschlussfortschritt des aktuellen Nutzers',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:viewownprogress',
    ],
    'local_kursassistent_save_student_comment' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'save_student_comment',
        'description' => 'Speichert einen Lehrkraft-Kommentar zum Fortschritt eines Teilnehmers',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
    'local_kursassistent_get_student_comments' => [
        'classname' => 'local_kursassistent\external\local_kursassistent_external',
        'methodname' => 'get_student_comments',
        'description' => 'Liefert alle Lehrkraft-Kommentare eines Kurses',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/kursassistent:use',
    ],
];
