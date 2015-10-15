<div class="ia-items directory-popular-listings">
	{foreach $popular_listings as $listing name=popular_listings}
		<div class="media ia-item ia-item-bordered-bottom">
			<div class="media-body">
				<h5 class="media-heading">
					{if !$core.config.directory_redirect_to_site}
						{ia_url type='link' item='listings' data=$listing text=$listing.title}
					{else}
						<a href="{$listing.url}" target="_blank">{$listing.title}</a>
					{/if}
				</h5>
				<p>{$listing.url}</p>
				<p class="ia-item-date">
					<i class="icon-eye-open"></i> {$listing.views_num} {lang key='views'}
					<br>
					{section name=star loop=$listing.rank}<i class="icon-star icon-orange"></i> {/section}
				</p>
			</div>
		</div>
	{/foreach}
</div>