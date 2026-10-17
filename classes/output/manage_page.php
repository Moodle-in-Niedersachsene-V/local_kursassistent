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

use local_kursassistent\manager;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * Overview table of the block types for administrators.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_page implements renderable, templatable {
    /**
     * Collects the block types for the table.
     *
     * @param renderer_base $output The renderer.
     * @return array Template context
     */
    public function export_for_template(renderer_base $output): array {
        $rows = [];
        foreach (manager::get_types(false) as $type) {
            $isactivity = $type->typ === 'aktivitaet' && !empty($type->modname);
            $rows[] = [
                'iconurl' => $output->image_url(manager::normalize_icon($type->icon), 'local_kursassistent')->out(false),
                'title' => $type->titel,
                'typelabel' => get_string('typ_' . $type->typ, 'local_kursassistent'),
                'modname' => $isactivity ? $type->modname : '',
                'active' => (bool) $type->aktiv,
                'editurl' => (new moodle_url('/local/kursassistent/edit.php', ['id' => $type->id]))->out(false),
                'deleteurl' => (new moodle_url('/local/kursassistent/manage.php', [
                    'action' => 'delete',
                    'id' => $type->id,
                ]))->out(false),
            ];
        }
        return [
            'newurl' => (new moodle_url('/local/kursassistent/edit.php'))->out(false),
            'types' => $rows,
        ];
    }
}
