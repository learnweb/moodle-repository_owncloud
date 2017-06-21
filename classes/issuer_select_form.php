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
 * Create an Form Class for the tool_deprovisionuser
 *
 * @package   tool_deprovisionuser
 * @copyright 2017 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_owncloud2;
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
use moodleform;
use core_plugin_manager;

/**
 * Form Class which allows the sideadmin to select between the available issuers.
 *
 * @package   tool_deprovisionuser
 * @copyright 2017 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issuer_select_form extends moodleform {
    /**
     * Defines the sub-plugin select form.
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;
        // Gets all available issuers
        $issuers = $DB->get_records('oauth2_issuer', array(), '', 'id, name');
        $types = array();
        foreach ($issuers as $value) {
            $types[$value->name] = $value->name;
        }
        // TODO Where is issuer saved
        $isissuerselected = empty(get_config('owncloud2'));
        // Different text in case no plugin was selected beforehand.
        if ($isissuerselected) {
            $text = 'Please select a issuer';
        } else {
            $text = 'Change the issuer';
        }
        $mform->addElement('select', 'issuer', $text, $types);
        // If a issuer is selected it is shown as the default.
        if (!$isissuerselected) {
            $mform->setDefault('issuer', get_config('repository_owncloud2', 'owncloud2_issuer'));
        }
        $mform->addElement('submit', 'reset', 'Submit');
        // Gets all available plugins of type userstatus.

    }

    /**
     * Checks data for correctness
     * Returns an string in an array when the sub-plugin is not available.
     *
     * @param array $data
     * @param array $files
     * @return bool/array array in case the sub-plugin is not valid, otherwise true.
     */
    public function validation($data, $files) {
        global $DB;
        $issuers = $DB->get_records('oauth2_issuer', array(), '', 'id, name');
        $issubplugin = false;
        foreach ($issuers as $value) {
            if ($value->name == $data['issuer']) {
                $issubplugin = true;
                break;
            }
        }
        if ($issubplugin == false) {
            // TODO Exception
        }
        return $issubplugin;
    }
}