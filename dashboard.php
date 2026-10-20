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
 * Dashboard des Kursassistenten: Kurs aus einer Liste wählen, Kennzahlen und Bereiche.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);

$courseid = optional_param('courseid', 0, PARAM_INT);
$tab = optional_param('tab', 'stand', PARAM_ALPHA);

// Nur Kurse, in denen die Person den Assistenten nutzen darf.
$courses = [];
foreach (enrol_get_my_courses(['id', 'fullname'], 'fullname ASC') as $mycourse) {
    if (has_capability('local/kursassistent:use', context_course::instance($mycourse->id))) {
        $courses[$mycourse->id] = format_string($mycourse->fullname, true, ['context' => context_course::instance($mycourse->id)]);
    }
}
if ($courseid && !isset($courses[$courseid])) {
    // Ein Kurs ohne Einschreibung, etwa bei Administrierenden, nur mit gültiger Berechtigung zulassen.
    $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
    if ($coursecontext && has_capability('local/kursassistent:use', $coursecontext)) {
        $courses[$courseid] = format_string(get_course($courseid)->fullname, true, ['context' => $coursecontext]);
    } else {
        $courseid = 0;
    }
}
if (!$courseid) {
    // Ohne Angabe den zuletzt benutzten Kurs öffnen, sonst den ersten der Liste.
    $last = (int) get_user_preferences('local_kursassistent_lastcourse', 0);
    $courseid = isset($courses[$last]) ? $last : (int) (array_key_first($courses) ?? 0);
}
if ($courseid) {
    set_user_preference('local_kursassistent_lastcourse', $courseid);
}

$PAGE->set_url(new moodle_url('/local/kursassistent/dashboard.php', ['courseid' => $courseid, 'tab' => $tab]));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('dashboardtitel', 'local_kursassistent'));
$PAGE->set_heading(get_string('dashboardtitel', 'local_kursassistent'));
$PAGE->requires->css('/local/kursassistent/styles.css');
if ($courseid) {
    $PAGE->requires->js_call_amd('local_kursassistent/assistent', 'initDashboard', [$courseid, $tab]);
    $PAGE->requires->js_call_amd('local_kursassistent/kursinfo', 'init', [$courseid]);
}

$renderer = $PAGE->get_renderer('local_kursassistent');

echo $OUTPUT->header();
echo $renderer->render(new \local_kursassistent\output\dashboard_page($courses, $courseid, $tab));
echo $OUTPUT->footer();
