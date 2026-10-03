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
 * Verbindung zum Kompetenzraster von Exabis (block_exacomp).
 *
 * Das Zuordnen von Kompetenzen zu Aktivitäten läuft bei Exabis über
 * Lernmaterialien: Ein Lernmaterial trägt die Deskriptoren und verweist auf das
 * Kursmodul. Genau diesen Weg nutzt auch die App Dakora Plus.
 *
 * Geschrieben wird nicht in Tabellen von Exabis, sondern über dessen Funktion
 * block_exacomp_relate_example_to_activity(). Das ist dieselbe Funktion, die der Tab
 * "Moodle-Aktivitäten verknüpfen" im Kompetenzraster beim Speichern aufruft. Sie ist
 * keine zugesicherte Schnittstelle. Deshalb wird vor dem Aufruf geprüft, dass sie
 * existiert und ihre ersten Parameter unverändert heissen. Sie prüft selbst keine
 * Berechtigung, das übernimmt die Brücke mit block_exacomp_is_teacher().
 *
 * Gelesen wird der Ist-Zustand direkt aus zwei Tabellen, weil dafür kein passender
 * Dienst bereitsteht; dabei werden nur Felder verwendet, deren Vorhandensein
 * geprüft wurde.
 *
 * Fehlt Exabis, meldet verfuegbar() dies und der Kursassistent blendet die
 * Kompetenzspalte aus. Eine feste Abhängigkeit besteht nicht.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exacomp_bruecke {
    /** Klasse mit den Webservice-Methoden von Exabis. */
    const EXACOMP_KLASSE = '\block_exacomp\externallib\externallib';

    /**
     * Prüft, ob Exabis installiert ist und die benötigten Methoden bereitstellt.
     *
     * @return bool
     */
    public static function verfuegbar(): bool {
        if (!\core_component::get_component_directory('block_exacomp')) {
            return false;
        }
        if (!class_exists(self::EXACOMP_KLASSE)) {
            return false;
        }

        $benoetigt = [
            'dakora_get_all_topics_by_course',
            'dakora_get_all_descriptors',
        ];
        foreach ($benoetigt as $methode) {
            if (!method_exists(self::EXACOMP_KLASSE, $methode)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Wandelt die Rückgabe von Exabis einheitlich in verschachtelte Arrays um.
     *
     * Exabis gibt je nach Methode Objekte oder Arrays zurück. Diese Umwandlung
     * macht den weiteren Code unabhängig davon.
     *
     * @param mixed $daten
     * @return mixed
     */
    protected static function als_array($daten) {
        if (is_object($daten) || is_array($daten)) {
            return json_decode(json_encode($daten), true);
        }
        return $daten;
    }

    /**
     * Ruft eine Methode von Exabis auf und unterdrückt dabei deren Ausgaben.
     *
     * Exabis erzeugt unter neueren PHP-Versionen Hinweise auf veraltete
     * Schreibweisen. Diese würden sonst in der Antwort eines Webservice landen
     * und sie unbrauchbar machen.
     *
     * @param string $methode Name der Methode
     * @param array $argumente Benannte Argumente
     * @return mixed Rückgabe der Methode, als Array
     */
    protected static function rufe_auf(string $methode, array $argumente) {
        $klasse = self::EXACOMP_KLASSE;

        ob_start();
        try {
            $ergebnis = $klasse::$methode(...$argumente);
        } finally {
            ob_end_clean();
        }

        return self::als_array($ergebnis);
    }

    /**
     * Liefert den Kompetenzbaum des Kurses: Themen mit ihren Deskriptoren.
     *
     * @param int $courseid
     * @return array Liste von Themen, jedes mit einer Liste von Deskriptoren
     */
    public static function get_kompetenzbaum(int $courseid): array {
        if (!self::verfuegbar()) {
            return [];
        }

        $themendaten = self::rufe_auf('dakora_get_all_topics_by_course', [
            'courseid' => $courseid,
            'userid' => 0,
            'forall' => true,
            'groupid' => 0,
        ]);

        if (empty($themendaten['topics'])) {
            return [];
        }

        $baum = [];
        foreach ($themendaten['topics'] as $thema) {
            if (empty($thema['visible'])) {
                continue;
            }

            $deskriptoren = self::rufe_auf('dakora_get_all_descriptors', [
                'courseid' => $courseid,
                'topicid' => (int) $thema['topicid'],
                'userid' => 0,
                'forall' => true,
                'editmode' => 0,
            ]);

            if (empty($deskriptoren)) {
                continue;
            }

            $liste = [];
            foreach ($deskriptoren as $d) {
                if (empty($d['visible'])) {
                    continue;
                }
                $liste[] = [
                    'descriptorid' => (int) $d['descriptorid'],
                    'title' => $d['descriptortitle'],
                    'numbering' => (string) $d['numbering'],
                    'niveau' => (string) $d['niveautitle'],
                ];
            }

            if (empty($liste)) {
                continue;
            }

            $baum[] = [
                'topicid' => (int) $thema['topicid'],
                'title' => $thema['topictitle'],
                'numbering' => (string) $thema['numbering'],
                'subject' => $thema['subjecttitle'],
                'descriptors' => $liste,
            ];
        }

        return $baum;
    }

    /**
     * Liefert die bestehenden Zuordnungen des Kurses je Kursmodul.
     *
     * Gelesen wird direkt aus den Tabellen von Exabis, da hierfür kein Dienst
     * bereitsteht. Verwendet werden nur die Felder activityid und courseid der
     * Lernmaterialien sowie descrid und exampid der Verknüpfungstabelle.
     *
     * @param int $courseid
     * @return array Kursmodul-ID als Schlüssel, Liste von Deskriptor-IDs als Wert
     */
    public static function get_zuordnungen(int $courseid): array {
        global $DB;

        if (!self::verfuegbar()) {
            return [];
        }

        // Je Aktivität zählt nur das Lernmaterial mit der kleinsten ID. Nur dieses zeigt
        // Exabis im Tab "Moodle-Aktivitäten verknüpfen" an. Die Anzeige im Assistenten
        // folgt deshalb dieser Regel, damit beide dasselbe zeigen.
        $sql = "SELECT mm.id, e.activityid, mm.descrid
                  FROM {block_exacompexamples} e
                  JOIN {block_exacompdescrexamp_mm} mm ON mm.exampid = e.id
                 WHERE e.courseid = :courseid
                   AND e.activityid > 0
                   AND mm.descrid > 0
                   AND e.id = (SELECT MIN(e2.id)
                                 FROM {block_exacompexamples} e2
                                WHERE e2.courseid = e.courseid
                                  AND e2.activityid = e.activityid)";

        $zuordnungen = [];
        foreach ($DB->get_records_sql($sql, ['courseid' => $courseid]) as $satz) {
            $cmid = (int) $satz->activityid;
            if (!isset($zuordnungen[$cmid])) {
                $zuordnungen[$cmid] = [];
            }
            $zuordnungen[$cmid][] = (int) $satz->descrid;
        }

        return $zuordnungen;
    }

    /**
     * Liefert das Lernmaterial, das Exabis einer Aktivität zuordnet.
     *
     * Gibt es mehrere, nimmt Exabis das erste. Hier zählt entsprechend die kleinste ID.
     *
     * @param int $courseid
     * @param int $cmid
     * @return \stdClass|null Lernmaterial mit seiner ID, oder null wenn es keines gibt
     */
    protected static function finde_lernmaterial(int $courseid, int $cmid): ?\stdClass {
        global $DB;

        $gefunden = $DB->get_records(
            'block_exacompexamples',
            ['courseid' => $courseid, 'activityid' => $cmid],
            'id ASC',
            'id',
            0,
            1
        );

        return $gefunden ? reset($gefunden) : null;
    }

    /**
     * Prüft, ob ein Lernmaterial Verknüpfungen trägt, die keine einfachen Kompetenzen sind.
     *
     * Dazu zählen die Verknüpfung mit einem Themenübergreifenden Fach und die Markierung
     * für freies Material. Beide haben keine Kompetenz-ID. Exabis löscht beim Setzen der
     * Zuordnung alle Verknüpfungen eines Lernmaterials; solche Zeilen würden mit verloren
     * gehen.
     *
     * @param int $lernmaterialid
     * @return bool
     */
    protected static function hat_fremde_verknuepfungen(int $lernmaterialid): bool {
        global $DB;

        return $DB->record_exists_select(
            'block_exacompdescrexamp_mm',
            'exampid = :exampid AND (descrid IS NULL OR descrid <= 0)',
            ['exampid' => $lernmaterialid]
        );
    }

    /**
     * Lädt die Funktionsbibliothek von Exabis und prüft die benötigten Funktionen.
     *
     * Die Funktionen sind keine zugesicherte Schnittstelle. Da der Aufruf mit
     * Positionsargumenten erfolgt, wird zusätzlich die Reihenfolge der Parameter
     * kontrolliert. Weicht sie ab, wird nichts geschrieben.
     *
     * @return bool
     */
    protected static function lade_bibliothek(): bool {
        $verzeichnis = \core_component::get_component_directory('block_exacomp');
        if (!$verzeichnis || !file_exists($verzeichnis . '/lib/lib.php')) {
            return false;
        }

        ob_start();
        try {
            require_once($verzeichnis . '/lib/lib.php');
        } finally {
            ob_end_clean();
        }

        $benoetigt = ['block_exacomp_relate_example_to_activity', 'block_exacomp_is_teacher'];
        foreach ($benoetigt as $funktion) {
            if (!function_exists($funktion)) {
                return false;
            }
        }

        $namen = [];
        $parameter = (new \ReflectionFunction('block_exacomp_relate_example_to_activity'))->getParameters();
        foreach ($parameter as $eintrag) {
            $namen[] = $eintrag->getName();
        }

        return array_slice($namen, 0, 3) === ['courseid', 'activityid', 'descriptors'];
    }

    /**
     * Ruft eine Funktion von Exabis auf und unterdrückt dabei deren Ausgaben.
     *
     * @param string $funktion Name der Funktion
     * @param array $argumente Positionsargumente
     * @return mixed Rückgabe der Funktion
     */
    protected static function rufe_funktion_auf(string $funktion, array $argumente) {
        ob_start();
        try {
            return $funktion(...$argumente);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Setzt die Kompetenzen einer Aktivität auf die übergebene, vollständige Auswahl.
     *
     * Exabis ersetzt dabei alle bisherigen Zuordnungen des Lernmaterials durch die
     * übergebenen. Kompetenzen, die nicht mehr in der Auswahl stehen, werden damit
     * entfernt. Gibt es zur Aktivität noch kein Lernmaterial, legt Exabis eines an.
     * Ein vorhandenes Lernmaterial wird nicht verändert, nur seine Zuordnungen.
     *
     * Die Auswahl darf nicht leer sein: Ein Lernmaterial ohne Kompetenz erscheint im
     * Kompetenzraster nicht mehr.
     *
     * @param int $courseid
     * @param int $cmid Kursmodul, dem die Kompetenzen zugeordnet werden
     * @param array $descriptorids Vollständige Auswahl der Deskriptor-IDs
     * @return void
     */
    public static function setze_zuordnung(int $courseid, int $cmid, array $descriptorids): void {
        if (!self::verfuegbar() || !self::lade_bibliothek()) {
            throw new \moodle_exception('exacomp_nichtverfuegbar', 'local_kursassistent');
        }

        $auswahl = [];
        foreach ($descriptorids as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $auswahl, true)) {
                $auswahl[] = $id;
            }
        }

        if (empty($auswahl)) {
            throw new \moodle_exception('exacomp_keineauswahl', 'local_kursassistent');
        }

        // Die Funktion von Exabis prüft keine Berechtigung, das geschieht hier.
        if (!self::rufe_funktion_auf('block_exacomp_is_teacher', [$courseid])) {
            throw new \moodle_exception('exacomp_keinelehrkraft', 'local_kursassistent');
        }

        $material = self::finde_lernmaterial($courseid, $cmid);
        if ($material !== null && self::hat_fremde_verknuepfungen((int) $material->id)) {
            throw new \moodle_exception('exacomp_fremdeverknuepfung', 'local_kursassistent');
        }

        self::rufe_funktion_auf('block_exacomp_relate_example_to_activity', [$courseid, $cmid, $auswahl]);
    }
}
