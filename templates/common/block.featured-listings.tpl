{if !empty($featured_listings)}
	<div class="ia-items directory-featured-listings">
		{foreach $featured_listings as $listing name=featured_listings}
			<div class="media ia-item ia-item-bordered-bottom">
				{if $core.config.directory_enable_thumbshots}
					<img src="http://free.pagepeeker.com/v2/thumbs.php?size=m&url={$listing.url|escape:url}" class="media-object thumbnail" width="120">
				{/if}

				<div class="media-body">
					<h5 class="media-heading">
						{if !$core.config.directory_redirect_to_site}
							{ia_url type='link' item='listings' data=$listing text=$listing.title}
						{else}
							<a href="{$listing.url}" target="_blank">{$listing.title}</a>
						{/if}
					</h5>
					<p class="ia-item-date">
						{$listing.url}
						{if 0 != $listing.rank}
							<br>{section name=star loop=$listing.rank}<i class="icon-star icon-orange"></i> {/section}
						{/if}
					</p>

					<p class="ia-item-body">{$listing.short_description|strip_tags|truncate:80}</p>
				</div>
			</div>
		{/foreach}
	</div>
{/if}