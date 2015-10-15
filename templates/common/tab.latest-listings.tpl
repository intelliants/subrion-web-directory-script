<div class="ia-items directory-latest-listings">
	{foreach $latest_listings as $listing name=latest_listings}
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
					<i class="icon-calendar"></i> 
					{$listing.date_added|date_format:$core.config.date_format}
					{if 0 != $listing.rank}
						<br>{section name=star loop=$listing.rank}<i class="icon-star icon-orange"></i> {/section}
					{/if}
				</p>
			</div>
		</div>
	{/foreach}
</div>