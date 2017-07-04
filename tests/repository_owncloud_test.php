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
 * This file contains tests for the repository_owncloud class.
 *
 * @package     repository_owncloud
 * @group       repository_owncloud
 * @copyright   2017 Westfälische Wilhelms-Universität Münster (WWU Münster)
 * @author      Projektseminar Uni Münster
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

class repository_owncloud_testcase extends advanced_testcase {

    /** @var null|repository_owncloud the repository_owncloud object, which the tests are run on. */
    private $repo = null;

    /**
     * Sets up the tested minor repository_owncloud object and all data records which are
     * needed to initialize the repository.
     */
    protected function setUp() {
        global $DB;
        $this->resetAfterTest(true);

        $typeparams = array('type' => 'owncloud', 'visible' => 0, 'issuerid' => 1, 'validissuers' => '');
        // First, create a owncloud repository type and instance.
        $generator = $this->getDataGenerator()->get_plugin_generator('repository_owncloud');
        $reptype = $generator->create_type($typeparams);

        // Then insert a name for the instance into the database.
        $instance = $DB->get_record('repository_instances', array('typeid' => $reptype->id));
        $DB->update_record('repository_instances', (object) array('id' => $instance->id, 'name' => 'ownCloud'));

        // At last, create a repository_owncloud object from the instance id.
        $this->repo = new repository_owncloud($instance->id);
        $this->repo->options['typeid'] = $reptype->id;
    }

    /**
     * A dummy test.
     */
    public function test_dummy_test(){
        self::assertEquals(1,1);
        $this->setAdminUser();
        $client = $this->createMock(\core\oauth2\client::class);
        $client->expects($this->once())->method('get_endpoint_url')->will($this->returnValue('https://google.de'));
        $path = $this->repo->get_file('some');
        self::assertEquals('https://www.google.de',$path);

    }

}