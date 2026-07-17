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

namespace local_kursassistent\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use local_kursassistent\manager;
use local_kursassistent\video_helper;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/externallib.php');

/**
 * Globale External-Klasse (kein Namespace-Konflikt, analog block_questionfilter).
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_kursassistent_external extends external_api {

    /**
     * Parameter für get_peertube_videos.
     *
     * @return external_function_parameters
     */
    public static function get_peertube_videos_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
        ]);
    }

    /**
     * Liefert die PeerTube-Videoliste direkt über repository_peertubeoauth::get_listing(),
     * ohne den (für diese Kombination inkompatiblen) generischen Datei-Picker.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_peertube_videos(int $courseid): array {
        $params = self::validate_parameters(self::get_peertube_videos_parameters(), ['courseid' => $courseid]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        global $CFG, $DB;
        require_once($CFG->dirroot . '/repository/lib.php');

        if (!\core_component::get_component_directory('repository_peertubeoauth')) {
            return ['available' => false, 'videos' => []];
        }

        $coursecontext = \context_course::instance($params['courseid']);
        $systemcontext = \context_system::instance();

        $instanceid = $DB->get_field_sql(
            "SELECT ri.id
               FROM {repository_instances} ri
               JOIN {repository} r ON r.id = ri.typeid
              WHERE r.type = :type
                AND ri.contextid = :contextid",
            ['type' => 'peertubeoauth', 'contextid' => $coursecontext->id]
        );

        if (!$instanceid) {
            $instanceid = $DB->get_field_sql(
                "SELECT ri.id
                   FROM {repository_instances} ri
                   JOIN {repository} r ON r.id = ri.typeid
                  WHERE r.type = :type
                    AND ri.contextid = :contextid",
                ['type' => 'peertubeoauth', 'contextid' => $systemcontext->id]
            );
        }

        if (!$instanceid) {
            // Fallback: irgendeine vorhandene Instanz (z. B. eine andere Kursinstanz),
            // damit die Galerie auch dann funktioniert, wenn (noch) keine Instanz für
            // diesen konkreten Kurs oder auf Systemebene existiert.
            $instanceid = $DB->get_field_sql(
                "SELECT ri.id
                   FROM {repository_instances} ri
                   JOIN {repository} r ON r.id = ri.typeid
                  WHERE r.type = :type
               ORDER BY ri.id ASC",
                ['type' => 'peertubeoauth'],
                IGNORE_MULTIPLE
            );
        }

        if (!$instanceid) {
            return ['available' => false, 'videos' => []];
        }

        try {
            $repo = \repository::get_instance($instanceid);
            $listing = $repo->get_listing();
        } catch (\Exception $e) {
            return ['available' => false, 'videos' => []];
        }

        $videos = [];
        foreach ($listing['list'] ?? [] as $item) {
            $videos[] = [
                'titel' => $item['shorttitle'] ?? $item['title'] ?? '',
                'url' => $item['source'] ?? $item['url'] ?? '',
                'thumbnail' => $item['thumbnail'] ?? '',
            ];
        }

        return ['available' => true, 'videos' => $videos];
    }

    /**
     * Rückgabestruktur für get_peertube_videos.
     *
     * @return external_single_structure
     */
    public static function get_peertube_videos_returns(): external_single_structure {
        return new external_single_structure([
            'available' => new external_value(PARAM_BOOL, 'Repository verfügbar'),
            'videos' => new external_multiple_structure(new external_single_structure([
                'titel' => new external_value(PARAM_TEXT, 'Titel'),
                'url' => new external_value(PARAM_URL, 'Embed-URL'),
                'thumbnail' => new external_value(PARAM_RAW, 'Vorschaubild-URL', VALUE_DEFAULT, ''),
            ])),
        ]);
    }

    /**
     * Parameter für create_section.
     *
     * @return external_function_parameters
     */
    public static function create_section_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
            'name' => new external_value(PARAM_TEXT, 'Name des neuen Abschnitts', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Erzeugt einen neuen Kursabschnitt am Ende des Kurses.
     *
     * @param int $courseid
     * @param string $name
     * @return array
     */
    public static function create_section(int $courseid, string $name = ''): array {
        global $CFG;

        $params = self::validate_parameters(self::create_section_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        $course = get_course($params['courseid']);

        require_once($CFG->dirroot . '/course/lib.php');
        $section = course_create_section($course);

        $finalname = trim($params['name']);
        if ($finalname !== '') {
            course_update_section($course, $section, ['name' => $finalname]);
        } else {
            $finalname = get_section_name($course, $section);
        }

        return [
            'sectionnum' => (int) $section->section,
            'name' => $finalname,
        ];
    }

    /**
     * Rückgabestruktur für create_section.
     *
     * @return external_single_structure
     */
    public static function create_section_returns(): external_single_structure {
        return new external_single_structure([
            'sectionnum' => new external_value(PARAM_INT, 'Neue Abschnittsnummer'),
            'name' => new external_value(PARAM_TEXT, 'Name des neuen Abschnitts'),
        ]);
    }

    /**
     * Parameter für rename_section.
     *
     * @return external_function_parameters
     */
    public static function rename_section_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Abschnittsnummer'),
            'name' => new external_value(PARAM_TEXT, 'Neuer Abschnittsname'),
        ]);
    }

    /**
     * Benennt einen Kursabschnitt um.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $name
     * @return array
     */
    public static function rename_section(int $courseid, int $sectionnum, string $name): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::rename_section_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'name' => $name,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        $course = get_course($params['courseid']);
        $section = $DB->get_record('course_sections', [
            'course' => $params['courseid'],
            'section' => $params['sectionnum'],
        ], '*', MUST_EXIST);

        require_once($CFG->dirroot . '/course/lib.php');
        course_update_section($course, $section, ['name' => trim($params['name'])]);

        return ['success' => true, 'name' => trim($params['name'])];
    }

    /**
     * Rückgabestruktur für rename_section.
     *
     * @return external_single_structure
     */
    public static function rename_section_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Erfolgreich'),
            'name' => new external_value(PARAM_TEXT, 'Gespeicherter Name'),
        ]);
    }

    /**
     * Parameter für get_bausteine.
     *
     * @return external_function_parameters
     */
    public static function get_bausteine_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
        ]);
    }

    /**
     * Liefert aktive Bausteine inkl. Kurs-Abschnittsliste und Video-Status.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_bausteine(int $courseid): array {
        global $USER, $OUTPUT;

        $params = self::validate_parameters(self::get_bausteine_parameters(), ['courseid' => $courseid]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        $types = manager::get_types(true);
        $bausteine = [];
        foreach ($types as $t) {
            $iconname = manager::normalize_icon($t->icon);
            $bausteine[] = [
                'id' => (int) $t->id,
                'titel' => $t->titel,
                'iconurl' => $OUTPUT->image_url($iconname, 'local_kursassistent')->out(false),
                'typ' => $t->typ,
                'modname' => $t->modname ?? '',
                'platzhalter' => $t->platzhalter ?? '',
            ];
        }

        $course = get_course($params['courseid']);
        $modinfo = get_fast_modinfo($course);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $name = get_section_name($course, $section);
            $sections[] = [
                'sectionnum' => (int) $section->section,
                'name' => $name,
            ];
        }

        $videoaktiv = video_helper::is_active();
        $repoverfuegbar = $videoaktiv && video_helper::is_repository_verfuegbar();
        $uploadverfuegbar = $videoaktiv && video_helper::is_upload_plugin_verfuegbar();
        $channel = ['ready' => false, 'channelname' => null];
        if ($uploadverfuegbar) {
            $channel = video_helper::get_channel_status((int) $USER->id);
        }

        return [
            'bausteine' => $bausteine,
            'sections' => $sections,
            'videoaktiv' => $videoaktiv,
            'repoverfuegbar' => $repoverfuegbar,
            'uploadverfuegbar' => $uploadverfuegbar,
            'channelready' => (bool) $channel['ready'],
            'channelname' => $channel['channelname'] ?? '',
            'templatewizardverfuegbar' => (bool) \core_component::get_component_directory('local_coursetemplatewizard'),
        ];
    }

    /**
     * Rückgabestruktur für get_bausteine.
     *
     * @return external_single_structure
     */
    public static function get_bausteine_returns(): external_single_structure {
        return new external_single_structure([
            'bausteine' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'ID'),
                'titel' => new external_value(PARAM_TEXT, 'Titel'),
                'iconurl' => new external_value(PARAM_URL, 'Icon-URL'),
                'typ' => new external_value(PARAM_ALPHA, 'Typ'),
                'modname' => new external_value(PARAM_ALPHANUMEXT, 'Aktivitätstyp (nur bei typ=aktivitaet)', VALUE_DEFAULT, ''),
                'platzhalter' => new external_value(PARAM_RAW, 'Platzhalter-HTML'),
            ])),
            'sections' => new external_multiple_structure(new external_single_structure([
                'sectionnum' => new external_value(PARAM_INT, 'Abschnittsnummer'),
                'name' => new external_value(PARAM_TEXT, 'Abschnittsname'),
            ])),
            'videoaktiv' => new external_value(PARAM_BOOL, 'Video-Funktion aktiv'),
            'repoverfuegbar' => new external_value(PARAM_BOOL, 'Verlinkung verfügbar'),
            'uploadverfuegbar' => new external_value(PARAM_BOOL, 'Upload verfügbar'),
            'channelready' => new external_value(PARAM_BOOL, 'Kanal bereit'),
            'channelname' => new external_value(PARAM_TEXT, 'Kanalname', VALUE_DEFAULT, ''),
            'templatewizardverfuegbar' => new external_value(PARAM_BOOL, 'local_coursetemplatewizard installiert'),
        ]);
    }

    /**
     * Parameter für insert_bausteine.
     *
     * @return external_function_parameters
     */
    public static function insert_bausteine_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Zielabschnitt'),
            'auswahl' => new external_multiple_structure(new external_single_structure([
                'typeid' => new external_value(PARAM_INT, 'Baustein-ID'),
                'inhalt' => new external_value(PARAM_RAW, 'Eingegebener bzw. generierter Inhalt', VALUE_DEFAULT, ''),
            ])),
        ]);
    }

    /**
     * Fügt die ausgewählten Bausteine als Labels in den Kurs ein.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param array $auswahl
     * @return array
     */
    public static function insert_bausteine(int $courseid, int $sectionnum, array $auswahl): array {
        $params = self::validate_parameters(self::insert_bausteine_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'auswahl' => $auswahl,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        $erzeugt = [];
        foreach ($params['auswahl'] as $item) {
            $type = manager::get_type((int) $item['typeid']);
            $cmid = manager::create_label(
                $params['courseid'],
                $params['sectionnum'],
                $type,
                $item['inhalt'] ?? ''
            );
            $erzeugt[] = ['typeid' => (int) $type->id, 'cmid' => $cmid];
        }

        rebuild_course_cache($params['courseid'], true);

        return ['erzeugt' => $erzeugt];
    }

    /**
     * Rückgabestruktur für insert_bausteine.
     *
     * @return external_single_structure
     */
    public static function insert_bausteine_returns(): external_single_structure {
        return new external_single_structure([
            'erzeugt' => new external_multiple_structure(new external_single_structure([
                'typeid' => new external_value(PARAM_INT, 'Baustein-ID'),
                'cmid' => new external_value(PARAM_INT, 'Erzeugtes Kursmodul'),
            ])),
        ]);
    }
}
