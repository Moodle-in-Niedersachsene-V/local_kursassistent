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

namespace local_kursassistent\output;

use local_kursassistent\statistics;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * Dashboard of the course assistant: course list, key figures and the areas of the assistant.
 *
 * @package    local_kursassistent
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard_page implements renderable, templatable {
    /** @var string[] Valid areas (tabs). */
    const TABS = ['stand', 'bausteine', 'einrichtung', 'uebersicht'];

    /** @var array Course names indexed by course id, only courses the person may use the assistant in. */
    protected $courses;

    /** @var int Selected course, 0 if none. */
    protected $courseid;

    /** @var string Selected area. */
    protected $tab;

    /**
     * Constructor.
     *
     * @param array $courses Course names indexed by course id
     * @param int $courseid Selected course, 0 if none
     * @param string $tab Selected area
     */
    public function __construct(array $courses, int $courseid, string $tab) {
        $this->courses = $courses;
        $this->courseid = $courseid;
        $this->tab = in_array($tab, self::TABS) ? $tab : 'stand';
    }

    /**
     * Collects the course list, the key figures and the areas.
     *
     * @param renderer_base $output The renderer.
     * @return array Template context
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $data = [
            'hascourses' => !empty($this->courses),
            'hascourse' => false,
        ];
        if (empty($this->courses) || !isset($this->courses[$this->courseid])) {
            return $data;
        }

        $url = new moodle_url('/local/kursassistent/dashboard.php', ['tab' => $this->tab]);
        $select = new \single_select($url, 'courseid', $this->courses, $this->courseid, null, 'local-kursassistent-courseselect');
        $select->set_label(get_string('dashboard_kurs', 'local_kursassistent'));
        $data['selector'] = $output->render($select);
        $data['hascourse'] = true;
        $data['courseid'] = $this->courseid;
        $data['coursename'] = $this->courses[$this->courseid];
        $data['tab'] = $this->tab;
        $data['helpdashboard'] = $output->help_icon('dashboard', 'local_kursassistent');
        $data['helpinfo'] = $output->help_icon('kursinfo', 'local_kursassistent');
        $data['helpattention'] = $output->help_icon('dashboard_attention', 'local_kursassistent');

        $tabs = [];
        foreach (self::TABS as $key) {
            $tabs[] = [
                'label' => get_string('tab_' . $key, 'local_kursassistent'),
                'url' => (new moodle_url('/local/kursassistent/dashboard.php', [
                    'courseid' => $this->courseid,
                    'tab' => $key,
                ]))->out(false),
                'active' => $key === $this->tab,
            ];
        }
        $data['tabs'] = $tabs;
        foreach (self::TABS as $key) {
            $data['is' . $key] = $key === $this->tab;
        }
        $data['tabtext'] = $this->tab === 'stand' ? '' : get_string('tab_' . $this->tab . '_text', 'local_kursassistent');

        $course = $DB->get_record('course', ['id' => $this->courseid], '*', MUST_EXIST);
        $context = \context_course::instance($this->courseid);
        $stats = statistics::collect($course, $context);
        $data['completionenabled'] = !empty($stats['completionenabled']);
        if ($data['completionenabled']) {
            $data += $this->key_figures($stats);
        }
        return $data;
    }

    /**
     * Calculates the key figures and the list of persons who need attention.
     *
     * @param array $stats Result of statistics::collect
     * @return array Template context
     */
    protected function key_figures(array $stats): array {
        $users = [];
        foreach ($stats['users'] as $user) {
            $users[$user['userid']] = ['name' => $user['fullname'], 'overdue' => 0];
        }
        $done = 0;
        $possible = count($stats['activities']) * count($users);
        $overduecells = 0;
        $nextdue = null;
        $nextname = '';
        $now = time();

        foreach ($stats['activities'] as $activity) {
            $pending = false;
            foreach ($activity['usercompletions'] as $entry) {
                if ($entry['status'] === 'done') {
                    $done++;
                    continue;
                }
                $pending = true;
                if ($entry['status'] === 'overdue' && isset($users[$entry['userid']])) {
                    $users[$entry['userid']]['overdue']++;
                    $overduecells++;
                }
            }
            if ($pending && $activity['due'] >= $now && ($nextdue === null || $activity['due'] < $nextdue)) {
                $nextdue = $activity['due'];
                $nextname = $activity['name'];
            }
        }

        $attention = [];
        foreach ($users as $user) {
            if ($user['overdue'] > 0) {
                $attention[] = [
                    'name' => $user['name'],
                    'overdue' => $user['overdue'],
                    'text' => get_string('attention_overdue', 'local_kursassistent', $user['overdue']),
                ];
            }
        }
        usort($attention, function ($a, $b) {
            return $b['overdue'] <=> $a['overdue'];
        });
        $personscount = count($attention);

        return [
            'studentcount' => count($users),
            'avgpercent' => $possible > 0 ? (int) round(($done / $possible) * 100) : 0,
            'overduecount' => $overduecells,
            'overduepersons' => get_string('kpi_ueberfaellig_personen', 'local_kursassistent', $personscount),
            'hasnextdue' => $nextdue !== null,
            'nextdue' => $nextdue !== null
                ? $nextname . ' · ' . userdate($nextdue, get_string('strftimedatefullshort', 'langconfig'))
                : '',
            'hasattention' => $personscount > 0,
            'attention' => array_slice($attention, 0, 5),
        ];
    }
}
