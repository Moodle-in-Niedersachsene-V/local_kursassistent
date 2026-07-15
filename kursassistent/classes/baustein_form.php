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

namespace local_kursassistent;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Formular zum Anlegen/Bearbeiten eines Bausteins.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class baustein_form extends \moodleform {

    /**
     * Formular-Definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'titel', get_string('titel', 'local_kursassistent'), ['size' => 40]);
        $mform->setType('titel', PARAM_TEXT);
        $mform->addRule('titel', get_string('required'), 'required', null, 'client');

        $iconoptions = [
            'target-arrow' => get_string('icon_target-arrow', 'local_kursassistent'),
            'key' => get_string('icon_key', 'local_kursassistent'),
            'file' => get_string('icon_file', 'local_kursassistent'),
            'image' => get_string('icon_image', 'local_kursassistent'),
            'video' => get_string('icon_video', 'local_kursassistent'),
            'checklist' => get_string('icon_checklist', 'local_kursassistent'),
            'file-text' => get_string('icon_file-text', 'local_kursassistent'),
            'bulb' => get_string('icon_bulb', 'local_kursassistent'),
            'alert-circle' => get_string('icon_alert-circle', 'local_kursassistent'),
            'list-numbers' => get_string('icon_list-numbers', 'local_kursassistent'),
            'clipboard-check' => get_string('icon_clipboard-check', 'local_kursassistent'),
        ];
        $mform->addElement('select', 'icon', get_string('icon', 'local_kursassistent'), $iconoptions);
        $mform->setDefault('icon', 'file-text');

        $typoptions = [
            'text' => get_string('typ_text', 'local_kursassistent'),
            'datei' => get_string('typ_datei', 'local_kursassistent'),
            'bild' => get_string('typ_bild', 'local_kursassistent'),
            'video' => get_string('typ_video', 'local_kursassistent'),
        ];
        $mform->addElement('select', 'typ', get_string('typ', 'local_kursassistent'), $typoptions);
        $mform->setDefault('typ', 'text');

        $mform->addElement(
            'textarea',
            'platzhalter',
            get_string('platzhalter', 'local_kursassistent'),
            ['rows' => 3, 'cols' => 50]
        );
        $mform->setType('platzhalter', PARAM_RAW);
        $mform->hideIf('platzhalter', 'typ', 'neq', 'text');

        $mform->addElement('advcheckbox', 'aktiv', get_string('aktiv', 'local_kursassistent'));
        $mform->setDefault('aktiv', 1);

        $this->add_action_buttons();
    }
}
