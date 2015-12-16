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
 * @module local_libwall/wall
 * @package local_libwall
 * @subpackage amd
 * @copyright 2015 David Mudrak <david@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["jquery", "core/ajax", "core/templates", "core/notification"], function($, ajax, templates, notification) {
    /* jshint strict:true */
    "use strict";

    function debug(msg, obj) {
        /* jshint devel:true */
        console.debug("[local_libwall] %s %o", msg, obj ? obj : null);
    }

    function init(uniqid) {
        var wall = $("#" + uniqid + "-libwall-wall");

        wall.on("click", "[data-libwall-widget=addcomment]", function() {
            add_comment_submit_handler(wall);
        });

        debug("initialized wall  " + uniqid, wall);
    }

    function add_comment_submit_handler(wall) {
        var textbox = wall.find("[data-libwall-area=addcomment] [role=textbox]");

        if (!textbox.length) {
            debug("error: unable to find wall input textbox", textbox);
            return;
        }

        var content = $.trim(textbox.val());

        if (content === "") {
            return;
        }

        if (textbox.prop("disabled")) {
            return;
        }

        textbox.prop("disabled", true);

        var promises = ajax.call([{
            methodname: "local_libwall_add_comment",
            args: {
                wallid: wall.attr("data-libwall-wallid"),
                content: content,
                contentformat: 0, // TODO
                maxseqnum: wall.attr("data-libwall-maxseqnum")
            }
        }]);

        promises[0].done(function (data) {
            add_comment_response_handler(data, wall, textbox);
        }).fail(notification.exception);
    }

    function add_comment_response_handler(data, wall, textbox) {

        if (data.wall.id != wall.attr("data-libwall-wallid")) {
            notification.exception(new Error("Unexpected identifier mismatch"));
            return false;
        }

        if (!data.comments) {
            notification.exception(new Error("Unexpected empty result"));
            return false;
        }

        templates.render("local_libwall/comments", data)
            .done(function(html, js) {
                wall.find("[data-libwall-area=comments]").prepend(html);
                wall.attr("data-libwall-maxseqnum", data.wall.maxseqnum);
                templates.runTemplateJS(js);
                textbox.val("");
                textbox.prop("disabled", false);
            })
            .fail(notification.exception);
    }

    return {init: init};
});
