{if isset($category) && $category.description}
	<div class="page-content">{$category.description}</div>
{/if}

{if 'directory_home' == $core.page.name}
	{ia_hooker name='smartyFrontDirectoryCategories'}
{/if}

{if $listings}
	{if !in_array($core.page.name, array('top_listings', 'new_listings', 'random_listings'))}
		{if !isset($listings_sorting) || $listings_sorting}
			<div class="btn-toolbar items-sorting text-center">
				<p class="btn-group">
					<span class="btn btn-small disabled">{lang key='sort_by'}:</span>
					{if 'date_added' == $sort_name}<span class="btn btn-small disabled">{lang key='date_added'}</span>{else}<a class="btn btn-small" href="{$smarty.const.IA_SELF}?sort_by=date_added" rel="nofollow">{lang key='date_added'}</a>{/if}
					{if 'title' == $sort_name}<span class="btn btn-small disabled">{lang key='title'}</span>{else}<a class="btn btn-small" href="{$smarty.const.IA_SELF}?sort_by=title" rel="nofollow">{lang key='title'}</a>{/if}
					{if 'rank' == $sort_name}<span class="btn btn-small disabled">{lang key='rank'}</span>{else}<a class="btn btn-small" href="{$smarty.const.IA_SELF}?sort_by=rank" rel="nofollow">{lang key='rank'}</a>{/if}
				</p>
				<p class="btn-group">
					{if 'asc' == $sort_type}<span class="btn btn-small disabled">{lang key='ascending'}</span>{else}<a class="btn btn-small" href="{$smarty.const.IA_SELF}?order_type=asc" rel="nofollow">{lang key='ascending'}</a>{/if}
					{if 'desc' == $sort_type}<span class="btn btn-small disabled">{lang key='descending'}</span>{else}<a class="btn btn-small" href="{$smarty.const.IA_SELF}?order_type=desc" rel="nofollow">{lang key='descending'}</a>{/if}
				</p>
			</div>
		{/if}
	{/if}

	<div class="ia-items">
		{foreach $listings as $listing}
			{include file='extra:directory/list-listings'}
		{/foreach}

		{navigation aTotal=$aTotal aTemplate=$aTemplate aItemsPerPage=$aItemsPerPage aNumPageItems=5 aTruncateParam=1}
	</div>
{elseif isset($category) && $category.parent_id > 0}
	<div class="alert alert-info">{lang key='no_web_listings'}</div>
{elseif !isset($category)}
	<div class="alert alert-info">{lang key='no_web_listings'}</div>
{/if}