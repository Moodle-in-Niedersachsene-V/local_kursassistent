/**
 * Mein Fortschritt – Schüler-Ansicht des eigenen Abschlussfortschritts.
 *
 * @module     local_kursassistent/fortschritt
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    var courseid;

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
     * Baut das Fortschritts-Modal auf.
     *
     * @param {Object} response Antwort von get_own_progress
     * @return {jQuery}
     */
    function baueModal(response) {
        var $overlay = $('<div>', {'class': 'local-kursassistent-overlay'});
        var $modal = $('<div>', {'class': 'local-kursassistent-modal'});

        // Header.
        var $header = $('<div>', {'class': 'd-flex align-items-center justify-content-between mb-3'});
        $header.append($('<p>', {'class': 'font-weight-bold mb-0', text: 'Mein Fortschritt'}));
        var $closeBtn = $('<button>', {
            type: 'button',
            'class': 'close local-kursassistent-progress-close',
            'aria-label': 'Schließen'
        }).html('&times;');
        $header.append($closeBtn);
        $modal.append($header);

        if (!response.completionenabled) {
            $modal.append($('<div>', {
                'class': 'alert alert-info',
                text: 'Die Abschlussverfolgung ist in diesem Kurs nicht aktiviert.'
            }));
            $overlay.append($modal);
            return $overlay;
        }

        if (response.totalcount === 0) {
            $modal.append($('<div>', {
                'class': 'alert alert-info',
                text: 'In diesem Kurs gibt es keine Aktivitäten mit Abschlussverfolgung.'
            }));
            $overlay.append($modal);
            return $overlay;
        }

        // Zusammenfassung.
        var percent = response.totalcount > 0
            ? Math.round((response.completedcount / response.totalcount) * 100)
            : 0;

        var $summary = $('<div>', {'class': 'local-kursassistent-stat-summary mb-3 p-3'});
        $summary.append($('<div>', {'class': 'local-kursassistent-stat-headline'})
            .append($('<span>', {'class': 'local-kursassistent-stat-percent', text: percent + '%'}))
            .append($('<span>', {text: ' abgeschlossen'}))
        );
        $summary.append($('<div>', {
            'class': 'small text-muted mt-1',
            text: response.completedcount + ' von ' + response.totalcount + ' Aktivitäten'
        }));
        $summary.append(erzeugeBalken(percent));
        $modal.append($summary);

        // Aktivitäten-Liste, gruppiert nach Abschnitt.
        var currentSection = -1;
        response.activities.forEach(function(a) {
            if (a.sectionnum !== currentSection) {
                currentSection = a.sectionnum;
                $modal.append($('<div>', {
                    'class': 'local-kursassistent-stat-section-header mt-3 mb-1',
                    text: a.sectionname
                }));
            }

            var $row = $('<div>', {'class': 'local-kursassistent-fortschritt-row d-flex align-items-center mb-1'});
            var $status = $('<span>', {
                'class': a.completed ? 'local-kursassistent-stat-done mr-2' : 'local-kursassistent-stat-open mr-2',
                text: a.completed ? '✓' : '○'
            });
            var $name = $('<span>', {'class': 'small flex-grow-1'})
                .append($('<span>', {'class': 'badge badge-secondary mr-1', text: a.modname}))
                .append($('<span>', {text: a.name, 'class': a.completed ? '' : 'text-muted'}));
            $row.append($status, $name);
            $modal.append($row);
        });

        // Kommentar der Lehrkraft anzeigen, falls vorhanden.
        if (response.teachercomment) {
            var $commentBox = $('<div>', {'class': 'local-kursassistent-teacher-comment mt-3 p-3'});
            $commentBox.append($('<div>', {
                'class': 'small font-weight-bold mb-1',
                text: 'Hinweis deiner Lehrkraft:'
            }));
            $commentBox.append($('<div>', {
                'class': 'small',
                text: response.teachercomment
            }));
            $modal.append($commentBox);
        }

        // Fertig-Button.
        var $footer = $('<div>', {'class': 'd-flex justify-content-end mt-3'});
        $footer.append($('<button>', {
            type: 'button',
            'class': 'btn btn-secondary local-kursassistent-progress-close',
            text: 'Schließen'
        }));
        $modal.append($footer);

        $overlay.append($modal);
        return $overlay;
    }

    return {
        /**
         * Initialisiert die Fortschritts-Ansicht für Schüler.
         *
         * @param {Number} cid Kurs-ID
         */
        init: function(cid) {
            courseid = cid;

            $(document).on('click', '.local-kursassistent-progresslink', function(e) {
                e.preventDefault();

                Ajax.call([{
                    methodname: 'local_kursassistent_get_own_progress',
                    args: {courseid: courseid}
                }])[0].done(function(response) {
                    var $overlay = baueModal(response);
                    $('body').append($overlay);
                }).fail(Notification.exception);
            });

            $(document).on('click', '.local-kursassistent-progress-close', function() {
                $(this).closest('.local-kursassistent-overlay').remove();
            });
        }
    };
});
