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
 * @group repo_owncloud
 * @group repo_owncloud_manager
 * @copyright  2017 Project seminar (Learnweb, University of Münster)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_owncloud_access_controlled_link_manager_testcase extends advanced_testcase {

    /** @var null|\repository_owncloud the repository_owncloud object, which the tests are run on. */
    public $linkmanager = null;

    /** @var null|\core\oauth2\issuer which belongs to the repository_owncloud object.*/
    public $issuer = null;

    /**
     * SetUp to create an repository instance.
     */
    protected function setUp() {
        $this->resetAfterTest(true);

        // Admin is necessary to create issuer object.
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('repository_owncloud');
        $this->issuer = $generator->test_create_issuer();
        $generator->test_create_endpoints($this->issuer->get('id'));
    }

    /**
     * Tests whether class can be constructed.
     */
    public function test_construction() {
        $mockclient = $this->getMockBuilder(\repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone(
        )->getMock();
        // ExpectedException is here needed since the contructor of the linkmanager request whether the systemaccount is
        // Logged in. This is checked in \core\oauth2\api.php get_system_oauth_client(l.293)
        // However, since the client is newly created in the method and the method is static phpunit is not able to mock it.
        $this->expectException('repository_owncloud\request_exception');
        $this->linkmanager = new \repository_owncloud\access_controlled_link_manager($mockclient, $this->issuer, 'owncloud');

        $mock = $this->createMock(\core\oauth2\client::class);
        $mock->expects($this->once())->method('get_system_oauth_client')->with($this->issuer)->willReturn(true);
        $this->linkmanager = new \repository_owncloud\access_controlled_link_manager($mockclient, $this->issuer, 'owncloud');
    }
    /**
     * Function to test the private function create_share_user_sysaccount.
     */
    public function test_create_share_user_sysaccount_user_shares() {
        $dateofexpiration = time() + 604800;
        $username = 'user1';
        $params = [
            'path' => "/ambient.txt",
            'shareType' => \repository_owncloud\ocs_client::SHARE_TYPE_USER,
            'publicUpload' => false,
            'expiration' => $dateofexpiration,
            'shareWith' => $username,
        ];
        $expectedresponse = <<<XML
<?xml version="1.0"?>
<ocs>
 <meta>
  <status>ok</status>
  <statuscode>100</statuscode>
  <message/>
 </meta>
 <data>
  <id>207</id>
  <share_type>0</share_type>
  <uid_owner>user1</uid_owner>
  <displayname_owner>user1</displayname_owner>
  <permissions>19</permissions>
  <stime>1511532198</stime>
  <parent/>
  <expiration/>
  <token/>
  <uid_file_owner>user1</uid_file_owner>
  <displayname_file_owner>user1</displayname_file_owner>
  <path>/ambient.txt</path>
  <item_type>file</item_type>
  <mimetype>text/plain</mimetype>
  <storage_id>home::user1</storage_id>
  <storage>3</storage>
  <item_source>545</item_source>
  <file_source>545</file_source>
  <file_parent>20</file_parent>
  <file_target>/ambient.txt</file_target>
  <share_with>tech</share_with>
  <share_with_displayname>tech</share_with_displayname>
  <mail_send>0</mail_send>
 </data>
</ocs>
XML;

        $mockclient = $this->getMockBuilder(\repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone(
        )->getMock();
        // ExpectedException is here needed since the contructor of the linkmanager request whether the systemaccount is
        // Logged in. This is checked in \core\oauth2\api.php get_system_oauth_client(l.293)
        // However, since the client is newly created in the method and the method is static phpunit is not able to mock it.
        $this->expectException('repository_owncloud\request_exception');

        $this->linkmanager = new \repository_owncloud\access_controlled_link_manager($mockclient, $this->issuer, 'owncloud');

        $mockclient->expects($this->once())->method('call')->with('create_share', $params)->will(
            $this->returnValue($expectedresponse));
        $result = $this->linkmanager->create_share_user_sysaccount("/ambient.txt", 604800, true, 'owncloud');
        $xml = simplexml_load_string($expectedresponse);
        $expected = array();
        $expected['statuscode'] = $xml->meta->statuscode;
        $expected['shareid'] = $xml->data->id;
        $expected['fileid'] = $xml->data->item_source;
        $expected['filetarget'] = ((string)$xml->data[0]->file_target);
        $this->assertEquals($expected, $result);
    }
    /**
     * Test the delete share function.
     */
    public function test_delete_share_dataowner_sysaccount() {
        $mockclient = $this->getMockBuilder(repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $shareid = 5;
        $deleteshareparams = [
            'share_id' => $shareid
        ];
        $returnxml = <<<XML
<?xml version="1.0"?>
<ocs>
    <meta>
    <status>ok</status>
    <statuscode>100</statuscode>
    <message/>
    </meta>
    <data/>
</ocs>
XML;
        // ExpectedException is here needed since the contructor of the linkmanager request whether the systemaccount is
        // Logged in. This is checked in \core\oauth2\api.php get_system_oauth_client(l.293)
        // However, since the client is newly created in the method and the method is static phpunit is not able to mock it.

        $this->expectException('repository_owncloud\request_exception');
        $this->linkmanager = new \repository_owncloud\access_controlled_link_manager($mockclient, $this->issuer, 'owncloud');
        $mockclient->expects($this->once())->method('call')->with('delete_share', $deleteshareparams)->will($this->returnValue($returnxml));

        $result = $this->linkmanager->delete_share_dataowner_sysaccount($shareid, 'repository_owncloud');
        $xml = simplexml_load_string($returnxml);
        $expected = $xml->meta->statuscode;
        $this->assertEquals($expected, $result);
    }

    /**
     * Test whether the webdav client gets the right params and whether function differentiates between move and copy.
     *
     * @throws \repository_owncloud\configuration_exception
     * @throws \repository_owncloud\request_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function test_transfer_file_to_path() {
        $mockclient = $this->getMockBuilder(repository_owncloud\owncloud_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $ocsmockclient = $this->getMockBuilder(repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();

        $parsedwebdavurl = parse_url($this->issuer->get_endpoint_url('webdav'));
        $webdavprefix = $parsedwebdavurl['path'];
        $srcpath = 'sourcepath';
        $dstpath = "destinationpath/another/path";
        // ExpectedException is here needed since the contructor of the linkmanager request whether the systemaccount is
        // Logged in. This is checked in \core\oauth2\api.php get_system_oauth_client(l.293)
        // However, since the client is newly created in the method and the method is static phpunit is not able to mock it.
        $this->expectException('repository_owncloud\request_exception');

        $this->linkmanager = new \repository_owncloud\access_controlled_link_manager($ocsmockclient, $this->issuer, 'owncloud');

        $fakeaccesstoken = new stdClass();
        $fakeaccesstoken->token = "fake access token";
        $oauthmock = $this->createMock(\core\oauth2\client::class);

        $oauthmock->expects($this->once())->method('get_accesstoken')->will($this->returnValue($fakeaccesstoken));
        $this->set_private_property($oauthmock, 'client', \repository_owncloud\access_controlled_link_manager::class);

        $this->linkmanager->create_system_dav();
        $mockclient->expects($this->once())->method('copy_file')->with($webdavprefix . $srcpath,
            $webdavprefix . $dstpath . '/' . $srcpath, true)->willReturn(201);
        $result = $this->linkmanager->transfer_file_to_path($srcpath, $dstpath, 'copy');
        $expected = array();
        $expected['success'] = 201;
        $this->assertEquals($expected, $result);
        $mockclient->expects($this->once())->method('move')->with($webdavprefix . $srcpath,
            $webdavprefix . $dstpath . '/' . $srcpath, false)->willReturn(201);
        $result = $this->linkmanager->transfer_file_to_path($srcpath, $dstpath, 'move');
        $expected = array();
        $expected['success'] = 201;
        $this->assertEquals($expected, $result);
    }

    /**
     * Function which test that create folder path does return the adequate results (path and success).
     * Additionally mock checks whether the right params are passed to the corresponding functions.
     */
    public function test_create_folder_path_folders_are_not_created() {
        $ocsmockclient = $this->getMockBuilder(repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();

        $mocks = $this->set_up_mocks_for_create_folder_path(true, 0, 4);
        // ExpectedException is here needed since the contructor of the linkmanager request whether the systemaccount is
        // Logged in. This is checked in \core\oauth2\api.php get_system_oauth_client(l.293)
        // However, since the client is newly created in the method and the method is static phpunit is not able to mock it.
        $this->expectException('repository_owncloud\request_exception');

        $this->linkmanager = new \repository_owncloud\access_controlled_link_manager($ocsmockclient, $this->issuer, 'owncloud');
        $this->set_private_property($mocks['mockclient'], 'systemwebdavclient', $this->linkmanager);

        $result = $this->linkmanager->create_folder_path_access_controlled_links($mocks['mockcontext'], "mod_resource",
            'content', 0);
        $expected = array();
        $expected['success'] = true;
        $expected['fullpath'] = '/somename/mod_resource/content/0';
        $this->assertEquals($expected, $result);
    }
    /**
     * Function which test that create folder path does return the adequate results (path and success).
     * Additionally mock checks whether the right params are passed to the corresponding functions.
     */
    public function test_create_folder_path_folders_are_created() {
        $ocsmockclient = $this->getMockBuilder(repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();

        $mocks = $this->set_up_mocks_for_create_folder_path(false, 0, 0, true, 201);
        // ExpectedException is here needed since the contructor of the linkmanager request whether the systemaccount is
        // Logged in. This is checked in \core\oauth2\api.php get_system_oauth_client(l.293)
        // However, since the client is newly created in the method and the method is static phpunit is not able to mock it.
        $this->expectException('repository_owncloud\request_exception');

        $this->linkmanager = new \repository_owncloud\access_controlled_link_manager($ocsmockclient, $this->issuer, 'owncloud');
        $this->set_private_property($mocks['mockclient'], 'systemwebdavclient', $this->linkmanager);
        $result = $this->linkmanager->create_folder_path_access_controlled_links($mocks['mockcontext'], "mod_resource",
            'content', 0);
        $expected = array();
        $expected['success'] = true;
        $expected['fullpath'] = '/somename/mod_resource/content/0';
        $this->assertEquals($expected, $result);
    }
    /**
     * Function which test that create folder path does return the adequate results (path and success).
     * Additionally mock checks whether the right params are passed to the corresponding functions.
     */
    public function test_create_folder_path_folder_creation_fails() {
        $ocsmockclient = $this->getMockBuilder(repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();

        // ExpectedException is here needed since the contructor of the linkmanager request whether the systemaccount is
        // Logged in. This is checked in \core\oauth2\api.php get_system_oauth_client(l.293)
        // However, since the client is newly created in the method and the method is static phpunit is not able to mock it.
        $this->expectException('repository_owncloud\request_exception');

        $this->linkmanager = new \repository_owncloud\access_controlled_link_manager($ocsmockclient, $this->issuer, 'owncloud');
        $mocks = $this->set_up_mocks_for_create_folder_path(false, 4, 0, true, 400);

        $this->set_private_property($mocks['mockclient'], 'systemwebdavclient', $this->linkmanager);
        $result = $this->linkmanager->create_folder_path_access_controlled_links($mocks['mockcontext'], "mod_resource",
            'content', 0);
        $expected = array();
        $expected['success'] = false;
        $expected['fullpath'] = '/somename/mod_resource/content/0';
        $this->assertEquals($expected, $result);
    }
    /**
     * Helper function to generate mocks for testing create folder path.
     * @param bool $returnisdir
     * @param bool $callmkcol
     * @param int $returnmkcol
     * @return array
     */
    protected function set_up_mocks_for_create_folder_path($returnisdir, $firstcount, $secondcount, $callmkcol = false, $returnmkcol = 201) {
        $mockcontext = $this->createMock(context_module::class);
        $mocknestedcontext = $this->createMock(context_module::class);
        $mockclient = $this->getMockBuilder(repository_owncloud\owncloud_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $parsedwebdavurl = parse_url($this->issuer->get_endpoint_url('webdav'));
        $webdavprefix = $parsedwebdavurl['path'];
        $mockclient->expects($this->exactly($firstcount))->method('is_dir')->with($this->logicalOr(
            $this->logicalOr($webdavprefix . '/somename/mod_resource', $webdavprefix . '/somename'),
            $this->logicalOr($webdavprefix . '/somename/mod_resource/content/0', $webdavprefix . '/somename/mod_resource/content')))->willReturn($returnisdir);
        if ($callmkcol == true) {
            $mockclient->expects($this->exactly($secondcount))->method('mkcol')->willReturn($returnmkcol);
        }
        $mockcontext->method('get_parent_contexts')->willReturn(array('1' => $mocknestedcontext));
        $mocknestedcontext->method('get_context_name')->willReturn('somename');
        return array('mockcontext' => $mockcontext, 'mockclient' => $mockclient);
    }

    /** Test whether the systemwebdav client is constructed correctly. Port is set to 443 in case of https, to 80 in
     * case of http and exception is thrown when endpoint does not exist.
     * @throws \repository_owncloud\configuration_exception
     * @throws coding_exception
     */
    public function test_create_system_dav() {
        $ocsmockclient = $this->getMockBuilder(repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $oauthclientmock = $this->getMockBuilder(\core\oauth2\client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $fakeaccesstoken = new stdClass();
        $fakeaccesstoken->token = "fake access token";

        // ExpectedException is here needed since the contructor of the linkmanager request whether the systemaccount is
        // Logged in. This is checked in \core\oauth2\api.php get_system_oauth_client(l.293)
        // However, since the client is newly created in the method and the method is static phpunit is not able to mock it.
        $this->expectException('repository_owncloud\request_exception');

        $this->linkmanager = new \repository_owncloud\access_controlled_link_manager($ocsmockclient, $this->issuer, 'owncloud');
        $oauthclientmock->expects($this->once())->method('get_accesstoken')->willReturn($fakeaccesstoken);
        $this->set_private_property($oauthclientmock, 'systemoauthclient', $this->linkmanager);
        $dav = $this->linkmanager->create_system_dav();

        $this->assertEquals($dav->port, 443);
        $this->assertEquals($dav->debug, false);

        $endpoints = \core\oauth2\api::get_endpoints($this->issuer);
        $ids = array();
        foreach ($endpoints as $endpoint) {
            $name = $endpoint->get('name');
            if ($name === 'webdav') {
                $ids[$endpoint->get('id')] = $endpoint->get('id');
            }
        }
        foreach ($ids as $id) {
            core\oauth2\api::delete_endpoint($id);
        }

        $endpoint = new stdClass();
        $endpoint->name = "webdav_endpoint";
        $endpoint->url = 'http://www.default.test/webdav/index.php';
        $endpoint->issuerid = $this->issuer->get('id');
        \core\oauth2\api::create_endpoint($endpoint);
        $oauthclientmock->expects($this->once())->method('get_accesstoken')->willReturn($fakeaccesstoken);
        $dav = $this->linkmanager->create_system_dav();
        $this->assertEquals($dav->port, 80);
        $this->assertEquals($dav->debug, false);

        $endpoints = \core\oauth2\api::get_endpoints($this->issuer);
        $ids = array();
        foreach ($endpoints as $endpoint) {
            $name = $endpoint->get('name');
            if ($name === 'webdav') {
                $ids[$endpoint->get('id')] = $endpoint->get('id');
            }
        }
        foreach ($ids as $id) {
            core\oauth2\api::delete_endpoint($id);
        }
        $this->expectException(\repository_owncloud\configuration_exception::class);
        $this->linkmanager->create_system_dav();
    }


    /**
     * Helper method, which inserts a given mock value into the repository_owncloud object.
     *
     * @param mixed $value mock value that will be inserted.
     * @param string $propertyname name of the private property.
     * @return ReflectionProperty the resulting reflection property.
     */
    protected function set_private_property($value, $propertyname, $class) {
        $refclient = new ReflectionClass($class);
        $private = $refclient->getProperty($propertyname);
        $private->setAccessible(true);
        $private->setValue($class, $value);

        return $private;
    }
}