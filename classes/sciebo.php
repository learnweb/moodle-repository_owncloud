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

use repository_sciebo\sciebo_client;

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

        // The WebDav configuration is currently hardcoded. Will be moved to user interface.
        $this->webdav_host = 'localhost';
        $this->dav = new sciebo_client('localhost', '', '', 'bearer', '');
        $this->dav->port = 80;
        $this->dav->debug = false;
    }

    /**
     * Returns the auth url for OAuth 2.0 request
     * @return string the auth url
     */
    protected function auth_url() {
        return 'http://localhost/owncloud/index.php/apps/oauth2/authorize';
    }

    /**
     * Returns the token url for OAuth 2.0 request
     * @return string the auth url
     */
    protected function token_url() {
        return 'http://localhost/owncloud/index.php/apps/oauth2/api/v1/token';
    }

    /**
     * The WebDav listing function is encapsulated into this helper function.
     * @param $path
     * @return array
     */
    public function get_listing($path) {
        $this->dav->set_token($this->get_accesstoken()->token);
        return $this->dav->ls($path);
    }

    public function callback() {
        $this->log_out();
        $this->is_logged_in();
    }

    public function post($url, $params = '', $options = array()) {
        // A basic auth header has to be added to the request in order to provide the necessary user
        // credentials to the ownCloud interface.
        $this->setHeader(array(
            'Authorization: Basic ' . base64_encode($this->get_clientid() . ':' . $this->get_clientsecret())
        ));

        return parent::post($url, $params, $options);
    }
}