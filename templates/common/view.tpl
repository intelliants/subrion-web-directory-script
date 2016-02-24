<div class="ia-item-view {$item.status}{if $item.sponsored} ia-item-sponsored{/if}{if $item.featured == 1} ia-item-featured{/if}">
	<div class="ia-item-view__info">
		{if $item.sponsored}<span class="ia-item-view__info__item"><span class="label label-warning">{lang key='sponsored'}</span></span>{/if}
		{if $item.featured}<span class="ia-item-view__info__item"><span class="label label-info">{lang key='featured'}</span></span>{/if}
		<span class="ia-item-view__info__item">
			<span class="fa fa-calendar"></span>
			{lang key='on'} {$item.date_added|date_format:$core.config.date_format}
		</span>
		<span class="ia-item-view__info__item"><span class="fa fa-folder"></span> <a href="{ia_url item='categs' data=$item type='url'}">{$item.category_title}</a></span>
		<span class="ia-item-view__info__item"><span class="fa fa-eye"></span> {$item.views_num} {lang key='views'}</span>
	</div>

	<div class="ia-item m-b">
		{if $core.config.directory_enable_thumbshots}
			<div class="pull-left text-center m-r">
				<img src="http://free.pagepeeker.com/v2/thumbs.php?size=m&url={$item.url|escape:url}" class="thumbnail js-thumbnail" data-url="{$item.url|escape:url}">
				{if $item.rank}
					{section name=star loop=$item.rank}<span class="fa fa-star text-warning"></span> {/section}
				{/if}
			</div>
		{/if}

		<div class="ia-item__content">
			<p><span class="fa fa-link"></span> <a href="{$item.url}" target="_blank">{$item.url|strip_tags|truncate:50:'...'}</a></p>

			{if $core.config.directory_enable_pagerank && $item.pagerank}
				<p><span class="fa fa-signal"></span> {lang key='pagerank'} {$item.pagerank}</p>
			{/if}

			{if $core.config.directory_enable_alexarank && $item.alexa_rank}
				<p><span class="fa fa-globe"></span> {lang key='alexa_rank'} <a href="http://www.alexa.com/siteinfo/{$item.domain}#">{$item.alexa_rank}</a></p>
			{/if}

			<!-- AddThis Button BEGIN -->
			<div class="addthis_toolbox addthis_default_style panel-item pull-left">
				<a class="addthis_counter addthis_pill_style"></a>
			</div>
			<script type="text/javascript">var addthis_config = { "data_track_addressbar":true };</script>
			<script type="text/javascript" src="//s7.addthis.com/js/300/addthis_widget.js#pubid=ra-5228073734bf0c90"></script>
			<!-- AddThis Button END -->
		</div>
	</div>

	<div class="ia-item-view__section">
		<div>{$item.description}</div>
	</div>
	{include file='item-view-tabs.tpl' isView=true exceptions=array('title', 'url', 'reciprocal', 'description')}

	{if isset($actionClaimListing)}
		<a id="{$actionClaimListing.id}" {foreach $actionClaimListing.attributes as $attr => $value}{$attr}="{$value}" {/foreach}>{$actionClaimListing.title}</a>
	{/if}
	{ia_hooker name='smartyViewListingBeforeFooter'}
</div>

{ia_add_media files='js:_IA_URL_packages/directory/js/front/view'}

{ia_add_js}
$(function() {
	$('.js-delete-listing').on('click', function(e) {
		e.preventDefault();

		intelli.confirm(_t('do_you_really_want_to_delete_listing'), { url: $(this).attr('href') });
	});
});
{/ia_add_js}