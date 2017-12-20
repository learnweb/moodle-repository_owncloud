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



class access_controlled_link_manager{
    /**
     * OCS client that uses the Open Collaboration Services REST API.
     * @var ocs_client
     */
    private $ocsclient;

    /**
     * Client to manage oauth2 features from the systemaccount.
     * @var systemoauthclient
     */
    private $systemoauthclient;
    /**
     * Client to manage webdav request from the systemaccount..
     * @var systemwebdavclient
     */
    private $systemwebdavclient;
    /**
     * Issuer from the oauthclient.
     * @var issuer
     */
    private $issuer;
    /**
     * Name of the related repository.
     * @var repositoryname
     */
    private $repositoryname;

    /**
     * access_controlled_link_manager constructor.
     * @param $ocsclient
     * @param $issuer
     * @param $repositoryname
     * @throws \coding_exception
     * @throws \moodle_exception
     * @throws configuration_exception
     * @throws request_exception
     */
    public function __construct($ocsclient, $issuer, $repositoryname) {
        $this->ocsclient = $ocsclient;
        $this->systemoauthclient = \core\oauth2\api::get_system_oauth_client($issuer);
        $this->repositoryname = $repositoryname;
        if ($this->systemoauthclient === false) {
            if ($this->systemoauthclient->is_logged_in() === false) {
                $details = get_string('contactadminwith', 'repository_owncloud',
                    'The systemaccount could not be connected.');
                throw new \repository_owncloud\request_exception(array('instance' => $repositoryname, 'errormessage' => $details));
            }
        }
        $this->issuer = $issuer;
        $this->systemwebdavclient = $this->create_system_dav();
    }
    /** Deletes the share of the sysaccount and the dataowner.
     * @param $shareid
     */
    public function delete_share_dataowner_sysaccount($shareid) {
        $deleteshareparams = [
            'share_id' => $shareid
        ];
        $deleteshareresponse = $this->ocsclient->call('delete_share', $deleteshareparams);
        $xml = simplexml_load_string($deleteshareresponse);

        if ($xml->meta->statuscode != 100) {
            \core\notification::warning('You just shared a file with a access controlled link.
             However, the share between you and the systemaccount could not be deleted and is still present in your instance.');
        }
    }

    /** Creates a share between the dataowner and the sysaccount.
     * @param $source
     * @param $temp Time until the share expires
     * @param $direction true for sharing with the sysaccount, false for sharing with the secondperson
     * @return array statuscode and shareid
     * @throws \coding_exception
     * @throws \moodle_exception
     * @throws \repository_owncloud\request_exception
     */
    public function create_share_user_sysaccount($source, $temp, $direction) {
        $result = array();
        $path = $source;
        $expiration = time() + $temp;
        $dateofexpiration = (string) $expiration;

        if ($direction === false) {
            $source = $source->get_reference();
            $jsondecode = json_decode($source);
            $path = $jsondecode->link;
        } else {
            $systemuserinfo = $this->systemoauthclient->get_userinfo();
            $username = $systemuserinfo['username'];
        }
        $createshareparams = [
            'path' => $path,
            'shareType' => ocs_client::SHARE_TYPE_USER,
            'publicUpload' => false,
            'expiration' => $dateofexpiration,
            'shareWith' => $username,
        ];

        // File is now shared with the system account.
        if ($direction === true) {
            $createshareresponse = $this->ocsclient->call('create_share', $createshareparams);
        } else {
            if (empty($this->systemoauthclient)) {
                $this->systemoauthclient = new ocs_client(\core\oauth2\api::get_system_oauth_client($this->issuer));
            }
            $createshareresponse = $this->systemoauthclient->call('create_share', $createshareparams);
        }
        $xml = simplexml_load_string($createshareresponse);

        $result['statuscode'] = $xml->meta->statuscode;
        $result['shareid'] = $xml->data->id;
        $result['fileid'] = $xml->data->item_source;
        $result['filetarget'] = ((string)$xml->data[0]->file_target);
        if ($result['statuscode'] != 100 && $result['statuscode'] != 403) {
            $details = get_string('filenotaccessed', 'repository_owncloud');
            throw new \repository_owncloud\request_exception(array('instance' => $this->repositoryname, 'errormessage' => $details));
        }
        return $result;
    }

    /** Copy a file to a new place
     * @param string $srcpath source path
     * @param string $dstpath
     * @throws configuration_exception
     * @throws \coding_exception
     * @throws \moodle_exception
     * @throws \repository_owncloud\request_exception
     */
    public function copy_file_to_path($srcpath, $dstpath) {
        // TODO: srcpath might be a confusing name.
        $result = array();
        $this->systemwebdavclient->open();
        $webdavendpoint = $this->parse_endpoint_url('webdav');

        $srcpath = ltrim($srcpath, '/');
        $sourcepath = $webdavendpoint['path'] . $srcpath;
        $destinationpath = $webdavendpoint['path'] . $dstpath . '/' . $srcpath;

        $result = $this->systemwebdavclient->copy_file($sourcepath, $destinationpath, true);
        $this->systemwebdavclient->close();
        if ($result != 201) {
            $details = get_string('contactadminwith', 'repository_owncloud',
                'A webdav request to copy a file to the systemaccount failed.');
            throw new \repository_owncloud\request_exception(array('instance' => $this->repositoryname, 'errormessage' => $details));
        }
    }
    /** Copy a file to a new place - uses a separate variable for webdavclient since it is the one from the user not
     * from the systemaccount.
     * @param string $srcpath source path
     * @param string $dstpath
     * @param string $webdavclient
     * @return array
     * @throws configuration_exception
     */
    public function move_file_to_folder($srcpath, $dstpath, $webdavclient) {
        $result = array();
        $webdavclient->open();
        $webdavendpoint = $this->parse_endpoint_url('webdav');

        $sourcepath = $webdavendpoint['path'] . $srcpath;
        $destinationpath = $webdavendpoint['path'] . $dstpath . '/' . $srcpath;

        $result['success'] = $webdavclient->move($sourcepath, $destinationpath, false);
        $webdavclient->close();
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
            // Todo: break/exception when status code !=201?
            if ($response != 201) {
                $result['success'] = false;
                continue;
            }
        }
        if ($result['success'] != true) {
            $details = get_string('contactadminwith', 'repository_owncloud',
                'Folder path in the systemaccount could not be created.');
            throw new \repository_owncloud\request_exception(array('instance' => $this->repositoryname, 'errormessage' => $details));
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
            $this->systemoauthclient->get_accesstoken()->token,
            $webdavendpoint['path']);

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
            throw new \repository_owncloud\configuration_exception(sprintf('Endpoint %s not defined.', $endpointname));
        }
        return parse_url($url);
    }

}