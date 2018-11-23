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
 * Test functionality related to the migration to repository_nextcloud.
 *
 * @package     repository_owncloud
 * @copyright  2018 Jan Dageförde (University of Münster)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Test functionality related to the migration to repository_nextcloud.
 * @group repository_owncloud
 * @copyright  2018 Jan Dageförde (University of Münster)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_owncloud_migration_testcase extends advanced_testcase {

    /** @var stdClass repository type record */
    private $repositorytype;

    /** @var repository_owncloud_generator */
    private $generator;

    /**
     * @var \core\oauth2\issuer
     */
    private $issuer;

    protected function setUp() {
        $this->resetAfterTest(true);

        // Admin is neccessary to create api and issuer objects.
        $this->setAdminUser();

        $this->generator = $this->getDataGenerator()->get_plugin_generator('repository_owncloud');

        // Generic issuer.
        $this->issuer = $this->generator->test_create_issuer();

        // Params for the config form.
        $this->repositorytype = $this->generator->create_type([
            'visible' => 1,
            'enableuserinstances' => 0,
            'enablecourseinstances' => 0,
        ]);
    }

    /**
     * get_file has two different behaviours. The "usual" (non-migration) one is tested by repository_owncloud_lib_testcase.
     * The migration one passes a string containing a reference to get_file and expects it to download the file from the given URL.
     *
     * @see repository_owncloud_lib_testcase::test_get_file
     */
    public function test_get_file_migration() {
        global $CFG;
        if ($CFG->branch < 36) {
            // No functionality in Moodle 3.5 and lower.
            return;
        }
        $instance = $this->generator->create_instance([
            'issuerid' => $this->issuer->get('id'),
            'pluginname' => 'ownCloud',
            'controlledlinkfoldername' => 'Moodlefiles',
            'supportedreturntypes' => 'both',
            'defaultreturntype' => FILE_INTERNAL,
        ]);

        // At last, create a repository_owncloud object from the instance id.
        $repo = new repository_owncloud($instance->id);
        $repo->options['typeid'] = $this->repositorytype->id;
        $repo->options['sortorder'] = 1;

        // Fictitious path to a publicly shared file.
        $sharepath = 'https://test.local/manual.txt';

        // Should never use WebDAV to download because we are not accessing the file via WebDAV.
        $mock = $this->createMock(repository_owncloud\owncloud_client::class);
        $mock->expects($this->never())->method('open');
        // Substitute WebDAV client with mock.
        $refclient = new ReflectionClass($repo);
        $private = $refclient->getProperty('dav');
        $private->setAccessible(true);
        $private->setValue($repo, $mock);

        // Mock a successful curl request.
        curl::mock_response(true);

        // Actually call the method under test.
        $reference = new stdClass();
        $reference->type = "FILE_REFERENCE";
        $reference->link = $sharepath;
        $result = $repo->get_file(json_encode($reference));

        // Check whether result has the keys that are required to convert this into a local file.
        $this->assertArrayHasKey('path', $result);
        $this->assertEquals($sharepath, $result['url']);
    }

    /**
     * If there is no instance, migration shall fail.
     */
    public function test_nothing_to_migrate() {
        global $CFG;
        if ($CFG->branch < 36) {
            // No functionality in Moodle 3.5 and lower.
            return;
        }
        // Purge caches.
        \cache::make('core', 'repositories')->purge();

        // Nothing to migrate, so must return false.
        $this->assertFalse(\repository_owncloud\migration::migrate_all_instances());

        // There may not be a Nextcloud instance either.
        $this->assertEmpty(\repository::get_instances(['type' => 'nextcloud', 'onlyvisible' => false]));
    }

    /**
     * Test that, after migration, there is no ownCloud type. The one existing instance must be of the Nextcloud type afterwards.
     */
    public function test_migrate_one_instance() {
        global $CFG;
        if ($CFG->branch < 36) {
            // No functionality in Moodle 3.5 and lower.
            return;
        }

        $this->generator->create_instance([
            'issuerid' => $this->issuer->get('id'),
            'pluginname' => 'ownCloud',
            'controlledlinkfoldername' => 'Moodlefiles',
            'supportedreturntypes' => 'both',
            'defaultreturntype' => FILE_INTERNAL,
        ]);

        // Purge caches.
        \cache::make('core', 'repositories')->purge();

        // Migration completes successfully.
        $this->assertTrue(\repository_owncloud\migration::migrate_all_instances());

        // There is no ownCloud type or instance left.
        $this->assertEmpty(\repository::get_instances(['type' => 'owncloud', 'onlyvisible' => false]));

        // There is one Nextcloud instance.
        $this->assertCount(1, \repository::get_instances(['type' => 'nextcloud', 'onlyvisible' => false]));
    }
}
