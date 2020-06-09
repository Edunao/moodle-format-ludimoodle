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
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     Céline Hernandez (celine@edunao.com)
 * @package    format_ludimoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function ($) {
    var uniqueidx = 0;
    var relativeprogression = {
        init: function (params, globaluniqueidx) {
            console.log('Relative progression init: ', params);
            if (params.summarydata != undefined) {
                this.init_summary(params.summarydata, globaluniqueidx);
            }
            if (params.summarydata != undefined) {
                this.init_attempt(params.moddata, globaluniqueidx);
            }
        },
        init_summary: function (params, globaluniqueidx) {
            console.log('Relative progression summary init: ', params);

            // Transform image to svg and show only needed layers
            $('.relativeprogress-summary-img.svg').each(function () {
                var $img = $(this);
                var imgID = $img.attr('id');
                var imgClass = $img.attr('class');
                var imgURL = $img.attr('src');

                $.get({url: imgURL, async: false}, function (data) {
                    var $svg = $(data).find('svg');
                    if (typeof imgID !== 'undefined') {
                        $svg = $svg.attr('id', imgID);
                    }
                    if (typeof imgClass !== 'undefined') {
                        $svg = $svg.attr('class', imgClass + ' replaced-svg');
                    }
                    $svg = $svg.removeAttr('xmlns:a');

                    var id = $svg.attr('id');

                    $svg.children().each(function () {
                        var self = $(this);
                        var childid = self.attr('id');
                        self.attr('id', 'ludi-svg_' + globaluniqueidx + uniqueidx + '_' + childid);
                        if (params[id].revealed_layers == undefined || params[id].revealed_layers.includes(childid) === false) {
                            self.remove();
                        }
                    });

                    var svghtml = $svg.html();

                    svghtml = svghtml.replace(/SVGID_/g, 'SVGID_' + uniqueidx + '_' + 'relativeprogression');
                    uniqueidx++;
                    $svg.html(svghtml);
                    $svg.css('visibility', 'visible');
                    $img.replaceWith($svg);
                });
            });

            // Update rank value if rank is superior to 1
            $.each(params, function (id, attributes) {
                if (attributes.rank != undefined) {
                    let rank = Array.isArray(attributes.rank) ? attributes.rank[0] : attributes.rank;
                    let rankselector = Array.isArray(attributes.rank) ? '#' + id + ' ' + attributes.rank_container[0] : '#' + id + ' ' + attributes.rank_container;
                    $(rankselector).html(rank);

                    // Fix rank and prefix display
                    let prefix = relativeprogression.get_rank_prefix(rank);
                    let prefixselector = Array.isArray(attributes.rank) ? '#' + id + ' ' + attributes.rank_prefix_container[0] : '#' + id + ' ' + attributes.rank_prefix_container;
                    if ($(prefixselector).length > 0) {
                        // let rankwidth = $(prefixselector)[0].getBBox().width;
                        $(prefixselector).html(prefix);
                        if (rank < 10) {
                            $(rankselector).attr('x', 6);
                            $(prefixselector).attr('x', -2);
                        } else {
                            // var shift = 17 - rankwidth;
                            // $(rankselector).attr('x', shift);
                            $(rankselector).attr('x', 3);
                        }
                    }

                }

            });

        },
        init_attempt: function (params, globaluniqueidx) {
            console.log('Relative progression in attempt init: ', params);
            var that = this;

            $('.relativeprogress-mod-img.svg').each(function () {
                var $img = $(this);
                var imgID = $img.attr('id');
                var imgClass = $img.attr('class');
                var imgURL = $img.attr('src');

                $.get({url: imgURL, async: false}, function (data) {
                    var $svg = $(data).find('svg');
                    if (typeof imgID !== 'undefined') {
                        $svg = $svg.attr('id', imgID);
                    }
                    if (typeof imgClass !== 'undefined') {
                        $svg = $svg.attr('class', imgClass + ' replaced-svg');
                    }
                    $svg = $svg.removeAttr('xmlns:a');

                    var id = $svg.attr('id');

                    //
                    var element_map = {
                        "-2": 'prev_2',
                        "-1": 'prev_1',
                        1: 'next_1',
                        2: 'next_2',
                    };


                    if (params[id] != undefined) {

                        var motivatorparams = params[id];
                        var revealedlayers = [];

                        // Other "people" rank
                        $.each(element_map, function (rankindex, paramname) {
                            let rankoptions = motivatorparams[paramname];

                            // Choose between layers and alternative layers
                            let random = Math.floor(Math.random() * Math.floor(2));

                            let rank = motivatorparams.ranks[parseInt(rankindex)];
                            var rankcontainer = '';
                            var rankprefix = '';

                            if(rank > 0){
                                if (random > 0) {
                                    $.merge(revealedlayers, rankoptions.layers);
                                    rankcontainer = rankoptions.rank_container;
                                    rankprefix = rankoptions.rank_prefix_container;
                                } else {
                                    $.merge(revealedlayers, rankoptions.alternative_layers);
                                    rankcontainer = rankoptions.alternative_rank_container;
                                    rankprefix = rankoptions.alternative_rank_prefix_container;
                                }

                                // Update rank value
                                let prefix = that.get_rank_prefix(rank);
                                $svg.find(rankprefix).html(prefix);
                                $svg.find(rankcontainer).html(rank);
                            }

                        });

                        // My rank
                        var myrank = motivatorparams['ranks'][0];
                        if(myrank != 1){
                            $.merge(revealedlayers, motivatorparams.base_layers);

                            if(motivatorparams['ranks'][0] != undefined){
                                // There are answers
                                let rankcontainer = motivatorparams.rank_container;
                                let rankprefix = motivatorparams.rank_prefix_container;
                                let myrank = motivatorparams['ranks'][0];

                                let prefix = that.get_rank_prefix(myrank);
                                $svg.find(rankcontainer).html(myrank);
                                $svg.find(rankprefix).html(prefix);

                                $.merge(revealedlayers, motivatorparams.info_layers);
                            }

                        }else{
                            $.merge(revealedlayers, motivatorparams.victory_layers);
                        }

                        $svg.children().each(function () {
                            var self = $(this);
                            var childid = self.attr('id');
                            self.attr('id', 'ludi-svg_' + globaluniqueidx + uniqueidx + '_' + childid);
                            if (revealedlayers.includes(childid) === false) {
                                self.remove();
                            }
                        });

                    }


                    var svghtml = $svg.html();

                    svghtml = svghtml.replace(/SVGID_/g, 'SVGID_' + uniqueidx + '_' + 'relativeprogression');
                    uniqueidx++;
                    $svg.html(svghtml);
                    $svg.css('visibility', 'visible');
                    $img.replaceWith($svg);
                });
            });

        },
        get_rank_prefix: function (rank) {
            if(rank === 1){
                return 'er';
            } else if (rank === 2) {
                return 'nd';
            } else {
                return 'ème';
            }
        }
    }

    return relativeprogression;
});