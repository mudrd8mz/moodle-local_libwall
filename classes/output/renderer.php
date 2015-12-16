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
 * Provides the {@link local_libwall\output\renderer} class.
 *
 * @package     plugintype_pluginname
 * @subpackage  plugintype_pluginname
 * @category    optional API reference
 * @copyright   2015 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_libwall\output;

use plugin_renderer_base;
use local_libwall\wall;

/**
 * Defines the renderer for the comments wall.
 *
 * @copyright 2015 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the instance of a wall with loaded comments.
     *
     * @param local_libwall\wall $wall
     * @return string
     */
    public function render_wall(wall $wall) {
        return parent::render_from_template('local_libwall/wall', $wall->export_for_template($this));
    }
}
