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

namespace local_kursassistent\hook_callbacks;

/**
 * Tests for the callback of the work planner.
 *
 * The block is not installed in the continuous integration, the test is skipped there.
 *
 * @package    local_kursassistent
 * @category   test
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_kursassistent\hook_callbacks\workplanner_callback
 */
final class workplanner_callback_test extends \advanced_testcase {
    /**
     * The callback runs for a course with infos and notes without errors.
     */
    public function test_extend_planner_runs(): void {
        global $DB;
        $this->resetAfterTest();
        if (!class_exists(\block_workplanner\hook\extend_planner::class)) {
            $this->markTestSkipped('block_workplanner is not installed.');
        }
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        $page = $generator->create_module('page', ['course' => $course->id]);

        \local_kursassistent\course_info::save($course->id, (int) $teacher->id, 'Info', true, []);
        $DB->insert_record('local_kursassistent_comment', (object) [
            'courseid' => $course->id,
            'studentid' => $student->id,
            'teacherid' => $teacher->id,
            'cmid' => $page->cmid,
            'authorrole' => 'teacher',
            'visibility' => 'student',
            'commenttext' => 'Note',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $hook = new \block_workplanner\hook\extend_planner((int) $student->id, [(int) $course->id]);
        workplanner_callback::extend_planner($hook);
        $this->assertTrue(true);
    }
}
