$(function() {

    // translations etc. are defined in VersionControl.module
    var moduleConfig = config.VersionControl;

    // edit comment feature
    $('.InputfieldWrapper#VersionControlHistory').on('click', '.edit-comment', function() {
        var $container = $(this).parent('td').prev('td');
        var revision = $(this).data('revision');
        var comment = prompt(moduleConfig.i18n.commentPrompt, $container.text());
        if (comment !== null) {
            $.post(moduleConfig.processPage+'comment', { revision: revision, comment: comment }, function(data) {
                $container.text(data).effect("highlight", {}, 500);
            });
        }
        return false;
    });

    // update GET params when history filters are toggled
    $('.InputfieldWrapper#VersionControlHistory').on('change', '#history_filters select', function(event) {
        var params = "";
        $(this).parents('#history_filters:first').find('input, select').each(function() {
            if (params) params += "&";
            params += $(this).attr('name') + "=" + $(this).val();
            event.stopPropagation();
        });
        window.location.search = params;
    });

    // activate history tab if history filters were toggled
    var $tab = $('#VersionControlHistory');
    if ($tab.length && $tab.children('ul:first').data('active')) {
        $('a#_' + $tab.attr('id')).trigger('click');
    }

    // remove history filters from Page Edit form on submit
    $('form#ProcessPageEdit').on('submit', function() {
        $(this).find('#history_filters').remove();
    });

    // preview feature
    $tab.on('click', 'a.preview', function() {
        var $a = $(this);
        $('body')
            .append('<div id="preview-overlay"></div>')
            .append('<div id="preview"><iframe src="' + $a.attr('href') + '" seamless></iframe></div>');
        $('#preview iframe').on('load', function(e) {
            $('#preview').addClass('loaded');
            $(e.target.contentWindow).on('beforeunload', function(e) {
                e.preventDefault();
                closePreview($a);
            });
        });
        $('#preview-overlay').fadeIn();
        $('#preview')
            .show()
            .animate({ right: 0 }, 500, function() {
                $('body').addClass('preview');
            });
        $(document)
            .on('keyup.preview', function() {
                closePreview($a);
            })
            .on('click.preview', function() {
                closePreview($a);
            });
        return false;
    });
    var closePreview = function($a) {
        $(window).off('blur.preview');
        $(document).off('keyup.preview');
        $(document).off('click.preview');
        $('body').removeClass('preview');
        $('#preview-overlay').fadeOut(function() {
            $(this).remove();
        });
        $('#preview').animate({ right: '-80%' }, 500, function() {
            $(this).remove();
        });
        $a.parents('tr:first').effect("highlight", {}, 1500);
        return false;
    }

    // Confirm before restoring page
    $tab.on('click', 'a.restore', function() {
        return confirm(moduleConfig.i18n.confirmRestore);
    });

});
