$(function()
{
	$('#js-cmd-report-listing').on('click', function(e)
	{
		e.preventDefault();

		var id = $(this).data('id');

		intelli.confirm(_t('do_you_want_report_broken'), '', function(result) {
			$.post(intelli.config.packages.directory.url + 'listing/read.json', {action: 'report', id: id}, function(data)
			{
				intelli.notifFloatBox({msg: _t('you_sent_report'), type: 'success', autohide: true});
			});
		});
	});
});