<div class="ia-items directory-random-listings">
	{foreach $random_listings as $listing name=random_listings}
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
					<span class="fa fa-calendar"></span> 
					{$listing.date_added|date_format:$core.config.date_format}
					{if 0 != $listing.rank}
						{section name=star loop=$listing.rank}<span class="fa fa-star text-warning"></span> {/section}
					{/if}
				</p>
			</div>
		</div>
	{/foreach}
</div>