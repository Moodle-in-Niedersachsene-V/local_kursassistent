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
 * Auswahl und Übernahme einer Kursvorlage.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_kursassistent\vorlagen_manager;

$courseid = required_param('courseid', PARAM_INT);
$templateid = optional_param('templateid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('local/kursassistent:use', $context);

$course = get_course($courseid);

$PAGE->set_url(new moodle_url('/local/kursassistent/vorlagen.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('kursvorlageuebernehmen', 'local_kursassistent'));
$PAGE->set_heading(get_string('kursvorlageuebernehmen', 'local_kursassistent'));

$renderer = $PAGE->get_renderer('local_kursassistent');
$seitenurl = new moodle_url('/local/kursassistent/vorlagen.php', ['courseid' => $courseid]);

// Ausfuehrung nach Bestaetigung.
if ($templateid && $confirm && confirm_sesskey()) {
    $vorlage = vorlagen_manager::get_geprueft($templateid);

    echo $OUTPUT->header();
    echo $renderer->render(new \local_kursassistent\output\transfer_running());
    flush();

    vorlagen_manager::uebernehmen($templateid, $courseid);

    echo $renderer->render(new \local_kursassistent\output\transfer_done(
        format_string($vorlage->fullname, true, ['context' => $context, 'escape' => false]),
        $courseid
    ));
    echo $OUTPUT->footer();
    die;
}

// Rueckfrage vor der Uebernahme.
if ($templateid) {
    $vorlage = vorlagen_manager::get_geprueft($templateid);

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('vorlagen_bestaetigung', 'local_kursassistent', [
            'vorlage' => format_string($vorlage->fullname),
            'kurs' => format_string($course->fullname),
        ]),
        new moodle_url('/local/kursassistent/vorlagen.php', [
            'courseid' => $courseid,
            'templateid' => $templateid,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]),
        $seitenurl
    );
    echo $OUTPUT->footer();
    die;
}

// Uebersicht der verfuegbaren Vorlagen.
echo $OUTPUT->header();
echo $renderer->render(new \local_kursassistent\output\templates_page($course, $context));
echo $OUTPUT->footer();
