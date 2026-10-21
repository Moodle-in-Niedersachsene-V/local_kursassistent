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

namespace local_kursassistent\event;

/**
 * A competency was awarded automatically because all activities assigned to it are completed.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_awarded extends \core\event\base {
    /**
     * Sets the basic data of the event.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the name of the event.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_competency_awarded', 'local_kursassistent');
    }

    /**
     * Returns the description of the event.
     *
     * @return string
     */
    public function get_description() {
        return "The competency with the Exabis id '{$this->objectid}' was awarded automatically to the user with id " .
            "'{$this->relateduserid}' in the course with id '{$this->courseid}'.";
    }

    /**
     * Checks the data of the event.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (empty($this->relateduserid)) {
            throw new \coding_exception('The relateduserid must be set.');
        }
        if (empty($this->objectid)) {
            throw new \coding_exception('The objectid must be set.');
        }
    }

    /**
     * The event has no mapping for backup and restore.
     *
     * @return bool
     */
    public static function get_objectid_mapping() {
        return false;
    }
}
