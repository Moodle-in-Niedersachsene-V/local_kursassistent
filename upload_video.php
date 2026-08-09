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
 * Direkter Video-Upload aus dem Kursassistenten-Modal heraus.
 *
 * Kein Webservice, da hier ein echter Multipart-Datei-Upload verarbeitet wird.
 * Docket an local_peertubeupload_api an, statt die PeerTube-Upload-Logik
 * ein zweites Mal zu implementieren.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

header('Content-Type: application/json');

/**
 * Bricht den Upload mit einer generischen Fehlermeldung ab.
 *
 * Gibt bewusst nur einen Fehlerschlüssel zurück, damit keine internen
 * Details nach außen gelangen.
 *
 * @param string $error Fehlerschlüssel
 * @return void
 */
function kursassistent_upload_fail(string $error): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

$courseid = optional_param('courseid', 0, PARAM_INT);
$sectionnum = optional_param('sectionnum', 0, PARAM_INT);
$typeid = optional_param('typeid', 0, PARAM_INT);

if (!$courseid || !$typeid) {
    kursassistent_upload_fail('missing_parameters');
}

$context = context_course::instance($courseid);
require_capability('local/kursassistent:use', $context);

if (!\core_component::get_component_directory('local_peertubeupload')) {
    kursassistent_upload_fail('upload_plugin_missing');
}

$apifile = $CFG->dirroot . '/local/peertubeupload/classes/api.php';
$channelmanagerfile = $CFG->dirroot . '/local/peertubeupload/classes/channel_manager.php';
if (!file_exists($apifile) || !file_exists($channelmanagerfile)) {
    kursassistent_upload_fail('upload_plugin_outdated');
}
require_once($apifile);
require_once($channelmanagerfile);

try {
    $type = \local_kursassistent\manager::get_type($typeid);
} catch (\dml_exception $e) {
    kursassistent_upload_fail('invalid_type');
}

if ($type->typ !== 'video') {
    kursassistent_upload_fail('invalid_type');
}

if (empty($_FILES['videofile']) || $_FILES['videofile']['error'] !== UPLOAD_ERR_OK) {
    kursassistent_upload_fail('no_file');
}

$channelstatus = local_peertubeupload_channel_manager::is_channel_ready((int) $USER->id);
if (empty($channelstatus['ready']) || empty($channelstatus['channelname'])) {
    kursassistent_upload_fail('channel_not_ready');
}

$api = new local_peertubeupload_api();
$token = $api->get_access_token();
if (!$token) {
    kursassistent_upload_fail('auth_failed');
}

$channelid = $api->get_channel_id($channelstatus['channelname'], $token);
if (!$channelid) {
    kursassistent_upload_fail('channel_not_found');
}

$tmpfile = $_FILES['videofile']['tmp_name'];
$originalfilename = $_FILES['videofile']['name'];
$title = $type->titel . ' - ' . fullname($USER);
// PeerTube verlangt Titel zwischen 3 und 120 Zeichen.
$title = core_text::substr($title, 0, 120);
if (core_text::strlen($title) < 3) {
    $title = $type->titel . ' Video';
}

$result = $api->upload_video($tmpfile, $originalfilename, $title, $channelid, $token);

if (!$result->success) {
    kursassistent_upload_fail('upload_failed: ' . $result->error);
}

$uuid = $result->video->uuid ?? $result->video->shortUUID ?? '';
$baseurl = rtrim(get_config('peertubeoauth', 'instanceurl'), '/');
$embedurl = $baseurl . '/videos/embed/' . $uuid;

$iframehtml = html_writer::tag(
    'div',
    html_writer::empty_tag('iframe', [
        'title' => s($title),
        'width' => '560',
        'height' => '315',
        'src' => $embedurl,
        'frameborder' => '0',
        'allowfullscreen' => 'allowfullscreen',
        'sandbox' => 'allow-same-origin allow-scripts allow-popups allow-forms',
    ]),
    ['class' => 'local-kursassistent-video-embed']
);

try {
    $cmid = \local_kursassistent\manager::create_label($courseid, $sectionnum, $type, $iframehtml);
} catch (\Exception $e) {
    kursassistent_upload_fail('label_creation_failed');
}

rebuild_course_cache($courseid, true);

echo json_encode(['success' => true, 'cmid' => $cmid]);
