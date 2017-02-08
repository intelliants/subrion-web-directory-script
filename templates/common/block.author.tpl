{if isset($author)}
	<div class="ia-item-author">
		<a class="ia-item-author__image" href="{ia_url type='url' item='members' data=$author}">
			{ia_image file=$author.avatar type='thumbnail' width=120 title=$author.fullname|default:$author.username gravatar=true email=$author.email}
		</a>
		<div class="ia-item-author__content">
			<h4 class="ia-item__title"><a href="{ia_url type='url' item='members' data=$author}">{$author.fullname}</a></h4>
			<div class="ia-item__additional">
				<p><span class="fa fa-link"></span> {lang key='listings'}: <b>{$listings_num|string_format:'%d'}</b></p>
				<p><a href="#send-email-box" data-toggle="modal"><span class="fa fa-envelope"></span> {lang key='send_email'}</a></p>

				{if $author.phone}
					<p><span class="fa fa-phone"></span> {lang key='field_phone'}: {$author.phone}</p>
				{/if}
			</div>
			{if $author.facebook || $author.twitter || $author.gplus || $author.linkedin}
				<p class="text-center">
					{if isset($author.facebook) && $author.facebook}
						<a href="{$author.facebook}" class="fa-stack fa-lg"><i class="fa fa-circle fa-stack-2x"></i><i class="fa fa-facebook fa-stack-1x fa-inverse"></i></a>
					{/if}
					{if isset($author.twitter) && $author.twitter}
						<a href="{$author.twitter}" class="fa-stack fa-lg"><i class="fa fa-circle fa-stack-2x"></i><i class="fa fa-twitter fa-stack-1x fa-inverse"></i></a>
					{/if}
					{if isset($author.gplus) && $author.gplus}
						<a href="{$author.gplus}" class="fa-stack fa-lg"><i class="fa fa-circle fa-stack-2x"></i><i class="fa fa-google-plus fa-stack-1x fa-inverse"></i></a>
					{/if}
					{if isset($author.linkedin) && $author.linkedin}
						<a href="{$author.linkedin}" class="fa-stack fa-lg"><i class="fa fa-circle fa-stack-2x"></i><i class="fa fa-linkedin fa-stack-1x fa-inverse"></i></a>
					{/if}
				</p>
			{/if}
		</div>

		{ia_hooker name='smartyViewListingAuthorBlock'}

	</div>

	<div class="modal fade" id="send-email-box">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title">{lang key="send_email"}</h4>
				</div>
				<div class="modal-body">
					<div id="author-block-alert" class="alert" style="display: none;"></div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="from-name">{lang key='your_name'}:</label>
								<input type="text" id="from-name" name="from_name" class="form-control">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="from-email">{lang key='your_email'}:</label>
								<input type="text" id="from-email" name="from_email" class="form-control">
							</div>
						</div>
					</div>

					<div class="form-group">
						<label for="email-body">{lang key='msg'}:</label>
						<textarea id="email-body" name="email_body" class="form-control" rows="4"></textarea>
					</div>

					{if !$member}
						<div class="captcha">
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
			</div><!-- /.modal-content -->
		</div><!-- /.modal-dialog -->
	</div><!-- /.modal -->

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