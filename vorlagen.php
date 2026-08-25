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

$kursurl = new moodle_url('/course/view.php', ['id' => $courseid]);
$seitenurl = new moodle_url('/local/kursassistent/vorlagen.php', ['courseid' => $courseid]);

// Ausfuehrung nach Bestaetigung.
if ($templateid && $confirm && confirm_sesskey()) {
    $vorlage = vorlagen_manager::get_geprueft($templateid);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('vorlagen_uebernahmelaeuft', 'local_kursassistent'));
    echo html_writer::tag('p', get_string('vorlagen_bittewarten', 'local_kursassistent'));
    flush();

    vorlagen_manager::uebernehmen($templateid, $courseid);

    echo $OUTPUT->notification(
        get_string('vorlagen_fertig', 'local_kursassistent', format_string($vorlage->fullname)),
        \core\output\notification::NOTIFY_SUCCESS
    );
    echo html_writer::div(
        html_writer::link($kursurl, get_string('zurueckzumkurs', 'local_kursassistent'), ['class' => 'btn btn-primary']),
        'mt-3'
    );
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

if (!vorlagen_manager::is_aktiv()) {
    echo $OUTPUT->notification(
        get_string('vorlagen_nichtkonfiguriert', 'local_kursassistent'),
        \core\output\notification::NOTIFY_INFO
    );
    echo html_writer::link($kursurl, get_string('zurueckzumkurs', 'local_kursassistent'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    die;
}

$vorlagen = vorlagen_manager::get_vorlagen();

if (empty($vorlagen)) {
    echo $OUTPUT->notification(
        get_string('vorlagen_leer', 'local_kursassistent'),
        \core\output\notification::NOTIFY_INFO
    );
    echo html_writer::link($kursurl, get_string('zurueckzumkurs', 'local_kursassistent'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    die;
}

echo html_writer::tag('p', get_string('vorlagen_hinweis', 'local_kursassistent'));

echo html_writer::start_div('local-kursassistent-vorlagen');
foreach ($vorlagen as $vorlage) {
    $auswahlurl = new moodle_url('/local/kursassistent/vorlagen.php', [
        'courseid' => $courseid,
        'templateid' => $vorlage->id,
    ]);

    echo html_writer::start_div('local-kursassistent-vorlage');

    if (!empty($vorlage->bildurl)) {
        echo html_writer::empty_tag('img', [
            'src' => $vorlage->bildurl,
            'alt' => '',
            'class' => 'local-kursassistent-vorlage-bild',
        ]);
    } else {
        echo html_writer::div('', 'local-kursassistent-vorlage-bild local-kursassistent-vorlage-leer');
    }

    echo html_writer::tag('h5', format_string($vorlage->fullname), ['class' => 'local-kursassistent-vorlage-titel']);

    if (!empty($vorlage->summary)) {
        $kurzfassung = shorten_text(
            html_to_text(format_text($vorlage->summary, $vorlage->summaryformat, ['context' => $context]), 0, false),
            200
        );
        echo html_writer::tag('p', $kurzfassung, ['class' => 'local-kursassistent-vorlage-text']);
    }

    echo html_writer::link(
        $auswahlurl,
        get_string('vorlagen_auswaehlen', 'local_kursassistent'),
        ['class' => 'btn btn-primary btn-sm']
    );

    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::div(
    html_writer::link($kursurl, get_string('zurueckzumkurs', 'local_kursassistent'), ['class' => 'btn btn-secondary']),
    'mt-3'
);

echo $OUTPUT->footer();
