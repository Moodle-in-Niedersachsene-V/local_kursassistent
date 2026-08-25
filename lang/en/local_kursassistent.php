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

$string['abbrechen'] = 'Cancel';
$string['aktiv'] = 'Active';
$string['aktiv_help'] = 'Only active building blocks appear in the course assistant. A deactivated building block is kept here and can be switched back on later. Content already inserted into courses is unaffected.';
$string['anleitung_inhalt'] = 'Guide text for teachers';
$string['anleitung_inhalt_default'] = '<p>The course assistant helps you set up course sections quickly. Open it via the "Course assistant" button at the top of the course, select the building blocks you want and fill them in.</p>';
$string['anleitung_inhalt_desc'] = 'This text is shown to teachers when they click "Guide" in the course. Freely editable - e.g. for school-specific notes on using the course assistant.';
$string['anleitung_leer'] = 'No guide has been set up for this school yet.';
$string['anleitungtitel'] = 'Course assistant guide';
$string['assistentbutton'] = 'Course assistant';
$string['bausteineeinfuegen'] = 'Insert building blocks';
$string['bausteineingefuegt'] = 'Building block was inserted.';
$string['bausteineverwalten'] = 'Manage building blocks';
$string['bausteineverwalten_desc'] = 'Configure which building blocks teachers can select in the course assistant.';
$string['bearbeiten'] = 'Edit';
$string['bittewaehlen'] = 'Please choose …';
$string['dateiauswaehlen'] = 'Select file';
$string['dateiauswaehlen_help'] = 'The "Choose a file" button opens Moodle\'s file picker. All storage locations configured on this site are available there, such as upload from your own computer or a connected cloud storage. Images are displayed directly in the building block, all other files as a download link.';
$string['dateiauswaehlen_hinweis'] = 'Select a file for the "{$a}" building block. All storage locations configured for this school are available (e.g. file upload, Nextcloud, PeerTube).';
$string['dateifehlt'] = 'No file was found.';
$string['dateiwirdverarbeitet'] = 'Processing file …';
$string['deaktiviert'] = 'Disabled';
$string['geloescht'] = 'The building block was deleted.';
$string['icon'] = 'Icon';
$string['icon_alert-circle'] = 'Exclamation mark (note)';
$string['icon_bulb'] = 'Lightbulb (tip)';
$string['icon_checklist'] = 'Checklist';
$string['icon_clipboard-check'] = 'Clipboard (task)';
$string['icon_file'] = 'File';
$string['icon_file-text'] = 'Text document';
$string['icon_help'] = 'The symbol shown in the course assistant and in the heading of the created building block. Twelve bundled symbols are available; they are provided by the plugin itself and require no internet connection.';
$string['icon_image'] = 'Image';
$string['icon_key'] = 'Key';
$string['icon_link'] = 'Link (URL)';
$string['icon_list-numbers'] = 'Numbered list (instructions)';
$string['icon_target-arrow'] = 'Target';
$string['icon_video'] = 'Video';
$string['invalidtype'] = 'Invalid building block type for this action.';
$string['kanalbereit_text'] = 'Your channel {$a} is ready. Choose a video file to upload.';
$string['kanalbereit_titel'] = 'Channel ready';
$string['kanaleinrichten'] = 'Set up channel now';
$string['kanalhinweis_text'] = 'Before you can upload a video, your PeerTube channel needs to be set up once. This only takes a moment.';
$string['kanalhinweis_titel'] = 'Channel not set up yet';
$string['kursassistent:manage'] = 'Manage course assistant building blocks';
$string['kursassistent:use'] = 'Use the course assistant in a course';
$string['kurseinrichtung'] = 'Course setup';
$string['kursformataendern'] = 'Change course format';
$string['kursvorlageuebernehmen'] = 'Apply course template';
$string['loeschen'] = 'Delete';
$string['loeschen_bestaetigung'] = 'Delete this building block?';
$string['loeschen_bestaetigung_text'] = 'Really delete the building block "{$a}"? Content already inserted into courses remains unchanged.';
$string['modalbeschreibung'] = 'Choose the building blocks you want to add to your course.';
$string['modaltitel'] = 'Course assistant';
$string['modname'] = 'Activity type';
$string['modname_help'] = 'Only relevant for the "Activity" type. Defines which Moodle activity is created when a teacher selects the building block. All activities installed on this site are available.';
$string['neuerbaustein'] = 'New building block';
$string['platzhalter'] = 'Placeholder text (type "Text" only)';
$string['platzhalter_help'] = 'Only relevant for the "Text" type. This text is pre-filled in the input field when the teacher selects the building block. It serves as a writing aid and can be overwritten entirely. An opening phrase such as "After this section, participants will be able to ..." works well.';
$string['pluginname'] = 'Course assistant';
$string['privacy:metadata:local_kursassistent_log'] = 'Log of building blocks inserted by a teacher via the course assistant.';
$string['privacy:metadata:local_kursassistent_log:courseid'] = 'The course the building block was inserted into.';
$string['privacy:metadata:local_kursassistent_log:timecreated'] = 'The time the building block was inserted.';
$string['privacy:metadata:local_kursassistent_log:typeid'] = 'The building block type used.';
$string['privacy:metadata:local_kursassistent_log:userid'] = 'The ID of the user who inserted the building block.';
$string['status'] = 'Status';
$string['titel'] = 'Title';
$string['titel_help'] = 'The name teachers see in the course assistant. It also appears as the heading of the building block created in the course. Short, descriptive names work best, for example "Learning objectives" or "Assignment".';
$string['typ'] = 'Type';
$string['typ_aktivitaet'] = 'Activity';
$string['typ_bild'] = 'Image';
$string['typ_datei'] = 'File';
$string['typ_help'] = 'Determines what happens when the building block is selected.<ul><li><strong>Text</strong>: The teacher enters text that appears as a text area in the course.</li><li><strong>File</strong> and <strong>Image</strong>: The file picker opens with all configured storage locations.</li><li><strong>Video</strong>: Choice between an existing PeerTube video, an own upload, or a link to another platform.</li><li><strong>Activity</strong>: The familiar form for creating a Moodle activity opens.</li></ul>Grouping within the course assistant follows automatically from this choice.';
$string['typ_text'] = 'Text';
$string['typ_video'] = 'Video';
$string['uebernehmen'] = 'Apply';
$string['videoaktiv'] = 'Enable video feature';
$string['videoaktiv_desc'] = 'Controls whether the "Video" building block type is visible in the course assistant at all. Within the block, the teacher then chooses whether to link, upload, or paste a link - depending on which plugins (repository_peertubeoauth, local_peertubeupload) are installed.';
$string['vorlagen_auswaehlen'] = 'Apply this template';
$string['vorlagen_bestaetigung'] = 'Add the content of template "{$a->vorlage}" to course "{$a->kurs}"? Existing content is kept and the template is added to it. User data such as submissions or grades is not transferred.';
$string['vorlagen_bittewarten'] = 'Depending on the size of the template this may take a moment. Please do not close this window.';
$string['vorlagen_fertig'] = 'The content of template "{$a}" has been added to the course.';
$string['vorlagen_hinweis'] = 'Choose a template. Its content will be added to your course; existing content is kept.';
$string['vorlagen_keinadmin'] = 'No site administrator could be determined for the backup.';
$string['vorlagen_keinekategorie'] = 'No course category for templates has been configured.';
$string['vorlagen_keinevorlage'] = 'The selected course is not in the configured course category for templates.';
$string['vorlagen_leer'] = 'The configured course category currently contains no courses.';
$string['vorlagen_nichtkonfiguriert'] = 'No course category with templates has been configured for this site yet. An administrator can set it in the course assistant settings.';
$string['vorlagen_uebernahmelaeuft'] = 'Applying template';
$string['vorlagenkategorie'] = 'Course category containing the templates';
$string['vorlagenkategorie_desc'] = 'All courses in this category are offered to teachers as templates in the course assistant. Without a selection, the "Apply course template" building block does not appear.';
$string['vorlagenkategorie_keine'] = 'No selection - feature disabled';
$string['zielabschnitt'] = 'Target section';
$string['zurueckzumkurs'] = 'Back to course';
