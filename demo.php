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
 * Demonstration of the library features
 *
 * @package     local_libwall
 * @copyright   2015 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');

require_login();

if (!is_siteadmin()) {
    die('Site admins allowed only');
}

$plugin = new stdClass();
include(__DIR__.'/version.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/libwall/demo.php');

$wall0 = \local_libwall\wall::instance_by_location(context_system::instance(), 'local_libwall', 'demo', 0);
$wall0->load_comments();

$wall1 = \local_libwall\wall::instance_by_location(context_system::instance(), 'local_libwall', 'demo', 1);
$wall1->load_comments();

$output = $PAGE->get_renderer('local_libwall');

echo $output->header();
echo $output->heading('Comments wall demo / libwall '.$plugin->release.' (build: '.$plugin->version.')');
echo '<div class="row-fluid">';
echo '<div class="span4">';
echo $output->heading('Wall demo/0', 3);
echo $output->render($wall0);
echo '</div>';
echo '<div class="span4">';
echo $output->heading('Wall demo/0 again', 3);
echo $output->render($wall0);
echo '</div>';
echo '<div class="span4">';
echo $output->heading('Wall demo/1', 3);
echo $output->render($wall1);
echo '</div>';
echo $output->footer();
