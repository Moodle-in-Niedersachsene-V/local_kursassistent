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
 * English language file for local_kursassistent.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Course assistant';
$string['kursassistent:use'] = 'Use the course assistant in a course';
$string['kursassistent:manage'] = 'Manage course assistant building blocks';

$string['assistentbutton'] = 'Course assistant';
$string['modaltitel'] = 'Course assistant';
$string['modalbeschreibung'] = 'Choose the building blocks you want to add to your course.';
$string['zielabschnitt'] = 'Target section';
$string['bausteineeinfuegen'] = 'Insert building blocks';
$string['abbrechen'] = 'Cancel';

$string['bausteineverwalten'] = 'Manage building blocks';
$string['bausteineverwalten_desc'] = 'Configure which building blocks teachers can select in the course assistant.';
$string['neuerbaustein'] = 'New building block';
$string['titel'] = 'Title';
$string['icon'] = 'Icon';
$string['icon_target-arrow'] = 'Target';
$string['icon_key'] = 'Key';
$string['icon_file'] = 'File';
$string['icon_image'] = 'Image';
$string['icon_video'] = 'Video';
$string['icon_checklist'] = 'Checklist';
$string['icon_file-text'] = 'Text document';
$string['icon_bulb'] = 'Lightbulb (tip)';
$string['icon_alert-circle'] = 'Exclamation mark (note)';
$string['icon_list-numbers'] = 'Numbered list (instructions)';
$string['icon_clipboard-check'] = 'Clipboard (task)';
$string['typ'] = 'Type';
$string['typ_text'] = 'Text';
$string['typ_datei'] = 'File';
$string['typ_bild'] = 'Image';
$string['typ_video'] = 'Video';
$string['platzhalter'] = 'Placeholder text (type "Text" only)';
$string['status'] = 'Status';
$string['aktiv'] = 'Active';
$string['deaktiviert'] = 'Disabled';
$string['bearbeiten'] = 'Edit';
$string['loeschen'] = 'Delete';
$string['loeschen_bestaetigung'] = 'Delete this building block?';

$string['videoaktiv'] = 'Enable video feature';
$string['videoaktiv_desc'] = 'Controls whether the "Video" building block type is visible in the course assistant at all. Within the block, the teacher then chooses whether to link, upload, or paste a link - depending on which plugins (repository_peertubeoauth, local_peertubeupload) are installed.';

$string['kanalhinweis_titel'] = 'Channel not set up yet';
$string['kanalhinweis_text'] = 'Before you can upload a video, your PeerTube channel needs to be set up once. This only takes a moment.';
$string['kanaleinrichten'] = 'Set up channel now';
$string['kanalbereit_titel'] = 'Channel ready';
$string['kanalbereit_text'] = 'Your channel {$a} is ready. Choose a video file to upload.';

$string['dateiauswaehlen'] = 'Select file';
$string['dateiauswaehlen_hinweis'] = 'Select a file for the "{$a}" building block. All storage locations configured for this school are available (e.g. file upload, Nextcloud, PeerTube).';
$string['uebernehmen'] = 'Apply';
$string['bausteineingefuegt'] = 'Building block was inserted.';
$string['dateiwirdverarbeitet'] = 'Processing file …';
$string['dateifehlt'] = 'No file was found.';
$string['invalidtype'] = 'Invalid building block type for this action.';

$string['privacy:metadata:local_kursassistent_log'] = 'Log of building blocks inserted by a teacher via the course assistant.';
$string['privacy:metadata:local_kursassistent_log:userid'] = 'The ID of the user who inserted the building block.';
$string['privacy:metadata:local_kursassistent_log:courseid'] = 'The course the building block was inserted into.';
$string['privacy:metadata:local_kursassistent_log:typeid'] = 'The building block type used.';
$string['privacy:metadata:local_kursassistent_log:timecreated'] = 'The time the building block was inserted.';
