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
 * The class contains a test script for the moodle block groups
 *
 * @package repository_sciebo
 * @copyright 2016 N Herrmann
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
class repository_sciebo_testcase extends advanced_testcase {

    protected function set_up() {
        // Recommended in Moodle docs to always include CFG.
        global $CFG;
        $generator = $this->getDataGenerator()->get_plugin_generator('repository_sciebo');
        $data = $generator->test_create_preparation();
        $this->resetAfterTest(true);
        return $data;
    }
    /**
     * Function to test the locallib functions.
     */
    public function test_locallib() {
        global $DB, $CFG;
        $data = $this->set_up();
        // Test the function that changes the database.

        /*$functionresultshow = $DB->get_records('block_groups_hide', array('id' => $data['group1']->id));
        $functionresulthide = $DB->get_records('block_groups_hide', array('id' => $data['group2']->id));
        $booleanvisible = empty($functionresultshow);
        $booleandeleted = empty($functionresulthide);

        $this->assertEquals(false, $booleanvisible);
        $this->assertEquals(true, $booleandeleted);

        // Test the function that counts the grouping members.
        $functioncount = count_grouping_members();
        $this->assertEquals(2, $functioncount[$data['grouping1']->id]->number);
        // Members are not counted multiple.
        $this->assertEquals(3, $functioncount[$data['grouping2']->id]->number);
        // Test empty grouping.
        $this->assertEquals(0, $functioncount[$data['grouping3']->id]->number);*/
    }
    /**
     * Methodes recommended by moodle to assure database and dataroot is reset.
     */
    public function test_deleting() {
        global $DB;
        $this->resetAfterTest(true);
        $DB->delete_records('user');
        $this->assertEmpty($DB->get_records('user'));
    }
    /**
     * Methodes recommended by moodle to assure database is reset.
     */
    public function test_user_table_was_reset() {
        global $DB;
        $this->assertEquals(2, $DB->count_records('user', array()));
    }
}