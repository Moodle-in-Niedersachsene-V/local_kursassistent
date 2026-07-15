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
 * Kursassistent: Modal-Logik.
 *
 * @module     local_kursassistent/assistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {

    var courseid = null;
    var bausteineDaten = [];

    /**
     * Baut das Modal-Markup basierend auf den geladenen Bausteinen.
     *
     * @param {Object} response Antwort von local_kursassistent_get_bausteine
     * @return {jQuery}
     */
    function baueModal(response) {
        bausteineDaten = response.bausteine;

        var $overlay = $('<div>', {'class': 'local-kursassistent-overlay'});
        var $modal = $('<div>', {'class': 'local-kursassistent-modal'});

        var $header = $('<div>', {'class': 'd-flex align-items-center justify-content-between mb-2'});
        $header.append($('<p>', {'class': 'font-weight-bold mb-0', text: M.str.local_kursassistent ? '' : ''}));
        var $closeBtn = $('<button>', {
            'type': 'button',
            'class': 'close local-kursassistent-close',
            'aria-label': 'Close'
        }).html('&times;');
        $header.append($closeBtn);
        $modal.append($header);

        var $list = $('<div>', {'class': 'local-kursassistent-liste mb-3'});
        response.bausteine.forEach(function(b) {
            var $item = $('<label>', {'class': 'local-kursassistent-item'});
            var $checkbox = $('<input>', {
                type: 'checkbox',
                'class': 'local-kursassistent-checkbox',
                'data-typeid': b.id,
                'data-typ': b.typ
            });
            var $iconwrap = $('<span>', {'class': 'local-kursassistent-icon'});
            $iconwrap.append($('<img>', {src: b.iconurl, alt: '', 'class': 'local-kursassistent-icon-img'}));
            var $label = $('<span>', {text: b.titel});
            $item.append($checkbox, $iconwrap, $label);
            $list.append($item);

            if (b.typ === 'text') {
                var $textarea = $('<textarea>', {
                    'class': 'local-kursassistent-textinput d-none',
                    'data-typeid': b.id,
                    rows: 2,
                    placeholder: b.titel
                }).val(stripHtml(b.platzhalter));
                $list.append($textarea);
            }

            if (b.typ === 'video') {
                var $videowrap = $('<div>', {
                    'class': 'local-kursassistent-videowrap d-none',
                    'data-typeid': b.id
                });

                if (!response.videoaktiv) {
                    $videowrap.append($('<p>', {'class': 'text-muted small mb-0', text: 'Video-Funktion ist an dieser Schule nicht aktiviert.'}));
                } else {
                    var tabs = [];
                    if (response.repoverfuegbar) {
                        tabs.push('verlinken');
                    }
                    if (response.uploadverfuegbar) {
                        tabs.push('hochladen');
                    }
                    tabs.push('link');

                    var $tabbar = $('<div>', {'class': 'local-kursassistent-tabbar'});
                    var $panes = $('<div>', {'class': 'local-kursassistent-tabpanes'});

                    var tabLabels = {
                        verlinken: 'Verlinken',
                        hochladen: 'Hochladen',
                        link: 'Link einfügen'
                    };

                    tabs.forEach(function(tab, idx) {
                        var $btn = $('<button>', {
                            type: 'button',
                            'class': 'local-kursassistent-tabbtn' + (idx === 0 ? ' active' : ''),
                            'data-tab': tab,
                            text: tabLabels[tab]
                        });
                        $tabbar.append($btn);

                        var $pane = $('<div>', {
                            'class': 'local-kursassistent-tabpane' + (idx === 0 ? '' : ' d-none'),
                            'data-tab': tab
                        });

                        if (tab === 'verlinken') {
                            $pane.append($('<div>', {'class': 'local-kursassistent-videogallery', 'data-typeid': b.id, 'data-loaded': '0'}));
                        } else if (tab === 'hochladen') {
                            if (!response.channelready) {
                                var $warn = $('<div>', {'class': 'local-kursassistent-warnbox'});
                                $warn.append($('<p>', {'class': 'mb-1', text: 'Kanal noch nicht eingerichtet'}));
                                $warn.append($('<p>', {'class': 'mb-2 small', text: 'Bevor du ein Video hochladen kannst, muss einmalig dein PeerTube-Kanal eingerichtet werden.'}));
                                $warn.append($('<a>', {
                                    href: M.cfg.wwwroot + '/local/peertubeupload/index.php',
                                    target: '_blank',
                                    'class': 'btn btn-sm btn-primary',
                                    text: 'Kanal jetzt einrichten'
                                }));
                                $pane.append($warn);
                            } else {
                                $pane.append($('<p>', {'class': 'small mb-2', text: 'Kanal ' + response.channelname + ' ist bereit.'}));
                                $pane.append($('<input>', {type: 'file', 'class': 'local-kursassistent-fileinput', accept: 'video/*'}));
                            }
                        } else if (tab === 'link') {
                            $pane.append($('<p>', {'class': 'small text-muted mb-2', text: 'Link zu einem vorhandenen Video einfügen:'}));
                            $pane.append($('<input>', {
                                type: 'url',
                                'class': 'form-control local-kursassistent-linkinput',
                                placeholder: 'https://...'
                            }));
                        }

                        $panes.append($pane);
                    });

                    $videowrap.append($tabbar, $panes);
                }

                $list.append($videowrap);
            }

            if (b.typ === 'datei' || b.typ === 'bild') {
                // Datei/Bild-Bausteine navigieren direkt zur Picker-Seite (echter Moodle-Datei-Picker
                // mit allen konfigurierten Repositories) statt einer Checkbox-Batch-Auswahl.
                $item.addClass('local-kursassistent-navitem');
                $checkbox.replaceWith($('<span>', {'class': 'local-kursassistent-checkbox-spacer'}));
                $item.on('click', function(e) {
                    e.preventDefault();
                    var sectionnum = parseInt($overlay.find('.local-kursassistent-section').val(), 10) || 0;
                    window.location.href = M.cfg.wwwroot + '/local/kursassistent/pick_file.php?courseid=' +
                        courseid + '&sectionnum=' + sectionnum + '&typeid=' + b.id;
                });
            }
        });
        $modal.append($list);

        var $sectionwrap = $('<div>', {'class': 'mb-3'});
        $sectionwrap.append($('<label>', {'class': 'small text-muted d-block mb-1', text: 'Zielabschnitt'}));
        var $select = $('<select>', {'class': 'form-control local-kursassistent-section'});
        response.sections.forEach(function(s) {
            $select.append($('<option>', {value: s.sectionnum, text: s.name}));
        });
        $sectionwrap.append($select);
        $modal.append($sectionwrap);

        var $footer = $('<div>', {'class': 'd-flex justify-content-end'});
        var $cancel = $('<button>', {'type': 'button', 'class': 'btn btn-secondary mr-2 local-kursassistent-close', text: 'Abbrechen'});
        var $submit = $('<button>', {'type': 'button', 'class': 'btn btn-primary local-kursassistent-submit', text: 'Bausteine einfügen'});
        $footer.append($cancel, $submit);
        $modal.append($footer);

        $overlay.append($modal);

        $list.on('change', '.local-kursassistent-checkbox', function() {
            var $cb = $(this);
            var typeid = $cb.data('typeid');
            var checked = $cb.is(':checked');
            $list.find('[data-typeid="' + typeid + '"]').not('.local-kursassistent-checkbox').toggleClass('d-none', !checked);
        });

        $list.on('click', '.local-kursassistent-tabbtn', function() {
            var $btn = $(this);
            var tab = $btn.data('tab');
            var $wrap = $btn.closest('.local-kursassistent-videowrap');
            $wrap.find('.local-kursassistent-tabbtn').removeClass('active');
            $btn.addClass('active');
            $wrap.find('.local-kursassistent-tabpane').addClass('d-none');
            $wrap.find('.local-kursassistent-tabpane[data-tab="' + tab + '"]').removeClass('d-none');

            if (tab === 'verlinken') {
                ladeVideoGalerie($wrap.find('.local-kursassistent-videogallery'));
            }
        });

        $list.on('click', '.local-kursassistent-galerie-item', function() {
            var $item = $(this);
            $item.closest('.local-kursassistent-videogallery').find('.local-kursassistent-galerie-item').removeClass('selected');
            $item.addClass('selected');
        });

        return $overlay;
    }

    /**
     * Entfernt einfache HTML-Tags aus einem Platzhaltertext für die Vorbefüllung.
     *
     * @param {String} html
     * @return {String}
     */
    function stripHtml(html) {
        if (!html) {
            return '';
        }
        var $tmp = $('<div>').html(html);
        return $tmp.text();
    }

    /**
     * Lädt die PeerTube-Videoliste nach und rendert sie als klickbare Galerie.
     *
     * @param {jQuery} $gallery
     */
    function ladeVideoGalerie($gallery) {
        if ($gallery.data('loaded') === 1 || $gallery.attr('data-loaded') === '1') {
            return;
        }
        $gallery.attr('data-loaded', '1');
        $gallery.append($('<p>', {'class': 'small text-muted mb-0', text: 'Lade Videos …'}));

        Ajax.call([{
            methodname: 'local_kursassistent_get_peertube_videos',
            args: {courseid: courseid}
        }])[0].done(function(response) {
            $gallery.empty();

            if (!response.available) {
                $gallery.append($('<p>', {'class': 'small text-muted mb-0', text: 'Kein Video-Repository für diese Schule konfiguriert.'}));
                return;
            }
            if (response.videos.length === 0) {
                $gallery.append($('<p>', {'class': 'small text-muted mb-0', text: 'Keine Videos gefunden.'}));
                return;
            }

            response.videos.forEach(function(v) {
                var $item = $('<div>', {
                    'class': 'local-kursassistent-galerie-item',
                    'data-url': v.url,
                    'title': v.titel
                });
                if (v.thumbnail) {
                    $item.append($('<img>', {src: v.thumbnail, alt: ''}));
                }
                $item.append($('<span>', {text: v.titel}));
                $gallery.append($item);
            });
        }).fail(function() {
            $gallery.empty().append($('<p>', {'class': 'small text-danger mb-0', text: 'Videos konnten nicht geladen werden.'}));
        });
    }

    /**
     * Lädt eine Videodatei über den eigenen Upload-Endpunkt hoch (kein Webservice,
     * da echter Multipart-Upload).
     *
     * @param {Number} sectionnum
     * @param {Number} typeid
     * @param {File} file
     * @return {jQuery.Promise}
     */
    function ladeVideoHoch(sectionnum, typeid, file) {
        var formdata = new FormData();
        formdata.append('sesskey', M.cfg.sesskey);
        formdata.append('courseid', courseid);
        formdata.append('sectionnum', sectionnum);
        formdata.append('typeid', typeid);
        formdata.append('videofile', file);

        var deferred = $.Deferred();

        fetch(M.cfg.wwwroot + '/local/kursassistent/upload_video.php', {
            method: 'POST',
            body: formdata,
            credentials: 'same-origin'
        }).then(function(response) {
            return response.json();
        }).then(function(data) {
            if (data.success) {
                deferred.resolve(data);
            } else {
                deferred.reject(data.error || 'upload_failed');
            }
        }).catch(function(error) {
            deferred.reject(error);
        });

        return deferred.promise();
    }

    /**
     * Sammelt die Auswahl aus dem Modal und sendet sie an den Server.
     * Video-Uploads laufen separat (echter Datei-Upload), alles andere über
     * den bestehenden Batch-Webservice-Aufruf.
     *
     * @param {jQuery} $overlay
     */
    function sendeAuswahl($overlay) {
        var sectionnum = parseInt($overlay.find('.local-kursassistent-section').val(), 10);
        var auswahl = [];
        var videouploads = [];

        $overlay.find('.local-kursassistent-checkbox:checked').each(function() {
            var typeid = parseInt($(this).data('typeid'), 10);
            var typ = $(this).data('typ');
            var inhalt = '';

            if (typ === 'text') {
                var text = $overlay.find('textarea[data-typeid="' + typeid + '"]').val() || '';
                inhalt = '<p>' + $('<div>').text(text).html() + '</p>';
                auswahl.push({typeid: typeid, inhalt: inhalt});
            } else if (typ === 'video') {
                var $wrap = $overlay.find('.local-kursassistent-videowrap[data-typeid="' + typeid + '"]');
                var activeTab = $wrap.find('.local-kursassistent-tabbtn.active').data('tab');

                if (activeTab === 'link') {
                    var url = $wrap.find('.local-kursassistent-linkinput').val() || '';
                    if (url) {
                        var safeUrl = $('<div>').text(url).html();
                        inhalt = '<div class="local-kursassistent-video-embed"><p><a href="' + safeUrl + '" target="_blank" rel="noopener">' + safeUrl + '</a></p></div>';
                    } else {
                        inhalt = '<p class="text-muted">Kein Link angegeben.</p>';
                    }
                    auswahl.push({typeid: typeid, inhalt: inhalt});
                } else if (activeTab === 'verlinken') {
                    var $selected = $wrap.find('.local-kursassistent-galerie-item.selected');
                    var gurl = $selected.length ? $selected.data('url') : '';
                    if (gurl) {
                        // URL in eigenem Absatz, Linktext = URL: passt zum bestehenden
                        // Fallback-Renderer von repository_peertubeoauth, der solche Links
                        // automatisch in ein eingebettetes iFrame umwandelt. Der umgebende
                        // Container begrenzt die Darstellungsgröße.
                        var safeGurl = $('<div>').text(gurl).html();
                        inhalt = '<div class="local-kursassistent-video-embed"><p><a href="' + safeGurl + '">' + safeGurl + '</a></p></div>';
                        auswahl.push({typeid: typeid, inhalt: inhalt});
                    }
                    // Kein Video ausgewählt: Baustein wird einfach übersprungen.
                } else if (activeTab === 'hochladen') {
                    var $fileinput = $wrap.find('.local-kursassistent-fileinput');
                    var file = $fileinput.length && $fileinput[0].files.length ? $fileinput[0].files[0] : null;
                    if (file) {
                        videouploads.push({typeid: typeid, file: file});
                    }
                    // Kein Datei ausgewählt: Baustein wird einfach übersprungen (kein Platzhalter nötig).
                }
            } else {
                inhalt = '<p class="text-muted">Inhalt wird nach Upload ergänzt.</p>';
                auswahl.push({typeid: typeid, inhalt: inhalt});
            }
        });

        if (auswahl.length === 0 && videouploads.length === 0) {
            Notification.alert('Hinweis', 'Bitte wähle mindestens einen Baustein aus.', 'OK');
            return;
        }

        var $submitBtn = $overlay.find('.local-kursassistent-submit');
        $submitBtn.prop('disabled', true).text('Wird verarbeitet …');

        var promises = [];

        if (auswahl.length > 0) {
            promises.push(Ajax.call([{
                methodname: 'local_kursassistent_insert_bausteine',
                args: {courseid: courseid, sectionnum: sectionnum, auswahl: auswahl}
            }])[0]);
        }

        videouploads.forEach(function(item) {
            promises.push(ladeVideoHoch(sectionnum, item.typeid, item.file));
        });

        $.when.apply($, promises).done(function() {
            window.location.reload();
        }).fail(function(error) {
            $submitBtn.prop('disabled', false).text('Bausteine einfügen');
            Notification.alert('Fehler beim Einfügen',
                'Mindestens ein Baustein konnte nicht eingefügt werden: ' + (error || 'unbekannter Fehler'), 'OK');
        });
    }

    return {
        /**
         * Initialisiert den Kursassistenten für den aktuellen Kurs.
         *
         * @param {Object} args
         */
        init: function(cid) {
            courseid = cid;

            $(document).on('click', '.local-kursassistent-navlink', function(e) {
                e.preventDefault();
                Ajax.call([{
                    methodname: 'local_kursassistent_get_bausteine',
                    args: {courseid: courseid}
                }])[0].done(function(response) {
                    var $overlay = baueModal(response);
                    $('body').append($overlay);
                }).fail(Notification.exception);
            });

            $(document).on('click', '.local-kursassistent-close', function() {
                $(this).closest('.local-kursassistent-overlay').remove();
            });

            $(document).on('click', '.local-kursassistent-submit', function() {
                sendeAuswahl($(this).closest('.local-kursassistent-overlay'));
            });
        }
    };
});
