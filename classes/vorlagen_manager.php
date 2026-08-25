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

use backup;
use backup_controller;
use restore_controller;

/**
 * Verwaltet die Kursvorlagen und deren Übernahme in einen Zielkurs.
 *
 * Die Übernahme entspricht dem Verfahren von Moodles Kursimport: Der Inhalt der
 * Vorlage wird dem Zielkurs hinzugefügt, vorhandene Inhalte bleiben erhalten.
 * Es werden keine Nutzerdaten übertragen.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vorlagen_manager {
    /**
     * Liefert die konfigurierte Kategorie-ID für Kursvorlagen.
     *
     * @return int 0, wenn keine Kategorie konfiguriert ist
     */
    public static function get_kategorie_id(): int {
        return (int) get_config('local_kursassistent', 'vorlagenkategorie');
    }

    /**
     * Prüft, ob die Vorlagenfunktion einsatzbereit ist.
     *
     * @return bool
     */
    public static function is_aktiv(): bool {
        $kategorieid = self::get_kategorie_id();
        if ($kategorieid <= 0) {
            return false;
        }
        return \core_course_category::get($kategorieid, IGNORE_MISSING, true) !== null;
    }

    /**
     * Liefert alle Kurse aus der konfigurierten Vorlagenkategorie.
     *
     * @return array Liste von Objekten mit id, fullname, summary und bildurl
     */
    public static function get_vorlagen(): array {
        global $DB;

        $kategorieid = self::get_kategorie_id();
        if ($kategorieid <= 0) {
            return [];
        }

        $records = $DB->get_records(
            'course',
            ['category' => $kategorieid],
            'sortorder ASC',
            'id, fullname, shortname, summary, summaryformat'
        );

        $vorlagen = [];
        foreach ($records as $record) {
            $record->bildurl = self::get_kursbild_url((int) $record->id);
            $vorlagen[] = $record;
        }

        return $vorlagen;
    }

    /**
     * Liefert die URL des Kursbildes (Übersichtsbild) oder null.
     *
     * @param int $courseid
     * @return string|null
     */
    protected static function get_kursbild_url(int $courseid): ?string {
        $context = \context_course::instance($courseid);
        $fs = get_file_storage();
        $dateien = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false);

        foreach ($dateien as $datei) {
            if (!$datei->is_valid_image()) {
                continue;
            }
            return \moodle_url::make_pluginfile_url(
                $context->id,
                'course',
                'overviewfiles',
                null,
                $datei->get_filepath(),
                $datei->get_filename()
            )->out(false);
        }

        return null;
    }

    /**
     * Prüft, ob die angegebene Kurs-ID tatsächlich eine freigegebene Vorlage ist.
     *
     * Diese Prüfung ist sicherheitsrelevant: Die Sicherung der Vorlage läuft mit
     * erweiterten Rechten. Ohne diese Beschränkung liesse sich darüber der Inhalt
     * beliebiger Kurse auslesen.
     *
     * @param int $templateid
     * @return \stdClass Kursobjekt der Vorlage
     * @throws \moodle_exception Wenn der Kurs nicht in der Vorlagenkategorie liegt
     */
    public static function get_geprueft(int $templateid): \stdClass {
        $kategorieid = self::get_kategorie_id();
        if ($kategorieid <= 0) {
            throw new \moodle_exception('vorlagen_keinekategorie', 'local_kursassistent');
        }

        $kurs = get_course($templateid);
        if ((int) $kurs->category !== $kategorieid) {
            throw new \moodle_exception('vorlagen_keinevorlage', 'local_kursassistent');
        }

        return $kurs;
    }

    /**
     * Ermittelt die Nutzer-ID, unter der die Sicherung der Vorlage läuft.
     *
     * Lehrkräfte sind in der Vorlagenkategorie in der Regel nicht eingeschrieben und
     * besitzen dort keine Sicherungsrechte. Die Sicherung läuft daher unter dem
     * ersten Websiteadministrator. Die Beschränkung auf die konfigurierte
     * Vorlagenkategorie in get_geprueft() ist die zugehörige Schutzmassnahme.
     *
     * @return int
     * @throws \moodle_exception Wenn keine Administration ermittelbar ist
     */
    protected static function get_sicherungsnutzer_id(): int {
        $admins = get_admins();
        if (empty($admins)) {
            throw new \moodle_exception('vorlagen_keinadmin', 'local_kursassistent');
        }
        return (int) reset($admins)->id;
    }

    /**
     * Überträgt den Inhalt einer Vorlage in den Zielkurs.
     *
     * Vorhandene Inhalte des Zielkurses bleiben erhalten, der Vorlageninhalt wird
     * ergänzt. Nutzerdaten werden nicht übertragen.
     *
     * @param int $templateid Kurs-ID der Vorlage
     * @param int $targetcourseid Kurs-ID des Zielkurses
     * @return void
     */
    public static function uebernehmen(int $templateid, int $targetcourseid): void {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        // Sicherstellen, dass nur freigegebene Vorlagen verwendet werden.
        self::get_geprueft($templateid);

        // Die Berechtigung im Zielkurs wird bewusst gegen die aufrufende Person geprüft.
        $zielcontext = \context_course::instance($targetcourseid);
        require_capability('moodle/restore:restoretargetimport', $zielcontext);

        \core_php_time_limit::raise(600);
        raise_memory_limit(MEMORY_EXTRA);

        $sicherungsnutzer = self::get_sicherungsnutzer_id();

        // Schritt 1: Sicherung der Vorlage ohne Nutzerdaten.
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $templateid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $sicherungsnutzer
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Schritt 2: Wiederherstellung in den Zielkurs, vorhandene Inhalte bleiben erhalten.
        $rc = new restore_controller(
            $backupid,
            $targetcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id,
            backup::TARGET_EXISTING_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        rebuild_course_cache($targetcourseid, true);
    }
}
