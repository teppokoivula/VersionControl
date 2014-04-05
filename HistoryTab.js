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

});