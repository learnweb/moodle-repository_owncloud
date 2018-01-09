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
        $this->expectException('repository_owncloud\request_exception');
        $this->expectExceptionMessage('A request to owncloud has failed. The requested action could not be executed.' .
            ' In case this happens frequently please contact the side administrator with the following additional information:'
            . '<br>"<i>The systemaccount could not be connected.</i>"');
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
        $this->expectException('repository_owncloud\request_exception');
        $this->expectExceptionMessage('A request to owncloud has failed. The requested action could not be executed.' .
            ' In case this happens frequently please contact the side administrator with the following additional information:'
            . '<br>"<i>The systemaccount could not be connected.</i>"');

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

        $this->expectException('repository_owncloud\request_exception');
        $this->expectExceptionMessage('A request to owncloud has failed. The requested action could not be executed.' .
            ' In case this happens frequently please contact the side administrator with the following additional information:'
            . '<br>"<i>The systemaccount could not be connected.</i>"');

        $this->linkmanager = new \repository_owncloud\access_controlled_link_manager($mockclient, $this->issuer, 'owncloud');
        $mockclient->expects($this->once())->method('call')->with('delete_share', $deleteshareparams)->will($this->returnValue($returnxml));

        $result = $this->linkmanager->delete_share_dataowner_sysaccount($shareid, 'repository_owncloud');
        $xml = simplexml_load_string($returnxml);
        $expected = $xml->meta->statuscode;
        $this->assertEquals($expected, $result);
    }

    /**
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
        $this->expectException('repository_owncloud\request_exception');
        $this->expectExceptionMessage('A request to owncloud has failed. The requested action could not be executed.' .
            ' In case this happens frequently please contact the side administrator with the following additional information:'
            . '<br>"<i>The systemaccount could not be connected.</i>"');
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