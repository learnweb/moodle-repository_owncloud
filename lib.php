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

use repository_owncloud\ocs_client;

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
     * ownCloud webdav client which is used for webdav operations.
     * (Type is identical to webdav_client starting from Moodle 3.4.)
     *
     * @var \repository_owncloud\owncloud_client
     */
    private $dav = null;

    /**
     * Basepath for WebDAV operations
     * @var string
     */
    private $davbasepath;

    /**
     * OCS client that uses the Open Collaboration Services REST API.
     * @var ocs_client
     */
    private $ocsclient;

    /**
     * repository_owncloud constructor.
     * @param int $repositoryid
     * @param bool|int|stdClass $context
     * @param array $options
     */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        parent::__construct($repositoryid, $context, $options);
        try {
            // Issuer from repository instance config.
            $issuerid = $this->get_option('issuerid');
            $this->issuer = \core\oauth2\api::get_issuer($issuerid);
        } catch (dml_missing_record_exception $e) {
            // A repository is marked as disabled when no issuer is present.
            $this->disabled = true;
            return;
        }

        try {
            // Load the webdav endpoint and parse the basepath.
            $webdavendpoint = $this->parse_endpoint_url('webdav');
            // Get basepath without trailing slash, because future uses will come with a leading slash.
            $basepath = $webdavendpoint['path'];
            if (strlen($basepath) > 0 && substr($basepath, -1) === '/') {
                $basepath = substr($basepath, 0, -1);
            }
            $this->davbasepath = $basepath;
        } catch (\repository_owncloud\configuration_exception $e) {
            // A repository is marked as disabled when no webdav_endpoint is present
            // or it fails to parse, because all operations concerning files
            // rely on the webdav endpoint.
            $this->disabled = true;
            return;
        }

        if (!$this->issuer) {
            $this->disabled = true;
            return;
        } else if (!$this->issuer->get('enabled')) {
            // In case the Issuer is not enabled, the repository is disabled.
            $this->disabled = true;
            return;
        } else if (!self::is_valid_issuer($this->issuer)) {
            // Check if necessary endpoints are present.
            $this->disabled = true;
            return;
        }

        $this->ocsclient = new ocs_client($this->get_user_oauth_client());
    }

    /**
     * Initiates the webdav client.
     * @throws \repository_owncloud\configuration_exception If configuration is missing (endpoints).
     */
    private function initiate_webdavclient() {
        if ($this->dav !== null) {
            return $this->dav;
        }

        $webdavendpoint = $this->parse_endpoint_url('webdav');

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

        // Authentication method is `bearer` for OAuth 2. Pass token of authenticated client, too.
        $this->dav = new repository_owncloud\owncloud_client($server, '', '', 'bearer', $webdavtype,
            $this->get_user_oauth_client()->get_accesstoken()->token);

        $this->dav->port = $webdavport;
        $this->dav->debug = false;
        return $this->dav;
    }

    /**
     * Check if an issuer provides all endpoints that we require.
     * @param \core\oauth2\issuer $issuer An issuer.
     * @return bool True, if all endpoints exist; false otherwise.
     */
    private static function is_valid_issuer($issuer) {
        $endpointwebdav = false;
        $endpointocs = false;
        $endpointtoken = false;
        $endpointauth = false;
        $endpoints = \core\oauth2\api::get_endpoints($issuer);
        foreach ($endpoints as $endpoint) {
            $name = $endpoint->get('name');
            switch ($name) {
                case 'webdav_endpoint':
                    $endpointwebdav = true;
                    break;
                case 'ocs_endpoint':
                    $endpointocs = true;
                    break;
                case 'token_endpoint':
                    $endpointtoken = true;
                    break;
                case 'authorization_endpoint':
                    $endpointauth = true;
                    break;
            }
        }
        return $endpointwebdav && $endpointocs && $endpointtoken && $endpointauth;
    }

    /**
     * This function does exactly the same as in the WebDAV repository. The only difference is, that
     * the ownCloud OAuth2 client uses OAuth2 instead of Basic Authentication.
     *
     * @param string $reference relative path to the file.
     * @param string $title title of the file.
     * @return array|bool returns either the moodle path to the file or false.
     */
    public function get_file($reference, $title = '') {

        // Normal file
        $reference = urldecode($reference);
        // Prepare a file with an arbitrary name - cannot be $title because of special chars (cf. MDL-57002).
        $path = $this->prepare_file(uniqid());
        $this->initiate_webdavclient();
        if (!$this->dav->open()) {
            return false;
        }
        $this->dav->get_file($this->davbasepath . $reference, $path);
        $this->dav->close();

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
        if (empty($path)) {
            $path = '/';
        }

        $ret = $this->get_listing_prepare_response($path);

        // Before any WebDAV method can be executed, a WebDAV client socket needs to be opened
        // which connects to the server.
        $this->initiate_webdavclient();
        if (!$this->dav->open()) {
            return $ret;
        }

        // Since the paths which are received from the PROPFIND WebDAV method are url encoded
        // (because they depict actual web-paths), the received paths need to be decoded back
        // for the plugin to be able to work with them.
        $ls = $this->dav->ls($this->davbasepath . urldecode($path));
        $this->dav->close();

        // The method get_listing return all information about all child files/folders of the
        // current directory. If no information was received, the directory must be empty.
        if (!is_array($ls)) {
            return $ret;
        }

        // Process WebDAV output and convert it into Moodle format.
        $ret['list'] = $this->get_listing_convert_response($path, $ls);
        return $ret;

    }

    /**
     * Use OCS to generate a public share to the requested file.
     * This method derives a download link from the public share URL.
     *
     * @param string $url relative path to the chosen file
     * @return string the generated download link.
     * @throws \repository_owncloud\request_exception If ownCloud responded badly
     *
     */
    public function get_link($url) {
        $ocsparams = [
            'path' => $url,
            'shareType' => ocs_client::SHARE_TYPE_PUBLIC,
            'publicUpload' => false,
            'permissions' => ocs_client::SHARE_PERMISSION_READ
            ];

        $response = $this->ocsclient->call('create_share', $ocsparams);
        $xml = simplexml_load_string($response);

        if ($xml === false ) {
            throw new \repository_owncloud\request_exception('Invalid response');
        }

        if ((string)$xml->meta->status !== 'ok') {
            throw new \repository_owncloud\request_exception(
                sprintf('(%s) %s', $xml->meta->statuscode, $xml->meta->message));
        }

        // Take the share link and convert it into a download link.
        return ((string)$xml->data[0]->url) . '/download';
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

    /** Called when a file is selected as a "access control link".
     * Invoked at MOODLE/repository/repository_ajax.php
     *
     * This is called at the point the reference files are being copied from the draft area to the real area
     *
     * @param string $reference this reference is generated by
     *                          repository::get_file_reference()
     * @param context $context the target context for this new file.
     * @param string $component the target component for this new file.
     * @param string $filearea the target filearea for this new file.
     * @param string $itemid the target itemid for this new file.
     * @return string updated reference (final one before it's saved to db).
     * @throws repository_exception
     */
    public function reference_file_selected($reference, $context, $component, $filearea, $itemid) {
        // todo: Check if file already exist
        $source = $reference;
        $filereturn = json_decode($reference);

        // Check this issuer is enabled.
        if ($this->disabled) {
            throw new repository_exception('cannotdownload', 'repository');
        }
        // Get the system oauth client.
        $systemauth = \core\oauth2\api::get_system_oauth_client($this->issuer);

        if ($systemauth === false) {
            $details = 'Cannot connect as system user';
            throw new repository_exception('errorwhilecommunicatingwith', 'repository', '', $details);
        }
        // Creates a owncloud_client for the system account.
        // todo: should the client be created here?
        $sysdav = $this->create_system_dav($systemauth);

        // Get the system user email so we can share the file with this user.
        $systemuserinfo = $systemauth->get_userinfo();
        $systemusername = $systemuserinfo['username'];

        // Get the current user.
        $userauth = $this->get_user_oauth_client();
        if ($userauth === false) {
            $details = 'Cannot connect as current user';
            throw new repository_exception('errorwhilecommunicatingwith', 'repository', '', $details);
        }
        // 1. Share the File with the system account.
        $responsecreateshare = $this->create_share_user_sysaccount($source, $systemusername, 86400, true);

        // todo: check statuscode also for share already exist
        if ($responsecreateshare['statuscode'] != 100) {
            throw new repository_exception('cannotdownload', 'repository');
        }

        // 2. Create a unique path in the system account.
        // todo: now generated as Google. Check expedience.
        $foldercreate = $this->create_folder_path_access_controlled_links($context, $component, $filearea, $itemid, $sysdav);
        if ($foldercreate['success'] != true) {
            throw new repository_exception('Could not create folder path', 'repository');
        }
        // 3. Copy File to the new folder path.
        $copyfile = $this->copy_file_to_path($source, $foldercreate['fullpath'], $sysdav);
        if ($copyfile['success'] != 201) {
            throw new repository_exception('Could not copy file', 'repository');
        }

        // 4. Delete the share.
        $reponsedeleteshare = $this->delete_share_dataowner_sysaccount($responsecreateshare['shareid']);

        if ($reponsedeleteshare != 100) {
            // todo: react in some way, however, this becomes difficult since:
            // 1. Throwing an exception might be misunderstood, since the file was successfully created.
            // 2. However, we do not want to see the share in the user account.
            throw new repository_exception('Share is still present', 'repository');
        }

        // todo: return the link to the file is not tested with a owncloud instance.
        // 5. Create final share for access.
        $finalshare = $foldercreate['fullpath'] . $source;

        // Update the returned reference so that the stored_file in moodle points to the newly copied file.
        $filereturn->link = $finalshare;
        $filereturn->name = $source;
        $filereturn->usesystem = true;
        $filereturn = json_encode($filereturn);
        return $filereturn;
    }

    /** Creates a public link in the sysaccount.
     * @param $finalshare
     * @return array
     */
    private function create_private_link ($path) {
        $result = array();
        $createfinalshareparams = [
            'path' => $path,
            'shareType' => ocs_client::SHARE_TYPE_USER,
            'publicUpload' => false,
            'permissions' => ocs_client::SHARE_PERMISSION_READ
        ];

        // File is now shared with the system account.
        // todo: insert check for responsecreateshare 100 success.
        $this->systemocsclient = new ocs_client(\core\oauth2\api::get_system_oauth_client($this->issuer));

        $responsecreateshare = $this->systemocsclient->call('create_share', $createfinalshareparams);
        $xml = simplexml_load_string($responsecreateshare);
        $result['success'] = $xml->meta->statuscode;
        $result['link'] = (string) $xml->data->url;
        $result['id'] = (string) $xml->data->id;
        return $result;
    }

    /** Deletes the share of the sysaccount and the dataowner.
     * @param $shareid
     * @return mixed
     */
    private function delete_share_dataowner_sysaccount($shareid) {
        $deleteshareparams = [
            'share_id' => $shareid
        ];
        $deleteshareresponse = $this->ocsclient->call('delete_share', $deleteshareparams);
        $xml = simplexml_load_string($deleteshareresponse);
        return $xml->meta->statuscode;
    }

    /** Creates a share between the dataowner and the sysaccount.
     * @param $source
     * @param $username dependent on the direction systemaccountname or other username
     * @param $temp Time until the share expires
     * @param $direction true for sharing with the sysaccount, false for sharing with the secondperson
     * @return array statuscode and shareid
     */
    private function create_share_user_sysaccount($source, $username, $temp, $direction) {
        $result = array();
        // todo: maybe set expiration date?
        $path = $source;
        $functionsargs = null;
        $time = time();
        $expiration = $time + $temp;
        $test = (string) $expiration;
        if ($direction === false) {
            $source = $source->get_reference();
            $jsondecode = json_decode($source);
            $path = $jsondecode->link;
        }
        $createtempshareparams = [
            'path' => $path,
            'shareType' => ocs_client::SHARE_TYPE_USER,
            'publicUpload' => false,
            'expiration' => $test,
            'shareWith' => $username,
        ];

        // File is now shared with the system account.
        if ($direction === true) {
            $createshareresponse = $this->ocsclient->call('create_share', $createtempshareparams);
        } else {
            $this->systemocsclient = new ocs_client(\core\oauth2\api::get_system_oauth_client($this->issuer));
            $createshareresponse = $this->systemocsclient->call('create_share', $createtempshareparams);
        }
        $xml = simplexml_load_string($createshareresponse);

        // todo: check statuscode also for share already exist
        $result['statuscode'] = $xml->meta->statuscode;
        $result['shareid'] = $xml->data->id;
        $result['filetarget'] = ((string)$xml->data[0]->file_target);
        return $result;
    }

    /** Copy a file to a new place
     * @param string $srcpath source path
     * @param string $dstpath
     * @param /repository/owncloud/owncloud_client $sysdav
     * @return array
     */
    private function copy_file_to_path($srcpath, $dstpath, $sysdav) {
        $result = array();
        $sysdav->open();
        $webdavendpoint = $this->issuer->get_endpoint_url('webdav');
        $baseurl = $this->issuer->get('baseurl');
        $path = trim($webdavendpoint, $baseurl);
        $prefixwebdav = rtrim('/'.ltrim($path, '/ '), '/ ');

        $sourcepath = $prefixwebdav . $srcpath;
        $destinationpath = $prefixwebdav . $dstpath . '/' . $srcpath;

        $result['success'] = $sysdav->copy_file($sourcepath, $destinationpath, true);
        $sysdav->close();
        return $result;
    }

    /** Creates a unique folder path for the access controlled link.
     * @param $context
     * @param $component
     * @param $filearea
     * @param int $itemid
     * @return array $result success for the http status code and fullpath for the generated path.
     */
    private function create_folder_path_access_controlled_links($context, $component, $filearea, $itemid, $sysdav) {
        // Initialize the return array.
        $result = array();
        $result['success'] = true;

        // The fullpath to store the file is generated from the context.
        $fullpath = '';
        $allfolders = [];

        $contextlist = array_reverse($context->get_parent_contexts(true));

        foreach ($contextlist as $context) {
            // Make sure a folder exists here.
            $foldername = clean_param($context->get_context_name(), PARAM_PATH);
            $allfolders[] = $foldername;
        }

        $allfolders[] = clean_param($component, PARAM_PATH);
        $allfolders[] = clean_param($filearea, PARAM_PATH);
        $allfolders[] = clean_param($itemid, PARAM_PATH);

        // Extracts the end of the webdavendpoint.
        $webdavendpoint = $this->issuer->get_endpoint_url('webdav');
        $baseurl = $this->issuer->get('baseurl');
        $path = trim($webdavendpoint, $baseurl);
        $prefixwebdav = rtrim('/'.ltrim($path, '/ '), '/ ');
        // Checks whether folder exist and creates non-existent folders.
        foreach ($allfolders as $foldername) {
            $sysdav->open();
            $fullpath .= '/' . $foldername;
            $proof = $sysdav->is_dir($fullpath);
            // Folder already exist, continue
            if ($proof) {
                $sysdav->close();
                continue;
            }
            $sysdav->open();
            $response = $sysdav->mkcol($prefixwebdav . $fullpath);

            $sysdav->close();
            // todo: break/exception when status code !=201?
            if ($response != 201) {
                    $result['success'] = false;
                    continue;
            }
        }
        $result['fullpath'] = $fullpath;

        return $result;
    }

    /** Creates a new owncloud_client for the system account.
     * todo: check whether instead a change_client method should be provided in the owncloud_client class.
     * Advantages to insert method: more modular, create less overhead
     * Disadvantage: diff to original class gets bigger
     *
     * @param $systemauth
     * @return \repository_owncloud\owncloud_client
     */
    private function create_system_dav($systemauth) {
        $webdavendpoint = $this->parse_endpoint_url('webdav');

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

        // Authentication method is `bearer` for OAuth 2. Pass oauth client from which WebDAV obtains the token when needed.
        $dav = new repository_owncloud\owncloud_client($server, '', '', 'bearer', $webdavtype,
            $systemauth->get_accesstoken()->token, $webdavendpoint['path']);

        $dav->port = $webdavport;
        $dav->debug = false;
        return $dav;
    }

    /**
     * Repository method that serves the referenced file (created e.g. via get_link).
     * All parameters are there for compatibility with superclass, but they are ignored.
     *
     * @param stored_file $storedfile (ignored)
     * @param int $lifetime (ignored)
     * @param int $filter (ignored)
     * @param bool $forcedownload (ignored)
     * @param array $options (ignored)
     */
    public function send_file($storedfile, $lifetime=null , $filter=0, $forcedownload=false, array $options = null) {
        // TODO 1. assure the user has is logged in.
        if (empty($this->client)) {
            $details = 'Cannot connect as current user';
            throw new repository_exception('errorwhilecommunicatingwith', 'repository', '', $details);
        }
        if (!$this->client->is_logged_in()) {
            // TODO: temporary solution, works but is ugly.
            $this->print_login_popup(['style' => 'margin-top: 250px']);
            exit;
        }

        // TODO 2. Create private share
        $userinfo = $this->client->get_userinfo();

        $username = $userinfo['username'];
        $response = $this->create_share_user_sysaccount($storedfile, $username, 1440, false);
        // Path can only be generated when share was successfull.
        if (!empty($reponse)) {
            $statuscode = $response['statuscode'];
            if ($statuscode == 100 || 403) {
                $baseurl = $this->issuer->get('baseurl');
                $baseurl = rtrim($baseurl, '/');
                if ($response['statuscode'] == 100) {
                    $path = $baseurl . $response['filetarget'];
                } else {
                    // Create path without shareinformation. This is the case when old private share is still valid.
                    $directory = $storedfile->get_filepath();
                    $name = $storedfile->get_filename();
                    $path = $directory . $name;
                }
                header('Location: ' . $baseurl . $path);
            } else {
                send_file_not_found();
            }
        } else {
            send_file_not_found();
        }
    }
    public function print_login_popup($attr = null) {
        global $OUTPUT, $PAGE;

        $url = new moodle_url($this->client->get_login_url());
        $state = $url->get_param('state') . '&reloadparent=true';
        $url->param('state', $state);

        $PAGE->set_pagelayout('embedded');
        echo $OUTPUT->header();

        $repositoryname = get_string('pluginname', 'repository_owncloud');

        $button = new single_button($url, get_string('logintoaccount', 'repository', $repositoryname), 'post', true);
        $button->add_action(new popup_action('click', $url, 'Login'));
        $button->class = 'mdl-align';
        $button = $OUTPUT->render($button);
        echo html_writer::div($button, '', $attr);

        echo $OUTPUT->footer();
    }
    /**
     * Which return type should be selected by default.
     *
     * @return int
     */
    public function default_returntype() {
        return FILE_CONTROLLED_LINK;

    }
    protected function add_temp_writer_to_file(\repository_googledocs\rest $client, $fileid, $email) {
        // Expires in 7 days.
        $expires = new DateTime();
        $expires->add(new DateInterval("P7D"));

        $updateeditor = [
            'emailAddress' => $email,
            'role' => 'writer',
            'type' => 'user',
            'expirationTime' => $expires->format(DateTime::RFC3339)
        ];
        $params = ['fileid' => $fileid, 'sendNotificationEmail' => 'false'];
        $response = $client->call('create_permission', $params, json_encode($updateeditor));
        if (empty($response->id)) {
            $details = 'Cannot add user ' . $email . ' as a writer for document: ' . $fileid;
            throw new repository_exception('errorwhilecommunicatingwith', 'repository', '', $details);
        }
        return true;
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
        $loginurl = $client->get_login_url();
        if ($this->options['ajax']) {
            $ret = array();
            $btn = new \stdClass();
            $btn->type = 'popup';
            $btn->url = $loginurl->out(false);
            $ret['login'] = array($btn);
            return $ret;
        } else {
            echo html_writer::link($loginurl, get_string('login', 'repository'),
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
     * @link https://tracker.moodle.org/browse/MDL-59512
     */
    public function callback() {
        $client = $this->get_user_oauth_client();
        // If an Access Token is stored within the client, it has to be deleted to prevent the addition
        // of an Bearer authorization header in the request method.
        $client->log_out();
        // A patch for the support of basic authentication for the oauth2 client is proposed in MDL-59512.
        // With the modifications the setHeader method is unnecessary. However, when using the plugin with the moodle
        // core the lines are indispensable. Therefore, they are retained.
        if (false) {
            $client->setHeader(array(
                'Authorization: Basic ' . base64_encode($client->get_clientid() . ':' . $client->get_clientsecret())
            ));
        }
        // This will upgrade to an access token if we have an authorization code and save the access token in the session.
        $client->is_logged_in();
    }

    /**
     * Create an instance for this plug-in
     *
     * @param string $type the type of the repository
     * @param int $userid the user id
     * @param stdClass $context the context
     * @param array $params the options for this instance
     * @param int $readonly whether to create it readonly or not (defaults to not)
     * @return mixed
     */
    public static function create($type, $userid, $context, $params, $readonly=0) {
        require_capability('moodle/site:config', context_system::instance());
        return parent::create($type, $userid, $context, $params, $readonly);
    }

    /**
     * This method adds a select form and additional information to the settings form..
     *
     * @param \moodleform $mform Moodle form (passed by reference)
     */
    public static function instance_config_form($mform) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            $mform->addElement('static', null, '',  get_string('nopermissions', 'error', get_string('configplugin',
                'repository_owncloud')));
            return false;
        }

        // Load configured issuers.
        $issuers = core\oauth2\api::get_all_issuers();
        $types = array();

        // Validates which issuers implement the right endpoints. WebDav is necessary for ownCloud.
        $validissuers = [];
        foreach ($issuers as $issuer) {
            $types[$issuer->get('id')] = $issuer->get('name');
            if (self::is_valid_issuer($issuer)) {
                $validissuers[] = $issuer->get('name');
            }
        }

        // Render the form.
        $url = new \moodle_url('/admin/tool/oauth2/issuers.php');
        $mform->addElement('static', null, '', get_string('oauth2serviceslink', 'repository_owncloud', $url->out()));

        $mform->addElement('select', 'issuerid', get_string('chooseissuer', 'repository_owncloud'), $types);
        $mform->addRule('issuerid', get_string('required'), 'required', null, 'issuer');
        $mform->addHelpButton('issuerid', 'chooseissuer', 'repository_owncloud');
        $mform->setType('issuerid', PARAM_INT);

        // All issuers that are valid are displayed seperately (if any).
        if (count($validissuers) === 0) {
            $mform->addElement('html', get_string('no_right_issuers', 'repository_owncloud'));
        } else {
            $mform->addElement('html', get_string('right_issuers', 'repository_owncloud', implode(', ', $validissuers)));
        }
    }

    /**
     * Save settings for repository instance
     *
     * @param array $options settings
     * @return bool
     */
    public function set_option($options = array()) {
        $options['issuerid'] = clean_param($options['issuerid'], PARAM_INT);
        $ret = parent::set_option($options);
        return $ret;
    }

    /**
     * Names of the plugin settings
     *
     * @return array
     */
    public static function get_instance_option_names() {
        return ['issuerid'];
    }

    /**
     * Method to define which file-types are supported (hardcoded can not be changed in Admin Menu)
     * By default FILE_INTERNAL is supported. In case a system account is connected and an issuer exist,
     * FILE_CONTROLLED_LINK is supported.
     * FILE_INTERNAL - the file is uploaded/downloaded and stored directly within the Moodle file system.
     * FILE_CONTROLLED_LINK - creates a controlled version of the file at the determined place.
     * The file itself can not be changed any longer by the owner.
     * @return int return type bitmask supported
     */
    public function supported_returntypes() {
        if (!empty($this->issuer) && $this->issuer->is_system_account_connected()) {
            // TODO: decide whether extra setting for supportedreturntypes is needed
            return FILE_CONTROLLED_LINK | FILE_INTERNAL;
        } else {
            return FILE_INTERNAL;
        }
    }

    /**
     * Returns the parsed url of the chosen endpoint.
     * @param string $endpointname
     * @return array parseurl [scheme => https/http, host=>'hostname', port=>443, path=>'path']
     * @throws \repository_owncloud\configuration_exception if an endpoint is undefined
     */
    private function parse_endpoint_url($endpointname) {
        $url = $this->issuer->get_endpoint_url($endpointname);
        if (empty($url)) {
            throw new \repository_owncloud\configuration_exception(sprintf('Endpoint %s not defined.', $endpointname));
        }
        return parse_url($url);
    }

    /**
     * Take the WebDAV `ls()' output and convert it into a format that Moodle's filepicker understands.
     *
     * @param string $dirpath Relative (urlencoded) path of the folder of interest.
     * @param array $ls Output by WebDAV
     * @return array Moodle-formatted list of directory contents; ready for use as $ret['list'] in get_listings
     */
    private function get_listing_convert_response($dirpath, $ls) {
        global $OUTPUT;
        $folders = array();
        $files = array();
        // TODO (#6) handle (base)path in client, not here.
        $parsedurl = $this->parse_endpoint_url('webdav');
        $basepath = rtrim('/' . ltrim($parsedurl['path'], '/ '), '/ ');

        foreach ($ls as $item) {
            if (!empty($item['lastmodified'])) {
                $item['lastmodified'] = strtotime($item['lastmodified']);
            } else {
                $item['lastmodified'] = null;
            }

            // Extracting object title from absolute path: First remove ownCloud basepath.
            $item['href'] = substr(urldecode($item['href']), strlen($basepath));
            // Then remove relative path to current folder.
            $title = substr($item['href'], strlen($dirpath));

            if (!empty($item['resourcetype']) && $item['resourcetype'] == 'collection') {
                // A folder.
                if ($dirpath == $item['href']) {
                    // Skip "." listing.
                    continue;
                }

                $folders[strtoupper($title)] = array(
                    'title' => rtrim($title, '/'),
                    'thumbnail' => $OUTPUT->image_url(file_folder_icon(90))->out(false),
                    'children' => array(),
                    'datemodified' => $item['lastmodified'],
                    'path' => $item['href']
                );
            } else {
                // A file.
                $size = !empty($item['getcontentlength']) ? $item['getcontentlength'] : '';
                $files[strtoupper($title)] = array(
                    'title' => $title,
                    'thumbnail' => $OUTPUT->image_url(file_extension_icon($title, 90))->out(false),
                    'size' => $size,
                    'datemodified' => $item['lastmodified'],
                    'source' => $item['href']
                );
            }
        }
        ksort($files);
        ksort($folders);
        return array_merge($folders, $files);
    }

    /**
     * Prepare response of get_listing; namely
     * - defining setting elements,
     * - filling in the parent path of the currently-viewed directory.
     * @param string $path Relative path
     * @return array ret array for use as get_listing's $ret
     */
    private function get_listing_prepare_response($path) {
        $ret = [
            // Fetch the list dynamically. An AJAX request is sent to the server as soon as the user opens a folder.
            'dynload' => true,
            'nosearch' => true, // Disable search.
            'nologin' => false, // Provide a login link because a user logs into his/her private ownCloud storage.
            'path' => array([ // Contains all parent paths to the current path.
                'name' => $this->get_meta()->name,
                'path' => '',
            ]),
            'list' => array(), // Contains all file/folder information and is required to build the file/folder tree.
        ];

        // If relative path is a non-top-level path, calculate all its parents' paths.
        // This is used for navigation in the file picker.
        if ($path != '/') {
            $chunks = explode('/', trim($path, '/'));
            $parent = '/';
            // Every sub-path to the last part of the current path is a parent path.
            foreach ($chunks as $chunk) {
                $subpath = $parent . $chunk . '/';
                $ret['path'][] = [
                    'name' => urldecode($chunk),
                    'path' => $subpath
                ];
                // Prepare next iteration.
                $parent = $subpath;
            }
        }
        return $ret;
    }
}