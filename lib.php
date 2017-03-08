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

    /** @var null|sciebo the ownCloud client. */
    private $sciebo = null;

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

        // Checks, whether all the required data is available. $this->options['checked'] is set to true, if the
        // data was checked once to prevend multiple printings of the warning.
        if (empty($this->options['checked'])) {
            $this->sciebo->check_data();
            $this->options['checked'] = true;
        }
    }

    /**
     * This function does exactly the same as in the WebDAV repository. The only difference is, that
     * the Sciebo OAuth2 client uses OAuth2 instead of Basic Authentication.
     *
     * @param string $url relative path to the file.
     * @param string $title title of the file.
     * @return array|bool returns either the moodle path to the file or false.
     */
    public function get_file($url, $title = '') {
        $url = urldecode($url);
        $path = $this->prepare_file($title);
        if (!$this->sciebo->open()) {
            return false;
        }
        $webdavpath = rtrim('/'.ltrim(get_config('tool_oauth2sciebo', 'path'), '/ '), '/ '); // Without slash in the end.
        $this->sciebo->get_file($webdavpath . $url, $path);

        return array('path' => $path);
    }

    /**
     * This function does exactly the same as in the WebDAV repository. The only difference is, that
     * the Sciebo OAuth2 client uses OAuth2 instead of Basic Authentication.
     *
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
        $ret['path'] = array(array('name' => get_string('owncloud', 'repository_sciebo'), 'path' => ''));
        $ret['list'] = array();
        $ret['manage'] = $CFG->wwwroot.'/'.$CFG->admin.'/tool/oauth2sciebo/index.php';

        if (!$this->sciebo->open()) {
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
     * Method to generate a download link for a chosen file (in the file picker).
     * Creates a share for the chosen file and fetches the specific file ID through
     * the OCS Share API (ownCloud).
     *
     * @param string $url relative path to the chosen file
     * @return string the generated downloadlink.
     * @throws repository_exception if $url is empty an exception is thrown.
     */
    public function get_link($url) {
        $pref = get_config('tool_oauth2sciebo', 'type') . '://';

        $output = $this->sciebo->get_link($url);
        $xml = simplexml_load_string($output);
        $fields = explode("/s/", $xml->data[0]->url[0]);
        $fileid = $fields[1];

        $path = str_replace('remote.php/webdav/', '', get_config('tool_oauth2sciebo', 'path'));

        return $pref . get_config('tool_oauth2sciebo', 'server'). '/' . $path .
        'public.php?service=files&t=' . $fileid . '&download';
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
        if($usefilereference) {
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
        header('Location: ' . $storedfile->get_reference());
    }

    /**
     * Function which checks whether the user is logged in on the ownCloud instance.
     *
     * @return bool false, if no Access Token is set or can be requested.
     */
    public function check_login() {
        $token = unserialize(get_user_preferences('oC_token'));
        $this->sciebo->set_access_token($token);

        // If a user Access Token is available or can be refreshed, it is stored within the user specific
        // preferences.
        if ($this->sciebo->is_logged_in()) {

            $tok = serialize($this->sciebo->get_accesstoken());
            set_user_preference('oC_token', $tok);
            return true;

        } else {

            // Otherwise it is set to null.
            set_user_preference('oC_token', null);
            return false;

        }
    }

    /**
     * Prints a simple Login Button which redirects to an authorization window from ownCloud.
     *
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
     *
     * @return array login window properties.
     */
    public function logout() {
        $this->sciebo->log_out();
        set_user_preference('oC_token', null);
        return $this->print_login();
    }

    /**
     * Sets up access token after the redirection from ownCloud.
     */
    public function callback() {
        $this->sciebo->callback();

        // The user Access Token, as soon as it is received and verified, gets stored within
        // the user specific preferences.
        if ($this->sciebo->is_logged_in()) {

            $tok = serialize($this->sciebo->get_accesstoken());
            set_user_preference('oC_token', $tok);

        } else {
            // If the Access Token has expired, not received or cannot be refreshed,
            // the user specific preference is set to null.
            set_user_preference('oC_token', null);
        }
    }

    /**
     * This method adds a notification to the settings form, which redirects to the OAuth 2.0 client.
     *
     * @param moodleform $mform Moodle form (passed by reference)
     * @param string $classname repository class name
     */
    public static function type_config_form($mform, $classname = 'repository') {
        global $CFG, $OUTPUT;

        $link = $CFG->wwwroot.'/'.$CFG->admin.'/tool/oauth2sciebo/index.php';

        // A notification is added to the settings page in form of a notification.
        $html = $OUTPUT->notification(get_string('settings', 'repository_sciebo',
                '<a href="'.$link.'" target="_blank">'. get_string('oauth2', 'repository_sciebo') .'</a>'), 'warning');

        $mform->addElement('html', $html);

        parent::type_config_form($mform);
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
        return FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE;
    }
}