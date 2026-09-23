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

        global $USER;

        // Reihenfolge ist entscheidend: Die persoenliche Instanz im Nutzerkontext traegt den
        // Kanalnamen der Lehrkraft und zeigt deren eigene Videos. Instanzen im Kurs- oder
        // Systemkontext haben in der Regel keinen Kanalnamen und liefern stattdessen alle
        // Videos des Moderator-Kontos.
        $kontexte = [
            \context_user::instance($USER->id)->id,
            \context_course::instance($params['courseid'])->id,
            \context_system::instance()->id,
        ];

        $instanceid = null;
        foreach ($kontexte as $kontextid) {
            $instanceid = $DB->get_field_sql(
                "SELECT ri.id
                   FROM {repository_instances} ri
                   JOIN {repository} r ON r.id = ri.typeid
                  WHERE r.type = :type
                    AND ri.contextid = :contextid
               ORDER BY ri.id ASC",
                ['type' => 'peertubeoauth', 'contextid' => $kontextid],
                IGNORE_MULTIPLE
            );
            if ($instanceid) {
                break;
            }
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
            'vorlagenverfuegbar' => \local_kursassistent\vorlagen_manager::is_aktiv(),
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
            'vorlagenverfuegbar' => new external_value(PARAM_BOOL, 'Kursvorlagen konfiguriert'),
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

    // Abschlussverfolgung & Voraussetzungen.

    /**
     * Parameter für get_course_activities.
     *
     * @return external_function_parameters
     */
    public static function get_course_activities_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
        ]);
    }

    /**
     * Liefert alle sichtbaren Aktivitäten eines Kurses mit Abschluss- und Voraussetzungsinformationen.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_course_activities(int $courseid): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::get_course_activities_parameters(), ['courseid' => $courseid]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        require_once($CFG->dirroot . '/course/lib.php');

        $course = get_course($params['courseid']);
        $modinfo = get_fast_modinfo($course);

        // Prüfen, ob Abschlussverfolgung im Kurs aktiviert ist.
        $completionenabled = !empty($course->enablecompletion);

        // Abschnittssichtbarkeit ermitteln.
        $sectionvismap = [];
        foreach ($modinfo->get_section_info_all() as $si) {
            $sectionvismap[(int) $si->section] = (int) $si->visible;
        }

        // Abschnitte sammeln (inkl. Sichtbarkeit).
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $si) {
            $sections[] = [
                'sectionnum' => (int) $si->section,
                'sectionname' => get_section_name($course, $si),
                'sectionvisible' => (int) $si->visible,
            ];
        }

        $activities = [];
        foreach ($modinfo->get_cms() as $cm) {
            // Labels überspringen, aber versteckte Module einbeziehen.
            if ($cm->modname === 'label') {
                continue;
            }

            $restrictions = self::parse_availability($cm->availability, $modinfo);

            // Aktivitätsspezifisches Feld completionsubmit auslesen, falls vorhanden.
            $completionsubmit = 0;
            $actcompfields = [
                'assign' => 'completionsubmit',
                'choice' => 'completionsubmit',
                'feedback' => 'completionsubmit',
                'workshop' => 'completionsubmit',
                'survey' => 'completionsubmit',
            ];
            if (isset($actcompfields[$cm->modname])) {
                $actrecord = $DB->get_record(
                    $cm->modname,
                    ['id' => $cm->instance],
                    $actcompfields[$cm->modname],
                    IGNORE_MISSING
                );
                if ($actrecord) {
                    $field = $actcompfields[$cm->modname];
                    $completionsubmit = !empty($actrecord->$field) ? 1 : 0;
                }
            }

            // Completiongradeitemnumber: NULL = keine Bewertung nötig, 0 = Bewertung erhalten.
            $completiongrade = (!is_null($cm->completiongradeitemnumber)) ? 1 : 0;

            $activities[] = [
                'cmid' => (int) $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
                'sectionnum' => (int) $cm->sectionnum,
                'sectionname' => get_section_name($course, $cm->sectionnum),
                'completion' => (int) $cm->completion,
                'completionview' => !empty($cm->completionview) ? 1 : 0,
                'completiongrade' => $completiongrade,
                'completionpassgrade' => !empty($cm->completionpassgrade) ? 1 : 0,
                'completionsubmit' => $completionsubmit,
                'restrictions' => $restrictions,
                'availabilityjson' => $cm->availability ?? '',
                'visible' => (int) $cm->visible,
                'sectionvisible' => $sectionvismap[(int) $cm->sectionnum] ?? 1,
            ];
        }

        return [
            'completionenabled' => $completionenabled,
            'activities' => $activities,
            'sections' => $sections,
        ];
    }

    /**
     * Parst das Availability-JSON einer Aktivität in eine lesbare Voraussetzungsliste.
     *
     * @param string|null $availability JSON-String oder null
     * @param \course_modinfo $modinfo
     * @return array
     */
    private static function parse_availability(?string $availability, \course_modinfo $modinfo): array {
        if (empty($availability)) {
            return [];
        }

        $data = json_decode($availability, true);
        if (empty($data) || empty($data['c'])) {
            return [];
        }

        $restrictions = [];
        foreach ($data['c'] as $condition) {
            if (!isset($condition['type'])) {
                continue;
            }
            if ($condition['type'] === 'completion' && isset($condition['cm'])) {
                $targetcm = (int) $condition['cm'];
                $expectedstate = (int) ($condition['e'] ?? 1);
                $targetname = '';
                try {
                    $cminfo = $modinfo->get_cm($targetcm);
                    $targetname = $cminfo->name;
                } catch (\Exception $e) {
                    $targetname = 'ID ' . $targetcm;
                }
                $restrictions[] = [
                    'type' => 'completion',
                    'cmid' => $targetcm,
                    'cmname' => $targetname,
                    'expectedstate' => $expectedstate,
                    'description' => $targetname,
                ];
            } else if ($condition['type'] === 'date') {
                $restrictions[] = [
                    'type' => 'date',
                    'cmid' => 0,
                    'cmname' => '',
                    'expectedstate' => 0,
                    'description' => !empty($condition['t'])
                        ? userdate($condition['t'], get_string('strftimedatefull'))
                        : '',
                ];
            } else if ($condition['type'] === 'grade') {
                // Grade-Conditions nutzen 'id' als CM-ID (Moodle-Konvention).
                $gradecmid = (int) ($condition['id'] ?? $condition['cm'] ?? 0);
                $gradecmname = '';
                if ($gradecmid > 0) {
                    try {
                        $cminfo = $modinfo->get_cm($gradecmid);
                        $gradecmname = $cminfo->name;
                    } catch (\Exception $e) {
                        $gradecmname = 'ID ' . $gradecmid;
                    }
                }
                $mindesc = !empty($condition['min']) ? '>= ' . round($condition['min'], 1) . '%' : '';
                $restrictions[] = [
                    'type' => 'grade',
                    'cmid' => $gradecmid,
                    'cmname' => $gradecmname,
                    'expectedstate' => 0,
                    'description' => $gradecmname . ($mindesc ? ' ' . $mindesc : ''),
                ];
            } else {
                // Andere Typen pauschal aufnehmen.
                $restrictions[] = [
                    'type' => $condition['type'],
                    'cmid' => 0,
                    'cmname' => '',
                    'expectedstate' => 0,
                    'description' => $condition['type'],
                ];
            }
        }

        return $restrictions;
    }

    /**
     * Rückgabestruktur für get_course_activities.
     *
     * @return external_single_structure
     */
    public static function get_course_activities_returns(): external_single_structure {
        return new external_single_structure([
            'completionenabled' => new external_value(PARAM_BOOL, 'Abschlussverfolgung im Kurs aktiv'),
            'activities' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Kursmodul-ID'),
                'name' => new external_value(PARAM_TEXT, 'Aktivitätsname'),
                'modname' => new external_value(PARAM_ALPHANUMEXT, 'Modultyp'),
                'sectionnum' => new external_value(PARAM_INT, 'Abschnittsnummer'),
                'sectionname' => new external_value(PARAM_TEXT, 'Abschnittsname'),
                'completion' => new external_value(PARAM_INT, '0=keine, 1=manuell, 2=automatisch'),
                'completionview' => new external_value(PARAM_INT, 'Abschluss bei Anzeige'),
                'completiongrade' => new external_value(PARAM_INT, 'Bewertung erhalten'),
                'completionpassgrade' => new external_value(PARAM_INT, 'Bestehensgrenze erreicht'),
                'completionsubmit' => new external_value(PARAM_INT, 'Abschluss bei Abgabe'),
                'restrictions' => new external_multiple_structure(new external_single_structure([
                    'type' => new external_value(PARAM_ALPHANUMEXT, 'Bedingungstyp'),
                    'cmid' => new external_value(PARAM_INT, 'Ziel-CM-ID'),
                    'cmname' => new external_value(PARAM_TEXT, 'Ziel-Aktivitätsname'),
                    'expectedstate' => new external_value(PARAM_INT, 'Erwarteter Zustand'),
                    'description' => new external_value(PARAM_TEXT, 'Lesbare Beschreibung'),
                ])),
                'availabilityjson' => new external_value(PARAM_RAW, 'Availability-JSON'),
                'visible' => new external_value(PARAM_INT, 'Aktivität sichtbar (0/1)'),
                'sectionvisible' => new external_value(PARAM_INT, 'Abschnitt sichtbar (0/1)'),
            ])),
            'sections' => new external_multiple_structure(new external_single_structure([
                'sectionnum' => new external_value(PARAM_INT, 'Abschnittsnummer'),
                'sectionname' => new external_value(PARAM_TEXT, 'Abschnittsname'),
                'sectionvisible' => new external_value(PARAM_INT, 'Abschnitt sichtbar (0/1)'),
            ])),
        ]);
    }

    /**
     * Parameter für set_completion.
     *
     * @return external_function_parameters
     */
    public static function set_completion_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
            'updates' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Kursmodul-ID'),
                'completion' => new external_value(PARAM_INT, '0=keine, 1=manuell, 2=automatisch'),
                'completionview' => new external_value(PARAM_INT, 'Abschluss bei Ansicht (0/1)', VALUE_DEFAULT, -1),
                'completionpassgrade' => new external_value(PARAM_INT, 'Abschluss bei Bestehen (0/1)', VALUE_DEFAULT, -1),
                'completionsubmit' => new external_value(PARAM_INT, 'Abschluss bei Abgabe (0/1)', VALUE_DEFAULT, -1),
                'completiongrade' => new external_value(PARAM_INT, 'Bewertung erhalten (0/1)', VALUE_DEFAULT, -1),
            ])),
        ]);
    }

    /**
     * Setzt die Abschlussverfolgung für eine oder mehrere Aktivitäten.
     *
     * @param int $courseid
     * @param array $updates
     * @return array
     */
    public static function set_completion(int $courseid, array $updates): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::set_completion_parameters(), [
            'courseid' => $courseid,
            'updates' => $updates,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        require_once($CFG->dirroot . '/course/lib.php');

        $course = get_course($params['courseid']);

        // Abschlussverfolgung muss im Kurs aktiviert sein.
        if (empty($course->enablecompletion)) {
            return [
                'success' => false,
                'message' => get_string('completion_not_enabled', 'local_kursassistent'),
                'updated' => [],
            ];
        }

        $updated = [];
        foreach ($params['updates'] as $upd) {
            $cmid = (int) $upd['cmid'];
            $completion = (int) $upd['completion'];

            // Gültige Werte: 0, 1, 2.
            if ($completion < 0 || $completion > 2) {
                continue;
            }

            $cm = $DB->get_record('course_modules', ['id' => $cmid, 'course' => $params['courseid']], '*', IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $cm->completion = $completion;

            $completionview = isset($upd['completionview']) ? (int) $upd['completionview'] : -1;
            $completionpassgrade = isset($upd['completionpassgrade']) ? (int) $upd['completionpassgrade'] : -1;
            $completionsubmit = isset($upd['completionsubmit']) ? (int) $upd['completionsubmit'] : -1;
            $completiongrade = isset($upd['completiongrade']) ? (int) $upd['completiongrade'] : -1;

            if ($completion == 2) {
                // Automatischer Abschluss: Sub-Optionen setzen.
                // -1 = nicht mitgesendet → Standardverhalten (bei Ansicht aktivieren).
                $cm->completionview = ($completionview >= 0) ? $completionview : 1;
                $cm->completionpassgrade = ($completionpassgrade >= 0) ? $completionpassgrade : 0;

                // Completiongradeitemnumber: 0 = Bewertung erhalten, NULL = keine Bewertung nötig.
                if ($completiongrade >= 0) {
                    $cm->completiongradeitemnumber = $completiongrade ? 0 : null;
                }
            } else {
                // Manuell oder keine: Sub-Optionen deaktivieren.
                $cm->completionview = 0;
                $cm->completionpassgrade = 0;
                $cm->completiongradeitemnumber = null;
                $completionsubmit = 0; // Auch aktivitätsspezifische Felder deaktivieren.
            }
            $DB->update_record('course_modules', $cm);

            // Aktivitätsspezifische Abschlussbedingungen setzen/zurücksetzen.
            $modinfo = get_fast_modinfo($course);
            try {
                $cminfo = $modinfo->get_cm($cmid);
                self::set_activity_completion_fields($DB, $cminfo->modname, $cminfo->instance, $completionsubmit);
            } catch (\Exception $e) {
                // Modulinfo nicht verfügbar – kein Abbruch, Fehler wird protokolliert.
                debugging(
                    'set_completion: Modulinfo fuer cmid ' . $cmid . ' nicht verfuegbar: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }

            $updated[] = [
                'cmid' => $cmid,
                'completion' => $completion,
                'completionview' => (int) $cm->completionview,
                'completionpassgrade' => (int) $cm->completionpassgrade,
                'completiongrade' => is_null($cm->completiongradeitemnumber) ? 0 : 1,
            ];
        }

        rebuild_course_cache($params['courseid'], true);

        return [
            'success' => true,
            'message' => '',
            'updated' => $updated,
        ];
    }

    /**
     * Rückgabestruktur für set_completion.
     *
     * @return external_single_structure
     */
    public static function set_completion_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Erfolgreich'),
            'message' => new external_value(PARAM_TEXT, 'Hinweistext', VALUE_DEFAULT, ''),
            'updated' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Kursmodul-ID'),
                'completion' => new external_value(PARAM_INT, 'Neuer Completion-Wert'),
                'completionview' => new external_value(PARAM_INT, 'Abschluss bei Ansicht'),
                'completionpassgrade' => new external_value(PARAM_INT, 'Abschluss bei Bestehen'),
                'completiongrade' => new external_value(PARAM_INT, 'Bewertung erhalten'),
            ])),
        ]);
    }

    /**
     * Setzt aktivitätsspezifische Abschlussfelder (z. B. completionsubmit bei assign).
     *
     * Wenn $completionsubmit >= 0 ist, wird das Feld auf diesen Wert gesetzt.
     * Bei -1 (nicht mitgesendet) wird es auf 0 zurückgesetzt, damit keine
     * ungewollten Bedingungen aus der Standardkonfiguration aktiv bleiben.
     *
     * @param \moodle_database $db
     * @param string $modname Modultyp (z. B. 'assign', 'quiz')
     * @param int $instance Instanz-ID in der Modultabelle
     * @param int $completionsubmit Gewünschter Wert für completionsubmit (-1 = zurücksetzen)
     */
    private static function set_activity_completion_fields(
        \moodle_database $db,
        string $modname,
        int $instance,
        int $completionsubmit
    ): void {
        // Bekannte aktivitätsspezifische Abschlussfelder je Modultyp.
        // Completionsubmit wird explizit gesteuert, alle anderen auf 0 zurueckgesetzt.
        $submitfields = [
            'assign' => 'completionsubmit',
            'choice' => 'completionsubmit',
            'feedback' => 'completionsubmit',
            'workshop' => 'completionsubmit',
            'survey' => 'completionsubmit',
        ];
        $resetfields = [
            'quiz' => ['completionattempts', 'completionminattempts'],
            'forum' => ['completionposts', 'completiondiscussions', 'completionreplies'],
            'data' => ['completionentries'],
            'glossary' => ['completionentries'],
            'lesson' => ['completionendreached', 'completiontimespent'],
            'wiki' => ['completionedits'],
            'scorm' => ['completionstatusrequired', 'completionscorerequired', 'completionstatusallscos'],
        ];

        $hassubmit = isset($submitfields[$modname]);
        $hasreset = isset($resetfields[$modname]);

        if (!$hassubmit && !$hasreset) {
            return;
        }

        $record = $db->get_record($modname, ['id' => $instance], '*', IGNORE_MISSING);
        if (!$record) {
            return;
        }

        $changed = false;

        // Completionsubmit-Feld explizit setzen.
        if ($hassubmit) {
            $field = $submitfields[$modname];
            if (property_exists($record, $field)) {
                $newval = ($completionsubmit >= 0) ? $completionsubmit : 0;
                if ((int) $record->$field !== $newval) {
                    $record->$field = $newval;
                    $changed = true;
                }
            }
        }

        // Weitere aktivitätsspezifische Felder zurücksetzen (z. B. quiz, forum).
        if ($hasreset) {
            foreach ($resetfields[$modname] as $field) {
                if (property_exists($record, $field) && (int) $record->$field !== 0) {
                    $record->$field = 0;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $db->update_record($modname, $record);
        }
    }

    /**
     * Parameter für set_restriction.
     *
     * @return external_function_parameters
     */
    public static function set_restriction_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
            'cmid' => new external_value(PARAM_INT, 'Kursmodul-ID, die eingeschränkt wird'),
            'requiredcmid' => new external_value(PARAM_INT, 'Kursmodul-ID fuer die Bedingung (0 zum Entfernen)'),
            'restrictiontype' => new external_value(
                PARAM_ALPHA,
                'Bedingungstyp: completion oder grade',
                VALUE_DEFAULT,
                'completion'
            ),
            'mingrade' => new external_value(PARAM_FLOAT, 'Mindest-Bewertung in Prozent (nur bei grade)', VALUE_DEFAULT, 50.0),
        ]);
    }

    /**
     * Setzt oder entfernt eine Abschluss-Voraussetzung für eine Aktivität.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $requiredcmid
     * @param string $restrictiontype Bedingungstyp: completion oder grade
     * @param float $mingrade Mindestbewertung in Prozent, nur bei grade
     * @return array
     */
    public static function set_restriction(
        int $courseid,
        int $cmid,
        int $requiredcmid,
        string $restrictiontype = 'completion',
        float $mingrade = 50.0
    ): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::set_restriction_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'requiredcmid' => $requiredcmid,
            'restrictiontype' => $restrictiontype,
            'mingrade' => $mingrade,
        ]);

        // Nur erlaubte Typen.
        if (!in_array($params['restrictiontype'], ['completion', 'grade'])) {
            throw new \invalid_parameter_exception('restrictiontype must be completion or grade');
        }

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        require_once($CFG->dirroot . '/course/lib.php');

        $cm = $DB->get_record('course_modules', [
            'id' => $params['cmid'],
            'course' => $params['courseid'],
        ], '*', MUST_EXIST);

        if ($params['requiredcmid'] > 0) {
            // Prüfen, ob das Ziel-CM existiert.
            $DB->get_record('course_modules', [
                'id' => $params['requiredcmid'],
                'course' => $params['courseid'],
            ], 'id', MUST_EXIST);

            if ($params['restrictiontype'] === 'grade') {
                $availability = self::add_grade_condition(
                    $cm->availability,
                    $params['requiredcmid'],
                    $params['mingrade']
                );
            } else {
                $availability = self::add_completion_condition(
                    $cm->availability,
                    $params['requiredcmid']
                );
            }
        } else {
            // Bedingungen des jeweiligen Typs entfernen.
            if ($params['restrictiontype'] === 'grade') {
                $availability = self::remove_grade_conditions($cm->availability);
            } else {
                $availability = self::remove_completion_conditions($cm->availability);
            }
        }

        $cm->availability = $availability;
        $DB->update_record('course_modules', $cm);

        rebuild_course_cache($params['courseid'], true);

        return ['success' => true, 'availability' => $availability ?? ''];
    }

    /**
     * Fügt eine Abschluss-Bedingung zum Availability-JSON hinzu.
     *
     * @param string|null $existing Bestehendes Availability-JSON
     * @param int $requiredcmid CM-ID, die abgeschlossen sein muss
     * @return string Neues Availability-JSON
     */
    private static function add_completion_condition(?string $existing, int $requiredcmid): string {
        $newcondition = ['type' => 'completion', 'cm' => $requiredcmid, 'e' => 1];

        if (empty($existing)) {
            return json_encode([
                'op' => '&',
                'c' => [$newcondition],
                'showc' => [true],
            ]);
        }

        $data = json_decode($existing, true);
        if (empty($data) || !isset($data['c'])) {
            return json_encode([
                'op' => '&',
                'c' => [$newcondition],
                'showc' => [true],
            ]);
        }

        // Bestehende Completion-Bedingungen für dasselbe Ziel entfernen.
        $filtered = [];
        $showc = [];
        foreach ($data['c'] as $idx => $cond) {
            if ($cond['type'] === 'completion' && ($cond['cm'] ?? 0) == $requiredcmid) {
                continue;
            }
            $filtered[] = $cond;
            $showc[] = $data['showc'][$idx] ?? true;
        }

        $filtered[] = $newcondition;
        $showc[] = true;

        $data['c'] = $filtered;
        $data['showc'] = $showc;

        return json_encode($data);
    }

    /**
     * Entfernt alle Completion-Bedingungen aus dem Availability-JSON.
     *
     * @param string|null $existing
     * @return string|null
     */
    private static function remove_completion_conditions(?string $existing): ?string {
        if (empty($existing)) {
            return null;
        }

        $data = json_decode($existing, true);
        if (empty($data) || empty($data['c'])) {
            return null;
        }

        $filtered = [];
        $showc = [];
        foreach ($data['c'] as $idx => $cond) {
            if (($cond['type'] ?? '') === 'completion') {
                continue;
            }
            $filtered[] = $cond;
            $showc[] = $data['showc'][$idx] ?? true;
        }

        if (empty($filtered)) {
            return null;
        }

        $data['c'] = $filtered;
        $data['showc'] = $showc;

        return json_encode($data);
    }

    /**
     * Fügt eine Bewertungs-Bedingung zum Availability-JSON hinzu.
     *
     * @param string|null $existing Bestehendes Availability-JSON
     * @param int $requiredcmid CM-ID, deren Bewertung erforderlich ist
     * @param float $mingrade Mindest-Bewertung in Prozent
     * @return string Neues Availability-JSON
     */
    private static function add_grade_condition(?string $existing, int $requiredcmid, float $mingrade): string {
        $newcondition = ['type' => 'grade', 'id' => $requiredcmid, 'min' => $mingrade];

        if (empty($existing)) {
            return json_encode([
                'op' => '&',
                'c' => [$newcondition],
                'showc' => [true],
            ]);
        }

        $data = json_decode($existing, true);
        if (empty($data) || !isset($data['c'])) {
            return json_encode([
                'op' => '&',
                'c' => [$newcondition],
                'showc' => [true],
            ]);
        }

        // Bestehende Grade-Bedingungen für dasselbe Ziel entfernen.
        $filtered = [];
        $showc = [];
        foreach ($data['c'] as $idx => $cond) {
            if ($cond['type'] === 'grade' && ($cond['id'] ?? 0) == $requiredcmid) {
                continue;
            }
            $filtered[] = $cond;
            $showc[] = $data['showc'][$idx] ?? true;
        }

        $filtered[] = $newcondition;
        $showc[] = true;

        $data['c'] = $filtered;
        $data['showc'] = $showc;

        return json_encode($data);
    }

    /**
     * Entfernt alle Grade-Bedingungen aus dem Availability-JSON.
     *
     * @param string|null $existing
     * @return string|null
     */
    private static function remove_grade_conditions(?string $existing): ?string {
        if (empty($existing)) {
            return null;
        }

        $data = json_decode($existing, true);
        if (empty($data) || empty($data['c'])) {
            return null;
        }

        $filtered = [];
        $showc = [];
        foreach ($data['c'] as $idx => $cond) {
            if (($cond['type'] ?? '') === 'grade') {
                continue;
            }
            $filtered[] = $cond;
            $showc[] = $data['showc'][$idx] ?? true;
        }

        if (empty($filtered)) {
            return null;
        }

        $data['c'] = $filtered;
        $data['showc'] = $showc;

        return json_encode($data);
    }

    /**
     * Rückgabestruktur für set_restriction.
     *
     * @return external_single_structure
     */
    public static function set_restriction_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Erfolgreich'),
            'availability' => new external_value(PARAM_RAW, 'Neues Availability-JSON'),
        ]);
    }

    /**
     * Parameter für create_lernpfad.
     *
     * @return external_function_parameters
     */
    public static function create_lernpfad_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Kursmodul-ID'),
                'Geordnete Liste der CM-IDs für den Lernpfad'
            ),
            'setcompletion' => new external_value(
                PARAM_BOOL,
                'Abschlussverfolgung automatisch aktivieren, falls nötig',
                VALUE_DEFAULT,
                true
            ),
            'completiontype' => new external_value(
                PARAM_ALPHA,
                'Abschlussart: manual, view, passgrade',
                VALUE_DEFAULT,
                'manual'
            ),
            'chaintype' => new external_value(
                PARAM_ALPHA,
                'Verkettungstyp: completion oder grade',
                VALUE_DEFAULT,
                'completion'
            ),
            'mingrade' => new external_value(
                PARAM_FLOAT,
                'Mindestbewertung in % (nur bei chaintype=grade)',
                VALUE_DEFAULT,
                50.0
            ),
        ]);
    }

    /**
     * Erzeugt einen linearen Lernpfad: Jede Aktivität erfordert den Abschluss der vorherigen.
     *
     * @param int $courseid
     * @param array $cmids
     * @param bool $setcompletion
     * @param string $completiontype Art des Abschlusses: manual oder automatic
     * @param string $chaintype Verkettungsart: completion oder grade
     * @param float $mingrade Mindestbewertung in Prozent, nur bei chaintype grade
     * @return array
     */
    public static function create_lernpfad(
        int $courseid,
        array $cmids,
        bool $setcompletion = true,
        string $completiontype = 'manual',
        string $chaintype = 'completion',
        float $mingrade = 50.0
    ): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::create_lernpfad_parameters(), [
            'courseid' => $courseid,
            'cmids' => $cmids,
            'setcompletion' => $setcompletion,
            'completiontype' => $completiontype,
            'chaintype' => $chaintype,
            'mingrade' => $mingrade,
        ]);

        if (!in_array($params['completiontype'], ['manual', 'view', 'passgrade'])) {
            $params['completiontype'] = 'manual';
        }
        if (!in_array($params['chaintype'], ['completion', 'grade'])) {
            $params['chaintype'] = 'completion';
        }

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        require_once($CFG->dirroot . '/course/lib.php');

        $course = get_course($params['courseid']);

        if (empty($course->enablecompletion)) {
            return [
                'success' => false,
                'message' => get_string('completion_not_enabled', 'local_kursassistent'),
                'verknuepfungen' => [],
            ];
        }

        if (count($params['cmids']) < 2) {
            return [
                'success' => false,
                'message' => get_string('lernpfad_mindestens_zwei', 'local_kursassistent'),
                'verknuepfungen' => [],
            ];
        }

        $verknuepfungen = [];

        for ($i = 1; $i < count($params['cmids']); $i++) {
            $vorhercmid = (int) $params['cmids'][$i - 1];
            $aktcmid = (int) $params['cmids'][$i];

            // Vorherige Aktivität: ggf. Abschluss aktivieren.
            if ($params['setcompletion']) {
                $vorhercm = $DB->get_record('course_modules', [
                    'id' => $vorhercmid,
                    'course' => $params['courseid'],
                ], '*', IGNORE_MISSING);
                if ($vorhercm && (int) $vorhercm->completion === 0) {
                    switch ($params['completiontype']) {
                        case 'view':
                            $vorhercm->completion = 2;
                            $vorhercm->completionview = 1;
                            $vorhercm->completionpassgrade = 0;
                            break;
                        case 'passgrade':
                            $vorhercm->completion = 2;
                            $vorhercm->completionview = 0;
                            $vorhercm->completionpassgrade = 1;
                            break;
                        default: // Manual (Manueller Abschluss).
                            $vorhercm->completion = 1;
                            $vorhercm->completionview = 0;
                            $vorhercm->completionpassgrade = 0;
                            break;
                    }
                    $DB->update_record('course_modules', $vorhercm);

                    // Aktivitätsspezifische Abschlussfelder zurücksetzen.
                    try {
                        $cminfo = $modinfo->get_cm($vorhercmid);
                        self::set_activity_completion_fields($DB, $cminfo->modname, $cminfo->instance, 0);
                    } catch (\Exception $e) {
                        // Kein Abbruch, Fehler wird protokolliert.
                        debugging('set_restriction: Modulinfo fuer cmid ' . $vorhercmid . ' nicht verfuegbar: '
                            . $e->getMessage(), DEBUG_DEVELOPER);
                    }
                }
            }

            // Aktuelle Aktivität: Voraussetzung auf vorherige setzen.
            $cm = $DB->get_record('course_modules', [
                'id' => $aktcmid,
                'course' => $params['courseid'],
            ], '*', IGNORE_MISSING);

            if (!$cm) {
                continue;
            }

            if ($params['chaintype'] === 'grade') {
                $cm->availability = self::add_grade_condition(
                    $cm->availability,
                    $vorhercmid,
                    $params['mingrade']
                );
            } else {
                $cm->availability = self::add_completion_condition($cm->availability, $vorhercmid);
            }
            $DB->update_record('course_modules', $cm);

            $verknuepfungen[] = [
                'cmid' => $aktcmid,
                'requiredcmid' => $vorhercmid,
            ];
        }

        rebuild_course_cache($params['courseid'], true);

        return [
            'success' => true,
            'message' => '',
            'verknuepfungen' => $verknuepfungen,
        ];
    }

    /**
     * Rückgabestruktur für create_lernpfad.
     *
     * @return external_single_structure
     */
    public static function create_lernpfad_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Erfolgreich'),
            'message' => new external_value(PARAM_TEXT, 'Hinweistext', VALUE_DEFAULT, ''),
            'verknuepfungen' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Kursmodul-ID'),
                'requiredcmid' => new external_value(PARAM_INT, 'Vorausgesetzte CM-ID'),
            ])),
        ]);
    }

    // Sichtbarkeitssteuerung.

    /**
     * Parameter für set_visibility.
     *
     * @return external_function_parameters
     */
    public static function set_visibility_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
            'updates' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'ID (cmid bei activity, sectionnum bei section)'),
                'type' => new external_value(PARAM_ALPHA, 'activity oder section'),
                'visible' => new external_value(PARAM_INT, 'Sichtbar (0 oder 1)'),
            ])),
        ]);
    }

    /**
     * Setzt die Sichtbarkeit von Aktivitäten und/oder Kursabschnitten.
     *
     * @param int $courseid
     * @param array $updates
     * @return array
     */
    public static function set_visibility(int $courseid, array $updates): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::set_visibility_parameters(), [
            'courseid' => $courseid,
            'updates' => $updates,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        require_once($CFG->dirroot . '/course/lib.php');

        $course = get_course($params['courseid']);
        $updated = [];

        foreach ($params['updates'] as $upd) {
            $visible = (int) $upd['visible'] ? 1 : 0;

            if ($upd['type'] === 'activity') {
                $cmid = (int) $upd['id'];
                $cm = $DB->get_record('course_modules', [
                    'id' => $cmid,
                    'course' => $params['courseid'],
                ], '*', IGNORE_MISSING);
                if (!$cm) {
                    continue;
                }
                set_coursemodule_visible($cmid, $visible);
                $updated[] = ['id' => $cmid, 'type' => 'activity', 'visible' => $visible];
            } else if ($upd['type'] === 'section') {
                $sectionnum = (int) $upd['id'];
                $section = $DB->get_record('course_sections', [
                    'course' => $params['courseid'],
                    'section' => $sectionnum,
                ], '*', IGNORE_MISSING);
                if (!$section) {
                    continue;
                }
                course_update_section($course, $section, ['visible' => $visible]);
                $updated[] = ['id' => $sectionnum, 'type' => 'section', 'visible' => $visible];
            }
        }

        rebuild_course_cache($params['courseid'], true);

        return ['success' => true, 'updated' => $updated];
    }

    /**
     * Rückgabestruktur für set_visibility.
     *
     * @return external_single_structure
     */
    public static function set_visibility_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Erfolgreich'),
            'updated' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'ID'),
                'type' => new external_value(PARAM_ALPHA, 'activity oder section'),
                'visible' => new external_value(PARAM_INT, 'Neuer Sichtbarkeitswert'),
            ])),
        ]);
    }

    // Abschnittsvorlagen.

    /**
     * Parameter für get_section_templates.
     *
     * @return external_function_parameters
     */
    public static function get_section_templates_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID (für Kontextprüfung)'),
        ]);
    }

    /**
     * Liefert alle verfügbaren Abschnittsvorlagen (globale + eigene).
     *
     * @param int $courseid
     * @return array
     */
    public static function get_section_templates(int $courseid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_section_templates_parameters(), ['courseid' => $courseid]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        $canmanage = has_capability('local/kursassistent:manage', \context_system::instance());

        // Globale Vorlagen (userid=0) + eigene Vorlagen.
        $sql = "SELECT * FROM {local_kursassistent_sectpl}
                 WHERE userid = 0 OR userid = :userid
              ORDER BY userid ASC, sortorder ASC, name ASC";
        $records = $DB->get_records_sql($sql, ['userid' => $USER->id]);

        $templates = [];
        foreach ($records as $rec) {
            $templates[] = [
                'id' => (int) $rec->id,
                'name' => $rec->name,
                'description' => $rec->description ?? '',
                'definition' => $rec->definition,
                'isglobal' => ((int) $rec->userid === 0) ? 1 : 0,
                'isown' => ((int) $rec->userid === (int) $USER->id) ? 1 : 0,
                'candelete' => ((int) $rec->userid === (int) $USER->id || $canmanage) ? 1 : 0,
            ];
        }

        return ['templates' => $templates, 'canmanage' => $canmanage ? 1 : 0];
    }

    /**
     * Rückgabestruktur für get_section_templates.
     *
     * @return external_single_structure
     */
    public static function get_section_templates_returns(): external_single_structure {
        return new external_single_structure([
            'templates' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Vorlagen-ID'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'description' => new external_value(PARAM_TEXT, 'Beschreibung', VALUE_DEFAULT, ''),
                'definition' => new external_value(PARAM_RAW, 'JSON-Definition'),
                'isglobal' => new external_value(PARAM_INT, 'Globale Vorlage (0/1)'),
                'isown' => new external_value(PARAM_INT, 'Eigene Vorlage (0/1)'),
                'candelete' => new external_value(PARAM_INT, 'Löschbar (0/1)'),
            ])),
            'canmanage' => new external_value(PARAM_INT, 'Darf globale Vorlagen verwalten (0/1)'),
        ]);
    }

    /**
     * Parameter für save_section_template.
     *
     * @return external_function_parameters
     */
    public static function save_section_template_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID (für Kontextprüfung)'),
            'id' => new external_value(PARAM_INT, 'Vorlagen-ID (0 = neue Vorlage)', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Name der Vorlage'),
            'description' => new external_value(PARAM_TEXT, 'Beschreibung', VALUE_DEFAULT, ''),
            'definition' => new external_value(PARAM_RAW, 'JSON-Definition'),
            'isglobal' => new external_value(PARAM_BOOL, 'Als globale Vorlage speichern', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Speichert eine Abschnittsvorlage (neu oder aktualisieren).
     *
     * @param int $courseid
     * @param int $id
     * @param string $name
     * @param string $description
     * @param string $definition
     * @param bool $isglobal
     * @return array
     */
    public static function save_section_template(
        int $courseid,
        int $id = 0,
        string $name = '',
        string $description = '',
        string $definition = '',
        bool $isglobal = false
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::save_section_template_parameters(), [
            'courseid' => $courseid,
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'definition' => $definition,
            'isglobal' => $isglobal,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        // Globale Vorlagen erfordern die :manage-Berechtigung.
        if ($params['isglobal']) {
            require_capability('local/kursassistent:manage', \context_system::instance());
        }

        // JSON validieren.
        $decoded = json_decode($params['definition'], true);
        if (!is_array($decoded)) {
            throw new \invalid_parameter_exception('definition muss ein gültiges JSON-Array sein.');
        }

        $now = time();

        if ($params['id'] > 0) {
            // Bestehende Vorlage aktualisieren.
            $existing = $DB->get_record('local_kursassistent_sectpl', ['id' => $params['id']], '*', MUST_EXIST);

            // Eigentümerprüfung: nur eigene oder mit :manage.
            if ((int) $existing->userid !== (int) $USER->id) {
                require_capability('local/kursassistent:manage', \context_system::instance());
            }

            $existing->name = $params['name'];
            $existing->description = $params['description'];
            $existing->definition = $params['definition'];
            $existing->userid = $params['isglobal'] ? 0 : (int) $USER->id;
            $existing->timemodified = $now;

            $DB->update_record('local_kursassistent_sectpl', $existing);

            return ['success' => true, 'id' => (int) $existing->id];
        }

        // Neue Vorlage erstellen.
        $record = new \stdClass();
        $record->userid = $params['isglobal'] ? 0 : (int) $USER->id;
        $record->name = $params['name'];
        $record->description = $params['description'];
        $record->definition = $params['definition'];
        $record->sortorder = 0;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $newid = $DB->insert_record('local_kursassistent_sectpl', $record);

        return ['success' => true, 'id' => (int) $newid];
    }

    /**
     * Rückgabestruktur für save_section_template.
     *
     * @return external_single_structure
     */
    public static function save_section_template_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Erfolgreich'),
            'id' => new external_value(PARAM_INT, 'Vorlagen-ID'),
        ]);
    }

    /**
     * Parameter für delete_section_template.
     *
     * @return external_function_parameters
     */
    public static function delete_section_template_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID (für Kontextprüfung)'),
            'id' => new external_value(PARAM_INT, 'Vorlagen-ID'),
        ]);
    }

    /**
     * Löscht eine Abschnittsvorlage.
     *
     * @param int $courseid
     * @param int $id
     * @return array
     */
    public static function delete_section_template(int $courseid, int $id): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::delete_section_template_parameters(), [
            'courseid' => $courseid,
            'id' => $id,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        $existing = $DB->get_record('local_kursassistent_sectpl', ['id' => $params['id']], '*', MUST_EXIST);

        // Eigentümerprüfung: nur eigene oder mit :manage.
        if ((int) $existing->userid !== (int) $USER->id) {
            require_capability('local/kursassistent:manage', \context_system::instance());
        }

        $DB->delete_records('local_kursassistent_sectpl', ['id' => $params['id']]);

        return ['success' => true];
    }

    /**
     * Rückgabestruktur für delete_section_template.
     *
     * @return external_single_structure
     */
    public static function delete_section_template_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Erfolgreich'),
        ]);
    }

    /**
     * Parameter für apply_section_template.
     *
     * @return external_function_parameters
     */
    public static function apply_section_template_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Zielabschnitt'),
            'templateid' => new external_value(PARAM_INT, 'Vorlagen-ID'),
        ]);
    }

    /**
     * Wendet eine Abschnittsvorlage auf einen Kursabschnitt an.
     * Jeder Eintrag in der Vorlagen-Definition erzeugt ein Label über manager::create_label().
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param int $templateid
     * @return array
     */
    public static function apply_section_template(int $courseid, int $sectionnum, int $templateid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::apply_section_template_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'templateid' => $templateid,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        $template = $DB->get_record('local_kursassistent_sectpl', ['id' => $params['templateid']], '*', MUST_EXIST);

        // Zugriffsprüfung: globale oder eigene Vorlage.
        if ((int) $template->userid !== 0 && (int) $template->userid !== (int) $USER->id) {
            throw new \moodle_exception('nopermissions', 'error', '', 'Zugriff auf diese Vorlage verweigert.');
        }

        $definition = json_decode($template->definition, true);
        if (!is_array($definition)) {
            return ['success' => false, 'message' => 'Ungültige Vorlagendefinition.', 'erzeugt' => 0];
        }

        $erzeugt = 0;
        foreach ($definition as $entry) {
            $art = $entry['art'] ?? 'baustein';

            if ($art === 'aktivitaet') {
                // Aktivität erstellen.
                $modname = $entry['modname'] ?? '';
                $name = $entry['name'] ?? '';
                $intro = $entry['intro'] ?? '';
                if (empty($modname) || empty($name)) {
                    continue;
                }
                try {
                    manager::create_activity(
                        $params['courseid'],
                        $params['sectionnum'],
                        $modname,
                        $name,
                        $intro
                    );
                    $erzeugt++;
                } catch (\Exception $e) {
                    // Modul nicht verfügbar oder Fehler — überspringen.
                    continue;
                }
            } else {
                // Baustein (Label) erstellen — bisheriges Verhalten.
                $typeid = (int) ($entry['typeid'] ?? 0);
                $inhalt = $entry['inhalt'] ?? '';

                if ($typeid <= 0) {
                    continue;
                }

                try {
                    $type = manager::get_type($typeid);
                } catch (\Exception $e) {
                    continue;
                }

                manager::create_label(
                    $params['courseid'],
                    $params['sectionnum'],
                    $type,
                    $inhalt
                );
                $erzeugt++;
            }
        }

        rebuild_course_cache($params['courseid'], true);

        return [
            'success' => true,
            'message' => '',
            'erzeugt' => $erzeugt,
        ];
    }

    /**
     * Rückgabestruktur für apply_section_template.
     *
     * @return external_single_structure
     */
    public static function apply_section_template_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Erfolgreich'),
            'message' => new external_value(PARAM_TEXT, 'Hinweistext', VALUE_DEFAULT, ''),
            'erzeugt' => new external_value(PARAM_INT, 'Anzahl erzeugter Elemente'),
        ]);
    }

    // Abschnittsinhalte auslesen (fuer Abschnitt als Vorlage speichern).

    /**
     * Parameter für get_section_content.
     *
     * @return external_function_parameters
     */
    public static function get_section_content_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Abschnittsnummer'),
        ]);
    }

    /**
     * Liest alle Kursmodule eines Abschnitts aus und gibt sie als typisierte
     * Einträge zurück (baustein vs. aktivitaet), sodass daraus eine Vorlage
     * gespeichert werden kann.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @return array
     */
    public static function get_section_content(int $courseid, int $sectionnum): array {
        global $DB;

        $params = self::validate_parameters(self::get_section_content_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        $modinfo = get_fast_modinfo($params['courseid']);

        // Alle Baustein-Typen laden, um Labels zuordnen zu können.
        $bausteintypen = manager::get_types(true);
        $bausteinmap = [];
        foreach ($bausteintypen as $bt) {
            $bausteinmap[(int) $bt->id] = $bt;
        }

        // Log-Einträge laden, um Labels den Baustein-Typen zuzuordnen.
        $logrecords = $DB->get_records('local_kursassistent_log', ['courseid' => $params['courseid']]);
        $cmidtotype = [];
        foreach ($logrecords as $lr) {
            $cmidtotype[(int) $lr->cmid] = (int) $lr->typeid;
        }

        $items = [];
        $sections = $modinfo->get_section_info_all();

        $targetsection = null;
        foreach ($sections as $si) {
            if ((int) $si->section === $params['sectionnum']) {
                $targetsection = $si;
                break;
            }
        }

        if (!$targetsection) {
            return ['items' => [], 'sectionname' => ''];
        }

        $sequence = $modinfo->sections[$params['sectionnum']] ?? [];

        foreach ($sequence as $cmid) {
            if (!isset($modinfo->cms[$cmid])) {
                continue;
            }
            $cm = $modinfo->cms[$cmid];
            if ($cm->deletioninprogress) {
                continue;
            }

            if ($cm->modname === 'label') {
                // Prüfen, ob dieses Label einem Baustein-Typ zugeordnet ist.
                $typeid = $cmidtotype[(int) $cmid] ?? 0;
                if ($typeid > 0 && isset($bausteinmap[$typeid])) {
                    // Bekannter Baustein.
                    $labelinstance = $DB->get_record('label', ['id' => $cm->instance], 'intro');
                    $items[] = [
                        'art' => 'baustein',
                        'modname' => '',
                        'name' => $bausteinmap[$typeid]->titel,
                        'intro' => $labelinstance->intro ?? '',
                        'typeid' => $typeid,
                    ];
                } else {
                    // Sonstiges Label — als generischen Baustein aufnehmen.
                    $labelinstance = $DB->get_record('label', ['id' => $cm->instance], 'intro');
                    $items[] = [
                        'art' => 'label',
                        'modname' => 'label',
                        'name' => $cm->name ?: 'Textfeld',
                        'intro' => $labelinstance->intro ?? '',
                        'typeid' => 0,
                    ];
                }
            } else {
                // Aktivität.
                $intro = '';
                $instance = $DB->get_record($cm->modname, ['id' => $cm->instance], 'intro', IGNORE_MISSING);
                if ($instance && isset($instance->intro)) {
                    $intro = $instance->intro;
                }
                $items[] = [
                    'art' => 'aktivitaet',
                    'modname' => $cm->modname,
                    'name' => $cm->name,
                    'intro' => $intro,
                    'typeid' => 0,
                ];
            }
        }

        $sectionname = $targetsection->name ?: get_string('section') . ' ' . $params['sectionnum'];

        return ['items' => $items, 'sectionname' => $sectionname];
    }

    /**
     * Rückgabestruktur für get_section_content.
     *
     * @return external_single_structure
     */
    public static function get_section_content_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'art' => new external_value(PARAM_ALPHA, 'baustein, label oder aktivitaet'),
                'modname' => new external_value(PARAM_TEXT, 'Modulname (bei Aktivität)', VALUE_DEFAULT, ''),
                'name' => new external_value(PARAM_TEXT, 'Anzeigename'),
                'intro' => new external_value(PARAM_RAW, 'Intro-/Inhaltstext', VALUE_DEFAULT, ''),
                'typeid' => new external_value(PARAM_INT, 'Baustein-Typ-ID (bei Baustein)', VALUE_DEFAULT, 0),
            ])),
            'sectionname' => new external_value(PARAM_TEXT, 'Name des Abschnitts'),
        ]);
    }

    // Verfuegbare Aktivitaetstypen fuer Vorlagen.

    /**
     * Parameter für get_activity_types.
     *
     * @return external_function_parameters
     */
    public static function get_activity_types_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID (für Kontextprüfung)'),
        ]);
    }

    /**
     * Liefert die gängigen Aktivitätstypen für die Vorlagen-Auswahl.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_activity_types(int $courseid): array {
        $params = self::validate_parameters(self::get_activity_types_parameters(), ['courseid' => $courseid]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        $types = manager::get_common_activity_types();
        $result = [];
        foreach ($types as $modname => $displayname) {
            $result[] = ['modname' => $modname, 'displayname' => $displayname];
        }

        return ['types' => $result];
    }

    /**
     * Rückgabestruktur für get_activity_types.
     *
     * @return external_single_structure
     */
    public static function get_activity_types_returns(): external_single_structure {
        return new external_single_structure([
            'types' => new external_multiple_structure(new external_single_structure([
                'modname' => new external_value(PARAM_TEXT, 'Modulname'),
                'displayname' => new external_value(PARAM_TEXT, 'Anzeigename'),
            ])),
        ]);
    }

    // Kursstatistik - Teilnehmer-Abschluss.

    /**
     * Parameter für get_course_statistics.
     *
     * @return external_function_parameters
     */
    public static function get_course_statistics_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
        ]);
    }

    /**
     * Liefert Abschluss-Statistiken für alle Aktivitäten im Kurs:
     * pro Aktivität die Anzahl eingeschriebener Teilnehmer, die Anzahl
     * abgeschlossener Teilnehmer und die Liste der einzelnen Teilnehmer
     * mit ihrem Abschlussstatus.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_course_statistics(int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::get_course_statistics_parameters(), [
            'courseid' => $courseid,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $completioninfo = new \completion_info($course);

        if (!$completioninfo->is_enabled()) {
            return [
                'completionenabled' => false,
                'activities' => [],
                'users' => [],
            ];
        }

        $modinfo = get_fast_modinfo($course);

        // Eingeschriebene Teilnehmer mit Studentenrolle holen.
        $users = get_enrolled_users(
            $context,
            'mod/assign:submit',
            0,
            'u.id, u.firstname, u.lastname',
            'u.lastname ASC, u.firstname ASC'
        );

        // Alle Aktivitäten sammeln, die Abschlussverfolgung haben.
        $activities = [];
        $sections = $modinfo->get_section_info_all();

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || $cm->deletioninprogress) {
                continue;
            }

            $completiondata = $completioninfo->get_data($cm, false);
            $tracking = $completioninfo->is_enabled($cm);
            if ($tracking == COMPLETION_TRACKING_NONE) {
                continue;
            }

            // Abschluss-Daten pro Teilnehmer ermitteln.
            $completedcount = 0;
            $usercompletions = [];
            foreach ($users as $user) {
                $data = $completioninfo->get_data($cm, false, $user->id);
                $iscomplete = in_array((int) $data->completionstate, [
                    COMPLETION_COMPLETE,
                    COMPLETION_COMPLETE_PASS,
                ]);
                if ($iscomplete) {
                    $completedcount++;
                }
                $usercompletions[] = [
                    'userid' => (int) $user->id,
                    'completed' => $iscomplete,
                    'completionstate' => (int) $data->completionstate,
                ];
            }

            // Abschnittsnamen ermitteln.
            $sectionname = '';
            if (isset($sections[$cm->sectionnum])) {
                $secinfo = $sections[$cm->sectionnum];
                $sectionname = !empty($secinfo->name) ? $secinfo->name : get_string('section') . ' ' . $cm->sectionnum;
            }

            $activities[] = [
                'cmid' => (int) $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
                'sectionnum' => (int) $cm->sectionnum,
                'sectionname' => $sectionname,
                'totalusers' => count($users),
                'completedusers' => $completedcount,
                'usercompletions' => $usercompletions,
            ];
        }

        // Teilnehmer-Liste für die Tabelle.
        $userlist = [];
        foreach ($users as $user) {
            $userlist[] = [
                'userid' => (int) $user->id,
                'fullname' => fullname($user),
            ];
        }

        return [
            'completionenabled' => true,
            'activities' => $activities,
            'users' => $userlist,
        ];
    }

    /**
     * Rückgabestruktur für get_course_statistics.
     *
     * @return external_single_structure
     */
    public static function get_course_statistics_returns(): external_single_structure {
        return new external_single_structure([
            'completionenabled' => new external_value(PARAM_BOOL, 'Abschlussverfolgung aktiv'),
            'activities' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Kursmodul-ID'),
                'name' => new external_value(PARAM_TEXT, 'Aktivitätsname'),
                'modname' => new external_value(PARAM_TEXT, 'Modulname'),
                'sectionnum' => new external_value(PARAM_INT, 'Abschnittsnummer'),
                'sectionname' => new external_value(PARAM_TEXT, 'Abschnittsname'),
                'totalusers' => new external_value(PARAM_INT, 'Gesamtzahl Teilnehmer'),
                'completedusers' => new external_value(PARAM_INT, 'Abgeschlossen'),
                'usercompletions' => new external_multiple_structure(new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'Nutzer-ID'),
                    'completed' => new external_value(PARAM_BOOL, 'Abgeschlossen ja/nein'),
                    'completionstate' => new external_value(PARAM_INT, 'Abschlussstatus-Code'),
                ])),
            ])),
            'users' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'Nutzer-ID'),
                'fullname' => new external_value(PARAM_TEXT, 'Vollständiger Name'),
            ])),
        ]);
    }

    // Eigener Fortschritt (Schueler-Ansicht).

    /**
     * Parameter für get_own_progress.
     *
     * @return external_function_parameters
     */
    public static function get_own_progress_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
        ]);
    }

    /**
     * Liefert den eigenen Abschlussfortschritt des aktuellen Nutzers.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_own_progress(int $courseid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_own_progress_parameters(), [
            'courseid' => $courseid,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:viewownprogress', $context);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $completioninfo = new \completion_info($course);

        if (!$completioninfo->is_enabled()) {
            return [
                'completionenabled' => false,
                'activities' => [],
                'completedcount' => 0,
                'totalcount' => 0,
            ];
        }

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $activities = [];
        $completedcount = 0;

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || $cm->deletioninprogress) {
                continue;
            }

            $tracking = $completioninfo->is_enabled($cm);
            if ($tracking == COMPLETION_TRACKING_NONE) {
                continue;
            }

            $data = $completioninfo->get_data($cm, false, $USER->id);
            $iscomplete = in_array((int) $data->completionstate, [
                COMPLETION_COMPLETE,
                COMPLETION_COMPLETE_PASS,
            ]);
            if ($iscomplete) {
                $completedcount++;
            }

            $sectionname = '';
            if (isset($sections[$cm->sectionnum])) {
                $secinfo = $sections[$cm->sectionnum];
                $sectionname = !empty($secinfo->name) ? $secinfo->name : get_string('section') . ' ' . $cm->sectionnum;
            }

            $activities[] = [
                'cmid' => (int) $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
                'sectionnum' => (int) $cm->sectionnum,
                'sectionname' => $sectionname,
                'completed' => $iscomplete,
                'completionstate' => (int) $data->completionstate,
            ];
        }

        // Kommentar der Lehrkraft laden, falls vorhanden.
        $comment = $DB->get_record('local_kursassistent_comment', [
            'courseid' => $courseid,
            'studentid' => $USER->id,
        ]);

        return [
            'completionenabled' => true,
            'activities' => $activities,
            'completedcount' => $completedcount,
            'totalcount' => count($activities),
            'teachercomment' => $comment ? $comment->commenttext : '',
        ];
    }

    /**
     * Rückgabestruktur für get_own_progress.
     *
     * @return external_single_structure
     */
    public static function get_own_progress_returns(): external_single_structure {
        return new external_single_structure([
            'completionenabled' => new external_value(PARAM_BOOL, 'Abschlussverfolgung aktiv'),
            'activities' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Kursmodul-ID'),
                'name' => new external_value(PARAM_TEXT, 'Aktivitätsname'),
                'modname' => new external_value(PARAM_TEXT, 'Modulname'),
                'sectionnum' => new external_value(PARAM_INT, 'Abschnittsnummer'),
                'sectionname' => new external_value(PARAM_TEXT, 'Abschnittsname'),
                'completed' => new external_value(PARAM_BOOL, 'Abgeschlossen'),
                'completionstate' => new external_value(PARAM_INT, 'Abschlussstatus-Code'),
            ])),
            'completedcount' => new external_value(PARAM_INT, 'Abgeschlossene Aktivitäten'),
            'totalcount' => new external_value(PARAM_INT, 'Gesamtzahl Aktivitäten'),
            'teachercomment' => new external_value(PARAM_RAW, 'Kommentar der Lehrkraft', VALUE_DEFAULT, ''),
        ]);
    }

    // Save_student_comment - Lehrkraft speichert Kommentar pro Schueler.

    /**
     * Parameter für save_student_comment.
     *
     * @return external_function_parameters
     */
    public static function save_student_comment_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
            'studentid' => new external_value(PARAM_INT, 'User-ID des Teilnehmers'),
            'comment' => new external_value(PARAM_RAW, 'Kommentartext (leer = löschen)'),
        ]);
    }

    /**
     * Speichert oder aktualisiert einen Lehrkraft-Kommentar für einen Teilnehmer.
     *
     * @param int $courseid
     * @param int $studentid
     * @param string $comment
     * @return array
     */
    public static function save_student_comment(int $courseid, int $studentid, string $comment): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::save_student_comment_parameters(), [
            'courseid' => $courseid,
            'studentid' => $studentid,
            'comment' => $comment,
        ]);
        $courseid = $params['courseid'];
        $studentid = $params['studentid'];
        $comment = trim($params['comment']);

        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        // Nur zu Personen, die tatsaechlich im Kurs sind. Ohne diese Pruefung liessen sich
        // ueber den Parameter studentid Eintraege zu beliebigen Nutzerkonten anlegen.
        if (!is_enrolled($context, $studentid)) {
            throw new \moodle_exception('kommentar_keinteilnehmer', 'local_kursassistent');
        }

        $now = time();
        $existing = $DB->get_record('local_kursassistent_comment', [
            'courseid' => $courseid,
            'studentid' => $studentid,
        ]);

        if (empty($comment)) {
            // Leerer Kommentar = löschen.
            if ($existing) {
                $DB->delete_records('local_kursassistent_comment', ['id' => $existing->id]);
            }
            return ['success' => true];
        }

        if ($existing) {
            $existing->commenttext = $comment;
            $existing->teacherid = $USER->id;
            $existing->timemodified = $now;
            $DB->update_record('local_kursassistent_comment', $existing);
        } else {
            $record = new \stdClass();
            $record->courseid = $courseid;
            $record->studentid = $studentid;
            $record->teacherid = $USER->id;
            $record->commenttext = $comment;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_kursassistent_comment', $record);
        }

        return ['success' => true];
    }

    /**
     * Rückgabestruktur für save_student_comment.
     *
     * @return external_single_structure
     */
    public static function save_student_comment_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Erfolgreich gespeichert'),
        ]);
    }

    // Get_student_comments - Alle Kommentare eines Kurses fuer Lehrkraefte.

    /**
     * Parameter für get_student_comments.
     *
     * @return external_function_parameters
     */
    public static function get_student_comments_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
        ]);
    }

    /**
     * Liefert alle Lehrkraft-Kommentare eines Kurses.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_student_comments(int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::get_student_comments_parameters(), [
            'courseid' => $courseid,
        ]);
        $courseid = $params['courseid'];

        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        $records = $DB->get_records('local_kursassistent_comment', ['courseid' => $courseid]);
        $comments = [];
        foreach ($records as $rec) {
            $comments[] = [
                'studentid' => (int) $rec->studentid,
                'comment' => $rec->commenttext,
                'teacherid' => (int) $rec->teacherid,
                'timemodified' => (int) $rec->timemodified,
            ];
        }

        return ['comments' => $comments];
    }

    /**
     * Rückgabestruktur für get_student_comments.
     *
     * @return external_single_structure
     */
    public static function get_student_comments_returns(): external_single_structure {
        return new external_single_structure([
            'comments' => new external_multiple_structure(new external_single_structure([
                'studentid' => new external_value(PARAM_INT, 'User-ID des Teilnehmers'),
                'comment' => new external_value(PARAM_RAW, 'Kommentartext'),
                'teacherid' => new external_value(PARAM_INT, 'User-ID der Lehrkraft'),
                'timemodified' => new external_value(PARAM_INT, 'Letzte Änderung'),
            ])),
        ]);
    }
}
