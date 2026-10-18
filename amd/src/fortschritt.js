/**
 * Mein Fortschritt – Schüler-Ansicht des eigenen Abschlussfortschritts.
 *
 * @module     local_kursassistent/fortschritt
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/templates'], function($, Ajax, Notification, Templates) {

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
     * @return {Array} Abschnitte mit Name und Aktivitäten
     */
    function groupBySection(activities) {
        var sections = [];
        activities.forEach(function(a) {
            var last = sections[sections.length - 1];
            if (!last || last.sectionnum !== a.sectionnum) {
                last = {sectionnum: a.sectionnum, sectionname: a.sectionname, activities: []};
                sections.push(last);
            }
            last.activities.push({
                modname: a.modname,
                name: a.name,
                completed: a.completed,
                grade: a.grade || '',
                teachernote: a.teachernote || ''
            });
        });
        return sections;
    }

    /**
     * Bereitet die Antwort des Servers für das Template auf.
     *
     * @param {Object} response Antwort von get_own_progress
     * @return {Object} Kontext für local_kursassistent/progress_modal
     */
    function buildContext(response) {
        var total = response.totalcount;
        var percent = total > 0 ? Math.round((response.completedcount / total) * 100) : 0;
        var enabled = !!response.completionenabled;

        return {
            notenabled: !enabled,
            empty: enabled && total === 0,
            showcontent: enabled && total > 0,
            percent: percent,
            barclass: getBarClass(percent),
            completedcount: response.completedcount,
            totalcount: total,
            sections: groupBySection(response.activities || []),
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
        return Templates.renderForPromise('local_kursassistent/progress_modal', buildContext(response))
            .then(function(result) {
                Templates.appendNodeContents('body', result.html, result.js);
                return null;
            })
            .catch(Notification.exception);
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

            $(document).on('click', '.local-kursassistent-progress-close', function() {
                $(this).closest('.local-kursassistent-overlay').remove();
            });
        }
    };
});
