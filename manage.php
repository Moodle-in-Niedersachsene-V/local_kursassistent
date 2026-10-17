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
$confirm = optional_param('confirm', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/kursassistent/manage.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('bausteineverwalten', 'local_kursassistent'));
$PAGE->set_heading(get_string('bausteineverwalten', 'local_kursassistent'));
admin_externalpage_setup('local_kursassistent_manage');

$returnurl = new moodle_url('/local/kursassistent/manage.php');

if ($action === 'delete' && $id) {
    $type = \local_kursassistent\manager::get_type($id);

    if ($confirm && confirm_sesskey()) {
        \local_kursassistent\manager::delete_type($id);
        redirect(
            $returnurl,
            get_string('geloescht', 'local_kursassistent'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Ohne Bestätigung zuerst nachfragen - löschende Aktionen dürfen nicht
    // allein durch einen Linkaufruf ausgelöst werden.
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('loeschen_bestaetigung_text', 'local_kursassistent', s($type->titel)),
        new moodle_url('/local/kursassistent/manage.php', [
            'action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey(),
        ]),
        $returnurl
    );
    echo $OUTPUT->footer();
    die;
}

$renderer = $PAGE->get_renderer('local_kursassistent');

echo $OUTPUT->header();
echo $renderer->render(new \local_kursassistent\output\manage_page());
echo $OUTPUT->footer();
