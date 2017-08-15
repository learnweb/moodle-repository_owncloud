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
 * REST interface to ownCloud's implementation of Open Collaboration Services.
 *
 * @package    repository_owncloud
 * @copyright  2017 Jan Dageförde (Learnweb, University of Münster)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace repository_owncloud;

use core\oauth2\client;
use core\oauth2\rest;

defined('MOODLE_INTERNAL') || die();

/**
 * REST interface to ownCloud's implementation of Open Collaboration Services.
 *
 * @copyright  2017 Jan Dageförde (Learnweb, University of Münster)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ocs_client extends rest {

    const SHARE_TYPE_PUBLIC = 3;
    const SHARE_PERMISSION_READ = 1;

    /**
     * OCS endpoint as configured for the used issuer.
     * @var string
     */
    private $ocsendpoint;

    /**
     * Get endpoint URLs from the used issuer to use them in get_api_functions().
     * @param client $oauthclient OAuth-authenticated ownCloud client
     * @throws configuration_exception Exception if critical endpoints are missing.
     */
    public function __construct(client $oauthclient) {

        parent::__construct($oauthclient);
        $issuer = $oauthclient->get_issuer();
        $this->ocsendpoint = $issuer->get_endpoint_url('ocs');
        if ($this->ocsendpoint === false) {
            throw new configuration_exception('Endpoint ocs_endpoint not defined.');
        }
    }

    /**
     * Define relevant functions of the OCS API.
     *
     * Docs:
     * create_share: https://doc.owncloud.org/server/10.0/developer_manual/core/ocs-share-api.html#create-a-new-share
     *
     */
    public function get_api_functions() {
        return [
            'create_share' => [
                'endpoint' => $this->ocsendpoint,
                'method' => 'post',
                'args' => [
                    'path' => PARAM_TEXT, // Could be PARAM_PATH, we really don't want to enforce a Moodle understanding of paths.
                    'shareType' => PARAM_INT,
                    'publicUpload' => PARAM_RAW, // Actually Boolean, but neither String-Boolean ('false') nor PARAM_BOOL (0/1).
                    'permissions' => PARAM_INT
                ],
                'response' => 'text/xml'
            ],
        ];
    }

}
