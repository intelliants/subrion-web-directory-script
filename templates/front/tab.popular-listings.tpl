<div class="ia-items directory-popular-listings">
	{foreach $listingsBlocksData.tabs_popular as $listing}
		<div class="ia-item ia-item--border-bottom">
			<div class="ia-item__content">
				<div class="ia-item__title">
					{if !$core.config.directory_redirect_to_site}
						{ia_url type='link' item='listings' data=$listing text=$listing.title}
					{else}
						<a href="{$listing.url}" target="_blank">{$listing.title}</a>
					{/if}
				</div>
				<p>{$listing.url}</p>
				<p>
					<span class="fa fa-eye"></span> {$listing.views_num} {lang key='views'}
					{section name=star loop=$listing.rank}<span class="fa fa-star text-warning"></span> {/section}
				</p>
			</div>
		</div>
	{/foreach}
</div>