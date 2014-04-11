$(function() {

    // translations etc. are defined in VersionControl.module
    var moduleConfig = config.VersionControl;

    // edit comment feature
    $('.edit-comment').bind('click', function() {
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
    $('#history_filters select').bind('change', function() {
        var params = "";
        $(this).parents('#history_filters:first').find('input, select').each(function() {
            if (params) params += "&";
            params += $(this).attr('name') + "=" + $(this).val();
        });
        window.location.search = params;
    });

    // activate history tab if history filters were toggled
    var $tab = $('#VersionControlHistory');
    if ($tab.length && $tab.children('ul:first').data('active')) {
        $('a#_' + $tab.attr('id')).trigger('click');
    }

    // remove history filters from Page Edit form on submit
    $('form#ProcessPageEdit').bind('submit', function() {
        $(this).find('#history_filters').remove();
    });

    // preview feature
    $('a.preview').on('click', function() {
        var $a = $(this);
        $('body')
            .addClass('preview')
            .data('top', $('body').scrollTop())
            .append('<iframe id="preview" seamless></iframe>')
            .append('<a id="close-preview" href="#"><i class="fa fa-times-circle"></i>' + moduleConfig.i18n.closePreview.replace('%s', $a.data('date')) + '</a>');
        $('#preview').fadeIn(function() {
            $(this).attr('src', $a.attr('href'));
            $('#close-preview').fadeIn();
        });
        $(document).on('keyup.preview', function(e) {
            if (e.keyCode == 27) $('#close-preview').trigger('click');
        });
        $('#close-preview').on('click', function() {
            $('body')
                .removeClass('preview')
                .animate({ scrollTop: $('body').data('top') }, 'fast', function() {
                    $a.parents('tr:first').effect("highlight", {}, 1500);
                });
            $('#close-preview').fadeOut(function() {
                $(this).remove();
            });
            $('#preview').fadeOut(function() {
                $(this).remove();
            });
            $(document).unbind('keyup.preview');
        });
        return false;
    });

});