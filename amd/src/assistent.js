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
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    var courseid = null;

    /**
     * Baut das Modal-Markup basierend auf den geladenen Bausteinen.
     *
     * @param {Object} response Antwort von local_kursassistent_get_bausteine
     * @return {jQuery}
     */
    function baueModal(response) {
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

        var kategorien = [
            {key: 'info', label: 'Lerninformationen', typen: ['text']},
            {key: 'material', label: 'Lernmaterial', typen: ['datei', 'bild', 'video']},
            {key: 'aktivitaet', label: 'Aktivitäten', typen: ['aktivitaet']},
        ];

        var $list = $('<div>', {'class': 'local-kursassistent-liste mb-3'});

        kategorien.forEach(function(kat) {
            var bausteineInKat = response.bausteine.filter(function(b) {
                return kat.typen.indexOf(b.typ) !== -1;
            });
            if (bausteineInKat.length === 0) {
                return;
            }

            var $gruppe = $('<div>', {'class': 'local-kursassistent-gruppe'});
            var $gruppenHeader = $('<div>', {'class': 'local-kursassistent-gruppenheader'});
            $gruppenHeader.append($('<span>', {text: kat.label}));
            $gruppenHeader.append($('<span>', {'class': 'local-kursassistent-gruppenpfeil', text: '▸'}));
            var $gruppenInhalt = $('<div>', {'class': 'local-kursassistent-gruppeninhalt d-none'});
            $gruppe.append($gruppenHeader, $gruppenInhalt);
            $list.append($gruppe);

            bausteineInKat.forEach(function(b) {
                baueBausteinItem(b, $gruppenInhalt, response, $overlay);
            });
        });

        // Vierte Gruppe "Kurseinrichtung": statische Einträge, nicht aus den Bausteinen des
        // Servers gespeist, da es sich um kursweite Aktionen statt Inhalts-Bausteine handelt.
        var $kgGruppe = $('<div>', {'class': 'local-kursassistent-gruppe'});
        var $kgHeader = $('<div>', {'class': 'local-kursassistent-gruppenheader'});
        $kgHeader.append($('<span>', {text: 'Kurseinrichtung'}));
        $kgHeader.append($('<span>', {'class': 'local-kursassistent-gruppenpfeil', text: '▸'}));
        var $kgInhalt = $('<div>', {'class': 'local-kursassistent-gruppeninhalt d-none'});
        $kgGruppe.append($kgHeader, $kgInhalt);
        $list.append($kgGruppe);

        var $formatItem = $('<label>', {'class': 'local-kursassistent-item local-kursassistent-navitem'});
        $formatItem.append($('<span>', {'class': 'local-kursassistent-checkbox-spacer'}));
        var $formatIcon = $('<span>', {'class': 'local-kursassistent-icon'});
        $formatIcon.append($('<img>', {
            src: M.cfg.wwwroot + '/local/kursassistent/pix/layout.svg',
            alt: '',
            'class': 'local-kursassistent-icon-img',
        }));
        $formatItem.append($formatIcon, $('<span>', {text: 'Kursformat ändern'}));
        $formatItem.on('click', function(e) {
            e.preventDefault();
            window.location.href = M.cfg.wwwroot + '/course/edit.php?id=' + courseid;
        });
        $kgInhalt.append($formatItem);

        if (response.templatewizardverfuegbar) {
            var $vorlageItem = $('<label>', {'class': 'local-kursassistent-item local-kursassistent-navitem'});
            $vorlageItem.append($('<span>', {'class': 'local-kursassistent-checkbox-spacer'}));
            var $vorlageIcon = $('<span>', {'class': 'local-kursassistent-icon'});
            $vorlageIcon.append($('<img>', {
                src: M.cfg.wwwroot + '/local/kursassistent/pix/copy.svg',
                alt: '',
                'class': 'local-kursassistent-icon-img',
            }));
            $vorlageItem.append($vorlageIcon, $('<span>', {text: 'Kursvorlage übernehmen'}));
            $vorlageItem.on('click', function(e) {
                e.preventDefault();
                window.location.href = M.cfg.wwwroot
                    + '/local/coursetemplatewizard/list_courses_to_copy.php?targetcourseid='
                    + courseid;
            });
            $kgInhalt.append($vorlageItem);
        }

        $modal.append($list);

        var $sectionwrap = $('<div>', {'class': 'mb-3'});
        $sectionwrap.append($('<label>', {'class': 'small text-muted d-block mb-1', text: 'Zielabschnitt'}));
        var $sectionrow = $('<div>', {'class': 'local-kursassistent-sectionrow'});
        var $select = $('<select>', {'class': 'form-control local-kursassistent-section'});
        response.sections.forEach(function(s) {
            $select.append($('<option>', {value: s.sectionnum, text: s.name}));
        });
        var $renameBtn = $('<button>', {
            type: 'button',
            'class': 'btn btn-outline-secondary local-kursassistent-rename-btn',
            title: 'Abschnitt umbenennen'
        });
        $renameBtn.append($('<img>', {
            src: M.cfg.wwwroot + '/local/kursassistent/pix/pencil.svg',
            alt: '',
            'class': 'local-kursassistent-rename-icon'
        }));
        var $addBtn = $('<button>', {
            type: 'button',
            'class': 'btn btn-outline-secondary local-kursassistent-addsection-btn',
            title: 'Neuen Abschnitt erstellen'
        });
        $addBtn.append($('<img>', {
            src: M.cfg.wwwroot + '/local/kursassistent/pix/plus.svg',
            alt: '',
            'class': 'local-kursassistent-rename-icon'
        }));
        $sectionrow.append($select, $renameBtn, $addBtn);
        $sectionwrap.append($sectionrow);

        var $addPanel = $('<div>', {'class': 'local-kursassistent-renamepanel d-none local-kursassistent-addpanel'});
        $addPanel.append($('<label>', {'class': 'small text-muted d-block mb-1', text: 'Name des neuen Abschnitts (optional)'}));
        var $addInput = $('<input>', {
            type: 'text',
            'class': 'form-control local-kursassistent-addinput mb-2',
            placeholder: 'z. B. Woche 5: Bruchrechnung',
        });
        $addPanel.append($addInput);
        var $addFooter = $('<div>', {'class': 'd-flex justify-content-end'});
        var $addCancel = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-secondary mr-2 local-kursassistent-add-cancel',
            text: 'Abbrechen',
        });
        var $addSave = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-primary local-kursassistent-add-save',
            text: 'Erstellen',
        });
        $addFooter.append($addCancel, $addSave);
        $addPanel.append($addFooter);
        $sectionwrap.append($addPanel);

        var $renamePanel = $('<div>', {'class': 'local-kursassistent-renamepanel d-none'});
        $renamePanel.append($('<label>', {'class': 'local-kursassistent-renamelabel small text-muted d-block mb-1'}));
        var $renameInput = $('<input>', {type: 'text', 'class': 'form-control local-kursassistent-renameinput mb-2'});
        $renamePanel.append($renameInput);
        var $renameFooter = $('<div>', {'class': 'd-flex justify-content-end'});
        var $renameCancel = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-secondary mr-2 local-kursassistent-rename-cancel',
            text: 'Abbrechen',
        });
        var $renameSave = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-primary local-kursassistent-rename-save',
            text: 'Speichern',
        });
        $renameFooter.append($renameCancel, $renameSave);
        $renamePanel.append($renameFooter);
        $sectionwrap.append($renamePanel);

        $modal.append($sectionwrap);

        var $footer = $('<div>', {'class': 'd-flex justify-content-end'});
        var $cancel = $('<button>', {
            'type': 'button',
            'class': 'btn btn-secondary mr-2 local-kursassistent-close',
            text: 'Abbrechen',
        });
        var $submit = $('<button>', {
            'type': 'button',
            'class': 'btn btn-primary local-kursassistent-submit',
            text: 'Bausteine einfügen',
        });
        $footer.append($cancel, $submit);
        $modal.append($footer);

        $overlay.append($modal);

        $list.on('click', '.local-kursassistent-gruppenheader', function() {
            var $header = $(this);
            var $inhalt = $header.next('.local-kursassistent-gruppeninhalt');
            $inhalt.toggleClass('d-none');
            var $pfeil = $header.find('.local-kursassistent-gruppenpfeil');
            $pfeil.text($pfeil.text() === '▸' ? '▾' : '▸');
        });

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

        $overlay.on('click', '.local-kursassistent-addsection-btn', function() {
            $overlay.find('.local-kursassistent-renamepanel').not('.local-kursassistent-addpanel').addClass('d-none');
            $overlay.find('.local-kursassistent-addinput').val('');
            $overlay.find('.local-kursassistent-addpanel').removeClass('d-none');
        });

        $overlay.on('click', '.local-kursassistent-add-cancel', function() {
            $overlay.find('.local-kursassistent-addpanel').addClass('d-none');
        });

        $overlay.on('click', '.local-kursassistent-add-save', function() {
            var name = $overlay.find('.local-kursassistent-addinput').val().trim();
            var $saveBtn = $(this);
            $saveBtn.prop('disabled', true).text('Erstellt …');

            Ajax.call([{
                methodname: 'local_kursassistent_create_section',
                args: {courseid: courseid, name: name}
            }])[0].done(function(res) {
                var $select = $overlay.find('.local-kursassistent-section');
                $select.append($('<option>', {value: res.sectionnum, text: res.name}));
                $select.val(res.sectionnum);
                $overlay.find('.local-kursassistent-addpanel').addClass('d-none');
                $saveBtn.prop('disabled', false).text('Erstellen');
            }).fail(function() {
                $saveBtn.prop('disabled', false).text('Erstellen');
                Notification.alert('Fehler', 'Der Abschnitt konnte nicht erstellt werden.', 'OK');
            });
        });

        $overlay.on('click', '.local-kursassistent-rename-btn', function() {
            $overlay.find('.local-kursassistent-addpanel').addClass('d-none');
            var $select = $overlay.find('.local-kursassistent-section');
            var aktuellerName = $select.find('option:selected').text();
            var $panel = $overlay.find('.local-kursassistent-renamepanel');
            $panel.find('.local-kursassistent-renamelabel').text('Neuer Name für „' + aktuellerName + '"');
            $panel.find('.local-kursassistent-renameinput').val(aktuellerName);
            $panel.removeClass('d-none');
            $select.prop('disabled', true);
        });

        $overlay.on('click', '.local-kursassistent-rename-cancel', function() {
            $overlay.find('.local-kursassistent-renamepanel').addClass('d-none');
            $overlay.find('.local-kursassistent-section').prop('disabled', false);
        });

        $overlay.on('click', '.local-kursassistent-rename-save', function() {
            var $select = $overlay.find('.local-kursassistent-section');
            var sectionnum = parseInt($select.val(), 10);
            var neuerName = $overlay.find('.local-kursassistent-renameinput').val().trim();

            if (!neuerName) {
                return;
            }

            var $saveBtn = $(this);
            $saveBtn.prop('disabled', true).text('Speichert …');

            Ajax.call([{
                methodname: 'local_kursassistent_rename_section',
                args: {courseid: courseid, sectionnum: sectionnum, name: neuerName}
            }])[0].done(function(res) {
                $select.find('option:selected').text(res.name);
                $overlay.find('.local-kursassistent-renamepanel').addClass('d-none');
                $select.prop('disabled', false);
                $saveBtn.prop('disabled', false).text('Speichern');
            }).fail(function() {
                $saveBtn.prop('disabled', false).text('Speichern');
                Notification.alert('Fehler', 'Der Abschnitt konnte nicht umbenannt werden.', 'OK');
            });
        });

        return $overlay;
    }

    /**
     * Baut ein einzelnes Baustein-Item (Checkbox/Navitem + zugehörige Zusatzelemente)
     * und hängt es an den übergebenen Gruppen-Container an.
     *
     * @param {Object} b Baustein-Daten
     * @param {jQuery} $container Ziel-Container (Gruppeninhalt)
     * @param {Object} response Gesamte Serverantwort (für Video-Status etc.)
     * @param {jQuery} $overlay Das Modal-Overlay (für Zugriff auf Zielabschnitt)
     */
    function baueBausteinItem(b, $container, response, $overlay) {
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
        $container.append($item);

        if (b.typ === 'text') {
            var $textarea = $('<textarea>', {
                'class': 'local-kursassistent-textinput d-none',
                'data-typeid': b.id,
                rows: 2,
                placeholder: b.titel
            }).val(stripHtml(b.platzhalter));
            $container.append($textarea);
        }

        if (b.typ === 'video') {
            var $videowrap = $('<div>', {
                'class': 'local-kursassistent-videowrap d-none',
                'data-typeid': b.id
            });

            if (!response.videoaktiv) {
                $videowrap.append($('<p>', {
                    'class': 'text-muted small mb-0',
                    text: 'Video-Funktion ist an dieser Schule nicht aktiviert.',
                }));
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
                    verlinken: 'PeerTube-Video wählen',
                    hochladen: 'Video hochladen',
                    link: 'Anderer Video-Link'
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
                        $pane.append($('<p>', {
                            'class': 'small text-muted mb-2',
                            text: 'Wähle ein bereits auf eurem PeerTube vorhandenes Video aus:',
                        }));
                        $pane.append($('<div>', {
                            'class': 'local-kursassistent-videogallery',
                            'data-typeid': b.id,
                            'data-loaded': '0',
                        }));
                    } else if (tab === 'hochladen') {
                        $pane.append($('<p>', {
                            'class': 'small text-muted mb-2',
                            text: 'Falls du noch keinen PeerTube-Kanal hast, wirst du zuerst zur '
                                + 'Einrichtung eines Kanals geführt. Danach kannst du hier das '
                                + 'Video hochladen.',
                        }));
                        if (!response.channelready) {
                            $pane.append($('<a>', {
                                href: M.cfg.wwwroot + '/local/peertubeupload/index.php',
                                target: '_blank',
                                'class': 'btn btn-sm btn-primary',
                                text: 'Video hochladen'
                            }));
                        } else {
                            $pane.append($('<input>', {type: 'file', 'class': 'local-kursassistent-fileinput', accept: 'video/*'}));
                        }
                    } else if (tab === 'link') {
                        $pane.append($('<p>', {
                            'class': 'small text-muted mb-2',
                            text: 'Link zu einem Video von einer anderen Plattform einfügen (z. B. YouTube, Vimeo):',
                        }));
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

            $container.append($videowrap);
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

        if (b.typ === 'aktivitaet') {
            // Aktivitäts-Bausteine navigieren direkt zu Moodles nativem
            // "Aktivität hinzufügen"-Formular - vorausgefüllt mit Typ, Kurs und Zielabschnitt.
            $item.addClass('local-kursassistent-navitem');
            $checkbox.replaceWith($('<span>', {'class': 'local-kursassistent-checkbox-spacer'}));
            $item.on('click', function(e) {
                e.preventDefault();
                var sectionnum = parseInt($overlay.find('.local-kursassistent-section').val(), 10) || 0;
                window.location.href = M.cfg.wwwroot + '/course/modedit.php?add=' + encodeURIComponent(b.modname) +
                    '&type=&course=' + courseid + '&section=' + sectionnum + '&return=0&sr=0';
            });
        }
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
                $gallery.append($('<p>', {
                    'class': 'small text-muted mb-0',
                    text: 'Kein Video-Repository für diese Schule konfiguriert.',
                }));
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
                        inhalt = '<div class="local-kursassistent-video-embed"><p>'
                            + '<a href="' + safeUrl + '" target="_blank" rel="noopener">'
                            + safeUrl + '</a></p></div>';
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
                        inhalt = '<div class="local-kursassistent-video-embed"><p>'
                            + '<a href="' + safeGurl + '">' + safeGurl + '</a></p></div>';
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
         * @param {Number} cid ID des aktuellen Kurses.
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
