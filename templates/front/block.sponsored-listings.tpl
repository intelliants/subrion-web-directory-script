{if !empty($listingsBlocksData.sponsored)}
    <div class="ia-items directory-sponsored-listings">
        {foreach $listingsBlocksData.sponsored as $listing}
            <div class="ia-item ia-item--border-bottom">
                {if $core.config.directory_enable_thumbshots}
                    <img src="http://free.pagepeeker.com/v2/thumbs.php?size=m&url={$listing.url|escape:url}" class="m-b img-responsive">
                {/if}

                <div class="ia-item__content">
                    <div class="ia-item__title">
                        {if !$core.config.directory_redirect_to_site}
                            <a href="{$listing.link}" target="_blank">{$listing.title|escape}</a>
                        {else}
                            <a href="{$listing.url}" target="_blank">{$listing.title|escape}</a>
                        {/if}
                    </div>
                    <p class="text-overflow">
                        {$listing.url}
                        {if 0 != $listing.rank}
                            <br>
                            {section name=star loop=$listing.rank}<span class="fa fa-star text-danger"></span>{/section}
                        {/if}
                    </p>

                    <p>{$listing.description|strip_tags|truncate:80}</p>
                </div>
            </div>
        {/foreach}
    </div>
{/if}