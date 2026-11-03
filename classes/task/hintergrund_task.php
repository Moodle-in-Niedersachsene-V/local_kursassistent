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

namespace local_kursassistent\task;

/**
 * Hintergrundauftrag für Kurssicherung und Duplizieren von Aktivitäten.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hintergrund_task extends \core\task\adhoc_task {
    /**
     * Führt den Auftrag mit den Rechten der Person aus, die ihn gestartet hat.
     */
    public function execute() {
        \local_kursassistent\hintergrund::ausfuehren((array) $this->get_custom_data());
    }
}
