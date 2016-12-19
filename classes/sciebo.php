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
 * Sciebo Class for oauth2sciebo admin tool
 *
 * @package    tool_oauth2sciebo
 * @copyright  2016 Westfälische Wilhelms-Universität Münster (WWU Münster)
 * @author     Projektseminar Uni Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace repository_sciebo;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/oauthlib.php');

class sciebo extends \oauth2_client {
    /**
     * Create the DropBox API Client.
     *
     * @param   string      $key        The API key
     * @param   string      $secret     The API secret
     * @param   string      $callback   The callback URL
     */
    public function __construct($key, $secret, $callback) {
        parent::__construct($key, $secret, $callback, '');
    }

    /**
     * Returns the auth url for OAuth 2.0 request
     * @return string the auth url
     */
    protected function auth_url() {
        return 'https://pssl16.uni-muenster.de/owncloud9.2/index.php/apps/oauth2/authorize';
    }

    /**
     * Returns the token url for OAuth 2.0 request
     * @return string the auth url
     */
    protected function token_url() {
        return ': https://pssl16.uni-muenster.de/owncloud9.2/index.php/apps/oauth2/api/v1/token';
    }

}