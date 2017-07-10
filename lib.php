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
 * @copyright  2017 Project seminar (Learnweb, University of Münster)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/repository/lib.php');
/**
 * ownCloud repository class.
 *
 * @package    repository_owncloud
 * @copyright  2017 Project seminar (Learnweb, University of Münster)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_owncloud extends repository {

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

    /**
     * Additional scopes needed for the repository. Currently, ownCloud does not actually support/use scopes, so
     * this is intended as a hint at required functionality and will help declare future scopes.
     */
    const SCOPES = 'files ocs';

    /**
     * owncloud_client webdav client which is used for webdav operations.
     * @var \repository_owncloud\owncloud_client
     */
    private $dav = null;

    /**
     * repository_owncloud constructor.
     * @param int $repositoryid
     * @param bool|int|stdClass $context
     * @param array $options
     */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        parent::__construct($repositoryid, $context, $options);
        try {
            // Issuer from config table, in the type_config_form a select form is defined to choose an issuer.
            $issuerid = get_config('owncloud', 'issuerid');
            $this->issuer = \core\oauth2\api::get_issuer($issuerid);
        } catch (dml_missing_record_exception $e) {
            // A Repository is marked as disabled when no issuer is present.
            $this->disabled = true;
        } try {
            // Check the webdavendpoint.
            $this->get_parsedurl('webdav');
        } catch (Exception $e) {
            // A Repository is marked as disabled when no webdav_endpoint is present
            // or it fails to parse, because all operations concerning files
            // rely on the webdav endpoint.
            $this->disabled = true;
        }
        if (!$this->issuer) {
            $this->disabled = true;
        } else if (!$this->issuer->get('enabled')) {
            // In case the Issuer is not enabled, the repository is disabled.
            $this->disabled = true;
        } else if (!self::is_valid_issuer($this->issuer)) {
            // Check if necessary endpoints are present.
            $this->disabled = true;
        }
        if ($this->disabled) {
            return;
        }

        // Exclusively when a issuer is present and the plugin is not disabled the webdavclient is generated.
        $this->initiate_webdavclient();
    }


    /**
     * Initiates the webdav client.
     * @throws \repository_owncloud\configuration_exception If configuration is missing (endpoints).
     */
    public function initiate_webdavclient() {
        $webdavendpoint = $this->get_parsedurl('webdav');

        // Selects the necessary information (port, type, server) from the path to build the webdavclient.
        $server = $webdavendpoint['host'];
        if ($webdavendpoint['scheme'] === 'https') {
            $webdavtype = 'ssl://';
            $webdavport = 443;
        } else if ($webdavendpoint['scheme'] === 'http') {
            $webdavtype = '';
            $webdavport = 80;
        }

        // Override default port, if a specific one is set.
        if (isset($webdavendpoint['port'])) {
            $webdavport = $webdavendpoint['port'];
        }

        $oauthclient = $this->get_user_oauth_client();

        // Authentication method is set to Bearer, since we use OAuth 2.0.
        $this->dav = new repository_owncloud\owncloud_client($server, '', '', 'bearer', $webdavtype, $oauthclient);
        // TODO set (base)path inside owncloud_client!! We should not need to take care of it here...
        $this->dav->port = $webdavport;
        $this->dav->debug = false;
    }

    /**
     * Check if an issuer provides all endpoints that we require.
     * @param $issuer An issuer.
     * @return bool True, if all endpoints exist; false otherwise.
     */
    private static function is_valid_issuer($issuer) {
        $endpoinwebdav = false;
        $endpointoken = false;
        $endpoinuserinfo = false;
        $endpoinauth = false;
        $endpoints = \core\oauth2\api::get_endpoints($issuer);
        foreach ($endpoints as $endpoint) {
            $name = $endpoint->get('name');
            switch ($name) {
                case 'webdav_endpoint':
                    $endpoinwebdav = true;
                    break;
                case 'token_endpoint':
                    $endpointoken = true;
                    break;
                case 'authorization_endpoint':
                    $endpoinauth = true;
                    break;
                case 'userinfo_endpoint':
                    $endpoinuserinfo = true;
                    break;
            }
        }
        return $endpoinwebdav && $endpoinuserinfo && $endpointoken && $endpoinauth;
    }

    /**
     * If the plugin is set to hidden in the settings or any client settings date is missing,
     * the plugin is set to invisible and thus, not shown in the file picker.
     *
     * @return bool false, if set to hidden or settings data is missing.
     */
    public function is_visible() {
        if (!parent::is_visible()) {
            return false;
        }
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
        $parsedurl = $this->get_parsedurl('webdav');
        // TODO handle (base)path in client, not here.
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
        // Get basepath from endpoint
        // TODO handle (base)path in client, not here.
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
        $query = http_build_query(array('path' => $url,
            'shareType' => 3,
            'publicUpload' => false,
            'permissions' => 31
        ), null, "&");

        $baseurl = $this->issuer->get('baseurl');
        // This is ownCloud specific.
        // TODO IMPROVE: could be an additional setting in the Oauth2 issuer.
        $posturl = $baseurl . '/ocs/v1.php/apps/files_sharing/api/v1/shares';
        $client = $this->get_user_oauth_client();

        $response = $client->post($posturl, $query, []);

        $ret = array();

        $xml = simplexml_load_string($response);
        $ret['code'] = $xml->meta->statuscode;
        $ret['status'] = $xml->meta->status;

        // The link is generated.

        $fields = explode("/s/", $xml->data[0]->url[0]);
        $fileid = $fields[1];

        $ret['link'] = $this->get_path($fileid);

        return $ret['link'];
    }
    /**
     * This method is used to generate file and folder paths to ownCloud after a successful share.
     * Depending on the share type (public or private share), it returns the path to the shared
     * file or folder.
     *
     * @param $type string either personal or private. Depending on share type.
     * @param $id string file or folder id of the concerning content.
     * @return bool|string returns the generated path, if $type it personal or private. Otherwise, false.
     */
    public function get_path($id) {
        $baseurl = $this->issuer->get('baseurl');
        return $baseurl . '/public.php?service=files&t=' . $id . '&download';
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
     * Get a cached user authenticated oauth client.
     * @param bool|moodle_url $overrideurl Use this url instead of the repo callback.
     * @return \core\oauth2\client
     */
    protected function get_user_oauth_client($overrideurl = false) {
        if ($this->client) {
            return $this->client;
        }
        // TODO $overrideurl is not used currently. GDocs uses it in send_file. Evaluate whether we need it.
        if ($overrideurl) {
            $returnurl = $overrideurl;
        } else {
            $returnurl = new moodle_url('/repository/repository_callback.php');
            $returnurl->param('callback', 'yes');
            $returnurl->param('repo_id', $this->id);
            $returnurl->param('sesskey', sesskey());
        }
        $this->client = \core\oauth2\api::get_user_oauth_client($this->issuer, $returnurl, self::SCOPES);
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
     * The Moodle OAuth 2 API transfers Client ID and secret as params in the request.
     * However, the ownCloud OAuth 2 App expects Client ID and secret to be in the request header.
     * Therefore, the header is set beforehand, and ClientID and Secret are passed twice.
     */
    public function callback() {
        $client = $this->get_user_oauth_client();
        // If an Access Token is stored within the client, it has to be deleted to prevent the addition
        // of an Bearer authorization header in the request method.
        $client->log_out();
        $client->setHeader(array(
            'Authorization: Basic ' . base64_encode($client->get_clientid() . ':' . $client->get_clientsecret())
        ));
        // This will upgrade to an access token if we have an authorization code and save the access token in the session.
        $client->is_logged_in();
    }

    /**
     * This method adds a select form and additional information to the settings form..
     *
     * @param moodleform $mform Moodle form (passed by reference)
     * @param string $classname repository class name
     */
    public static function type_config_form($mform, $classname = 'repository') {
        global $OUTPUT;
        parent::type_config_form($mform);

        // Firstly all issuers are considered.
        $issuers = core\oauth2\api::get_all_issuers();
        $types = array();

        // Fetch selected issuer.
        $issuerid = get_config('owncloud', 'issuerid');

        // Validates which issuers implement the right endpoints. WebDav is necessary for ownCloud.
        $validissuers = [];
        foreach ($issuers as $issuer) {
            if (self::is_valid_issuer($issuer)) {
                $validissuers[] = $issuer->get('name');
            }
            $types[$issuer->get('id')] = $issuer->get('name');
        }

        // Depending on the hitherto settings the user is which issuer is chosen.
        // In case no issuer is chosen there appears a warning.
        // Additionally when the chosen issuer is invalid there appears a strong warning.
        $text = '';
        $strrequired = get_string('required');
        if (!empty($issuerid)) {
            if (!in_array($types[$issuerid], $validissuers)) {
                $text .= get_string('invalid_issuer', 'repository_owncloud', $types[$issuerid]);
                $urgency = 'error';
            } else {
                $text .= get_string('settings_withissuer', 'repository_owncloud', $types[$issuerid]);
                $urgency = 'info';
            }
        } else {
            $text .= get_string('settings_withoutissuer', 'repository_owncloud');
            $urgency = 'warning';
        }

        // Render the form.
        $url = new \moodle_url('/admin/tool/oauth2/issuers.php');
        $mform->addElement('static', null, '', get_string('oauth2serviceslink', 'repository_owncloud', $url->out()));

        $mform->addElement('html', $OUTPUT->notification($text, $urgency));
        $select = $mform->addElement('select', 'issuerid', get_string('chooseissuer', 'repository_owncloud'), $types);
        $mform->addRule('issuerid', $strrequired, 'required', null, 'issuer');
        $mform->addHelpButton('issuerid', 'chooseissuer', 'repository_owncloud');
        $mform->setType('issuerid', PARAM_RAW_TRIMMED);

        // All issuers that are valid are displayed seperately (if any).
        if (count($validissuers) === 0) {
            $mform->addElement('html', get_string('no_right_issuers', 'repository_owncloud'));
        } else {
            $mform->addElement('html', get_string('right_issuers', 'repository_owncloud', implode(', ', $validissuers)));
        }
        // The default is set to the issuer chosen.
        if (!empty($issuerid)) {
            $select->setSelected($issuerid);
        }
    }

    /**
     * Names of the plugin settings
     *
     * @return array
     */
    public static function get_type_option_names() {
        return array('issuerid', 'pluginname', 'validissuers');
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
     * Now only FILE_INTERNAL since get_line and get_file_reference is not implemented.
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
     * Returns the parsed url of the choosen endpoint.
     * @param string $endpointname
     * @return array parseurl [scheme => https/http, host=>'hostname', port=>443, path=>'path']
     * @throws \repository_owncloud\configuration_exception if an endpoint is undefined
     */
    private function get_parsedurl($endpointname) {
        $url = $this->issuer->get_endpoint_url($endpointname);
        if (empty($url)) {
            throw new \repository_owncloud\configuration_exception(sprintf('Endpoint %s not defined.', $endpointname));
        }
        return parse_url($url);
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
            // the settings page of repositories.
            $settingsurl = new moodle_url('/admin/repository.php');
            $ret['manage'] = $settingsurl->out();
        }
        return $ret;
    }
}