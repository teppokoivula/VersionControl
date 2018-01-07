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

        // iterate through field specific revision containers and add their
        // contents to that fields header (.ui-widget-header) only if they
        // contain at least one revision other than what's currently used
        $('#version-control-data > div').each(function() {
            if ($(this).data('revision')) {
                var $if = $('.Inputfield_'+$(this).data('field'));
                $if.find('> label')
                    .addClass('with-history')
                    .before($(this));
                $(this).find('a:first').addClass('ui-state-active');
                if ($if.hasClass('InputfieldTinyMCE') || $if.hasClass('InputfieldCKEditor')) return;
                var $cacheobj = $if.find('.InputfieldContent') || $if.find('div.ui-widget-content');
                cache[$(this).data('field')+"."+$(this).data('revision')] = $cacheobj.clone(true, true);
            }
        });
        
        // iterate through history-enabled fields to add a revision toggle
        $('.ui-widget-header.with-history, .InputfieldHeader.with-history').each(function() {
            var toggle_class = "field-revisions-toggle";
            var toggle_title = "";
            if ($(this).siblings('.field-revisions').find('li').length < 1) {
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
        
        // when a link in revision list is clicked, fetch data for appropriate
        // revision from the interface (most of the code here is related to how
        // things are presented, loading animation etc.)
        $('.field-revisions').on('click', '> ul > li > a', function() {
            if ($(this).hasClass('ui-state-active')) return false;
            var settings = { render: 'Input' };
            var $this = $(this);
            var $if = $this.parents('.Inputfield:first');
            var field = $this.parents('.field-revisions:first').data('field');
            $if.find('.field-revisions .ui-state-active').removeClass('ui-state-active');
            $this.addClass('ui-state-active');
            $('.compare-revisions').remove();
            var $content = $if.find('.InputfieldContent') || $if.find('div.ui-widget-content');
            var $loading = $('<span class="field-revisions-loading"></span>').hide().css({
                height: $content.innerHeight()+'px',
                backgroundColor: $content.css('background-color')
            });
            if ($if.hasClass('InputfieldTinyMCE') || $if.hasClass('InputfieldCKEditor')) {
                // for some inputfield types we need to get raw data as JSON
                // instead of pre-rendered inputfield markup (HTML)
                settings = { render: 'JSON' };
            }
            var revision = $(this).data('revision');
            if (cache[field+"."+revision]) {
                if (settings.render != "JSON" && revision == $this.parents('.field-revisions:first').data('revision')) {
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
                $.get(moduleConfig.processPage+'field', { revision: $this.data('revision'), field: field, settings: settings }, function(data) {
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
        
        // when mouse cursor is moved on a revisions toggle (or it is clicked,
        // to make it accessible for touch devices etc.) show (or hide if it 
        // was already visible) revision list
        $('.field-revisions-toggle').on('click mouseenter', function() {
            if ($(this).hasClass('inactive')) return false;
            var $revisions = $(this).parent('label').siblings('.field-revisions');
            var show = ($revisions.is(':visible')) ? false : true;
            $('.field-revisions').slideUp();
            if (show) $revisions.slideDown();
            return false;
        });

        // hide revision list when user moves mouse cursor off it; timeout
        // started out as a bugfix, but now it's kept purely for usability
        var revision_timeout;
        $('.field-revisions')
            .on('mouseenter', function() {
                $(this).slideDown();
                if (revision_timeout) {
                    clearTimeout(revision_timeout);
                    revision_timeout = false;
                }
            })
            .on('mouseleave', function() {
                var $this = $(this);
                revision_timeout = setTimeout(function() {
                    revision_timeout = false;
                    $this.slideUp(function() {
                        $('.compare-revisions').remove();
                    });
                }, 500);
            });

        // if <ul> element containing revision history is long enough to get
        // vertical scrollbar, add some extra padding to compensate for it
        $('.field-revisions > ul').each(function() {
            // fetch DOM object matching current jQuery object (jQuery object
            // doesn't have clientHeight or scrollHeight which we need here)
            var dom_ul = $(this)[0];
            // to get correct heights parent element needs to be visible (this
            // should happen so fast that user never notices anything strange)
            $(this).parent().show();
            if (dom_ul.clientHeight < dom_ul.scrollHeight) {
                $(this).addClass('scroll');
            }
            $(this).parent().hide();
        });

        // enable Diff Match Patch if/when required
        var enableDiffMatchPatch = function() {
            var dmp = new diff_match_patch();
            var r1 = document.getElementById('r1').value;
            var r2 = document.getElementById('r2').value;
            dmp.Diff_Timeout = moduleConfig.diff.timeout;
            dmp.Diff_EditCost = moduleConfig.diff.editCost;
            var ms_start = (new Date()).getTime();
            var d = dmp.diff_main(r1, r2);
            var ms_end = (new Date()).getTime();
            var s = (ms_end - ms_start) / 1000 + 's';
            if (moduleConfig.diff.cleanup) {
                dmp['diff_cleanup' + moduleConfig.diff.cleanup](d);
            }
            var ds = dmp.diff_prettyHtml(d);
            document.getElementById('diff').innerHTML = ds;
        }
        
        // when mouse cursor is moved on a revision link, show compare/diff
        // link, which -- when clicked -- loads a text diff for displaying
        // differences between selected revision and current revision.
        $('.field-revisions.diff')
            .on('hover', '> ul > li > a', function() {
                $('.compare-revisions').remove();
                if (!$(this).hasClass('ui-state-active')) {
                    // in this case r1 refers to current revision, r2 to selected
                    // revision. diff is fetched as HTML from revision interface.
                    var field = $(this).parents('.field-revisions:first').data('field');
                    var r1 = $(this).parents('.field-revisions:first').find('.ui-state-active').data('revision');
                    var r2 = $(this).data('revision');
                    var href = moduleConfig.processPage+'diff/?revisions='+r1+':'+r2+'&field='+field;
                    var label = moduleConfig.i18n.compareWithCurrent;
                    $(this).before('<div class="compare-revisions"><a class="diff-trigger" href="'+href+'">'+label+'</a></div>');
                }
            })
            .on('click', '.compare-revisions > a.diff-trigger', function() {
                var $parent = $(this).parent();
                var $loading = $('<span class="field-revisions-loading"></span>').hide().css({
                    height: $parent.innerHeight()+'px',
                    backgroundColor: $parent.css('background-color')
                });
                $parent.prepend($loading.fadeIn(250)).load($(this).attr('href'), function() {
                    $(this).find('a.diff-trigger').remove();
                    if ($parent.find('ul.page-diff').length) {
                        if (typeof enableDiffSwitch != 'function') {
                            $.getScript(moduleConfig.moduleDir+"diff_switch.min.js", function() {
                                enableDiffSwitch(moduleConfig);
                            });
                        } else {
                            enableDiffSwitch(moduleConfig);
                        }
                    } else {
                        if (typeof diff_match_patch != 'function') {
                            $.getScript(moduleConfig.moduleDir+"diff_match_patch_20121119/javascript/diff_match_patch.js", function() {
                                enableDiffMatchPatch();
                            });
                        } else {
                            enableDiffMatchPatch();
                        }
                    }
                    $(this).animate({
                        width: '400px',
                        padding: '14px'
                    });
                    $loading.fadeOut(350, function() {
                        $(this).remove();
                    });
                });
                return false;
            });
    
    });
    
});
