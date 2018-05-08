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
 * Provider Class to implement the Privacy API of Moodle35.
 *
 * @package    repository_owncloud
 * @copyright  2018 Nina Herrmann (Learnweb, University of Münster)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace repository_owncloud\privacy;

defined('MOODLE_INTERNAL') || die();
use core_privacy\local\metadata\collection;
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    use \core_privacy\local\legacy_polyfill;
    public static function _get_metadata(collection $collection) {
        // The repository uses a user specific acesstoken (called confirmation token), provided by the oauthlib, ...
        // Saved in the session to access files. However, the oauthlib Privacy API is outsourced to the oauth2 plugin.
        // For this reason the collections includes the oauth2 subplugin.
        $collection->add_subsystem_link(
            'auth_oauth2',
            [],
            'privacy:metadata:auth_oauth2'
        );
        return $collection;
    }
}