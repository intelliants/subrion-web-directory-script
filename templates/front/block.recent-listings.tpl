{if $listingsBlocksData.recent}
    <h2 class="page-header">{$block.title|escape}</h2>
    <div class="ia-items directory-recent-listings">
        {foreach $listingsBlocksData.recent as $listing}
            {include 'module:directory/list-listings.tpl'}
        {/foreach}
    </div>
{/if}