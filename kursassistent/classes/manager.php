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

/**
 * Zentrale Verwaltungsklasse für Bausteine und Label-Erzeugung.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** Erlaubte Bausteintypen. */
    const TYPES = ['text', 'datei', 'bild', 'video', 'aktivitaet'];

    /**
     * Liefert alle installierten, sichtbaren Aktivitätstypen (modname => Anzeigename)
     * für die Admin-Auswahl bei Bausteinen vom Typ "aktivitaet".
     *
     * @return array
     */
    public static function get_available_modules(): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $modules = get_module_types_names();
        asort($modules);
        return $modules;
    }

    /**
     * Liefert alle aktiven Bausteine, sortiert.
     *
     * @param bool $onlyactive Nur aktive Bausteine liefern.
     * @return array
     */
    public static function get_types(bool $onlyactive = true): array {
        global $DB;

        $conditions = [];
        if ($onlyactive) {
            $conditions['aktiv'] = 1;
        }

        // Videobausteine nur anzeigen, wenn die Video-Funktion aktiviert ist.
        $videoaktiv = \local_kursassistent\video_helper::is_active();
        $records = $DB->get_records('local_kursassistent_types', $conditions, 'sortorder ASC');

        if (!$videoaktiv) {
            $records = array_filter($records, function($r) {
                return $r->typ !== 'video';
            });
        }

        return array_values($records);
    }

    /**
     * Liefert einen einzelnen Baustein.
     *
     * @param int $id
     * @return \stdClass
     */
    public static function get_type(int $id): \stdClass {
        global $DB;
        return $DB->get_record('local_kursassistent_types', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Legt einen neuen Baustein an oder aktualisiert einen bestehenden.
     *
     * @param \stdClass $data
     * @return int ID des Bausteins
     */
    public static function save_type(\stdClass $data): int {
        global $DB;

        if (!in_array($data->typ, self::TYPES, true)) {
            throw new \invalid_parameter_exception('Ungültiger Bausteintyp');
        }

        $now = time();

        if (!empty($data->id)) {
            $data->timemodified = $now;
            $DB->update_record('local_kursassistent_types', $data);
            return (int) $data->id;
        }

        $data->timecreated = $now;
        $data->timemodified = $now;
        if (empty($data->sortorder)) {
            $max = $DB->get_field_sql('SELECT MAX(sortorder) FROM {local_kursassistent_types}');
            $data->sortorder = ((int) $max) + 1;
        }
        return (int) $DB->insert_record('local_kursassistent_types', $data);
    }

    /**
     * Löscht einen Baustein.
     *
     * @param int $id
     */
    public static function delete_type(int $id): void {
        global $DB;
        $DB->delete_records('local_kursassistent_types', ['id' => $id]);
    }

    /**
     * Speichert die Reihenfolge der Bausteine.
     *
     * @param array $orderedids Liste von IDs in neuer Reihenfolge
     */
    public static function save_order(array $orderedids): void {
        global $DB;
        $position = 1;
        foreach ($orderedids as $id) {
            $DB->set_field('local_kursassistent_types', 'sortorder', $position, ['id' => (int) $id]);
            $position++;
        }
    }

    /**
     * Erzeugt ein Label und übernimmt eine über den Datei-Picker ausgewählte
     * Datei (Draft-Area) sauber in den Dateibereich des Labels. Funktioniert
     * unabhängig davon, aus welchem Repository die Datei stammt (lokaler
     * Upload, Nextcloud, OneDrive, PeerTube, ...).
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param \stdClass $type
     * @param int $draftitemid
     * @return int cmid des erzeugten Labels
     */
    public static function create_label_with_file(int $courseid, int $sectionnum, \stdClass $type, int $draftitemid): int {
        global $DB, $USER, $OUTPUT;

        // Schritt 1: Label mit Platzhaltertext anlegen, um cmid/Kontext zu bekommen.
        $platzhalter = \html_writer::tag('p', get_string('dateiwirdverarbeitet', 'local_kursassistent'), ['class' => 'text-muted']);
        $cmid = self::create_label($courseid, $sectionnum, $type, $platzhalter);

        $modcontext = \context_module::instance($cmid);
        $cm = get_coursemodule_from_id('label', $cmid, 0, false, MUST_EXIST);

        // Schritt 2: Datei aus der Draft-Area in den endgültigen Dateibereich des Labels übernehmen.
        file_save_draft_area_files($draftitemid, $modcontext->id, 'mod_label', 'intro', 0);

        $fs = get_file_storage();
        $files = $fs->get_area_files($modcontext->id, 'mod_label', 'intro', 0, 'id', false);
        $file = reset($files);

        $iconhtml = $OUTPUT->image_icon(self::normalize_icon($type->icon), $type->titel, 'local_kursassistent', [
            'class' => 'local-kursassistent-label-icon',
            'style' => 'width:22px;height:22px;vertical-align:middle;margin-right:4px;max-width:22px;max-height:22px;',
        ]);
        $header = \html_writer::tag('h4', $iconhtml . ' ' . s($type->titel));

        if ($file) {
            $url = \moodle_url::make_pluginfile_url(
                $modcontext->id, 'mod_label', 'intro', 0, $file->get_filepath(), $file->get_filename()
            );
            $mimetype = $file->get_mimetype();
            if ($mimetype && strpos($mimetype, 'image/') === 0) {
                $inhalt = \html_writer::empty_tag('img', [
                    'src' => $url->out(false),
                    'alt' => s($file->get_filename()),
                    'style' => 'max-width:100%;height:auto;',
                ]);
            } else {
                $inhalt = \html_writer::link($url, $file->get_filename(), ['target' => '_blank']);
            }
        } else {
            $inhalt = \html_writer::tag('p', get_string('dateifehlt', 'local_kursassistent'), ['class' => 'text-danger']);
        }

        $DB->set_field('label', 'intro', $header . $inhalt, ['id' => $cm->instance]);

        rebuild_course_cache($courseid, true);

        return $cmid;
    }

    /**
     * Erzeugt ein Text-Label in der angegebenen Kurssektion.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param \stdClass $type Baustein-Datensatz
     * @param string $inhalt Vom Nutzer eingegebener bzw. generierter HTML-Inhalt
     * @return int cmid des erzeugten Labels
     */
    public static function create_label(int $courseid, int $sectionnum, \stdClass $type, string $inhalt): int {
        global $CFG, $DB, $USER, $OUTPUT;

        require_once($CFG->dirroot . '/course/modlib.php');

        $course = get_course($courseid);

        $iconhtml = $OUTPUT->image_icon(self::normalize_icon($type->icon), $type->titel, 'local_kursassistent', [
            'class' => 'local-kursassistent-label-icon',
            'style' => 'width:22px;height:22px;vertical-align:middle;margin-right:4px;max-width:22px;max-height:22px;',
        ]);
        $header = \html_writer::tag('h4', $iconhtml . ' ' . s($type->titel));
        $fulltext = $header . $inhalt;

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = 'label';
        $moduleinfo->course = $course->id;
        $moduleinfo->section = $sectionnum;
        $moduleinfo->visible = 1;
        $moduleinfo->introeditor = [
            'text' => $fulltext,
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ];
        $moduleinfo->name = $type->titel;
        $moduleinfo->cmidnumber = '';
        $moduleinfo->groupmode = 0;
        $moduleinfo->groupingid = 0;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->completion = 0;

        $module = $DB->get_record('modules', ['name' => 'label'], '*', MUST_EXIST);
        $moduleinfo->module = $module->id;
        $moduleinfo->modulename = $module->name;

        $created = create_module($moduleinfo);

        $log = new \stdClass();
        $log->courseid = $courseid;
        $log->userid = $USER->id;
        $log->typeid = $type->id;
        $log->cmid = $created->coursemodule;
        $log->timecreated = time();
        $DB->insert_record('local_kursassistent_log', $log);

        return (int) $created->coursemodule;
    }

    /** Im Plugin gebündelte Icons (Dateiname ohne .svg unter pix/). */
    const ICONS = [
        'target-arrow', 'key', 'file', 'image', 'video', 'checklist', 'file-text',
        'bulb', 'alert-circle', 'list-numbers', 'clipboard-check', 'link',
    ];

    /**
     * Stellt sicher, dass ein Icon-Name tatsächlich als gebündeltes Icon existiert,
     * sonst Fallback auf "file-text".
     *
     * @param string $icon
     * @return string
     */
    public static function normalize_icon(string $icon): string {
        $icon = trim($icon);
        if (!in_array($icon, self::ICONS, true)) {
            return 'file-text';
        }
        return $icon;
    }
}
