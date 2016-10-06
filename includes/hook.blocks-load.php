<?php
//##copyright##

if (iaView::REQUEST_HTML == $iaView->getRequestType())
{
	$iaListing = $iaCore->factoryPackage('listing', 'directory');
	$iaCateg = $iaCore->factoryPackage('categ', $iaListing->getPackageName());

	// set default values for blocks to avoid isset validation
	$blocksData = array('recent' => [], 'featured' => [], 'sponsored' => []);

	if ($iaView->blockExists('directory_listings_tabs'))
	{
		if ($iaCore->get('directory_listings_tabs_new'))
		{
			$blocksData['tabs_new'] = $iaListing->getLatest($iaCore->get('directory_listings_tabs_new_limit', 6));
		}

		if ($iaCore->get('directory_listings_tabs_popular'))
		{
			$blocksData['tabs_popular'] = $iaListing->getPopular($iaCore->get('directory_listings_tabs_popular_limit', 6));
		}

		if ($iaCore->get('directory_listings_tabs_random'))
		{
			$blocksData['tabs_popular'] = $iaListing->getRandom($iaCore->get('directory_listings_tabs_random_limit', 6));
		}
	}

	if ($iaView->blockExists('recent_listings'))
	{
		$blocksData['recent'] = $iaListing->getLatest($iaCore->get('directory_listings_recent_limit', 6));
	}

	if ($iaView->blockExists('featured_listings'))
	{
		$blocksData['featured'] = $iaListing->get("t1.`featured` != 0", 0, $iaCore->get('directory_listings_featured_limit', 6), 't1.`featured_start` DESC');
	}

	if ($iaView->blockExists('sponsored_listings'))
	{
		$blocksData['sponsored'] = $iaListing->get("t1.`sponsored` != 0", 0, $iaCore->get('directory_listings_sponsored_limit', 6), 't1.`sponsored_start` DESC');
	}

	if ($iaView->blockExists('directory_categories_tree'))
	{
		$iaView->assign('directory_categories_tree', $iaCateg->get('`level` = 1'));
	}

	if ($iaView->blockExists('directory_categories') || 'directory_home' == $iaView->name())
	{
		$hideIfEmpty = $iaCore->get('directory_hide_empty_categories');

		$category = ('directory_home' == $iaView->name()) ? $iaView->getValues('category') : $iaDb->row('id', '`level` = 0', iaCateg::getTable());

		$condition = "c.`parent_id` = {$category['id']} AND c.`status` = 'active'";
		$hideIfEmpty && ($condition .= ' AND c.`num_all_listings` != 0');

		$children = $iaCateg->get($condition, $category['id']);

		if ($iaCore->get('directory_display_subcategories'))
		{
			foreach ($children as $key => $cat)
			{
				$condition = "c.`parent_id` = {$cat['id']} AND c.`status` = 'active'";
				$hideIfEmpty && ($condition .= ' AND c.`num_all_listings` != 0');

				$children[$key]['subcategories'] = $iaCateg->get($condition, $cat['id'], 0, $iaCore->get('directory_subcategories_number'), 'c.`title`, c.`title_alias`');
			}
		}

		$iaView->assign('directory_categories', $children);
		unset($children);
	}

	if ($iaView->blockExists('filters') && $iaListing->getItemName() == $iaView->get('filtersItemName'))
	{
		$categories = $iaDb->all(array('id', 'title'), "`status` = 'active' AND `level` = 1 ORDER BY `title`", null, null, $iaCateg::getTable());

		if (!empty($categories))
		{
			$iaView->assign('directoryFiltersCategories', $categories);
		}
	}

	$iaView->assign('listingsBlocksData', $blocksData);
}