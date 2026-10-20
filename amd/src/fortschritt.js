/**
 * Mein Fortschritt – Schüler-Ansicht des eigenen Abschlussfortschritts.
 *
 * @module     local_kursassistent/fortschritt
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/templates', 'core/str'], function($, Ajax, Notification, Templates, Str) {

    var courseid;

    /**
     * Liefert die CSS-Klasse für die Farbe des Fortschrittsbalkens.
     *
     * @param {Number} percent 0–100
     * @return {String}
     */
    function getBarClass(percent) {
        if (percent >= 75) {
            return 'local-kursassistent-bar-high';
        }
        return percent >= 40 ? 'local-kursassistent-bar-mid' : 'local-kursassistent-bar-low';
    }

    /**
     * Gruppiert die Aktivitäten nach Abschnitt in der Reihenfolge der Antwort.
     *
     * @param {Array} activities Aktivitäten aus get_own_progress
     * @param {Object} statusLabels Anzeigetexte je Status
     * @return {Array} Abschnitte mit Name, Zählung und Aktivitäten
     */
    function groupBySection(activities, statusLabels) {
        var sections = [];
        activities.forEach(function(a) {
            var last = sections[sections.length - 1];
            if (!last || last.sectionnum !== a.sectionnum) {
                last = {
                    sectionnum: a.sectionnum,
                    sectionname: a.sectionname,
                    sectionkey: 'sec' + a.sectionnum,
                    donecount: 0,
                    totalcount: 0,
                    expanded: false,
                    activities: []
                };
                sections.push(last);
            }
            var status = a.status || (a.completed ? 'done' : 'open');
            last.totalcount++;
            if (status === 'done') {
                last.donecount++;
            } else {
                last.expanded = true;
            }
            last.activities.push({
                modname: a.modname,
                name: a.name,
                completed: status === 'done',
                status: status,
                statuslabel: statusLabels[status],
                showprogress: status === 'progress',
                grade: a.grade || '',
                teachernote: a.teachernote || ''
            });
        });
        return sections;
    }

    /**
     * Sucht den nächsten Schritt: zuerst Überfälliges mit dem frühesten Termin, sonst die erste unerledigte Aktivität.
     *
     * @param {Array} activities Aktivitäten aus get_own_progress
     * @param {Object} statusLabels Anzeigetexte je Status
     * @return {Object|null}
     */
    function findNextStep(activities, statusLabels) {
        var candidates = activities.filter(function(a) {
            return (a.status || (a.completed ? 'done' : 'open')) !== 'done';
        });
        if (candidates.length === 0) {
            return null;
        }
        var overdue = candidates.filter(function(a) {
            return a.status === 'overdue';
        }).sort(function(x, y) {
            return x.due - y.due;
        });
        var pick = overdue.length > 0 ? overdue[0] : candidates[0];
        return {
            name: pick.name,
            url: pick.url || '',
            hasurl: !!pick.url,
            duetext: pick.duetext || '',
            status: pick.status || 'open',
            statuslabel: statusLabels[pick.status || 'open']
        };
    }

    /**
     * Bereitet die Antwort des Servers für das Template auf.
     *
     * @param {Object} response Antwort von get_own_progress
     * @param {Object} statusLabels Anzeigetexte je Status
     * @return {Object} Kontext für local_kursassistent/progress_modal
     */
    function buildContext(response, statusLabels) {
        var total = response.totalcount;
        var percent = total > 0 ? Math.round((response.completedcount / total) * 100) : 0;
        var enabled = !!response.completionenabled;
        var activities = response.activities || [];
        var overdueList = activities.filter(function(a) {
            return a.status === 'overdue';
        }).map(function(a) {
            return {name: a.name, url: a.url || '', hasurl: !!a.url, duetext: a.duetext || ''};
        });
        var nextstep = findNextStep(activities, statusLabels);

        return {
            notenabled: !enabled,
            empty: enabled && total === 0,
            showcontent: enabled && total > 0,
            percent: percent,
            barclass: getBarClass(percent),
            completedcount: response.completedcount,
            totalcount: total,
            hasnextstep: nextstep !== null,
            nextstep: nextstep,
            hasoverdue: overdueList.length > 0,
            overdue: overdueList,
            sections: groupBySection(activities, statusLabels),
            teachercomment: response.teachercomment || ''
        };
    }

    /**
     * Zeigt das Fortschritts-Fenster.
     *
     * @param {Object} response Antwort von get_own_progress
     * @return {Promise}
     */
    function showModal(response) {
        return Str.get_strings([
            {key: 'status_done', component: 'local_kursassistent'},
            {key: 'status_progress', component: 'local_kursassistent'},
            {key: 'status_open', component: 'local_kursassistent'},
            {key: 'status_overdue', component: 'local_kursassistent'}
        ]).then(function(strings) {
            var statusLabels = {done: strings[0], progress: strings[1], open: strings[2], overdue: strings[3]};
            return Templates.renderForPromise('local_kursassistent/progress_modal', buildContext(response, statusLabels));
        }).then(function(result) {
            Templates.appendNodeContents('body', result.html, result.js);
            return null;
        }).catch(Notification.exception);
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
                }])[0].then(showModal).catch(Notification.exception);
            });

            $(document).on('click', '.local-kursassistent-section-toggle', function() {
                var $btn = $(this);
                var open = $btn.attr('aria-expanded') === 'true';
                $btn.attr('aria-expanded', open ? 'false' : 'true');
                $btn.find('.local-kursassistent-section-icon')
                    .toggleClass('fa-chevron-down', !open)
                    .toggleClass('fa-chevron-right', open);
                $('#' + $btn.attr('aria-controls')).toggleClass('d-none', open);
            });

            $(document).on('click', '.local-kursassistent-progress-close', function() {
                $(this).closest('.local-kursassistent-overlay').remove();
            });
        }
    };
});
