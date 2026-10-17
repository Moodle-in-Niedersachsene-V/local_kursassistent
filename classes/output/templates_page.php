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

use local_kursassistent\vorlagen_manager;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * Selection of the course templates a teacher can apply to a course.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class templates_page implements renderable, templatable {
    /** @var \stdClass The course the template is applied to. */
    private $course;

    /** @var \context_course Context for the formatting of the summaries. */
    private $context;

    /**
     * Creates the page data.
     *
     * @param \stdClass $course The target course.
     * @param \context_course $context The context of the course.
     */
    public function __construct(\stdClass $course, \context_course $context) {
        $this->course = $course;
        $this->context = $context;
    }

    /**
     * Collects the available templates.
     *
     * @param renderer_base $output The renderer.
     * @return array Template context
     */
    public function export_for_template(renderer_base $output): array {
        $data = [
            'backurl' => (new moodle_url('/course/view.php', ['id' => $this->course->id]))->out(false),
            'notconfigured' => !vorlagen_manager::is_aktiv(),
            'empty' => false,
            'items' => [],
        ];
        if ($data['notconfigured']) {
            return $data;
        }

        foreach (vorlagen_manager::get_vorlagen() as $template) {
            $summary = '';
            if (!empty($template->summary)) {
                // Plain text on purpose: Mustache escapes it, so it must not be escaped here.
                $summary = shorten_text(
                    html_to_text(
                        format_text($template->summary, $template->summaryformat, ['context' => $this->context]),
                        0,
                        false
                    ),
                    200
                );
            }
            $data['items'][] = [
                'title' => format_string($template->fullname, true, ['context' => $this->context, 'escape' => false]),
                'summary' => $summary,
                'imageurl' => !empty($template->bildurl) ? (string) $template->bildurl : '',
                'selecturl' => (new moodle_url('/local/kursassistent/vorlagen.php', [
                    'courseid' => $this->course->id,
                    'templateid' => $template->id,
                ]))->out(false),
            ];
        }
        $data['empty'] = empty($data['items']);
        return $data;
    }
}
