<?php
//##copyright##

if (iaView::REQUEST_HTML == $iaView->getRequestType())
{
	$iaListing = $iaCore->factoryPackage('listing', 'directory');

	$limit = $iaCore->get('directory_listings_perblock', 5);

	if ($iaView->blockExists('directory_listings_tabs'))
	{
		if ($iaCore->get('directory_listings_tabs_new'))
		{
			$iaView->assign('latest_listings', $iaListing->getLatest($iaCore->get('directory_new_listings_perblock', 5)));
		}

		if ($iaCore->get('directory_listings_tabs_popular'))
		{
			$iaView->assign('popular_listings', $iaListing->getPopular($iaCore->get('directory_popular_listings_perblock', 5)));
		}

		if ($iaCore->get('directory_listings_tabs_random'))
		{
			$iaView->assign('random_listings', $iaListing->getRandom($iaCore->get('directory_random_listings_perblock', 5)));
		}
	}

	if ($iaView->blockExists('recent_listings'))
	{
		$iaView->assign('latest_listings', $iaListing->getLatest($limit));
	}

	if ($iaView->blockExists('featured_listings'))
	{
		$iaView->assign('featured_listings', $iaListing->get("t1.`featured` != 0", 0, $limit, "t1.`featured_start` DESC"));
	}

	if ($iaView->blockExists('sponsored_listings'))
	{
		$iaView->assign('sponsored_listings', $iaListing->get("t1.`sponsored` != 0", 0, $limit, "t1.`sponsored_start` DESC"));
	}

	if ($iaView->blockExists('directory_categories_tree'))
	{
		$iaCateg = $iaCore->factoryPackage('categ', $iaListing->getPackageName());
		$iaView->assign('directory_categories_tree', $iaCateg->get('`level` = 1'));
	}

	if ($iaView->blockExists('directory_categories') || 'directory_home' == $iaView->name())
	{
		$iaCateg = $iaCore->factoryPackage('categ', $iaListing->getPackageName());

		$hideIfEmpty = $iaCore->get('directory_hide_empty_categories');

		$category = ('directory_home' == $iaView->name())
			? $iaView->getValues('category')
			: $iaDb->row('id', '`level` = 0', iaCateg::getTable());

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
		$iaCateg = $iaCore->factoryPackage('categ', $iaListing->getPackageName());

		$categories = $iaDb->all(array('id', 'title'), "`status` = 'active' AND `level` = 1 ORDER BY `title`", null, null, $iaCateg::getTable());

		if (!empty($categories))
		{
			$iaView->assign('directoryFiltersCategories', $categories);
		}
	}
}