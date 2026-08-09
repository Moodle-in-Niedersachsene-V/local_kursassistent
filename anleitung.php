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
 * Anleitung für Lehrkräfte - Inhalt vom Admin frei editierbar.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('local/kursassistent:use', $context);

$course = get_course($courseid);

$PAGE->set_url(new moodle_url('/local/kursassistent/anleitung.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('anleitungtitel', 'local_kursassistent'));
$PAGE->set_heading(get_string('anleitungtitel', 'local_kursassistent'));

$inhalt = get_config('local_kursassistent', 'anleitung_inhalt');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('anleitungtitel', 'local_kursassistent'));

if (empty(trim(strip_tags((string) $inhalt)))) {
    echo html_writer::tag('p', get_string('anleitung_leer', 'local_kursassistent'), ['class' => 'text-muted']);
} else {
    echo html_writer::div(format_text($inhalt, FORMAT_HTML, ['context' => $context]));
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('zurueckzumkurs', 'local_kursassistent'),
        ['class' => 'btn btn-secondary']
    ),
    'mt-3'
);

echo $OUTPUT->footer();
