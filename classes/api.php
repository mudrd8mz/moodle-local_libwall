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
 * Provides the {@link local_libwall\api} class.
 *
 * @package     local_libwall
 * @copyright   2015 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_libwall;

defined(MOODLE_INTERNAL || die());

require_once($CFG->libdir.'/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_format_value;
use external_single_structure;
use external_multiple_structure;

/**
 * Provides external API for the libwall library.
 *
 * @copyright 2015 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api extends external_api {

    /**
     * add_comment() parameters description.
     *
     * @return external_function_parameters
     */
    public static function add_comment_parameters() {
        return new external_function_parameters([
            'wallid' => new external_value(PARAM_INT, 'Wall instance id'),
            'content' => new external_value(PARAM_RAW, 'Comment content'),
            'contentformat' => new external_format_value('content', VALUE_REQUIRED),
            'maxseqnum' => new external_value(PARAM_INT, 'The last already loaded comment sequence number', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * add_comment() implementation.
     *
     * @return array jsonable list of hashmaps
     */
    public static function add_comment($wallid, $content, $contentformat, $maxseqnum) {
        global $PAGE;

        $params = self::validate_parameters(self::add_comment_parameters(), [
            'wallid' => $wallid,
            'content' => $content,
            'contentformat' => $contentformat,
            'maxseqnum' => $maxseqnum,
        ]);

        // TODO: Permissions checks.

        $wall = wall::instance_by_id($wallid);

        $wall->add_comment($content, $contentformat);

        // Load all the recent comments up the one already present on the client side.
        $wall->load_comments(null, $maxseqnum + 1);

        $output = $PAGE->get_renderer('local_libwall');

        // Export the whole wall for rendering via template.
        $data = $wall->export_for_template($output);

        // Selectively pick just those parts needed to update comments on the client side.
        return [
            'wall' => [
                'id' => $data['wall']['id'],
                'maxseqnum' => $data['wall']['maxseqnum'],
            ],
            'comments' => $data['comments'],
        ];
    }

    /**
     * add_comment() returned structure description.
     *
     * @return external_single_structure
     */
    public static function add_comment_returns() {
        return new external_single_structure([
            'wall' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Wall instance id'),
                'maxseqnum' => new external_value(PARAM_INT, 'Sequence number of the most recent returned comment'),
            ]),
            'comments' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Comment id'),
                    'seqnum' => new external_value(PARAM_INT, 'Comment sequence number'),
                    'content' => new external_value(PARAM_RAW, 'Comment formatted content'),
                    'format' => new external_format_value('content'),
                    'timecreated' => new external_single_structure([
                        'absdate' => new external_value(PARAM_RAW, 'Human readable date/time of comment creation'),
                        'reldate' => new external_value(PARAM_RAW, 'Human readable age of the comment'),
                        'iso8601date' => new external_value(PARAM_RAW, 'Machine readable ISO-8601 date/time of comment creation'),
                    ]),
                    'author' => [
                        'fullname' => new external_value(PARAM_RAW, 'Comment author full name'),
                        'link' => new external_value(PARAM_URL, 'URL of the author profile page'),
                        'picture' => new external_value(PARAM_RAW, 'HTML fragment displaying the user picture'),
                    ],
                    'replies' => new external_multiple_structure(
                        new external_single_structure([
                        ])
                    ),
                ])
            )
        ]);
    }

    /**
     * add_comment() can be called via AJAX.
     *
     * @return bool
     */
    public static function add_comment_is_allowed_from_ajax() {
        return true;
    }
}
