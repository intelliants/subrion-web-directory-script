<form method="post" enctype="multipart/form-data" class="ia-form">
	{preventCsrf}

	{include file='plans.tpl'}

	{capture name='general' append='fieldset_before'}
		<div class="fieldset">
			<div class="fieldset__header">{lang key='field_category_id'}</div>
			<div class="fieldset__content">
				<input type="text" id="js-category-label" value="{if isset($category.title)}{$category.title|escape:'html'}{else}{lang key="field_category_id_annotation"}{/if}" disabled class="form-control">
				<a href="#" class="categories-toggle" id="js-tree-toggler">{lang key='open_close'}</a>
				<div id="js-tree" class="tree categories-tree"{if iaCore::ACTION_EDIT == $pageAction} style="display:none"{/if}></div>
				<input type="hidden" name="category_id" id="input-category" value="{$category.id}">
			</div>
		</div>
		{ia_add_js}
$(function()
{
	new IntelliTree(
	{
		url: intelli.config.packages.directory.url + 'add/read.json?get=tree',
		nodeOpened: [{$category.parents}],
		nodeSelected: {$category.id}
	});
});
		{/ia_add_js}
		{ia_add_media files='tree'}

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
		{include file='captcha.tpl'}

		<div class="fieldset__actions">
			<button type="submit" class="btn btn-primary" name="data-listing">{lang key='save'}</button>
		</div>
	{/capture}

	{ia_hooker name='smartyListingSubmitBeforeFooter'}

	{include file='item-view-tabs.tpl'}
</form>
{ia_add_media files='js:_IA_URL_packages/directory/js/front/listings'}
{ia_hooker name='smartyDirectoryListingSubmitAfterJs'}