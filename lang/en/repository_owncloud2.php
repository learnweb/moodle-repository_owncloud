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
$string['settings_withoutissuer'] = 'You have not added an OAuth2 issuer yet.';
$string['settings_addissuer'] = '<br>To add a new issuer visit';
$string['visit_oauth2doku'] = '<br>For additional help visit the ';
$string['settings_withissuer'] = 'Currently the {$a} issuer is active.';
$string['right_issuers'] = 'The following issuers implement the suitable endpoints: <br> {$a}';
$string['oauth2'] = 'OAuth2 issuer';
$string['chooseissuer'] = 'Choice of issuer';
$string['chooseissuer_help'] = 'To add a new issuer visit the admin OAuth 2 services page. <br>
For additional help with the OAuth2 API visit the Moodle Dokumentation.';
$string['invalid_issuer'] = 'Currently the {$a} is active, however it does not implement all necessary endpoints. The repository will not work.';
$string['chooseissuer_link'] = 'OAuth_2_services';

// Exceptions.
$string['exception_config'] = 'A Mistake in the configuration of the OAuth2 Client occurred{$a}';
$string['web_endpoint_missing'] = 'The webdav endpoint for the owncloud oauth2 issuer is not working.
Therefore the owncloud repository is disabled';
