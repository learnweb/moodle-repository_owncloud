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
 * Nextcloud repository installation script.
 * @package    repository_nextcloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2018 Jan Dageförde (Learnweb, University of Münster)
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Nextcloud repository installation: Migrates existing instances of the contrib repository_owncloud,
 * if that had been installed earlier.
 *
 * @return bool A status indicating success or failure
 */
function xmldb_repository_nextcloud_install() {
    global $DB;

    $formertype = \repository::get_type_by_typename('owncloud');
    if (!$formertype) {
        // Nothing to do! Old plugin was not installed or inactive.
        return true;
    }

    $owncloudinstances = repository::get_instances(['type' => 'owncloud']);

    if (empty($owncloudinstances)) {
        // Nothing to do! No instances were configured.
        return true;
    }

    // Probably there is no repository_nextcloud type yet, but let's be sure.
    $nextcloud = $DB->get_record('repository', ['type' => 'nextcloud'], 'id', IGNORE_MISSING);
    if ($nextcloud) {
        $nextcloudtypeid = $nextcloud->id;
    } else {
        $nextcloudtype = new repository_type('nextcloud', array(), $formertype->get_visible());
        if (!$nextcloudtypeid = $nextcloudtype->create(true)) {
            return false;
        }
    }

    // Migrate each repository_owncloud instance.
    foreach ($owncloudinstances as $instance) {
        // Change type of repository instance to that of repository_nextcloud.
        $DB->set_field('repository_instances', 'typeid', $nextcloudtypeid, ['repositoryid' => $instance->id]);

        // File references are migrated automatically as they reference instanceid only, which we didn't change.
        // Instance configuration is migrated automatically as settings reference instanceid only.
    }

    // Update file references that were created with an early version of the original repository_owncloud plugin, as the format
    // for file references has changed. References have just been migrated, therefore use the new type id.
    repository_nextcloud_migrate_old_file_references($nextcloudtypeid);

    // Reset repository cache to avoid deleting those instances that we just migrated.
    cache::make('core', 'repositories')->purge();

    // Delete and disable the ownCloud repo.
    $formertype->delete();
    core_plugin_manager::reset_caches();

    $sql = "SELECT count('x')
              FROM {repository_instances} i, {repository} r
             WHERE r.type=:plugin AND r.id=i.typeid";
    $params = array('plugin' => 'owncloud');
    return $DB->count_records_sql($sql, $params) == 0;
}

/**
 * We also need to update file references that were created with an early version of the original repository_owncloud plugin,
 * as the format for file references has changed (from plain strings to structured JSON objects). To that end, look for files that
 * were created with an instance of this repo. The subsequent code is from a former upgrade script:
 * https://github.com/learnweb/moodle-repository_owncloud/blob/627024be6a551c665405669d12101bc1bcb46ff6/db/upgrade.php#L37 ff.
 * @param int $nextcloudtypeid
 */
function repository_nextcloud_migrate_old_file_references(int $nextcloudtypeid) {
    global $DB;

    // Get relevant file reference entries.
    $sql = "SELECT fr.id, fr.reference
              FROM {files_reference} fr, {repository_instances} ri
             WHERE fr.repositoryid = ri.id and ri.typeid = :nextcloudtypeid";
    $files = $DB->get_records_sql($sql, ['nextcloudtypeid' => $nextcloudtypeid]);
    foreach ($files as $file) {
        // Upgrade: Wrap reference in JSON object.
        $decoded = json_decode($file->reference);
        if (is_object($decoded)) {
            if (isset($decoded->type)) {
                // Is already in new format.
                continue;
            }
            // We can safely assume that type should be FILE_CONTROLLED_LINK, because JSON references were never
            // used for FILE_REFERENCE.
            $decoded->type = 'FILE_CONTROLLED_LINK';
            $encoded = json_encode($decoded);
            $DB->set_field('files_reference', 'reference', $encoded, ['id' => $file->id]);
            $DB->set_field('files_reference', 'referencehash', sha1($encoded), ['id' => $file->id]);
            continue;
        }
        // Non-JSON strings only ever existed for FILE_REFERENCE.
        $newreference = new stdClass();
        $newreference->type = 'FILE_REFERENCE';
        $newreference->link = $file->reference;
        $encoded = json_encode($newreference);
        $DB->set_field('files_reference', 'reference', $encoded, ['id' => $file->id]);
        $DB->set_field('files_reference', 'referencehash', sha1($encoded), ['id' => $file->id]);
    }
}