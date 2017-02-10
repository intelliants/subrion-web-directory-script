{if $listingsBlocksData.featured}
	<div class="ia-items directory-featured-listings">
		{foreach $listingsBlocksData.featured as $listing}
			<div class="ia-item ia-item--border-bottom">
				{if $core.config.directory_enable_thumbshots}
					<img src="http://free.pagepeeker.com/v2/thumbs.php?size=m&url={$listing.url|escape:url}" class="m-b img-responsive">
				{/if}

				<div class="ia-item__content">
					<div class="ia-item__title">
						{if !$core.config.directory_redirect_to_site}
							{ia_url type='link' item='listings' data=$listing text=$listing.title}
						{else}
							<a href="{$listing.url}" target="_blank">{$listing.title}</a>
						{/if}
					</div>
					<p class="text-overflow">
						{$listing.url}
						{if 0 != $listing.rank}
							<br>{section name=star loop=$listing.rank}<span class="fa fa-star text-info"></span> {/section}
						{/if}
					</p>

					<p>{$listing.description|strip_tags|truncate:80}</p>
				</div>
			</div>
		{/foreach}
	</div>
{/if}