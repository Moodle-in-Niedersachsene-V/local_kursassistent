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
 * Seite zum Anlegen/Bearbeiten eines Bausteins.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/baustein_form.php');

require_login();
$context = context_system::instance();
require_capability('local/kursassistent:manage', $context);

$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/kursassistent/edit.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$title = $id ? get_string('bearbeiten', 'local_kursassistent') : get_string('neuerbaustein', 'local_kursassistent');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add(get_string('pluginname', 'local_kursassistent'), new moodle_url('/local/kursassistent/manage.php'));
$PAGE->navbar->add($title);

$returnurl = new moodle_url('/local/kursassistent/manage.php');

$existing = $id ? \local_kursassistent\manager::get_type($id) : null;

$form = new \local_kursassistent\baustein_form();

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    \local_kursassistent\manager::save_type($data);
    redirect($returnurl);
} else if ($existing) {
    $form->set_data($existing);
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
