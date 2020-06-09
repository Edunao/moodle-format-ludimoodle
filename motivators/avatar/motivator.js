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
 * js for avatar motivator
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     David Bokobza <david.bokobza@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    var avatar = {
        init: function(params) {
            console.log('avatar init : ', params);

            /**
             * Center inventory in the window
             * @param inventory object
             */
            function center_inventory(inventory) {
                var maxwidth = $(window).width();
                var width = inventory.width();
                var left = (maxwidth - width) / 2 - 30; // 30px for padding left
                inventory.css('left', left);
            }
            // Open inventory on click
            $('.bag img, .close-inventory').on('click', function() {
                // Prevent close pop up on ludimoodle overlay click
                $('.ludimoodle-overlay').unbind("click");

                // Center inventory in the window
                var inventory = $('.ludi-main-container.show .inventory');
                center_inventory(inventory);
                $(window).on('resize', function() {
                    center_inventory(inventory);
                });
                // Open inventory
                inventory.toggle(400);
                $('.ludimoodle-overlay').toggle(400);

                // Change icon of bag
                var bag = $('.bag img');
                var oldmode = bag.hasClass('bag-is-open') ? 'open' : 'close';

                bag.toggleClass('bag-is-open');

                var mode = bag.hasClass('bag-is-open') ? 'open' : 'close';
                var url = bag.attr('src');
                bag.attr('src', url.replace(oldmode, mode));

                // Log inventory click
                $.ajax({
                    url: M.cfg.wwwroot + '/course/format/ludimoodle/motivators/avatar/ajax.php',
                    method: 'POST',
                    data: {
                        action: 'log-inventory',
                        userid: params.userid,
                        courseid: params.courseid,
                        mode: mode,
                    },
                    success: function() {
                        console.log('inventory-' + mode + ' log added');
                    },
                    error: function(error) {
                        console.log('error', error);
                    }
                });
            });

            // Change item on avatar
            $('.inventory img.is-reached').on('click', function() {
                var itemidx    = $(this).data('itemidx');
                var slotidx    = $(this).closest('.inventory-row').data('slotidx');
                var currentimg = $('img[data-slotidx="' + slotidx + '"]');
                var visual     = $('.inventory').data('visual');
                $('.inventory-row[data-slotidx="' + slotidx + '"] .inventory-grid img').removeClass('is-used').addClass('not-used');
                if (currentimg.data('itemidx') ===  itemidx) {
                    // Same item, so unset it
                    itemidx = 0;
                } else {
                    $(this).removeClass('not-used');
                    $(this).addClass('is-used');
                }
                // Get item url
                $.ajax({
                    url: M.cfg.wwwroot + '/course/format/ludimoodle/motivators/avatar/ajax.php',
                    method: 'POST',
                    data: {
                        action: 'change-item',
                        userid: params.userid,
                        courseid: params.courseid,
                        itemidx: itemidx,
                        slotidx: slotidx,
                        visual: visual
                    },
                    success: function(url) {
                        if (currentimg.length > 0) {
                            currentimg.attr('src', url);
                            currentimg.data('itemidx', itemidx);
                        }
                    },
                    error: function(error) {
                        console.log('error', error);
                    }
                });
            });
        }
    };
    return avatar;
});