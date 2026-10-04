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
define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/sortable_list',
    'local_kursassistent/rueckkehr'],
        function($, Ajax, Notification, Str, SortableList, Rueckkehr) {

    var courseid = null;
    var etwasEingefuegt = false;

    /**
     * Merker für die Rückkehr vom Formular: vorhandene Aktivitäten und Name des Bausteins.
     * Wird beim ersten Aufbau der Übersicht verbraucht.
     */
    var rueckkehrNeu = null;

    /**
     * Erzeugt ein Moodle-konformes Hilfe-Icon (blaues Fragezeichen mit Popover).
     *
     * @param {string} titleKey Sprachschlüssel für den Popover-Titel
     * @param {string} textKey Sprachschlüssel für den Popover-Text
     * @return {jQuery}
     */
    function erstelleHilfeIcon(titleKey, textKey) {
        var $icon = $('<a>', {
            'class': 'btn btn-link p-0 ml-1 local-kursassistent-helpicon',
            'role': 'button',
            'tabindex': '0',
            'aria-label': 'Hilfe',
            'data-title-key': titleKey,
            'data-text-key': textKey
        });
        $icon.append($('<i>', {'class': 'icon fa fa-question-circle text-info fa-fw'}));

        // Popover beim ersten Klick laden und anzeigen.
        $icon.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $self = $(this);
            // Bereits offen → schließen.
            if ($self.data('popover-open')) {
                $self.data('popover-open', false);
                $('.local-kursassistent-helppopover').remove();
                return;
            }
            // Andere Popovers schließen.
            $('.local-kursassistent-helppopover').remove();
            $('.local-kursassistent-helpicon').data('popover-open', false);

            var tKey = $self.data('title-key');
            var bKey = $self.data('text-key');
            Str.get_strings([
                {key: tKey, component: 'local_kursassistent'},
                {key: bKey, component: 'local_kursassistent'}
            ]).done(function(strings) {
                var $pop = $('<div>', {'class': 'local-kursassistent-helppopover popover bs-popover-bottom show'});
                $pop.append($('<div>', {'class': 'arrow'}));
                $pop.append($('<h3>', {'class': 'popover-header', text: strings[0]}));
                $pop.append($('<div>', {'class': 'popover-body'}).html(strings[1]));
                $self.after($pop);
                $self.data('popover-open', true);

                // Schließen bei Klick außerhalb.
                setTimeout(function() {
                    $(document).one('click', function() {
                        $pop.remove();
                        $self.data('popover-open', false);
                    });
                }, 10);
            });
        });

        return $icon;
    }

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
        var $titleWrap = $('<div>', {'class': 'd-flex align-items-center'});
        $titleWrap.append($('<p>', {'class': 'font-weight-bold mb-0', text: 'Kursassistent'}));
        $titleWrap.append(erstelleHilfeIcon('assistent_help_title', 'assistent_help'));
        $header.append($titleWrap);
        var $closeBtn = $('<button>', {
            'type': 'button',
            'class': 'close local-kursassistent-close',
            'aria-label': 'Close'
        }).html('&times;');
        $header.append($closeBtn);
        $modal.append($header);

        var kategorien = [
            {key: 'info', label: 'Lerninformationen', typen: ['text'], helpTitle: 'kat_info_help_title', helpText: 'kat_info_help'},
            {key: 'material', label: 'Lernmaterial', typen: ['datei', 'bild', 'video'],
                helpTitle: 'kat_material_help_title', helpText: 'kat_material_help'},
            {key: 'aktivitaet', label: 'Aktivitäten', typen: ['aktivitaet'],
                helpTitle: 'kat_aktivitaet_help_title', helpText: 'kat_aktivitaet_help'},
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
            var $labelWrap = $('<span>', {'class': 'd-flex align-items-center'});
            $labelWrap.append($('<span>', {text: kat.label}));
            if (kat.helpTitle) {
                $labelWrap.append(erstelleHilfeIcon(kat.helpTitle, kat.helpText));
            }
            $gruppenHeader.append($labelWrap);
            $gruppenHeader.append($('<span>', {'class': 'local-kursassistent-gruppenpfeil', text: '▸'}));
            var $gruppenInhalt = $('<div>', {'class': 'local-kursassistent-gruppeninhalt d-none'});
            $gruppe.append($gruppenHeader, $gruppenInhalt);
            $list.append($gruppe);

            bausteineInKat.forEach(function(b) {
                baueBausteinItem(b, $gruppenInhalt, response, $overlay);
            });

            if (kat.typen.indexOf('aktivitaet') !== -1) {
                $gruppenInhalt.append($('<p>', {
                    'class': 'small text-muted mt-2 mb-1',
                    text: 'Moodle öffnet jetzt das Einstellungsformular. Danach geht es hier weiter.'
                }));
            }
        });

        // Vierte Gruppe "Kurseinrichtung": statische Einträge, nicht aus den Bausteinen des
        // Servers gespeist, da es sich um kursweite Aktionen statt Inhalts-Bausteine handelt.
        var $kgGruppe = $('<div>', {'class': 'local-kursassistent-gruppe'});
        var $kgHeader = $('<div>', {'class': 'local-kursassistent-gruppenheader'});
        var $kgLabelWrap = $('<span>', {'class': 'd-flex align-items-center'});
        $kgLabelWrap.append($('<span>', {text: 'Kurseinrichtung'}));
        $kgLabelWrap.append(erstelleHilfeIcon('kat_kurseinrichtung_help_title', 'kat_kurseinrichtung_help'));
        $kgHeader.append($kgLabelWrap);
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
            'class': 'local-kursassistent-icon-img'
        }));
        $formatItem.append($formatIcon, $('<span>', {text: 'Kursformat ändern'}));
        $formatItem.on('click', function(e) {
            e.preventDefault();
            navigiereMitWarnung($overlay, M.cfg.wwwroot + '/course/edit.php?id=' + courseid,
                'Kursformat ändern');
        });
        $kgInhalt.append($formatItem);

        if (response.vorlagenverfuegbar) {
            var $vorlageItem = $('<label>', {'class': 'local-kursassistent-item local-kursassistent-navitem'});
            $vorlageItem.append($('<span>', {'class': 'local-kursassistent-checkbox-spacer'}));
            var $vorlageIcon = $('<span>', {'class': 'local-kursassistent-icon'});
            $vorlageIcon.append($('<img>', {
                src: M.cfg.wwwroot + '/local/kursassistent/pix/copy.svg',
                alt: '',
                'class': 'local-kursassistent-icon-img'
            }));
            $vorlageItem.append($vorlageIcon, $('<span>', {text: 'Kursvorlage übernehmen'}));
            $vorlageItem.on('click', function(e) {
                e.preventDefault();
                navigiereMitWarnung($overlay, M.cfg.wwwroot +
                    '/local/kursassistent/vorlagen.php?courseid=' + courseid, 'Kursvorlage übernehmen');
            });
            $kgInhalt.append($vorlageItem);
        }

        // Abschnittsvorlagen-Eintrag.
        var $sectplItem = $('<label>', {'class': 'local-kursassistent-item local-kursassistent-navitem'});
        $sectplItem.append($('<span>', {'class': 'local-kursassistent-checkbox-spacer'}));
        var $sectplIcon = $('<span>', {'class': 'local-kursassistent-icon'});
        $sectplIcon.append($('<img>', {
            src: M.cfg.wwwroot + '/local/kursassistent/pix/file-text.svg',
            alt: '',
            'class': 'local-kursassistent-icon-img'
        }));
        $sectplItem.append($sectplIcon, $('<span>', {text: 'Abschnittsvorlagen'}));
        $sectplItem.on('click', function(e) {
            e.preventDefault();
            oeffneSubpanel($overlay, 'abschnittsvorlagen');
        });
        $kgInhalt.append($sectplItem);

        // Fünfte Gruppe: Lernpfade, Kompetenzen und Statistik.
        var $avGruppe = $('<div>', {'class': 'local-kursassistent-gruppe'});
        var $avHeader = $('<div>', {'class': 'local-kursassistent-gruppenheader'});
        var $avLabelWrap = $('<span>', {'class': 'd-flex align-items-center'});
        $avLabelWrap.append($('<span>', {text: 'Lernpfade, Kompetenzen und Statistik'}));
        $avLabelWrap.append(erstelleHilfeIcon('kat_lernpfade_help_title', 'kat_lernpfade_help'));
        $avHeader.append($avLabelWrap);
        $avHeader.append($('<span>', {'class': 'local-kursassistent-gruppenpfeil', text: '▸'}));
        var $avInhalt = $('<div>', {'class': 'local-kursassistent-gruppeninhalt d-none'});
        $avGruppe.append($avHeader, $avInhalt);
        $list.append($avGruppe);

        var avItems = [
            {key: 'abschluss', label: 'Abschlussverfolgung', icon: 'check-circle.svg'},
            {key: 'voraussetzungen', label: 'Voraussetzungen', icon: 'lock.svg'},
            {key: 'lernpfad', label: 'Lernpfad erstellen', icon: 'route.svg'},
            {key: 'uebersicht', label: 'Übersicht', icon: 'eye.svg'},
            {key: 'statistik', label: 'Statistik', icon: 'checklist.svg'},
        ];

        avItems.forEach(function(av) {
            var $avItem = $('<label>', {'class': 'local-kursassistent-item local-kursassistent-navitem'});
            $avItem.append($('<span>', {'class': 'local-kursassistent-checkbox-spacer'}));
            var $avIcon = $('<span>', {'class': 'local-kursassistent-icon'});
            $avIcon.append($('<img>', {
                src: M.cfg.wwwroot + '/local/kursassistent/pix/' + av.icon,
                alt: '',
                'class': 'local-kursassistent-icon-img'
            }));
            $avItem.append($avIcon, $('<span>', {text: av.label}));
            $avItem.on('click', function(e) {
                e.preventDefault();
                oeffneSubpanel($overlay, av.key);
            });
            $avInhalt.append($avItem);
        });

        // Zielabschnitt zuerst anzeigen, damit die Lehrkraft als erstes den Abschnitt wählt.
        var $sectionwrap = $('<div>', {'class': 'mb-3'});
        var $sectionLabel = $('<label>', {'class': 'small text-muted d-flex align-items-center mb-1'});
        $sectionLabel.append($('<span>', {text: 'Zielabschnitt'}));
        $sectionLabel.append(erstelleHilfeIcon('zielabschnitt', 'zielabschnitt_help'));
        $sectionwrap.append($sectionLabel);
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
            placeholder: 'z. B. Woche 5: Bruchrechnung'
        });
        $addPanel.append($addInput);
        var $addFooter = $('<div>', {'class': 'd-flex justify-content-end'});
        var $addCancel = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-secondary mr-2 local-kursassistent-add-cancel',
            text: 'Abbrechen'
        });
        var $addSave = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-primary local-kursassistent-add-save',
            text: 'Erstellen'
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
            text: 'Abbrechen'
        });
        var $renameSave = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-primary local-kursassistent-rename-save',
            text: 'Speichern'
        });
        $renameFooter.append($renameCancel, $renameSave);
        $renamePanel.append($renameFooter);
        $sectionwrap.append($renamePanel);

        $modal.append($sectionwrap);

        $modal.append($list);

        // Subpanel-Container (wird bei Bedarf befüllt).
        var $subpanel = $('<div>', {'class': 'local-kursassistent-subpanel d-none'});
        $modal.append($subpanel);

        var $meldung = $('<div>', {'class': 'local-kursassistent-meldung d-none'});
        $modal.append($meldung);

        var $footer = $('<div>', {'class': 'd-flex justify-content-end'});
        var $cancel = $('<button>', {
            'type': 'button',
            'class': 'btn btn-secondary mr-2 local-kursassistent-fertig',
            text: 'Fertig'
        });
        var $submit = $('<button>', {
            'type': 'button',
            'class': 'btn btn-primary local-kursassistent-submit',
            text: 'Bausteine einfügen'
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
                    text: 'Video-Funktion ist an dieser Schule nicht aktiviert.'
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
                            text: 'Wähle ein bereits auf eurem PeerTube vorhandenes Video aus:'
                        }));
                        $pane.append($('<div>', {
                            'class': 'local-kursassistent-videogallery',
                            'data-typeid': b.id,
                            'data-loaded': '0'
                        }));
                    } else if (tab === 'hochladen') {
                        $pane.append($('<p>', {
                            'class': 'small text-muted mb-2',
                            text: 'Falls du noch keinen PeerTube-Kanal hast, wirst du zuerst zur ' +
                                'Einrichtung eines Kanals geführt. Danach kannst du hier das Video hochladen.'
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
                            text: 'Link zu einem Video von einer anderen Plattform einfügen (z. B. YouTube, Vimeo):'
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
                navigiereMitWarnung($overlay, M.cfg.wwwroot + '/local/kursassistent/pick_file.php?courseid=' +
                    courseid + '&sectionnum=' + sectionnum + '&typeid=' + b.id, b.titel);
            });
        }

        if (b.typ === 'aktivitaet') {
            // Aktivitäts-Bausteine öffnen Moodles natives Formular im selben Tab. Vorher merkt sich
            // der Assistent, dass er nach dem Speichern wieder aufgehen soll.
            $item.addClass('local-kursassistent-navitem');
            $checkbox.replaceWith($('<span>', {'class': 'local-kursassistent-checkbox-spacer'}));
            $item.on('click', function(e) {
                e.preventDefault();
                var sectionnum = parseInt($overlay.find('.local-kursassistent-section').val(), 10) || 0;
                var url = M.cfg.wwwroot + '/course/modedit.php?add=' +
                    encodeURIComponent(b.modname) + '&type=&course=' + courseid +
                    '&section=' + sectionnum + '&return=0&sr=0';
                navigiereMitWarnung($overlay, url, b.titel, function(weiter) {
                    merkeRueckkehr(b, sectionnum, weiter);
                });
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
                    text: 'Kein Video-Repository für diese Schule konfiguriert.'
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
            return data;
        }).catch(function(error) {
            deferred.reject(error);
        });

        return deferred.promise();
    }

    /**
     * Zeigt eine Meldung im Kopfbereich des Fensters an.
     *
     * @param {jQuery} $overlay
     * @param {String} text
     * @param {String} art 'erfolg' oder 'hinweis'
     */
    function zeigeMeldung($overlay, text, art) {
        var $meldung = $overlay.find('.local-kursassistent-meldung');
        $meldung
            .removeClass('d-none local-kursassistent-meldung-erfolg local-kursassistent-meldung-hinweis')
            .addClass('local-kursassistent-meldung-' + art)
            .text(text);
    }

    /**
     * Prüft, ob im Fenster noch nicht eingefügte Auswahlen stehen.
     *
     * @param {jQuery} $overlay
     * @return {Boolean}
     */
    function hatOffeneEingaben($overlay) {
        return $overlay.find('.local-kursassistent-checkbox:checked').length > 0;
    }

    /**
     * Merkt sich vor dem Wechsel zum Moodle-Formular, dass der Assistent danach wieder aufgehen soll.
     *
     * Hält dazu die vorhandenen Aktivitäten fest, damit die neue Aktivität später erkannt werden
     * kann. Scheitert das Laden, wird trotzdem gewechselt; der Assistent öffnet sich dann ohne
     * Markierung.
     *
     * @param {Object} baustein Angeklickter Aktivitäts-Baustein
     * @param {Number} sectionnum Gewählter Zielabschnitt
     * @param {Function} weiter Wird danach aufgerufen und führt den Wechsel aus
     */
    function merkeRueckkehr(baustein, sectionnum, weiter) {
        var daten = {
            courseid: courseid,
            modname: baustein.modname,
            titel: baustein.titel,
            sectionnum: sectionnum,
            vorher: null
        };
        var fertig = function() {
            Rueckkehr.merke(daten);
            weiter();
        };

        Ajax.call([{
            methodname: 'local_kursassistent_get_course_activities',
            args: {courseid: courseid}
        }])[0].done(function(response) {
            daten.vorher = response.activities.map(function(a) {
                return a.cmid;
            });
            fertig();
        }).fail(fertig);
    }

    /**
     * Öffnet den Assistenten.
     *
     * @param {Function|undefined} nachDemOeffnen Wird mit dem geöffneten Fenster aufgerufen
     */
    function oeffneAssistent(nachDemOeffnen) {
        Ajax.call([{
            methodname: 'local_kursassistent_get_bausteine',
            args: {courseid: courseid}
        }])[0].done(function(response) {
            var $overlay = baueModal(response);
            $('body').append($overlay);
            if (nachDemOeffnen) {
                nachDemOeffnen($overlay);
            }
        }).fail(Notification.exception);
    }

    /**
     * Wertet die Rückkehr vom Moodle-Formular aus: Gibt es eine neue Aktivität, geht der
     * Assistent direkt in die Übersicht und markiert sie.
     *
     * @param {jQuery} $overlay
     * @param {Object} rueckkehr Gemerkte Daten
     */
    function zeigeRueckkehrErgebnis($overlay, rueckkehr) {
        if (!rueckkehr.vorher) {
            return;
        }
        rueckkehrNeu = {vorher: rueckkehr.vorher, titel: rueckkehr.titel};
        oeffneSubpanel($overlay, 'uebersicht');
    }

    /**
     * Ermittelt die Aktivität, die seit dem Wechsel zum Moodle-Formular neu im Kurs ist.
     *
     * Der Merker wird dabei verbraucht, damit spätere Aufbauten der Übersicht, etwa nach dem
     * Duplizieren, nicht erneut markieren.
     *
     * @param {Array} aktivitaeten Aktivitäten des Kurses
     * @return {Object|null} cmid, name und titel der neuen Aktivität, oder null
     */
    function verbraucheNeueAktivitaet(aktivitaeten) {
        var merker = rueckkehrNeu;
        rueckkehrNeu = null;
        if (!merker) {
            return null;
        }
        var neue = aktivitaeten.filter(function(a) {
            return merker.vorher.indexOf(a.cmid) === -1;
        });
        if (!neue.length) {
            return null;
        }
        var juengste = neue.reduce(function(a, b) {
            return b.cmid > a.cmid ? b : a;
        });
        return {cmid: juengste.cmid, name: juengste.name, titel: merker.titel};
    }

    /**
     * Markiert die neue Aktivität in der Übersicht und meldet, dass sie angelegt wurde.
     *
     * @param {jQuery} $container Panel mit der Übersichtstabelle
     * @param {Object} neu cmid, name und titel der neuen Aktivität
     */
    function markiereNeueAktivitaet($container, neu) {
        var $zeile = $container.find('.local-kursassistent-sortrow[data-cmid="' + neu.cmid + '"]');
        if ($zeile.length) {
            $zeile.addClass('local-kursassistent-neuzeile');
            $zeile.children('td').eq(1).append($('<span>', {
                'class': 'local-kursassistent-neumarke',
                text: 'neu'
            }));
            $zeile[0].scrollIntoView({block: 'nearest'});
        }
        zeigePanelMeldung($container, neu.titel + ' „' + neu.name +
            '“ wurde angelegt. Hier stellst du die Aktivität weiter ein.', 'erfolg');
    }

    /**
     * Wechselt zu einer anderen Seite und warnt vorher, falls noch Eingaben offen sind.
     *
     * @param {jQuery} $overlay
     * @param {String} url Ziel-URL
     * @param {String} bausteinname Name des angeklickten Bausteins
     * @param {Function|undefined} vorWechsel Optionaler Schritt vor dem Wechsel; ruft die übergebene
     *     Funktion auf, sobald der Wechsel erfolgen darf
     */
    function navigiereMitWarnung($overlay, url, bausteinname, vorWechsel) {
        var gehe = function() {
            if (vorWechsel) {
                vorWechsel(function() {
                    window.location.href = url;
                });
                return;
            }
            window.location.href = url;
        };

        if (!hatOffeneEingaben($overlay)) {
            gehe();
            return;
        }

        var $panel = $('<div>', {'class': 'local-kursassistent-abfrage'});
        $panel.append($('<p>', {'class': 'font-weight-bold mb-1', text: 'Noch nicht eingefügte Eingaben'}));
        $panel.append($('<p>', {
            'class': 'small mb-3',
            text: 'Du hast Bausteine ausgewählt, die noch nicht eingefügt wurden. „' + bausteinname +
                '" öffnet eine neue Seite - deine Eingaben gehen dabei verloren, wenn sie nicht vorher eingefügt werden.'
        }));

        var $erst = $('<button>', {
            type: 'button',
            'class': 'btn btn-primary btn-sm d-block mb-2 local-kursassistent-abfrage-speichern',
            text: 'Erst einfügen, dann weiter'
        });
        var $ohne = $('<button>', {
            type: 'button',
            'class': 'btn btn-outline-secondary btn-sm d-block mb-2 local-kursassistent-abfrage-weiter',
            text: 'Ohne Speichern weiter'
        });
        var $ab = $('<button>', {
            type: 'button',
            'class': 'btn btn-link btn-sm d-block local-kursassistent-abfrage-abbrechen',
            text: 'Abbrechen'
        });
        $panel.append($erst, $ohne, $ab);

        $erst.on('click', function() {
            sendeAuswahl($overlay, gehe);
        });
        $ohne.on('click', gehe);
        $ab.on('click', function() {
            $panel.remove();
        });

        $overlay.find('.local-kursassistent-modal').append($panel);
        $panel[0].scrollIntoView({block: 'nearest'});
    }

    /**
     * Sammelt die Auswahl aus dem Modal und sendet sie an den Server.
     * Video-Uploads laufen separat (echter Datei-Upload), alles andere über
     * den bestehenden Batch-Webservice-Aufruf.
     *
     * @param {jQuery} $overlay
     * @param {Function} weiter Wird nach erfolgreichem Einfügen aufgerufen
     */
    function sendeAuswahl($overlay, weiter) {
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
                        inhalt = '<div class="local-kursassistent-video-embed"><p><a href="' + safeUrl +
                            '" target="_blank" rel="noopener">' + safeUrl + '</a></p></div>';
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
                        inhalt = '<div class="local-kursassistent-video-embed"><p><a href="' + safeGurl +
                            '">' + safeGurl + '</a></p></div>';
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
            etwasEingefuegt = true;

            var anzahl = auswahl.length + videouploads.length;
            zeigeMeldung($overlay, anzahl === 1
                ? 'Ein Baustein wurde eingefügt.'
                : anzahl + ' Bausteine wurden eingefügt.', 'erfolg');

            // Haken loesen, Eingaben aber stehen lassen: die Lehrkraft sieht weiterhin,
            // was sie geschrieben hat, ein zweiter Klick erzeugt aber nicht versehentlich
            // eine Dublette. Wer bewusst einen weiteren Baustein will, hakt erneut an.
            $overlay.find('.local-kursassistent-checkbox:checked').each(function() {
                var $cb = $(this);
                $cb.prop('checked', false);
                var typeid = $cb.data('typeid');
                $overlay.find('[data-typeid="' + typeid + '"]')
                    .not('.local-kursassistent-checkbox').addClass('d-none');
                var $zeile = $cb.closest('.local-kursassistent-item');
                if (!$zeile.find('.local-kursassistent-eingefuegt').length) {
                    $zeile.append($('<span>', {
                        'class': 'local-kursassistent-eingefuegt',
                        text: 'bereits eingefügt'
                    }));
                }
            });

            $submitBtn.prop('disabled', false).text('Bausteine einfügen');

            if (typeof weiter === 'function') {
                weiter();
            }
        }).fail(function(error) {
            $submitBtn.prop('disabled', false).text('Bausteine einfügen');
            Notification.alert('Fehler beim Einfügen',
                'Mindestens ein Baustein konnte nicht eingefügt werden: ' + (error || 'unbekannter Fehler'), 'OK');
        });
    }

    // ---------------------------------------------------------------
    // Abschluss & Voraussetzungen: Subpanel-Logik
    // ---------------------------------------------------------------

    /**
     * Öffnet ein Subpanel (Abschluss, Voraussetzungen, Lernpfad, Übersicht).
     * Versteckt das Hauptmodal und zeigt stattdessen das Subpanel.
     *
     * @param {jQuery} $overlay
     * @param {String} panelKey
     */
    function oeffneSubpanel($overlay, panelKey) {
        var $modal = $overlay.find('.local-kursassistent-modal');
        var $subpanel = $modal.find('.local-kursassistent-subpanel');

        // Hauptinhalt verstecken.
        $modal.find('.local-kursassistent-liste, .local-kursassistent-sectionrow, .local-kursassistent-meldung')
            .closest('.mb-3').addClass('local-kursassistent-hauptinhalt-hidden');
        $modal.find('.local-kursassistent-liste').addClass('d-none');
        $modal.find('.local-kursassistent-submit, .local-kursassistent-fertig').addClass('d-none');
        $modal.children('.mb-3').addClass('d-none');

        $subpanel.empty().removeClass('d-none');

        // Zurück-Button.
        var $zurueck = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-outline-secondary mb-3 local-kursassistent-zurueck',
            text: '← Zurück'
        });
        $zurueck.on('click', function() {
            schliesseSubpanel($overlay);
        });
        $subpanel.append($zurueck);

        // Abschnittsvorlagen und Statistik haben eigene Datenquellen.
        if (panelKey === 'abschnittsvorlagen') {
            rendereAbschnittsvorlagenPanel($subpanel, $overlay);
            return;
        }
        if (panelKey === 'statistik') {
            rendereStatistikPanel($subpanel);
            return;
        }

        // Lade-Indikator.
        var $loader = $('<div>', {'class': 'text-center py-3'});
        $loader.append($('<span>', {'class': 'text-muted', text: 'Lade Aktivitäten …'}));
        $subpanel.append($loader);

        // Aktivitäten vom Server laden.
        Ajax.call([{
            methodname: 'local_kursassistent_get_course_activities',
            args: {courseid: courseid}
        }])[0].done(function(response) {
            $loader.remove();

            if (!response.completionenabled && panelKey !== 'uebersicht') {
                $subpanel.append($('<div>', {
                    'class': 'alert alert-warning',
                    text: 'Die Abschlussverfolgung ist in diesem Kurs nicht aktiviert. ' +
                        'Bitte aktiviere sie zuerst in den Kurseinstellungen.'
                }));
                var $linkBtn = $('<a>', {
                    href: M.cfg.wwwroot + '/course/edit.php?id=' + courseid,
                    'class': 'btn btn-primary btn-sm mt-2',
                    text: 'Kurseinstellungen öffnen'
                });
                $subpanel.append($linkBtn);
                return;
            }

            switch (panelKey) {
                case 'abschluss':
                    rendereAbschlussPanel($subpanel, response);
                    break;
                case 'voraussetzungen':
                    rendereVoraussetzungenPanel($subpanel, response);
                    break;
                case 'lernpfad':
                    rendereLernpfadPanel($subpanel, response);
                    break;
                case 'uebersicht':
                    rendereUebersichtPanel($subpanel, response);
                    break;
            }
        }).fail(function() {
            $loader.remove();
            $subpanel.append($('<div>', {
                'class': 'alert alert-danger',
                text: 'Aktivitäten konnten nicht geladen werden.'
            }));
        });
    }

    /**
     * Schließt das Subpanel und zeigt den Hauptinhalt des Modals wieder an.
     *
     * @param {jQuery} $overlay
     */
    function schliesseSubpanel($overlay) {
        var $modal = $overlay.find('.local-kursassistent-modal');
        $modal.find('.local-kursassistent-subpanel').addClass('d-none').empty();
        $modal.find('.local-kursassistent-liste').removeClass('d-none');
        $modal.find('.local-kursassistent-submit, .local-kursassistent-fertig').removeClass('d-none');
        $modal.children('.mb-3').removeClass('d-none');
        // Die beim Öffnen gesetzte !important-Klasse wieder entfernen.
        $modal.find('.local-kursassistent-hauptinhalt-hidden').removeClass('local-kursassistent-hauptinhalt-hidden');
    }

    /**
     * Rendert das Panel für Abschlussverfolgung.
     *
     * @param {jQuery} $container
     * @param {Object} response Server-Antwort mit activities
     */
    function rendereAbschlussPanel($container, response) {
        $container.append($('<h5>', {text: 'Abschlussverfolgung'}));
        $container.append($('<p>', {
            'class': 'small text-muted mb-3',
            text: 'Lege fest, wie der Abschluss von Aktivitäten erfasst wird.'
        }));

        if (response.activities.length === 0) {
            $container.append($('<p>', {'class': 'text-muted', text: 'Keine Aktivitäten im Kurs gefunden.'}));
            return;
        }

        // Massenaktionen.
        var $massenzeile = $('<div>', {'class': 'd-flex gap-2 mb-3', style: 'gap:8px'});
        var massenButtons = [
            {label: 'Alle manuell', value: 1},
            {label: 'Alle automatisch', value: 2},
            {label: 'Alle deaktivieren', value: 0},
        ];
        massenButtons.forEach(function(mb) {
            var $btn = $('<button>', {
                type: 'button',
                'class': 'btn btn-sm btn-outline-secondary',
                text: mb.label
            });
            $btn.on('click', function() {
                $container.find('.local-kursassistent-completion-select').val(mb.value).trigger('change');
            });
            $massenzeile.append($btn);
        });
        $container.append($massenzeile);

        // Ursprüngliche Werte merken, um nur Änderungen zu senden.
        var originalValues = {};
        response.activities.forEach(function(act) {
            originalValues[act.cmid] = {
                completion: act.completion,
                completionview: act.completionview || 0,
                completionsubmit: act.completionsubmit || 0,
                completiongrade: act.completiongrade || 0,
                completionpassgrade: act.completionpassgrade || 0
            };
        });

        // Aktivitäts-Liste.
        var $liste = $('<div>', {'class': 'local-kursassistent-av-liste'});
        var lastSection = -1;

        response.activities.forEach(function(act) {
            if (act.sectionnum !== lastSection) {
                $liste.append($('<div>', {
                    'class': 'local-kursassistent-av-sectionheader',
                    text: act.sectionname
                }));
                lastSection = act.sectionnum;
            }

            var $row = $('<div>', {'class': 'local-kursassistent-av-row local-kursassistent-av-row-wrap', 'data-cmid': act.cmid});
            $row.append($('<span>', {'class': 'local-kursassistent-av-name', text: act.name}));

            var $select = $('<select>', {
                'class': 'form-control form-control-sm local-kursassistent-completion-select',
                'data-cmid': act.cmid
            });
            $select.append($('<option>', {value: 0, text: 'Keine', selected: act.completion === 0}));
            $select.append($('<option>', {value: 1, text: 'Manuell', selected: act.completion === 1}));
            $select.append($('<option>', {value: 2, text: 'Automatisch', selected: act.completion === 2}));
            $row.append($select);

            // Sub-Optionen für automatischen Abschluss.
            var $subopts = $('<div>', {'class': 'local-kursassistent-av-subopts'});
            var $cbView = $('<label>', {'class': 'local-kursassistent-av-subopt small'});
            var $cbViewInput = $('<input>', {
                type: 'checkbox',
                'class': 'local-kursassistent-completionview mr-1',
                'data-cmid': act.cmid,
                checked: act.completionview === 1
            });
            $cbView.append($cbViewInput, 'Bei Ansicht');

            var $cbSubmit = $('<label>', {'class': 'local-kursassistent-av-subopt small'});
            var $cbSubmitInput = $('<input>', {
                type: 'checkbox',
                'class': 'local-kursassistent-completionsubmit mr-1',
                'data-cmid': act.cmid,
                checked: act.completionsubmit === 1
            });
            $cbSubmit.append($cbSubmitInput, 'Bei Abgabe');

            var $cbReqGrade = $('<label>', {'class': 'local-kursassistent-av-subopt small'});
            var $cbReqGradeInput = $('<input>', {
                type: 'checkbox',
                'class': 'local-kursassistent-completiongrade mr-1',
                'data-cmid': act.cmid,
                checked: act.completiongrade === 1
            });
            $cbReqGrade.append($cbReqGradeInput, 'Bewertung erhalten');

            var $cbGrade = $('<label>', {'class': 'local-kursassistent-av-subopt small'});
            var $cbGradeInput = $('<input>', {
                type: 'checkbox',
                'class': 'local-kursassistent-completionpassgrade mr-1',
                'data-cmid': act.cmid,
                checked: act.completionpassgrade === 1
            });
            $cbGrade.append($cbGradeInput, 'Bei Bestehen');
            $subopts.append($cbView, $cbSubmit, $cbReqGrade, $cbGrade);

            // Nur sichtbar wenn Automatisch ausgewählt ist.
            if (act.completion !== 2) {
                $subopts.addClass('d-none');
            }
            $row.append($subopts);

            // Toggle Sub-Optionen bei Änderung.
            $select.on('change', function() {
                if (parseInt($(this).val(), 10) === 2) {
                    $subopts.removeClass('d-none');
                } else {
                    $subopts.addClass('d-none');
                }
            });

            $liste.append($row);
        });

        $container.append($liste);

        // Speichern-Button.
        var $speichern = $('<button>', {
            type: 'button',
            'class': 'btn btn-primary mt-3',
            text: 'Änderungen speichern'
        });
        $speichern.on('click', function() {
            var updates = [];
            $container.find('.local-kursassistent-completion-select').each(function() {
                var $sel = $(this);
                var cmid = parseInt($sel.data('cmid'), 10);
                var completion = parseInt($sel.val(), 10);
                var entry = {cmid: cmid, completion: completion};
                if (completion === 2) {
                    var $row = $sel.closest('.local-kursassistent-av-row');
                    entry.completionview = $row.find('.local-kursassistent-completionview').is(':checked') ? 1 : 0;
                    entry.completionsubmit = $row.find('.local-kursassistent-completionsubmit').is(':checked') ? 1 : 0;
                    entry.completiongrade = $row.find('.local-kursassistent-completiongrade').is(':checked') ? 1 : 0;
                    entry.completionpassgrade = $row.find('.local-kursassistent-completionpassgrade').is(':checked') ? 1 : 0;
                } else {
                    entry.completionview = 0;
                    entry.completionsubmit = 0;
                    entry.completiongrade = 0;
                    entry.completionpassgrade = 0;
                }

                // Nur geänderte Aktivitäten senden.
                var orig = originalValues[cmid];
                if (orig &&
                    entry.completion === orig.completion &&
                    entry.completionview === orig.completionview &&
                    entry.completionsubmit === orig.completionsubmit &&
                    entry.completiongrade === orig.completiongrade &&
                    entry.completionpassgrade === orig.completionpassgrade) {
                    return; // Keine Änderung — überspringen.
                }
                updates.push(entry);
            });

            // Nichts geändert → Hinweis statt API-Aufruf.
            if (updates.length === 0) {
                $container.find('.local-kursassistent-meldung').remove();
                $container.append($('<div>', {
                    'class': 'local-kursassistent-meldung local-kursassistent-meldung-erfolg mt-2',
                    text: 'Keine Änderungen vorgenommen.'
                }));
                return;
            }

            $speichern.prop('disabled', true).text('Speichert …');

            Ajax.call([{
                methodname: 'local_kursassistent_set_completion',
                args: {courseid: courseid, updates: updates}
            }])[0].done(function(res) {
                $speichern.prop('disabled', false).text('Änderungen speichern');
                if (res.success) {
                    etwasEingefuegt = true;
                    // Ursprüngliche Werte aktualisieren, damit erneutes Speichern korrekt vergleicht.
                    updates.forEach(function(sent) {
                        originalValues[sent.cmid] = {
                            completion: sent.completion,
                            completionview: sent.completionview,
                            completionsubmit: sent.completionsubmit,
                            completiongrade: sent.completiongrade,
                            completionpassgrade: sent.completionpassgrade
                        };
                    });
                    var $msg = $('<div>', {
                        'class': 'local-kursassistent-meldung local-kursassistent-meldung-erfolg mt-2',
                        text: res.updated.length + ' Aktivität(en) aktualisiert.'
                    });
                    $container.find('.local-kursassistent-meldung').remove();
                    $container.append($msg);
                } else {
                    Notification.alert('Hinweis', res.message, 'OK');
                }
            }).fail(function() {
                $speichern.prop('disabled', false).text('Änderungen speichern');
                Notification.alert('Fehler', 'Die Einstellungen konnten nicht gespeichert werden.', 'OK');
            });
        });
        $container.append($speichern);
    }

    /**
     * Rendert das Panel für Voraussetzungen.
     *
     * @param {jQuery} $container
     * @param {Object} response
     */
    function rendereVoraussetzungenPanel($container, response) {
        $container.append($('<h5>', {text: 'Voraussetzungen'}));
        $container.append($('<p>', {
            'class': 'small text-muted mb-3',
            text: 'Lege fest, welche Bedingung erfüllt sein muss, bevor eine Aktivität zugänglich wird: ' +
                'Abschluss einer anderen Aktivität oder eine Mindestbewertung.'
        }));

        if (response.activities.length === 0) {
            $container.append($('<p>', {'class': 'text-muted', text: 'Keine Aktivitäten im Kurs gefunden.'}));
            return;
        }

        var $liste = $('<div>', {'class': 'local-kursassistent-av-liste'});
        var lastSection = -1;

        response.activities.forEach(function(act) {
            if (act.sectionnum !== lastSection) {
                $liste.append($('<div>', {
                    'class': 'local-kursassistent-av-sectionheader',
                    text: act.sectionname
                }));
                lastSection = act.sectionnum;
            }

            var $row = $('<div>', {'class': 'local-kursassistent-av-row local-kursassistent-va-row', 'data-cmid': act.cmid});
            var $nameCol = $('<div>', {'class': 'local-kursassistent-va-name'});
            $nameCol.append($('<span>', {text: act.name}));

            // Bestehende Voraussetzungen anzeigen (Abschluss + Bewertung).
            var relevantRestrictions = act.restrictions.filter(function(r) {
                return r.type === 'completion' || r.type === 'grade';
            });
            if (relevantRestrictions.length > 0) {
                relevantRestrictions.forEach(function(r) {
                    var badgeClass = r.type === 'grade'
                        ? 'badge badge-warning local-kursassistent-va-badge'
                        : 'badge badge-info local-kursassistent-va-badge';
                    var prefix = r.type === 'grade' ? '★ ' : '← ';
                    var badgeText = prefix + (r.cmname || r.description);
                    if (r.type === 'grade' && r.description) {
                        badgeText = '★ ' + r.description;
                    }
                    var $badge = $('<span>', {'class': badgeClass, text: badgeText});
                    var $removeBtn = $('<button>', {
                        type: 'button',
                        'class': 'local-kursassistent-va-remove',
                        title: 'Entfernen',
                        'data-cmid': act.cmid,
                        'data-rtype': r.type
                    }).html('&times;');
                    $removeBtn.on('click', function(e) {
                        e.stopPropagation();
                        setzeVoraussetzung(act.cmid, 0, $row, response, r.type);
                    });
                    $badge.append($removeBtn);
                    $nameCol.append($badge);
                });
            }

            $row.append($nameCol);

            // Bedingungstyp-Auswahl.
            var $controls = $('<div>', {'class': 'local-kursassistent-va-controls'});

            var $typeSelect = $('<select>', {
                'class': 'form-control form-control-sm local-kursassistent-va-typeselect',
                'data-cmid': act.cmid
            });
            $typeSelect.append($('<option>', {value: 'completion', text: 'Abschluss'}));
            $typeSelect.append($('<option>', {value: 'grade', text: 'Bewertung'}));

            // Aktivitäts-Dropdown.
            var $actSelect = $('<select>', {
                'class': 'form-control form-control-sm local-kursassistent-va-select',
                'data-cmid': act.cmid
            });
            $actSelect.append($('<option>', {value: 0, text: '-- Keine --'}));
            response.activities.forEach(function(other) {
                if (other.cmid !== act.cmid) {
                    $actSelect.append($('<option>', {value: other.cmid, text: other.name}));
                }
            });

            // Mindestbewertung-Eingabe (nur für Bewertung sichtbar).
            var $mingradeInput = $('<input>', {
                type: 'number',
                'class': 'form-control form-control-sm local-kursassistent-va-mingrade d-none',
                min: 0, max: 100, value: 50,
                placeholder: '%',
                title: 'Mindestbewertung in %'
            });

            // Vorhandene Werte vorauswählen.
            var completionRestrictions = relevantRestrictions.filter(function(r) {
                return r.type === 'completion';
            });
            if (completionRestrictions.length > 0) {
                $actSelect.val(completionRestrictions[0].cmid);
            }

            // Toggle Mindestbewertung-Feld.
            $typeSelect.on('change', function() {
                if ($(this).val() === 'grade') {
                    $mingradeInput.removeClass('d-none');
                } else {
                    $mingradeInput.addClass('d-none');
                }
            });

            $actSelect.on('change', function() {
                var reqcmid = parseInt($(this).val(), 10);
                var rtype = $typeSelect.val();
                var mingrade = parseFloat($mingradeInput.val()) || 50;
                setzeVoraussetzung(act.cmid, reqcmid, $row, response, rtype, mingrade);
            });

            $controls.append($typeSelect, $actSelect, $mingradeInput);
            $row.append($controls);

            $liste.append($row);
        });

        $container.append($liste);
    }

    /**
     * Setzt/entfernt eine Voraussetzung via AJAX.
     *
     * @param {Number} cmid
     * @param {Number} requiredcmid
     * @param {jQuery} $row
     * @param {Object} response
     * @param {String} restrictiontype 'completion' oder 'grade'
     * @param {Number} mingrade Mindestbewertung in % (nur bei grade)
     */
    function setzeVoraussetzung(cmid, requiredcmid, $row, response, restrictiontype, mingrade) {
        restrictiontype = restrictiontype || 'completion';
        mingrade = mingrade || 50;
        $row.css('opacity', 0.5);

        Ajax.call([{
            methodname: 'local_kursassistent_set_restriction',
            args: {
                courseid: courseid,
                cmid: cmid,
                requiredcmid: requiredcmid,
                restrictiontype: restrictiontype,
                mingrade: mingrade
            }
        }])[0].done(function() {
            etwasEingefuegt = true;
            $row.css('opacity', 1);

            // Badges des jeweiligen Typs aktualisieren.
            $row.find('.local-kursassistent-va-badge').filter(function() {
                // Beim Entfernen (requiredcmid=0) nur Badges des gleichen Typs entfernen.
                if (requiredcmid > 0) {
                    return $(this).closest('[data-rtype]').length === 0 ||
                        $(this).find('[data-rtype="' + restrictiontype + '"]').length > 0;
                }
                return $(this).find('[data-rtype="' + restrictiontype + '"]').length > 0;
            }).remove();

            // Einfacher: alle Badges des Typs entfernen und neu aufbauen.
            $row.find('.local-kursassistent-va-remove[data-rtype="' + restrictiontype + '"]')
                .closest('.local-kursassistent-va-badge').remove();

            if (requiredcmid > 0) {
                var targetName = '';
                response.activities.forEach(function(a) {
                    if (a.cmid === requiredcmid) {
                        targetName = a.name;
                    }
                });
                var isGrade = restrictiontype === 'grade';
                var badgeClass = isGrade
                    ? 'badge badge-warning local-kursassistent-va-badge'
                    : 'badge badge-info local-kursassistent-va-badge';
                var badgeText = isGrade
                    ? '★ ' + targetName + ' >= ' + Math.round(mingrade) + '%'
                    : '← ' + targetName;
                var $badge = $('<span>', {'class': badgeClass, text: badgeText});
                var $removeBtn = $('<button>', {
                    type: 'button',
                    'class': 'local-kursassistent-va-remove',
                    title: 'Entfernen',
                    'data-cmid': cmid,
                    'data-rtype': restrictiontype
                }).html('&times;');
                $removeBtn.on('click', function(e) {
                    e.stopPropagation();
                    setzeVoraussetzung(cmid, 0, $row, response, restrictiontype);
                });
                $badge.append($removeBtn);
                $row.find('.local-kursassistent-va-name').append($badge);
                $row.find('.local-kursassistent-va-select').val(requiredcmid);
            } else {
                $row.find('.local-kursassistent-va-select').val(0);
            }
        }).fail(function() {
            $row.css('opacity', 1);
            Notification.alert('Fehler', 'Die Voraussetzung konnte nicht gesetzt werden.', 'OK');
        });
    }

    /**
     * Rendert das Panel für Lernpfad.
     *
     * @param {jQuery} $container
     * @param {Object} response
     */
    function rendereLernpfadPanel($container, response) {
        $container.append($('<h5>', {text: 'Lernpfad erstellen'}));
        $container.append($('<p>', {
            'class': 'small text-muted mb-3',
            text: 'Erstelle einen linearen Lernpfad: Jede Aktivität wird erst freigeschaltet, ' +
                'wenn die vorherige abgeschlossen ist. ' +
                'Wähle die Aktivitäten aus und bringe sie ggf. in die gewünschte Reihenfolge.'
        }));

        if (response.activities.length < 2) {
            $container.append($('<p>', {
                'class': 'text-muted',
                text: 'Es werden mindestens zwei Aktivitäten benötigt.'
            }));
            return;
        }

        var $alleBtn = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-outline-secondary mb-3',
            text: 'Alle auswählen'
        });
        $alleBtn.on('click', function() {
            $container.find('.local-kursassistent-lp-check').prop('checked', true);
        });
        $container.append($alleBtn);

        var $liste = $('<div>', {'class': 'local-kursassistent-av-liste local-kursassistent-lp-liste'});
        var lastSection = -1;

        response.activities.forEach(function(act) {
            if (act.sectionnum !== lastSection) {
                $liste.append($('<div>', {
                    'class': 'local-kursassistent-av-sectionheader',
                    text: act.sectionname
                }));
                lastSection = act.sectionnum;
            }

            var $row = $('<label>', {'class': 'local-kursassistent-av-row local-kursassistent-lp-row'});
            var $cb = $('<input>', {
                type: 'checkbox',
                'class': 'local-kursassistent-lp-check mr-2',
                value: act.cmid
            });
            $row.append($cb, $('<span>', {text: act.name}));
            $liste.append($row);
        });

        $container.append($liste);

        // Optionen-Bereich.
        var $optionen = $('<div>', {'class': 'local-kursassistent-lp-optionen mt-3 mb-2'});

        // 1. Abschluss aktivieren.
        var $completionCheck = $('<label>', {'class': 'small d-flex align-items-center', style: 'gap:6px'});
        var $completionCb = $('<input>', {type: 'checkbox', checked: true, 'class': 'local-kursassistent-lp-completion'});
        $completionCheck.append($completionCb, 'Abschluss automatisch aktivieren, falls nötig');
        $optionen.append($completionCheck);

        // 2. Abschlussart (nur sichtbar wenn Checkbox aktiv).
        var $completionTypeRow = $('<div>', {'class': 'small d-flex align-items-center mt-1 ml-3', style: 'gap:6px'});
        $completionTypeRow.append($('<span>', {text: 'Art:'}));
        var $completionTypeSelect = $('<select>', {
            'class': 'form-control form-control-sm local-kursassistent-lp-completiontype',
            style: 'width:180px'
        });
        $completionTypeSelect.append($('<option>', {value: 'manual', text: 'Manuell'}));
        $completionTypeSelect.append($('<option>', {value: 'view', text: 'Automatisch (bei Ansicht)'}));
        $completionTypeSelect.append($('<option>', {value: 'passgrade', text: 'Automatisch (bei Bestehen)'}));
        $completionTypeRow.append($completionTypeSelect);
        $optionen.append($completionTypeRow);

        $completionCb.on('change', function() {
            if ($(this).is(':checked')) {
                $completionTypeRow.removeClass('d-none');
            } else {
                $completionTypeRow.addClass('d-none');
            }
        });

        // 3. Verkettungstyp.
        var $chainRow = $('<div>', {'class': 'small d-flex align-items-center mt-2', style: 'gap:6px'});
        $chainRow.append($('<span>', {text: 'Verkettung über:'}));
        var $chainSelect = $('<select>', {
            'class': 'form-control form-control-sm local-kursassistent-lp-chaintype',
            style: 'width:150px'
        });
        $chainSelect.append($('<option>', {value: 'completion', text: 'Abschluss'}));
        $chainSelect.append($('<option>', {value: 'grade', text: 'Bewertung'}));
        $chainRow.append($chainSelect);

        // Mindestbewertung (nur bei Bewertung sichtbar).
        var $mingradeInput = $('<input>', {
            type: 'number',
            'class': 'form-control form-control-sm local-kursassistent-lp-mingrade d-none',
            min: 0, max: 100, value: 50,
            style: 'width:70px',
            title: 'Mindestbewertung in %'
        });
        var $mingradeLabel = $('<span>', {'class': 'local-kursassistent-lp-mingrade-label d-none', text: '%'});
        $chainRow.append($mingradeInput, $mingradeLabel);

        $chainSelect.on('change', function() {
            if ($(this).val() === 'grade') {
                $mingradeInput.removeClass('d-none');
                $mingradeLabel.removeClass('d-none');
            } else {
                $mingradeInput.addClass('d-none');
                $mingradeLabel.addClass('d-none');
            }
        });

        $optionen.append($chainRow);
        $container.append($optionen);

        var $erstellen = $('<button>', {
            type: 'button',
            'class': 'btn btn-primary',
            text: 'Lernpfad erstellen'
        });
        $erstellen.on('click', function() {
            var cmids = [];
            $container.find('.local-kursassistent-lp-check:checked').each(function() {
                cmids.push(parseInt($(this).val(), 10));
            });
            if (cmids.length < 2) {
                Notification.alert('Hinweis', 'Bitte wähle mindestens zwei Aktivitäten aus.', 'OK');
                return;
            }

            var setcompletion = $container.find('.local-kursassistent-lp-completion').is(':checked');
            var completiontype = $container.find('.local-kursassistent-lp-completiontype').val() || 'manual';
            var chaintype = $container.find('.local-kursassistent-lp-chaintype').val() || 'completion';
            var mingrade = parseFloat($container.find('.local-kursassistent-lp-mingrade').val()) || 50;

            $erstellen.prop('disabled', true).text('Erstellt Lernpfad …');

            Ajax.call([{
                methodname: 'local_kursassistent_create_lernpfad',
                args: {
                    courseid: courseid,
                    cmids: cmids,
                    setcompletion: setcompletion,
                    completiontype: completiontype,
                    chaintype: chaintype,
                    mingrade: mingrade
                }
            }])[0].done(function(res) {
                $erstellen.prop('disabled', false).text('Lernpfad erstellen');
                if (res.success) {
                    etwasEingefuegt = true;
                    $container.find('.local-kursassistent-meldung').remove();
                    $container.append($('<div>', {
                        'class': 'local-kursassistent-meldung local-kursassistent-meldung-erfolg mt-2',
                        text: 'Lernpfad erstellt: ' + res.verknuepfungen.length + ' Verknüpfung(en) gesetzt.'
                    }));
                } else {
                    Notification.alert('Hinweis', res.message, 'OK');
                }
            }).fail(function() {
                $erstellen.prop('disabled', false).text('Lernpfad erstellen');
                Notification.alert('Fehler', 'Der Lernpfad konnte nicht erstellt werden.', 'OK');
            });
        });
        $container.append($erstellen);
    }

    /**
     * Zeigt einen Serverfehler mit der tatsächlichen Meldung an.
     *
     * Ohne das geht der Grund verloren und es bleibt nur ein allgemeiner Hinweis,
     * mit dem sich ein Problem nicht eingrenzen lässt.
     *
     * @param {Object} fehler Fehlerobjekt aus core/ajax
     * @param {String} fallback Text, wenn der Server keine Meldung liefert
     */
    function zeigeServerfehler(fehler, fallback) {
        var text = fallback;
        if (fehler && fehler.message) {
            text = fehler.message;
        }
        if (fehler && fehler.debuginfo) {
            text += ' (' + fehler.debuginfo + ')';
        }
        Notification.alert('Fehler', text, 'OK');
    }

    /**
     * Zwischenspeicher für Kompetenzbaum und Zuordnungen des Kurses.
     *
     * Wird einmal je Öffnen der Übersicht geladen, damit nicht für jede Zeile
     * eine eigene Abfrage nötig ist.
     */
    var kompetenzDaten = null;

    /**
     * Lädt Kompetenzbaum und Zuordnungen und baut die Spalte auf.
     *
     * @param {jQuery} $container Panel mit der Übersichtstabelle
     */
    function ladeKompetenzen($container) {
        Ajax.call([{
            methodname: 'local_kursassistent_get_competencies',
            args: {courseid: courseid}
        }])[0].done(function(response) {
            kompetenzDaten = response;

            if (!response.available) {
                // Ohne Kompetenzraster bleibt die Spalte leer und unauffällig.
                $container.find('.local-kursassistent-kompspalte').text('');
                return;
            }

            zeigeKompetenzMarken($container);
        }).fail(function() {
            $container.find('.local-kursassistent-kompspalte').text('');
        });
    }

    /**
     * Sucht einen Deskriptor im geladenen Kompetenzbaum.
     *
     * @param {Number} descriptorid
     * @return {Object|null}
     */
    function findeDeskriptor(descriptorid) {
        if (!kompetenzDaten || !kompetenzDaten.topics) {
            return null;
        }
        var treffer = null;
        kompetenzDaten.topics.forEach(function(thema) {
            thema.descriptors.forEach(function(d) {
                if (d.descriptorid === descriptorid) {
                    treffer = d;
                }
            });
        });
        return treffer;
    }

    /**
     * Liefert die zugeordneten Deskriptor-IDs einer Aktivität.
     *
     * @param {Number} cmid
     * @return {Array}
     */
    function holeZuordnung(cmid) {
        if (!kompetenzDaten || !kompetenzDaten.assignments) {
            return [];
        }
        var gefunden = [];
        kompetenzDaten.assignments.forEach(function(z) {
            if (z.cmid === cmid) {
                gefunden = z.descriptorids;
            }
        });
        return gefunden;
    }

    /**
     * Liefert die Beschriftung einer Kompetenz für die Marke in der Übersicht.
     *
     * @param {Object|null} d Deskriptor, oder null wenn er im Kompetenzbaum fehlt
     * @param {Number} id Deskriptor-ID
     * @return {String}
     */
    function kompetenzBeschriftung(d, id) {
        if (!d) {
            return '#' + id;
        }
        return d.numbering ? d.numbering + ' ' + d.title : d.title;
    }

    /**
     * Schreibt die zugeordneten Kompetenzen als Marken in die Spalte.
     *
     * @param {jQuery} $container
     */
    function zeigeKompetenzMarken($container) {
        $container.find('.local-kursassistent-kompspalte').each(function() {
            var $zelle = $(this);
            var cmid = parseInt($zelle.data('cmid'), 10);
            var ids = holeZuordnung(cmid);

            $zelle.empty();

            if (!ids.length) {
                $zelle.append($('<span>', {'class': 'text-muted small', text: 'keine'}));
            } else {
                ids.slice(0, 2).forEach(function(id) {
                    var beschriftung = kompetenzBeschriftung(findeDeskriptor(id), id);
                    $zelle.append($('<span>', {
                        'class': 'local-kursassistent-kompmarke',
                        title: beschriftung,
                        text: beschriftung
                    }));
                });
                if (ids.length > 2) {
                    $zelle.append($('<span>', {
                        'class': 'text-muted small ml-1',
                        text: '+' + (ids.length - 2)
                    }));
                }
            }

            $zelle.append($('<button>', {
                type: 'button',
                'class': 'btn btn-sm p-0 ml-1 local-kursassistent-komp-btn',
                'data-cmid': cmid,
                title: 'Kompetenzen zuordnen',
                text: '+'
            }));
        });
    }

    /**
     * Baut die Zeile einer einzelnen Kompetenz mit Auswahlfeld.
     *
     * Bereits zugeordnete Kompetenzen sind angehakt. Nimmt man den Haken heraus und
     * speichert, wird die Zuordnung entfernt.
     *
     * @param {Object} d Deskriptor
     * @param {Array} bereits Bereits zugeordnete Deskriptor-IDs
     * @return {jQuery}
     */
    function baueKompetenzZeile(d, bereits) {
        var $zeile = $('<label>', {'class': 'local-kursassistent-kompzeile'});
        var $haken = $('<input>', {
            type: 'checkbox',
            'class': 'local-kursassistent-kompauswahl',
            value: d.descriptorid
        });
        if (bereits.indexOf(d.descriptorid) !== -1) {
            $haken.prop('checked', true);
        }
        var text = (d.numbering ? d.numbering + ' ' : '') + d.title +
            (d.niveau ? ' (' + d.niveau + ')' : '');
        $zeile.append($haken, $('<span>', {text: text}));
        return $zeile;
    }

    /**
     * Baut den einklappbaren Block eines Kompetenzthemas.
     *
     * Themen mit bereits zugeordneten Kompetenzen sind zunächst aufgeklappt, alle
     * anderen eingeklappt, damit die Liste übersichtlich bleibt. Bei nur einem Thema
     * ist es immer aufgeklappt.
     *
     * @param {Object} thema Thema mit seinen Deskriptoren
     * @param {Array} bereits Bereits zugeordnete Deskriptor-IDs
     * @param {Boolean} immerOffen Thema von Anfang an aufklappen
     * @return {jQuery}
     */
    function baueKompetenzThema(thema, bereits, immerOffen) {
        var titel = thema.subject + ' – ' +
            (thema.numbering ? thema.numbering + ' ' : '') + thema.title;
        var zugeordnet = thema.descriptors.filter(function(d) {
            return bereits.indexOf(d.descriptorid) !== -1;
        }).length;
        var offen = immerOffen || zugeordnet > 0;

        var $block = $('<div>', {'class': 'local-kursassistent-kompblock'});
        var $kopf = $('<button>', {
            type: 'button',
            'class': 'local-kursassistent-kompthema',
            'aria-expanded': offen ? 'true' : 'false'
        });
        var $pfeil = $('<span>', {
            'class': 'local-kursassistent-gruppenpfeil',
            text: offen ? '▾' : '▸'
        });
        var $auswahlzahl = $('<span>', {
            'class': 'local-kursassistent-kompauswahlzahl',
            text: zugeordnet ? zugeordnet + ' ausgewählt' : ''
        });
        $kopf.append($pfeil, $('<span>', {'class': 'local-kursassistent-kompthematitel', text: titel}),
            $('<span>', {
                'class': 'local-kursassistent-kompanzahl',
                text: '(' + thema.descriptors.length + ')'
            }), $auswahlzahl);

        var $inhalt = $('<div>', {'class': 'local-kursassistent-kompinhalt' + (offen ? '' : ' d-none')});
        thema.descriptors.forEach(function(d) {
            $inhalt.append(baueKompetenzZeile(d, bereits));
        });

        $kopf.on('click', function() {
            var aufgeklappt = $inhalt.hasClass('d-none');
            $inhalt.toggleClass('d-none', !aufgeklappt);
            $pfeil.text(aufgeklappt ? '▾' : '▸');
            $kopf.attr('aria-expanded', aufgeklappt ? 'true' : 'false');
        });

        // Bei eingeklapptem Thema zeigt der Kopf, wie viele Kompetenzen gewählt sind.
        $inhalt.on('change', '.local-kursassistent-kompauswahl', function() {
            var anzahl = $inhalt.find('.local-kursassistent-kompauswahl:checked').length;
            $auswahlzahl.text(anzahl ? anzahl + ' ausgewählt' : '');
        });

        return $block.append($kopf, $inhalt);
    }

    /**
     * Liest die vollständige Auswahl aus dem Kompetenzdialog.
     *
     * @param {jQuery} $panel
     * @return {Array} Deskriptor-IDs aller angehakten Kompetenzen
     */
    function leseKompetenzAuswahl($panel) {
        var auswahl = [];
        $panel.find('.local-kursassistent-kompauswahl:checked').each(function() {
            auswahl.push(parseInt($(this).val(), 10));
        });
        return auswahl;
    }

    /**
     * Vergleicht die aktuelle Auswahl mit dem bisherigen Stand.
     *
     * @param {Array} bereits Bisher zugeordnete Deskriptor-IDs
     * @param {Array} auswahl Aktuell angehakte Deskriptor-IDs
     * @return {Object} Listen der neuen und der entfernten Deskriptor-IDs
     */
    function vergleicheKompetenzAuswahl(bereits, auswahl) {
        return {
            neu: auswahl.filter(function(id) {
                return bereits.indexOf(id) === -1;
            }),
            entfernt: bereits.filter(function(id) {
                return auswahl.indexOf(id) === -1;
            })
        };
    }

    /**
     * Zeigt im Dialog an, was beim Speichern hinzukommt oder wegfällt.
     *
     * @param {jQuery} $panel
     * @param {Array} bereits Bisher zugeordnete Deskriptor-IDs
     */
    function zeigeKompetenzAenderung($panel, bereits) {
        var unterschied = vergleicheKompetenzAuswahl(bereits, leseKompetenzAuswahl($panel));
        var teile = [];
        if (unterschied.neu.length) {
            teile.push(unterschied.neu.length + ' hinzufügen');
        }
        if (unterschied.entfernt.length) {
            teile.push(unterschied.entfernt.length + ' entfernen');
        }
        $panel.find('.local-kursassistent-kompaenderung')
            .text(teile.length ? 'Beim Speichern: ' + teile.join(', ') : '')
            .toggleClass('local-kursassistent-kompaenderung-warnung', unterschied.entfernt.length > 0);
    }

    /**
     * Öffnet den Auswahlbereich für die Kompetenzen einer Aktivität.
     *
     * @param {jQuery} $container
     * @param {Number} cmid
     */
    function oeffneKompetenzDialog($container, cmid) {
        $container.find('.local-kursassistent-komp-panel').remove();

        if (!kompetenzDaten || !kompetenzDaten.topics.length) {
            zeigePanelMeldung($container,
                'Für diesen Kurs sind noch keine Kompetenzen eingerichtet.', 'hinweis');
            return;
        }

        var bereits = holeZuordnung(cmid);
        var $panel = $('<div>', {'class': 'local-kursassistent-komp-panel'});
        $panel.append($('<p>', {'class': 'font-weight-bold mb-1', text: 'Kompetenzen zuordnen'}));
        $panel.append($('<p>', {
            'class': 'small text-muted mb-2',
            text: 'Die Auswahl wird als Exabis-Lernmaterial gespeichert und mit dieser ' +
                'Aktivität verknüpft. Sie erscheint anschliessend auch im Kompetenzraster. ' +
                'Angehakte Kompetenzen sind zugeordnet, nimm den Haken heraus, um eine ' +
                'Zuordnung zu entfernen. Mindestens eine Kompetenz muss zugeordnet bleiben.'
        }));

        var $baum = $('<div>', {'class': 'local-kursassistent-kompbaum'});
        var nurEinThema = kompetenzDaten.topics.length === 1;
        kompetenzDaten.topics.forEach(function(thema) {
            $baum.append(baueKompetenzThema(thema, bereits, nurEinThema));
        });
        $panel.append($baum);
        $panel.append($('<div>', {'class': 'small mt-2 local-kursassistent-kompaenderung'}));

        $baum.on('change', '.local-kursassistent-kompauswahl', function() {
            zeigeKompetenzAenderung($panel, bereits);
        });

        var $fuss = $('<div>', {'class': 'd-flex justify-content-end mt-2'});
        var $abbrechen = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-secondary mr-2 local-kursassistent-komp-abbrechen',
            text: 'Abbrechen'
        });
        var $speichern = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-primary local-kursassistent-komp-speichern',
            text: 'Zuordnung speichern'
        });
        $fuss.append($abbrechen, $speichern);
        $panel.append($fuss);

        $abbrechen.on('click', function() {
            $panel.remove();
        });

        $speichern.on('click', function() {
            var auswahl = leseKompetenzAuswahl($panel);
            var unterschied = vergleicheKompetenzAuswahl(bereits, auswahl);

            if (!auswahl.length) {
                zeigePanelMeldung($container,
                    'Mindestens eine Kompetenz muss zugeordnet bleiben.', 'hinweis');
                return;
            }
            if (!unterschied.neu.length && !unterschied.entfernt.length) {
                zeigePanelMeldung($container, 'Es gibt nichts zu speichern.', 'hinweis');
                return;
            }

            $speichern.prop('disabled', true).text('Wird gespeichert …');

            Ajax.call([{
                methodname: 'local_kursassistent_replace_competencies',
                args: {courseid: courseid, cmid: cmid, descriptorids: auswahl}
            }])[0].done(function() {
                $panel.remove();
                zeigePanelMeldung($container, 'Die Zuordnung wurde gespeichert.', 'erfolg');
                ladeKompetenzen($container);
            }).fail(function(fehler) {
                $speichern.prop('disabled', false).text('Zuordnung speichern');
                zeigeServerfehler(fehler, 'Die Kompetenzen konnten nicht zugeordnet werden.');
            });
        });

        $container.append($panel);
        $panel[0].scrollIntoView({block: 'nearest'});
    }

    /**
     * Aktiviert das Umsortieren der Aktivitäten per Ziehen.
     *
     * Nutzt Moodles core/sortable_list, damit Verhalten und Tastaturbedienung
     * denen der Kursseite entsprechen.
     *
     * @param {jQuery} $container Panel mit der Übersichtstabelle
     */
    function aktiviereSortierung($container) {
        var $tbody = $container.find('.local-kursassistent-uebersicht-table tbody');
        if (!$tbody.length) {
            return;
        }

        new SortableList($tbody, {
            moveHandlerSelector: '.local-kursassistent-griff'
        });

        $tbody.find('.local-kursassistent-sortrow').on(SortableList.EVENTS.DROP, function() {
            var $zeile = $(this);
            speichereReihenfolge($container, $zeile);
        });
    }

    /**
     * Speichert die neue Position einer verschobenen Aktivität.
     *
     * @param {jQuery} $container
     * @param {jQuery} $zeile Die verschobene Tabellenzeile
     */
    function speichereReihenfolge($container, $zeile) {
        var cmid = parseInt($zeile.data('cmid'), 10);

        // Abschnitt aus der vorausgehenden Abschnittszeile ableiten.
        var sectionnum = null;
        $zeile.prevAll('tr').each(function() {
            if (sectionnum !== null) {
                return;
            }
            var $vor = $(this);
            if ($vor.hasClass('local-kursassistent-section-row')) {
                sectionnum = parseInt($vor.find('.local-kursassistent-vis-toggle').data('id'), 10);
            }
        });
        if (sectionnum === null) {
            sectionnum = parseInt($zeile.data('sectionnum'), 10) || 0;
        }

        // Nachfolgende Aktivität bestimmen, vor die einsortiert wird.
        var beforecmid = 0;
        var $naechste = $zeile.nextAll('.local-kursassistent-sortrow').first();
        if ($naechste.length) {
            beforecmid = parseInt($naechste.data('cmid'), 10) || 0;
        }

        Ajax.call([{
            methodname: 'local_kursassistent_move_activity',
            args: {courseid: courseid, cmid: cmid, sectionnum: sectionnum, beforecmid: beforecmid}
        }])[0].done(function() {
            $zeile.data('sectionnum', sectionnum);
            zeigePanelMeldung($container, 'Die neue Reihenfolge wurde gespeichert.', 'erfolg');
        }).fail(function(fehler) {
            zeigeServerfehler(fehler, 'Die Reihenfolge konnte nicht gespeichert werden.');
        });
    }

    /**
     * Zeigt eine kurze Meldung oberhalb eines Panels an.
     *
     * @param {jQuery} $container
     * @param {String} text
     * @param {String} art 'erfolg' oder 'hinweis'
     */
    function zeigePanelMeldung($container, text, art) {
        $container.find('.local-kursassistent-panelmeldung').remove();
        $container.prepend($('<div>', {
            'class': 'local-kursassistent-panelmeldung local-kursassistent-meldung ' +
                'local-kursassistent-meldung-' + art,
            text: text
        }));
    }

    /**
     * Öffnet den Dialog zum Duplizieren einer Aktivität.
     *
     * @param {jQuery} $container
     * @param {Number} cmid
     * @param {String} name Name der Aktivität
     */
    function oeffneDuplizierenDialog($container, cmid, name) {
        $container.find('.local-kursassistent-dupl-panel').remove();

        var $panel = $('<div>', {'class': 'local-kursassistent-dupl-panel'});
        $panel.append($('<p>', {'class': 'font-weight-bold mb-1', text: 'Aktivität duplizieren'}));
        $panel.append($('<p>', {'class': 'small text-muted mb-3', text: name}));

        var gruppe = 'ka-dupl-ziel-' + cmid;

        var $optHier = $('<label>', {'class': 'local-kursassistent-dupl-option'});
        var $radioHier = $('<input>', {type: 'radio', name: gruppe, value: 'hier', checked: true});
        $optHier.append($radioHier, $('<span>').append(
            $('<span>', {'class': 'd-block', text: 'In diesen Kurs'}),
            $('<span>', {'class': 'small text-muted', text: 'Kopie landet direkt hinter dem Original'})
        ));

        var $optAnder = $('<label>', {'class': 'local-kursassistent-dupl-option'});
        var $radioAnder = $('<input>', {type: 'radio', name: gruppe, value: 'anderer'});
        $optAnder.append($radioAnder, $('<span>').append(
            $('<span>', {'class': 'd-block', text: 'In einen anderen Kurs'}),
            $('<span>', {'class': 'small text-muted', text: 'Nur Kurse, in denen du bearbeiten darfst'})
        ));

        var $zielBereich = $('<div>', {'class': 'local-kursassistent-dupl-ziel d-none'});
        var $kursSuche = $('<input>', {
            type: 'text',
            'class': 'form-control form-control-sm mb-2 local-kursassistent-dupl-suche',
            placeholder: 'Kurs suchen …'
        });
        var $kursListe = $('<select>', {
            'class': 'form-control form-control-sm mb-2 local-kursassistent-dupl-kurs',
            size: 5
        });
        var $abschnittListe = $('<select>', {
            'class': 'form-control form-control-sm local-kursassistent-dupl-abschnitt'
        });
        $zielBereich.append($kursSuche, $kursListe, $abschnittListe);

        var $fuss = $('<div>', {'class': 'd-flex justify-content-end mt-3'});
        var $abbrechen = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-secondary mr-2 local-kursassistent-dupl-abbrechen',
            text: 'Abbrechen'
        });
        var $starten = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-primary local-kursassistent-dupl-start',
            text: 'Duplizieren'
        });
        $fuss.append($abbrechen, $starten);

        $panel.append($optHier, $optAnder, $zielBereich, $fuss);
        $container.append($panel);

        $panel.find('input[type="radio"]').on('change', function() {
            var anderer = $radioAnder.is(':checked');
            $zielBereich.toggleClass('d-none', !anderer);
            if (anderer && !$kursListe.children().length) {
                ladeZielkurse($kursListe, $abschnittListe, '');
            }
        });

        var suchTimer = null;
        $kursSuche.on('input', function() {
            var begriff = $(this).val();
            window.clearTimeout(suchTimer);
            suchTimer = window.setTimeout(function() {
                ladeZielkurse($kursListe, $abschnittListe, begriff);
            }, 300);
        });

        $kursListe.on('change', function() {
            ladeZielabschnitte($abschnittListe, parseInt($(this).val(), 10));
        });

        $abbrechen.on('click', function() {
            $panel.remove();
        });

        $starten.on('click', function() {
            var zielkurs = 0;
            var zielabschnitt = -1;
            if ($radioAnder.is(':checked')) {
                zielkurs = parseInt($kursListe.val(), 10) || 0;
                zielabschnitt = parseInt($abschnittListe.val(), 10);
                if (!zielkurs) {
                    zeigePanelMeldung($container, 'Bitte zuerst einen Zielkurs auswählen.', 'hinweis');
                    return;
                }
            }
            fuehreDuplizierenAus($container, $panel, $starten, cmid, zielkurs, zielabschnitt);
        });

        $panel[0].scrollIntoView({block: 'nearest'});
    }

    /**
     * Lädt die Kurse, in die eingefügt werden darf.
     *
     * @param {jQuery} $kursListe
     * @param {jQuery} $abschnittListe
     * @param {String} begriff Suchbegriff
     */
    function ladeZielkurse($kursListe, $abschnittListe, begriff) {
        $kursListe.empty().append($('<option>', {text: 'Lade Kurse …', disabled: true}));

        Ajax.call([{
            methodname: 'local_kursassistent_get_target_courses',
            args: {courseid: courseid, query: begriff}
        }])[0].done(function(response) {
            $kursListe.empty();
            if (!response.courses.length) {
                $kursListe.append($('<option>', {text: 'Kein passender Kurs gefunden.', disabled: true}));
                $abschnittListe.empty();
                return;
            }
            response.courses.forEach(function(kurs) {
                var beschriftung = kurs.fullname + (kurs.iscurrent ? ' (aktueller Kurs)' : '');
                $kursListe.append($('<option>', {value: kurs.id, text: beschriftung}));
            });
            ladeZielabschnitte($abschnittListe, parseInt($kursListe.val(), 10));
        }).fail(function() {
            $kursListe.empty().append($('<option>', {text: 'Kurse konnten nicht geladen werden.', disabled: true}));
        });
    }

    /**
     * Lädt die Abschnitte des gewählten Zielkurses.
     *
     * @param {jQuery} $abschnittListe
     * @param {Number} zielkursid
     */
    function ladeZielabschnitte($abschnittListe, zielkursid) {
        if (!zielkursid) {
            $abschnittListe.empty();
            return;
        }
        $abschnittListe.empty().append($('<option>', {text: 'Lade Abschnitte …', disabled: true}));

        Ajax.call([{
            methodname: 'local_kursassistent_get_course_sections_list',
            args: {courseid: zielkursid}
        }])[0].done(function(response) {
            $abschnittListe.empty();
            response.sections.forEach(function(abschnitt) {
                $abschnittListe.append($('<option>', {
                    value: abschnitt.sectionnum,
                    text: abschnitt.name
                }));
            });
        }).fail(function() {
            $abschnittListe.empty().append($('<option>', {text: 'Abschnitte nicht verfügbar', disabled: true}));
        });
    }

    /**
     * Führt das Duplizieren aus und meldet das Ergebnis zurück.
     *
     * @param {jQuery} $container
     * @param {jQuery} $panel
     * @param {jQuery} $starten Schaltfläche, die währenddessen gesperrt wird
     * @param {Number} cmid
     * @param {Number} zielkurs 0 für denselben Kurs
     * @param {Number} zielabschnitt -1 für hinter dem Original
     */
    function fuehreDuplizierenAus($container, $panel, $starten, cmid, zielkurs, zielabschnitt) {
        $starten.prop('disabled', true).text('Kopie wird erstellt …');

        Ajax.call([{
            methodname: 'local_kursassistent_duplicate_activity',
            args: {
                courseid: courseid,
                cmid: cmid,
                targetcourseid: zielkurs,
                targetsectionnum: zielabschnitt
            }
        }])[0].done(function(response) {
            $panel.remove();
            if (response.targetcourseid === courseid) {
                zeigePanelMeldung($container, 'Die Kopie wurde erstellt.', 'erfolg');
                ladeUebersichtNeu($container);
                return;
            }
            $container.find('.local-kursassistent-panelmeldung').remove();
            var $meldung = $('<div>', {
                'class': 'local-kursassistent-panelmeldung local-kursassistent-meldung ' +
                    'local-kursassistent-meldung-erfolg'
            });
            $meldung.append($('<span>', {text: 'Die Kopie wurde im anderen Kurs angelegt. '}));
            $meldung.append($('<a>', {
                href: response.targetcourseurl,
                target: '_blank',
                text: 'Zum Zielkurs'
            }));
            $container.prepend($meldung);
        }).fail(function(fehler) {
            $starten.prop('disabled', false).text('Duplizieren');
            zeigeServerfehler(fehler, 'Die Aktivität konnte nicht dupliziert werden.');
        });
    }

    /**
     * Lädt die Übersicht neu, etwa nach dem Duplizieren im selben Kurs.
     *
     * @param {jQuery} $container
     */
    function ladeUebersichtNeu($container) {
        Ajax.call([{
            methodname: 'local_kursassistent_get_course_activities',
            args: {courseid: courseid}
        }])[0].done(function(response) {
            var $meldung = $container.find('.local-kursassistent-panelmeldung').detach();
            $container.empty();
            if ($meldung.length) {
                $container.append($meldung);
            }
            rendereUebersichtPanel($container, response);
        }).fail(function() {
            Notification.alert('Fehler', 'Die Übersicht konnte nicht neu geladen werden.', 'OK');
        });
    }

    /**
     * Rendert das Übersichts-Panel mit Sichtbarkeitssteuerung.
     *
     * @param {jQuery} $container
     * @param {Object} response
     */
    function rendereUebersichtPanel($container, response) {
        var neueAktivitaet = verbraucheNeueAktivitaet(response.activities);
        $container.append($('<h5>', {text: 'Übersicht: Abschluss, Voraussetzungen & Sichtbarkeit'}));

        if (!response.completionenabled) {
            $container.append($('<div>', {
                'class': 'alert alert-warning',
                text: 'Die Abschlussverfolgung ist in diesem Kurs nicht aktiviert.'
            }));
        }

        if (response.activities.length === 0) {
            $container.append($('<p>', {'class': 'text-muted', text: 'Keine Aktivitäten im Kurs gefunden.'}));
            return;
        }

        // Sichtbarkeit: Massenaktionen.
        var $sichtbarkeitBar = $('<div>', {'class': 'd-flex gap-2 mb-3 align-items-center', style: 'gap:8px'});
        $sichtbarkeitBar.append($('<span>', {'class': 'small font-weight-bold', text: 'Sichtbarkeit:'}));
        var $alleEinBtn = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-outline-secondary',
            text: 'Alle einblenden'
        });
        var $alleAusBtn = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-outline-secondary',
            text: 'Alle ausblenden'
        });
        $sichtbarkeitBar.append($alleEinBtn, $alleAusBtn);
        $container.append($sichtbarkeitBar);

        var $tabelle = $('<table>', {'class': 'table table-sm table-striped local-kursassistent-uebersicht-table'});
        var $thead = $('<thead>').append(
            $('<tr>').append(
                $('<th>', {text: '', style: 'width:28px'}),
                $('<th>', {text: 'Aktivität'}),
                $('<th>', {text: 'Sichtbar', 'class': 'text-center', style: 'width:70px'}),
                $('<th>', {text: 'Kopie', 'class': 'text-center', style: 'width:60px'}),
                $('<th>', {text: 'Abschluss'}),
                $('<th>', {text: 'Voraussetzungen'}),
                $('<th>', {text: 'Kompetenzen'})
            )
        );
        $tabelle.append($thead);

        var $tbody = $('<tbody>');
        var lastSection = -1;
        var completionLabels = {'0': 'Keine', '1': 'Manuell', '2': 'Automatisch'};

        // Abschnitte für Sichtbarkeits-Toggle sammeln.
        var sectionsSeen = {};

        response.activities.forEach(function(act) {
            if (act.sectionnum !== lastSection) {
                sectionsSeen[act.sectionnum] = true;
                var sectionVisible = act.sectionvisible !== undefined ? act.sectionvisible : 1;
                var $sectionRow = $('<tr>', {'class': 'local-kursassistent-section-row'});
                var $sectionTd = $('<td>', {
                    'class': 'font-weight-bold local-kursassistent-av-sectionheader',
                    text: act.sectionname
                });
                var $sectionVisCell = $('<td>', {'class': 'text-center'});
                var $sectionToggle = $('<button>', {
                    type: 'button',
                    'class': 'btn btn-sm p-0 local-kursassistent-vis-toggle',
                    'data-id': act.sectionnum,
                    'data-type': 'section',
                    'data-visible': sectionVisible,
                    title: sectionVisible ? 'Abschnitt ausblenden' : 'Abschnitt einblenden'
                });
                $sectionToggle.append($('<img>', {
                    src: M.cfg.wwwroot + '/local/kursassistent/pix/' +
                        (sectionVisible ? 'eye.svg' : 'eye-off.svg'),
                    alt: sectionVisible ? 'Sichtbar' : 'Verborgen',
                    'class': 'local-kursassistent-vis-icon'
                }));
                if (!sectionVisible) {
                    $sectionToggle.addClass('local-kursassistent-vis-hidden');
                }
                $sectionVisCell.append($sectionToggle);
                $sectionRow.append($('<td>'), $sectionTd, $sectionVisCell,
                    $('<td>'), $('<td>'), $('<td>'), $('<td>'));
                $tbody.append($sectionRow);
                lastSection = act.sectionnum;
            }

            var completionText = baueAbschlussText(act, completionLabels);
            var restrictionTexts = baueBedingungsTexte(act.restrictions);

            var actVisible = act.visible !== undefined ? act.visible : 1;
            var $row = $('<tr>', {
                'class': 'local-kursassistent-sortrow',
                'data-cmid': act.cmid,
                'data-sectionnum': act.sectionnum
            });

            // Anfasser zum Umsortieren.
            var $griffCell = $('<td>', {'class': 'local-kursassistent-griffzelle'});
            $griffCell.append($('<span>', {
                'class': 'local-kursassistent-griff',
                title: 'Zum Verschieben ziehen',
                'aria-hidden': 'true',
                text: '⠿'
            }));
            $row.append($griffCell);

            var $nameCell = $('<td>', {text: act.name});
            if (!actVisible) {
                $nameCell.addClass('text-muted');
            }
            $row.append($nameCell);

            // Sichtbarkeits-Toggle.
            var $visCell = $('<td>', {'class': 'text-center'});
            var $visToggle = $('<button>', {
                type: 'button',
                'class': 'btn btn-sm p-0 local-kursassistent-vis-toggle',
                'data-id': act.cmid,
                'data-type': 'activity',
                'data-visible': actVisible,
                title: actVisible ? 'Aktivität ausblenden' : 'Aktivität einblenden'
            });
            $visToggle.append($('<img>', {
                src: M.cfg.wwwroot + '/local/kursassistent/pix/' +
                    (actVisible ? 'eye.svg' : 'eye-off.svg'),
                alt: actVisible ? 'Sichtbar' : 'Verborgen',
                'class': 'local-kursassistent-vis-icon'
            }));
            if (!actVisible) {
                $visToggle.addClass('local-kursassistent-vis-hidden');
            }
            $visCell.append($visToggle);
            $row.append($visCell);

            // Duplizieren.
            var $duplCell = $('<td>', {'class': 'text-center'});
            var $duplBtn = $('<button>', {
                type: 'button',
                'class': 'btn btn-sm p-0 local-kursassistent-dupl-btn',
                'data-cmid': act.cmid,
                'data-name': act.name,
                title: 'Aktivität duplizieren'
            });
            $duplBtn.append($('<img>', {
                src: M.cfg.wwwroot + '/local/kursassistent/pix/copy.svg',
                alt: 'Duplizieren',
                'class': 'local-kursassistent-vis-icon'
            }));
            $duplCell.append($duplBtn);
            $row.append($duplCell);

            var $completionCell = $('<td>');
            var badgeClasses = {'0': 'badge-secondary', '1': 'badge-primary', '2': 'badge-success'};
            var badgeClass = badgeClasses[String(act.completion)] || 'badge-success';
            $completionCell.append($('<span>', {
                'class': 'badge ' + badgeClass,
                text: completionText
            }));
            $row.append($completionCell);
            $row.append($('<td>', {text: restrictionTexts.length > 0 ? restrictionTexts.join('; ') : '–'}));

            // Spalte für die Kompetenzen. Befüllt wird sie nachgelagert, sobald der
            // Kompetenzbaum geladen ist.
            $row.append($('<td>', {
                'class': 'local-kursassistent-kompspalte',
                'data-cmid': act.cmid
            }));
            $tbody.append($row);
        });

        $tabelle.append($tbody);
        $container.append($tabelle);

        // Sichtbarkeits-Toggle-Handler.
        $container.on('click', '.local-kursassistent-vis-toggle', function() {
            var $btn = $(this);
            var id = parseInt($btn.data('id'), 10);
            var type = $btn.data('type');
            var currentVisible = parseInt($btn.data('visible'), 10);
            var newVisible = currentVisible ? 0 : 1;

            $btn.css('opacity', 0.4);

            Ajax.call([{
                methodname: 'local_kursassistent_set_visibility',
                args: {
                    courseid: courseid,
                    updates: [{id: id, type: type, visible: newVisible}]
                }
            }])[0].done(function() {
                etwasEingefuegt = true;
                $btn.data('visible', newVisible).attr('data-visible', newVisible);
                $btn.find('img').attr('src', M.cfg.wwwroot + '/local/kursassistent/pix/' +
                    (newVisible ? 'eye.svg' : 'eye-off.svg'))
                    .attr('alt', newVisible ? 'Sichtbar' : 'Verborgen');
                var objekt = type === 'section' ? 'Abschnitt' : 'Aktivität';
                var aktion = newVisible ? ' ausblenden' : ' einblenden';
                $btn.attr('title', objekt + aktion);
                $btn.toggleClass('local-kursassistent-vis-hidden', !newVisible);
                $btn.css('opacity', 1);

                // Name-Zelle updaten (text-muted bei unsichtbar).
                if (type === 'activity') {
                    $btn.closest('tr').find('td:first').toggleClass('text-muted', !newVisible);
                }
            }).fail(function() {
                $btn.css('opacity', 1);
                Notification.alert('Fehler', 'Die Sichtbarkeit konnte nicht geändert werden.', 'OK');
            });
        });

        // Massenaktionen: Alle ein-/ausblenden.
        $alleEinBtn.on('click', function() {
            massenSichtbarkeit($container, 1);
        });
        $alleAusBtn.on('click', function() {
            massenSichtbarkeit($container, 0);
        });

        // Umsortieren per Ziehen aktivieren.
        aktiviereSortierung($container);

        // Kompetenzen nachladen und Spalte befüllen.
        ladeKompetenzen($container);

        if (neueAktivitaet) {
            markiereNeueAktivitaet($container, neueAktivitaet);
        }

        $container.off('click.kakompetenz').on('click.kakompetenz',
            '.local-kursassistent-komp-btn', function() {
                oeffneKompetenzDialog($container, parseInt($(this).data('cmid'), 10));
            });

        // Duplizieren-Schaltflächen.
        $container.off('click.kaduplizieren').on('click.kaduplizieren',
            '.local-kursassistent-dupl-btn', function() {
                var $btn = $(this);
                oeffneDuplizierenDialog($container, parseInt($btn.data('cmid'), 10),
                    String($btn.data('name')));
            });
    }

    /**
     * Setzt die Sichtbarkeit aller Aktivitäten und Abschnitte auf einmal.
     *
     * @param {jQuery} $container
     * @param {Number} visible 1=einblenden, 0=ausblenden
     */
    function massenSichtbarkeit($container, visible) {
        var updates = [];
        $container.find('.local-kursassistent-vis-toggle').each(function() {
            var $btn = $(this);
            var current = parseInt($btn.data('visible'), 10);
            if (current !== visible) {
                updates.push({
                    id: parseInt($btn.data('id'), 10),
                    type: $btn.data('type'),
                    visible: visible
                });
            }
        });

        if (updates.length === 0) {
            return;
        }

        $container.find('.local-kursassistent-vis-toggle').css('opacity', 0.4);

        Ajax.call([{
            methodname: 'local_kursassistent_set_visibility',
            args: {courseid: courseid, updates: updates}
        }])[0].done(function() {
            etwasEingefuegt = true;
            $container.find('.local-kursassistent-vis-toggle').each(function() {
                var $btn = $(this);
                $btn.data('visible', visible).attr('data-visible', visible);
                $btn.find('img').attr('src', M.cfg.wwwroot + '/local/kursassistent/pix/' +
                    (visible ? 'eye.svg' : 'eye-off.svg'))
                    .attr('alt', visible ? 'Sichtbar' : 'Verborgen');
                $btn.toggleClass('local-kursassistent-vis-hidden', !visible);
                $btn.css('opacity', 1);
                if ($btn.data('type') === 'activity') {
                    $btn.closest('tr').find('td:first').toggleClass('text-muted', !visible);
                }
            });
        }).fail(function() {
            $container.find('.local-kursassistent-vis-toggle').css('opacity', 1);
            Notification.alert('Fehler', 'Die Sichtbarkeit konnte nicht geändert werden.', 'OK');
        });
    }

    // ---------------------------------------------------------------
    // Abschnittsvorlagen-Panel
    // ---------------------------------------------------------------

    /**
     * Rendert das Panel für Abschnittsvorlagen.
     *
     * @param {jQuery} $container
     * @param {jQuery} $overlay
     */
    function rendereAbschnittsvorlagenPanel($container, $overlay) {
        $container.append($('<h5>', {text: 'Abschnittsvorlagen'}));
        $container.append($('<p>', {
            'class': 'small text-muted mb-3',
            text: 'Wende vorgefertigte Vorlagen auf Kursabschnitte an oder erstelle ' +
                'eigene Vorlagen aus deiner aktuellen Baustein-Auswahl.'
        }));

        // Zielabschnitt-Auswahl (Optionen aus dem Hauptdropdown übernehmen).
        var $sectionWrap = $('<div>', {'class': 'mb-3'});
        $sectionWrap.append($('<label>', {'class': 'small text-muted d-block mb-1', text: 'Zielabschnitt für Vorlage'}));
        var $sectionSelect = $('<select>', {'class': 'form-control local-kursassistent-sectpl-section'});
        $overlay.find('.local-kursassistent-section option').each(function() {
            $sectionSelect.append($('<option>', {value: $(this).val(), text: $(this).text()}));
        });
        // Vorauswahl auf aktuell gewählten Abschnitt setzen.
        var aktuell = $overlay.find('.local-kursassistent-section').val();
        if (aktuell) {
            $sectionSelect.val(aktuell);
        }
        $sectionWrap.append($sectionSelect);
        $container.append($sectionWrap);

        var $loader = $('<div>', {'class': 'text-center py-3'});
        $loader.append($('<span>', {'class': 'text-muted', text: 'Lade Vorlagen …'}));
        $container.append($loader);

        Ajax.call([{
            methodname: 'local_kursassistent_get_section_templates',
            args: {courseid: courseid}
        }])[0].done(function(response) {
            $loader.remove();
            rendereVorlagenListe($container, response, $overlay);
        }).fail(function() {
            $loader.remove();
            $container.append($('<div>', {
                'class': 'alert alert-danger',
                text: 'Vorlagen konnten nicht geladen werden.'
            }));
        });
    }

    /**
     * Rendert die Vorlagenliste und die Steuerungselemente.
     *
     * @param {jQuery} $container
     * @param {Object} response Server-Antwort mit templates
     * @param {jQuery} $overlay
     */
    function rendereVorlagenListe($container, response, $overlay) {
        // Bestehende Liste und Buttons entfernen (bei Neuaufbau).
        $container.find('.local-kursassistent-sectpl-content').remove();

        var $content = $('<div>', {'class': 'local-kursassistent-sectpl-content'});

        if (response.templates.length === 0) {
            $content.append($('<p>', {
                'class': 'text-muted mb-3',
                text: 'Es sind noch keine Vorlagen vorhanden.'
            }));
        } else {
            var $liste = $('<div>', {'class': 'local-kursassistent-av-liste mb-3'});

            response.templates.forEach(function(tpl) {
                var $row = $('<div>', {
                    'class': 'local-kursassistent-av-row local-kursassistent-sectpl-row',
                    'data-tplid': tpl.id
                });

                var typLabel = tpl.isown ? 'Persönlich' : 'Global';
                var $badge = $('<span>', {
                    'class': 'badge ' + (tpl.isown ? 'badge-info' : 'badge-secondary'),
                    text: typLabel
                });

                var $nameWrap = $('<div>', {'class': 'local-kursassistent-sectpl-name'});
                $nameWrap.append($('<span>', {'class': 'font-weight-bold', text: tpl.name}));
                $nameWrap.append(' ');
                $nameWrap.append($badge);
                if (tpl.description) {
                    $nameWrap.append($('<div>', {'class': 'small text-muted', text: tpl.description}));
                }

                var $actions = $('<div>', {'class': 'local-kursassistent-sectpl-actions'});

                // Anwenden-Button.
                var $applyBtn = $('<button>', {
                    type: 'button',
                    'class': 'btn btn-sm btn-primary',
                    text: 'Anwenden'
                });
                $applyBtn.on('click', function() {
                    wendeVorlageAn(tpl, $container);
                });
                $actions.append($applyBtn);

                // Löschen-Button (nur wenn candelete).
                if (tpl.candelete) {
                    var $delBtn = $('<button>', {
                        type: 'button',
                        'class': 'btn btn-sm btn-outline-danger ml-1',
                        title: 'Vorlage löschen'
                    }).html('&times;');
                    $delBtn.on('click', function() {
                        loescheVorlage(tpl, $container, response, $overlay);
                    });
                    $actions.append($delBtn);
                }

                $row.append($nameWrap, $actions);
                $liste.append($row);
            });

            $content.append($liste);
        }

        // ========== Abschnitt als Vorlage speichern ==========
        var $snapshotBereich = $('<div>', {'class': 'local-kursassistent-sectpl-neu mb-3'});
        $snapshotBereich.append($('<h6>', {text: 'Abschnitt als Vorlage speichern'}));
        $snapshotBereich.append($('<p>', {
            'class': 'small text-muted mb-2',
            text: 'Übernimmt alle Elemente (Bausteine und Aktivitäten) des gewählten Abschnitts als Vorlage.'
        }));

        var $snapSectionWrap = $('<div>', {'class': 'd-flex mb-2', style: 'gap:8px'});
        var $snapSectionSelect = $('<select>', {'class': 'form-control form-control-sm'});
        $overlay.find('.local-kursassistent-section option').each(function() {
            $snapSectionSelect.append($('<option>', {value: $(this).val(), text: $(this).text()}));
        });
        var aktuellSnap = $overlay.find('.local-kursassistent-section').val();
        if (aktuellSnap) {
            $snapSectionSelect.val(aktuellSnap);
        }
        var $snapLoadBtn = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-outline-primary',
            text: 'Laden'
        });
        $snapSectionWrap.append($snapSectionSelect, $snapLoadBtn);
        $snapshotBereich.append($snapSectionWrap);

        var $snapPreview = $('<div>', {'class': 'local-kursassistent-sectpl-baustein-liste mb-2 d-none'});
        $snapshotBereich.append($snapPreview);

        var $snapNameInput = $('<input>', {
            type: 'text',
            'class': 'form-control form-control-sm mb-2 d-none',
            placeholder: 'Name der Vorlage'
        });
        var $snapGlobalCheck = $('<label>', {'class': 'small d-flex align-items-center mb-2 d-none', style: 'gap:6px'});
        var $snapGlobalCb = $('<input>', {type: 'checkbox'});
        $snapGlobalCheck.append($snapGlobalCb, 'Globale Vorlage (für alle Lehrkräfte)');

        var $snapSaveBtn = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-primary d-none',
            text: 'Als Vorlage speichern'
        });

        $snapshotBereich.append($snapNameInput, $snapGlobalCheck, $snapSaveBtn);

        // Laden: Abschnittsinhalte anzeigen.
        $snapLoadBtn.on('click', function() {
            var secnum = parseInt($snapSectionSelect.val(), 10) || 0;
            $snapLoadBtn.prop('disabled', true).text('Lädt …');
            Ajax.call([{
                methodname: 'local_kursassistent_get_section_content',
                args: {courseid: courseid, sectionnum: secnum}
            }])[0].done(function(secRes) {
                $snapLoadBtn.prop('disabled', false).text('Laden');
                $snapPreview.empty().removeClass('d-none');
                $snapNameInput.removeClass('d-none');
                $snapGlobalCheck.removeClass('d-none');
                $snapSaveBtn.removeClass('d-none');

                if (secRes.items.length === 0) {
                    $snapPreview.append($('<p>', {
                        'class': 'small text-muted p-2',
                        text: 'Der Abschnitt enthält keine Elemente.'
                    }));
                    $snapSaveBtn.addClass('d-none');
                    return;
                }

                $snapNameInput.val(secRes.sectionname + ' (Vorlage)');

                secRes.items.forEach(function(item, idx) {
                    var $row = $('<label>', {'class': 'local-kursassistent-av-row local-kursassistent-lp-row'});
                    var $cb = $('<input>', {
                        type: 'checkbox',
                        'class': 'local-kursassistent-snap-check mr-2',
                        checked: true,
                        'data-idx': idx
                    });
                    var artLabel = item.art === 'aktivitaet' ? '⚙ ' + item.modname : '📝';
                    $row.append($cb, $('<span>', {text: artLabel + ' ' + item.name}));
                    $snapPreview.append($row);
                });

                // Speichern-Handler.
                $snapSaveBtn.off('click').on('click', function() {
                    var snapName = $snapNameInput.val().trim();
                    if (!snapName) {
                        Notification.alert('Hinweis', 'Bitte gib einen Namen für die Vorlage ein.', 'OK');
                        return;
                    }
                    var definition = [];
                    $snapPreview.find('.local-kursassistent-snap-check:checked').each(function() {
                        var idx = parseInt($(this).data('idx'), 10);
                        var item = secRes.items[idx];
                        if (item.art === 'aktivitaet') {
                            definition.push({
                                art: 'aktivitaet',
                                modname: item.modname,
                                name: item.name,
                                intro: item.intro || ''
                            });
                        } else {
                            definition.push({
                                art: 'baustein',
                                typeid: item.typeid,
                                inhalt: item.intro || ''
                            });
                        }
                    });
                    if (definition.length === 0) {
                        Notification.alert('Hinweis', 'Bitte wähle mindestens ein Element aus.', 'OK');
                        return;
                    }
                    $snapSaveBtn.prop('disabled', true).text('Speichert …');
                    Ajax.call([{
                        methodname: 'local_kursassistent_save_section_template',
                        args: {
                            courseid: courseid,
                            id: 0,
                            name: snapName,
                            description: 'Erstellt aus Abschnitt „' + secRes.sectionname + '"',
                            definition: JSON.stringify(definition),
                            isglobal: $snapGlobalCb.is(':checked')
                        }
                    }])[0].done(function() {
                        $snapSaveBtn.prop('disabled', false).text('Als Vorlage speichern');
                        ladeVorlagenListeNeu($container, $overlay);
                    }).fail(function() {
                        $snapSaveBtn.prop('disabled', false).text('Als Vorlage speichern');
                        Notification.alert('Fehler', 'Konnte nicht gespeichert werden.', 'OK');
                    });
                });
            }).fail(function() {
                $snapLoadBtn.prop('disabled', false).text('Laden');
                Notification.alert('Fehler', 'Abschnittsinhalt konnte nicht geladen werden.', 'OK');
            });
        });

        $content.append($snapshotBereich);

        // ========== Neue Vorlage manuell erstellen ==========
        var $neuerBereich = $('<div>', {'class': 'local-kursassistent-sectpl-neu'});
        $neuerBereich.append($('<h6>', {text: 'Neue Vorlage manuell erstellen'}));

        var $nameInput = $('<input>', {
            type: 'text',
            'class': 'form-control form-control-sm mb-2',
            placeholder: 'Name der Vorlage'
        });
        var $descInput = $('<input>', {
            type: 'text',
            'class': 'form-control form-control-sm mb-2',
            placeholder: 'Beschreibung (optional)'
        });

        // === Tab-Leiste: Bausteine | Aktivitäten ===
        var $tabBar = $('<div>', {'class': 'local-kursassistent-tabbar mb-2'});
        var $tabBausteine = $('<button>', {
            type: 'button',
            'class': 'local-kursassistent-tabbtn active',
            text: 'Bausteine'
        });
        var $tabAktivitaeten = $('<button>', {
            type: 'button',
            'class': 'local-kursassistent-tabbtn',
            text: 'Aktivitäten'
        });
        $tabBar.append($tabBausteine, $tabAktivitaeten);

        // === Baustein-Tab ===
        var $bausteinPane = $('<div>', {'class': 'local-kursassistent-sectpl-baustein-liste mb-2'});

        Ajax.call([{
            methodname: 'local_kursassistent_get_bausteine',
            args: {courseid: courseid}
        }])[0].done(function(bausteinResponse) {
            var textBausteine = bausteinResponse.bausteine.filter(function(b) {
                return b.typ === 'text';
            });
            textBausteine.forEach(function(b) {
                var $row = $('<label>', {'class': 'local-kursassistent-av-row local-kursassistent-lp-row'});
                var $cb = $('<input>', {
                    type: 'checkbox',
                    'class': 'local-kursassistent-sectpl-check mr-2',
                    value: b.id
                });
                $row.append($cb, $('<span>', {text: b.titel}));
                $bausteinPane.append($row);

                var $textWrap = $('<div>', {
                    'class': 'local-kursassistent-textinput d-none',
                    'data-for-baustein': b.id
                });
                var $textarea = $('<textarea>', {
                    'class': 'form-control form-control-sm local-kursassistent-sectpl-inhalt',
                    rows: 3,
                    placeholder: b.titel + ' — Inhalt eingeben …',
                    'data-typeid': b.id
                });
                $textWrap.append($textarea);
                $bausteinPane.append($textWrap);

                $cb.on('change', function() {
                    if ($(this).is(':checked')) {
                        $textWrap.removeClass('d-none');
                    } else {
                        $textWrap.addClass('d-none');
                        $textarea.val('');
                    }
                });
            });
            if (textBausteine.length === 0) {
                $bausteinPane.append($('<p>', {
                    'class': 'small text-muted p-2',
                    text: 'Keine Text-Bausteine verfügbar.'
                }));
            }
        });

        // === Aktivitäten-Tab ===
        var $aktivitaetenPane = $('<div>', {'class': 'local-kursassistent-sectpl-baustein-liste mb-2 d-none'});
        var $aktListe = $('<div>', {'class': 'local-kursassistent-sectpl-akt-eintraege'});
        $aktivitaetenPane.append($aktListe);

        // Aktivitätstyp-Auswahl + Hinzufügen-Button.
        var $aktAddRow = $('<div>', {'class': 'd-flex mt-2 p-2', style: 'gap:6px'});
        var $aktTypeSelect = $('<select>', {'class': 'form-control form-control-sm'});
        var $aktNameInput = $('<input>', {
            type: 'text',
            'class': 'form-control form-control-sm',
            placeholder: 'Name der Aktivität'
        });
        var $aktAddBtn = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-outline-primary',
            text: '+ Hinzufügen'
        });
        $aktAddRow.append($aktTypeSelect, $aktNameInput, $aktAddBtn);
        $aktivitaetenPane.append($aktAddRow);

        // Aktivitätstypen laden.
        Ajax.call([{
            methodname: 'local_kursassistent_get_activity_types',
            args: {courseid: courseid}
        }])[0].done(function(typesRes) {
            typesRes.types.forEach(function(t) {
                $aktTypeSelect.append($('<option>', {value: t.modname, text: t.displayname}));
            });
        });

        var aktCounter = 0;
        $aktAddBtn.on('click', function() {
            var modname = $aktTypeSelect.val();
            var aktName = $aktNameInput.val().trim();
            if (!aktName) {
                aktName = $aktTypeSelect.find('option:selected').text();
            }
            var id = 'akt_' + (++aktCounter);
            var $row = $('<div>', {
                'class': 'local-kursassistent-av-row',
                'data-aktid': id,
                'data-modname': modname,
                'data-aktname': aktName
            });
            $row.append($('<span>', {'class': 'badge badge-primary mr-2', text: modname}));
            $row.append($('<span>', {'class': 'flex-grow-1', text: aktName}));
            var $removeBtn = $('<button>', {
                type: 'button',
                'class': 'btn btn-sm btn-outline-danger',
                title: 'Entfernen'
            }).html('&times;');
            $removeBtn.on('click', function() {
                $row.remove();
            });
            $row.append($removeBtn);
            $aktListe.append($row);
            $aktNameInput.val('');
        });

        // Tab-Umschaltung.
        $tabBausteine.on('click', function() {
            $tabBausteine.addClass('active');
            $tabAktivitaeten.removeClass('active');
            $bausteinPane.removeClass('d-none');
            $aktivitaetenPane.addClass('d-none');
        });
        $tabAktivitaeten.on('click', function() {
            $tabAktivitaeten.addClass('active');
            $tabBausteine.removeClass('active');
            $aktivitaetenPane.removeClass('d-none');
            $bausteinPane.addClass('d-none');
        });

        // Global-Checkbox.
        var $globalCheck = $('<label>', {'class': 'small d-flex align-items-center mb-2', style: 'gap:6px'});
        var $globalCb = $('<input>', {type: 'checkbox', 'class': 'local-kursassistent-sectpl-global'});
        $globalCheck.append($globalCb, 'Globale Vorlage (für alle Lehrkräfte)');

        var $saveBtn = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-primary',
            text: 'Vorlage speichern'
        });
        $saveBtn.on('click', function() {
            speichereVorlage($nameInput, $descInput, $globalCb, $bausteinPane, $aktListe, $container, $overlay, $saveBtn);
        });

        $neuerBereich.append($nameInput, $descInput, $tabBar, $bausteinPane, $aktivitaetenPane, $globalCheck, $saveBtn);
        $content.append($neuerBereich);
        $container.append($content);
    }

    /**
     * Baut den Anzeigetext für die Abschlussverfolgung einer Aktivität.
     *
     * Ausgelagert, damit die Rendering-Schleife übersichtlich bleibt.
     *
     * @param {Object} act Aktivitätsdaten
     * @param {Object} labels Zuordnung Abschlussart zu Anzeigetext
     * @return {String}
     */
    function baueAbschlussText(act, labels) {
        var text = labels[act.completion] || 'Keine';
        if (act.completion !== 2) {
            return text;
        }

        var bedingungen = [
            {feld: act.completionview, name: 'Ansicht'},
            {feld: act.completionsubmit, name: 'Abgabe'},
            {feld: act.completiongrade, name: 'Bewertung'},
            {feld: act.completionpassgrade, name: 'Bestehen'}
        ];
        var details = bedingungen.filter(function(b) {
            return b.feld;
        }).map(function(b) {
            return b.name;
        });

        if (details.length > 0) {
            text += ' (' + details.join(', ') + ')';
        }
        return text;
    }

    /**
     * Baut die Anzeigetexte für die Voraussetzungen einer Aktivität.
     *
     * @param {Array} restrictions Liste der Bedingungen
     * @return {Array} Lesbare Texte
     */
    function baueBedingungsTexte(restrictions) {
        var praefixe = {
            completion: 'Abschluss von: ',
            date: 'Datum: ',
            grade: 'Bewertung: '
        };

        return restrictions.map(function(r) {
            if (r.type === 'completion') {
                return praefixe.completion + r.cmname;
            }
            if (praefixe[r.type]) {
                return praefixe[r.type] + r.description;
            }
            return r.type;
        });
    }

    /**
     * Lädt die Vorlagenliste neu und zeigt eine Erfolgsmeldung an.
     *
     * Bewusst als eigene Funktion, damit die Aufrufkette flach bleibt.
     *
     * @param {jQuery} $container
     * @param {jQuery} $overlay
     */
    function ladeVorlagenListeNeu($container, $overlay) {
        Ajax.call([{
            methodname: 'local_kursassistent_get_section_templates',
            args: {courseid: courseid}
        }])[0].done(function(newResponse) {
            rendereVorlagenListe($container, newResponse, $overlay);
            $container.find('.local-kursassistent-meldung').remove();
            $container.append($('<div>', {
                'class': 'local-kursassistent-meldung local-kursassistent-meldung-erfolg mt-2',
                text: 'Abschnittsvorlage wurde gespeichert.'
            }));
        });
    }

    /**
     * Wendet eine Vorlage auf den aktuell gewählten Abschnitt an.
     *
     * @param {Object} tpl Vorlagen-Daten
     * @param {jQuery} $container
     */
    function wendeVorlageAn(tpl, $container) {
        // Abschnitts-Auswahl aus dem Subpanel verwenden (nicht aus dem versteckten Hauptdropdown).
        var $sectSelect = $container.find('.local-kursassistent-sectpl-section');
        var sectionnum = parseInt($sectSelect.val(), 10) || 0;
        var sectionName = $sectSelect.find('option:selected').text();

        // Bestätigungsdialog inline.
        var bestehend = $container.find('.local-kursassistent-sectpl-confirm');
        if (bestehend.length) {
            bestehend.remove();
        }

        var $confirm = $('<div>', {'class': 'local-kursassistent-sectpl-confirm local-kursassistent-renamepanel mt-2'});
        $confirm.append($('<p>', {
            'class': 'small mb-2',
            text: 'Die Vorlage „' + tpl.name + '" wird auf den Abschnitt „' + sectionName +
                '" angewendet. Vorhandene Inhalte bleiben erhalten.'
        }));
        var $confirmFooter = $('<div>', {'class': 'd-flex justify-content-end', style: 'gap:8px'});
        var $cancelBtn = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-secondary',
            text: 'Abbrechen'
        });
        $cancelBtn.on('click', function() {
            $confirm.remove();
        });
        var $okBtn = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-primary',
            text: 'Anwenden'
        });
        $okBtn.on('click', function() {
            $okBtn.prop('disabled', true).text('Wird angewendet …');

            Ajax.call([{
                methodname: 'local_kursassistent_apply_section_template',
                args: {courseid: courseid, sectionnum: sectionnum, templateid: tpl.id}
            }])[0].done(function(res) {
                $confirm.remove();
                etwasEingefuegt = true;
                $container.find('.local-kursassistent-meldung').remove();
                $container.append($('<div>', {
                    'class': 'local-kursassistent-meldung local-kursassistent-meldung-erfolg mt-2',
                    text: 'Vorlage angewendet: ' + res.erzeugt + ' Baustein(e) eingefügt.'
                }));
            }).fail(function() {
                $okBtn.prop('disabled', false).text('Anwenden');
                Notification.alert('Fehler', 'Die Vorlage konnte nicht angewendet werden.', 'OK');
            });
        });
        $confirmFooter.append($cancelBtn, $okBtn);
        $confirm.append($confirmFooter);
        $container.append($confirm);
        $confirm[0].scrollIntoView({block: 'nearest'});
    }

    /**
     * Löscht eine Vorlage nach Bestätigung.
     *
     * @param {Object} tpl
     * @param {jQuery} $container
     * @param {Object} response
     * @param {jQuery} $overlay
     */
    function loescheVorlage(tpl, $container, response, $overlay) {
        // Inline-Bestätigung.
        var $row = $container.find('[data-tplid="' + tpl.id + '"]');
        if ($row.find('.local-kursassistent-sectpl-delconfirm').length) {
            return;
        }
        var $delConfirm = $('<div>', {'class': 'local-kursassistent-sectpl-delconfirm small mt-1'});
        $delConfirm.append($('<span>', {text: 'Wirklich löschen? '}));
        var $jaBtn = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-danger mr-1',
            text: 'Ja'
        });
        var $neinBtn = $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-secondary',
            text: 'Nein'
        });
        $neinBtn.on('click', function() {
            $delConfirm.remove();
        });
        $jaBtn.on('click', function() {
            $jaBtn.prop('disabled', true).text('Löscht …');
            Ajax.call([{
                methodname: 'local_kursassistent_delete_section_template',
                args: {courseid: courseid, id: tpl.id}
            }])[0].done(function() {
                // Vorlagenliste neu laden.
                Ajax.call([{
                    methodname: 'local_kursassistent_get_section_templates',
                    args: {courseid: courseid}
                }])[0].done(function(newResponse) {
                    rendereVorlagenListe($container, newResponse, $overlay);
                    $container.find('.local-kursassistent-meldung').remove();
                    $container.append($('<div>', {
                        'class': 'local-kursassistent-meldung local-kursassistent-meldung-erfolg mt-2',
                        text: 'Vorlage wurde gelöscht.'
                    }));
                });
            }).fail(function() {
                $jaBtn.prop('disabled', false).text('Ja');
                Notification.alert('Fehler', 'Die Vorlage konnte nicht gelöscht werden.', 'OK');
            });
        });
        $delConfirm.append($jaBtn, $neinBtn);
        $row.append($delConfirm);
    }

    /**
     * Speichert eine neue Vorlage.
     *
     * @param {jQuery} $nameInput
     * @param {jQuery} $descInput
     * @param {jQuery} $globalCb
     * @param {jQuery} $bausteinListe
     * @param {jQuery} $aktListe
     * @param {jQuery} $container
     * @param {jQuery} $overlay
     * @param {jQuery} $saveBtn
     */
    function speichereVorlage($nameInput, $descInput, $globalCb, $bausteinListe, $aktListe,
            $container, $overlay, $saveBtn) {
        var name = $nameInput.val().trim();
        if (!name) {
            Notification.alert('Hinweis', 'Bitte gib einen Namen für die Vorlage ein.', 'OK');
            return;
        }

        var definition = [];

        // Baustein-Einträge sammeln.
        $bausteinListe.find('.local-kursassistent-sectpl-check:checked').each(function() {
            var typeid = parseInt($(this).val(), 10);
            var $textarea = $bausteinListe.find('.local-kursassistent-sectpl-inhalt[data-typeid="' + typeid + '"]');
            var inhalt = $textarea.length ? $textarea.val().trim() : '';
            definition.push({art: 'baustein', typeid: typeid, inhalt: inhalt});
        });

        // Aktivitäten-Einträge sammeln.
        $aktListe.find('[data-aktid]').each(function() {
            var modname = $(this).data('modname');
            var aktName = $(this).data('aktname');
            definition.push({art: 'aktivitaet', modname: modname, name: aktName});
        });

        if (definition.length === 0) {
            Notification.alert('Hinweis', 'Bitte wähle mindestens einen Baustein oder eine Aktivität aus.', 'OK');
            return;
        }

        var isglobal = $globalCb.is(':checked');

        $saveBtn.prop('disabled', true).text('Speichert …');

        Ajax.call([{
            methodname: 'local_kursassistent_save_section_template',
            args: {
                courseid: courseid,
                id: 0,
                name: name,
                description: $descInput.val().trim(),
                definition: JSON.stringify(definition),
                isglobal: isglobal
            }
        }])[0].done(function() {
            $saveBtn.prop('disabled', false).text('Vorlage speichern');
            // Vorlagenliste neu laden.
            Ajax.call([{
                methodname: 'local_kursassistent_get_section_templates',
                args: {courseid: courseid}
            }])[0].done(function(newResponse) {
                rendereVorlagenListe($container, newResponse, $overlay);
                $container.find('.local-kursassistent-meldung').remove();
                $container.append($('<div>', {
                    'class': 'local-kursassistent-meldung local-kursassistent-meldung-erfolg mt-2',
                    text: 'Vorlage wurde gespeichert.'
                }));
            });
        }).fail(function() {
            $saveBtn.prop('disabled', false).text('Vorlage speichern');
            Notification.alert('Fehler', 'Die Vorlage konnte nicht gespeichert werden.', 'OK');
        });
    }

    //  Statistik-Panel

    /**
     * Rendert das Statistik-Panel mit Fortschrittsbalken und Detail-Tabelle.
     *
     * @param {jQuery} $subpanel
     */
    function rendereStatistikPanel($subpanel) {
        $subpanel.append($('<h5>', {text: 'Statistik – Teilnehmer-Abschluss'}));
        $subpanel.append($('<p>', {
            'class': 'small text-muted mb-3',
            text: 'Zeigt den Abschlussfortschritt der Teilnehmer für alle Aktivitäten mit Abschlussverfolgung.'
        }));

        var $loader = $('<div>', {'class': 'text-center py-3'});
        $loader.append($('<span>', {'class': 'text-muted', text: 'Lade Statistik …'}));
        $subpanel.append($loader);

        Ajax.call([{
            methodname: 'local_kursassistent_get_course_statistics',
            args: {courseid: courseid}
        }])[0].done(function(response) {
            $loader.remove();

            if (!response.completionenabled) {
                $subpanel.append($('<div>', {
                    'class': 'alert alert-warning',
                    text: 'Die Abschlussverfolgung ist in diesem Kurs nicht aktiviert. ' +
                        'Bitte aktiviere sie zuerst in den Kurseinstellungen.'
                }));
                return;
            }

            if (response.activities.length === 0) {
                $subpanel.append($('<div>', {
                    'class': 'alert alert-info',
                    text: 'In diesem Kurs gibt es keine Aktivitäten mit Abschlussverfolgung.'
                }));
                return;
            }

            if (response.users.length === 0) {
                $subpanel.append($('<div>', {
                    'class': 'alert alert-info',
                    text: 'Es sind keine Teilnehmer in diesem Kurs eingeschrieben.'
                }));
                return;
            }

            // Kursweite Zusammenfassung.
            var totalPossible = response.activities.length * response.users.length;
            var totalCompleted = 0;
            response.activities.forEach(function(a) {
                totalCompleted += a.completedusers;
            });
            var overallPercent = totalPossible > 0 ? Math.round((totalCompleted / totalPossible) * 100) : 0;

            var $summary = $('<div>', {'class': 'local-kursassistent-stat-summary mb-3 p-3'});
            $summary.append($('<div>', {'class': 'local-kursassistent-stat-headline'})
                .append($('<span>', {'class': 'local-kursassistent-stat-percent', text: overallPercent + '%'}))
                .append($('<span>', {text: ' Gesamtfortschritt'}))
            );
            $summary.append($('<div>', {'class': 'small text-muted mt-1',
                text: response.users.length + ' Teilnehmer · ' + response.activities.length + ' Aktivitäten'
            }));
            var $overallBar = erzeugeBalken(overallPercent);
            $summary.append($overallBar);
            $subpanel.append($summary);

            // Fortschrittsbalken pro Aktivität, gruppiert nach Abschnitt.
            var $balkenBereich = $('<div>', {'class': 'local-kursassistent-stat-balken mb-3'});
            var currentSection = -1;

            response.activities.forEach(function(activity) {
                // Abschnitts-Überschrift.
                if (activity.sectionnum !== currentSection) {
                    currentSection = activity.sectionnum;
                    $balkenBereich.append($('<div>', {
                        'class': 'local-kursassistent-stat-section-header mt-3 mb-1',
                        text: activity.sectionname
                    }));
                }

                var percent = activity.totalusers > 0
                    ? Math.round((activity.completedusers / activity.totalusers) * 100)
                    : 0;
                var $row = $('<div>', {'class': 'local-kursassistent-stat-row mb-2'});
                var $label = $('<div>', {'class': 'd-flex justify-content-between align-items-center mb-1'});
                $label.append($('<span>', {'class': 'small'})
                    .append($('<span>', {'class': 'badge badge-secondary mr-1', text: activity.modname}))
                    .append($('<span>', {text: activity.name}))
                );
                $label.append($('<span>', {
                    'class': 'small text-muted',
                    text: activity.completedusers + ' / ' + activity.totalusers
                }));
                $row.append($label);
                $row.append(erzeugeBalken(percent));
                $balkenBereich.append($row);
            });

            $subpanel.append($balkenBereich);

            // Aufklappbare Detail-Tabelle – Kommentare parallel laden.
            var $detailToggle = $('<button>', {
                type: 'button',
                'class': 'btn btn-sm btn-outline-secondary mb-2',
                text: '▸ Detail-Tabelle einblenden'
            });
            var $detailBereich = $('<div>', {'class': 'd-none'});

            // Kommentare beim ersten Öffnen der Tabelle laden.
            var commentsLoaded = false;
            var commentsMap = {};

            $detailToggle.on('click', function() {
                if ($detailBereich.hasClass('d-none')) {
                    $detailBereich.removeClass('d-none');
                    $detailToggle.text('▾ Detail-Tabelle ausblenden');
                    // Tabelle nur beim ersten Öffnen aufbauen.
                    if (!commentsLoaded) {
                        commentsLoaded = true;
                        Ajax.call([{
                            methodname: 'local_kursassistent_get_student_comments',
                            args: {courseid: courseid}
                        }])[0].done(function(cResponse) {
                            cResponse.comments.forEach(function(c) {
                                commentsMap[c.studentid] = c.comment;
                            });
                            $detailBereich.empty().append(erzeugeDetailTabelle(response, commentsMap));
                        }).fail(function() {
                            // Auch ohne Kommentare die Tabelle anzeigen.
                            $detailBereich.empty().append(erzeugeDetailTabelle(response, commentsMap));
                        });
                    }
                } else {
                    $detailBereich.addClass('d-none');
                    $detailToggle.text('▸ Detail-Tabelle einblenden');
                }
            });

            $subpanel.append($detailToggle, $detailBereich);

        }).fail(function() {
            $loader.remove();
            $subpanel.append($('<div>', {
                'class': 'alert alert-danger',
                text: 'Statistik konnte nicht geladen werden.'
            }));
        });
    }

    /**
     * Erzeugt einen CSS-Fortschrittsbalken.
     *
     * @param {Number} percent 0–100
     * @return {jQuery}
     */
    function erzeugeBalken(percent) {
        var colorClass = 'local-kursassistent-bar-low';
        if (percent >= 75) {
            colorClass = 'local-kursassistent-bar-high';
        } else if (percent >= 40) {
            colorClass = 'local-kursassistent-bar-mid';
        }
        var $wrapper = $('<div>', {'class': 'local-kursassistent-bar-track'});
        var $fill = $('<div>', {
            'class': 'local-kursassistent-bar-fill ' + colorClass,
            style: 'width:' + percent + '%'
        });
        $wrapper.append($fill);
        return $wrapper;
    }

    /**
     * Erzeugt die Teilnehmer × Aktivitäten Detail-Tabelle mit Kommentarspalte.
     *
     * @param {Object} response Antwort von get_course_statistics
     * @param {Object} commentsMap studentid → Kommentartext
     * @return {jQuery}
     */
    function erzeugeDetailTabelle(response, commentsMap) {
        var $wrapper = $('<div>', {'class': 'local-kursassistent-stat-table-wrap'});
        var $table = $('<table>', {'class': 'table table-sm table-bordered local-kursassistent-stat-table'});

        // Kopfzeile.
        var $thead = $('<thead>');
        var $headRow = $('<tr>');
        $headRow.append($('<th>', {text: 'Teilnehmer', 'class': 'local-kursassistent-stat-sticky'}));
        response.activities.forEach(function(a) {
            var $th = $('<th>', {
                'class': 'local-kursassistent-stat-th-activity',
                title: a.name + ' (' + a.modname + ')'
            });
            $th.append($('<span>', {text: a.name}));
            $headRow.append($th);
        });
        // Zusammenfassungsspalte.
        $headRow.append($('<th>', {text: 'Gesamt', 'class': 'text-center'}));
        // Kommentarspalte.
        $headRow.append($('<th>', {text: 'Kommentar', 'class': 'text-center local-kursassistent-stat-comment-th'}));
        $thead.append($headRow);
        $table.append($thead);

        // Lookup: userid → activity cmid → completed.
        var completionMap = {};
        response.activities.forEach(function(a) {
            a.usercompletions.forEach(function(uc) {
                if (!completionMap[uc.userid]) {
                    completionMap[uc.userid] = {};
                }
                completionMap[uc.userid][a.cmid] = uc.completed;
            });
        });

        // Körper.
        var $tbody = $('<tbody>');
        response.users.forEach(function(user) {
            var $row = $('<tr>');
            $row.append($('<td>', {text: user.fullname, 'class': 'local-kursassistent-stat-sticky small'}));
            var userCompleted = 0;
            response.activities.forEach(function(a) {
                var done = completionMap[user.userid] && completionMap[user.userid][a.cmid];
                if (done) {
                    userCompleted++;
                }
                $row.append($('<td>', {
                    'class': 'text-center',
                    html: done ? '<span class="local-kursassistent-stat-done">✓</span>'
                               : '<span class="local-kursassistent-stat-open">–</span>'
                }));
            });
            var userPercent = response.activities.length > 0
                ? Math.round((userCompleted / response.activities.length) * 100)
                : 0;
            $row.append($('<td>', {
                'class': 'text-center small',
                text: userCompleted + '/' + response.activities.length + ' (' + userPercent + '%)'
            }));

            // Kommentar-Zelle.
            var $commentCell = $('<td>', {'class': 'local-kursassistent-stat-comment-cell'});
            var existingComment = commentsMap[user.userid] || '';
            var $commentDisplay = $('<div>', {'class': 'local-kursassistent-comment-display d-flex align-items-center'});
            var $commentText = $('<span>', {
                'class': 'small flex-grow-1' + (existingComment ? '' : ' text-muted'),
                text: existingComment || 'Kommentar …'
            });
            var $editBtn = $('<button>', {
                type: 'button',
                'class': 'btn btn-sm btn-link p-0 ml-1 local-kursassistent-comment-edit',
                title: 'Kommentar bearbeiten',
                html: '✎'
            });
            $commentDisplay.append($commentText, $editBtn);

            var $commentEdit = $('<div>', {'class': 'local-kursassistent-comment-editarea d-none'});
            var $textarea = $('<textarea>', {
                'class': 'form-control form-control-sm',
                rows: 2,
                text: existingComment
            });
            var $btnRow = $('<div>', {'class': 'd-flex justify-content-end mt-1'});
            var $cancelBtn = $('<button>', {
                type: 'button',
                'class': 'btn btn-sm btn-outline-secondary mr-1',
                text: 'Abbrechen'
            });
            var $saveBtn = $('<button>', {
                type: 'button',
                'class': 'btn btn-sm btn-primary',
                text: 'Speichern'
            });
            $btnRow.append($cancelBtn, $saveBtn);
            $commentEdit.append($textarea, $btnRow);

            $commentCell.append($commentDisplay, $commentEdit);

            // Bearbeiten öffnen.
            $editBtn.on('click', function() {
                $commentDisplay.addClass('d-none');
                $commentEdit.removeClass('d-none');
                $textarea.focus();
            });

            // Abbrechen.
            $cancelBtn.on('click', function() {
                $textarea.val(commentsMap[user.userid] || '');
                $commentEdit.addClass('d-none');
                $commentDisplay.removeClass('d-none');
            });

            // Speichern.
            $saveBtn.on('click', function() {
                var newComment = $textarea.val().trim();
                $saveBtn.prop('disabled', true).text('…');
                Ajax.call([{
                    methodname: 'local_kursassistent_save_student_comment',
                    args: {courseid: courseid, studentid: user.userid, comment: newComment}
                }])[0].done(function() {
                    commentsMap[user.userid] = newComment;
                    $commentText.text(newComment || 'Kommentar …')
                        .toggleClass('text-muted', !newComment);
                    $commentEdit.addClass('d-none');
                    $commentDisplay.removeClass('d-none');
                    $saveBtn.prop('disabled', false).text('Speichern');
                }).fail(function() {
                    $saveBtn.prop('disabled', false).text('Speichern');
                    Notification.addNotification({
                        message: 'Kommentar konnte nicht gespeichert werden.',
                        type: 'error'
                    });
                });
            });

            $row.append($commentCell);
            $tbody.append($row);
        });
        $table.append($tbody);

        // Fußzeile: Summe pro Aktivität.
        var $tfoot = $('<tfoot>');
        var $footRow = $('<tr>');
        $footRow.append($('<td>', {text: 'Abgeschlossen', 'class': 'local-kursassistent-stat-sticky small font-weight-bold'}));
        response.activities.forEach(function(a) {
            var pct = a.totalusers > 0 ? Math.round((a.completedusers / a.totalusers) * 100) : 0;
            $footRow.append($('<td>', {
                'class': 'text-center small',
                text: a.completedusers + '/' + a.totalusers + ' (' + pct + '%)'
            }));
        });
        var overallDone = 0;
        var overallTotal = response.activities.length * response.users.length;
        response.activities.forEach(function(a) {
            overallDone += a.completedusers;
        });
        var overallPct = overallTotal > 0 ? Math.round((overallDone / overallTotal) * 100) : 0;
        $footRow.append($('<td>', {
            'class': 'text-center small font-weight-bold',
            text: overallDone + '/' + overallTotal + ' (' + overallPct + '%)'
        }));
        $footRow.append($('<td>')); // Leere Zelle unter Kommentarspalte.
        $tfoot.append($footRow);
        $table.append($tfoot);

        $wrapper.append($table);
        return $wrapper;
    }

    return {
        /**
         * Initialisiert den Kursassistenten für den aktuellen Kurs.
         *
         * @param {Number} cid Kurs-ID
         */
        init: function(cid) {
            courseid = cid;

            $(document).on('click', '.local-kursassistent-navlink', function(e) {
                e.preventDefault();
                oeffneAssistent();
            });

            $(document).on('click', '.local-kursassistent-close', function() {
                $(this).closest('.local-kursassistent-overlay').remove();
                if (etwasEingefuegt) {
                    window.location.reload();
                }
            });

            $(document).on('click', '.local-kursassistent-fertig', function() {
                var $overlay = $(this).closest('.local-kursassistent-overlay');
                if (hatOffeneEingaben($overlay)) {
                    navigiereMitWarnung($overlay, window.location.href, 'Fertig');
                    return;
                }
                $overlay.remove();
                if (etwasEingefuegt) {
                    window.location.reload();
                }
            });

            $(document).on('click', '.local-kursassistent-submit', function() {
                sendeAuswahl($(this).closest('.local-kursassistent-overlay'));
            });

            // Rückkehr vom Moodle-Formular: den Assistenten selbst wieder öffnen. Die Merkung
            // wird dabei verbraucht, ein erneutes Laden der Seite öffnet ihn nicht noch einmal.
            var rueckkehr = Rueckkehr.lese(courseid);
            if (rueckkehr) {
                Rueckkehr.loesche();
                oeffneAssistent(function($overlay) {
                    zeigeRueckkehrErgebnis($overlay, rueckkehr);
                });
            }
        }
    };
});
