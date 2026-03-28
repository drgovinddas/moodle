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
define(['jquery', 'jqueryui', 'mod_icontent/cookiehandler'], function($, jqui, c) {
    /**
     *
     * @param {number|string} cmid
     * @param {number|string} pageid
     */
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

    /**
     *
     * @param {jQuery} $btn
     * @param {boolean} disabled
     */
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

    /**
     *
     * @param {number|string} cmid
     * @param {boolean} replace
     */
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

    /**
     * Trigger a light reflow pass so late-loaded widgets can size themselves.
     */
    function onTriggerRenderRefresh() {
        var trigger = function() {
            $(window).trigger('resize');
        };

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(trigger);
        } else {
            setTimeout(trigger, 0);
        }

        // Some widgets render after an async paint cycle in Chrome.
        setTimeout(trigger, 200);
        setTimeout(trigger, 600);
    }

    /**
     * Notify Moodle filters that new page content was injected via AJAX.
     * This is required for MathJax/TeX and other filters to process new nodes.
     */
    function onNotifyFilterContentUpdated() {
        var $nodes = $('.icontent-page .fulltextpage');
        if (!$nodes.length) {
            $nodes = $('.fulltextpage');
        }

        if (!$nodes.length || typeof require !== 'function') {
            return;
        }

        require(['core_filters/events'], function(filterEvents) {
            if (!filterEvents || typeof filterEvents.notifyFilterContentUpdated !== 'function') {
                return;
            }

            filterEvents.notifyFilterContentUpdated($nodes.toArray());
        });
    }

    /**
     * Re-initialize Tiny editors for dynamically injected essay/autograde responses.
     */
    function onEnsureEssayEditorsReady() {
        if (typeof require !== 'function') {
            return;
        }

        var $targets = $('.fulltextpage textarea.qtype_essay_response, .fulltextpage .qtype_essay_response textarea');
        if (!$targets.length) {
            return;
        }

        require(['editor_tiny/editor'], function(tinyEditor) {
            if (!tinyEditor || typeof tinyEditor.setupForElementId !== 'function') {
                return;
            }

            $targets.each(function() {
                var id = this.id;
                if (!id) {
                    return;
                }

                if (document.getElementById(id + '_ifr')) {
                    return;
                }

                if (typeof tinyEditor.getInstanceForElementId === 'function' && tinyEditor.getInstanceForElementId(id)) {
                    return;
                }

                tinyEditor.setupForElementId({
                    elementId: id,
                    options: {}
                });
            });
        });
    }

    /**
     * Re-run essayautograde widget init on dynamically injected question HTML.
     */
    function onEnsureEssayAutogradeReady() {
        if (typeof require !== 'function') {
            return;
        }

        var $itemcount = $('.fulltextpage .itemcount[data-itemtype]:first');
        if (!$itemcount.length) {
            return;
        }

        var readonly = $('#idformquestions').length === 0;
        var itemtype = $itemcount.attr('data-itemtype') || 'words';
        var minitems = parseInt($itemcount.attr('data-minitems') || '0', 10);
        var maxitems = parseInt($itemcount.attr('data-maxitems') || '0', 10);
        var editortype = 'textarea';
        var $responses = $('.fulltextpage .question.essayautograde .answer .qtype_essay_response');
        if (!$responses.length) {
            $responses = $('.fulltextpage .question.essayautograde .qtype_essay_response');
        }

        if ($responses.find('iframe').length) {
            editortype = 'tinymce';
        } else if ($responses.find('[contenteditable=true]').length) {
            editortype = 'atto';
        }

        require(['qtype_essayautograde/essayautograde'], function(essayAutograde) {
            if (!essayAutograde || typeof essayAutograde.init !== 'function') {
                return;
            }

            essayAutograde.init(readonly, itemtype, minitems, maxitems, editortype, '');
        });
    }

    /**
     * Keep essayautograde word-count badges in sync with plain and TinyMCE editors.
     */
    function onSyncEssayAutogradeWordCount() {
        var findResponses = function() {
            return $('.fulltextpage .question.essayautograde textarea[name$="_answer"], ' +
                '.fulltextpage .question.essayautograde textarea[name$="[text]"]');
        };

        var $responses = findResponses();
        if (!$responses.length) {
            var findtries = 0;
            var findmaxtries = 20;
            var findinterval = setInterval(function() {
                findtries++;
                var $found = findResponses();
                if ($found.length || findtries >= findmaxtries) {
                    clearInterval(findinterval);
                    if ($found.length) {
                        onSyncEssayAutogradeWordCount();
                    }
                }
            }, 150);
            return;
        }

        var wordsplit = /[\s\u2014\u2013]+/;
        var countWords = function(text) {
            var value = (text || '').trim();
            if (!value) {
                return 0;
            }
            return value.split(wordsplit).filter(function(item) {
                return item !== '';
            }).length;
        };

        var escapedId = function(id) {
            return '#' + id.replace(/(:|\.|\[|\]|,|=|@)/g, '\\$1');
        };

        var updateCount = function($textarea, overrideText) {
            var name = $textarea.attr('name') || '';
            if (!name) {
                return;
            }
            name = name.replace(/\[text\]$/, '');
            var selector = escapedId('id_' + name + '_itemcount') + ' .countitems .value';
            var value = (typeof overrideText === 'string') ? overrideText : ($textarea.val() || '');
            var $target = $(selector);
            if ($target.length) {
                $target.text(countWords(value));
            }
        };

        $responses.each(function() {
            var $textarea = $(this);
            var editorid = this.id;

            $textarea.off('.icontentessaywc').on('input.icontentessaywc keyup.icontentessaywc change.icontentessaywc', function() {
                updateCount($textarea);
            });
            updateCount($textarea);

            if (!editorid) {
                return;
            }

            var tries = 0;
            var maxtries = 20;
            var bindIframeBody = function() {
                var iframe = document.getElementById(editorid + '_ifr');
                if (!iframe) {
                    return false;
                }

                var d = iframe.contentWindow || iframe.contentDocument;
                if (d && d.document) {
                    d = d.document;
                }
                if (!d || !d.body) {
                    return false;
                }

                var $body = $(d.body);
                $body.off('.icontentessaywc').on('input.icontentessaywc keyup.icontentessaywc change.icontentessaywc', function() {
                    updateCount($textarea, $body.text());
                });
                updateCount($textarea, $body.text());
                return true;
            };

            if (bindIframeBody()) {
                return;
            }

            var intervalid = setInterval(function() {
                tries++;
                if (bindIframeBody() || tries >= maxtries) {
                    clearInterval(intervalid);
                }
            }, 150);
        });
    }

    /**
     * Fallback for PoodLL sketch pages loaded by AJAX where toolbars/canvas may not
     * fully initialize until a hard page load.
     *
     * @param {number|string} cmid
     */
    function onEnsurePoodllSketchReady(cmid) {
        var hasPoodllSketch = $('.fulltextpage .que.poodllrecording').length > 0 ||
            $('.fulltextpage .qtype_poodllrecording_response').length > 0;

        if (!hasPoodllSketch) {
            return;
        }

        setTimeout(function() {
            var hasTools = $('.fulltextpage .drawing-board-controls').length > 0 ||
                $('.fulltextpage .literally .lc-options').length > 0;
            var hasCanvas = $('.fulltextpage .drawing-board-canvas').length > 0 ||
                $('.fulltextpage .literally canvas').length > 0;

            if (hasTools && hasCanvas) {
                return;
            }

            var pageid = $('.fulltextpage').attr('data-pageid');
            var deepLink = getDeepLink(cmid, pageid);

            if (deepLink && deepLink !== '#') {
                window.location.assign(deepLink);
            } else {
                window.location.reload();
            }
        }, 400);
    }

    // Loads page
    /**
     *
     * @param {Event} e
     */
    function onLoadPageClick(e) {
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
        // $('[data-toggle="tooltip"]').tooltip('destroy');
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
                if (data.transitioneffect !== "0") {
                    $(".icontent-page").hide();
                    $(".icontent-page").html(data.fullpageicontent);
                    $(".icontent-page").show(data.transitioneffect, 1000);
                } else {
                    $(".icontent-page").html(data.fullpageicontent);
                }
                onChecksHighcontrast();
                onChangeStateControlButtons(data);
                onUpdateDeepLink(requestdata.id, false);
                onNotifyFilterContentUpdated();
                onEnsureEssayEditorsReady();
                onEnsureEssayAutogradeReady();
                onSyncEssayAutogradeWordCount();
                onTriggerRenderRefresh();
                onEnsurePoodllSketchReady(requestdata.id);
            }
        }); // End AJAX

    } // End onLoad..

    // Checks if the cookie is set.
    /**
     *
     */
    function onChecksHighcontrast() {
        if (c.cookie('highcontrast') == "yes") {
            $(".fulltextpage").addClass("highcontrast").css({"background-color": "#000000", "background-image": "none"});
        }
    }
    // Change state the control buttons
    /**
     *
     * @param {Object} $data
     */
    function onChangeStateControlButtons($data) {
        var $btnprev = $('.icontent-buttonbar .btn-previous-page');
        var $btnnext = $('.icontent-buttonbar .btn-next-page');
        var cmid = $btnprev.attr('data-cmid') || $btnnext.attr('data-cmid') || $('.load-page[data-cmid]:first').attr('data-cmid');

        if ($data.previous) {
            onSetControlDisabled($btnprev, false);
            $btnprev.attr("data-pagenum", $data.previous);
            if ($data.previouspageid) {
                $btnprev.attr('data-pageid', $data.previouspageid);
                $btnprev.attr('href', getDeepLink(cmid, $data.previouspageid));
            }
        } else {
            onSetControlDisabled($btnprev, true);
            $btnprev.removeAttr('data-pageid');
            $btnprev.attr('href', '#');
        }

        if ($data.next) {
            onSetControlDisabled($btnnext, false);
            $btnnext.attr("data-pagenum", $data.next);
            if ($data.nextpageid) {
                $btnnext.attr('data-pageid', $data.nextpageid);
                $btnnext.attr('href', getDeepLink(cmid, $data.nextpageid));
            }
        } else {
            onSetControlDisabled($btnnext, true);
            $btnnext.removeAttr('data-pageid');
            $btnnext.attr('href', '#');
        }
    }
    // Disable button when clicked.
    /**
     *
     * @param {number|string} pagenum
     */
    function onBtnActiveEnableDisableClick(pagenum) {
        $(".load-page").removeClass("active");
        $(".btn-icontent-page").removeClass('disabled').removeAttr('aria-disabled').removeAttr('tabindex');
        $(".btn-icontent-page").removeAttr("disabled");
        $(".page" + pagenum).addClass("active");
        $(".page" + pagenum).addClass('disabled').attr('aria-disabled', 'true').attr('tabindex', '-1');
        $(".page" + pagenum).prop("disabled", true);
    }
    return {
        init: function() {
            onChecksHighcontrast();
            onBtnActiveEnableDisableClick($(".fulltextpage").attr('data-pagenum'));
            onUpdateDeepLink($(".load-page[data-cmid]:first").attr('data-cmid'), true);
            onNotifyFilterContentUpdated();
            onEnsureEssayEditorsReady();
            onEnsureEssayAutogradeReady();
            onSyncEssayAutogradeWordCount();
            onTriggerRenderRefresh();
            onEnsurePoodllSketchReady($(".load-page[data-cmid]:first").attr('data-cmid'));
            $(".load-page").click(onLoadPageClick);
        }
    };
});
