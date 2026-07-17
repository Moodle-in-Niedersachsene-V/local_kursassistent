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
 * Übersichtsseite der Bausteinverwaltung.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
$context = context_system::instance();
require_capability('local/kursassistent:manage', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/kursassistent/manage.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('bausteineverwalten', 'local_kursassistent'));
$PAGE->set_heading(get_string('bausteineverwalten', 'local_kursassistent'));
admin_externalpage_setup('local_kursassistent_manage');

if ($action === 'delete' && $id && confirm_sesskey()) {
    \local_kursassistent\manager::delete_type($id);
    redirect(new moodle_url('/local/kursassistent/manage.php'));
}

echo $OUTPUT->header();

echo html_writer::start_div('local-kursassistent-manage');
echo html_writer::tag('p', get_string('bausteineverwalten_desc', 'local_kursassistent'), ['class' => 'text-muted']);

echo html_writer::start_div('d-flex justify-content-end mb-3');
echo html_writer::link(
    new moodle_url('/local/kursassistent/edit.php'),
    get_string('neuerbaustein', 'local_kursassistent'),
    ['class' => 'btn btn-primary']
);
echo html_writer::end_div();

$types = \local_kursassistent\manager::get_types(false);

echo html_writer::start_tag('table', ['class' => 'table table-hover']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', '');
echo html_writer::tag('th', get_string('titel', 'local_kursassistent'));
echo html_writer::tag('th', get_string('typ', 'local_kursassistent'));
echo html_writer::tag('th', get_string('status', 'local_kursassistent'));
echo html_writer::tag('th', '');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($types as $type) {
    echo html_writer::start_tag('tr');
    $iconhtml = $OUTPUT->image_icon(\local_kursassistent\manager::normalize_icon($type->icon), $type->titel, 'local_kursassistent');
    echo html_writer::tag('td', $iconhtml);
    echo html_writer::tag('td', s($type->titel));
    $typlabel = get_string('typ_' . $type->typ, 'local_kursassistent');
    if ($type->typ === 'aktivitaet' && !empty($type->modname)) {
        $typlabel .= ' (' . s($type->modname) . ')';
    }
    echo html_writer::tag('td', $typlabel);
    $statuslabel = $type->aktiv
        ? html_writer::tag('span', get_string('aktiv', 'local_kursassistent'), ['class' => 'badge badge-success'])
        : html_writer::tag('span', get_string('deaktiviert', 'local_kursassistent'), ['class' => 'badge badge-secondary']);
    echo html_writer::tag('td', $statuslabel);

    $editurl = new moodle_url('/local/kursassistent/edit.php', ['id' => $type->id]);
    $deleteurl = new moodle_url('/local/kursassistent/manage.php', [
        'action' => 'delete', 'id' => $type->id, 'sesskey' => sesskey(),
    ]);
    $aktionen = html_writer::link($editurl, get_string('bearbeiten', 'local_kursassistent'), ['class' => 'btn btn-sm btn-outline-secondary mr-1']);
    $aktionen .= html_writer::link($deleteurl, get_string('loeschen', 'local_kursassistent'), [
        'class' => 'btn btn-sm btn-outline-danger',
        'data-confirm' => get_string('loeschen_bestaetigung', 'local_kursassistent'),
    ]);
    echo html_writer::tag('td', $aktionen);
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();

echo $OUTPUT->footer();
