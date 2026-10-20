// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Kursinfos der Lehrkraft im Dashboard: Liste, Schreiben, Ändern, Löschen.
 *
 * @module     local_kursassistent/kursinfo
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/templates', 'core/str'], function($, Ajax, Notification, Templates, Str) {

    var courseid;
    var data = {infos: [], students: []};
    var $root;

    /**
     * Zeigt die Liste der Kursinfos.
     *
     * @return {Promise}
     */
    function renderList() {
        var infos = data.infos.map(function(info) {
            return {
                id: info.id,
                infotext: info.infotext,
                timetext: info.timetext,
                authorname: info.authorname,
                toall: info.toall,
                recipientstext: info.recipients.join(', ')
            };
        });
        return Templates.renderForPromise('local_kursassistent/kursinfo_list', {
            hasinfos: infos.length > 0,
            infos: infos
        }).then(function(result) {
            Templates.replaceNodeContents($root.find('[data-region="local-kursassistent-info-list"]'), result.html, result.js);
            return null;
        });
    }

    /**
     * Lädt die Kursinfos vom Server und zeigt sie.
     *
     * @return {Promise}
     */
    function load() {
        return Ajax.call([{
            methodname: 'local_kursassistent_get_course_infos',
            args: {courseid: courseid}
        }])[0].then(function(response) {
            data = response;
            return renderList();
        }).catch(Notification.exception);
    }

    /**
     * Schließt das Formular.
     */
    function closeForm() {
        $root.find('[data-region="local-kursassistent-info-formarea"]').empty();
    }

    /**
     * Öffnet das Formular für eine neue oder eine vorhandene Kursinfo.
     *
     * @param {Number} infoid 0 für neu
     * @return {Promise}
     */
    function openForm(infoid) {
        var info = data.infos.find(function(i) {
            return i.id === infoid;
        });
        var selected = info ? info.userids : [];
        return Templates.renderForPromise('local_kursassistent/kursinfo_form', {
            infoid: info ? info.id : 0,
            infotext: info ? info.infotext : '',
            toall: info ? info.toall : true,
            students: data.students.map(function(s) {
                return {userid: s.userid, fullname: s.fullname, selected: selected.indexOf(s.userid) !== -1};
            })
        }).then(function(result) {
            var $area = $root.find('[data-region="local-kursassistent-info-formarea"]');
            Templates.replaceNodeContents($area, result.html, result.js);
            $area.find('textarea').trigger('focus');
            return null;
        }).catch(Notification.exception);
    }

    /**
     * Speichert die Kursinfo aus dem Formular.
     *
     * @param {jQuery} $form Formular
     */
    function save($form) {
        var toall = $form.find('input[name="local-kursassistent-info-to"]:checked').val() === 'all';
        var userids = [];
        $form.find('[data-region="local-kursassistent-info-students"] input:checked').each(function() {
            userids.push(parseInt($(this).val(), 10));
        });
        Ajax.call([{
            methodname: 'local_kursassistent_save_course_info',
            args: {
                courseid: courseid,
                text: $form.find('textarea').val(),
                toall: toall,
                userids: userids,
                infoid: parseInt($form.attr('data-infoid'), 10) || 0
            }
        }])[0].then(function() {
            closeForm();
            return load();
        }).catch(Notification.exception);
    }

    /**
     * Löscht eine Kursinfo auf dem Server und lädt die Liste neu.
     *
     * @param {Number} infoid Kursinfo-ID
     * @return {Promise}
     */
    function deleteInfo(infoid) {
        return Ajax.call([{
            methodname: 'local_kursassistent_delete_course_info',
            args: {courseid: courseid, infoid: infoid}
        }])[0].then(load).catch(Notification.exception);
    }

    /**
     * Löscht eine Kursinfo nach Rückfrage.
     *
     * @param {Number} infoid Kursinfo-ID
     */
    function remove(infoid) {
        Str.get_strings([
            {key: 'delete', component: 'core'},
            {key: 'kursinfo_loeschen_frage', component: 'local_kursassistent'},
            {key: 'delete', component: 'core'},
            {key: 'cancel', component: 'core'}
        ]).then(function(strings) {
            Notification.confirm(strings[0], strings[1], strings[2], strings[3], function() {
                deleteInfo(infoid);
            });
            return null;
        }).catch(Notification.exception);
    }

    return {
        /**
         * Initialisiert den Bereich Kursinfos im Dashboard.
         *
         * @param {Number} cid Kurs-ID
         */
        init: function(cid) {
            courseid = cid;
            $root = $('[data-region="local-kursassistent-infos"]');
            if (!$root.length) {
                return;
            }

            $root.on('click', '[data-action="local-kursassistent-info-new"]', function() {
                openForm(0);
            });
            $root.on('click', '[data-action="local-kursassistent-info-edit"]', function() {
                openForm(parseInt($(this).attr('data-infoid'), 10));
            });
            $root.on('click', '[data-action="local-kursassistent-info-delete"]', function() {
                remove(parseInt($(this).attr('data-infoid'), 10));
            });
            $root.on('click', '[data-action="local-kursassistent-info-cancel"]', closeForm);
            $root.on('change', 'input[name="local-kursassistent-info-to"]', function() {
                $root.find('[data-region="local-kursassistent-info-students"]')
                    .toggleClass('d-none', $(this).val() === 'all');
            });
            $root.on('submit', '[data-region="local-kursassistent-info-form"]', function(e) {
                e.preventDefault();
                save($(this));
            });

            load();
        }
    };
});
