/**
 * Rückkehr in den Kursassistenten nach dem Moodle-Formular einer Aktivität.
 *
 * Der Assistent wechselt zum Formular im selben Tab und merkt sich vorher im
 * sessionStorage, dass er danach wieder geöffnet werden soll. Die Merkung gilt nur
 * für diesen Tab und läuft nach 30 Minuten ab. Dieses Modul kapselt Speicher und
 * Gültigkeit und zeigt auf dem Formular und auf anderen Seiten des Kurses einen
 * Hinweis mit dem Weg zurück.
 *
 * @module     local_kursassistent/rueckkehr
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {

    var SCHLUESSEL = 'local_kursassistent_rueckkehr';
    var GUELTIG_MS = 30 * 60 * 1000;

    /**
     * Liefert den sessionStorage oder null, wenn der Browser ihn nicht erlaubt.
     *
     * @return {Storage|null}
     */
    function speicher() {
        try {
            return window.sessionStorage;
        } catch (e) {
            return null;
        }
    }

    /**
     * Entfernt die Merkung.
     */
    function loesche() {
        var s = speicher();
        if (!s) {
            return;
        }
        try {
            s.removeItem(SCHLUESSEL);
        } catch (e) {
            return;
        }
    }

    /**
     * Liest die Merkung, sofern sie zu diesem Kurs gehört und noch gültig ist.
     *
     * @param {Number} courseid
     * @return {Object|null} Gemerkte Daten oder null
     */
    function lese(courseid) {
        var s = speicher();
        var daten = null;
        if (!s) {
            return null;
        }
        try {
            daten = JSON.parse(s.getItem(SCHLUESSEL));
        } catch (e) {
            return null;
        }
        if (!daten || daten.courseid !== courseid || !daten.zeit) {
            return null;
        }
        if (Date.now() - daten.zeit > GUELTIG_MS) {
            loesche();
            return null;
        }
        return daten;
    }

    /**
     * Merkt sich, dass der Assistent nach dem Formular wieder geöffnet werden soll.
     *
     * @param {Object} daten courseid, art, titel, sectionnum und je nach Art weitere Angaben
     */
    function merke(daten) {
        var s = speicher();
        if (!s) {
            return;
        }
        daten.zeit = Date.now();
        try {
            s.setItem(SCHLUESSEL, JSON.stringify(daten));
        } catch (e) {
            return;
        }
    }

    /**
     * Liest, ob die Seite, zu der der Assistent gewechselt hat, erfolgreich abgeschlossen wurde.
     *
     * Die Seite hängt dazu den Parameter kaergebnis an die Adresse des Kurses. Er wird hier
     * gelesen und wieder aus der Adresse entfernt, damit ein Neuladen nichts auslöst.
     *
     * @return {String|null} Wert des Parameters oder null
     */
    function leseErgebnis() {
        var adresse = new window.URL(window.location.href);
        var ergebnis = adresse.searchParams.get('kaergebnis');
        if (ergebnis === null) {
            return null;
        }
        adresse.searchParams.delete('kaergebnis');
        try {
            window.history.replaceState(window.history.state, '', adresse.toString());
        } catch (e) {
            return ergebnis;
        }
        return ergebnis;
    }

    /**
     * Prüft, ob die aktuelle Seite die Zielseite ist, zu der der Assistent gewechselt hat.
     *
     * Das ist bei einer Aktivität das Formular zum Anlegen genau dieses Typs und bei einer
     * Datei die Auswahlseite des Assistenten.
     *
     * @param {Object} daten Gemerkte Daten
     * @return {Boolean}
     */
    function istZielseite(daten) {
        var pfad = window.location.pathname;
        if (daten.art === 'datei') {
            return pfad.indexOf('/local/kursassistent/pick_file.php') !== -1;
        }
        if (pfad.indexOf('/course/modedit.php') === -1) {
            return false;
        }
        var parameter = new window.URLSearchParams(window.location.search);
        return parameter.get('add') === daten.modname;
    }

    /**
     * Liefert den Text des Hinweises.
     *
     * @param {Object} daten Gemerkte Daten
     * @param {Boolean} zielseite Ob die Seite die Zielseite des Assistenten ist
     * @return {String}
     */
    function baueHinweistext(daten, zielseite) {
        if (!zielseite) {
            return 'Du kommst vom Kursassistenten.';
        }
        if (daten.art === 'datei') {
            return 'Du wählst eine Datei für den Kursassistenten aus. Nach dem Übernehmen geht es dort weiter.';
        }
        return 'Du legst diese Aktivität über den Kursassistenten an. Nach dem Speichern geht es dort weiter.';
    }

    /**
     * Baut den Hinweis mit dem Weg zurück zum Assistenten.
     *
     * @param {Object} daten Gemerkte Daten
     * @param {Number} courseid
     * @return {jQuery}
     */
    function baueHinweis(daten, courseid) {
        var formular = istZielseite(daten);
        var kursUrl = M.cfg.wwwroot + '/course/view.php?id=' + courseid;
        var $hinweis = $('<div>', {
            'class': 'alert alert-info d-flex align-items-center local-kursassistent-rueckkehr',
            role: 'status'
        });

        $hinweis.append($('<span>', {
            'class': 'flex-grow-1',
            text: baueHinweistext(daten, formular)
        }));

        if (formular) {
            $hinweis.append($('<a>', {
                href: kursUrl,
                'class': 'alert-link ml-3 text-nowrap',
                text: 'Abbrechen und zurück'
            }));
            return $hinweis;
        }

        $hinweis.append($('<a>', {
            href: kursUrl,
            'class': 'btn btn-secondary btn-sm ml-3 text-nowrap',
            text: 'Zurück zum Kursassistenten'
        }));
        var $aus = $('<button>', {
            type: 'button',
            'class': 'btn btn-link btn-sm ml-1 text-nowrap',
            text: 'Ausblenden'
        });
        $aus.on('click', function() {
            loesche();
            $hinweis.remove();
        });
        $hinweis.append($aus);
        return $hinweis;
    }

    return {
        lese: lese,
        merke: merke,
        loesche: loesche,
        leseErgebnis: leseErgebnis,

        /**
         * Zeigt auf Seiten außerhalb der Kursansicht den Hinweis, solange die Merkung gilt.
         *
         * @param {Number} courseid
         */
        init: function(courseid) {
            var daten = lese(courseid);
            if (!daten) {
                return;
            }
            var $ziel = $('[role="main"]').first();
            if (!$ziel.length) {
                $ziel = $('#region-main').first();
            }
            if (!$ziel.length) {
                return;
            }
            $ziel.prepend(baueHinweis(daten, courseid));
        }
    };
});
