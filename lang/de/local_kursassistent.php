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

/**
 * Deutsche Sprachdatei für local_kursassistent.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Kursassistent';
$string['kursassistent:use'] = 'Kursassistent im Kurs verwenden';
$string['kursassistent:manage'] = 'Bausteine des Kursassistenten verwalten';

$string['assistentbutton'] = 'Kursassistent';
$string['modaltitel'] = 'Kursassistent';
$string['modalbeschreibung'] = 'Wähle die Bausteine, die du deinem Kurs hinzufügen möchtest.';
$string['zielabschnitt'] = 'Zielabschnitt';
$string['bausteineeinfuegen'] = 'Bausteine einfügen';
$string['abbrechen'] = 'Abbrechen';

$string['bausteineverwalten'] = 'Bausteine verwalten';
$string['bausteineverwalten_desc'] = 'Hier legst du fest, welche Bausteine Lehrkräfte im Kursassistenten sehen und auswählen können.';
$string['neuerbaustein'] = 'Neuer Baustein';
$string['titel'] = 'Titel';
$string['icon'] = 'Icon';
$string['icon_target-arrow'] = 'Zielscheibe';
$string['icon_key'] = 'Schlüssel';
$string['icon_file'] = 'Datei';
$string['icon_image'] = 'Bild';
$string['icon_video'] = 'Video';
$string['icon_checklist'] = 'Checkliste';
$string['icon_file-text'] = 'Textdokument';
$string['icon_bulb'] = 'Glühbirne (Tipp)';
$string['icon_alert-circle'] = 'Ausrufezeichen (Hinweis)';
$string['icon_list-numbers'] = 'Nummerierte Liste (Anleitung)';
$string['icon_clipboard-check'] = 'Klemmbrett (Arbeitsanweisung)';
$string['icon_link'] = 'Link (URL)';
$string['typ'] = 'Typ';
$string['typ_text'] = 'Text';
$string['typ_datei'] = 'Datei';
$string['typ_bild'] = 'Bild';
$string['typ_video'] = 'Video';
$string['typ_aktivitaet'] = 'Aktivität';
$string['modname'] = 'Aktivitätstyp';
$string['bittewaehlen'] = 'Bitte wählen …';
$string['platzhalter'] = 'Platzhaltertext (nur bei Typ „Text“)';
$string['status'] = 'Status';
$string['aktiv'] = 'Aktiv';
$string['deaktiviert'] = 'Deaktiviert';
$string['bearbeiten'] = 'Bearbeiten';
$string['loeschen'] = 'Löschen';
$string['loeschen_bestaetigung'] = 'Soll dieser Baustein wirklich gelöscht werden?';
$string['loeschen_bestaetigung_text'] = 'Soll der Baustein „{$a}" wirklich gelöscht werden? Bereits in Kurse eingefügte Inhalte bleiben erhalten.';
$string['geloescht'] = 'Der Baustein wurde gelöscht.';

$string['videoaktiv'] = 'Video-Funktion aktivieren';
$string['videoaktiv_desc'] = 'Legt fest, ob der Bausteintyp „Video“ im Kursassistenten überhaupt sichtbar ist. Innerhalb des Bausteins entscheidet die Lehrkraft dann selbst, ob sie ein Video verlinkt, hochlädt oder einen Link einfügt – abhängig davon, welche Plugins (repository_peertubeoauth, local_peertubeupload) installiert sind.';

$string['kanalhinweis_titel'] = 'Kanal noch nicht eingerichtet';
$string['kanalhinweis_text'] = 'Bevor du ein Video hochladen kannst, muss einmalig dein PeerTube-Kanal eingerichtet werden. Das dauert nur einen Moment.';
$string['kanaleinrichten'] = 'Kanal jetzt einrichten';
$string['kanalbereit_titel'] = 'Kanal bereit';
$string['kanalbereit_text'] = 'Dein Kanal {$a} ist eingerichtet. Wähle eine Videodatei zum Hochladen.';

$string['dateiauswaehlen'] = 'Datei auswählen';
$string['dateiauswaehlen_hinweis'] = 'Wähle eine Datei für den Baustein „{$a}“ aus. Es stehen alle an dieser Schule konfigurierten Speicherorte zur Auswahl (z. B. Datei-Upload, Nextcloud, PeerTube).';
$string['uebernehmen'] = 'Übernehmen';
$string['bausteineingefuegt'] = 'Baustein wurde eingefügt.';
$string['dateiwirdverarbeitet'] = 'Datei wird verarbeitet …';
$string['dateifehlt'] = 'Es wurde keine Datei gefunden.';
$string['anleitungtitel'] = 'Anleitung zum Kursassistenten';
$string['anleitung_inhalt'] = 'Anleitungstext für Lehrkräfte';
$string['anleitung_inhalt_desc'] = 'Dieser Text wird Lehrkräften angezeigt, wenn sie im Kurs auf „Anleitung" klicken. Frei editierbar - z. B. für schulspezifische Hinweise zur Nutzung des Kursassistenten.';
$string['anleitung_inhalt_default'] = '<p>Der Kursassistent hilft dir, Kursabschnitte schnell zu gestalten. Öffne ihn über den Button „Kursassistent" oben im Kurs, wähle die gewünschten Bausteine aus und fülle sie aus.</p>';
$string['anleitung_leer'] = 'Für diese Schule wurde noch keine Anleitung hinterlegt.';
$string['templatewizard_installiert'] = 'Das Plugin local_coursetemplatewizard ist installiert. Die Kategorie mit den Vorlagenkursen wird dort unter "Kursvorlagen-Assistent" eingestellt.';
$string['templatewizard_fehlt'] = 'Die Funktion "Kursvorlage übernehmen" benötigt das zusätzliche Plugin local_coursetemplatewizard. Ohne dieses Plugin erscheint der Baustein im Kursassistenten nicht.';
$string['kurseinrichtung'] = 'Kurseinrichtung';
$string['kursformataendern'] = 'Kursformat ändern';
$string['kursvorlageuebernehmen'] = 'Kursvorlage übernehmen';
$string['zurueckzumkurs'] = 'Zurück zum Kurs';
$string['invalidtype'] = 'Ungültiger Bausteintyp für diese Aktion.';

$string['privacy:metadata:local_kursassistent_log'] = 'Protokoll der von einer Lehrkraft im Kursassistenten eingefügten Bausteine.';
$string['privacy:metadata:local_kursassistent_log:userid'] = 'Die ID der Nutzerin/des Nutzers, die/der den Baustein eingefügt hat.';
$string['privacy:metadata:local_kursassistent_log:courseid'] = 'Der Kurs, in dem der Baustein eingefügt wurde.';
$string['privacy:metadata:local_kursassistent_log:typeid'] = 'Der verwendete Bausteintyp.';
$string['privacy:metadata:local_kursassistent_log:timecreated'] = 'Zeitpunkt der Einfügung.';
