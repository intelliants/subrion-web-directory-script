$(function()
{
	intelli.flags = {doNotRespond: false, waitingTrigger: false};

	var $cSelect = $('#js-l-c'),
		$scSelect = $('#js-l-sc');

	$cSelect.on('change', function(e)
	{
		var value = $(this).val(), doNotRespond = intelli.flags.doNotRespond;

		$scSelect.val(0).prop('disabled', true).find('option:not(:first)').remove();

		intelli.flags.doNotRespond || intelli.search.run();

		if (value != '')
		{
			$.getJSON(intelli.config.packages.directory.url + 'directory/read.json', {id: value}, function(response)
			{
				if (response && response.length > 0)
				{
					var d = $scSelect.data('value');
					$.each(response, function(index, item)
					{
						var $option = $('<option>').val(item.id).text(item.text);
						if (d == item.id) $option.attr('selected', true);
						$scSelect.append($option);
					});

					$scSelect.prop('disabled', false);
					//waitingTrigger && $scSelect.trigger('change');
				}
			});
		}
		else {
			$scSelect.prop('disabled', true);
		}

		intelli.flags.doNotRespond = false;
		intelli.flags.waitingTrigger = false;
	});

	$scSelect.on('change', function()
	{
		intelli.search.run();
	});

	'search' == intelli.pageName || (intelli.flags.doNotRespond = true);
	$scSelect.data('value') && (intelli.flags.waitingTrigger = true);

	$cSelect.trigger('change');
});