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
 * Admin-Einstellungen für local_kursassistent.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_kursassistent', get_string('pluginname', 'local_kursassistent'));

    $settings->add(new admin_setting_configcheckbox(
        'local_kursassistent/videoaktiv',
        get_string('videoaktiv', 'local_kursassistent'),
        get_string('videoaktiv_desc', 'local_kursassistent'),
        1
    ));

    $kategorien = ['0' => get_string('vorlagenkategorie_keine', 'local_kursassistent')]
        + \core_course_category::make_categories_list();
    $settings->add(new admin_setting_configselect(
        'local_kursassistent/vorlagenkategorie',
        get_string('vorlagenkategorie', 'local_kursassistent'),
        get_string('vorlagenkategorie_desc', 'local_kursassistent'),
        '0',
        $kategorien
    ));

    $settings->add(new admin_setting_confightmleditor(
        'local_kursassistent/anleitung_inhalt',
        get_string('anleitung_inhalt', 'local_kursassistent'),
        get_string('anleitung_inhalt_desc', 'local_kursassistent'),
        get_string('anleitung_inhalt_default', 'local_kursassistent')
    ));

    $verwaltungurl = new moodle_url('/local/kursassistent/manage.php');
    $settings->add(new admin_setting_heading(
        'local_kursassistent_verwaltung',
        get_string('bausteineverwalten', 'local_kursassistent'),
        get_string('bausteineverwalten_desc', 'local_kursassistent') . '<br /><br />' .
        html_writer::link($verwaltungurl, get_string('bausteineverwalten', 'local_kursassistent'), ['class' => 'btn btn-secondary'])
    ));

    $ADMIN->add('localplugins', $settings);

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_kursassistent_manage',
        get_string('bausteineverwalten', 'local_kursassistent'),
        $verwaltungurl,
        'local/kursassistent:manage'
    ));
}
