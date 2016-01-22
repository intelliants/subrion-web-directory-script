<?php
//##copyright##

$iaListing = $iaCore->factoryPackage('listing', IA_CURRENT_PACKAGE);

if (iaView::REQUEST_JSON == $iaView->getRequestType())
{
	if ('report' == $_POST['action'])
	{
		$id = (int)$_POST['id'];
		$comment = '';
		if ((isset($_POST['comments']) && $_POST['comments']))
		{
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
		$iaMailer->setReplacements(array(
			'title' => $listing['title'],
			'comments' => $comment,
		));
		$iaMailer->sendToAdministrators();

		$email = (isset($listing['email']) && $listing['email']) ? $listing['email'] : $iaDb->one('email', iaDb::convertIds($listing['member_id']), iaUsers::getTable());

		if ($email)
		{
			$iaMailer->loadTemplate('reported_as_broken');
			$iaMailer->setReplacements(array(
				'title' => $listing['title'],
				'comments' => $comment,
			));
			$iaMailer->addAddress($email);

			$iaMailer->send();
		}
		$fields = array('reported_as_broken' => 1);
		if ($comment)
		{
			if (isset($listing['reported_as_broken_comments']) && $listing['reported_as_broken_comments'])
			{
				$comment = $listing['reported_as_broken_comments'] . $comment;
			}
			$fields['reported_as_broken_comments'] = $comment;
		}
		$iaDb->update($fields, iaDb::convertIds($id), null, iaListing::getTable());
	}
}

if (iaView::REQUEST_HTML == $iaView->getRequestType())
{
	$listingId = (count($iaView->url) > 0) ? (int)$iaView->url[count($iaView->url) - 1] : 0;
	if (!$listingId)
	{
		return iaView::errorPage(iaView::ERROR_NOT_FOUND);
	}

	$listing = $iaListing->getById($listingId);

	if (empty($listing))
	{
		return iaView::errorPage(iaView::ERROR_NOT_FOUND);
	}

	$categoryPath = $iaView->url;
	unset($categoryPath[count($categoryPath) - 1]);
	$categoryPath = $categoryPath ? implode(IA_URL_DELIMITER, $categoryPath) . IA_URL_DELIMITER : '';
	$categoryPath = str_replace(str_replace(IA_URL, '', $iaListing->getInfo('url')), '', $categoryPath);

	if ($categoryPath != $listing['category_alias'])
	{
		$validUrl = $iaListing->url('view', $listing);
		iaUtil::go_to($validUrl);
	}

	$iaCategory = $iaCore->factoryPackage('categ', IA_CURRENT_PACKAGE);

	$category = $iaCategory->getById($listing['category_id']);

	$listing['item'] = $iaListing->getItemName();

	$iaView->set('subpage', $category['id']);

	if ($category && isset($category['parents']) && $category['parents'])
	{
		$parents = $iaCategory->get("`id` IN({$category['parents']}) AND `parent_id` > -1");

		foreach ($parents as $p)
		{
			$url = $iaCategory->url('view', $p);
			iaBreadcrumb::add($p['title'], $url);
		}
	}

	if (iaUsers::hasIdentity() && iaUsers::getIdentity()->id == $listing['member_id'])
	{
		$pageActions[] = array(
			'icon' => 'icon-edit',
			'title' => iaLanguage::get('edit'),
			'url' => $iaListing->url(iaCore::ACTION_EDIT, $listing),
			'classes' => 'btn-info'
		);

		$pageActions[] = array(
			'icon' => 'icon-remove',
			'title' => iaLanguage::get('remove'),
			'url' => $iaListing->url(iaCore::ACTION_DELETE, $listing),
			'classes' => 'btn-danger js-delete-listing'
		);

		$iaView->set('actions', $pageActions);
	}

	$iaItem = $iaCore->factory('item');

	if ($listing['url'])
	{
		$iaItem->setItemTools(array(
			'id' => 'action-visit',
			'title' => iaLanguage::get('visit_site'),
			'attributes' => array(
				'href' => $listing['url'],
			)
		));
	}

	$iaItem->setItemTools(array(
		'id' => 'action-report',
		'title' => iaLanguage::get('report_listing'),
		'attributes' => array(
			'href' => '#',
			'id' => 'js-cmd-report-listing',
			'data-id' => $listing['id']
		)
	));

	// update favorites status
	$listing = array_shift($iaItem->updateItemsFavorites(array($listing), $iaListing->getItemName()));

	iaBreadcrumb::replaceEnd($listing['title'], IA_SELF);

	$iaListing->incrementViewsCounter($listingId);

	// get fieldgroups
	$iaField = $iaCore->factory('field');
	list($tabs, $fieldgroups) = $iaField->generateTabs($iaField->filterByGroup($item, $iaListing->getItemName()));

	// compose tabs
	$sections = array_merge(array('common' => $fieldgroups), $tabs);
	$iaView->assign('sections', $sections);

	$iaCore->startHook('phpViewListingBeforeStart', array(
		'listing' => $listingId,
		'item' => 'listings',
		'title' => $listing['title'],
		'desc' => $listing['short_description'],
	));

	$author = $iaDb->row_bind(iaDb::ALL_COLUMNS_SELECTION, '`status` = :status AND `id` = :id', array('status' => iaCore::STATUS_ACTIVE, 'id' => $listing['member_id']), iaUsers::getTable());
	$counter = $iaDb->one(iaDb::STMT_COUNT_ROWS, iaDb::convertIds($listing['member_id'], 'member_id'), iaListing::getTable());

	$iaView->assign('author', $author);
	$iaView->assign('listings_num', $counter);
	$iaView->assign('item', $listing);

	$iaView->set('keywords', $listing['meta_keywords']);
	$iaView->set('description', $listing['meta_description']);

	$iaView->title($listing['title']);

	$iaView->display('view');
}