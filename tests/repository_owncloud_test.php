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
     * @param $endpointname
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
    public function test_get_link() {
        $mock = $this->getMockBuilder(\core\oauth2\client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $url = '/datei';
        $expectedresponse = <<<XML
<?xml version='1.0'?>
<document>
 <title>sometitle</title>
 <data>
 <url>https://www.default.de</url>
 </data>
 <meta>
 <statuscode>HTTP/1.1 200</statuscode>
 <status>
   OK
 </status>
 </meta>
</document>
XML;
        // Expected Parameters.
        $ocsquery = http_build_query(array('path' => $url,
            'shareType' => 3,
            'publicUpload' => false,
            'permissions' => 31
        ), null, "&");
        $posturl = $this->issuer->get_endpoint_url('ocs');

        // With test whether mock is called with right parameters.
        $mock->expects($this->once())->method('post')->with($posturl, $ocsquery, [])->will($this->returnValue($expectedresponse));
        $this->set_private_property($mock, 'client');

        // Method does extract the link from the xml format.
        $this->assertEquals('https://www.default.de/download', $this->repo->get_link('/datei'));
    }

    /**
     * Test get_file reference, merely returns the input if no optional_param is set.
     */
    public function test_get_file_reference_withoutoptionalparam() {
        $this->repo->get_file_reference('/somefile');
        $this->assertEquals('/somefile', $this->repo->get_file_reference('/somefile'));
    }

    /**
     * Test get_file reference in case the optional param is set. Therefore has to simulate the get_link method.
     */
    public function test_get_file_reference_withoptionalparam() {
        $_GET['usefilereference'] = true;
        // Calls for get link(). Therefore, mocks for get_link are build.
        $mock = $this->getMockBuilder(\core\oauth2\client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $expectedresponse = <<<XML
<?xml version='1.0'?>
<document>
 <title>sometitle</title>
 <data>
 <url>https://www.default.de/somefile</url>
 </data>
 <meta>
 <statuscode>HTTP/1.1 200</statuscode>
 <status>
   OK
 </status>
 </meta>
</document>
XML;
        // Expected Parameters.
        $ocsquery = http_build_query(array('path' => '/somefile',
            'shareType' => 3,
            'publicUpload' => false,
            'permissions' => 31
        ), null, "&");
        $posturl = $this->issuer->get_endpoint_url('ocs');

        // With test whether mock is called with right parameters.
        $mock->expects($this->once())->method('post')->with($posturl, $ocsquery, [])->will($this->returnValue($expectedresponse));
        $this->set_private_property($mock, 'client');

        // Method redirects to get_link() and return the suitable value.
        $this->assertEquals('https://www.default.de/somefile/download', $this->repo->get_file_reference('/somefile'));
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
        $idwebdav = $this->get_endpoint_id('webdav_endpoint');
        if (!empty($idwebdav)) {
            foreach ($idwebdav as $id) {
                \core\oauth2\api::delete_endpoint($id);
            }
        }
        $generator = $this->getDataGenerator()->get_plugin_generator('repository_owncloud');

        $generator->test_create_single_endpoint($this->issuer->get('id'), "webdav_endpoint",
            "https://www.default.de:8080/webdav/index.php");
        $dav = $this->repo->initiate_webdavclient();

        $value = $this->get_private_property($dav, '_port');

        $this->assertEquals('8080', $value->getValue($dav));

        $this->expectException(core\invalid_persistent_exception::class);

        $generator->test_create_single_endpoint($this->issuer->get('id'), "webdav_endpoint",
            "http://www.default.de/webdav/index.php");
        $this->repo->initiate_webdavclient();
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
     * Test the type_config_form
     *
     */
    public function test_type_config_form() {
        // Simulate the QuickFormClass
        $form = $this->getMockBuilder(MoodleQuickForm::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        // No issuer was perviously selected.
        set_config('issuerid', '',  'owncloud');

        // Expected Messages since no issuer is selected.
        $functionsparams = $this->get_params_addelement_configform('warning', 'issuervalidation_without');

        // The expected values for the methode are defined. It is expected to be called 6 times.
        // Since the params can not be allocated to specific calls a logical OR is used.
        $this->set_type_config_form_expect($form, $functionsparams, 6, null);
        // Finally, the methode is called.
        phpunit_util::call_internal_method($this->repo, 'type_config_form', array($form), 'repository_owncloud');

    }

    /**
     * Test the type-config form with a valid issuer.
     */
    /*public function test_type_config_valid_issuer() {
        $form = $this->getMockBuilder(MoodleQuickForm::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $selectelement = $this->getMockBuilder(MoodleQuickForm_select::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();

        set_config('issuerid', $this->issuer->get('id'),  'owncloud');

        // Params for the addElement function are generated.
        // Since the function is called six times and can not be tested individually all params for the 6 calls are generated.
        $functionsparams = $this->get_params_addelement_configform('info', 'issuervalidation_valid');

        $this->set_type_config_form_expect($form, $functionsparams, 6, $selectelement);

        $this->expect_exceptions($form);
    }*/
    /**
     * Test the type-config form with a invalid issuer.
     */
    /*public function test_type_config_invalid_issuer() {
        $form = $this->getMockBuilder(MoodleQuickForm::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $selectelement = $this->getMockBuilder(MoodleQuickForm_select::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        set_config('issuerid', $this->issuer->get('id'),  'owncloud');
        // Delete issuer endpoint to make issuer invalid.
        $idwebdav = $this->get_endpoint_id('webdav_endpoint');
        if (!empty($idwebdav)) {
            foreach ($idwebdav as $id) {
                \core\oauth2\api::delete_endpoint($id);
            }
        }
        // Params for the addElement function are generated.
        // Since the function is called six times and can not be tested individually all params for the 6 calls are generated.
        $functionsparams = $this->get_params_addelement_configform('error', 'issuervalidation_invalid');

        $this->set_type_config_form_expect ($form, $functionsparams, 6, $selectelement);

        $this->expect_exceptions($form);
    }*/

    /**
     * Was supposed to handle the different php Versions since php 5.6 and 7/7.1 throw different exceptions.
     * Php 5.6 is not able to catch fatal_errors therefore it is excluded in travis.
     * Still exceptions are handled seperately.
     * @param $form
     */
    /*protected function expect_exceptions($form) {
        try {
            phpunit_util::call_internal_method($this->repo, 'type_config_form', array($form), 'repository_owncloud');
            // This block should never be reached since always a exception should be thrown.
            $this->assertTrue(false);
        } catch (Exception $e) {
            print "a";
            $this->assertRegExp('/Call to undefined method Mock_MoodleQuickForm_select/', $e->getMessage());
        } catch (Throwable $exceptionorerror) {
            print "b";
            $this->assertRegExp('/Call to undefined method Mock_MoodleQuickForm_select/', $exceptionorerror->getMessage());
        }

    }*/
    /**
     * Sets the expect params for form.
     * @param $form
     * @param $functionsparams
     * @param $count
     * @param $return
     */
    protected function set_type_config_form_expect ($form, $functionsparams, $count, $return) {
        $form->expects($this->exactly($count))->method('addElement')->with($this->logicalOr(
            'text', 'pluginname', 'Repository plugin name', array('size' => 40),
            'html', $functionsparams['outputnotifiction'],
            'static', null, '', get_string('oauth2serviceslink', 'repository_owncloud', $functionsparams['url']->out()),
            'text', 'pluginname', 'Repository plugin name', array('size' => 40),
            'static', 'pluginnamehelp', '', 'If you leave this empty the d... used.',
            'select', 'issuerid', get_string('chooseissuer', 'repository_owncloud'),
            $functionsparams['types']))->will($this->returnValue($return));
    }
    /**
     * Returns the param for the type_config_form.
     * @param $urgency
     * @param $message
     * @return array
     */
    protected function get_params_addelement_configform($urgency, $message) {
        global $OUTPUT;

        $addelementparams = array();
        $addelementparams['url'] = new \moodle_url('/admin/tool/oauth2/issuers.php');
        $issuers = core\oauth2\api::get_all_issuers();
        $types = array();
        $validissuers = [];
        foreach ($issuers as $issuer) {
            if (phpunit_util::call_internal_method($this->repo, "is_valid_issuer", array('issuer' => $issuer),
                'repository_owncloud')) {
                $validissuers[] = $issuer->get('name');
            }
            $types[$issuer->get('id')] = $issuer->get('name');
        }
        $addelementparams['types'] = $types;
        $addelementparams['validissuers'] = $validissuers;
        $issuervalidation = get_string($message, 'repository_owncloud', $types[ $this->issuer->get('id')]);
        $addelementparams['outputnotifiction'] = $OUTPUT->notification($issuervalidation, $urgency);
        return $addelementparams;
    }
    /**
     * Get private property
     *
     * @param $refclass name of the class
     * @param $propertyname name of the private property
     * @return ReflectionProperty the resulting reflection property.
     */
    protected function get_private_property($refclass, $propertyname) {
        $refclient = new ReflectionClass($refclass);
        $property = $refclient->getProperty($propertyname);
        $property->setAccessible(true);

        return $property;
    }
    /**
     * Helper method, which inserts a given owncloud mock object into the repository_owncloud object.
     *
     * @param $mock object mock object, which needs to be inserted.
     * @return ReflectionProperty the resulting reflection property.
     */
    protected function set_private_property($mock, $value) {
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
    protected function get_initialised_return_array() {
        $ret = array();
        $ret['dynload'] = true;
        $ret['nosearch'] = true;
        $ret['nologin'] = false;
        $ret['path'] = [
            [
                'name' => get_string('owncloud', 'repository_owncloud'),
                'path' => '',
            ]
        ];
        $ret['list'] = array();

        return $ret;
    }
}