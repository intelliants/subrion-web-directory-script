$(function() {
    var modal = $('#report-listing-modal');
    $('#js-cmd-report-listing').on('click', function(e) {
        e.preventDefault();

        listingId = $(this).data('id');
        modal.modal();
    });

    $('#report-listing-form').on('submit', function(e) {
        e.preventDefault();

        var comment = $('#report-listing-comment');
        var commentText = comment.val();

        intelli.post(intelli.config.packages.directory.url + 'listing/read.json', {
            action: 'report', id: listingId, comments: commentText
        }, function () {
            comment.val('');
            modal.modal('hide');
            intelli.notifFloatBox({msg: _t('you_sent_report'), type: 'success', autohide: true});
        });
    });
});