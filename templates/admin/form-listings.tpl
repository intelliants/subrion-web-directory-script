<form method="post" enctype="multipart/form-data" class="sap-form form-horizontal">
	{preventCsrf}

	<input type="hidden" id="js-listing-id" value="{if iaCore::ACTION_EDIT == $pageAction}{$id}{/if}">

	{capture name='general' append='fieldset_before'}
		<div id="category_fieldzone" class="row">
			<label class="col col-lg-2 control-label">
				{lang key='field_category_id'}<br>
				<a href="#" class="categories-toggle" id="js-tree-toggler">{lang key='open_close'}</a>
			</label>
			<div class="col col-lg-4">
				<input type="text" id="js-category-label" value="{if $category}{$category.title|escape:'html'}{else}{lang key='field_category_id_annotation'}{/if}" disabled>
				<div id="js-tree" class="tree categories-tree"></div>
				<input type="hidden" name="category_id" id="input-category" value="{if !isset($item.category_id)}1{else}{$item.category_id}{/if}">
			</div>
		</div>

		{ia_add_js}
$(function()
{
	new IntelliTree(
	{
		url: intelli.config.ia_url + 'directory/categories/read.json?get=tree',
		onchange: intelli.fillUrlBox,
		nodeOpened: [0,{$category.parents}],
		nodeSelected: {$item.category_id}
	});
	$('input[name=reported_as_broken]').change(function() {
		var comments = $('#reported-as-broken-comments');
		if (comments.length > 0) {
			comments.toggle();
		}
	});
});
		{/ia_add_js}
		{ia_add_media files='tree'}

		{if $core.config.listing_crossed}
			<div id="crossed_fieldzone" class="row">
				<label for="" class="col col-lg-2 control-label">
					{lang key='crossed_categories'} <span class="label label-info" id="crossed-limit">{$core.config.listing_crossed_limit - count($category.crossed)|default:0}</span><br>
					<a href="#" class="categories-toggle js-categories-toggle" data-toggle="#tree-crossed">{lang key='open_close'}</a>
				</label>
				<div class="col col-lg-4" style="margin: 8px 0">
					<div id="crossed-list">
						{if $category && isset($category.crossed) && $category.crossed}
							{foreach $category.crossed as $crid => $link}
								<span data-id="{$crid}">{$link}</span>{if !$link@last}, {/if}
							{/foreach}
						{else}
							<div class="alert alert-info">{lang key='no_crossed_categories'}</div>
						{/if}
					</div>

					<div id="tree-crossed" class="tree categories-tree"{if (isset($category.crossed) && $category.crossed) || ('edit' == $pageAction)} style="display:none"{/if}></div>
					<input type="hidden" id="crossed-links" name="crossed_links" value="{if isset($category.crossed) && $category.crossed}{','|implode:array_keys($category.crossed)}{elseif isset($smarty.post.crossed_links)}{$smarty.post.crossed_links}{/if}">
				</div>
			</div>
		{/if}
	{/capture}

	{capture name='title' append='field_after'}
		<div id="title_alias" class="row">
			<label for="" class="col col-lg-2 control-label">{lang key='title_alias'}</label>
			<div class="col col-lg-4">
				<input type="text" name="title_alias" id="field_title_alias" value="{if isset($item.title_alias)}{$item.title_alias}{/if}">
				<p class="help-block text-break-word">{lang key='page_url_will_be'}: <span class="text-danger" id="title_url">{$smarty.const.IA_URL}{if isset($item.title_alias) && isset($category.title_alias)}{$category.title_alias}{$item.title_alias}{/if}</span></p>
			</div>
		</div>
	{/capture}

	{capture name='general' append='fieldset_after'}
		<div id="rank" class="row">
			<label class="col col-lg-2 control-label">{lang key='rank'}</label>
			<div class="col col-lg-4">
				<select name="rank" id="field_rank">
				{section name=star loop=6}
					<option value="{$smarty.section.star.index}"{if isset($item.rank) && $item.rank == $smarty.section.star.index} selected="selected"{/if}>{$smarty.section.star.index}</option>
				{/section}
				</select>
			</div>
		</div>
		{if iaCore::ACTION_EDIT == $pageAction}
		<div id="reported-as-broken" class="row">
			<label class="col col-lg-2 control-label">{lang key='reported_as_broken'}</label>
			<div class="col col-lg-4">
				{html_radio_switcher name='reported_as_broken' value=$item.reported_as_broken}
			</div>
		</div>
			{if $item.reported_as_broken && isset($item.reported_as_broken_comments) && $item.reported_as_broken_comments}
				<div id="reported-as-broken-comments" class="row">
					<label class="col col-lg-2 control-label">{lang key='reported_as_broken_comments'}</label>
					<div class="col col-lg-4">
						{$item.reported_as_broken_comments|strip_tags|nl2br}
					</div>
				</div>
			{/if}
		{/if}
	{/capture}

	{ia_hooker name='smartyAdminSubmitItemBeforeFields'}

	{include file='field-type-content-fieldset.tpl' isSystem=true statuses=$statuses}
</form>

{ia_hooker name='smartyAdminSubmitItemBeforeFooter'}
{ia_add_media files='js:_IA_URL_packages/directory/js/admin/listings'}