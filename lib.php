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
require_once($CFG->libdir.'/oauthlib.php');
require_once($CFG->dirroot . '/repository/sciebo/mywebdavlib.php');

/**
 * sciebo repository plugin.
 *
 * @package    repository_Sciebo
 * @copyright  2016 Westfälische Universität Münster (WWU Münster)
 * @author     Projektseminar Uni Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_sciebo extends repository {
    /** @var later object for auth2.0 */
    private $sciebo = null;


    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()){
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
        $this->webdav_host = $this->webdav_type.$this->options['webdav_server'].$port;
        $this->dav = new sciebo_webdav_client($this->options['webdav_server'], $this->options['webdav_user'],
            $this->options['webdav_password'], $this->options['webdav_auth'], $this->webdav_type);
        $this->dav->port = $this->webdav_port;
        $this->dav->debug = false;
    }

    public function check_login() {
        return true;
    }

    public function get_file($url, $title = '') {
        $url = urldecode($url);
        $path = $this->prepare_file($title);
        if (!$this->dav->open()) {
            return false;
        }
        $webdavpath = rtrim('/'.ltrim($this->options['webdav_path'], '/ '), '/ '); // without slash in the end
        $this->dav->get_file($webdavpath . $url, $path);

        return array('path'=>$path);
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
        $ret['nologin'] = true;
        $ret['path'] = array(array('name'=>get_string('webdav', 'repository_webdav'), 'path'=>''));
        $ret['list'] = array();
        if (!$this->dav->open()) {
            return $ret;
        }
        $webdavpath = rtrim('/'.ltrim($this->options['webdav_path'], '/ '), '/ '); // without slash in the end
        if (empty($path) || $path =='/') {
            $path = '/';
        } else {
            $chunks = preg_split('|/|', trim($path, '/'));
            for ($i = 0; $i < count($chunks); $i++) {
                $ret['path'][] = array(
                    'name' => urldecode($chunks[$i]),
                    'path' => '/'. join('/', array_slice($chunks, 0, $i+1)). '/'
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
                        'title'=>rtrim($title, '/'),
                        'thumbnail'=>$OUTPUT->pix_url(file_folder_icon(90))->out(false),
                        'children'=>array(),
                        'datemodified'=>$v['lastmodified'],
                        'path'=>$v['href']
                    );
                }
            }else{
                // a file
                $size = !empty($v['getcontentlength'])? $v['getcontentlength']:'';
                $files[strtoupper($title)] = array(
                    'title'=>$title,
                    'thumbnail' => $OUTPUT->pix_url(file_extension_icon($title, 90))->out(false),
                    'size'=>$size,
                    'datemodified'=>$v['lastmodified'],
                    'source'=>$v['href']
                );
            }
        }
        ksort($files);
        ksort($folders);
        $ret['list'] = array_merge($folders, $files);
        return $ret;
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
        $mform->addElement('text', 'webdav_user', get_string('webdav_user', 'repository_webdav'), array('size' => '40'));
        $mform->setType('webdav_user', PARAM_RAW_TRIMMED); // Not for us to clean.
        $mform->addElement('password', 'webdav_password', get_string('webdav_password', 'repository_webdav'),
            array('size' => '40'));
    }

// TODO OAuth2 instance settings will be offered here
    /**
     * Optional Method to change settings.php of plugin (hardcoded can not be changed in Admin Menü)
     * e.g. add setting element API Key
     *
     * This is for modifying the Moodle form displaying the plugin settings. lib/formslib.php
     * Form Definition has details of all the types of elements you can add to the settings form.
     */
    /*
       public static function type_config_form($mform, $classname = 'repository') {
        //the following line is needed in order to retrieve the API key value from the database when Moodle displays the edit form
        $a = 'https:/thisisonlyaexampleurl.latertherealone';
        $mform->addElement('static', null, '', get_string('oauthinfo', 'repository_sciebo', $a));
        $api_key = get_config('sciebo_public', 'client_id');
        $secret = get_config('sciebo_public', 'secret');
        $mform->addElement('text', 'client_id', get_string('clientid', 'repository_sciebo'),
            array('value'=>$api_key,'size' => '40'));
        $mform->addElement('text', 'secret', get_string('secret', 'repository_sciebo'),
            array('value'=>$secret,'size' => '40'));
        $mform->addRule('client_id', get_string('required'), '', null, 'client');
        $mform->addRule('secret', get_string('required'), '', null, 'client');
    }
    */

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
        return FILE_INTERNAL|FILE_REFERENCE|FILE_EXTERNAL;
    }

    /**
     * Method to generate a downloadlink for a chosen file (in the file picker).
     * The link is not generated properly yet and the file has to be shared by its owner beforehand.
     * TODO implement another method to fetch the downloadlink
     * @param string $url relative path to the chosen file
     * @return string returns the generated downloadlink
     * @throws repository_exception if $url is empty an exception is thrown
     */
    public function get_link($url) {
        if (empty($url)) {
            throw new repository_exception('cannotdownload', 'repository');
        }
        return 'http://'.$this->options['owncloud_server'].'/public.php?service=files&t='.$url.'&download';;
    }

    /**
     * Method that generates a reference link to the chosen file. The Link does not work yet, another solution is neede.
     * TODO implement another method to fetch the downloadlink
     * @param stored_file $storedfile
     * @param int $lifetime
     * @param int $filter
     * @param bool $forcedownload
     * @param array|null $options
     */
    public function send_file($storedfile, $lifetime=86400 , $filter=0, $forcedownload=false, array $options = null) {
        $ref = $storedfile->get_reference();
        $ref = 'http://'.$this->options['owncloud_server'].'/public.php?service=files&t='.$ref.'&download';
        header('Location: ' . $ref);
    }

// TODO override optional- evaluate of neccessary
/*
    public function print_login()
    {
        return parent::print_login(); // TODO: Change the autogenerated stub
    }
    public function logout()
    {
        return parent::logout(); // TODO: Change the autogenerated stub
    }
    public function get_file_reference($source)
    {
        return parent::get_file_reference($source); // TODO: Change the autogenerated stub
    }

    public function get_file_source_info($source)
    {
        return parent::get_file_size($source); // TODO: Change the autogenerated stub
    }*/
}
