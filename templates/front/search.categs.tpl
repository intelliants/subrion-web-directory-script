{if !empty($listings)}
    <div class="ia-items">
        {foreach $listings as $listing}
            <p><a href="{$listing.link}">{$listing.title|escape}</a> ({$listing.num_all_listings})</p>
        {/foreach}
    </div>
{/if}