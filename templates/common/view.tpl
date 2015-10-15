<div class="media ia-item ia-item-view">
	{capture append='tabs_before' name='common'}
		<div class="ia-wrap clearfix">
			{if $item.featured}<span class="ia-badge ia-badge-featured" title="{lang key='featured'}"><i class="icon-star"></i></span>{/if}
			{if $item.sponsored}<span class="ia-badge ia-badge-sponsored" title="{lang key='sponsored'}"><i class="icon-dollar"></i></span>{/if}

			{if $core.config.directory_enable_thumbshots}
				<div class="pull-right text-center">
					<img src="http://free.pagepeeker.com/v2/thumbs.php?size=m&url={$item.url|escape:url}" class="media-object thumbnail js-thumbnail" data-url="{$item.url|escape:url}">
					{if $item.rank}
						{section name=star loop=$item.rank}<i class="icon-star icon-orange"></i> {/section}
					{/if}
				</div>
			{/if}

			<div class="media-body">
				<ul class="ia-list-items ia-list-items--left-margin">
					<li><i class="icon-link"></i> <a href="{$item.url}" target="_blank">{$item.url|strip_tags|truncate:50:'...'}</a></li>
					<li><i class="icon-folder-open"></i> <a href="{ia_url item='categs' data=$item type='url'}">{$item.category_title}</a></li>
					{if $core.config.directory_enable_pagerank && $item.pagerank}
						<li><i class="icon-signal"></i> {lang key='pagerank'} {$item.pagerank}</li>
					{/if}
					{if $core.config.directory_enable_alexarank && $item.alexa_rank}
						<li>
							<i class="icon-globe"></i> {lang key='alexa_rank'} 
							<a href="http://www.alexa.com/siteinfo/{$item.domain}#">{$item.alexa_rank}</a>
						</li>
					{/if}
				</ul>
			</div>

			<div class="ia-item-body">{$item.description}</div>
		</div>
	{/capture}

	{include file='item-view-tabs.tpl' isView=true exceptions=array('title', 'url', 'reciprocal', 'description')}

	<div class="ia-item-panel">
		<!-- AddThis Button BEGIN -->
		<div class="addthis_toolbox addthis_default_style panel-item pull-left">
			<a class="addthis_counter addthis_pill_style"></a>
		</div>
		<script type="text/javascript">var addthis_config = { "data_track_addressbar":true };</script>
		<script type="text/javascript" src="//s7.addthis.com/js/300/addthis_widget.js#pubid=ra-5228073734bf0c90"></script>
		<!-- AddThis Button END -->

		<span class="panel-item pull-left">
			<i class="icon-user"></i>
			{if $item.account_username}
				<a href="{ia_url item='members' data=$author type='url'}">{$item.member}</a>
			{else}
				<i>{lang key='guest'}</i>
			{/if}
		</span>
		<span class="panel-item pull-right"><i class="icon-time"></i> {$item.date_added|date_format:$core.config.date_format}</span>
		<span class="panel-item pull-right"><i class="icon-eye-open"></i> {$item.views_num} {lang key='views'}</span>

		{if $member && $smarty.session.user.id != $item.member_id}
			{printFavorites item=$item itemtype='listings' classname='pull-left'}
		{/if}
	</div>

	{ia_hooker name='smartyViewListingBeforeFooter'}
</div>

{ia_add_media files='js:_IA_URL_packages/directory/js/front/view'}