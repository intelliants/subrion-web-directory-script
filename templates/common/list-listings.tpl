<div class="media ia-item ia-item-bordered {$listing.status}{if $listing.featured} ia-item-featured{/if}{if $listing.sponsored} ia-item-sponsored{/if}" id="tdlisting{$listing.id}">
	{if $listing.featured}<span class="ia-badge ia-badge-featured" title="{lang key='featured'}"><i class="icon-star"></i></span>{/if}
	{if $listing.sponsored}<span class="ia-badge ia-badge-sponsored" title="{lang key='sponsored'}"><i class="icon-dollar"></i></span>{/if}
	{if $member && $member.id == $listing.member_id && 'active' != $listing.status}
		<span class="ia-badge ia-badge-{$listing.status}" title="{lang key=$listing.status default=$listing.status}"><i class="icon-warning-sign"></i></span>
	{/if}

	{if $core.config.directory_enable_thumbshots}
		<div class="pull-left text-center">
			<img src="http://free.pagepeeker.com/v2/thumbs.php?size=m&url={$listing.url|escape:url}" class="media-object thumbnail" width="150">
			{if $listing.rank}
				{section name=star loop=$listing.rank}<i class="icon-star icon-orange"></i> {/section}
			{/if}
		</div>
	{/if}

	<div class="media-body">
		<h3 class="media-heading">
			{if isset($listing.crossed) && $listing.crossed}@ {/if}
			{if !$core.config.directory_redirect_to_site}
				{ia_url type='link' item='listings' data=$listing text=$listing.title}
			{else}
				<a href="{$listing.url}" target="_blank">{$listing.title|escape:'html'}</a>
			{/if}
		</h3>
		
		<ul class="ia-list-items ia-list-items--left-margin">
			<li><i class="icon-link"></i> <a href="{$listing.url}" class="url">{$listing.url}</a></li>
			{if $core.config.directory_enable_pagerank && $listing.pagerank}
				<li><i class="icon-signal"></i> {lang key='pagerank'} {$listing.pagerank}
			{/if}
			{if $core.config.directory_enable_alexarank && $listing.alexa_rank}
				<li><i class="icon-globe"></i> {lang key='alexa_rank'}: <a href="http://www.alexa.com/siteinfo/{$listing.domain}#">{$listing.alexa_rank}</a></li>
			{/if}
		</ul>
		<div class="ia-item-body">{$listing.short_description|strip_tags|truncate:200:'...'}</div>
	</div>

	<div class="ia-item-panel">
		{ia_url type='icon' item='listings' data=$listing classname='btn-info pull-left'}
		{accountActions item=$listing itemtype='listings' classname='btn-info pull-left'}
		{printFavorites item=$listing itemtype='listings' classname='pull-left'}

		<span class="panel-item pull-left">
			<i class="icon-user"></i> 
			{if $listing.member}
				{if $core.config.members_enabled}
					<a href="{$smarty.const.IA_URL}member/{$listing.account_username}.html">{$listing.member}</a>
				{else}
					{$listing.member}
				{/if}
			{else}
				{lang key='guest'}
			{/if}
		</span>

		<span class="panel-item pull-right"><i class="icon-time"></i> {$listing.date_added|date_format:$core.config.date_format}</span>

		{if !isset($category) || $listing.category_id != $category.id}
			<span class="panel-item pull-right"><i class="icon-folder-open"></i> <a href="{ia_url type='url' item='categs' data=$listing}">{$listing.category_title}</a></span>
		{/if}

		<span class="panel-item pull-right"><i class="icon-eye-open"></i> {$listing.views_num} {lang key='views'}</span>
	</div>
</div>