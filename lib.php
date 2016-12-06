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
require_once($CFG->libdir.'/webdavlib.php');
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
        // set up webdav client (webdav core)
        if (empty($this->options['webdav_server'])) {
            return;
        }
        if ($this->options['webdav_auth'] == 'none') {
            $this->options['webdav_auth'] = false;
        }
        if (empty($this->options['webdav_type'])) {
            $this->webdav_type = '';
        } else {
            $this->webdav_type = 'ssl://';
        }
        if (empty($this->options['webdav_port'])) {
            $port = '';
            if (empty($this->webdav_type)) {
                $this->webdav_port = 80;
            } else {
                $this->webdav_port = 443;
                $port = ':443';
            }
        } else {
            $this->webdav_port = $this->options['webdav_port'];
            $port = ':' . $this->webdav_port;
        }

        $this->trigger_event();

        $this->options['webdav_user'] = get_user_preferences('webdav_user');
        $this->options['webdav_password'] = get_user_preferences('webdav_pass');

        $this->webdav_host = $this->webdav_type.$this->options['webdav_server'].$port;
        $this->dav = new webdav_client($this->options['webdav_server'], $this->options['webdav_user'],
            $this->options['webdav_password'], $this->options['webdav_auth'], $this->webdav_type);
        $this->dav->port = $this->webdav_port;
        $this->dav->debug = false;
    }

    /**
     * Function which triggers the login event. It has yet to be decided where this function should be called.
     */
    private function trigger_event() {
        $event = \repository_sciebo\event\sciebo_loggedin::create(array('context' => $this->context, 'other' => array('user' => optional_param('webdav_user', '', PARAM_RAW), 'pass' => optional_param('webdav_pass', '', PARAM_RAW))));
        $event->trigger();
    }

    public function get_file($url, $title = '') {
        $url = urldecode($url);
        $path = $this->prepare_file($title);
        if (!$this->dav->open()) {
            return false;
        }
        $webdavpath = rtrim('/'.ltrim($this->options['webdav_path'], '/ '), '/ '); // without slash in the end
        $this->dav->get_file($webdavpath . $url, $path);
        $this->logout();

        return array('path' => $path);
    }

    public function global_search() {
        return false;
    }

    public function get_listing($path='', $page = '') {
        global $CFG, $OUTPUT;
        $list = array();
        $ret  = array();
        $ret['dynload'] = true;
        $ret['nosearch'] = true;
        $ret['nologin'] = false;
        $ret['path'] = array(array('name' => get_string('webdav', 'repository_webdav'), 'path' => ''));
        $ret['list'] = array();

        if (!$this->dav->open()) {
            return $ret;
        }
        $webdavpath = rtrim('/'.ltrim($this->options['webdav_path'], '/ '), '/ '); // without slash in the end
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
        $dir = $this->dav->ls($webdavpath. urldecode($path));
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

            // Remove the server URL from the path (if present), otherwise links will not work - MDL-37014
            $server = preg_quote($this->options['webdav_server']);
            $v['href'] = preg_replace("#https?://{$server}#", '', $v['href']);
            // Extracting object title from absolute path
            $v['href'] = substr(urldecode($v['href']), strlen($webdavpath));
            $title = substr($v['href'], strlen($path));

            if (!empty($v['resourcetype']) && $v['resourcetype'] == 'collection') {
                // a folder
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
                // a file
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
     * The link is not generated properly yet and the file has to be shared by its owner beforehand.
     * TODO Authorization process has to be implemented differently.
     * @param string $url relative path to the chosen file
     * @return string returns the generated downloadlink
     * @throws repository_exception if $url is empty an exception is thrown
     */
    public function get_link($url) {
        $username = $this->options['webdav_user'];
        $password = $this->options['webdav_password'];

        if (($this->options['webdav_type']) === 0) {
            $pref = 'http://';
        } else {
            $pref = 'https://';
        }

        $ch = new curl();
        $output = $ch->post($pref.$this->options['webdav_server'].'/ocs/v1.php/apps/files_sharing/api/v1/shares',
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
        return $pref.$this->options['webdav_server'].'/public.php?service=files&t='.$fileid.'&download';
    }

    /**
     * Method that generates a reference link to the chosen file. The Link does not work yet, another solution is needed.
     * At the moment the get_link method is used to fetch the downloadlink to the file.
     */
    public function send_file($storedfile, $lifetime=86400 , $filter=0, $forcedownload=false, array $options = null) {
        $ref = $storedfile->get_reference();
        $ref = $this->get_link($ref);
        header('Location: ' . $ref);
        $this->logout();
    }

    public function check_login() {
        if (($this->options['webdav_user'] == '') || ($this->options['webdav_password'] == '')) {
            return false;
        } else {
            return true;
        }
    }

    public function print_login() {
        $ret = array();
        $username = new stdClass();
        $username->type = 'text';
        $username->id   = 'webdav_user';
        $username->name = 'webdav_user';
        $username->label = get_string('username').': ';
        $password = new stdClass();
        $password->type = 'password';
        $password->id   = 'webdav_pass';
        $password->name = 'webdav_pass';
        $password->label = get_string('password').': ';
        $ret['login'] = array($username, $password);
        $ret['login_btn_label'] = get_string('login');
        $ret['login_btn_action'] = 'login';
        return $ret;
    }

    public function logout()
    {
        set_user_preference('webdav_user', null);
        set_user_preference('webdav_pass', null);
        return $this->print_login();
    }

    public static function get_instance_option_names() {
        return array('webdav_type', 'webdav_server', 'webdav_port', 'webdav_path', 'webdav_user', 'webdav_password', 'webdav_auth');
    }

    public static function instance_config_form($mform) {
        $choices = array(0 => get_string('http', 'repository_webdav'), 1 => get_string('https', 'repository_webdav'));
        $mform->addElement('select', 'webdav_type', get_string('webdav_type', 'repository_webdav'), $choices);
        $mform->addRule('webdav_type', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'webdav_server', get_string('webdav_server', 'repository_webdav'), array('size' => '40'));
        $mform->addRule('webdav_server', get_string('required'), 'required', null, 'client');
        $mform->setType('webdav_server', PARAM_HOST);

        $mform->addElement('text', 'webdav_path', get_string('webdav_path', 'repository_webdav'), array('size' => '40'));
        $mform->addRule('webdav_path', get_string('required'), 'required', null, 'client');
        $mform->setType('webdav_path', PARAM_PATH);

        $choices = array();
        $choices['none'] = get_string('none');
        $choices['basic'] = get_string('webdavbasicauth', 'repository_webdav');
        $choices['digest'] = get_string('webdavdigestauth', 'repository_webdav');
        $mform->addElement('select', 'webdav_auth', get_string('authentication', 'admin'), $choices);
        $mform->addRule('webdav_auth', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'webdav_port', get_string('webdav_port', 'repository_webdav'), array('size' => '40'));
        $mform->setType('webdav_port', PARAM_INT);
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
     * TODO: Define necessary
     *
     * Can choose FILE_REFERENCE|FILE_INTERNAL|FILE_EXTERNAL
     * FILE_INTERNAL - the file is uploaded/downloaded and stored directly within the Moodle file system
     * FILE_EXTERNAL - the file stays in the external repository and is accessed from there directly
     * FILE_REFERENCE - the file may be cached locally, but is automatically synchronised, as required, with any changes to the external original
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