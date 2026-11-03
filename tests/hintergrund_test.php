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
 * Tests for the background jobs.
 *
 * @package    local_kursassistent
 * @category   test
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_kursassistent\hintergrund
 */
final class hintergrund_test extends \advanced_testcase {
    /**
     * Queuing creates an ad-hoc task and marks the job as waiting.
     */
    public function test_einreihen_queues_task_and_sets_status(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        hintergrund::einreihen('sicherung', (int) $teacher->id, (int) $course->id);

        $status = hintergrund::lese((int) $teacher->id, 'sicherung', (int) $course->id);
        $this->assertSame('wartet', $status['status']);
        $tasks = \core\task\manager::get_adhoc_tasks('\\local_kursassistent\\task\\hintergrund_task');
        $this->assertCount(1, $tasks);
    }

    /**
     * A job without the required permission fails with a message and is not retried.
     */
    public function test_ausfuehren_without_permission_reports_error(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectOutputRegex('/fehlgeschlagen/');
        hintergrund::ausfuehren(['art' => 'sicherung', 'userid' => (int) $student->id, 'courseid' => (int) $course->id]);

        $status = hintergrund::lese((int) $student->id, 'sicherung', (int) $course->id);
        $this->assertSame('fehler', $status['status']);
    }

    /**
     * Only the last jobs are kept so the preference stays small.
     */
    public function test_only_recent_jobs_are_kept(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        for ($i = 1; $i <= 8; $i++) {
            hintergrund::setze((int) $user->id, 'kopie', $i, 'fertig', str_repeat('x', 200));
        }
        $this->assertSame('keiner', hintergrund::lese((int) $user->id, 'kopie', 1)['status']);
        $this->assertSame('fertig', hintergrund::lese((int) $user->id, 'kopie', 8)['status']);
        $this->assertLessThan(1334, strlen(get_user_preferences(hintergrund::EINSTELLUNG, '', $user->id)));
    }
}
