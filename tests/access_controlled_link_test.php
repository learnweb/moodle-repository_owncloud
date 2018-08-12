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

global $CFG;
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Class repository_owncloud_testcase
 * @group repository_owncloud
 * @copyright  2017 Project seminar (Learnweb, University of Münster)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_owncloud_access_controlled_link_testcase extends advanced_testcase {

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
            'pluginname' => 'ownCloud',
            'controlledlinkfoldername' => 'Moodlefiles',
            'supportedreturntypes' => 'both',
            'defaultreturntype' => FILE_INTERNAL,
        ]);
        // At last, create a repository_owncloud object from the instance id.
        $this->repo = new repository_owncloud($instance->id);
        $this->repo->options['typeid'] = $reptype->id;
        $this->repo->options['sortorder'] = 1;
        $this->resetAfterTest(true);
    }

    /**
     * The reference_file_selected() methode is called every time a FILE_CONTROLLED_LINK is chosen for upload.
     * Since the function is very long the private function are tested separately, and merely the abortion of the
     * function are tested.
     *
     */
    public function test_reference_file_selected_error() {
        $this->repo->disabled = true;
        $this->expectException(\repository_exception::class);
        $this->repo->reference_file_selected('', context_system::instance(), '', '', '');

        $this->repo->disabled = false;
        $this->expectException(\repository_exception::class);
        $this->expectExceptionMessage('Cannot connect as system user');
        $this->repo->reference_file_selected('', context_system::instance(), '', '', '');

        $mock = $this->createMock(\core\oauth2\client::class);
        $mock->expects($this->once())->method('get_system_oauth_client')->with($this->issuer)->willReturn(true);

        $this->expectException(\repository_exception::class);
        $this->expectExceptionMessage('Cannot connect as current user');
        $this->repo->reference_file_selected('', context_system::instance(), '', '', '');

        $this->repo->expects($this->once())->method('get_user_oauth_client')->willReturn(true);
        $this->expectException(\repository_exception::class);
        $this->expectExceptionMessage('cannotdownload');
        $this->repo->reference_file_selected('', context_system::instance(), '', '', '');

        $this->repo->expects($this->once())->method('get_user_oauth_client')->willReturn(true);
        $this->expectException(\repository_exception::class);
        $this->expectExceptionMessage('cannotdownload');
        $this->repo->reference_file_selected('', context_system::instance(), '', '', '');

        $this->repo->expects($this->once())->method('get_user_oauth_client')->willReturn(true);
        $this->repo->expects($this->once())->method('copy_file_to_path')->willReturn(array('statuscode' =>
            array('success' => 400)));
        $this->expectException(\repository_exception::class);
        $this->expectExceptionMessage('Could not copy file');
        $this->repo->reference_file_selected('', context_system::instance(), '', '', '');

        $this->repo->expects($this->once())->method('get_user_oauth_client')->willReturn(true);
        $this->repo->expects($this->once())->method('copy_file_to_path')->willReturn(array('statuscode' =>
            array('success' => 201)));
        $this->repo->expects($this->once())->method('delete_share_dataowner_sysaccount')->willReturn(
            array('statuscode' => array('success' => 400)));
        $this->expectException(\repository_exception::class);
        $this->expectExceptionMessage('Share is still present');
        $this->repo->reference_file_selected('', context_system::instance(), '', '', '');

        $this->repo->expects($this->once())->method('get_user_oauth_client')->willReturn(true);
        $this->repo->expects($this->once())->method('copy_file_to_path')->willReturn(array('statuscode' =>
            array('success' => 201)));
        $this->repo->expects($this->once())->method('delete_share_dataowner_sysaccount')->willReturn(
            array('statuscode' => array('success' => 100)));
        $filereturn = array();
        $filereturn->link = 'some/fullpath' . 'some/target/path';
        $filereturn->name = 'mysource';
        $filereturn->usesystem = true;
        $filereturn = json_encode($filereturn);
        $return = $this->repo->reference_file_selected('mysource', context_system::instance(), '', '', '');
        $this->assertEquals($filereturn, $return);
    }

    /**
     * Helper function to generate mocks for testing create folder path.
     * @param bool $returnisdir
     * @param bool $callmkcol
     * @param int $returnmkcol
     * @return array
     */
    protected function set_up_mocks_for_create_folder_path($returnisdir, $callmkcol = false, $returnmkcol = 201) {
        $mockcontext = $this->createMock(context_module::class);
        $mocknestedcontext = $this->createMock(context_module::class);
        $mockclient = $this->getMockBuilder(
            repository_owncloud\owncloud_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();

        $parsedwebdavurl = parse_url($this->issuer->get_endpoint_url('webdav'));
        $webdavprefix = $parsedwebdavurl['path'];
        $mockclient->expects($this->exactly(4))->method('is_dir')->with($this->logicalOr(
            $this->logicalOr($webdavprefix . '/somename/mod_resource', $webdavprefix . '/somename'),
            $this->logicalOr($webdavprefix . '/somename/mod_resource/content/0', $webdavprefix .
                '/somename/mod_resource/content')))->willReturn($returnisdir);
        if ($callmkcol == true) {
            $mockclient->expects($this->exactly(4))->method('mkcol')->willReturn($returnmkcol);
        }
        $mockcontext->method('get_parent_contexts')->willReturn(array('1' => $mocknestedcontext));
        $mocknestedcontext->method('get_context_name')->willReturn('somename');
        return array('mockcontext' => $mockcontext, 'mockclient' => $mockclient);
    }

    /**
     * Test the send_file function.
     */
    public function test_send_file_errors() {
        $this->set_private_property('', 'client');
        $this->expectException(repository_owncloud\request_exception::class);
        $this->expectExceptionMessage(get_string('contactadminwith', 'repository_owncloud',
            'The OAuth client could not be connected.'));
        $this->repo->send_file('', '', '', '');

        // Testing whether the mock up appears is topic to behat.
        $mock = $this->createMock(\core\oauth2\client::class);
        $mock->expects($this->once())->method('is_logged_in')->willReturn(true);
        $this->repo->send_file('', '', '', '');

        // Checks that setting for foldername are used.
        $mock->expects($this->once())->method('is_dir')->with('Moodlefiles')->willReturn(false);
        // In case of false as return value mkcol is called to create the folder.
        $parsedwebdavurl = parse_url($this->issuer->get_endpoint_url('webdav'));
        $webdavprefix = $parsedwebdavurl['path'];
        $mock->expects($this->once())->method('mkcol')->with(
            $webdavprefix . 'Moodlefiles')->willReturn(400);
        $this->expectException(\repository_owncloud\request_exception::class);
        $this->expectExceptionMessage(get_string('requestnotexecuted', 'repository_owncloud'));
        $this->repo->send_file('', '', '', '');

        $expectedresponse = <<<XML
<?xml version="1.0"?>
<ocs>
 <meta>
  <status>ok</status>
  <statuscode>100</statuscode>
  <message/>
 </meta>
 <data>
  <element>
   <id>6</id>
   <share_type>0</share_type>
   <uid_owner>tech</uid_owner>
   <displayname_owner>tech</displayname_owner>
   <permissions>19</permissions>
   <stime>1511877999</stime>
   <parent/>
   <expiration/>
   <token/>
   <uid_file_owner>tech</uid_file_owner>
   <displayname_file_owner>tech</displayname_file_owner>
   <path>/System/Category Miscellaneous/Course Example Course/File morefiles/mod_resource/content/0/merge.txt</path>
   <item_type>file</item_type>
   <mimetype>text/plain</mimetype>
   <storage_id>home::tech</storage_id>
   <storage>4</storage>
   <item_source>824</item_source>
   <file_source>824</file_source>
   <file_parent>823</file_parent>
   <file_target>/merge (3).txt</file_target>
   <share_with>user2</share_with>
   <share_with_displayname>user1</share_with_displayname>
   <mail_send>0</mail_send>
  </element>
  <element>
   <id>5</id>
   <share_type>0</share_type>
   <uid_owner>tech</uid_owner>
   <displayname_owner>tech</displayname_owner>
   <permissions>19</permissions>
   <stime>1511877999</stime>
   <parent/>
   <expiration/>
   <token/>
   <uid_file_owner>tech</uid_file_owner>
   <displayname_file_owner>tech</displayname_file_owner>
   <path>/System/Category Miscellaneous/Course Example Course/File morefiles/mod_resource/content/0/merge.txt</path>
   <item_type>file</item_type>
   <mimetype>text/plain</mimetype>
   <storage_id>home::tech</storage_id>
   <storage>4</storage>
   <item_source>824</item_source>
   <file_source>824</file_source>
   <file_parent>823</file_parent>
   <file_target>/merged (3).txt</file_target>
   <share_with>user1</share_with>
   <share_with_displayname>user1</share_with_displayname>
   <mail_send>0</mail_send>
  </element>
 </data>
</ocs>
XML;

        // Checks that setting for foldername are used.
        $mock->expects($this->once())->method('is_dir')->with('Moodlefiles')->willReturn(true);
        // In case of true as return value mkcol is not called  to create the folder.
        $shareid = 5;

        $mockocsclient = $this->getMockBuilder(
            \repository_owncloud\ocs_client::class)->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $mockocsclient->expects($this->exactly(2))->method('call')->with('get_information_of_share',
            array('share_id' => $shareid))->will($this->returnValue($expectedresponse));
        $this->set_private_property($mock, 'ocsclient');
        $this->repo->expects($this->once())->method('move_file_to_folder')->with('/merged (3).txt', 'Moodlefiles',
            $mock)->willReturn(array('success' => 201));

        $this->repo->send_file('', '', '', '');

        // Create test for statuscode 403.

        // Checks that setting for foldername are used.
        $mock->expects($this->once())->method('is_dir')->with('Moodlefiles')->willReturn(true);
        // In case of true as return value mkcol is not called to create the folder.
        $shareid = 5;
        $mockocsclient = $this->getMockBuilder(\repository_owncloud\ocs_client::class
        )->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $mockocsclient->expects($this->exactly(1))->method('call')->with('get_shares',
            array('path' => '/merged (3).txt', 'reshares' => true))->will($this->returnValue($expectedresponse));
        $mockocsclient->expects($this->exactly(1))->method('call')->with('get_information_of_share',
            array('share_id' => $shareid))->will($this->returnValue($expectedresponse));
        $this->set_private_property($mock, 'ocsclient');
        $this->repo->expects($this->once())->method('move_file_to_folder')->with('/merged (3).txt', 'Moodlefiles',
            $mock)->willReturn(array('success' => 201));
        $this->repo->send_file('', '', '', '');
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
}