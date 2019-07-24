$(function() {

    // Configuration: "run-time" settings are defined here, "constant" settings (translations,
    // interface URL etc.) in VersionControl.module.
    var settings = {
        empty: true,
        render: 'HTML'
    };
    var moduleConfig = config.VersionControl;

    // Field data is cached to reduce need for redundant AJAX requests.
    var cache = {};

    // Fetch revision data for this page as HTML markup.
    $.get(moduleConfig.processPage + 'page', { pages_id: moduleConfig.pageID, settings: settings }, function(data) {
        
        // Prepend data (#version-control-data) to body.
        $('body').prepend(data);

        // Create a reusable spinner element.
        var $spinner = $('<i class="fa fa-spinner fa-spin"></i>');

        // Iterate field specific revision containers and add their contents to that field's header
        // (.ui-widget-header) if there's at least one revision other than the currently active one.
        $('#version-control-data > div').each(function() {
            if ($(this).data('revision')) {
                var $if = $('.Inputfield_' + $(this).data('field'));
                $(this).find('a, button').attr('tabindex', -1);
                $if.find('> label')
                    .addClass('with-history')
                    .before($(this));
                $(this).find('tr:eq(1)').addClass('ui-state-active');
                if ($if.hasClass('InputfieldTinyMCE') || $if.hasClass('InputfieldCKEditor')) return;
                var $cacheobj = $if.find('.InputfieldContent') || $if.find('div.ui-widget-content');
                cache[$(this).data('field') + "." + $(this).data('revision')] = $cacheobj.clone(true, true);
            }
        });
        
        // Iterate history-enabled fields to add a revision toggle to each of them.
        $('.ui-widget-header.with-history, .InputfieldHeader.with-history').each(function() {
            var toggle_class = "field-revisions-toggle";
            if ($(this).siblings('.field-revisions').find('tr').length < 2) {
                toggle_class += " inactive";
            }
            // Note: this is a bit sneaky, but basically we're creating a non-usable, hidden link,
            // and then using that to figure out how to style our real toggle button.
            var $revisions_toggle_sentinel = $('<a></a>')
                .hide()
                .appendTo($(this));
            var revisions_toggle_color = $revisions_toggle_sentinel.css('color');
            var $revisions_toggle = $('<button><i class="fa fa-clock-o"></i></button>')
                .addClass(toggle_class)
                .attr('title', moduleConfig.i18n.toggleRevisions)
                .attr('aria-expanded', false)
                .css('color', revisions_toggle_color);
            var $revisions_toggle_text = $('<span></span>')
                .addClass('visually-hidden')
                .text(moduleConfig.i18n.toggleRevisions)
                .appendTo($revisions_toggle);
            var $toggle_icon = $(this).find('.toggle-icon');
            if ($toggle_icon.length) {
                $toggle_icon.after($revisions_toggle);
            } else {
                $(this).append($revisions_toggle);
            }
        });
        
        // When a restore button in the revision list is clicked, fetch data for matching revision
        // from our API (most of the code here is for presentation, loading animation etc.)
        $('.field-revisions').on('click', '.field-revision-restore, .field-revision-current', function() {
            var $revision = $(this).parents('.field-revision:first');
            if ($revision.hasClass('ui-state-active')) {
                return false;
            }
            var settings = {
                render: 'Input'
            };
            var $revisions = $(this).parents('.field-revisions:first');
            var $if = $(this).parents('.Inputfield:first');
            var field = $revisions.data('field');
            $if.find('.field-revisions .ui-state-active').removeClass('ui-state-active');
            $revision.addClass('ui-state-active');
            $('.compare-revisions').remove();
            $('.field-revision-diff').removeClass('active');
            var $content = $if.find('.InputfieldContent') || $if.find('div.ui-widget-content');
            var $loading = $('<span class="field-revision-loading"></span>').hide().css({
                height: $content.innerHeight() + 'px',
                backgroundColor: $content.css('background-color')
            });
            if ($if.hasClass('InputfieldTinyMCE') || $if.hasClass('InputfieldCKEditor')) {
                // For some inputfield types we need to get raw data as JSON instead of pre-rendered
                // inputfield markup (HTML).
                settings = {
                    render: 'JSON'
                };
            }
            var revision = $revision.data('revision');
            if (cache[field + "." + revision]) {
                if (settings.render != "JSON" && revision == $revisions.data('revision')) {
                    // Current (latest) revision is the only one we already have cached content for.
                    $content.replaceWith(cache[field + "." + revision].clone(true, true));
                    if ($if.find('.InputfieldFileList').length) {
                        // For file inputs we need to trigger the 'reloaded' event manually in order
                        // to (re-)enable HTML5 AJAX uploads.
                        $if.find('.InputfieldFileInit').removeClass('InputfieldFileInit');
                        $if.trigger('reloaded');
                    }
                    if ($if.find('.InputfieldAsmSelect').length) {
                        var $select = $if.find('select[multiple=multiple]');
                        var options = typeof config === 'undefined' ? {
                            sortable: true
                        } : config[$select.attr('id')];
                        $select.appendTo($if.find('.InputfieldAsmSelect')).show();
                        $if.find('.asmContainer').remove();
                        $select.asmSelect(options);
                    }
                } else {
                    update($if, $content, settings, field, cache[field + "." + revision]);
                }
            } else {
                $content.css('position', 'relative').prepend($loading.fadeIn(250));
                $.get(moduleConfig.processPage + 'field', { revision: revision, field: field, settings: settings }, function(data) {
                    cache[field + "." + revision] = data;
                    update($if, $content, settings, field, cache[field + "." + revision]);
                    $loading.fadeOut(350, function() {
                        $(this).remove();
                    });
                });
            }
            return false;
        });

        // This function updates inputfield content based on inputfield and content objects,
        // settings (render mode etc.) and data (HTML or JSON).
        var update = function($if, $content, settings, field, data) {
            if (settings.render == "Input") {
                // Format of returned data is HTML.
                var before = $content.children('p.description:first');
                var after = $content.children('p.notes:first');
                $content.html(data).prepend(before).append(after);
                if ($if.hasClass('InputfieldImage') || $if.hasClass('InputfieldFile')) {
                    // Trigger InputfieldImage() manually.
                    InputfieldImage($);
                    // Image and file data isn't editable until it has been restored; here we're
                    // applying an overlay layer to prevent editing attempts and avoid confusion.
                    $content.prepend('<div class="version-control-overlay"></div>');
                    $('.version-control-overlay')
                        .attr('title', moduleConfig.i18n.editDisabled)
                        .on('click', function() {
                            alert($(this).attr('title'));
                        })
                        .hover(
                            function() {
                                $(this).parent('.overlay-parent').addClass('hover');
                            }, 
                            function() {
                                $(this).parent('.overlay-parent').removeClass('hover');
                            }
                        )
                        .parent('.InputfieldContent')
                        .addClass('overlay-parent');
                } else if ($if.hasClass('Inputfield_permissions')) {
                    $('.Inputfield_permissions .Inputfield_permissions > .InputfieldContent').insertAfter(
                        $('.Inputfield_permissions:first > .InputfieldContent:first')
                    );
                    $('.Inputfield_permissions:first > .InputfieldContent:first').remove();
                }
                if ($if.find('.InputfieldAsmSelect').length) {
                    var $select = $if.find('select[multiple=multiple]');
                    var options = typeof config === 'undefined' ? {
                        sortable: true
                    } : config[$select.attr('id')];
                    $select.asmSelect(options);
                }
            } else {
                // Format of returned data is JSON.
                $.each(data, function(property, value) {
                    var language = property.replace('data', '');
                    if (language) {
                        language = "__" + language;
                    }
                    if (typeof tinyMCE != "undefined" && tinyMCE.get('Inputfield_' + field + language)) {
                        // TinyMCE inputfield.
                        tinyMCE.get('Inputfield_' + field + language).setContent(value);
                    } else if ($if.find('.InputfieldCKEditorInline').length) {
                        // CKeditor inputfield in inline mode.
                        $if.find('.InputfieldCKEditorInline').html(value);
                    } else if (typeof CKEDITOR != "undefined" && CKEDITOR.instances['Inputfield_' + field + language]) {
                        // CKEditor inputfield.
                        CKEDITOR.instances['Inputfield_' + field + language].setData(value);
                    }
                });
            }
        }
        
        // When revisions toggle is clicked, show the revisions table – or hide it, in case it was
        // already visible.
        $('.field-revisions-toggle').on('click', function() {
            if ($(this).hasClass('inactive')) {
                return false;
            }
            var $revisions = $(this).parent('label').siblings('.field-revisions');
            if (!$(this).hasClass('active')) {
                $revisions.addClass('animatable');
            }
            if ($revisions.is(':visible')) {
                $(this)
                    .removeClass('active')
                    .attr('aria-expanded', false);
                $revisions.addClass('sliding').slideUp('fast', function() {
                    $revisions
                        .removeClass('animatable sliding')
                        .removeAttr('style')
                        .attr('aria-hidden', true)
                        .find('a, button')
                            .attr('tabindex', -1);
                    InputfieldColumnWidths();
                    $(window).trigger('resize.revisions-table');
                });
            } else {
                $(this)
                    .addClass('active')
                    .attr('aria-expanded', true);
                $revisions.addClass('sliding').slideDown('fast', function() {
                    $revisions
                        .removeClass('sliding')
                        .removeAttr('aria-hidden')
                        .focus()
                        .find('a, button')
                            .removeAttr('tabindex');
                    InputfieldColumnWidths();
                });
            }
            return false;
        });

        // Add the "scrollable" class to all oversized revision data tables.
        var revisions_table_resize_timeout;
        $(window)
            .on('resize.revisions-table', function() {
                clearTimeout(revisions_table_resize_timeout);
                revisions_table_resize_timeout = setTimeout(function() {
                    $('.field-revisions:not(.animatable)').each(function() {
                        $table = $(this).find('table');
                        if ($table.length && $(this).width() < $table.outerWidth()) {
                            // Revision table won't fit to the horizontal space, and becomes scrollable.
                            $(this)
                                .addClass('scrollable')
                                .find('> div')
                                    .trigger('scroll.revisions-table');
                        } else {
                            $(this).removeClass('scrollable');
                        }
                    });
                }, 250);
            })
            .trigger('resize.revisions-table');

        // Keep track of the scroll positions of scrollable revision table containers.
        $('.field-revisions > div').on('scroll.revisions-table', function() {
            var $revisions = $(this).parent();
            $revisions
                .toggleClass('scrollable--start', !$(this).scrollLeft())
                .toggleClass('scrollable--end', $(this)[0].scrollWidth - $(this).scrollLeft() == $(this).outerWidth());
        });

        // Enable Diff Match Patch if/when required.
        var enableDiffMatchPatch = function(r1, r2) {
            var r1 = r1 || document.getElementById('r1').value;
            var r2 = r2 || document.getElementById('r2').value;
            var ds;
            if (r1 == r2) {
                ds = '<em>' + moduleConfig.i18n.noDiff + '</em>';
            } else {
                if (typeof diff_match_patch != 'function') {
                    $.getScript(moduleConfig.moduleDir + "resources/js/diff_match_patch/diff_match_patch.js", function() {
                        enableDiffMatchPatch(r1, r2);
                    });
                    return false;
                } else {
                    var dmp = new diff_match_patch();
                    dmp.Diff_Timeout = moduleConfig.diff.timeout;
                    dmp.Diff_EditCost = moduleConfig.diff.editCost;
                    var ms_start = (new Date()).getTime();
                    var d = dmp.diff_main(r1, r2);
                    var ms_end = (new Date()).getTime();
                    var s = (ms_end - ms_start) / 1000 + 's';
                    if (moduleConfig.diff.cleanup) {
                        dmp['diff_cleanup' + moduleConfig.diff.cleanup](d);
                    }
                    ds = dmp.diff_prettyHtml(d);
                }
            }
            document.getElementById('diff').innerHTML = ds;
        }
        
        // When compare/diff button is clicked, display the difference between the target revision
        // and the revision that is currently active.
        $('.field-revisions')
            .on('click', '.field-revision-diff', function() {
                $('.compare-revisions').remove();
                $('.field-revision-diff').not(this).removeClass('active');
                $(this).toggleClass('active');
                if ($(this).hasClass('active')) {
                    // In this case r1 refers to current revision, r2 to the selected revision.
                    // Diff is fetched as pre-rendered HTML markup from the revision interface.
                    $(this).attr('aria-expanded', true);
                    var $revisions = $(this).parents('.field-revisions:first');
                    var $revision = $(this).parents('.field-revision:first');
                    var field = $revisions.data('field');
                    var r1 = $revisions.find('.ui-state-active:first').data('revision');
                    var r2 = $revision.data('revision');
                    var href = moduleConfig.processPage + 'diff/?revisions=' + r1 + ':' + r2 + '&field=' + field;
                    var $compare_revisions = $('<div></div>')
                        .attr('tabindex', -1)
                        .addClass('compare-revisions')
                        .insertAfter($(this))
                        .focus();
                    var $parent = $(this).parents('tr:first');
                    $compare_revisions.prepend($spinner).load(href, function() {
                        if ($parent.find('ul.page-diff').length) {
                            if (typeof enableDiffSwitch != 'function') {
                                $.getScript(moduleConfig.moduleDir + "diff_switch.min.js", function() {
                                    enableDiffSwitch(moduleConfig);
                                });
                            } else {
                                enableDiffSwitch(moduleConfig);
                            }
                        } else {
                            enableDiffMatchPatch();
                        }
                        var $compare_revisions_close = $('<button></button>')
                            .addClass('field-revision-button compare-revisions-close fa fas fa-times')
                            .attr('tabindex', 0)
                            .on('click', function(event) {
                                event.preventDefault();
                                $(this).parent().prev().focus().trigger('click');
                            })
                            .prependTo($compare_revisions);
                    });
                }
                return false;
            });
    
    });
    
});
