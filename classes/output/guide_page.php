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
 * Guide for teachers; the content is edited by administrators.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class guide_page implements renderable, templatable {
    /** @var \context_course Context for the formatting of the content. */
    private $context;

    /** @var int The course the guide is opened from. */
    private $courseid;

    /**
     * Creates the page data.
     *
     * @param \context_course $context The context of the course.
     * @param int $courseid The course the guide is opened from.
     */
    public function __construct(\context_course $context, int $courseid) {
        $this->context = $context;
        $this->courseid = $courseid;
    }

    /**
     * Prepares the guide text.
     *
     * @param renderer_base $output The renderer.
     * @return array Template context
     */
    public function export_for_template(renderer_base $output): array {
        $content = (string) get_config('local_kursassistent', 'anleitung_inhalt');
        $isempty = trim(strip_tags($content)) === '';
        return [
            'empty' => $isempty,
            // Sanitised by format_text(); the template prints it unescaped for that reason only.
            'content' => $isempty ? '' : format_text($content, FORMAT_HTML, ['context' => $this->context]),
            'backurl' => (new moodle_url('/course/view.php', ['id' => $this->courseid]))->out(false),
        ];
    }
}
