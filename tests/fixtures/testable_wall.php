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
 * Provides {@link local_libwall\testable_wall} class.
 *
 * @package     local_libwall
 * @category    test
 * @copyright   2015 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_libwall;

/**
 * Subclass of the wall class used in unit tests.
 *
 * @copyright 2015 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_wall extends wall {

    /** @var int|null allows to inject next value returned by {@link self::get_next_seqnum()} */
    public $fakenextseqnum = null;

    /**
     * Returns the seqnum of the next comment added to the wall.
     *
     * Allows to simulate race conditions in unit tests by injecting fake
     * (existing) value via {@link self::fakenextseqnum} property.
     *
     * @return int
     */
    protected function get_next_seqnum() {

        if (isset($this->fakenextseqnum)) {
            $fake = $this->fakenextseqnum;
            $this->fakenextseqnum = null;
            return $fake;

        } else {
            return parent::get_next_seqnum();
        }
    }
}
