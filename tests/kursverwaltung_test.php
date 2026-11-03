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

use local_kursassistent\external\kursverwaltung;

/**
 * Tests for course creation and backup services.
 *
 * @package    local_kursassistent
 * @category   test
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_kursassistent\external\kursverwaltung
 */
final class kursverwaltung_test extends \advanced_testcase {
    /**
     * A student must not use the services.
     */
    public function test_student_is_refused(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        kursverwaltung::get_backups($course->id);
    }

    /**
     * A backup contains no enrolled users and is listed with a download address.
     */
    public function test_backup_is_created_and_listed(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enableasyncbackup = 0;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->create_and_enrol($course, 'student');

        $result = kursverwaltung::create_backup($course->id);
        $this->assertNotEmpty($result['filename']);

        $list = kursverwaltung::get_backups($course->id);
        $this->assertTrue($list['cancreate']);
        $this->assertCount(1, $list['files']);
        $this->assertStringContainsString('forcedownload=1', $list['files'][0]['url']);
    }

    /**
     * Only categories with the permission to create courses are offered.
     */
    public function test_creation_offers_permitted_categories(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $data = kursverwaltung::get_kurserstellung($course->id);
        $this->assertSame([], $data['kategorien']);
        $this->assertSame([], $data['quellkurse']);
    }
}
