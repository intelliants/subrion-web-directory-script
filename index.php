<?php
/******************************************************************************
 *
 * Subrion Web Directory Script
 * Copyright (C) 2017 Intelliants, LLC <https://intelliants.com>
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

$iaCateg = $iaCore->factoryModule('categ', IA_CURRENT_MODULE);

if (iaView::REQUEST_JSON == $iaView->getRequestType()) {
    $output = [];

    if (isset($_GET['id']) && ($children = $iaCateg->getChildren($_GET['id']))) {
        foreach ($children as $child) {
            $output[] = ['id' => $child['id'], 'text' => $child['title']];
        }
    }

    $iaView->assign($output);
}

if (iaView::REQUEST_HTML == $iaView->getRequestType()) {
    $pageActions = [];
    $pageName = $iaView->name();

    if (isset($iaCore->requestPath[0])) {
        if ($value = $iaDb->one_bind('name', '`alias` = :alias', ['alias' => iaSanitize::sql($iaCore->requestPath[0]) . "/'"], 'pages')) {
            array_shift($iaCore->requestPath);
            $pageName = $value;
        }
    }

    $pagination = [
        'total' => 0,
        'limit' => $iaCore->get('directory_listings_perpage', 10),
        'start' => 0,
        'url' => IA_SELF . '?page={page}'
    ];

    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
    $pagination['start'] = ($page - 1) * $pagination['limit'];

    $order = '';

    $iaListing = $iaCore->factoryModule('listing', IA_CURRENT_MODULE);

    $listings = [];
    $orders = ['date_added-asc', 'date_added-desc', 'rank-desc', 'rank-asc', 'title-desc', 'title-asc'];

    if (!isset($_SESSION['d_order'])) {
        $_SESSION['d_order'] = $orders[0];
    }
    list($d_sort, $d_type) = explode('-', $_SESSION['d_order']);

    if (isset($_GET['sort_by'])) {
        $d_sort = $_GET['sort_by'];
        $_POST['sort_by'] = $d_sort . '-' . $d_type;
    }
    if (isset($_GET['order_type'])) {
        $d_type = $_GET['order_type'];
        $_POST['sort_by'] = $d_sort . '-' . $d_type;
    }

    // sort by, and save in session
    if (isset($_POST['sort_by']) && in_array($_POST['sort_by'], $orders)) {
        $_SESSION['d_order'] = $_POST['sort_by'];
    }

    if (!in_array($_SESSION['d_order'], $orders)) {
        $_SESSION['d_order'] = $orders[0];
    }

    if (isset($_SESSION['d_order'])) {
        list($sort, $type) = explode('-', $_SESSION['d_order']);
        $iaView->assign('sort_name', $sort);
        $iaView->assign('sort_type', $type);
        ('title' == $sort) && $sort .= '_' . $iaView->language;
        $order = ' `' . $sort . '` ' . $type;
    }

    $rssFeed = false;

    switch ($pageName) {
        case 'my_listings':
            if (!iaUsers::hasIdentity()) {
                return iaView::errorPage(iaView::ERROR_UNAUTHORIZED);
            }

            $listings = $iaListing->get('l.`member_id` = ' . iaUsers::getIdentity()->id . ' ', $pagination['start'], $pagination['limit'], $order);
            iaLanguage::set('no_web_listings', iaLanguage::get('no_my_listings'));

            break;

        case 'top_listings':
            $rssFeed = 'top';

            $listings = $iaListing->getTop($pagination['limit'], $pagination['start']);
            iaLanguage::set('no_web_listings', iaLanguage::get('no_web_listings2'));

            break;

        case 'new_listings':
            $rssFeed = 'latest';

            $listings = $iaListing->getLatest($pagination['limit'], $pagination['start']);
            iaLanguage::set('no_web_listings', iaLanguage::get('no_web_listings2'));

            break;

        case 'popular_listings':
            $rssFeed = 'popular';

            $listings = $iaListing->getPopular($pagination['limit'], $pagination['start']);
            iaLanguage::set('no_web_listings', iaLanguage::get('no_web_listings2'));

            break;

        case 'random_listings':
            $listings = $iaListing->getRandom($pagination['limit'], $pagination['start']);
            iaLanguage::set('no_web_listings', iaLanguage::get('no_web_listings2'));

            break;

        default:
            $categoryAlias = empty($iaCore->requestPath) ? false : implode(IA_URL_DELIMITER, $iaCore->requestPath) . IA_URL_DELIMITER;

            $rssFeed = empty($iaCore->requestPath) ? false : implode(IA_URL_DELIMITER, $iaCore->requestPath);

            $category = $iaCateg->getOne(iaDb::convertIds($categoryAlias, 'title_alias'));

            // requested category not found
            if ($categoryAlias && $category['id'] == $iaCateg->getRootId()) {
                return iaView::errorPage(iaView::ERROR_NOT_FOUND);
            }
            $iaView->set('subpage', $category['id']);

            // start breadcrumb
            if (IA_CURRENT_MODULE == $iaCore->get('default_package')) {
                iaBreadcrumb::remove(iaBreadcrumb::POSITION_LAST);
            }

            $filters = []; // pre-fill filters

            foreach ($iaCateg->getParents($category['id']) as $key => $parent) {
                (0 === $key || 1 === $key) && $filters[0 == $key ? 'c' : 'sc'] = $parent['id'];
                iaBreadcrumb::toEnd($parent['title'], $iaCateg->url('default', $parent));
            }

            $iaView->set('filtersParams', $filters);
            // end

            $iaCateg->incrementViewsCounter($category['id']);

            $listings = $iaListing->getByCategoryId($category['id'], $pagination['start'], $pagination['limit'], $order);

            $iaView->assign('category', $category);

            if ($iaCateg->getRootId() != $category['id']) {
                $iaView->set('description', $category['meta_description']);
                $iaView->set('keywords', $category['meta_keywords']);

                $iaView->title($category['title']);
            }
    }

    $pagination['total'] = $iaListing->getFoundRows();

    iaLanguage::set('no_web_listings', iaLanguage::getf('no_web_listings', ['url' => IA_MODULE_URL . 'add/' . (empty($category) ? '' : '?category=' . $category['id'])]));

    if ($iaAcl->isAccessible('add_listing', iaCore::ACTION_ADD)) {
        $pageActions[] = [
            'icon' => 'plus-square',
            'title' => iaLanguage::get('add_listing'),
            'url' => IA_MODULE_URL . 'add/' . (empty($category['id']) ? '' : '?category=' . $category['id'])
        ];
    }

    $rssFeed = $rssFeed ? $rssFeed . '.' . iaCore::EXTENSION_XML : 'latest.' . iaCore::EXTENSION_XML;

    if ('my_listings' != $pageName && 'random_listings' != $pageName) {
        $pageActions[] = [
            'icon' => 'rss ',
            'title' => null,
            'url' => IA_MODULE_URL . 'rss/' . $rssFeed,
            'classes' => 'btn-warning'
        ];
    }

    $iaView->set('actions', $pageActions);
    $iaView->set('filtersItemName', $iaListing->getItemName());

    $iaView->assign('rss_feed', $rssFeed);
    $iaView->assign('listings', $listings);
    $iaView->assign('pagination', $pagination);

    $iaView->display('index');
}

if (iaView::REQUEST_XML == $iaView->getRequestType()) {
    $iaListing = $iaCore->factoryModule('listing', IA_CURRENT_MODULE);

    $limit = (int)$iaCore->get('directory_listings_perpage', 10);

    if (isset($iaCore->requestPath[0]) && 'top' == $iaCore->requestPath[0]) {
        $listings = $iaListing->getTop($limit);
    } elseif (isset($iaCore->requestPath[0]) && 'latest' == $iaCore->requestPath[0]) {
        $listings = $iaListing->getLatest($limit);
    } else {
        $slug = implode(IA_URL_DELIMITER, $iaCore->requestPath) . IA_URL_DELIMITER;
        $category = $iaCateg->getBySlug($slug);

        $listings = $iaListing->getByCategoryId($category['id'], 0, $limit, 'li.`date_added` DESC');
    }

    $output = [
        'title' => $iaCore->get('site'),
        'description' => '',
        'link' => IA_URL,
        'item' => []
    ];

    foreach ($listings as $listing) {
        $output['item'][] = [
            'title' => $listing['title'],
            'guid' => $iaListing->url('view', $listing),
            'link' => $iaListing->url('view', $listing),
            'pubDate' => date('D, d M Y H:i:s T', strtotime($listing['date_added'])),
            'description' => iaSanitize::tags($listing['summary']),
            'category' => isset($category) ? $category : $listing['category_title']
        ];
    }

    $iaView->assign('channel', $output);
}
