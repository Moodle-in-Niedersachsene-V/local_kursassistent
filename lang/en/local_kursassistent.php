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
$string['abschluss_automatisch'] = 'Automatic';
$string['abschluss_automatisch_alt'] = 'Automatic (on view)';
$string['abschluss_bei_abgabe'] = 'On submission';
$string['abschluss_bei_ansicht'] = 'On view';
$string['abschluss_bei_bestehen'] = 'On passing grade';
$string['abschluss_bei_bewertung'] = 'Require grade';
$string['abschluss_keine'] = 'None';
$string['abschluss_manuell'] = 'Manual';
$string['abschlussverfolgung'] = 'Completion tracking';
$string['abschlussverfolgung_desc'] = 'Set how activity completion is tracked.';
$string['abschnitt_als_vorlage'] = 'Save section as template';
$string['abschnitt_als_vorlage_desc'] = 'Captures all elements from the selected section as a template.';
$string['abschnittsvorlagen'] = 'Section templates';
$string['abschnittsvorlagen_desc'] = 'Apply ready-made templates to course sections or create your own templates from your current building block selection.';
$string['aktiv'] = 'Active';
$string['aktiv_help'] = 'Only active building blocks appear in the course assistant. A deactivated building block is kept here and can be switched back on later. Content already inserted into courses is unaffected.';
$string['alle_ausblenden'] = 'Hide all';
$string['alle_automatisch'] = 'All automatic';
$string['alle_einblenden'] = 'Show all';
$string['alle_keine'] = 'Disable all';
$string['alle_manuell'] = 'All manual';
$string['anleitung_inhalt'] = 'Guide text for teachers';
$string['anleitung_inhalt_default'] = '<p>The course assistant helps you set up course sections quickly. Open it via the "Course assistant" button at the top of the course, select the building blocks you want and fill them in.</p>';
$string['anleitung_inhalt_desc'] = 'This text is shown to teachers when they click "Guide" in the course. Freely editable - e.g. for school-specific notes on using the course assistant.';
$string['anleitung_leer'] = 'No guide has been set up for this school yet.';
$string['anleitungtitel'] = 'Course assistant guide';
$string['assistent_help'] = '1. First select the <strong>target section</strong> where content will be inserted.<br>2. Then choose the desired <strong>building blocks</strong> from the categories (learning information, learning material, activities).<br>3. Alternatively, use <strong>Course setup</strong> to set a course format, apply a course template, or apply a section template.';
$string['assistent_help_title'] = 'How the course assistant works';
$string['assistentbutton'] = 'Course assistant';
$string['bausteineeinfuegen'] = 'Insert building blocks';
$string['bausteineingefuegt'] = 'Building block was inserted.';
$string['bausteineverwalten'] = 'Manage building blocks';
$string['bausteineverwalten_desc'] = 'Configure which building blocks teachers can select in the course assistant.';
$string['bearbeiten'] = 'Edit';
$string['bittewaehlen'] = 'Please choose …';
$string['completion_not_enabled'] = 'Completion tracking is not enabled in this course. Please enable it in the course settings first.';
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
$string['kat_aktivitaet_help'] = 'Create Moodle <strong>activities</strong> such as assignments, quizzes, or forums. The familiar Moodle editing page opens in a new tab so you can continue working in the assistant.';
$string['kat_aktivitaet_help_title'] = 'Activities';
$string['kat_info_help'] = '<strong>Text</strong> building blocks: Create text sections such as learning objectives, notes, or explanations. The text appears directly in the course section.';
$string['kat_info_help_title'] = 'Learning information';
$string['kat_kurseinrichtung_help'] = 'Course-wide settings: Change the <strong>course format</strong> (e.g. topics or weekly format), apply a <strong>course template</strong>, or apply a <strong>section template</strong> to a specific section.';
$string['kat_kurseinrichtung_help_title'] = 'Course setup';
$string['kat_lernpfade_help'] = '<strong>Completion tracking</strong>: Set how activity completion is tracked.<br><strong>Prerequisites</strong>: Define which condition must be met before an activity becomes accessible.<br><strong>Learning path</strong>: Create a linear learning path where each activity unlocks only after the previous one is completed.<br><strong>Overview</strong>: Shows all completion and prerequisite settings in the course.<br><strong>Statistics</strong>: Shows student completion progress.';
$string['kat_lernpfade_help_title'] = 'Learning paths and statistics';
$string['kat_material_help'] = 'Building blocks for <strong>files</strong>, <strong>images</strong>, and <strong>videos</strong>. The file picker provides access to all configured storage locations (e.g. file upload, Nextcloud, PeerTube).';
$string['kat_material_help_title'] = 'Learning material';
$string['kommentar'] = 'Comment';
$string['kommentar_abbrechen'] = 'Cancel';
$string['kommentar_fehler'] = 'Comment could not be saved.';
$string['kommentar_hinweis_lehrkraft'] = 'Note from your teacher:';
$string['kommentar_keinteilnehmer'] = 'No comment can be saved for this person because they are not enrolled in the course.';
$string['kommentar_platzhalter'] = 'Comment …';
$string['kommentar_speichern'] = 'Save';
$string['kursassistent:manage'] = 'Manage course assistant building blocks';
$string['kursassistent:use'] = 'Use the course assistant in a course';
$string['kursassistent:viewownprogress'] = 'View own completion progress in a course';
$string['kurseinrichtung'] = 'Course setup';
$string['kursformataendern'] = 'Change course format';
$string['kursvorlageuebernehmen'] = 'Apply course template';
$string['lernpfad'] = 'Create learning path';
$string['lernpfad_beschreibung'] = 'Create a linear learning path: each activity is unlocked only after the previous one is completed.';
$string['lernpfad_erstellt'] = 'Learning path created: {$a} links set.';
$string['lernpfad_mindestens_zwei'] = 'A learning path requires at least two activities.';
$string['lernpfad_starten'] = 'Create learning path';
$string['loeschen'] = 'Delete';
$string['loeschen_bestaetigung'] = 'Delete this building block?';
$string['loeschen_bestaetigung_text'] = 'Really delete the building block "{$a}"? Content already inserted into courses remains unchanged.';
$string['meinfortschritt'] = 'My progress';
$string['modalbeschreibung'] = 'Choose the building blocks you want to add to your course.';
$string['modaltitel'] = 'Course assistant';
$string['modname'] = 'Activity type';
$string['modname_help'] = 'Only relevant for the "Activity" type. Defines which Moodle activity is created when a teacher selects the building block. All activities installed on this site are available.';
$string['neuerbaustein'] = 'New building block';
$string['platzhalter'] = 'Placeholder text (type "Text" only)';
$string['platzhalter_help'] = 'Only relevant for the "Text" type. This text is pre-filled in the input field when the teacher selects the building block. It serves as a writing aid and can be overwritten entirely. An opening phrase such as "After this section, participants will be able to ..." works well.';
$string['pluginname'] = 'Course assistant';
$string['privacy:metadata:local_kursassistent_comment'] = 'Teacher comments on the learning progress of individual students.';
$string['privacy:metadata:local_kursassistent_comment:commenttext'] = 'The comment text.';
$string['privacy:metadata:local_kursassistent_comment:studentid'] = 'The ID of the student the comment was written for.';
$string['privacy:metadata:local_kursassistent_comment:teacherid'] = 'The ID of the teacher who wrote the comment.';
$string['privacy:metadata:local_kursassistent_comment:timecreated'] = 'Time of creation.';
$string['privacy:metadata:local_kursassistent_log'] = 'Log of building blocks inserted by a teacher via the course assistant.';
$string['privacy:metadata:local_kursassistent_log:courseid'] = 'The course the building block was inserted into.';
$string['privacy:metadata:local_kursassistent_log:timecreated'] = 'The time the building block was inserted.';
$string['privacy:metadata:local_kursassistent_log:typeid'] = 'The building block type used.';
$string['privacy:metadata:local_kursassistent_log:userid'] = 'The ID of the user who inserted the building block.';
$string['privacy:metadata:local_kursassistent_sectpl'] = 'Section templates created by teachers.';
$string['privacy:metadata:local_kursassistent_sectpl:description'] = 'The description of the template.';
$string['privacy:metadata:local_kursassistent_sectpl:name'] = 'The name of the template.';
$string['privacy:metadata:local_kursassistent_sectpl:timecreated'] = 'Time the template was created.';
$string['privacy:metadata:local_kursassistent_sectpl:userid'] = 'The ID of the user who created the template.';
$string['sichtbar'] = 'Visible';
$string['sichtbarkeit'] = 'Visibility';
$string['sichtbarkeit_abschnitt'] = 'Show/hide section';
$string['sichtbarkeit_aendern'] = 'Change visibility';
$string['sichtbarkeit_aktivitaet'] = 'Show/hide activity';
$string['sichtbarkeit_gespeichert'] = 'Visibility has been updated.';
$string['statistik'] = 'Statistics';
$string['statistik_abgeschlossen'] = 'Completed';
$string['statistik_aktivitaeten'] = 'Activities';
$string['statistik_beschreibung'] = 'Shows student completion progress for all activities with completion tracking.';
$string['statistik_detail_ausblenden'] = 'Hide detail table';
$string['statistik_detail_einblenden'] = 'Show detail table';
$string['statistik_gesamt'] = 'Total';
$string['statistik_gesamtfortschritt'] = 'Overall progress';
$string['statistik_keine_aktivitaeten'] = 'There are no activities with completion tracking in this course.';
$string['statistik_keine_teilnehmer'] = 'There are no students enrolled in this course.';
$string['statistik_teilnehmer'] = 'Students';
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
$string['uebersicht'] = 'Overview';
$string['uebersicht_beschreibung'] = 'Overview of all completion and restriction settings in this course.';
$string['verborgen'] = 'Hidden';
$string['videoaktiv'] = 'Enable video feature';
$string['videoaktiv_desc'] = 'Controls whether the "Video" building block type is visible in the course assistant at all. Within the block, the teacher then chooses whether to link, upload, or paste a link - depending on which plugins (repository_peertubeoauth, local_peertubeupload) are installed.';
$string['voraussetzung'] = 'Prerequisite';
$string['voraussetzung_entfernen'] = 'Remove prerequisite';
$string['voraussetzung_gesetzt'] = 'Prerequisite set.';
$string['voraussetzung_hinweis'] = 'Select the activity that must be completed before this activity becomes accessible.';
$string['voraussetzung_mindestbewertung'] = 'Minimum grade in %';
$string['voraussetzung_typ_abschluss'] = 'Completion';
$string['voraussetzung_typ_bewertung'] = 'Grade';
$string['voraussetzungen'] = 'Prerequisites';
$string['voraussetzungen_desc'] = 'Define which condition must be met before an activity becomes accessible.';
$string['vorlage_aktivitaet_name'] = 'Activity name';
$string['vorlage_aktivitaeten'] = 'Activities';
$string['vorlage_angewendet'] = 'Template applied: {$a} building blocks inserted.';
$string['vorlage_angewendet_n'] = 'Template applied: {$a} element(s) inserted.';
$string['vorlage_anwenden'] = 'Apply template';
$string['vorlage_anwenden_bestaetigung'] = 'Template "{$a->name}" will be applied to section "{$a->section}". Existing content is kept.';
$string['vorlage_bausteine'] = 'Building blocks (learning information)';
$string['vorlage_beschreibung'] = 'Description (optional)';
$string['vorlage_element_hinzufuegen'] = 'Add activity';
$string['vorlage_elemente_waehlen'] = 'Select the elements the template should contain:';
$string['vorlage_geloescht'] = 'Template deleted.';
$string['vorlage_gespeichert'] = 'Template saved.';
$string['vorlage_global'] = 'Global template (for all teachers)';
$string['vorlage_keine'] = 'No templates available yet.';
$string['vorlage_loeschen_bestaetigung'] = 'Really delete template "{$a}"?';
$string['vorlage_name'] = 'Template name';
$string['vorlage_neue_erstellen'] = 'Create new template';
$string['vorlage_persoenlich'] = 'Personal template';
$string['vorlage_speichern'] = 'Save as template';
$string['vorlage_typ_global'] = 'Global';
$string['vorlage_typ_persoenlich'] = 'Personal';
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
$string['zielabschnitt_help'] = 'The target section determines which course section new building blocks are inserted into. Select it <strong>first</strong> before adding building blocks. You can also rename the section or create a new one.';
$string['zurueck_zum_assistenten'] = 'Back';
$string['zurueckzumkurs'] = 'Back to course';
