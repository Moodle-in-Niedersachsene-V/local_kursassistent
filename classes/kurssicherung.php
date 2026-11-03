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

/**
 * Erstellt die Sicherung eines Kurses ohne Eingeschriebene.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class kurssicherung {
    /**
     * Sichert den Kurs ohne Eingeschriebene und ohne Nutzerdaten.
     *
     * Die Rechte werden bewusst nicht hier geprüft, sondern vom Aufrufer.
     *
     * @param int $courseid Kurs
     * @param int $userid Person, für die die Sicherung erstellt wird
     * @return string Dateiname der Sicherung, leer wenn Moodle keine Datei meldet
     */
    public static function erstellen(int $courseid, int $userid): string {
        global $CFG;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        \core_php_time_limit::raise(600);
        raise_memory_limit(MEMORY_EXTRA);

        $controller = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid
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
            // Ohne ausdrücklichen Namen heißt die Datei nur backup.mbz und ist dem Kurs nicht zuzuordnen.
            if ($plan->setting_exists('filename')) {
                $dateiname = \backup_plan_dbops::get_default_backup_filename(
                    \backup::FORMAT_MOODLE,
                    \backup::TYPE_1COURSE,
                    $courseid,
                    false,
                    false
                );
                $plan->get_setting('filename')->set_value($dateiname);
            }
            $controller->set_status(\backup::STATUS_AWAITING);
            $controller->execute_plan();
            $ergebnis = $controller->get_results();
        } finally {
            $controller->destroy();
        }

        $datei = $ergebnis['backup_destination'] ?? null;
        return $datei ? $datei->get_filename() : '';
    }
}
