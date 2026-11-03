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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Webservices für Kurserstellung und Kurssicherung im Reiter Kurseinrichtung.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class kursverwaltung extends external_api {
    /** @var int Höchstzahl der angebotenen Kurse als Grundlage. */
    private const MAX_QUELLKURSE = 200;

    /**
     * Parameter für get_kurserstellung.
     *
     * @return external_function_parameters
     */
    public static function get_kurserstellung_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
        ]);
    }

    /**
     * Liefert die Kursbereiche, in denen die Lehrkraft Kurse anlegen darf, und die Kurse,
     * die sie als Grundlage kopieren darf.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_kurserstellung(int $courseid): array {
        $params = self::validate_parameters(self::get_kurserstellung_parameters(), ['courseid' => $courseid]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        $kategorien = self::erlaubte_kursbereiche();

        $quellkurse = [];
        if ($kategorien) {
            $kurse = enrol_get_my_courses(['id', 'fullname', 'shortname'], 'fullname ASC');
            foreach ($kurse as $kurs) {
                if (count($quellkurse) >= self::MAX_QUELLKURSE) {
                    break;
                }
                if (has_capability('moodle/backup:backupcourse', \context_course::instance($kurs->id))) {
                    $quellkurse[] = [
                        'id' => (int) $kurs->id,
                        'name' => format_string($kurs->fullname, true, ['context' => \context_course::instance($kurs->id)]),
                    ];
                }
            }
        }

        return ['kategorien' => $kategorien, 'quellkurse' => $quellkurse];
    }

    /**
     * Liefert die Kursbereiche, in denen die angemeldete Person Kurse anlegen darf.
     *
     * Das Recht wird für jeden Kursbereich einzeln geprüft, ohne den Zwischenspeicher von
     * core_course_category, damit gerade vergebene Rollen sofort wirken.
     *
     * @return array Liste aus id und Name mit dem Pfad der übergeordneten Kursbereiche
     */
    private static function erlaubte_kursbereiche(): array {
        global $DB;

        $zeilen = $DB->get_records('course_categories', null, 'sortorder', 'id, name, path');

        $namen = [];
        foreach ($zeilen as $zeile) {
            $namen[$zeile->id] = $zeile->name;
        }

        $ergebnis = [];
        foreach ($zeilen as $zeile) {
            $kontext = \context_coursecat::instance($zeile->id);
            if (!has_capability('moodle/course:create', $kontext)) {
                continue;
            }
            $teile = [];
            foreach (array_filter(explode('/', $zeile->path)) as $id) {
                if (isset($namen[$id])) {
                    $teile[] = format_string($namen[$id], true, ['context' => $kontext]);
                }
            }
            $ergebnis[] = ['id' => (int) $zeile->id, 'name' => implode(' / ', $teile)];
        }
        usort($ergebnis, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        return $ergebnis;
    }

    /**
     * Rückgabestruktur für get_kurserstellung.
     *
     * @return external_single_structure
     */
    public static function get_kurserstellung_returns(): external_single_structure {
        $eintrag = new external_single_structure([
            'id' => new external_value(PARAM_INT, 'ID'),
            'name' => new external_value(PARAM_TEXT, 'Name'),
        ]);
        return new external_single_structure([
            'kategorien' => new external_multiple_structure($eintrag, 'Kursbereiche mit Recht zum Anlegen von Kursen'),
            'quellkurse' => new external_multiple_structure(clone $eintrag, 'Kurse, die kopiert werden dürfen'),
        ]);
    }

    /**
     * Parameter für get_backups.
     *
     * @return external_function_parameters
     */
    public static function get_backups_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
        ]);
    }

    /**
     * Liefert die Sicherungen im Sicherungsbereich des Kurses mit Download-Adresse.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_backups(int $courseid): array {
        $params = self::validate_parameters(self::get_backups_parameters(), ['courseid' => $courseid]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);

        $cancreate = has_capability('moodle/backup:backupcourse', $context);
        $candownload = has_capability('moodle/backup:downloadfile', $context);

        $dateien = [];
        if ($cancreate && $candownload) {
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'backup', 'course', 0, 'timecreated DESC', false);
            foreach ($files as $file) {
                $url = \moodle_url::make_pluginfile_url(
                    $context->id,
                    'backup',
                    'course',
                    0,
                    $file->get_filepath(),
                    $file->get_filename(),
                    true
                );
                $dateien[] = [
                    'filename' => $file->get_filename(),
                    'size' => display_size($file->get_filesize()),
                    'time' => userdate($file->get_timecreated(), get_string('strftimedatetimeshort', 'langconfig')),
                    'url' => $url->out(false),
                ];
            }
        }

        return [
            'cancreate' => $cancreate,
            'candownload' => $candownload,
            'files' => $dateien,
            'restoreurl' => (new \moodle_url('/backup/restorefile.php', ['contextid' => $context->id]))->out(false),
        ];
    }

    /**
     * Rückgabestruktur für get_backups.
     *
     * @return external_single_structure
     */
    public static function get_backups_returns(): external_single_structure {
        return new external_single_structure([
            'cancreate' => new external_value(PARAM_BOOL, 'Darf Sicherungen erstellen'),
            'candownload' => new external_value(PARAM_BOOL, 'Darf Sicherungen herunterladen'),
            'files' => new external_multiple_structure(new external_single_structure([
                'filename' => new external_value(PARAM_FILE, 'Dateiname'),
                'size' => new external_value(PARAM_TEXT, 'Größe'),
                'time' => new external_value(PARAM_TEXT, 'Erstellt am'),
                'url' => new external_value(PARAM_URL, 'Adresse zum Herunterladen'),
            ])),
            'restoreurl' => new external_value(PARAM_URL, 'Sicherungsbereich in Moodle'),
        ]);
    }

    /**
     * Parameter für create_backup.
     *
     * @return external_function_parameters
     */
    public static function create_backup_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Kurs-ID'),
        ]);
    }

    /**
     * Sichert den Kurs ohne Eingeschriebene und ohne Nutzerdaten in den Sicherungsbereich des Kurses.
     *
     * @param int $courseid
     * @return array
     */
    public static function create_backup(int $courseid): array {
        global $CFG, $USER;

        $params = self::validate_parameters(self::create_backup_parameters(), ['courseid' => $courseid]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/kursassistent:use', $context);
        require_capability('moodle/backup:backupcourse', $context);
        require_capability('moodle/backup:downloadfile', $context);

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        \core_php_time_limit::raise(600);
        raise_memory_limit(MEMORY_EXTRA);

        $controller = new \backup_controller(
            \backup::TYPE_1COURSE,
            $params['courseid'],
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        try {
            $plan = $controller->get_plan();
            // Ohne Eingeschriebene: keine Nutzer, Rollenzuweisungen, Abschlüsse, Kommentare, Protokolle und Bewertungsverläufe.
            $einstellungen = ['users', 'anonymize', 'role_assignments', 'userscompletion', 'comments', 'logs', 'grade_histories'];
            foreach ($einstellungen as $name) {
                if ($plan->setting_exists($name)) {
                    $setting = $plan->get_setting($name);
                    if ($setting->get_status() == \base_setting::NOT_LOCKED) {
                        $setting->set_value(false);
                    }
                }
            }
            $controller->set_status(\backup::STATUS_AWAITING);
            $controller->execute_plan();
            $ergebnis = $controller->get_results();
        } finally {
            $controller->destroy();
        }

        $datei = $ergebnis['backup_destination'] ?? null;
        return ['filename' => $datei ? $datei->get_filename() : ''];
    }

    /**
     * Rückgabestruktur für create_backup.
     *
     * @return external_single_structure
     */
    public static function create_backup_returns(): external_single_structure {
        return new external_single_structure([
            'filename' => new external_value(PARAM_TEXT, 'Dateiname der Sicherung'),
        ]);
    }
}
