<?php
//##copyright##

$package = 'directory';
$accessGranted = false;
$isDefaultPackage = ($package == $iaCore->get('default_package'));
$extras = $iaCore->getExtras($package);
$package_home = 'directory_home';

if ($iaCore->checkDomain() && $isDefaultPackage)
{
	$accessGranted = true;
}
elseif (!$iaCore->checkDomain())
{
	if (isset($extras['url']) && $extras['url'] == $iaView->domainUrl)
	{
		$accessGranted = true;
	}
}
elseif (count($iaCore->requestPath) > 0)
{
	$accessGranted = true;
}

$url = end($iaView->url);

$iaPage = $iaCore->factory('page', iaCore::FRONT);

if ($listingId = (int)$url)
{
	if ($listingData = $iaDb->row_bind(array('title_alias'), '`id` = :id', array('id' => $listingId), 'listings'))
	{
		if ($listingData['title_alias'])
		{
			$alias = substr($url, strpos($url, '-') + 1);
			if ($alias == $listingData['title_alias'])
			{
				$pageName = $iaPage->getUrlByName('view_listing', false);
				$pageName = array_shift(explode(IA_URL_DELIMITER, $pageName));

				array_unshift($iaCore->requestPath, 'listing');
				$iaView->name($pageName);
			}
		}
	}
}
elseif ($iaCore->checkDomain() && $isDefaultPackage)
{
	$alias = implode(IA_URL_DELIMITER, $iaView->url) . IA_URL_DELIMITER;
	if ($iaDb->exists('`status` = :status AND `title_alias` = :alias', array('status' => iaCore::STATUS_ACTIVE, 'alias' => $alias), 'categs'))
	{
		if ($pageUrl = $iaDb->one_bind('alias', '`name` = :page AND `status` = :status', array('page' => $package_home, 'status' => iaCore::STATUS_ACTIVE), 'pages'))
		{
			$pageUrl = array_shift(explode(IA_URL_DELIMITER, trim($pageUrl, IA_URL_DELIMITER)));
			$pageUrl = ('directory_home' == $iaCore->get('home_page')) ? $pageUrl . '_home' : $pageUrl;
			$iaView->name($pageUrl);
			$iaCore->requestPath = $iaView->url;
		}
	}
}