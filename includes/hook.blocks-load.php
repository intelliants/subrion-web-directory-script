<?php
//##copyright##

if (iaView::REQUEST_HTML != $iaView->getRequestType())
{
	return;
}

$iaListing = $iaCore->factoryPackage('listing', 'directory');

$sql  = "SELECT l.`id`, l.`title`, l.`title_alias`, l.`url`, l.`date_added`, l.`short_description`, l.`rank`, ";
$sql .= "cat.`title_alias` `category_alias`, cat.`no_follow`, cat.`num_listings` ";
$sql .= "FROM `{$iaDb->prefix}listings` AS l ";
$sql .= "JOIN `{$iaDb->prefix}categs` AS cat ON l.`category_id` = cat.`id` ";
$sql .= "LEFT JOIN `{$iaDb->prefix}members` AS acc ON l.`member_id` = acc.`id` ";
$sql .= "WHERE l.`status`='active' AND cat.`status`='active' ";
$sql .= "AND (acc.`status`='active' OR acc.`status` IS NULL) ";

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

	$hide_empty = $iaCore->get('directory_hide_empty_categories');
	$category = false;

	if ('directory_home' == $iaView->name())
	{
		$category = $iaView->getValues('category');
	}

	$category = $category ? $category : $iaDb->row('id', '`level` = 0', iaCateg::getTable());

	$condition = "`parent_id` = {$category['id']} AND `status` = 'active'";

	if ($hide_empty)
	{
		$condition .= ' AND `num_all_listings` != 0';
	}

	$children = $iaCateg->get($condition);

	if ($iaCore->get('directory_display_subcategories'))
	{
		foreach ($children as $key => $cat)
		{
			$condition = "`parent_id`={$cat['id']} AND `status`='active'";
			if ($hide_empty)
			{
				$condition .= ' AND `num_all_listings` != 0';
			}

			$children[$key]['subcategories'] = $iaCateg->get($condition, 0, $iaCore->get('directory_subcategories_number'), '`title`, `title_alias`');
		}
	}

	$iaView->assign('directory_categories', $children);
	unset($children);
}

if ($iaView->blockExists('filters') && $iaListing->getItemName() == $iaView->get('filtersItemName'))
{
	$iaCateg = $iaCore->factoryPackage('categ', $iaListing->getPackageName());

	empty($categories = $iaDb->all(array('id', 'title'), "`status` = 'active' AND `level` = 1 ORDER BY `title`",
		null, null, $iaCateg::getTable())) || $iaView->assign('directoryFiltersCategories', $categories);
}