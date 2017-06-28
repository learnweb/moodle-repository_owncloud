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
 * Language strings' definition for ownCloud repository.
 *
 * @package    repository_owncloud
 * @copyright  2017 Westfälische Wilhelms-Universität Münster (WWU Münster)
 * @author     Projektseminar Uni Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// General.
$string['pluginname'] = 'ownCloud2';
$string['configplugin'] = 'ownCloud repository configuration';
$string['owncloud2'] = 'ownCloud2';
$string['owncloud2:view'] = 'View ownCloud2';
$string['configplugin'] = 'ownCloud2 configuration';
$string['pluginname_help'] = 'ownCloud2 repository';

// Settings reminder.
$string['settings_withoutissuer'] = 'You have not added an OAuth2 issuer yet. <br>
Please submit the form to ensure that the plugin is working.';
$string['settings_addissuer'] = '<br>To add a new issuer visit';
$string['visit_oauth2doku'] = '<br>For additional help visit the ';
$string['settings_withissuer'] = 'Currently the {$a} issuer is active. <br>
To change the issuer submit the form with a suitable issuer.';
$string['oauth2'] = 'OAuth2 issuer';

// Exceptions.
$string['exception_config'] = 'A Mistake in the configuration of the OAuth2 Client occured{$a}';
$string['web_endpoint_missing'] = 'The webdav endpoint for the owncloud oauth2 issuer is not working. 
Therefore the owncloud repository is disabled';
