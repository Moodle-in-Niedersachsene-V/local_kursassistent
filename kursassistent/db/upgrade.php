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
 * Upgrade-Routine für local_kursassistent.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade-Schritte.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_kursassistent_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026071506) {
        // Alte Tabler-Icon-Klassennamen (ti-*) auf die neuen gebündelten SVG-Icon-Namen migrieren.
        $mapping = [
            'ti-target-arrow' => 'target-arrow',
            'target-arrow' => 'target-arrow',
            'ti-key' => 'key',
            'ti-file' => 'file',
            'ti-photo' => 'image',
            'ti-image' => 'image',
            'ti-video' => 'video',
            'ti-checklist' => 'checklist',
            'ti-file-text' => 'file-text',
        ];

        foreach ($mapping as $alt => $neu) {
            $DB->set_field('local_kursassistent_types', 'icon', $neu, ['icon' => $alt]);
        }

        // Alles, was danach immer noch nicht zu einem gebündelten Icon passt, auf Fallback setzen.
        $gueltig = ['target-arrow', 'key', 'file', 'image', 'video', 'checklist', 'file-text'];
        list($insql, $inparams) = $DB->get_in_or_equal($gueltig, SQL_PARAMS_NAMED, 'param', false);
        $DB->set_field_select('local_kursassistent_types', 'icon', 'file-text', "icon $insql", $inparams);

        upgrade_plugin_savepoint(true, 2026071506, 'local', 'kursassistent');
    }

    if ($oldversion < 2026071509) {
        // Alte 3-Optionen-Einstellung "videomodus" auf den neuen Ein/Aus-Schalter "videoaktiv" migrieren.
        $altermodus = get_config('local_kursassistent', 'videomodus');
        if ($altermodus !== false) {
            set_config('videoaktiv', $altermodus === 'aus' ? 0 : 1, 'local_kursassistent');
            unset_config('videomodus', 'local_kursassistent');
        } else if (get_config('local_kursassistent', 'videoaktiv') === false) {
            set_config('videoaktiv', 1, 'local_kursassistent');
        }

        upgrade_plugin_savepoint(true, 2026071509, 'local', 'kursassistent');
    }

    return true;
}
