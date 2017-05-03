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

$iaListing = $iaCore->factoryModule('listing', IA_CURRENT_MODULE);

if (iaView::REQUEST_JSON == $iaView->getRequestType()) {
    if ('report' == $_POST['action']) {
        $id = (int)$_POST['id'];
        $comment = '';
        if (!empty($_POST['comments'])) {
            $time = date('Y-m-d H:i:s');
            $iaCore->factory('util');
            $ip = iaUtil::getIp(false);
            $comment = <<<COMMENT
Date: {$time}
IP: {$ip}
Comment: {$_POST['comments']}


COMMENT;
        }

        $listing = $iaListing->getById($id);

        $iaMailer = $iaCore->factory('mailer');

        $iaMailer->loadTemplate('reported_as_broken');
        $iaMailer->setReplacements([
            'title' => $listing['title'],
            'comments' => $comment
        ]);
        $iaMailer->sendToAdministrators();

        $email = empty($listing['email']) ? $iaDb->one('email', iaDb::convertIds($listing['member_id']), iaUsers::getTable()) : $listing['email'];

        if ($email) {
            $iaMailer->loadTemplate('reported_as_broken');
            $iaMailer->setReplacements([
                'title' => $listing['title'],
                'comments' => $comment
            ]);
            $iaMailer->addAddress($email);

            $iaMailer->send();
        }

        $fields = ['reported_as_broken' => 1];

        if ($comment) {
            if (isset($listing['reported_as_broken_comments']) && $listing['reported_as_broken_comments']) {
                $comment = $listing['reported_as_broken_comments'] . $comment;
            }
            $fields['reported_as_broken_comments'] = $comment;
        }

        $iaDb->update($fields, iaDb::convertIds($id), null, iaListing::getTable());
    }
}

if (iaView::REQUEST_HTML == $iaView->getRequestType()) {
    $listingId = (int)end($iaCore->requestPath);
    if (!$listingId) {
        return iaView::errorPage(iaView::ERROR_NOT_FOUND);
    }

    $listing = $iaListing->getById($listingId);

    if (empty($listing) || iaCore::STATUS_APPROVAL == $listing['status'] &&
        !(iaUsers::hasIdentity() && iaUsers::getIdentity()->id == $listing['member_id'])) {
        return iaView::errorPage(iaView::ERROR_NOT_FOUND);
    }

    // URL validation
//	$categoryPath = $iaView->url;
//
//	unset($categoryPath[count($categoryPath) - 1]);
//	$baseUrl = IA_CURRENT_MODULE == $iaCore->get('default_package') ? IA_URL : $iaListing->getInfo('url') . 'listing/';
//
//	$categoryPath = $categoryPath ? implode(IA_URL_DELIMITER, $categoryPath) . IA_URL_DELIMITER : '';
//	$categoryPath = str_replace(str_replace(IA_URL, '', $baseUrl), '', $categoryPath);
//
//	if ($categoryPath != $listing['category_alias'])
//	{
//		$validUrl = $iaListing->url('view', $listing);
//		iaUtil::go_to($validUrl);
//	}

    $iaCateg = $iaCore->factoryModule('categ', IA_CURRENT_MODULE);

    $category = $iaCateg->getById($listing['category_id']);
    $iaView->assign('category', $category);

    $listing['item'] = $iaListing->getItemName();

    $iaView->set('subpage', $category['id']);

    foreach ($iaCateg->getParents($category['id']) as $p) {
        iaBreadcrumb::add($p['title'], $iaCateg->url('view', $p));
    }

    $iaItem = $iaCore->factory('item');

    if ($listing['url']) {
        $iaItem->setItemTools([
            'id' => 'action-visit',
            'title' => iaLanguage::get('visit_site'),
            'attributes' => [
                'href' => $listing['url'],
                'target' => '_blank',
            ]
        ]);
    }

    $iaItem->setItemTools([
        'id' => 'action-report',
        'title' => iaLanguage::get('report_listing'),
        'attributes' => [
            'href' => '#',
            'id' => 'js-cmd-report-listing',
            'data-id' => $listing['id']
        ]
    ]);

    if (iaUsers::hasIdentity() && iaUsers::getIdentity()->id == $listing['member_id']) {
        $actionUrls = [
            iaCore::ACTION_EDIT => $iaListing->url(iaCore::ACTION_EDIT, $listing),
            iaCore::ACTION_DELETE => $iaListing->url(iaCore::ACTION_DELETE, $listing)
        ];
        $iaView->assign('tools', $actionUrls);

        $iaItem->setItemTools([
            'id' => 'action-edit',
            'title' => iaLanguage::get('edit'),
            'attributes' => [
                'href' => $actionUrls[iaCore::ACTION_EDIT],
            ]
        ]);
        $iaItem->setItemTools([
            'id' => 'action-delete',
            'title' => iaLanguage::get('remove'),
            'attributes' => [
                'href' => $actionUrls[iaCore::ACTION_DELETE],
                'class' => 'js-delete-listing'
            ]
        ]);
    }

    // update favorites status
    $listing = $iaItem->updateItemsFavorites([$listing], $iaListing->getItemName());
    $listing = array_shift($listing);

    iaBreadcrumb::replaceEnd($listing['title'], IA_SELF);

    $iaListing->incrementViewsCounter($listingId);

    $iaCore->startHook('phpViewListingBeforeStart', [
        'listing' => $listingId,
        'item' => 'listings',
        'title' => $listing['title'],
        'desc' => substr(strip_tags($listing['description']), 0, 200),
    ]);

    $author = $iaCore->factory('users')->getInfo($listing['member_id']);
    $counter = $iaDb->one(iaDb::STMT_COUNT_ROWS, iaDb::convertIds($listing['member_id'], 'member_id'), iaListing::getTable());
    $sections = $iaCore->factory('field')->getTabs($iaListing->getItemName(), $listing);

    $iaView->assign('author', $author);
    $iaView->assign('listings_num', $counter);
    $iaView->assign('item', $listing);
    $iaView->assign('sections', $sections);

    $iaView->set('keywords', $listing['meta_keywords']);
    $iaView->set('description', $listing['meta_description']);

    $iaView->title($listing['title']);

    $iaView->display('view');
}
