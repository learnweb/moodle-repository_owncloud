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

require($CFG->dirroot . '/repository/owncloud/lib.php');

use tool_oauth2owncloud\owncloud;

class repository_owncloud_testcase extends advanced_testcase {

    /** @var null|repository_owncloud the repository_owncloud object, which the tests are run on. */
    private $repo = null;

    /**
     * Sets up the tested repository_owncloud object and all data records which are
     * needed to initialize the repository.
     */
    protected function setUp() {
        $this->resetAfterTest(true);

        global $DB;

        // Setup some settings required for the Client.
        set_config('clientid', 'testid', 'tool_oauth2owncloud');
        set_config('secret', 'testsecret', 'tool_oauth2owncloud');
        set_config('server', 'localhost', 'tool_oauth2owncloud');
        set_config('path', 'owncloud/remote.php/webdav/', 'tool_oauth2owncloud');
        set_config('protocol', 'https', 'tool_oauth2owncloud');
        set_config('port', 1000, 'tool_oauth2owncloud');

        $typeparams = array('type' => 'owncloud', 'visible' => 0);

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
     * Checks the is_visible method in case the repository is set to visible in the database.
     */
    public function test_is_visible_parent_true() {
        // Check, if the method returns true, if the repository is set to visible in the database
        // and the client configuration data is complete.
        $this->assertTrue($this->repo->is_visible());

        // Check, if the method returns false, when the repository is set to visible in the database,
        // but the client configuration data is incomplete.
        $this->repo->options['success'] = false;

        $this->assertFalse($this->repo->is_visible());
    }

    /**
     * Checks the is_visible method in case the repository is set to hidden in the database.
     */
    public function test_is_visible_parent_false() {
        global $DB;
        $id = $this->repo->options['typeid'];

        // Check, if the method returns false, when the repository is set to visible in the database
        // and the client configuration data is complete.
        $DB->update_record('repository', (object) array('id' => $id, 'visible' => 0));

        $this->assertFalse($this->repo->is_visible());
    }

    /**
     * Test for the get_file method from the repository_owncloud class.
     */
    public function test_get_file() {
        // WebDAV socket is not open.
        $mock = $this->createMock(owncloud::class);
        $mock->expects($this->once())->method('open')->will($this->returnValue(false));
        $private = $this->set_private_repository($mock);

        $this->assertFalse($this->repo->get_file('path'));

        // WebDAV socket is open and the request successful.
        $mock = $this->createMock(owncloud::class);
        $mock->expects($this->once())->method('open')->will($this->returnValue(true));
        $mock->expects($this->once())->method('get_file')->will($this->returnValue(true));
        $private->setValue($this->repo, $mock);

        $result = $this->repo->get_file('path', 'file');

        $this->assertNotNull($result['path']);
    }

    /**
     * Test the get_link method.
     */
    public function test_get_link() {
        $mock = $this->createMock(owncloud::class);
        $mock->expects($this->once())->method('get_link')->will($this->returnValue(array('link' => 'link')));
        $this->set_private_repository($mock);

        $this->assertEquals('link', $this->repo->get_link('path'));
    }

    /**
     * Tests for the get_file_reference method from the repository_owncloud class.
     */
    public function test_get_file_reference() {
        $mock = $this->createMock(owncloud::class);
        $mock->expects($this->once())->method('get_link')->will($this->returnValue(array('link' => 'link')));
        $this->set_private_repository($mock);

        $source = 'path';

        // Reference for a download.
        $this->assertEquals($source, $this->repo->get_file_reference($source));

        // A link should be generated.
        $_POST['usefilereference'] = true;

        $this->assertEquals('link', $this->repo->get_file_reference($source));
    }

    /**
     * Test check_login.
     */
    public function test_check_login() {
        $mock = $this->createMock(owncloud::class);
        $mock->expects($this->once())->method('check_login')->will($this->returnValue(true));
        $this->set_private_repository($mock);

        $this->assertTrue($this->repo->check_login());
    }

    /**
     * Test print_login.
     */
    public function test_print_login() {
        $mock = $this->createMock(owncloud::class);
        $mock->expects($this->exactly(2))->method('get_login_url')->will($this->returnValue(new moodle_url('url')));
        $this->set_private_repository($mock);

        // Test with ajax activated.
        $this->repo->options['ajax'] = true;

        $url = new moodle_url('url');
        $ret = array();
        $btn = new \stdClass();
        $btn->type = 'popup';
        $btn->url = $url->out(false);
        $ret['login'] = array($btn);

        $this->assertEquals($ret, $this->repo->print_login());

        // Test without ajax.
        $this->repo->options['ajax'] = false;

        $output = html_writer::link($url, get_string('login', 'repository'),
                array('target' => '_blank',  'rel' => 'noopener noreferrer'));

        $this->expectOutputString($output);
        $this->repo->print_login();
    }

    /**
     * Test logout.
     */
    public function test_logout() {
        $mock = $this->createMock(owncloud::class);
        $mock->expects($this->once())->method('logout');
        $mock->expects($this->once())->method('get_login_url')->will($this->returnValue(new moodle_url('url')));
        $this->set_private_repository($mock);
        $this->repo->options['ajax'] = true;

        $this->assertNull(get_user_preference('oC_token'));
        $this->assertEquals($this->repo->print_login(), $this->repo->logout());
    }

    /**
     * Test callback.
     */
    public function test_callback() {
        $mock = $this->createMock(owncloud::class);
        // Should call check_login exactly once.
        $mock->expects($this->once())->method('check_login');
        $this->set_private_repository($mock);

        $this->repo->callback();
    }

    /**
     * Test supported_filetypes.
     */
    public function test_supported_filetypes() {
        $this->assertEquals('*', $this->repo->supported_filetypes());
    }

    /**
     * Test supported_returntypes.
     */
    public function test_supported_returntypes() {
        $this->assertEquals(FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE, $this->repo->supported_returntypes());
    }

    /**
     * Helper method, which inserts a given owncloud mock object into the repository_owncloud object.
     *
     * @param $mock object mock object, which needs to be inserted.
     * @return ReflectionProperty the resulting reflection property.
     */
    protected function set_private_repository($mock) {
        $refclient = new ReflectionClass($this->repo);
        $private = $refclient->getProperty('owncloud');
        $private->setAccessible(true);
        $private->setValue($this->repo, $mock);

        return $private;
    }
}