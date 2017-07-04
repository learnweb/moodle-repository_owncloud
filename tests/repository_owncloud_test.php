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

class repository_owncloud_testcase extends advanced_testcase {

    /** @var null|repository_owncloud the repository_owncloud object, which the tests are run on. */
    private $repo = null;

    private $issuer = null;

    protected function setUp() {
        global $DB;
        $this->resetAfterTest(true);

        // Params for the config form.
        $typeparams = array('type' => 'owncloud', 'visible' => 0, 'issuerid' => 1, 'validissuers' => '');

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
        $data->baseurl = "www.baseurl.de";
        $data->image = "";
        $issuer = $api->create_issuer($data);

        $endpoint = new stdClass();
        $endpoint->name = "token";
        $endpoint->url = "https://www.someurl.de";
        $endpoint->issuerid = $issuer->get('id');
        $endpoint = $api->create_endpoint($endpoint);
        $generator->test_create_preparation();
        $reptype = $generator->create_type($typeparams);

        // Then insert a name for the instance into the database.
        /*$instance = $DB->get_record('repository_instances', array('typeid' => $reptype->id));
        $DB->update_record('repository_instances', (object) array('id' => $instance->id, 'name' => 'ownCloud'));

        // At last, create a repository_owncloud object from the instance id.
        // TODO: Function is called on null, for some reason the repo is not created.
        $this->repo = new repository_owncloud($instance->id);
        $this->repo->options['typeid'] = $reptype->id;*/
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
        $this->assertSame('www.filepath.de', $stub->get_endpoint_url("what"));
    }
}