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
 * ownCloud repository plugin library.
 *
 * @package    repository_owncloud
 * @copyright  2017 Westfälische Wilhelms-Universität Münster (WWU Münster)
 * @author     Projektseminar Uni Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// @codeCoverageIgnoreStart
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/repository/lib.php');
// @codeCoverageIgnoreEnd
/**
 * ownCloud repository class.
 *
 * @package    repository_owncloud
 * @copyright  2017 Westfälische Universität Münster (WWU Münster)
 * @author     Projektseminar Uni Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_owncloud2 extends repository {

    /**
     * OAuth 2 client
     * @var \core\oauth2\client
     */
    private $client = null;
    /**
     * OAuth 2 Issuer
     * @var \core\oauth2\issuer
     */
    private $issuer = null;

    /** @var null|owncloud_client webdav client which is used for webdav operations. */
    private $dav = null;

    /**
     * repository_owncloud2 constructor.
     * @param int $repositoryid
     * @param bool|int|stdClass $context
     * @param array $options
     */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        parent::__construct($repositoryid, $context, $options);
        global $DB;
        // A Repository is marked as disabled when no issuer is present
        try {
            // Needs the issuer id
            // TODO check whether more than one issuer exist.
            $issuer = $DB->get_record('oauth2_issuer', array('name' => 'owncloud'));
            $this->issuer = \core\oauth2\api::get_issuer($issuer->id);
            $this->initiate_webdavclient($issuer->id);
        } catch (dml_missing_record_exception $e) {
            $this->disabled = true;
        }
        if ($this->issuer && !$this->issuer->get('enabled')) {
            $this->disabled = true;
        }
        // Initialise the webdavclient.
        // The WebDAV attributes are set beforehand.
    }

    /**
     * Initiates the webdavclient.
     * @param $issuerid
     */
    public function initiate_webdavclient($issuerid) {
        global $DB;
        try {
            $record = $DB->get_record('oauth2_issuer', array('id' => $issuerid), 'baseurl');
            $baseurl = $record->baseurl;
        } catch (Exception $e){
            // TODO some meaningfull exception
        }
        $https = 'https://';
        $http = 'http://';
        if (is_string($baseurl) || strlen($http)<strlen($baseurl)) {
            if (substr($baseurl, 0, 8) === $https) {
                $webdavtype = 'ssl://';
                $webdavport = 443;
                $server = substr($baseurl, 8);
            }
            if (substr($baseurl, 0, 7) === $http) {
                $webdavtype = '';
                $webdavport = 80;
                $server = substr($baseurl, 7);
            } else {
                // TODO some meaningfull exception
            }
        } else {
            // TODO some meaningfull exception
        }
        // Authentication method is set to Bearer, since we use OAuth 2.0.
        // repository_googledocs\rest
        $this->dav = new repository_owncloud2\owncloud_client2($server, '', '', 'bearer', $webdavtype);
        $this->dav->port = $webdavport;
        $this->dav->debug = false;
    }
    /**
     * Output method, which prints a warning inside an activity, which uses the ownCloud repository.
     *
     * @codeCoverageIgnore
     */
    private function print_warning() {
        global $CFG, $OUTPUT;
        $sitecontext = context_system::instance();

        if (has_capability('moodle/site:config', $sitecontext)) {

            $link = $CFG->wwwroot . '/' . $CFG->admin . '/settings.php?section=oauth2owncloud';

            // Generates a link to the admin setting page.
            echo $OUTPUT->notification('<a href="' . $link . '" target="_blank" rel="noopener noreferrer">
                                ' . get_string('missing_settings_admin', 'tool_oauth2owncloud') . '</a>', 'warning');
        } else {

            // Otherwise, just print a notification, bacause the current user cannot configure admin
            // settings himself.
            echo $OUTPUT->notification(get_string('missing_settings_user', 'tool_oauth2owncloud'));
        }
    }

    /**
     * If the plugin is set to hidden in the settings or any client settings date is missing,
     * the plugin is set to invisible and thus, not shown in the file picker.
     *
     * @return bool false, if set to hidden or settings data is missing.
     */
    public function is_visible() {
        /*if (!parent::is_visible()) {
            return false;
        } else {
            // If any settings data is missing, return false.
            return $this->options['success'];
        }*/
        return true;
    }

    /**
     * This function does exactly the same as in the WebDAV repository. The only difference is, that
     * the ownCloud OAuth2 client uses OAuth2 instead of Basic Authentication.
     *
     * @param string $url relative path to the file.
     * @param string $title title of the file.
     * @return array|bool returns either the moodle path to the file or false.
     */
    public function get_file($url, $title = '') {
        $url = urldecode($url);
        $path = $this->prepare_file($title);
        if (!$this->dav->open()) {
            return false;
        }
        $this->set_accesstoken();
        $parsedurl = $this->get_parsedurl('webdav');
        $this->dav->get_file($parsedurl['path'] . $url, $path);

        return array('path' => $path);
    }

    /**
     * This function does exactly the same as in the WebDAV repository. The only difference is, that
     * the ownCloud OAuth2 client uses OAuth2 instead of Basic Authentication.
     *
     * @param string $path relative path to the directory or file.
     * @param string $page page number (given multiple pages of elements).
     * @return array directory properties.
     */
    public function get_listing($path='', $page = '') {
        global $OUTPUT;
        $sitecontext = context_system::instance();

        $ret = $this->prepare_get_listing();

        // Before any WebDAV method can be executed, a WebDAV client socket needs to be opened
        // which connects to the server.
        if (!$this->dav->open()) {
            return $ret;
        }

        if (empty($path) || $path == '/') {
            $path = '/';
        } else {
            // This calculates all the parents paths form the current path. This is shown in the
            // navigation bar of the file picker.
            $chunks = preg_split('|/|', trim($path, '/'));
            // Every sub-path to the last part of the current path, is a parent path.
            for ($i = 0; $i < count($chunks); $i++) {
                $ret['path'][] = array(
                    'name' => urldecode($chunks[$i]),
                    'path' => '/'. join('/', array_slice($chunks, 0, $i + 1)). '/'
                );
            }
        }
        // Firstly the accesstoken is set ...
        $this->set_accesstoken();
        // Then the endpoint for webdav access is generated from the registered endpoints and parsed.
        $parsedurl = $this->get_parsedurl('webdav');

        // Since the paths, which are received from the PROPFIND WebDAV method are url encoded
        // (because they depict actual web-paths), the received paths need to be decoded back
        // for the plugin to be able to work with them.
        $dir = $this->dav->ls($parsedurl['path'] . '/' . urldecode($path));

        // The method get_listing return all information about all child files/folders of the
        // current directory. If no information was received, the directory must be empty.
        if (!is_array($dir)) {
            return $ret;
        }
        $folders = array();
        $files = array();
        $webdavpath = rtrim('/'.ltrim($parsedurl['path'], '/ '), '/ ');
        foreach ($dir as $v) {
            if (!empty($v['lastmodified'])) {
                $v['lastmodified'] = strtotime($v['lastmodified']);
            } else {
                $v['lastmodified'] = null;
            }

            // Remove the server URL from the path (if present), otherwise links will not work - MDL-37014.
            $server = preg_quote($parsedurl['path']);
            $v['href'] = preg_replace("#https?://{$server}#", '', $v['href']);
            // Extracting object title from absolute path.
            $v['href'] = substr(urldecode($v['href']), strlen($webdavpath));
            $title = substr($v['href'], strlen($path));

            if (!empty($v['resourcetype']) && $v['resourcetype'] == 'collection') {
                // A folder.
                if ($path != $v['href']) {
                    $folders[strtoupper($title)] = array(
                        'title' => rtrim($title, '/'),
                        'thumbnail' => $OUTPUT->pix_url(file_folder_icon(90))->out(false),
                        'children' => array(),
                        'datemodified' => $v['lastmodified'],
                        'path' => $v['href']
                    );
                }
            } else {
                // A file.
                $size = !empty($v['getcontentlength']) ? $v['getcontentlength'] : '';
                $files[strtoupper($title)] = array(
                    'title' => $title,
                    'thumbnail' => $OUTPUT->pix_url(file_extension_icon($title, 90))->out(false),
                    'size' => $size,
                    'datemodified' => $v['lastmodified'],
                    'source' => $v['href']
                );
            }
        }
        ksort($files);
        ksort($folders);
        $ret['list'] = array_merge($folders, $files);
        return $ret;

    }

    /**
     * Method to generate a download link for a chosen file (in the file picker).
     * Creates a share for the chosen file and fetches the specific file ID through
     * the OCS Share API (ownCloud).
     *
     * @param string $url relative path to the chosen file
     * @return string the generated downloadlink.
     * @throws repository_exception if $url is empty an exception is thrown.
     */
    public function get_link($url) {
    //    $response = $this->owncloud->get_link($url);
     //   return $response['link'];
    }

    /**
     * This method converts the source from the file picker (chosen by the user) into
     * information, which will be received by methods that fetch files/references from
     * the ownCloud server.
     *
     * @param string $source source of the file, returned by repository as 'source' and received back from user (not cleaned)
     * @return string file reference, ready to be stored
     */
    public function get_file_reference($source) {
        $usefilereference = optional_param('usefilereference', false, PARAM_BOOL);

        $reference = $source;

        // If a filereference was requested, a public link to the file has to be generated and returned.
        if ($usefilereference) {
            $reference = $this->get_link($source);
        }

        // Otherwise, the simple relative path to the file is enough.
        return $reference;
    }

    /**
     * Method that generates a reference link to the chosen file.
     *
     * @codeCoverageIgnore
     */
    public function send_file($storedfile, $lifetime=86400 , $filter=0, $forcedownload=false, array $options = null) {
        // Delivers a download link to the concerning file.
        redirect($storedfile->get_reference());
    }

    /**
     * Function which checks whether the user is logged in on the ownCloud instance.
     *
     * @return bool false, if no Access Token is set or can be requested.
     */
    public function check_login() {
        $client = $this->get_user_oauth_client();
        return $client->is_logged_in();
    }

    /**
     * @param bool $overrideurl
     * @return \core\oauth2\client|\core\oauth2\core\oauth2\client
     */
    protected function get_user_oauth_client($overrideurl = false) {
        if ($this->client) {
            return $this->client;
        }
        if ($overrideurl) {
            $returnurl = $overrideurl;
        } else {
            $returnurl = new moodle_url('/repository/repository_callback.php');
            $returnurl->param('callback', 'yes');
            $returnurl->param('repo_id', $this->id);
            $returnurl->param('sesskey', sesskey());
        }
        $this->client = \core\oauth2\api::get_user_oauth_client($this->issuer, $returnurl);
        return $this->client;
    }



    /**
     * Prints a simple Login Button which redirects to an authorization window from ownCloud.
     *
     * @return mixed login window properties.
     */
    public function print_login() {
        $client = $this->get_user_oauth_client();
        $url = $client->get_login_url();
        if ($this->options['ajax']) {
            $ret = array();
            $btn = new \stdClass();
            $btn->type = 'popup';
            $btn->url = $url->out(false);
            $ret['login'] = array($btn);
            return $ret;
        } else {
            echo html_writer::link($url, get_string('login', 'repository'),
                    array('target' => '_blank',  'rel' => 'noopener noreferrer'));
        }
    }

    /**
     * Deletes the held Access Token and prints the Login window.
     *
     * @return array login window properties.
     */
    public function logout() {
        $client = $this->get_user_oauth_client();
        $client->log_out();
        return parent::logout();
    }

    /**
     * Sets up access token after the redirection from ownCloud.
     * The Moodle API transfers the Client ID and the token as Params in the Request.
     * However the OwnCLoud Plugin excepts the Client ID and Secret to be in the Request Header.
     * Therefore the Header is set beforehand, and ClientID and Secret are passed twice.
     */
    public function callback() {
        $client = $this->get_user_oauth_client();
        $client->setHeader(array(
            'Authorization: Basic ' . base64_encode($client->get_clientid() . ':' . $client->get_clientsecret())
        ));
            // If an Access Token is stored within the Client, it has to be deleted to prevent the addidion
            // of an Bearer Authorization Header in the request method.
        // TODO When is this neccessary?
        //$client->log_out();
        // This will upgrade to an access token if we have an authorization code and save the access token in the session.
        $client->is_logged_in();
    }

    /**
     * This method adds a notification to the settings form, which redirects to the OAuth 2.0 client.
     *
     * @codeCoverageIgnore
     * @param moodleform $mform Moodle form (passed by reference)
     * @param string $classname repository class name
     */
    public static function type_config_form($mform, $classname = 'repository') {
        global $CFG, $OUTPUT;

        $link = $CFG->wwwroot.'/'.$CFG->admin.'/settings.php?section=oauth2owncloud';

        // A notification is added to the settings page in form of a notification.
        $html = $OUTPUT->notification(get_string('settings', 'repository_owncloud',
                '<a href="'.$link.'" target="_blank" rel="noopener noreferrer">'.
                get_string('oauth2', 'repository_owncloud') .'</a>'), 'warning');

        $mform->addElement('html', $html);

        // TODO add Elements for endpoints aber wo werte ich die aus?
        parent::type_config_form($mform);
    }

    /**
     * Method to define which filetypes are supported (hardcoded can not be changed in Admin Menu)
     *
     * For a full list of possible types and groups, look in lib/filelib.php, function get_mimetypes_array()
     *
     * @return string '*' means this repository support any files
     */
    public function supported_filetypes() {
        return '*';
    }

    /**
     * Method to define which Files are supported (hardcoded can not be changed in Admin Menu)
     *
     * Can choose FILE_REFERENCE|FILE_INTERNAL|FILE_EXTERNAL
     * FILE_INTERNAL - the file is uploaded/downloaded and stored directly within the Moodle file system
     * FILE_EXTERNAL - the file stays in the external repository and is accessed from there directly
     * FILE_REFERENCE - the file may be cached locally, but is automatically synchronised, as required,
     *                 with any changes to the external original
     * @return int return type bitmask supported
     */
    public function supported_returntypes() {
        return FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE;
    }

    /**
     *  Sets the accesstoken for the current instance.
     */
    private function set_accesstoken() {
        // Sets the Access token.
        $client = $this->get_user_oauth_client();
        $token = $client->get_accesstoken();
        // Merely the token code is transfered, expirationdate is not neccessary.
        $this->dav->set_token($token->token);
    }

    /**
     * Returns the parsed url of the choosen endpoint.
     * @param string $endpointname
     * @return array parseurl [scheme => https/http, host=>'hostname', port=>443, path=>'path']
     */
    private function get_parsedurl($endpointname) {
        try {
            $webdavurl = $this->issuer->get_endpoint_url($endpointname);
        } catch (Exception $e) {
            // TODO Some meaningfull exception
        }
        // $parseurl = scheme https host sns-testing.sciebo.de port 443 path /remote.php/webdav
        return parse_url($webdavurl);
    }

    /**
     * Prepares the Params for the get_listing method.
     * @return array
     */
    private function prepare_get_listing() {
        global $CFG;
        // Array, which will have to be returned by this function.
        $ret  = array();

        // Tells the file picker to fetch the list dynamically. An AJAX request is send to the server,
        // as soon as the user opens a folder.
        $ret['dynload'] = true;

        // Search is disabled in this plugin.
        $ret['nosearch'] = true;

        // We need to provide a login link, because the user needs login himself with his own ownCloud
        // user account.
        $ret['nologin'] = false;

        // Contains all parent paths to the current path.
        $ret['path'] = array(array('name' => get_string('owncloud', 'repository_owncloud'), 'path' => ''));

        // Contains all file/folder information and is required to build the file/folder tree.
        $ret['list'] = array();

        $sitecontext = context_system::instance();
        if (has_capability('moodle/site:config', $sitecontext)) {

            // URL to manage a external repository. It is displayed in the file picker and in this case directs
            // the settings page of the oauth2owncloud admin tool.
            $ret['manage'] = $CFG->wwwroot.'/'.$CFG->admin.'/settings.php?section=oauth2owncloud';
        }
        return $ret;
    }
}