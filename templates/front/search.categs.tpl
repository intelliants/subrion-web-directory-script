{if !empty($listings)}
    <div class="ia-items">
        {foreach $listings as $listing}
            <p>{ia_url item='categs' type='link' data=$listing text=$listing.title} ({$listing.num_all_listings})</p>
        {/foreach}
    </div>
{/if}