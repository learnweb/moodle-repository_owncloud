<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 12.12.16
 * Time: 11:07
 */

namespace repository_sciebo;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/oauthlib.php');

class sciebo extends \oauth2_client
{
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
    protected function auth_url()
    {
        return 'http://localhost/owncloud/index.php/apps/oauth2/authorize';
    }

    /**
     * Returns the token url for OAuth 2.0 request
     * @return string the auth url
     */
    protected function token_url()
    {
        return 'http://localhost/owncloud/index.php/apps/oauth2/api/v1/token';
    }

}