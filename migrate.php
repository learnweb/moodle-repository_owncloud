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
 * Perform migration to the core plugin repository_nextcloud (M3.6 and above only).
 *
 * @package    repository_owncloud
 * @copyright  2018 Jan Dageförde (Learnweb, University of Münster), based on code by
 *             2017 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$PAGE->set_url('/repository/owncloud/migrate.php');
$PAGE->set_context(context_system::instance());
$strheading = get_string('migration', 'repository_owncloud');
$PAGE->set_title($strheading);
$PAGE->set_heading($strheading);

require_login();

require_capability('moodle/site:config', context_system::instance());

if ($CFG->branch < 36) {
    die('This functionality is only available in Moodle 3.6 and above.');
}

$confirm = optional_param('confirm', false, PARAM_BOOL);

if ($confirm) {
    require_sesskey();

    if (\repository_owncloud\migration::migrate_all_instances()) {
        $mesg = get_string('owncloudfilesmigrated', 'repository_owncloud');
        redirect(new moodle_url('/admin/repository.php', ['action' => 'edit', 'repos' => 'nextcloud', 'sesskey' => sesskey()]),
            $mesg, null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        $mesg = get_string('owncloudfilesnotmigrated', 'repository_owncloud');
        redirect(new moodle_url('/admin/repository.php', ['action' => 'edit', 'repos' => 'owncloud', 'sesskey' => sesskey()]),
            $mesg, null, \core\output\notification::NOTIFY_ERROR);
    }
} else {
    $continueurl = new moodle_url('/repository/owncloud/migrate.php', ['confirm' => true]);
    $cancelurl = new moodle_url('/admin/repository.php', ['action' => 'edit', 'repos' => 'owncloud', 'sesskey' => sesskey()]);
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('confirmmigration', 'repository_owncloud'), $continueurl, $cancelurl);
    echo $OUTPUT->footer();
}