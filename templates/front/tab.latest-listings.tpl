<div class="ia-items directory-latest-listings">
    {foreach $listingsBlocksData.tabs_new as $listing}
        <div class="ia-item ia-item--border-bottom">
            <div class="ia-item__content">
                <div class="ia-item__title">
                    {if $core.config.directory_redirect_to_site}
                        <a href="{$listing.url}" target="_blank">{$listing.title|escape}</a>
                    {else}
                        <a href="{$listing.link}" target="_blank">{$listing.title|escape}</a>
                    {/if}
                </div>
                <p>{$listing.url}</p>
                <p>
                    <span class="fa fa-calendar"></span>
                    {$listing.date_added|date_format}
                    {if 0 != $listing.rank}
                        {section name=star loop=$listing.rank}<span class="fa fa-star text-warning"></span>{/section}
                    {/if}
                </p>
            </div>
        </div>
    {/foreach}
</div>