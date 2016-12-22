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
 * Sciebo Repository Plugin
 *
 * @package    repository_Sciebo
 * @copyright  2016 Westfälische Universität Münster (WWU Münster)
 * @author     Projektseminar Uni Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/repository/lib.php');
use tool_oauth2sciebo\sciebo;
/**
 * sciebo repository plugin.
 *
 * @package    repository_Sciebo
 * @copyright  2016 Westfälische Universität Münster (WWU Münster)
 * @author     Projektseminar Uni Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_sciebo extends repository {

    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        parent::__construct($repositoryid, $context, $options);

        // The WebDav client is no longer handled in here.
        $returnurl = new moodle_url('/repository/repository_callback.php', [
            'callback'  => 'yes',
            'repo_id'   => $repositoryid,
            'sesskey'   => sesskey(),
        ]);

        // The Sciebo Object, which is described in the Admin Tool oauth2sciebo
        // is created. From now on is will handle all interactions with the Sciebo OAuth2 Client.
        $this->sciebo = new sciebo($returnurl);
    }

    /**
     * This function does exactly the same as in the WebDAV repository. The only difference is, that
     * the Sciebo OAuth2 client uses OAuth2 instead of Basic Authentication.
     * @param string $url relative path to the file.
     * @param string $title title of the file.
     * @return array|bool returns either the moodle path to the file or false.
     */
    public function get_file($url, $title = '') {
        $url = urldecode($url);
        $path = $this->prepare_file($title);
        if (!$this->sciebo->dav->open()) {
            return false;
        }
        $webdavpath = rtrim('/'.ltrim(get_config('tool_oauth2sciebo', 'path'), '/ '), '/ '); // Without slash in the end.
        $this->sciebo->get_file($webdavpath . $url, $path);
        $this->logout();

        return array('path' => $path);
    }

    /**
     * Searching is not available at the moment.
     * @return bool false
     */
    public function global_search() {
        return false;
    }

    /**
     * This function does exactly the same as in the WebDAV repository. The only difference is, that
     * the Sciebo OAuth2 client uses OAuth2 instead of Basic Authentication.
     * @param string $path relative path the the directory or file.
     * @param string $page
     * @return array directory properties.
     */
    public function get_listing($path='', $page = '') {
        global $CFG, $OUTPUT;
        $list = array();
        $ret  = array();
        $ret['dynload'] = true;
        $ret['nosearch'] = true;
        $ret['nologin'] = false;
        $ret['path'] = array(array('name' => get_string('webdav', 'repository_webdav'), 'path' => ''));
        $ret['list'] = array();

        if (!$this->sciebo->dav->open()) {
            return $ret;
        }
        $webdavpath = rtrim('/'.ltrim(get_config('tool_oauth2sciebo', 'path'), '/ '), '/ ');
        if (empty($path) || $path == '/') {
            $path = '/';
        } else {
            $chunks = preg_split('|/|', trim($path, '/'));
            for ($i = 0; $i < count($chunks); $i++) {
                $ret['path'][] = array(
                    'name' => urldecode($chunks[$i]),
                    'path' => '/'. join('/', array_slice($chunks, 0, $i + 1)). '/'
                );
            }
        }

        // The WebDav methods are getting outsourced and encapsulated to the sciebo class.
        $dir = $this->sciebo->get_listing($webdavpath. urldecode($path));

        if (!is_array($dir)) {
            return $ret;
        }
        $folders = array();
        $files = array();
        foreach ($dir as $v) {
            if (!empty($v['lastmodified'])) {
                $v['lastmodified'] = strtotime($v['lastmodified']);
            } else {
                $v['lastmodified'] = null;
            }

            // Remove the server URL from the path (if present), otherwise links will not work - MDL-37014.
            $server = preg_quote(get_config('tool_oauth2sciebo', 'server'));
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
     * Method to generate a downloadlink for a chosen file (in the file picker).
     * Creates a share for the chosen file and fetches the specific file ID through
     * the OCS Share API (ownCloud).
     * TODO Authorization process has to be implemented differently, since username and password are
     * TODO required at the moment.
     * @param string $url relative path to the chosen file
     * @return string returns the generated downloadlink
     * @throws repository_exception if $url is empty an exception is thrown
     */
    public function get_link($url) {
        $username = $this->get_config('tool_oauth2sciebo', 'user');
        $password = $this->get_config('tool_oauth2sciebo', 'pass');

        if (get_config('tool_oauth2sciebo', 'path') === 'http') {
            $pref = 'http://';
        } else {
            $pref = 'https://';
        }

        $ch = new curl();
        $output = $ch->post($pref.get_config('tool_oauth2sciebo', 'server').'/ocs/v1.php/apps/files_sharing/api/v1/shares',
            http_build_query(array('path' => $url,
                'shareType' => 3,
                'publicUpload' => false,
                'permissions' => 31,
            ), null, "&"),
            array('CURLOPT_USERPWD' => "$username:$password"));

        $xml = simplexml_load_string($output);
        $fields = explode("/s/", $xml->data[0]->url[0]);
        $fileid = $fields[1];
        $this->logout();
        return $pref.get_config('tool_oauth2sciebo', 'server').'/public.php?service=files&t='.$fileid.'&download';
    }

    /**
     * Method that generates a reference link to the chosen file.
     * TODO Find another method then just calling the get_link function.
     */
    public function send_file($storedfile, $lifetime=86400 , $filter=0, $forcedownload=false, array $options = null) {
        $ref = $storedfile->get_reference();
        $ref = $this->get_link($ref);
        header('Location: ' . $ref);
        $this->logout();
    }

    /**
     * Function which checks whether the user is logged in on the Sciebo instance.
     * @return bool return false, if no Access Token is set or can be requested.
     */
    public function check_login() {
        return $this->sciebo->is_logged_in();
    }

    /**
     * Prints a simple Login Button which redirects to a Authorization window from ownCloud.
     * @return array login window properties.
     */
    public function print_login() {
        $url = $this->sciebo->get_login_url();
        if ($this->options['ajax']) {
            $ret = array();
            $btn = new \stdClass();
            $btn->type = 'popup';
            $btn->url = $url->out(false);
            $ret['login'] = array($btn);
            return $ret;
        } else {
            echo html_writer::link($url, get_string('login', 'repository'), array('target' => '_blank'));
        }
    }

    /**
     * Deletes the held Access Token and prints the Login window.
     * @return array login window properties.
     */
    public function logout() {
        $this->sciebo->log_out();

        return $this->print_login();
    }

    /**
     * Sets up access token after the redirection from ownCloud.
     */
    public function callback() {
        $this->sciebo->callback();
    }

    /**
     * Is this repository accessing private data?
     *
     * @return bool
     */
    public function contains_private_data() {
        return false;
    }

    /**
     * Methode to define which filetypes are supported (hardcoded can not be changed in Admin Menü)
     *
     * For a full list of possible types and groups, look in lib/filelib.php, function get_mimetypes_array()
     *
     * @return string '*' means this repository support any files
     */
    public function supported_filetypes() {
        return '*';
    }

    /**
     * Method to define which Files are supported (hardcoded can not be changed in Admin Menü)
     *
     * Can choose FILE_REFERENCE|FILE_INTERNAL|FILE_EXTERNAL
     * FILE_INTERNAL - the file is uploaded/downloaded and stored directly within the Moodle file system
     * FILE_EXTERNAL - the file stays in the external repository and is accessed from there directly
     * FILE_REFERENCE - the file may be cached locally, but is automatically synchronised, as required,
     *                 with any changes to the external original
     * @return int return type bitmask supported
     */
    public function supported_returntypes() {
        return FILE_INTERNAL | FILE_REFERENCE | FILE_EXTERNAL;
    }


    // TODO override optional- evaluate of neccessary
    /*
    public function get_file_reference($source)
    {
        return parent::get_file_reference($source); // TODO: Change the autogenerated stub
    }

    public function get_file_source_info($source)
    {
        return parent::get_file_size($source); // TODO: Change the autogenerated stub
    }*/
}