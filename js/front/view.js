$(function()
{
	$('#js-cmd-report-listing').on('click', function(e)
	{
		e.preventDefault();

		if (confirm(_t('do_you_want_report_broken')))
		{
			$.post(intelli.config.packages.directory.url + 'listing/read.json', {action: 'report', id: $(this).data('id')}, function(data)
			{
				intelli.notifFloatBox({msg: _t('you_sent_report'), type: 'success', autohide: true});
			});
		}
	});
});