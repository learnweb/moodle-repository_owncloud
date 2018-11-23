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
    public static function migrate_all_instances() {
        global $DB, $CFG;

        // Sanity checks -- this method should really never be called for a non-admin. M3.6 and above only.
        require_capability('moodle/site:config', \context_system::instance());
        if ($CFG->branch < 36) {
            die('This functionality is only available in Moodle 3.6 and above.');
        }

        $formertype = \repository::get_type_by_typename('owncloud');
        if (!$formertype) {
            // Nothing to do! Old plugin was not installed or inactive.
            return true;
        }

        $owncloudinstances = \repository::get_instances(['type' => 'owncloud', 'onlyvisible' => false]);

        if (empty($owncloudinstances)) {
            // Nothing to do! No instances were configured.
            return false;
        }

        // Whether or not there is a repository_nextcloud type yet, let's be sure that now there is.
        $nextcloud = $DB->get_record('repository', ['type' => 'nextcloud'], 'id', IGNORE_MISSING);
        if ($nextcloud) {
            $nextcloudtypeid = $nextcloud->id;
        } else {
            $nextcloudtype = new \repository_type('nextcloud', array(), $formertype->get_visible());
            if (!($nextcloudtypeid = $nextcloudtype->create(true))) {
                // Needed to create the new type but couldn't.
                return false;
            }
        }

        // Migrate each repository_owncloud instance.
        foreach ($owncloudinstances as $instance) {
            // Download file references that used the "alias/shortcut" link option (not supported anymore).
            if (!self::download_legacy_alias_references($instance->id)) {
                // Somehow failed; do not migrate this instance!
                // Purge cache in case previous instances were migrated successfully.
                \cache::make('core', 'repositories')->purge();

                // Indicate failure.
                return false;
            }

            // Other references are migrated automatically as they refer to instanceid only, which we will not change.
            // Instance configuration is migrated automatically as settings refer to instanceid only.

            // Change type of repository instance to that of repository_nextcloud.
            $DB->set_field('repository_instances', 'typeid', $nextcloudtypeid, ['id' => $instance->id]);

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

    /**
     * Download file references that used the "alias/shortcut" link option into local file storage.
     * This kind of reference is not supported anymore.
     *
     * @param int $instanceid Repository instance
     * @return bool true if everything succeeded, false if any download failed.
     */
    private static function download_legacy_alias_references($instanceid) {
        $fs = get_file_storage();
        $files = $fs->get_external_files($instanceid);
        foreach ($files as $storedfile) {
            // Check whether this is an alias or an access controlled link first.
            $ref = json_decode($storedfile->get_reference());
            if (!is_object($ref)) {
                // Intermediate (draft) reference, do not alter.
                continue;
            }
            if ($ref->type === "FILE_CONTROLLED_LINK") {
                // ACL, do not alter.
                continue;
            }

            // Import reference.
            try {
                $fs->import_external_file($storedfile);
            } catch (\moodle_exception $e) {
                debugging($e->getMessage(), DEBUG_NORMAL);
                return false;
            }
        }
        return true;
    }
}