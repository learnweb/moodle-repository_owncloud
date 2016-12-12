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
 * Version.php for Sciebo repository
 *
 * @package    repository_Sciebo
 * @copyright  2016 Westfälische Universität Münster (WWU Münster)
 * @author     Projektseminar Uni Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2016120700;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2016051900;        // Requires this Moodle version.
$plugin->component = 'repository_sciebo'; // Full name of the plugin (used for diagnostics).
$plugin->maturity = MATURITY_ALPHA;
$plugin->dependencies = array(
    'tool_oauth2siebo' => ANY_VERSION
);