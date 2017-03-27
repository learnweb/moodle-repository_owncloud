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
        set_config('server', 'pssl16.uni-muenster.de', 'tool_oauth2owncloud');
        set_config('path', 'owncloud9.2/remote.php/webdav/', 'tool_oauth2owncloud');
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
     * Test get_listing method with an example directory. Tests error cases.
     */
    public function test_get_listing_error() {
        $ret = $this->get_ret();

        // WebDAV socket is not opened.
        $mock = $this->createMock(owncloud::class);
        $mock->expects($this->once())->method('open')->will($this->returnValue(false));
        $private = $this->set_private_repository($mock);

        $this->assertEquals($ret, $this->repo->get_listing('path'));

        // Response is not an array.
        $mock = $this->createMock(owncloud::class);
        $mock->expects($this->once())->method('open')->will($this->returnValue(true));
        $mock->expects($this->once())->method('get_listing')->will($this->returnValue('notanarray'));
        $private->setValue($this->repo, $mock);

        $this->assertEquals($ret, $this->repo->get_listing('/'));
    }
    /**
     * Test get_listing method with an example directory. Tests the root directory.
     */
    public function test_get_listing_root() {
        $ret = $this->get_ret();

        // This is the expected response from the get_listing method in the owncloud client.
        $response = array(
                array(
                        'href' => '/owncloud9.2/remote.php/webdav/',
                        'lastmodified' => 'Thu, 08 Dec 2016 16:06:26 GMT',
                        'resourcetype' => 'collection',
                        'status' => 'HTTP/1.1 200 OKHTTP/1.1 404 Not Found',
                        'getcontentlength' => ''
                ),
                array(
                        'href' => '/owncloud9.2/remote.php/webdav/Documents/',
                        'lastmodified' => 'Thu, 08 Dec 2016 16:06:26 GMT',
                        'resourcetype' => 'collection',
                        'status' => 'HTTP/1.1 200 OKHTTP/1.1 404 Not Found',
                        'getcontentlength' => ''
                ),
                array(
                        'href' => '/owncloud9.2/remote.php/webdav/welcome.txt',
                        'lastmodified' => 'Thu, 08 Dec 2016 16:06:26 GMT',
                        'status' => 'HTTP/1.1 200 OKHTTP/1.1 404 Not Found',
                        'getcontentlength' => '163'
                )
        );

        // The expected result from the get_listing method in the repository_owncloud class.
        $ret['list'] = array(
                'DOCUMENTS/' => array(
                        'title' => 'Documents',
                        'thumbnail' => null,
                        'children' => array(),
                        'datemodified' => 1481213186,
                        'path' => '/Documents/'
                ),
                'WELCOME.TXT' => array(
                        'title' => 'welcome.txt',
                        'thumbnail' => null,
                        'size' => '163',
                        'datemodified' => 1481213186,
                        'source' => '/welcome.txt'
                )
        );

        // Valid response from the client.
        $mock = $this->createMock(owncloud::class);
        $mock->expects($this->once())->method('open')->will($this->returnValue(true));
        $mock->expects($this->once())->method('get_listing')->will($this->returnValue($response));
        $this->set_private_repository($mock);

        $ls = $this->repo->get_listing('/');

        // Those attributes can not be tested properly.
        $ls['list']['DOCUMENTS/']['thumbnail'] = null;
        $ls['list']['WELCOME.TXT']['thumbnail'] = null;

        $this->assertEquals($ret, $ls);
    }

    /**
     * Test get_listing method with an example directory. Tests a different directory than the root
     * directory.
     */
    public function test_get_listing_directory() {
        $ret = $this->get_ret();

        // An additional directory path has to be added to the 'path' field within the returned array.
        $ret['path'][1] = array(
                'name' => 'dir',
                'path' => '/dir/'
        );

        // This is the expected response from the get_listing method in the owncloud client.
        $response = array(
                array(
                        'href' => '/owncloud9.2/remote.php/webdav/dir/',
                        'lastmodified' => 'Thu, 08 Dec 2016 16:06:26 GMT',
                        'resourcetype' => 'collection',
                        'status' => 'HTTP/1.1 200 OKHTTP/1.1 404 Not Found',
                        'getcontentlength' => ''
                ),
                array(
                        'href' => '/owncloud9.2/remote.php/webdav/dir/Documents/',
                        'lastmodified' => null,
                        'resourcetype' => 'collection',
                        'status' => 'HTTP/1.1 200 OKHTTP/1.1 404 Not Found',
                        'getcontentlength' => ''
                ),
                array(
                        'href' => '/owncloud9.2/remote.php/webdav/dir/welcome.txt',
                        'lastmodified' => 'Thu, 08 Dec 2016 16:06:26 GMT',
                        'status' => 'HTTP/1.1 200 OKHTTP/1.1 404 Not Found',
                        'getcontentlength' => '163'
                )
        );

        // The expected result from the get_listing method in the repository_owncloud class.
        $ret['list'] = array(
                'DOCUMENTS/' => array(
                        'title' => 'Documents',
                        'thumbnail' => null,
                        'children' => array(),
                        'datemodified' => null,
                        'path' => '/dir/Documents/'
                ),
                'WELCOME.TXT' => array(
                        'title' => 'welcome.txt',
                        'thumbnail' => null,
                        'size' => '163',
                        'datemodified' => 1481213186,
                        'source' => '/dir/welcome.txt'
                )
        );

        // Valid response from the client.
        $mock = $this->createMock(owncloud::class);
        $mock->expects($this->once())->method('open')->will($this->returnValue(true));
        $mock->expects($this->once())->method('get_listing')->will($this->returnValue($response));
        $this->set_private_repository($mock);

        $ls = $this->repo->get_listing('/dir/');

        // Can not be tested properly.
        $ls['list']['DOCUMENTS/']['thumbnail'] = null;
        $ls['list']['WELCOME.TXT']['thumbnail'] = null;

        $this->assertEquals($ret, $ls);
    }

    /**
     * Helper method to set required return parameters for get_listing.
     *
     * @return array array, which contains the parameters.
     */
    protected function get_ret() {
        $ret = array();
        $ret['dynload'] = true;
        $ret['nosearch'] = true;
        $ret['nologin'] = false;
        $ret['path'] = array(array('name' => get_string('owncloud', 'repository_owncloud'), 'path' => ''));
        $ret['list'] = array();

        return $ret;
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
        $mock->expects($this->once())->method('log_out');
        $mock->expects($this->exactly(2))->method('get_login_url')->will($this->returnValue(new moodle_url('url')));
        $this->set_private_repository($mock);
        $this->repo->options['ajax'] = true;

        $this->assertNull(get_user_preferences('oC_token'));
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