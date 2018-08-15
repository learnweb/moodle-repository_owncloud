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
 * Upgrade script for repository_owncloud.
 *
 * @package     repository_owncloud
 * @category    upgrade
 * @copyright 2018 Jan Dageförde <jan.dagefoerde@ercis.uni-muenster.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Upgrade function called by Moodle core if a new version is present.
 * @param int $oldversion Version that we are upgrading from
 * @return bool true on success
 */
function xmldb_repository_owncloud_upgrade($oldversion) {
    global $DB;

    // Convert old purely FILE_REFERENCE references to new reference representations that can be of different types.
    if ($oldversion < 2018081400) {
        // Look for files that were created with an instance of this repo.
        $sql = "SELECT fr.id, fr.reference
                  FROM {files_reference} fr, {repository_instances} ri, {repository} r
                 WHERE fr.repositoryid = ri.id and ri.typeid = r.id and r.type = :type";
        $files = $DB->get_records_sql($sql, ['type' => 'owncloud']);

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
        // Plugin savepoint reached.
        upgrade_plugin_savepoint(true, 2018081400, 'repository', 'owncloud');
    }

    return true;
}