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
 * Verwaltung der Hintergrundaufträge (Cron) mit Statusanzeige.
 *
 * Den Status der letzten Aufträge merkt sich eine Nutzereinstellung, damit keine eigene Tabelle nötig ist.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hintergrund {
    /** @var string Name der Nutzereinstellung mit dem Status der letzten Aufträge. */
    public const EINSTELLUNG = 'local_kursassistent_hintergrund';

    /** @var int Anzahl der gemerkten Aufträge. Die Einstellung fasst höchstens 1333 Zeichen. */
    private const MAX_EINTRAEGE = 5;

    /**
     * Gibt an, ob Sicherungen und Kopien im Hintergrund laufen.
     *
     * @return bool
     */
    public static function aktiv(): bool {
        $wert = get_config('local_kursassistent', 'hintergrund');
        return $wert === false || (bool) $wert;
    }

    /**
     * Reiht einen Auftrag ein und merkt ihn als wartend.
     *
     * @param string $art sicherung oder kopie
     * @param int $userid Person, die den Auftrag gestartet hat
     * @param int $courseid Kurs, in dem der Auftrag gestartet wurde
     * @param array $daten Weitere Angaben, bei kopie cmid, zielkurs und zielabschnitt
     */
    public static function einreihen(string $art, int $userid, int $courseid, array $daten = []): void {
        self::setze($userid, $art, $courseid, 'wartet');

        $task = new task\hintergrund_task();
        $task->set_component('local_kursassistent');
        $task->set_userid($userid);
        $task->set_custom_data(['art' => $art, 'userid' => $userid, 'courseid' => $courseid] + $daten);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Liefert den Status des letzten Auftrags einer Art in einem Kurs.
     *
     * @param int $userid
     * @param string $art
     * @param int $courseid
     * @return array status (keiner, wartet, laeuft, fertig, fehler), text und Zielkurs
     */
    public static function lese(int $userid, string $art, int $courseid): array {
        $schluessel = $art . ':' . $courseid;
        foreach (self::alle($userid) as $eintrag) {
            if (($eintrag['k'] ?? '') === $schluessel) {
                return [
                    'status' => (string) $eintrag['s'],
                    'text' => (string) ($eintrag['t'] ?? ''),
                    'targetcourseid' => (int) ($eintrag['c'] ?? 0),
                ];
            }
        }
        return ['status' => 'keiner', 'text' => '', 'targetcourseid' => 0];
    }

    /**
     * Merkt den Status eines Auftrags.
     *
     * @param int $userid
     * @param string $art
     * @param int $courseid
     * @param string $status wartet, laeuft, fertig oder fehler
     * @param string $text Meldung für die Anzeige
     * @param int $zielkurs Zielkurs bei einer Kopie
     */
    public static function setze(
        int $userid,
        string $art,
        int $courseid,
        string $status,
        string $text = '',
        int $zielkurs = 0
    ): void {
        $schluessel = $art . ':' . $courseid;
        $liste = array_filter(self::alle($userid), function ($eintrag) use ($schluessel) {
            return ($eintrag['k'] ?? '') !== $schluessel;
        });
        $liste[] = [
            'k' => $schluessel,
            's' => $status,
            't' => \core_text::substr($text, 0, 80),
            'c' => $zielkurs,
            'z' => time(),
        ];
        $liste = array_slice(array_values($liste), -self::MAX_EINTRAEGE);
        set_user_preference(self::EINSTELLUNG, json_encode($liste), $userid);
    }

    /**
     * Liest die gemerkten Aufträge.
     *
     * @param int $userid
     * @return array
     */
    private static function alle(int $userid): array {
        $wert = get_user_preferences(self::EINSTELLUNG, null, $userid);
        $liste = $wert === null ? [] : json_decode($wert, true);
        return is_array($liste) ? $liste : [];
    }

    /**
     * Führt einen Auftrag aus und hält Beginn und Ergebnis im Status fest.
     *
     * Fehler werden nicht weitergegeben, damit Moodle den Auftrag nicht endlos wiederholt.
     * Die Person sieht die Meldung im Kursassistenten.
     *
     * @param array $daten Angaben des Auftrags
     */
    public static function ausfuehren(array $daten): void {
        $art = (string) ($daten['art'] ?? '');
        $courseid = (int) ($daten['courseid'] ?? 0);
        $userid = (int) ($daten['userid'] ?? 0);
        if (!in_array($art, ['sicherung', 'kopie'], true) || !$courseid || !$userid) {
            return;
        }

        self::setze($userid, $art, $courseid, 'laeuft');
        try {
            if ($art === 'sicherung') {
                $name = self::sicherung($courseid, $userid);
                $text = get_string('hintergrund_sicherung_fertig', 'local_kursassistent', $name);
                self::setze($userid, $art, $courseid, 'fertig', $text);
            } else {
                $zielkurs = self::kopie($courseid, $userid, $daten);
                $text = get_string('hintergrund_kopie_fertig', 'local_kursassistent');
                self::setze($userid, $art, $courseid, 'fertig', $text, $zielkurs);
            }
        } catch (\Throwable $e) {
            mtrace('local_kursassistent: Hintergrundauftrag ' . $art . ' fehlgeschlagen: ' . $e->getMessage());
            $text = get_string('hintergrund_fehler', 'local_kursassistent', $e->getMessage());
            self::setze($userid, $art, $courseid, 'fehler', $text);
        }
    }

    /**
     * Sichert den Kurs, nachdem die Rechte erneut geprüft wurden.
     *
     * @param int $courseid
     * @param int $userid
     * @return string Dateiname
     */
    private static function sicherung(int $courseid, int $userid): string {
        $context = \context_course::instance($courseid);
        require_capability('moodle/backup:backupcourse', $context, $userid);
        require_capability('moodle/backup:downloadfile', $context, $userid);
        return kurssicherung::erstellen($courseid, $userid);
    }

    /**
     * Kopiert die Aktivität, nachdem die Rechte erneut geprüft wurden.
     *
     * @param int $courseid Kurs der Aktivität
     * @param int $userid
     * @param array $daten cmid, zielkurs und zielabschnitt
     * @return int Kurs, in dem die Kopie liegt
     */
    private static function kopie(int $courseid, int $userid, array $daten): int {
        global $DB;

        $cmid = (int) ($daten['cmid'] ?? 0);
        $zielkurs = (int) ($daten['zielkurs'] ?? 0) ?: $courseid;
        $zielabschnitt = (int) ($daten['zielabschnitt'] ?? -1);

        require_capability('moodle/backup:backuptargetimport', \context_course::instance($courseid), $userid);
        $DB->get_record('course_modules', ['id' => $cmid, 'course' => $courseid], 'id', MUST_EXIST);
        $course = get_course($courseid);

        if ($zielkurs === $courseid) {
            $abschnittid = $zielabschnitt >= 0 ? cm_aktionen::abschnitt_id($courseid, $zielabschnitt) : null;
            cm_aktionen::duplizieren($course, $cmid, $abschnittid);
            rebuild_course_cache($courseid, true);
        } else {
            require_capability('moodle/restore:restoretargetimport', \context_course::instance($zielkurs), $userid);
            aktivitaet_kopierer::in_anderen_kurs($cmid, $zielkurs, $zielabschnitt);
        }
        return $zielkurs;
    }
}
