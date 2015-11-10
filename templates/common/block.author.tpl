{if isset($author)}
	<div class="ia-items author-block">
		<div class="media ia-item account-info">
			<p>
				<a href="{ia_url item='members' data=$author type='url'}">
					{if isset($author.avatar) && $author.avatar}
						{printImage imgfile=$author.avatar width=100 height=100 title=$author.fullname|default:$author.username}
					{else}
						<img src="{$img}no-avatar.png" alt="{$author.username}">
					{/if}
				</a>
			</p>

			<div class="media-body">
				<ul class="unstyled">
					<li><i class="icon-user muted"></i> {ia_url item='members' data=$author type='link' text=$author.fullname}</li>
					<li><i class="icon-envelope muted"></i> <a href="#send-email-box" data-toggle="modal">{lang key='send_email'}</a></li>
				</ul>
			</div>

			<table class="table table-condensed text-small">
				<thead>
					<tr><th colspan="2">{lang key='author_activity'}</th></tr>
				</thead>
				<tbody>
					<tr>
						<td>{lang key='listings'}:</td>
						<td class="text-right">{$listings_num|string_format:'%d'}</td>
					<tr>
				</tbody>
			</table> 
		</div>
	</div>

	<div id="send-email-box" class="modal hide fade">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
			<h3>{lang key='send_email'}</h3>
		</div>
		<div class="modal-body">
			<div id="author-block-alert" class="alert" style="display: none;"></div>
			<div class="row-fluid">
				<div class="span6">
					<label class="control-label" for="from-name">{lang key='your_name'}:</label>
					<div class="controls">
						<input type="text" id="from-name" name="from_name" class="input-block-level">
					</div>
				</div>
				<div class="span6">
					<label class="control-label fright" for="from-email">{lang key='your_email'}:</label>
					<div class="controls fright">
						<input type="text" id="from-email" name="from_email" class="input-block-level">
					</div>
				</div>
			</div>

			<label class="control-label" for="email-body">{lang key='msg'}:</label>
			<div class="controls">
				<textarea id="email-body" name="email_body" class="input-block-level" rows="4"></textarea>
			</div>

			{if !$member}
				<div class="captcha" style="margin-top: 12px;">
					{captcha}
				</div>
			{/if}

			<input type="hidden" id="author-id" name="author_id" value="{$author.id}">
			<input type="hidden" id="regarding-page" name="regarding" value="{$core.page.title|escape:'html'}">
		</div>

		<div class="modal-footer">
			<a href="{$smarty.const.IA_SELF}#" class="btn" data-dismiss="modal">{lang key='cancel'}</a>
			<a href="{$smarty.const.IA_SELF}#" class="btn btn-primary" id="send-email">{lang key='send'}</a>
		</div>
	</div>

	{ia_add_js}
		$(function()
		{
			$('#send-email').click(function(e)
			{
				e.preventDefault();

				if (!$(this).hasClass('disabled'))
				{
					var url = intelli.config.ia_url + 'actions/read.json';
					var params = new Object();
					$.each($('input', '#send-email-box'), function()
					{
						var input_name = $(this).attr('name');
						params[input_name] = $(this).val();
					});

					params['action'] = 'send_email';
					params['email_body'] = $('#email-body').val();

					$.ajaxSetup( { async: false } );
					$.post(url, params, function(data)
					{
						if (data.error)
						{
							$('#author-block-alert').addClass('alert-danger').removeClass('alert-success');
						}
						else
						{
							$('#author-block-alert').addClass('alert-success').removeClass('alert-danger');
							$('#send-email').addClass('disabled');
							setTimeout(function()
							{
								$('#send-email-box').modal('hide');
							}, 1500);
						}

						$('#author-block-alert').html(data.message.join('<br>')).show();
					});

					$.ajaxSetup( { async: true } );
				}
			});
		});
	{/ia_add_js}
{/if}