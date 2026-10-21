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

namespace local_kursassistent;

/**
 * Tests for the automatic award of competencies.
 *
 * Exabis is not installed in the continuous integration, so these tests check the guard clauses only.
 * The award itself has to be checked on an instance with Exabis.
 *
 * @package    local_kursassistent
 * @category   test
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_kursassistent\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Without Exabis a completed activity changes nothing and raises no error.
     */
    public function test_completion_without_exacomp_does_nothing(): void {
        $this->resetAfterTest();
        if (exacomp_bruecke::verfuegbar()) {
            $this->markTestSkipped('Exabis is installed, the guard for a missing Exabis does not apply.');
        }
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $page = $generator->create_module('page', ['course' => $course->id], ['completion' => COMPLETION_TRACKING_MANUAL]);

        $sink = $this->redirectEvents();
        $completion = new \completion_info(get_course($course->id));
        $completion->update_state(get_coursemodule_from_instance('page', $page->id), COMPLETION_COMPLETE, (int) $student->id);
        $events = array_filter($sink->get_events(), function ($event) {
            return $event instanceof \local_kursassistent\event\competency_awarded;
        });
        $sink->close();

        $this->assertCount(0, $events);
    }

    /**
     * The setting switches the award off.
     */
    public function test_setting_off_stops_observer(): void {
        $this->resetAfterTest();
        set_config('awardcompetencies', 0, 'local_kursassistent');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $page = $generator->create_module('page', ['course' => $course->id], ['completion' => COMPLETION_TRACKING_MANUAL]);

        $completion = new \completion_info(get_course($course->id));
        $completion->update_state(get_coursemodule_from_instance('page', $page->id), COMPLETION_COMPLETE, (int) $student->id);

        $this->assertEquals(0, (int) get_config('local_kursassistent', 'awardcompetencies'));
    }
}
