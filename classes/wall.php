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
 * Provides the {@link local_libwall\wall} class.
 *
 * @package     local_libwall
 * @copyright   2015 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_libwall;

use renderable;
use templatable;
use stdClass;
use context;
use user_picture;
use renderer_base;
use dml_exception;
use coding_exception;
use core_date;
use moodle_url;

/**
 * Represents a wall on which the comments are placed.
 *
 * @copyright 2015 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wall implements renderable, templatable {

    /** @var int */
    public $id;

    /** @var context */
    public $context;

    /** @var string */
    public $component;

    /** @var string */
    public $area;

    /** @var int */
    public $itemid;

    /** @var array */
    public $comments = [];

    /** @var int */
    protected $defaultcommentformat = FORMAT_MOODLE;

    /** @var mixed */
    protected $wallrecord;

    /** @var array */
    protected $users = [];

    /**
     * Returns the wall instance given by the location.
     *
     * Creates a new wall record if it does not exist yet.
     *
     * @param context $context
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @return local_libwall
     */
    public static function instance_by_location(context $context, $component, $area = '', $itemid = 0) {
        global $DB;

        $wall = $DB->get_record('local_libwall_walls', array(
            'contextid' => $context->id,
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
        ));

        if (!$wall) {
            try {
                $id = $DB->insert_record('local_libwall_walls', array(
                    'contextid' => $context->id,
                    'component' => $component,
                    'area' => $area,
                    'itemid' => $itemid,
                    'timecreated' => time(),
                ));

            } catch (dml_exception $e) {
                // This might eventually happen when race condition occurs.
                // Ignore it for now. If the wall was not created, the next query will throw anyway.
            }

            $wall = $DB->get_record('local_libwall_walls', array('id' => $id), '*', MUST_EXIST);
        }

        return new static($wall);
    }

    /**
     * Returns the wall instance given by its id.
     *
     * @param int $id
     * @return local_libwall
     */
    public static function instance_by_id($id) {
        global $DB;

        $wall = $DB->get_record('local_libwall_walls', array('id' => $id), '*', MUST_EXIST);

        return new static($wall);
    }

    /**
     * Wall instance constructor.
     *
     * @param stdClass $wallrecord
     */
    protected function __construct(stdClass $wallrecord) {

        $this->id = $wallrecord->id;
        $this->context = context::instance_by_id($wallrecord->contextid);
        $this->component = $wallrecord->component;
        $this->area = $wallrecord->area;
        $this->itemid = $wallrecord->itemid;
        $this->wallrecord = $wallrecord;
    }

    /**
     * Adds a new comment to the wall.
     *
     * @param string $content comment text
     * @param int $format comment text format
     * @param stdClass|int $user author user or her id, defaults to the current $USER
     * @return int new comment id
     */
    public function add_comment($content, $format = FORMAT_MOODLE, $user = null) {
        global $USER;

        if ($format === null) {
            $format = FORMAT_MOODLE;
        }

        if ($user === null) {
            $userid = isset($USER->id) ? $USER->id : 0;
        } else {
            $userid = is_object($user) ? $user->id : $user;
        }

        if (empty($userid) or $userid < 1 or isguestuser($userid)) {
            throw new coding_exception('Attempting to create a comment with invalid user account associated');
        }

        // TODO Permission callback.

        return $this->insert_into_comments($content, $format, $userid);
    }

    /**
     * Load comments from the database.
     *
     * @param int $seqmax maximum comment seqnum to load, defaults to no limit
     * @param int $seqmin minimum comment seqnum to load, defaults to no limit
     * @param int $countmax maximum number of comments to loads, defaults to no limit
     * @return int number of loaded comments
     */
    public function load_comments($seqmax = null, $seqmin = null, $countmax = null) {
        global $DB;

        $this->comments = array();

        if ($seqmax !== null and $seqmin !== null and $seqmax < $seqmin) {
            throw new coding_exception('Invalid comments sequence limits specified');
        }

        if ($countmax !== null and $countmax < 1) {
            throw new coding_exception('Invalid comments count specified');
        }

        $commentuserfields = user_picture::fields('cu', null, 'commentuserid', 'commentuser');
        $replyuserfields = user_picture::fields('ru', null, 'replyuserid', 'replyuser');

        $sql = "SELECT c.id AS commentid, c.seqnum AS commentseqnum, c.content AS commentcontent, c.format AS commentformat,
                       c.timecreated AS commenttimecreated, $commentuserfields,
                       r.id AS replyid, r.content AS replycontent, r.timecreated AS replytimecreated, $replyuserfields
                  FROM {local_libwall_comments} c
             LEFT JOIN {local_libwall_replies} r ON (r.commentid = c.id)
             LEFT JOIN {user} cu ON c.userid = cu.id
             LEFT JOIN {user} ru ON r.userid = ru.id
                 WHERE c.wallid = :wallid";

        $params = array('wallid' => $this->id);

        if ($seqmax !== null) {
            $sql .= " AND c.seqnum <= :seqmax";
            $params['seqmax'] = $seqmax;
        }

        if ($seqmin !== null) {
            $sql .= " AND c.seqnum >= :seqmin";
            $params['seqmin'] = $seqmin;
        }

        // Comments are ordered newest first, replies are newest last.
        // For replies coming in the same second, the order is not defined.
        $sql .= " ORDER BY c.seqnum DESC, r.timecreated";

        $rs = $DB->get_recordset_sql($sql, $params);

        foreach ($rs as $record) {
            if (!isset($this->comments[$record->commentseqnum])) {
                if ($countmax !== null and count($this->comments) == $countmax) {
                    // We have loaded all required comments now.
                    break;
                }

                if ($record->commentuserid and !isset($this->users[$record->commentuserid])) {
                    $this->users[$record->commentuserid] = user_picture::unalias($record, null, 'commentuserid', 'commentuser');
                }

                $this->comments[$record->commentseqnum] = (object)array(
                    'seqnum' => $record->commentseqnum,
                    'id' => $record->commentid,
                    'content' => $record->commentcontent,
                    'format' => $record->commentformat,
                    'timecreated' => $record->commenttimecreated,
                    'user' => $this->users[$record->commentuserid],
                    'replies' => [],
                );
            }

            if ($record->replyid) {
                if ($record->replyuserid and !isset($this->users[$record->replyuserid])) {
                    $this->users[$record->replyuserid] = user_picture::unalias($record, null, 'replyuserid', 'replyuser');
                }

                $this->comments[$record->commentseqnum]->replies[] = (object)array(
                    'id' => $record->replyid,
                    'content' => $record->replycontent,
                    'timecreated' => $record->replytimecreated,
                    'user' => $this->users[$record->replyuserid],
                );
            }
        }

        $rs->close();

        return count($this->comments);
    }

    /**
     * Sets default format for comments.
     *
     * @param int $format
     */
    public function set_default_comment_format($format) {
        $this->defaultcommentformat = $format;
    }

    /**
     * Export wall data for the mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;
        require_once($CFG->libdir.'/externallib.php');

        $viewfullnames = has_capability('moodle/site:viewfullnames', $this->context);

        $exported = [
            'wall' => [
                'id' => $this->id,
                'maxseqnum' => $this->comments ? max(array_keys($this->comments)) : 0,
                'minseqnum' => $this->comments ? min(array_keys($this->comments)) : 0,
                'commentformatlist' => $this->get_comment_formats(),
                'commentformatdefault' => $this->defaultcommentformat,
            ],
            'comments' => [],
        ];

        foreach ($this->comments as $data) {
            list($content, $format) = external_format_text($data->content, $data->format, $this->context->id, $this->component,
                $this->area, $this->itemid);

            $exported['comments'][] = [
                'id' => $data->id,
                'seqnum' => $data->seqnum,
                'content' => $content,
                'format' => $format,
                'timecreated' => [
                    'absdate' => userdate($data->timecreated, '', core_date::get_user_timezone()),
                    'reldate' => get_string('reltimeago', 'local_libwall', format_time(time() - $data->timecreated)),
                    'iso8601date' => date('c', $data->timecreated),
                ],
                'author' => [
                    'fullname' => fullname($data->user, $viewfullnames),
                    'link' => (new moodle_url('/user/view.php', ['id' => $data->user->id]))->out(false),
                    'picture' => $output->user_picture($data->user)
                ],
                'replies' => [],
            ];
        }

        return $exported;
    }

    /**
     * Insert comment data into the database.
     *
     * @param string $content
     * @param int $format
     * @param int $userid
     * @return int
     */
    protected function insert_into_comments($content, $format, $userid, $retry = true) {
        global $DB;

        try {
            $newid = $DB->insert_record('local_libwall_comments', array(
                'wallid' => $this->id,
                'seqnum' => $this->get_next_seqnum(),
                'content' => $content,
                'format' => $format,
                'userid' => $userid,
                'timecreated' => time(),
            ));

        } catch (dml_exception $e) {
            // This may happen if race conditions occur and the seqnum has been
            // already taken. Attempt to insert once more, or give up and re-throw.
            if ($retry) {
                usleep(mt_rand(100000, 500000));
                $newid = $this->insert_into_comments($content, $format, $userid, false);

            } else {
                throw $e;
            }
        }

        return $newid;
    }

    /**
     * Get the sequential number of the next comment added to the wall.
     *
     * @return int
     */
    protected function get_next_seqnum() {
        global $DB;

        $sql = "SELECT COALESCE(MAX(seqnum) + 1, 1)
                  FROM {local_libwall_comments}
                 WHERE wallid = ?";

        return $DB->get_field_sql($sql, array($this->id), MUST_EXIST);
    }

    /**
     * Returns the list of supported comment text formats.
     *
     * @return array
     */
    protected function get_comment_formats() {
        return format_text_menu();
    }
}
