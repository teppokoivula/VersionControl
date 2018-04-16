$(function() {

    // configuration: "run-time" settings are defined here, "constant" settings
    // (translations, interface URL etc.) in VersionControlForTextFields.module
    var settings = { empty: true, render: 'HTML' };
    var moduleConfig = config.VersionControl;

    // field data is cached to reduce need for redundant AJAX requests
    var cache = {};

    // fetch revision data for this page as HTML markup
    $.get(moduleConfig.processPage+'page', { pages_id: moduleConfig.pageID, settings: settings }, function(data) {
        
        // prepend data (#version-control-data) to body
        $('body').prepend(data);

        // create a reusable spinner element
        var $spinner = $('<i class="fa fa-spinner fa-spin"></i>');

        // iterate through field specific revision containers and add their
        // contents to that fields header (.ui-widget-header) only if they
        // contain at least one revision other than what's currently used
        $('#version-control-data > div').each(function() {
            if ($(this).data('revision')) {
                var $if = $('.Inputfield_'+$(this).data('field'));
                $if.find('> label')
                    .addClass('with-history')
                    .before($(this));
                $(this).find('tr:eq(1)').addClass('ui-state-active');
                if ($if.hasClass('InputfieldTinyMCE') || $if.hasClass('InputfieldCKEditor')) return;
                var $cacheobj = $if.find('.InputfieldContent') || $if.find('div.ui-widget-content');
                cache[$(this).data('field')+"."+$(this).data('revision')] = $cacheobj.clone(true, true);
            }
        });
        
        // iterate through history-enabled fields to add a revision toggle
        $('.ui-widget-header.with-history, .InputfieldHeader.with-history').each(function() {
            var toggle_class = "field-revisions-toggle";
            var toggle_title = "";
            if ($(this).siblings('.field-revisions').find('tr').length < 2) {
                toggle_class += " inactive";
                toggle_title = " title='"+$(this).siblings('.field-revisions').text()+"'";
            }
            var revisions_toggle = '<a '+toggle_title+'class="'+toggle_class+'"><i class="fa fa-clock-o"></i></a>';
            if ($(this).find('.toggle-icon').length) {
                $(this).find('.toggle-icon').after(revisions_toggle);
            } else {
                $(this).append(revisions_toggle);
            }
        });
        
        // when a restore link in revision list is clicked, fetch data for that
        // revision from the interface (most of the code here is related to how
        // things are presented, loading animation etc.)
        $('.field-revisions').on('click', '.field-revision-restore, .field-revision-current', function() {
            var $revision = $(this).parents('.field-revision:first');
            if ($revision.hasClass('ui-state-active')) return false;
            var settings = { render: 'Input' };
            var $revisions = $(this).parents('.field-revisions:first');
            var $if = $(this).parents('.Inputfield:first');
            var field = $revisions.data('field');
            $if.find('.field-revisions .ui-state-active').removeClass('ui-state-active');
            $revision.addClass('ui-state-active');
            $('.compare-revisions').remove();
            $('.field-revision-diff').removeClass('active');
            var $content = $if.find('.InputfieldContent') || $if.find('div.ui-widget-content');
            var $loading = $('<span class="field-revision-loading"></span>').hide().css({
                height: $content.innerHeight()+'px',
                backgroundColor: $content.css('background-color')
            });
            if ($if.hasClass('InputfieldTinyMCE') || $if.hasClass('InputfieldCKEditor')) {
                // for some inputfield types we need to get raw data as JSON
                // instead of pre-rendered inputfield markup (HTML)
                settings = { render: 'JSON' };
            }
            var revision = $revision.data('revision');
            if (cache[field+"."+revision]) {
                if (settings.render != "JSON" && revision == $revisions.data('revision')) {
                    // current (latest) revision is the only one we've got
                    // inputfield content cached for as a jQuery object
                    $content.replaceWith(cache[field+"."+revision].clone(true, true));
                    if ($if.find('.InputfieldFileList').length) {
                        // for file inputs we need to trigger 'reloaded' event
                        // manually in order to (re-)enable HTML5 AJAX uploads
                        $if.find('.InputfieldFileInit').removeClass('InputfieldFileInit');
                        $if.trigger('reloaded');
                    }
                    if ($if.find('.InputfieldAsmSelect').length) {
                        var $select = $if.find('select[multiple=multiple]');
                        var options = typeof config === 'undefined' ? { sortable: true } : config[$select.attr('id')];
                        $select.appendTo($if.find('.InputfieldAsmSelect')).show();
                        $if.find('.asmContainer').remove();
                        $select.asmSelect(options);
                    }
                } else {
                    update($if, $content, settings, field, cache[field+"."+revision]);
                }
            } else {
                $content.css('position', 'relative').prepend($loading.fadeIn(250));
                $.get(moduleConfig.processPage+'field', { revision: revision, field: field, settings: settings }, function(data) {
                    cache[field+"."+revision] = data;
                    update($if, $content, settings, field, cache[field+"."+revision]);
                    $loading.fadeOut(350, function() {
                        $(this).remove();
                    });
                });
            }
            return false;
        });

        // this function updates inputfield content based on inputfield and
        // content objects, settings (render mode etc.) and data (HTML or JSON)
        var update = function($if, $content, settings, field, data) {
            if (settings.render == "Input") {
                // format of returned data is HTML
                var before = $content.children('p.description:first');
                var after = $content.children('p.notes:first');
                $content.html(data).prepend(before).append(after);
                if ($if.hasClass('InputfieldImage') || $if.hasClass('InputfieldFile')) {
                    // trigger InputfieldImage() manually
                    InputfieldImage($);
                    // image and file data isn't editable until it has been
                    // restored; we're using an overlay to avoid confusion
                    $content.prepend('<div class="version-control-overlay"></div>');
                    $('.version-control-overlay')
                        .attr('title', moduleConfig.i18n.editDisabled)
                        .on('click', function() {
                            alert($(this).attr('title'));
                        })
                        .hover(
                            function() { $(this).parent('.overlay-parent').addClass('hover') }, 
                            function() { $(this).parent('.overlay-parent').removeClass('hover') }
                        )
                        .parent('.InputfieldContent')
                        .addClass('overlay-parent');
                } else if ($if.hasClass('Inputfield_permissions')) {
                    $('.Inputfield_permissions .Inputfield_permissions > .InputfieldContent').insertAfter($('.Inputfield_permissions:first > .InputfieldContent:first'));
                    $('.Inputfield_permissions:first > .InputfieldContent:first').remove();
                }
                if ($if.find('.InputfieldAsmSelect').length) {
                    var $select = $if.find('select[multiple=multiple]');
                    var options = typeof config === 'undefined' ? { sortable: true } : config[$select.attr('id')];
                    $select.asmSelect(options);
                }
            } else {
                // format of returned data is JSON
                $.each(data, function(property, value) {
                    var language = property.replace('data', '');
                    if (language) language = "__"+language;
                    if (typeof tinyMCE != "undefined" && tinyMCE.get('Inputfield_'+field+language)) {
                        // TinyMCE inputfield
                        tinyMCE.get('Inputfield_'+field+language).setContent(value);
                    } else if ($if.find('.InputfieldCKEditorInline').length) {
                        // CKeditor inputfield in inline mode
                        $if.find('.InputfieldCKEditorInline').html(value);
                    } else if (typeof CKEDITOR != "undefined" && CKEDITOR.instances['Inputfield_'+field+language]) {
                        // CKEditor inputfield
                        CKEDITOR.instances['Inputfield_'+field+language].setData(value);
                    }
                });
            }
        }
        
        // when revisions toggle is clicked, show the revisions table – or hide
        // it in case it was already visible
        $('.field-revisions-toggle').on('click', function() {
            if ($(this).hasClass('inactive')) return false;
            var $revisions = $(this).parent('label').siblings('.field-revisions');
            if ($revisions.is(':visible')) {
                $(this).removeClass('active');
                $revisions.addClass('sliding').slideUp('fast', function() {
                    $revisions.removeClass('sliding');
                    InputfieldColumnWidths();
                });
            } else {
                $(this).addClass('active');
                $revisions.addClass('sliding').slideDown('fast', function() {
                    $revisions.removeClass('sliding');
                    InputfieldColumnWidths();
                    if (!$revisions.hasClass('scroll-tip') && $revisions.width() < $revisions.find('table').outerWidth()) {
                        var $scroll_tip = $('<div class="scroll-tip"><i class="fa fa-arrows-h" aria-hidden="true"></i></div>');
                        $revisions.prepend($scroll_tip).addClass('scroll-tip');
                        window.setTimeout(function() {
                            $scroll_tip.animate({"left": "+=20px"}, "slow", function() {
                                $(this).animate({"left": "-=20px"}, "slow", function() {
                                    $(this).fadeOut(250);
                                });
                            });
                        }, 250);
                    }
                });
            }
            return false;
        });

        // enable Diff Match Patch if/when required
        var enableDiffMatchPatch = function(r1, r2) {
            var r1 = r1 || document.getElementById('r1').value;
            var r2 = r2 || document.getElementById('r2').value;
            var ds;
            if (r1 == r2) {
                ds = '<em>' + moduleConfig.i18n.noDiff + '</em>';
            } else {
                if (typeof diff_match_patch != 'function') {
                    $.getScript(moduleConfig.moduleDir+"diff_match_patch_20121119/javascript/diff_match_patch.js", function() {
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
        
        // when compare/diff link is clicked, display the difference between
        // selected revision and currently active revision
        $('.field-revisions')
            .on('click', '.field-revision-diff', function() {
                $('.compare-revisions').remove();
                $('.field-revision-diff').not(this).removeClass('active');
                $(this).toggleClass('active');
                if ($(this).hasClass('active')) {
                    // in this case r1 refers to current revision, r2 to selected
                    // revision. diff is fetched as HTML from revision interface.
                    var $revisions = $(this).parents('.field-revisions:first');
                    var $revision = $(this).parents('.field-revision:first');
                    var field = $revisions.data('field');
                    var r1 = $revisions.find('.ui-state-active:first').data('revision');
                    var r2 = $revision.data('revision');
                    var href = moduleConfig.processPage+'diff/?revisions='+r1+':'+r2+'&field='+field;
                    var $compare_revisions = $('<div class="compare-revisions"></div>');
                    $(this).before($compare_revisions);
                    var $parent = $(this).parents('tr:first');
                    $compare_revisions.prepend($spinner).load(href, function() {
                        if ($parent.find('ul.page-diff').length) {
                            if (typeof enableDiffSwitch != 'function') {
                                $.getScript(moduleConfig.moduleDir+"diff_switch.min.js", function() {
                                    enableDiffSwitch(moduleConfig);
                                });
                            } else {
                                enableDiffSwitch(moduleConfig);
                            }
                        } else {
                            enableDiffMatchPatch();
                        }
                        var $compare_revisions_close = $('<a class="compare-revisions-close fa fas fa-times"></a>')
                            .on('click', function() {
                                $(this).parent().next().trigger('click');
                            })
                            .appendTo($compare_revisions);
                    });
                }
                return false;
            });
    
    });
    
});
