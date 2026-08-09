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
 * Datei-/Bild-/Video-Auswahl über den echten Moodle-Datei-Picker
 * (zeigt automatisch alle konfigurierten Repositories an).
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/repository/lib.php');
require_once(__DIR__ . '/classes/pick_file_form.php');

$courseid = required_param('courseid', PARAM_INT);
$sectionnum = required_param('sectionnum', PARAM_INT);
$typeid = required_param('typeid', PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('local/kursassistent:use', $context);

$type = \local_kursassistent\manager::get_type($typeid);
if (!in_array($type->typ, ['datei', 'bild'], true)) {
    throw new moodle_exception('invalidtype', 'local_kursassistent');
}

$acceptedtypes = '*';
// Neben echten Kopien (FILE_INTERNAL) auch Referenz-Repositories zulassen, damit
// Speicherorte wie repository_nextclouddirect im Picker erscheinen. Diese laden die
// Datei in die Cloud und hinterlegen in Moodle nur eine Referenz darauf.
$returntypes = FILE_INTERNAL | FILE_CONTROLLED_LINK | FILE_REFERENCE;
if ($type->typ === 'bild') {
    $acceptedtypes = ['web_image'];
}

$PAGE->set_url(new moodle_url('/local/kursassistent/pick_file.php', [
    'courseid' => $courseid, 'sectionnum' => $sectionnum, 'typeid' => $typeid,
]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('dateiauswaehlen', 'local_kursassistent') . ': ' . $type->titel);
$PAGE->set_heading(get_string('dateiauswaehlen', 'local_kursassistent') . ': ' . $type->titel);

$course = get_course($courseid);
$PAGE->set_course($course);

$returnurl = new moodle_url('/course/view.php', ['id' => $courseid], 'section-' . $sectionnum);

$form = new \local_kursassistent\pick_file_form(null, [
    'courseid' => $courseid,
    'sectionnum' => $sectionnum,
    'typeid' => $typeid,
    'accepted_types' => $acceptedtypes,
    'return_types' => $returntypes,
    'maxbytes' => $course->maxbytes ?? 0,
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    \local_kursassistent\manager::create_label_with_file($courseid, $sectionnum, $type, $data->datei);
    redirect(
        $returnurl,
        get_string('bausteineingefuegt', 'local_kursassistent'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('dateiauswaehlen_hinweis', 'local_kursassistent', $type->titel));
$form->display();
echo $OUTPUT->footer();
