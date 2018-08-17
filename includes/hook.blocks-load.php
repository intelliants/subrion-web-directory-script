<?php
/******************************************************************************
 *
 * Subrion Web Directory Script
 * Copyright (C) 2018 Intelliants, LLC <https://intelliants.com>
 *
 * This file is part of Subrion Web Directory Script.
 *
 * This program is a commercial software and any kind of using it must agree
 * to the license, see <https://subrion.pro/license.html>.
 *
 * This copyright notice may not be removed from the software source without
 * the permission of Subrion respective owners.
 *
 *
 * @link https://subrion.pro/product/directory.html
 *
 ******************************************************************************/

if (iaView::REQUEST_HTML == $iaView->getRequestType()) {
    $iaListing = $iaCore->factoryItem('listing');
    $iaCateg = $iaCore->factoryItem('categ');

    // set default values for blocks to avoid isset validation
    $blocksData = ['recent' => [], 'featured' => [], 'sponsored' => []];

    if ($iaView->blockExists('directory_listings_tabs')) {
        if ($iaCore->get('directory_listings_tabs_new')) {
            $blocksData['tabs_new'] = $iaListing->getLatest($iaCore->get('directory_listings_tabs_new_limit', 6));
        }

        if ($iaCore->get('directory_listings_tabs_popular')) {
            $blocksData['tabs_popular'] = $iaListing->getPopular($iaCore->get('directory_listings_tabs_popular_limit', 6));
        }

        if ($iaCore->get('directory_listings_tabs_random')) {
            $blocksData['tabs_random'] = $iaListing->getRandom($iaCore->get('directory_listings_tabs_random_limit', 6));
        }
    }

    if ($iaView->blockExists('recent_listings')) {
        $blocksData['recent'] = $iaListing->getLatest($iaCore->get('directory_listings_recent_limit', 6));
    }

    if ($iaView->blockExists('featured_listings')) {
        $blocksData['featured'] = $iaListing->get('l.`featured` != 0', 0, $iaCore->get('directory_listings_featured_limit', 6), iaDb::FUNCTION_RAND);
    }

    if ($iaView->blockExists('sponsored_listings')) {
        $blocksData['sponsored'] = $iaListing->get('l.`sponsored` != 0', 0, $iaCore->get('directory_listings_sponsored_limit', 6), iaDb::FUNCTION_RAND);
    }

    if ($iaView->blockExists('directory_categories_tree')) {
        $iaView->assign('directory_categories_tree', $iaCateg->get('`level` = 1'));
    }

    if ($iaView->blockExists('directory_categories') || 'directory_home' == $iaView->name()) {
        $hideIfEmpty = $iaCore->get('directory_hide_empty_categories');

        $category = ('directory_home' == $iaView->name()) ? $iaView->getValues('category') : $iaDb->row('id', '`level` = 0', iaCateg::getTable());

        $condition = "c.`" . iaCateg::COL_PARENT_ID . "` = {$category['id']} AND c.`status` = 'active'";
        $hideIfEmpty && ($condition .= ' AND c.`num_all_listings` != 0');

        $children = $iaCateg->get($condition, $category['id']);

        if ($iaCore->get('directory_display_subcategories')) {
            foreach ($children as $key => $cat) {
                $condition = "c.`" . iaCateg::COL_PARENT_ID . "` = {$cat['id']} AND c.`status` = 'active'";
                $hideIfEmpty && ($condition .= ' AND c.`num_all_listings` != 0');

                $children[$key]['subcategories'] = $iaCateg->get($condition, $cat['id'], 0, $iaCore->get('directory_subcategories_number'));
            }
        }

        $iaView->assign('directory_categories', $children);
        unset($children);
    }

    if ($iaView->blockExists('filters') && $iaListing->getItemName() == $iaView->get('filtersItemName')) {
        $fields = ['id', 'title' => 'title_' . $iaView->language, 'slug'];
        $categories =  $iaCateg->getTopLevel($fields, ['title', 'asc']);

        $iaView->assign('directoryFiltersCategories', $categories ? $categories : []);
    }

    $iaView->assign('listingsBlocksData', $blocksData);
}
