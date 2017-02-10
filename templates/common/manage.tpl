<form method="post" enctype="multipart/form-data" class="ia-form">
	{preventCsrf}

	{include 'plans.tpl'}

	{capture name='general' append='fieldset_before'}
		{include 'tree.tpl' url="{$core.packages.directory.url}add/tree.json"}

		{if $core.config.listing_crossed}
			<div class="fieldset">
				<div class="fieldset__header">
					{lang key='crossed_categories'} <small>{lang key='limit'}: <span class="badge">{$core.config.listing_crossed_limit}</span></small>
				</div>
				<div class="fieldset__content">
					<a href="#" id="change_crossed" class="categories-toggle js-categories-toggle" data-toggle="#tree-crossed">{lang key='open_close'}</a>
					<div id="tree-crossed" class="tree categories-tree" style="display:none"></div>
					<input type="hidden" id="crossed_links" name="crossed_links" value="{if isset($smarty.post.crossed_links)}{$smarty.post.crossed_links|escape:'html'}{/if}">

					<p class="m-t">
						{if $category && isset($category.crossed) && $category.crossed}
							{lang key='prev_crossed'}:
							{foreach $category.crossed as $entryId => $link}
								<span class="label label-success js-checked-crossed-node" data-id="{$entryId}">{$link}</span>{if !$link@last} {/if}
							{/foreach}
						{/if}
					</p>
					<p id="crossed_title" class="gap-top"></p>
				</div>
			</div>
		{/if}
	{/capture}

	{capture append='tabs_after' name='__all__'}
		{include 'captcha.tpl'}

		<div class="fieldset__actions">
			<button type="submit" class="btn btn-primary" name="data-listing">{lang key='save'}</button>
		</div>
	{/capture}

	{ia_hooker name='smartyListingSubmitBeforeFooter'}

	{include 'item-view-tabs.tpl'}
</form>
{ia_add_media files='js:_IA_URL_modules/directory/js/front/listings'}
{ia_hooker name='smartyDirectoryListingSubmitAfterJs'}