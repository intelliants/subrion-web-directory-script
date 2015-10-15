{if !empty($latest_listings)}
	<h2 class="page-header">{lang key='recently_added_listings'}</h2>
	<div class="ia-items directory-recent-listings">
		{foreach $latest_listings as $listing}
			{include file='extra:directory/list-listings'}
		{/foreach}
	</div>
{/if}