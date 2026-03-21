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
/*
 * Scanservice
 *
 * @package    mod_scanservice
 * @author     Johannes Burk & Vincent Schneider 2017
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery','jqueryui', 'mod_icontent/cookiehandler'], function($, jqui, c) {
    function getDeepLink(cmid, pageid) {
        if (!cmid || !pageid) {
            return '#';
        }

        var url = new URL(window.location.href);
        url.searchParams.set('id', cmid);
        url.searchParams.set('pageid', pageid);
        url.hash = '';
        return url.toString();
    }

    function onSetControlDisabled($btn, disabled) {
        if (disabled) {
            $btn.prop('disabled', true)
                .addClass('disabled')
                .attr('aria-disabled', 'true')
                .attr('tabindex', '-1');
            return;
        }

        $btn.removeAttr('disabled')
            .removeClass('disabled')
            .removeAttr('aria-disabled')
            .removeAttr('tabindex');
    }

    function onUpdateDeepLink(cmid, replace) {
        var pageid = $('.fulltextpage').attr('data-pageid');
        if (!cmid || !pageid || !window.history || !window.history.replaceState) {
            return;
        }

        var url = getDeepLink(cmid, pageid);

        if (replace) {
            window.history.replaceState({}, '', url);
        } else {
            window.history.pushState({}, '', url);
        }
    }

    // Loads page
    function onLoadPageClick(e){
        if (e && e.preventDefault) {
            e.preventDefault();
        }

        var requestdata = {
            "action": "loadpage",
            "id": $(this).attr('data-cmid'),
            "pagenum": $(this).attr('data-pagenum'),
            "sesskey": $(this).attr('data-sesskey')
        };
        // Destroy all tooltips
        //$('[data-toggle="tooltip"]').tooltip('destroy');
        // Loading page
        $(".icontent-page")
            .children('.fulltextpage')
            .prepend(
                $('<div />')
                    .addClass('loading')
                    .html('<img src="pix/loading.gif" alt="Loading" class="img-loading" />')
            )
            .css('opacity', '0.5');
        // Active link or button the atual page
        onBtnActiveEnableDisableClick(requestdata.pagenum);
        var postdata = "&" + $.param(requestdata);
        $.ajax({
            type: "POST",
            dataType: "json",
            url: "ajax.php", // Relative or absolute path to ajax.php file
            data: postdata,
            success: function(data) {
                if(data.transitioneffect !== "0"){
                    $(".icontent-page").hide();
                    $(".icontent-page").html(data.fullpageicontent);
                    $(".icontent-page").show(data.transitioneffect, 1000);
                }else{
                    $(".icontent-page").html(data.fullpageicontent);
                }
                onChecksHighcontrast();
                onChangeStateControlButtons(data);
                onUpdateDeepLink(requestdata.id, false);
            }
        }); // End AJAX

    } // End onLoad..

    // Checks if the cookie is set.
    function onChecksHighcontrast(){
        if (c.cookie('highcontrast') == "yes") {
            $(".fulltextpage").addClass("highcontrast").css({"background-color":"#000000", "background-image": "none"});
        }
    }
    // Change state the control buttons
    function onChangeStateControlButtons($data){
        var $btnprev = $('.icontent-buttonbar .btn-previous-page');
        var $btnnext = $('.icontent-buttonbar .btn-next-page');
        var cmid = $btnprev.attr('data-cmid') || $btnnext.attr('data-cmid') || $('.load-page[data-cmid]:first').attr('data-cmid');

        if($data.previous){
            onSetControlDisabled($btnprev, false);
            $btnprev.attr( "data-pagenum", $data.previous );
            if ($data.previouspageid) {
                $btnprev.attr('data-pageid', $data.previouspageid);
                $btnprev.attr('href', getDeepLink(cmid, $data.previouspageid));
            }
        }else{
            onSetControlDisabled($btnprev, true);
            $btnprev.removeAttr('data-pageid');
            $btnprev.attr('href', '#');
        }

        if($data.next){
            onSetControlDisabled($btnnext, false);
            $btnnext.attr( "data-pagenum", $data.next );
            if ($data.nextpageid) {
                $btnnext.attr('data-pageid', $data.nextpageid);
                $btnnext.attr('href', getDeepLink(cmid, $data.nextpageid));
            }
        }else{
            onSetControlDisabled($btnnext, true);
            $btnnext.removeAttr('data-pageid');
            $btnnext.attr('href', '#');
        }
    }
    // Disable button when clicked.
    function onBtnActiveEnableDisableClick(pagenum){
        var pagenum = pagenum;
        $(".load-page").removeClass("active");
        $(".btn-icontent-page").removeClass('disabled').removeAttr('aria-disabled').removeAttr('tabindex');
        $(".btn-icontent-page").removeAttr("disabled");
        $(".page"+ pagenum).addClass("active");
        $(".page"+ pagenum).addClass('disabled').attr('aria-disabled', 'true').attr('tabindex', '-1');
        $(".page"+ pagenum).prop("disabled", true );
    }
    return {
        init: function() {
            onChecksHighcontrast();
            onBtnActiveEnableDisableClick($(".fulltextpage").attr('data-pagenum'));
            onUpdateDeepLink($(".load-page[data-cmid]:first").attr('data-cmid'), true);
            $(".load-page").click(onLoadPageClick);
        }
    };
});
