<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Provides the {@link local_libwall_testcase} class.
 *
 * @package     local_libwall
 * @category    test
 * @copyright   2015 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_libwall;

use advanced_testcase;
use context_system;

global $CFG;

require_once(__DIR__.'/fixtures/testable_wall.php');

/**
 * Test cases for the {@link local_libwall\wall} class.
 *
 * @copyright 2015 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wall_testcase extends advanced_testcase {

    public function setUp() {
        $this->resetAfterTest();
    }

    public function test_getting_wall_instance() {

        $wall1 = wall::instance_by_location(context_system::instance(), 'block_foobar');

        $this->assertInstanceOf('\local_libwall\wall', $wall1);
        $this->assertSame(context_system::instance(), $wall1->context);
        $this->assertSame('block_foobar', $wall1->component);
        $this->assertSame('', $wall1->area);
        $this->assertEquals(0, $wall1->itemid);

        $wall2 = wall::instance_by_id($wall1->id);

        $this->assertInstanceOf('\local_libwall\wall', $wall2);
        $this->assertEquals($wall1->id, $wall2->id);
        $this->assertSame($wall1->context, $wall2->context);
        $this->assertSame($wall1->component, $wall2->component);
        $this->assertSame($wall1->area, $wall2->area);
        $this->assertEquals($wall1->itemid, $wall2->itemid);

        $wall3 = wall::instance_by_location(context_system::instance(), 'block_foobar', 'test', 3);
        $this->assertNotEquals($wall1->id, $wall3->id);
        $this->assertSame('test', $wall3->area);
        $this->assertEquals(3, $wall3->itemid);
    }

    public function test_add_comment() {
        global $DB;

        $user = get_admin();

        $wall1 = wall::instance_by_location(context_system::instance(), 'core', 'test', 1);
        $wall2 = wall::instance_by_location(context_system::instance(), 'core', 'test', 2);

        $id11 = $wall1->add_comment('Wall 1 - 1st comment', FORMAT_MOODLE, $user);
        $id12 = $wall1->add_comment('Wall 1 - 2nd comment', FORMAT_MOODLE, $user);
        $id21 = $wall2->add_comment('Wall 2 - 1st comment', FORMAT_MOODLE, $user);
        $id13 = $wall1->add_comment('Wall 1 - 3rd comment', FORMAT_MOODLE, $user);
        $id22 = $wall2->add_comment('Wall 2 - 2nd comment', FORMAT_MOODLE, $user);

        $this->assertEquals(1, $DB->get_field('local_libwall_comments', 'seqnum', array('id' => $id11), MUST_EXIST));
        $this->assertEquals(2, $DB->get_field('local_libwall_comments', 'seqnum', array('id' => $id12), MUST_EXIST));
        $this->assertEquals(1, $DB->get_field('local_libwall_comments', 'seqnum', array('id' => $id21), MUST_EXIST));
        $this->assertEquals(3, $DB->get_field('local_libwall_comments', 'seqnum', array('id' => $id13), MUST_EXIST));
        $this->assertEquals(2, $DB->get_field('local_libwall_comments', 'seqnum', array('id' => $id22), MUST_EXIST));
    }

    public function test_add_comment_race_condition_seqnum() {
        global $DB;

        $user = get_admin();

        $wall = testable_wall::instance_by_location(context_system::instance(), 'core', 'test', 3);

        $id1 = $wall->add_comment('First comment should get seqnum 1', FORMAT_MOODLE, $user);
        $this->assertEquals(1, $DB->get_field('local_libwall_comments', 'seqnum', array('id' => $id1), MUST_EXIST));

        $wall->fakenextseqnum = 1;
        $id2 = $wall->add_comment('Race conditions sim: attempt another seqnum=1 comment', FORMAT_MOODLE, $user);
        $this->assertEquals(2, $DB->get_field('local_libwall_comments', 'seqnum', array('id' => $id2), MUST_EXIST));
    }

    public function test_add_comment_invalid_content() {

        $wall = testable_wall::instance_by_location(context_system::instance(), 'core');

        $this->setExpectedException('dml_exception');
        $wall->add_comment(null, FORMAT_MOODLE, get_admin());
    }

    public function test_add_comment_guest_user() {
        global $CFG;

        $wall = testable_wall::instance_by_location(context_system::instance(), 'core');

        $this->setExpectedException('coding_exception');
        $wall->add_comment('Guests not allowed to comment', FORMAT_HTML, $CFG->siteguest);
    }

    public function test_add_comment_invalid_user() {
        global $CFG;

        $wall = testable_wall::instance_by_location(context_system::instance(), 'core');

        $this->setExpectedException('coding_exception');
        $wall->add_comment('WTF is this from?', FORMAT_PLAIN, -1);
    }
}
