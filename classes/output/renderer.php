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

use plugin_renderer_base;

/**
 * Renderer of the course assistant; every page is rendered from a Mustache template.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Renders the overview of the block types.
     *
     * @param manage_page $page The page data.
     * @return string HTML
     */
    public function render_manage_page(manage_page $page): string {
        return $this->render_from_template('local_kursassistent/manage_page', $page->export_for_template($this));
    }

    /**
     * Renders the dashboard of the course assistant.
     *
     * @param dashboard_page $page The page data.
     * @return string HTML
     */
    public function render_dashboard_page(dashboard_page $page): string {
        return $this->render_from_template('local_kursassistent/dashboard_page', $page->export_for_template($this));
    }

    /**
     * Renders the selection of course templates.
     *
     * @param templates_page $page The page data.
     * @return string HTML
     */
    public function render_templates_page(templates_page $page): string {
        return $this->render_from_template('local_kursassistent/templates_page', $page->export_for_template($this));
    }

    /**
     * Renders the guide for teachers.
     *
     * @param guide_page $page The page data.
     * @return string HTML
     */
    public function render_guide_page(guide_page $page): string {
        return $this->render_from_template('local_kursassistent/guide_page', $page->export_for_template($this));
    }

    /**
     * Renders the waiting message shown while a course template is applied.
     *
     * @param transfer_running $page The page data.
     * @return string HTML
     */
    public function render_transfer_running(transfer_running $page): string {
        return $this->render_from_template('local_kursassistent/transfer_running', $page->export_for_template($this));
    }

    /**
     * Renders the success message after a course template was applied.
     *
     * @param transfer_done $page The page data.
     * @return string HTML
     */
    public function render_transfer_done(transfer_done $page): string {
        return $this->render_from_template('local_kursassistent/transfer_done', $page->export_for_template($this));
    }
}
