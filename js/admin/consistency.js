$(function() {
    $('#js-cmd-recount-listings').on('click', function (e) {
        e.preventDefault();

        var start = 0,
            limit = 150,
            action = 'recount_listings',
            total = 0,
            progress = 0,
            interval = 2000,
            url = intelli.config.admin_url + '/directory/categories/consistency.json';

        var barHolder = $('#recount-listings-progress');
        var bar = $('.progress-bar', barHolder);
        var button = $(this);
        var startText = button.text();

        barHolder.removeClass('hidden').addClass('active');
        bar.text('');
        button.prop('disabled', true);

        intelli.post(url, {action: 'pre_recount_listings'}, function (response) {
            total = response.total;

            var timer = setInterval(function () {
                intelli.post(url, {start: start, limit: limit, action: action}, function () {
                    start += limit;
                    progress = Math.round(start / total * 100);

                    if (start > total) {
                        clearInterval(timer);
                        barHolder.removeClass('active');
                        bar.css('width', '100%');
                        intelli.notifFloatBox({msg: _t('done'), type: 'notif', autohide: true});
                        button.text(startText).prop('disabled', false);
                    }
                    else {
                        bar.css('width', progress + '%');
                        button.text(progress + '%');
                    }
                });
            }, interval);
        });
    });
});