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
 * Formular mit echtem Moodle-Datei-Picker (zeigt automatisch alle konfigurierten
 * Repositories wie Nextcloud, OneDrive, PeerTube etc. an).
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pick_file_form extends \moodleform {
    /**
     * Formular-Definition.
     */
    public function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata;

        $mform->addElement('hidden', 'courseid', $customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'sectionnum', $customdata['sectionnum']);
        $mform->setType('sectionnum', PARAM_INT);
        $mform->addElement('hidden', 'typeid', $customdata['typeid']);
        $mform->setType('typeid', PARAM_INT);

        $options = [
            'maxbytes' => $customdata['maxbytes'] ?? 0,
            'accepted_types' => $customdata['accepted_types'] ?? '*',
            'return_types' => $customdata['return_types'] ?? FILE_INTERNAL,
        ];

        $mform->addElement('filepicker', 'datei', get_string('dateiauswaehlen', 'local_kursassistent'), null, $options);
        $mform->addHelpButton('datei', 'dateiauswaehlen', 'local_kursassistent');
        // Bewusst KEINE client-seitige 'required'-Regel: diese erkennt externe
        // Repository-Referenzen (z. B. PeerTube-Auswahl) nicht zuverlässig als
        // ausgefüllt und blockiert dann fälschlich das Absenden. Die serverseitige
        // Prüfung in manager::create_label_with_file() fängt eine fehlende Datei ab.

        $this->add_action_buttons(true, get_string('uebernehmen', 'local_kursassistent'));
    }
}
