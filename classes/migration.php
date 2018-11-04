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
 * Helper for migrating to repository_nextcloud.
 *
 * Don't worry, repository_nextcloud is based on the same code and will work perfectly fine with ownCloud
 * as well as with Nextcloud!
 *
 * @package    repository_owncloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2018 Jan Dageförde (Learnweb, University of Münster)
 */

namespace repository_owncloud;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Helper for migrating to repository_nextcloud.
 *
 * @package    repository_owncloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2018 Jan Dageförde (Learnweb, University of Münster)
 */
class migration {
    /**
     * Migrate existing instances of repository_owncloud to the more recent repository_nextcloud.
     * (Note: If you are using ownCloud, the connection between Moodle and ownCloud will continue to work!)
     *
     * @return bool A status indicating success or failure
     */
    public static function migrate_all_instances() : bool {
        global $DB;

        // Sanity check -- this should really never be done by a non-admin.
        require_capability('moodle/site:config', \context_system::instance());

        $formertype = \repository::get_type_by_typename('owncloud');
        if (!$formertype) {
            // Nothing to do! Old plugin was not installed or inactive.
            return true;
        }

        $owncloudinstances = \repository::get_instances(['type' => 'owncloud']);

        if (empty($owncloudinstances)) {
            // Nothing to do! No instances were configured.
            return true;
        }

        // Whether or not there is a repository_nextcloud type yet, let's be sure that now there is.
        $nextcloud = $DB->get_record('repository', ['type' => 'nextcloud'], 'id', IGNORE_MISSING);
        if ($nextcloud) {
            $nextcloudtypeid = $nextcloud->id;
        } else {
            $nextcloudtype = new \repository_type('nextcloud', array(), $formertype->get_visible());
            if (!($nextcloudtypeid = $nextcloudtype->create(true))) {
                return false;
            }
        }

        // Migrate each repository_owncloud instance.
        foreach ($owncloudinstances as $instance) {
            // Change type of repository instance to that of repository_nextcloud.
            $DB->set_field('repository_instances', 'typeid', $nextcloudtypeid, ['id' => $instance->id]);

            // File references are migrated automatically as they reference instanceid only, which we didn't change.
            // Instance configuration is migrated automatically as settings reference instanceid only.
        }

        // Reset repository cache to avoid deleting those instances that we just migrated.
        \cache::make('core', 'repositories')->purge();

        // Delete and disable the ownCloud repo.
        $formertype->delete();
        \core_plugin_manager::reset_caches();

        $sql = "SELECT count('x')
                  FROM {repository_instances} i, {repository} r
                 WHERE r.type=:plugin AND r.id=i.typeid";
        $params = array('plugin' => 'owncloud');
        return $DB->count_records_sql($sql, $params) === 0;
    }
}