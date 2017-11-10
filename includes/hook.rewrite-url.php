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

$package = 'directory';
$accessGranted = false;
$isDefaultPackage = ($package == $iaCore->get('default_package'));
$module = $iaCore->getModules($package);
$package_home = 'directory_home';

if ($iaCore->checkDomain() && $isDefaultPackage) {
    $accessGranted = true;
} elseif (!$iaCore->checkDomain()) {
    if (isset($module['url']) && $module['url'] == $iaView->domainUrl) {
        $accessGranted = true;
    }
} elseif (count($iaCore->requestPath) > 0) {
    $accessGranted = true;
}

$url = end($iaView->url);

$iaPage = $iaCore->factory('page', iaCore::FRONT);

if ($listingId = (int)$url) {
    if ($listingData = $iaDb->row_bind(['slug'], '`id` = :id', ['id' => $listingId], 'listings')) {
        if ($listingData['slug']) {
            $slug = substr($url, strpos($url, '-') + 1);
            if ($slug == $listingData['slug']) {
                $pageName = $iaPage->getUrlByName('view_listing', false);
                $pageName = explode(IA_URL_DELIMITER, $pageName);
                $pageName = array_shift($pageName);

                array_unshift($iaCore->requestPath, 'listing');
                $iaView->name($pageName);
            }
        }
    }
} elseif ($iaCore->checkDomain() && $isDefaultPackage) {
    $slug = implode(IA_URL_DELIMITER, $iaView->url) . IA_URL_DELIMITER;

    if ($iaDb->exists('`status` = :status AND `slug` = :slug', ['status' => iaCore::STATUS_ACTIVE, 'slug' => $slug], 'categs')) {
        if ($pageUrl = $iaDb->one_bind('alias', '`name` = :page AND `status` = :status', ['page' => $package_home, 'status' => iaCore::STATUS_ACTIVE], 'pages')) {
            $pageUrl = explode(IA_URL_DELIMITER, trim($pageUrl, IA_URL_DELIMITER));
            $pageUrl = array_shift($pageUrl);
            $pageUrl = ('directory_home' == $iaCore->get('home_page')) ? $pageUrl . '_home' : $pageUrl;

            $iaView->name($pageUrl);
            $iaCore->requestPath = $iaView->url;
        }
    }
}
