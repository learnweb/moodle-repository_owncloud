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
 * @category    test
 * @copyright  2017 Project seminar (Learnweb, University of Münster)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Class repository_owncloud_testcase
 * @group repository_owncloud
 */
class repository_owncloud_testcase extends advanced_testcase {

    /** @var null|repository_owncloud the repository_owncloud object, which the tests are run on. */
    private $repo = null;

    /** @var null|core\oauth2\issuer which belongs to the repository_owncloud object.*/
    private $issuer = null;

    /**
     * SetUp to create an repository instance.
     */
    protected function setUp() {
        global $DB;
        $this->resetAfterTest(true);

        // Admin is neccessary to create api and issuer objects.
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('repository_owncloud');
        $data = $generator->test_create_preparation();

        // Create the issuer.
        $issuer = \core\oauth2\api::create_issuer($data['issuerdata']);
        $this->issuer = $issuer;

        // Create Endpoints for issuer.
        $this->create_endpoint_test("ocs_endpoint");
        $this->create_endpoint_test("authorization_endpoint");
        $this->create_endpoint_test("webdav_endpoint", "https://www.default.de/webdav/index.php");
        $this->create_endpoint_test("token_endpoint");

        // Params for the config form.
        $typeparams = array('type' => 'owncloud', 'visible' => 1, 'issuerid' => $issuer->get('id'), 'validissuers' => '');

        $reptype = $generator->create_type($typeparams);

        // Then insert a name for the instance into the database.
        $instance = $DB->get_record('repository_instances', array('typeid' => $reptype->id));
        $DB->update_record('repository_instances', (object) array('id' => $instance->id, 'name' => 'ownCloud'));

        // At last, create a repository_owncloud object from the instance id.
        $this->repo = new repository_owncloud($instance->id);
        $this->repo->options['typeid'] = $reptype->id;
        $this->resetAfterTest(true);
    }

    /**
     * Dummy test for mock.
     */
    public function test_return_self() {
        // Create a stub for the SomeClass class.
        $stub = $this->createMock(\core\oauth2\issuer::class);

        // Configure the stub.
        $stub->method('get_endpoint_url')->willReturn('www.filepath.de');

        $this->assertSame('www.filepath.de', $stub->get_endpoint_url("test"));
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
     * Test weather the repo is disabled.
     */
    public function test_repo_creation() {

        $issuerid = get_config('owncloud', 'issuerid');

        // Config saves the right id.
        $this->assertEquals($this->issuer->get('id'), $issuerid);

        // Function that is used in construct method returns the right id.
        $constructissuer = \core\oauth2\api::get_issuer($issuerid);
        $this->assertEquals($this->issuer->get('id'), $constructissuer->get('id'));

        $issuerenabled = $constructissuer->get('enabled');

        $this->assertEquals(true, $issuerenabled);
        $this->assertFalse($this->repo->disabled);
    }


    /**
     * Way to test private methods.
     * @param $object
     * @param $methodname
     * @param array $parameters
     * @return mixed
     */
    public function invoke_private_method(&$object, $methodname, array $parameters = array()) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodname);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }


    /**
     * Returns an array of endpoints or null.
     * @param $endpointname
     * @return array|null
     */
    private function get_endpoint_id($endpointname) {
        $endpoints = \core\oauth2\api::get_endpoints($this->issuer);
        $counter = 0;
        $id = array();
        foreach ($endpoints as $endpoint) {
            $name = $endpoint->get('name');
            if ($name === $endpointname) {
                $id[$endpoint->get('id')] = $endpoint->get('id');
            }
        }
        if (empty($id)) {
            return null;
        }
        return $id;
    }
    /**
     * Test if repository is disabled when webdav_endpoint is deleted.
     */
    public function test_issuer_webdav() {
        $idwebdav = $this->get_endpoint_id('webdav_endpoint');
        if (!empty($idwebdav)) {
            foreach ($idwebdav as $id) {
                \core\oauth2\api::delete_endpoint($id);
            }
        }
        $boolean = $this->invoke_private_method($this->repo, "is_valid_issuer", array('issuer' => $this->issuer));
        $this->assertFalse($boolean);
    }
    /**
     * Test if repository is disabled when ocs_endpoint is deleted.
     */
    public function test_issuer_ocs() {
        $idocs = $this->get_endpoint_id('ocs_endpoint');
        if (!empty($idocs)) {
            foreach ($idocs as $id) {
                \core\oauth2\api::delete_endpoint($id);
            }
        }
        $boolean = $this->invoke_private_method($this->repo, "is_valid_issuer", array('issuer' => $this->issuer));
        $this->assertFalse($boolean);
    }
    /**
     * Test if repository is disabled when token_endpoint is deleted.
     */
    public function test_issuer_token() {
        $idtoken = $this->get_endpoint_id('token_endpoint');
        if (!empty($idtoken)) {
            foreach ($idtoken as $id) {
                \core\oauth2\api::delete_endpoint($id);
            }
        }
        $boolean = $this->invoke_private_method($this->repo, "is_valid_issuer", array('issuer' => $this->issuer));
        $this->assertFalse($boolean);
    }

    /**
     * Test if repository is disabled when auth_endpoint is deleted.
     */
    public function test_issuer_authorization() {
        $idauth = $this->get_endpoint_id('authorization_endpoint');
        if (!empty($idauth)) {
            foreach ($idauth as $id) {
                \core\oauth2\api::delete_endpoint($id);
            }
        }
        $boolean = $this->invoke_private_method($this->repo, "is_valid_issuer", array('issuer' => $this->issuer));
        $this->assertFalse($boolean);
    }
    /**
     * Test if repository throws an error when endpoint does not exist.
     */
    public function test_parse_endpoint_url_error() {
        $this->expectException(\repository_owncloud\configuration_exception::class);
        $this->invoke_private_method($this->repo, "parse_endpoint_url", array('notexisting' => "notexisting"));
    }
    /**
     * Test get_listing method with an example directory. Tests error cases.
     */
    public function test_get_listing_error() {
        $ret = $this->get_ret();
        $this->setUser();
        // WebDAV socket is not opened.
        $mock = $this->createMock(\repository_owncloud\owncloud_client::class);
        $mock->expects($this->once())->method('open')->will($this->returnValue(false));
        $private = $this->set_private_repository($mock, 'dav');

        $this->assertEquals($ret, $this->repo->get_listing('path'));

        // Response is not an array.
        $mock = $this->createMock(\repository_owncloud\owncloud_client::class);
        $mock->expects($this->once())->method('open')->will($this->returnValue(true));
        $mock->expects($this->once())->method('ls')->will($this->returnValue('notanarray'));
        $private->setValue($this->repo, $mock);

        $this->assertEquals($ret, $this->repo->get_listing('/'));
    }
    /**
     * Test get_listing method with an example directory. Tests the root directory.
     */
    public function test_get_listing_root() {
        $this->setUser();
        $ret = $this->get_ret();

        // This is the expected response from the ls method.
        $response = array(
            array(
                'href' => 'remote.php/webdav/',
                'lastmodified' => 'Thu, 08 Dec 2016 16:06:26 GMT',
                'resourcetype' => 'collection',
                'status' => 'HTTP/1.1 200 OKHTTP/1.1 404 Not Found',
                'getcontentlength' => ''
            ),
            array(
                'href' => 'remote.php/webdav/Documents/',
                'lastmodified' => 'Thu, 08 Dec 2016 16:06:26 GMT',
                'resourcetype' => 'collection',
                'status' => 'HTTP/1.1 200 OKHTTP/1.1 404 Not Found',
                'getcontentlength' => ''
            ),
            array(
                'href' => 'remote.php/webdav/welcome.txt',
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
        $mock = $this->createMock(repository_owncloud\owncloud_client::class);
        $mock->expects($this->once())->method('open')->will($this->returnValue(true));
        $mock->expects($this->once())->method('ls')->will($this->returnValue($response));
        $this->set_private_repository($mock, 'dav');

        $ls = $this->repo->get_listing('/');

        // Those attributes can not be tested properly.
        $ls['list']['DOCUMENTS/']['thumbnail'] = null;
        $ls['list']['WELCOME.TXT']['thumbnail'] = null;

        $this->assertEquals($ret, $ls);
    }
    /**
     * Test logout.
     */
    public function test_logout() {
        $mock = $this->createMock(\core\oauth2\client::class);
        $mockhelper = $this->createMock(oauth2_client::class);

        $mock->expects($this->once())->method('log_out');
        $this->set_private_repository($mock, 'client');
        $this->repo->options['ajax'] = false;

        $this->assertEquals($this->repo->print_login(), $this->repo->logout());

        // TODO: TEst for ajax = true.

    }

    /**
     * Test callback.
     */
    public function test_callback() {
        $mock = $this->createMock(\core\oauth2\client::class);

        $mock2 = $this->createMock(oauth2_client::class);
        // Should call check_login exactly once.
        $mock->expects($this->once())->method('log_out');
        $mock->expects($this->once())->method('is_logged_in');

        $this->set_private_repository($mock, 'client');

        $this->repo->callback();
    }
    /**
     * Test check_login.
     */
    public function test_check_login() {
        $mock = $this->createMock(\core\oauth2\client::class);
        $mock->expects($this->once())->method('is_logged_in')->will($this->returnValue(true));
        $this->set_private_repository($mock, 'client');

        $this->assertTrue($this->repo->check_login());
    }
    /**
     * Test print_login.
     */
    public function test_print_login() {
        $mock = $this->createMock(\core\oauth2\client::class);
        $mock->expects($this->exactly(2))->method('get_login_url')->will($this->returnValue(new moodle_url('url')));
        $this->set_private_repository($mock, 'client');

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
    protected function set_private_repository($mock, $value) {
        $refclient = new ReflectionClass($this->repo);
        $private = $refclient->getProperty($value);
        $private->setAccessible(true);
        $private->setValue($this->repo, $mock);

        return $private;
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
     * @param $endpointtype
     * @param string $url
     * @return mixed
     */
    protected function create_endpoint_test($endpointtype, $url="https://www.default.de") {
        $endpoint = new stdClass();
        $endpoint->name = $endpointtype;
        $endpoint->url = $url;
        $endpoint->issuerid = $this->issuer->get('id');
        $return = \core\oauth2\api::create_endpoint($endpoint);
        return $return;
    }

}