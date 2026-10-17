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

namespace local_kursassistent\output;

use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * Success message after a course template was applied.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transfer_done implements renderable, templatable {
    /** @var string Name of the applied template. */
    private $templatename;

    /** @var int The course the template was applied to. */
    private $courseid;

    /**
     * Creates the page data.
     *
     * @param string $templatename Name of the applied template (plain text).
     * @param int $courseid The target course.
     */
    public function __construct(string $templatename, int $courseid) {
        $this->templatename = $templatename;
        $this->courseid = $courseid;
    }

    /**
     * Prepares the message.
     *
     * @param renderer_base $output The renderer.
     * @return array Template context
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'message' => get_string('vorlagen_fertig', 'local_kursassistent', $this->templatename),
            // The parameter tells the assistant in the course that the transfer worked.
            'backurl' => (new moodle_url('/course/view.php', [
                'id' => $this->courseid,
                'kaergebnis' => 'vorlage',
            ]))->out(false),
        ];
    }
}
