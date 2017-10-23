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
 * @copyright  2017 Project seminar (Learnweb, University of Münster)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class repository_owncloud_testcase
 * @copyright  2017 Project seminar (Learnweb, University of Münster)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_owncloud_lib_testcase extends advanced_testcase {

    /** @var null|\repository_owncloud the repository_owncloud object, which the tests are run on. */
    private $repo = null;

    /** @var null|\core\oauth2\issuer which belongs to the repository_owncloud object.*/
    private $issuer = null;

    /**
     * SetUp to create an repository instance.
     */
    protected function setUp() {
        $this->resetAfterTest(true);

        // Admin is neccessary to create api and issuer objects.
        $this->setAdminUser();

        /** @var repository_owncloud_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('repository_owncloud');
        $this->issuer = $generator->test_create_issuer();

        // Create Endpoints for issuer.
        $generator->test_create_endpoints($this->issuer->get('id'));

        // Params for the config form.
        $reptype = $generator->create_type([
            'visible' => 1,
            'enableuserinstances' => 0,
            'enablecourseinstances' => 0,
        ]);

        $instance = $generator->create_instance([
            'issuerid' => $this->issuer->get('id'),
        ]);

        // At last, create a repository_owncloud object from the instance id.
        $this->repo = new repository_owncloud($instance->id);
        $this->repo->options['typeid'] = $reptype->id;
        $this->repo->options['sortorder'] = 1;
        $this->resetAfterTest(true);
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
        $issuerid = $this->repo->get_option('issuerid');

        // Config saves the right id.
        $this->assertEquals($this->issuer->get('id'), $issuerid);

        // Function that is used in construct method returns the right id.
        $constructissuer = \core\oauth2\api::get_issuer($issuerid);
        $this->assertEquals($this->issuer->get('id'), $constructissuer->get('id'));

        $this->assertEquals(true, $constructissuer->get('enabled'));
        $this->assertFalse($this->repo->disabled);
    }
    /**
     * Returns an array of endpoints or null.
     * @param string $endpointname
     * @return array|null
     */
    private function get_endpoint_id($endpointname) {
        $endpoints = \core\oauth2\api::get_endpoints($this->issuer);
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
        $boolean = phpunit_util::call_internal_method($this->repo, "is_valid_issuer",
            array('issuer' => $this->issuer), 'repository_owncloud');
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
        $boolean = phpunit_util::call_internal_method($this->repo, "is_valid_issuer",
            array('issuer' => $this->issuer), 'repository_owncloud');
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
        $boolean = phpunit_util::call_internal_method($this->repo, "is_valid_issuer",
            array('issuer' => $this->issuer), 'repository_owncloud');
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
        $boolean = phpunit_util::call_internal_method($this->repo, "is_valid_issuer",
            array('issuer' => $this->issuer), 'repository_owncloud');
        $this->assertFalse($boolean);
    }
    /**
     * Test if repository throws an error when endpoint does not exist.
     */
    public function test_parse_endpoint_url_error() {
        $this->expectException(\repository_owncloud\configuration_exception::class);
        phpunit_util::call_internal_method($this->repo, "parse_endpoint_url",
            array('notexisting' => "notexisting"), 'repository_owncloud');
    }
    /**
     * Test get_listing method with an example directory. Tests error cases.
     */
    public function test_get_listing_error() {
        $ret = $this->get_initialised_return_array();
        $this->setUser();
        // WebDAV socket is not opened.
        $mock = $this->createMock(\repository_owncloud\owncloud_client::class);
        $mock->expects($this->once())->method('open')->will($this->returnValue(false));
        $private = $this->set_private_property($mock, 'dav');

        $this->assertEquals($ret, $this->repo->get_listing('/'));

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
        $ret = $this->get_initialised_return_array();

        // This is the expected response from the ls method.
        $response = array(
            array(
                'href' => 'remote.php/webdav/',
                'lastmodified' => 'Thu, 08 Dec 2016 16:06:26 GMT',
                'resourcetype' => 'collection',
                'status' => 'HTTP/1.1 200 OK',
                'getcontentlength' => ''
            ),
            array(
                'href' => 'remote.php/webdav/Documents/',
                'lastmodified' => 'Thu, 08 Dec 2016 16:06:26 GMT',
                'resourcetype' => 'collection',
                'status' => 'HTTP/1.1 200 OK',
                'getcontentlength' => ''
            ),
            array(
                'href' => 'remote.php/webdav/welcome.txt',
                'lastmodified' => 'Thu, 08 Dec 2016 16:06:26 GMT',
                'status' => 'HTTP/1.1 200 OK',
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
        $this->set_private_property($mock, 'dav');

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
        $ret = $this->get_initialised_return_array();
        $this->setUser();

        // An additional directory path has to be added to the 'path' field within the returned array.
        $ret['path'][1] = array(
            'name' => 'dir',
            'path' => '/dir/'
        );

        // This is the expected response from the get_listing method in the owncloud client.
        $response = array(
            array(
                'href' => 'remote.php/webdav/dir/',
                'lastmodified' => 'Thu, 08 Dec 2016 16:06:26 GMT',
                'resourcetype' => 'collection',
                'status' => 'HTTP/1.1 200 OK',
                'getcontentlength' => ''
            ),
            array(
                'href' => 'remote.php/webdav/dir/Documents/',
                'lastmodified' => null,
                'resourcetype' => 'collection',
                'status' => 'HTTP/1.1 200 OK',
                'getcontentlength' => ''
            ),
            array(
                'href' => 'remote.php/webdav/dir/welcome.txt',
                'lastmodified' => 'Thu, 08 Dec 2016 16:06:26 GMT',
                'status' => 'HTTP/1.1 200 OK',
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
        $mock = $this->createMock(repository_owncloud\owncloud_client::class);
        $mock->expects($this->once())->method('open')->will($this->returnValue(true));
        $mock->expects($this->once())->method('ls')->will($this->returnValue($response));
        $this->set_private_property($mock, 'dav');

        $ls = $this->repo->get_listing('/dir/');

        // Can not be tested properly.
        $ls['list']['DOCUMENTS/']['thumbnail'] = null;
        $ls['list']['WELCOME.TXT']['thumbnail'] = null;

        $this->assertEquals($ret, $ls);
    }
    /**
     * Test the get_link method.
     */
    public function test_get_link_success() {
        $mock = $this->getMockBuilder(\repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone(
            )->getMock();
        $file = '/datei';
        $expectedresponse = <<<XML
<?xml version="1.0"?>
<ocs>
 <meta>
  <status>ok</status>
  <statuscode>100</statuscode>
  <message/>
 </meta>
 <data>
  <id>2</id>
  <share_type>3</share_type>
  <uid_owner>admin</uid_owner>
  <displayname_owner>admin</displayname_owner>
  <permissions>1</permissions>
  <stime>1502883721</stime>
  <parent/>
  <expiration/>
  <token>QXbqrJj8DcMaXen</token>
  <uid_file_owner>admin</uid_file_owner>
  <displayname_file_owner>admin</displayname_file_owner>
  <path>/somefile</path>
  <item_type>file</item_type>
  <mimetype>application/pdf</mimetype>
  <storage_id>home::admin</storage_id>
  <storage>1</storage>
  <item_source>6</item_source>
  <file_source>6</file_source>
  <file_parent>4</file_parent>
  <file_target>/somefile</file_target>
  <share_with/>
  <share_with_displayname/>
  <name/>
  <url>https://www.default.test/somefile</url>
  <mail_send>0</mail_send>
 </data>
</ocs>
XML;
        // Expected Parameters.
        $ocsquery = [
            'path' => $file,
            'shareType' => \repository_owncloud\ocs_client::SHARE_TYPE_PUBLIC,
            'publicUpload' => false,
            'permissions' => \repository_owncloud\ocs_client::SHARE_PERMISSION_READ
        ];

        // With test whether mock is called with right parameters.
        $mock->expects($this->once())->method('call')->with('create_share', $ocsquery)->will($this->returnValue($expectedresponse));
        $this->set_private_property($mock, 'ocsclient');

        // Method does extract the link from the xml format.
        $this->assertEquals('https://www.default.test/somefile/download', $this->repo->get_link($file));
    }

    /**
     * get_link can get OCS failure responses. Test that this is handled appropriately.
     */
    public function test_get_link_failure() {
        $mock = $this->getMockBuilder(\repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone(
            )->getMock();
        $file = '/datei';
        $expectedresponse = <<<XML
<?xml version="1.0"?>
<ocs>
 <meta>
  <status>failure</status>
  <statuscode>404</statuscode>
  <message>Msg</message>
 </meta>
 <data/>
</ocs>
XML;
        // Expected Parameters.
        $ocsquery = [
            'path' => $file,
            'shareType' => \repository_owncloud\ocs_client::SHARE_TYPE_PUBLIC,
            'publicUpload' => false,
            'permissions' => \repository_owncloud\ocs_client::SHARE_PERMISSION_READ
        ];

        // With test whether mock is called with right parameters.
        $mock->expects($this->once())->method('call')->with('create_share', $ocsquery)->will($this->returnValue($expectedresponse));
        $this->set_private_property($mock, 'ocsclient');

        // Suppress (expected) XML parse error... ownCloud/Nextcloud sometimes return JSON on extremely bad errors.
        libxml_use_internal_errors(true);

        // Method get_link correctly raises an exception that contains error code and message.
        $this->expectException(\repository_owncloud\request_exception::class);
        $this->expectExceptionMessage(get_string('request_exception', 'repository_owncloud',
            sprintf('(%s) %s', '404', 'Msg')));
        $this->repo->get_link($file);
    }

    /**
     * get_link can get OCS responses that are not actually XML. Test that this is handled appropriately.
     */
    public function test_get_link_problem() {
        $mock = $this->getMockBuilder(\repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone(
            )->getMock();
        $file = '/datei';
        $expectedresponse = <<<JSON
{"message":"CSRF check failed"}
JSON;
        // Expected Parameters.
        $ocsquery = [
            'path' => $file,
            'shareType' => \repository_owncloud\ocs_client::SHARE_TYPE_PUBLIC,
            'publicUpload' => false,
            'permissions' => \repository_owncloud\ocs_client::SHARE_PERMISSION_READ
        ];

        // With test whether mock is called with right parameters.
        $mock->expects($this->once())->method('call')->with('create_share', $ocsquery)->will($this->returnValue($expectedresponse));
        $this->set_private_property($mock, 'ocsclient');

        // Suppress (expected) XML parse error... ownCloud/Nextcloud sometimes return JSON on extremely bad errors.
        libxml_use_internal_errors(true);

        // Method get_link correctly raises an exception.
        $this->expectException(\repository_owncloud\request_exception::class);
        $this->repo->get_link($file);
    }

    /**
     * Test get_file reference, merely returns the input if no optional_param is set.
     */
    public function test_get_file_reference_withoutoptionalparam() {
        $this->assertEquals('/somefile', $this->repo->get_file_reference('/somefile'));
    }

    /**
     * Test get_file reference in case the optional param is set. Therefore has to simulate the get_link method.
     */
    public function test_get_file_reference_withoptionalparam() {
        $_GET['usefilereference'] = true;
        $filename = '/somefile';
        // Calls for get link(). Therefore, mocks for get_link are build.
        $mock = $this->getMockBuilder(\repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone(
            )->getMock();
        $expectedresponse = <<<XML
<?xml version="1.0"?>
<ocs>
 <meta>
  <status>ok</status>
  <statuscode>100</statuscode>
  <message/>
 </meta>
 <data>
  <id>2</id>
  <share_type>3</share_type>
  <uid_owner>admin</uid_owner>
  <displayname_owner>admin</displayname_owner>
  <permissions>1</permissions>
  <stime>1502883721</stime>
  <parent/>
  <expiration/>
  <token>QXbqrJj8DcMaXen</token>
  <uid_file_owner>admin</uid_file_owner>
  <displayname_file_owner>admin</displayname_file_owner>
  <path>/somefile</path>
  <item_type>file</item_type>
  <mimetype>application/pdf</mimetype>
  <storage_id>home::admin</storage_id>
  <storage>1</storage>
  <item_source>6</item_source>
  <file_source>6</file_source>
  <file_parent>4</file_parent>
  <file_target>/somefile</file_target>
  <share_with/>
  <share_with_displayname/>
  <name/>
  <url>https://www.default.test/somefile</url>
  <mail_send>0</mail_send>
 </data>
</ocs>
XML;
        // Expected Parameters.
        $ocsquery = ['path' => $filename,
            'shareType' => \repository_owncloud\ocs_client::SHARE_TYPE_PUBLIC,
            'publicUpload' => false,
            'permissions' => \repository_owncloud\ocs_client::SHARE_PERMISSION_READ,
        ];

        // With test whether mock is called with right parameters.
        $mock->expects($this->once())->method('call')->with('create_share', $ocsquery)->will($this->returnValue($expectedresponse));
        $this->set_private_property($mock, 'ocsclient');

        // Method redirects to get_link() and return the suitable value.
        $this->assertEquals('https://www.default.test' . $filename . '/download', $this->repo->get_file_reference($filename));
    }

    /**
     * Test the send_file function.
     */
    public function test_send_file() {
        $storedfilemock = $this->createMock(stored_file::class);

        // When executing send file the get_refernce methode of the stored_file object is called.
        $storedfilemock->expects($this->exactly(1))->method('get_reference')->willReturn('/reference');
        // Since the redirect function does not belong to a class it can not be simulated with a mock.
        // However, since moodle throws specific exceptionsthis can be caught.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Unsupported redirect detected, script execution terminated');
        $this->repo->send_file($storedfilemock);
    }
    /**
     * Test logout.
     */
    public function test_logout() {
        $mock = $this->createMock(\core\oauth2\client::class);

        $mock->expects($this->exactly(2))->method('log_out');
        $this->set_private_property($mock, 'client');
        $this->repo->options['ajax'] = false;

        $this->assertEquals($this->repo->print_login(), $this->repo->logout());

        $mock->expects($this->exactly(2))->method('get_login_url')->will($this->returnValue(new moodle_url('url')));

        $this->repo->options['ajax'] = true;
        $this->assertEquals($this->repo->print_login(), $this->repo->logout());

    }
    /**
     * Test for the get_file method from the repository_owncloud class.
     */
    public function test_get_file() {
        // WebDAV socket is not open.
        $mock = $this->createMock(repository_owncloud\owncloud_client::class);
        $mock->expects($this->once())->method('open')->will($this->returnValue(false));
        $private = $this->set_private_property($mock, 'dav');

        $this->assertFalse($this->repo->get_file('path'));

        // WebDAV socket is open and the request successful.
        $mock = $this->createMock(repository_owncloud\owncloud_client::class);
        $mock->expects($this->once())->method('open')->will($this->returnValue(true));
        $mock->expects($this->once())->method('get_file')->will($this->returnValue(true));
        $private->setValue($this->repo, $mock);

        $result = $this->repo->get_file('path', 'file');

        $this->assertNotNull($result['path']);
    }

    /**
     * Test callback.
     */
    public function test_callback() {
        $mock = $this->createMock(\core\oauth2\client::class);
        // Should call check_login exactly once.
        $mock->expects($this->once())->method('log_out');
        $mock->expects($this->once())->method('is_logged_in');

        $this->set_private_property($mock, 'client');

        $this->repo->callback();
    }
    /**
     * Test check_login.
     */
    public function test_check_login() {
        $mock = $this->createMock(\core\oauth2\client::class);
        $mock->expects($this->once())->method('is_logged_in')->will($this->returnValue(true));
        $this->set_private_property($mock, 'client');

        $this->assertTrue($this->repo->check_login());
    }
    /**
     * Test print_login.
     */
    public function test_print_login() {
        $mock = $this->createMock(\core\oauth2\client::class);
        $mock->expects($this->exactly(2))->method('get_login_url')->will($this->returnValue(new moodle_url('url')));
        $this->set_private_property($mock, 'client');

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
     * Test the initiate_webdavclient function.
     */
    public function test_initiate_webdavclient() {
        global $CFG;

        $idwebdav = $this->get_endpoint_id('webdav_endpoint');
        if (!empty($idwebdav)) {
            foreach ($idwebdav as $id) {
                \core\oauth2\api::delete_endpoint($id);
            }
        }

        $generator = $this->getDataGenerator()->get_plugin_generator('repository_owncloud');
        $generator->test_create_single_endpoint($this->issuer->get('id'), "webdav_endpoint",
            "https://www.default.test:8080/webdav/index.php");

        $fakeaccesstoken = new stdClass();
        $fakeaccesstoken->token = "fake access token";
        $oauthmock = $this->createMock(\core\oauth2\client::class);
        $oauthmock->expects($this->once())->method('get_accesstoken')->will($this->returnValue($fakeaccesstoken));
        $this->set_private_property($oauthmock, 'client');

        $dav = phpunit_util::call_internal_method($this->repo, "initiate_webdavclient", [], 'repository_owncloud');

        // Verify that port is set correctly (private property).
        $refclient = new ReflectionClass($dav);
        if ($CFG->branch >= 34) {
            // Class owncloud_client is only alias; get property from parent.
            // TODO Rewrite as soon as M 3.3 is unsupported.
            $refclient = $refclient->getParentClass();
        }

        $property = $refclient->getProperty('_port');
        $property->setAccessible(true);

        $port = $property->getValue($dav);

        $this->assertEquals('8080', $port);
    }

    /**
     * Test supported_returntypes.
     */
    public function test_supported_returntypes() {
        $this->assertEquals(FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE, $this->repo->supported_returntypes());
    }

    /**
     * Helper method, which inserts a given mock value into the repository_owncloud object.
     *
     * @param mixed $value mock value that will be inserted.
     * @param string $propertyname name of the private property.
     * @return ReflectionProperty the resulting reflection property.
     */
    protected function set_private_property($value, $propertyname) {
        $refclient = new ReflectionClass($this->repo);
        $private = $refclient->getProperty($propertyname);
        $private->setAccessible(true);
        $private->setValue($this->repo, $value);

        return $private;
    }
    /**
     * Helper method to set required return parameters for get_listing.
     *
     * @return array array, which contains the parameters.
     */
    protected function get_initialised_return_array() {
        $ret = array();
        $ret['dynload'] = true;
        $ret['nosearch'] = true;
        $ret['nologin'] = false;
        $ret['path'] = [
            [
                'name' => $this->repo->get_meta()->name,
                'path' => '',
            ]
        ];
        $ret['list'] = array();

        return $ret;
    }
}