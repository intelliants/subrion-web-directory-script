$(function()
{
	var $cSelect = $('#js-l-c'),
		$scSelect = $('#js-l-sc');

	$cSelect.on('change', function(e)
	{
		var value = $(this).val();

		$scSelect.val(0).prop('disabled', true).find('option:not(:first)').remove();

		if (value != '')
		{
			$.getJSON(intelli.config.packages.directory.url + 'directory/read.json', {id: value}, function(response)
			{
				if (response && response.length > 0)
				{
					var d = $scSelect.data('id');
					$.each(response, function(index, item)
					{
						var $option = $('<option>').val(item.id).text(item.text);
						if (d == item.id) $option.attr('selected', true);
						$scSelect.append($option);
					});

					$scSelect.prop('disabled', false);
				}
			});
		}
		else {
			$scSelect.prop('disabled', true);
		}
	});

	if ($scSelect.data('id')) $cSelect.trigger('change');

	$scSelect.on('change', function()
	{
		intelli.search.run();
	});
});