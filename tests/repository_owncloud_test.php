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

    private $issuer = null;

    private $api = null;

    /*
    * @expectedException PHPUnit_Framework_Error_Warning
    */
    protected function setUp() {
        global $DB;
        $this->resetAfterTest(true);

        // First, create a owncloud repository type and instance.
        $generator = $this->getDataGenerator()->get_plugin_generator('repository_owncloud');

        $this->setAdminUser();
        $api = new \core\oauth2\api();

        $data = new stdClass();
        $data->name = "Service";
        $data->clientid = "Clientid";
        $data->clientsecret = "Secret";
        $data->loginscopes = "openid profile email";
        $data->loginscopesoffline = "openid profile email";
        $data->baseurl = "";
        $data->image = "aswdf";
        $issuer = $api->create_issuer($data);
        $this->issuer = $issuer;
        $this->api = $api;

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
    }

    /**
     * Dummy test for mock.
     */

    public function testReturnSelf()
    {
        // Create a stub for the SomeClass class.
        $stub = $this->createMock(\core\oauth2\issuer::class);

        // Configure the stub.
        $stub->method('get_endpoint_url')
            ->willReturn('www.filepath.de');

        // $stub->doSomething() returns $stub
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
     * Test weather the repo is disabled, however always returns true.
     */
    public function test_repo_creation() {

        $issuerid = get_config('owncloud', 'issuerid');

        // Config saves the right id
        $this->assertEquals($this->issuer->get('id'), $issuerid);

        // Function that is used in construct method returns the right id.
        $constructissuer = \core\oauth2\api::get_issuer($issuerid);
        $this->assertEquals($this->issuer->get('id'), $constructissuer->get('id'));

        $issuerenabled = $constructissuer->get('enabled');
        
        $this->assertEquals(true, $issuerenabled);
        $this->assertFalse($this->repo->disabled);
    }

    /**
     * Comparable to is_valid issuer()
     */
    public function test_endpoints(){
        $issuerid = get_config('owncloud', 'issuerid');

        // Config saves the right id
        $this->assertEquals($this->issuer->get('id'), $issuerid);

        // Function that is used in construct method returns the right id.
        $constructissuer = \core\oauth2\api::get_issuer($issuerid);
        // Creating endpoints

        // Executes the is valid_issuer function does return True.
        $endpointwebdav = false;
        $endpointocs = false;
        $endpointtoken = false;
        $endpointauth = false;
        $endpoints = \core\oauth2\api::get_endpoints($constructissuer);
        foreach ($endpoints as $endpoint) {
            $name = $endpoint->get('name');
            switch ($name) {
                case 'webdav_endpoint':
                    $endpointwebdav = true;
                    break;
                case 'ocs_endpoint':
                    $endpointocs = true;
                    break;
                case 'token_endpoint':
                    $endpointtoken = true;
                    break;
                case 'authorization_endpoint':
                    $endpointauth = true;
                    break;
            }
        }
        $boolean = $endpointwebdav && $endpointocs && $endpointtoken && $endpointauth;
        $this->assertTrue($boolean);
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
        $return = $this->api->create_endpoint($endpoint);
        return $return;
    }

}