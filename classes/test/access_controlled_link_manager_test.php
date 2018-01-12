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
 * Manages the creation and usage of access controlled links.
 *
 * @package    repository_owncloud
 * @copyright  2017 Nina Herrmann (Learnweb, University of Münster)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace repository_owncloud\test;

use repository_owncloud\access_controlled_link_manager;

class access_controlled_link_manager_test extends access_controlled_link_manager {

    /**
     * Access_controlled_link_manager constructor.
     * @param ocs_client $ocsclient
     * @param \core\oauth2\issuer $issuer
     * @param string $repositoryname
     * @throws \moodle_exception
     */

    public function __construct($ocsclient, \core\oauth2\issuer $issuer, $repositoryname, $systemdav) {
        $this->ocsclient = $ocsclient;
        $this->repositoryname = $repositoryname;
        $this->issuer = $issuer;
        $this->systemwebdavclient = $systemdav;
    }
}