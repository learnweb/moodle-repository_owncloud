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
namespace repository_owncloud;

use \core\oauth2\api;
use \core\notification;

defined('MOODLE_INTERNAL') || die();

class access_controlled_link_manager{
    /**
     * OCS client that uses the Open Collaboration Services REST API.
     * @var ocs_client
     */
    private $ocsclient;

    /**
     * Client to manage oauth2 features from the systemaccount.
     * @var \core\oauth2\client
     */
    private $systemoauthclient;
    /**
     * Client to manage webdav request from the systemaccount..
     * @var ocs_client
     */
    private $systemwebdavclient;
    /**
     * Issuer from the oauthclient.
     * @var \core\oauth2\issuer
     */
    private $issuer;
    /**
     * Name of the related repository.
     * @var string
     */
    private $repositoryname;

    /**
     * Access_controlled_link_manager constructor.
     * @param ocs_client $ocsclient
     * @param \core\oauth2\issuer $issuer
     * @param string $repositoryname
     * @throws \coding_exception
     * @throws \moodle_exception
     * @throws configuration_exception
     * @throws request_exception
     */
    public function __construct($ocsclient, $issuer, $repositoryname) {
        $this->ocsclient = $ocsclient;
        $this->systemoauthclient = api::get_system_oauth_client($issuer);
        $this->repositoryname = $repositoryname;
        if ($this->systemoauthclient === false || $this->systemoauthclient->is_logged_in() === false) {
            $details = get_string('contactadminwith', 'repository_owncloud',
                'The systemaccount could not be connected.');
            throw new request_exception(array('instance' => $repositoryname, 'errormessage' => $details));

        }
        $this->issuer = $issuer;
        $this->systemwebdavclient = $this->create_system_dav();
    }
    /** Deletes the share of the systemaccount and a user. In case the share could not be deleted a notification is
     * displayed.
     * @param $shareid
     */
    public function delete_share_dataowner_sysaccount($shareid) {
        $deleteshareparams = [
            'share_id' => $shareid
        ];
        $deleteshareresponse = $this->ocsclient->call('delete_share', $deleteshareparams);
        $xml = simplexml_load_string($deleteshareresponse);

        if ($xml->meta->statuscode != 100) {
            notification::warning('You just shared a file with a access controlled link.
             However, the share between you and the systemaccount could not be deleted and is still present in your instance.');
        }
    }

    /** Creates a share between a user and the systemaccount. If the variable username is set the file is shared with the
     * corresponding user otherwise with the systemaccount.
     * @param $source
     * @param int $timespan miliseconds until the share expires
     * @param string $username optional when set the file is shared with the corresponding user otherwise with
     * the systemaccount.
     * @return array statuscode, shareid, and filetarget
     * @throws \coding_exception
     * @throws \moodle_exception
     * @throws \repository_owncloud\request_exception
     */
    public function create_share_user_sysaccount($source, $timespan, $username = null) {
        $result = array();
        $path = $source;
        $expirationunix = time() + $timespan;
        $expiration = (string) date('Y-m-d', $expirationunix);
        // Expiration need to be in 'YYYY-MM-DD' format.

        if ($username != null) {
            $source = $source->get_reference();
            $jsondecode = json_decode($source);
            $path = $jsondecode->link;
            $shareusername = $username;
        } else {
            $systemuserinfo = $this->systemoauthclient->get_userinfo();
            $shareusername = $systemuserinfo['username'];
        }
        $createshareparams = [
            'path' => $path,
            'shareType' => ocs_client::SHARE_TYPE_USER,
            'publicUpload' => false,
            'expireDate' => $expiration,
            'shareWith' => $shareusername,
        ];

        // File is now shared with the system account.
        if ($username === null) {
            $createshareresponse = $this->ocsclient->call('create_share', $createshareparams);
        } else {
            $systemoauthclient = new ocs_client(\core\oauth2\api::get_system_oauth_client($this->issuer));
            $createshareresponse = $systemoauthclient->call('create_share', $createshareparams);
        }
        $xml = simplexml_load_string($createshareresponse);

        $statuscode = $xml->meta->statuscode;
        if ($statuscode != 100 && $statuscode != 403) {
            $details = get_string('filenotaccessed', 'repository_owncloud');
            throw new request_exception(get_string('request_exception',
                'repository_owncloud', array('instance' => $this->repositoryname, 'errormessage' => $details)));
        }
        $result['shareid'] = $xml->data->id;
        $result['statuscode'] = $statuscode;
        $result['filetarget'] = ((string)$xml->data[0]->file_target);

        return $result;
    }

    /** Copy a file to a new path.
     * @param string $srcpath source path
     * @param string $dstpath
     * @param string $operation move or copy
     * @throws configuration_exception
     * @throws \coding_exception
     * @throws \moodle_exception
     * @throws \repository_owncloud\request_exception
     */
    public function transfer_file_to_path($srcpath, $dstpath, $operation, $webdavclient = null) {
        $this->systemwebdavclient->open();
        $webdavendpoint = $this->parse_endpoint_url('webdav');

        $srcpath = ltrim($srcpath, '/');
        $sourcepath = $webdavendpoint['path'] . $srcpath;
        $dstpath = ltrim($dstpath, '/');
        $destinationpath = $webdavendpoint['path'] . $dstpath . '/' . $srcpath;

        if ($operation === 'copy') {
            $result = $this->systemwebdavclient->copy_file($sourcepath, $destinationpath, true);
        } else if ($operation === 'move') {
            $result = $webdavclient->move($sourcepath, $destinationpath, false);
        }
        $this->systemwebdavclient->close();
        if (!($result == 201 || $result == 412)) {
            $details = get_string('contactadminwith', 'repository_owncloud',
                'A webdav request to ' . $operation . ' a file failed.');
            throw new request_exception(array('instance' => $this->repositoryname,
                'errormessage' => $details));
        }
        return $result;
    }

    /** Creates a unique folder path for the access controlled link.
     * @param $context
     * @param $component
     * @param $filearea
     * @param int $itemid
     * @return array $result success for the http status code and fullpath for the generated path.
     * @throws configuration_exception
     * @throws \coding_exception
     * @throws \moodle_exception
     * @throws \repository_owncloud\request_exception
     */
    public function create_folder_path_access_controlled_links($context, $component, $filearea, $itemid) {
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
        $parsedwebdavurl = $this->parse_endpoint_url('webdav');
        $webdavprefix = $parsedwebdavurl['path'];
        // Checks whether folder exist and creates non-existent folders.
        foreach ($allfolders as $foldername) {
            $this->systemwebdavclient->open();
            $fullpath .= '/' . $foldername;
            $isdir = $this->systemwebdavclient->is_dir($webdavprefix . $fullpath);
            // Folder already exist, continue.
            if ($isdir === true) {
                $this->systemwebdavclient->close();
                continue;
            }
            $this->systemwebdavclient->open();
            $response = $this->systemwebdavclient->mkcol($webdavprefix . $fullpath);

            $this->systemwebdavclient->close();
            if ($response != 201) {
                $result['success'] = false;
                continue;
            }
        }
        if ($result['success'] != true) {
            $details = get_string('contactadminwith', 'repository_owncloud',
                'Folder path in the systemaccount could not be created.');
            throw new request_exception(array('instance' => $this->repositoryname,
                'errormessage' => $details));
        }
        $result['fullpath'] = $fullpath;

        return $result;
    }

    /** Creates a new owncloud_client for the system account.
     * @return owncloud_client
     * @throws configuration_exception
     */
    public function create_system_dav() {
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
        $dav = new owncloud_client($server, '', '', 'bearer', $webdavtype,
            $this->systemoauthclient->get_accesstoken()->token, $webdavendpoint['path']);

        $dav->port = $webdavport;
        $dav->debug = false;
        return $dav;
    }
    /**
     * Returns the parsed url of the chosen endpoint.
     * @param string $endpointname
     * @return array parseurl [scheme => https/http, host=>'hostname', port=>443, path=>'path']
     * @throws \repository_owncloud\configuration_exception if an endpoint is undefined
     */
    public function parse_endpoint_url($endpointname) {
        $url = $this->issuer->get_endpoint_url($endpointname);
        if (empty($url)) {
            throw new configuration_exception(sprintf('Endpoint %s not defined.', $endpointname));
        }
        return parse_url($url);
    }

    /** Creates a folder to store access controlled links.
     * @param string $controlledlinkfoldername
     * @param /repository/owncloud/owncloud_client $webdavclient
     * @throws \coding_exception
     * @throws configuration_exception
     * @throws request_exception
     */
    public function create_storage_folder($controlledlinkfoldername, $webdavclient) {
        $parsedwebdavurl = $this->parse_endpoint_url('webdav');
        $webdavprefix = $parsedwebdavurl['path'];
        // Checks whether folder exist and creates non-existent folders.
        $webdavclient->open();
        $isdir = $webdavclient->is_dir($webdavprefix . $controlledlinkfoldername);
        // Folder already exist, continue.
        if (!$isdir) {
            $responsecreateshare = $webdavclient->mkcol($webdavprefix . $controlledlinkfoldername);

            if ($responsecreateshare != 201) {
                // TODO copy is maybe possible.
                $webdavclient->close();
                throw new request_exception(array('instance' => $this->repositoryname,
                    'errormessage' => get_string('contactadminwith', 'repository_owncloud',
                    'The folder to store files in the user account could not be created.')));
            }
        }
        $webdavclient->close();
    }

    /** Gets all shares from a path (the path is file specific) and extracts the share of a specific user. In case
     * multiple shares exist the first one is taken. Multiple shares can only appear when shares are created outside
     * of this plugin, therefore this case is not handled.
     * @param stored_file $storedfile
     * @param string $username
     * @return \SimpleXMLElement
     * @throws \moodle_exception
     */
    public function get_shares_from_path($storedfile, $username) {
        $source = $storedfile->get_reference();
        $jsondecode = json_decode($source);
        $path = $jsondecode->link;
        $ocsparams = [
            'path' => $path,
            'reshares' => true
        ];
        $systemocsclient = new ocs_client(api::get_system_oauth_client($this->issuer));

        $getsharesresponse = $systemocsclient->call('get_shares', $ocsparams);
        $xml = simplexml_load_string($getsharesresponse);
        $validelement = array();
        foreach ($fileid = $xml->data->element as $element) {
            if ($element->share_with == $username) {
                $validelement = $element;
                break;
            }
        }
        return $validelement->id;
    }

    /** This method can only be used if the response is from a newly created share. In this case there is more information
     * in the response. For a reference visit https://doc.owncloud.org/server/10.0/developer_manual/core/ocs-share-api.html.
     * @param int $shareid
     * @param string $username
     * @return mixed the id of the share
     * @throws \coding_exception
     * @throws \repository_owncloud\request_exception
     */
    public function get_share_information_from_shareid($shareid, $username) {
        $ocsparams = [
            'share_id' => $shareid
        ];
        $shareinformation = $this->ocsclient->call('get_information_of_share', $ocsparams);
        $xml = simplexml_load_string($shareinformation);
        foreach ($fileid = $xml->data->element as $element) {
            if ($element->share_with == $username) {
                $validelement = $element;
                break;
            }
        }
        if (empty($validelement)) {
            throw new request_exception(array('instance' => $this->repositoryname,
                'errormessage' => get_string('filenotaccessed', 'repository_owncloud')));

        }
        return (string) $validelement->file_target;
    }
}