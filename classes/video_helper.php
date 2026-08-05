<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_kursassistent;

/**
 * Kapselt den Video-Modus (aus / verlinkung / upload) und den PeerTube-Kanalstatus.
 *
 * Bewusst lose gekoppelt: local_kursassistent hat keine harte Abhängigkeit
 * zu local_peertubeupload oder repository_peertubeoauth. Beide werden nur
 * genutzt, wenn tatsächlich installiert.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video_helper {
    /**
     * Prüft, ob die Video-Funktion an dieser Schule grundsätzlich aktiviert ist.
     *
     * @return bool
     */
    public static function is_active(): bool {
        return (bool) get_config('local_kursassistent', 'videoaktiv');
    }

    /**
     * Prüft, ob für den Modus 'upload' das Plugin local_peertubeupload verfügbar ist.
     *
     * @return bool
     */
    public static function is_upload_plugin_verfuegbar(): bool {
        return \core_component::get_component_directory('local_peertubeupload') !== null;
    }

    /**
     * Prüft, ob für den Modus 'verlinkung' das Repository repository_peertubeoauth verfügbar ist.
     *
     * @return bool
     */
    public static function is_repository_verfuegbar(): bool {
        return \core_component::get_component_directory('repository_peertubeoauth') !== null;
    }

    /**
     * Prüft den PeerTube-Kanalstatus einer Nutzerin/eines Nutzers über local_peertubeupload.
     *
     * Ruft, falls vorhanden, local_peertubeupload\channel_manager::is_channel_ready() auf.
     * Diese Methode muss in local_peertubeupload ergänzt werden (siehe Entwicklungsnotiz).
     * Ist die Methode nicht vorhanden, wird sicherheitshalber "nicht bereit" zurückgegeben.
     *
     * @param int $userid
     * @return array{ready: bool, channelname: ?string}
     */
    public static function get_channel_status(int $userid): array {
        global $CFG;

        if (!self::is_upload_plugin_verfuegbar()) {
            return ['ready' => false, 'channelname' => null];
        }

        $classfile = $CFG->dirroot . '/local/peertubeupload/classes/channel_manager.php';
        if (!file_exists($classfile)) {
            // Eine aeltere Version von local_peertubeupload ohne diese Klasse.
            return ['ready' => false, 'channelname' => null];
        }
        require_once($classfile);

        if (
            !class_exists('local_peertubeupload_channel_manager')
            || !method_exists('local_peertubeupload_channel_manager', 'is_channel_ready')
        ) {
            return ['ready' => false, 'channelname' => null];
        }

        $result = local_peertubeupload_channel_manager::is_channel_ready($userid);
        return [
            'ready' => (bool) ($result['ready'] ?? false),
            'channelname' => $result['channelname'] ?? null,
        ];
    }

    /**
     * Liefert die URL zur Kanal-Einrichtung (Profilseite von local_peertubeupload).
     *
     * @return \moodle_url|null
     */
    public static function get_kanal_einrichten_url(): ?\moodle_url {
        if (!self::is_upload_plugin_verfuegbar()) {
            return null;
        }
        return new \moodle_url('/local/peertubeupload/index.php');
    }
}
