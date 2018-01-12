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

    /** @var null|\repository_owncloud\test\access_controlled_link_manager_test test class for the access_controlled_link_manager. */
    public $linkmanager = null;

    /** @var null|\repository_owncloud\ocs_client The ocs_client used to send requests. */
    public $ocsmockclient = null;

    /** @var null|\core\oauth2\issuer which belongs to the repository_owncloud object. */
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
        $this->ocsmockclient = $this->getMockBuilder(repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $systemwebdavclient = $this->getMockBuilder(repository_owncloud\owncloud_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();

        $this->linkmanager = new \repository_owncloud\test\access_controlled_link_manager_test($this->ocsmockclient,
            $this->issuer, 'owncloud', $systemwebdavclient);

    }

    /**
     * Tests whether class can be constructed.
     */
    public function test_construction() {
        $mockclient = $this->getMockBuilder(\repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
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
        $username = 'user1';
        $params = [
            'path' => "/ambient.txt",
            'shareType' => \repository_owncloud\ocs_client::SHARE_TYPE_USER,
            'publicUpload' => false,
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
        $this->ocsmockclient->expects($this->once())->method('call')->with('create_share', $params)->will(
            $this->returnValue($expectedresponse));

        $clientmock = $this->createMock(\core\oauth2\client::class);
        $clientmock->expects($this->once())->method('get_userinfo')->willReturn(array('username' => 'user1'));
        $this->set_private_property($clientmock, 'systemoauthclient', $this->linkmanager);

        $result = $this->linkmanager->create_share_user_sysaccount("/ambient.txt");
        $xml = simplexml_load_string($expectedresponse);
        $expected = array();
        $expected['statuscode'] = $xml->meta->statuscode;
        $expected['shareid'] = $xml->data->id;
        $expected['filetarget'] = ((string)$xml->data[0]->file_target);
        $this->assertEquals($expected, $result);
    }
    /**
     * Test the delete_share_function. In case the request fails, the function throws an exception, however this
     * can not be tested in phpUnit since it is javascript.
     */
    public function test_delete_share_dataowner_sysaccount() {
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
        $this->ocsmockclient->expects($this->once())->method('call')->with('delete_share', $deleteshareparams)->will($this->returnValue($returnxml));
        $this->linkmanager->delete_share_dataowner_sysaccount($shareid, 'repository_owncloud');

    }

    /**
     * Function which test that create folder path does return the adequate results (path and success).
     * Additionally mock checks whether the right params are passed to the corresponding functions.
     */
    public function test_create_folder_path_folders_are_not_created() {

        $mocks = $this->set_up_mocks_for_create_folder_path(true, 'somename');
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

        // / in Contest is okay, number of context counts for number of iterations.
        $mocks = $this->set_up_mocks_for_create_folder_path(false, 'somename/more', true, 201);
        $this->set_private_property($mocks['mockclient'], 'systemwebdavclient', $this->linkmanager);
        $result = $this->linkmanager->create_folder_path_access_controlled_links($mocks['mockcontext'], "mod_resource",
            'content', 0);
        $expected = array();
        $expected['success'] = true;
        $expected['fullpath'] = '/somename/more/mod_resource/content/0';
        $this->assertEquals($expected, $result);
    }
    /**
     * Test whether the create_folder_path methode throws exception.
     */
    public function test_create_folder_path_folder_creation_fails() {

        $mocks = $this->set_up_mocks_for_create_folder_path(false, 'somename', true, 400);
        $this->set_private_property($mocks['mockclient'], 'systemwebdavclient', $this->linkmanager);
        $this->expectException(\repository_owncloud\request_exception::class);
        $this->linkmanager->create_folder_path_access_controlled_links($mocks['mockcontext'], "mod_resource",
            'content', 0);
    }
    /**
     * Helper function to generate mocks for testing create folder path.
     * @param bool $returnisdir
     * @param bool $callmkcol
     * @param int $returnmkcol
     * @return array
     */
    protected function set_up_mocks_for_create_folder_path($returnisdir, $returnestedcontext, $callmkcol = false, $returnmkcol = 201) {
        $mockcontext = $this->createMock(context_module::class);
        $mocknestedcontext = $this->createMock(context_module::class);

        $mockclient = $this->getMockBuilder(repository_owncloud\owncloud_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $parsedwebdavurl = parse_url($this->issuer->get_endpoint_url('webdav'));
        $webdavprefix = $parsedwebdavurl['path'];
        $dirstring = $webdavprefix . '/' . $returnestedcontext;
        $mockclient->expects($this->exactly(4))->method('is_dir')->with($this->logicalOr(
            $this->logicalOr($dirstring . '/mod_resource', $dirstring),
            $this->logicalOr($dirstring . '/mod_resource/content/0', $dirstring . '/mod_resource/content')))->willReturn($returnisdir);
        if ($callmkcol == true) {
            $mockclient->expects($this->exactly(4))->method('mkcol')->willReturn($returnmkcol);
        }
        $mockcontext->method('get_parent_contexts')->willReturn(array('1' => $mocknestedcontext));
        $mocknestedcontext->method('get_context_name')->willReturn($returnestedcontext);

        return array('mockcontext' => $mockcontext, 'mockclient' => $mockclient);
    }

    /**
     * Test whether the right methods from the webdavclient are called when the storage_folder is created.
     * This testcase might grow when owncloud has default folder to store downloaded files.
     * 1. Directory already exist -> no further action needed.
     */
    public function test_create_storage_folder_success() {
        // TODO: Replace with webdav_client when 3.3 is not longer supported.
        $mockwebdavclient = $this->createMock(\repository_owncloud\owncloud_client::class);
        $url = $this->issuer->get_endpoint_url('webdav');
        $parsedwebdavurl = parse_url($url);
        $webdavprefix = $parsedwebdavurl['path'];
        $mockwebdavclient->expects($this->once())->method('open')->willReturn(true);
        $mockwebdavclient->expects($this->once())->method('is_dir')->with($webdavprefix . 'myname')->willReturn(true);
        $mockwebdavclient->expects($this->once())->method('close');
        $this->linkmanager->create_storage_folder('myname', $mockwebdavclient);

    }
    /**
     * Test whether the right methods from the webdavclient are called when the storage_folder is created.
     * This testcase might grow when owncloud has default folder to store downloaded files.
     * 2. Directory does not exist. It is created with mkcol and returns a success.
     *
     */
    public function test_create_storage_folder_success_mkcol() {
        // TODO: Replace with webdav_client when 3.3 is not longer supported.
        $mockwebdavclient = $this->createMock(\repository_owncloud\owncloud_client::class);
        $url = $this->issuer->get_endpoint_url('webdav');
        $parsedwebdavurl = parse_url($url);
        $webdavprefix = $parsedwebdavurl['path'];
        $mockwebdavclient->expects($this->once())->method('open')->willReturn(true);
        $mockwebdavclient->expects($this->once())->method('is_dir')->with($webdavprefix . 'myname')->willReturn(false);
        $mockwebdavclient->expects($this->once())->method('mkcol')->with($webdavprefix . 'myname')->willReturn(201);
        $mockwebdavclient->expects($this->once())->method('close');

        $this->linkmanager->create_storage_folder('myname', $mockwebdavclient);
    }
    /**
     * Test whether the right methods from the webdavclient are called when the storage_folder is created.
     * This testcase might grow when owncloud has default folder to store downloaded files.
     * 3. Request to create Folder fails.
     */
    public function test_create_storage_folder_failure() {
        // TODO: Replace with webdav_client when 3.3 is not longer supported.
        $mockwebdavclient = $this->createMock(\repository_owncloud\owncloud_client::class);
        $url = $this->issuer->get_endpoint_url('webdav');
        $parsedwebdavurl = parse_url($url);
        $webdavprefix = $parsedwebdavurl['path'];
        $mockwebdavclient->expects($this->once())->method('open')->willReturn(true);
        $mockwebdavclient->expects($this->once())->method('is_dir')->with($webdavprefix . 'myname')->willReturn(false);
        $mockwebdavclient->expects($this->once())->method('mkcol')->with($webdavprefix . 'myname')->willReturn(400);

        $this->expectException(\repository_owncloud\request_exception::class);
        $this->linkmanager->create_storage_folder('myname', $mockwebdavclient);
    }
    /**
     * Test whether the webdav client gets the right params and whether function differentiates between move and copy.
     */
    public function test_transfer_file_to_path_copyfile() {
        // Initialize params.
        $parsedwebdavurl = parse_url($this->issuer->get_endpoint_url('webdav'));
        $webdavprefix = $parsedwebdavurl['path'];
        $srcpath = 'sourcepath';
        $dstpath = "destinationpath/another/path";

        // Mock the Webdavclient and set expected methods.
        // TODO: Replace with webdav_client when 3.3 is not longer supported.
        $systemwebdavclientmock = $this->createMock(\repository_owncloud\owncloud_client::class);
        $systemwebdavclientmock->expects($this->once())->method('open')->willReturn(true);
        $systemwebdavclientmock->expects($this->once())->method('copy_file')->with($webdavprefix . $srcpath,
            $webdavprefix . $dstpath . '/' . $srcpath, true)->willReturn(201);
        $this->set_private_property($systemwebdavclientmock, 'systemwebdavclient', $this->linkmanager);

        // Call of function.
        $result = $this->linkmanager->transfer_file_to_path($srcpath, $dstpath, 'copy');

        $this->assertEquals(201, $result);
    }
    /**
     * Test whether the webdav client gets the right params and whether function differentiates between move and copy.
     *
     */
    public function test_transfer_file_to_path_copyfile_movefile() {
        // Initialize params.
        $parsedwebdavurl = parse_url($this->issuer->get_endpoint_url('webdav'));
        $webdavprefix = $parsedwebdavurl['path'];
        $srcpath = 'sourcepath';
        $dstpath = "destinationpath/another/path";

        $systemwebdavclientmock = $this->createMock(\repository_owncloud\owncloud_client::class);

        $systemwebdavclientmock->expects($this->once())->method('open')->willReturn(true);
        $this->set_private_property($systemwebdavclientmock, 'systemwebdavclient', $this->linkmanager);
        $webdavclientmock = $this->createMock(\repository_owncloud\owncloud_client::class);

        $webdavclientmock->expects($this->once())->method('move')->with($webdavprefix . $srcpath,
            $webdavprefix . $dstpath . '/' . $srcpath, false)->willReturn(201);
        $result = $this->linkmanager->transfer_file_to_path($srcpath, $dstpath, 'move', $webdavclientmock);
        $this->assertEquals(201, $result);
    }

    // TODO missing functions : test_create_system_dav() test_get_share_information_from_shareid(),
    // TODO get_shares_from_path($storedfile, $username) test_get_shares_from_path()
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