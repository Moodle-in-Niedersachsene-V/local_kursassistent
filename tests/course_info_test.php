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
 * Tests for the course infos.
 *
 * @package    local_kursassistent
 * @category   test
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_kursassistent\course_info
 */
final class course_info_test extends \advanced_testcase {
    /**
     * An info for all is seen by every learner, an info for selected learners only by those.
     */
    public function test_get_for_user_respects_recipients(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $anna = $generator->create_and_enrol($course, 'student');
        $ben = $generator->create_and_enrol($course, 'student');

        course_info::save($course->id, (int) $teacher->id, 'For all', true, []);
        course_info::save($course->id, (int) $teacher->id, 'For Anna', false, [(int) $anna->id]);

        $annainfos = course_info::get_for_user((int) $anna->id, (int) $course->id);
        $beninfos = course_info::get_for_user((int) $ben->id, (int) $course->id);

        $this->assertCount(2, $annainfos);
        $this->assertCount(1, $beninfos);
        $this->assertEquals('For all', $beninfos[0]->infotext);
    }

    /**
     * Deleting an info removes its recipients too.
     */
    public function test_delete_removes_recipients(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $anna = $generator->create_and_enrol($course, 'student');

        $id = course_info::save($course->id, (int) $teacher->id, 'For Anna', false, [(int) $anna->id]);
        course_info::delete((int) $course->id, $id);

        $this->assertFalse($DB->record_exists('local_kursassistent_info', ['id' => $id]));
        $this->assertFalse($DB->record_exists('local_kursassistent_info_user', ['infoid' => $id]));
    }
}
