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
 * Version.php for ownCloud repository.
 *
 * @package    repository_owncloud
 * @copyright  2017 Westfälische Wilhelms-Universität Münster (WWU Münster)
 * @author     Projektseminar Uni Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2017030900;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2016052300;        // Requires Moodle 3.1 version.
$plugin->component = 'repository_owncloud'; // Full name of the plugin (used for diagnostics).
$plugin->maturity = MATURITY_BETA;
$plugin->dependencies = array(
    'tool_oauth2owncloud' => ANY_VERSION
);