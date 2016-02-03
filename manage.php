<?php
//##copyright##

$iaCateg = $iaCore->factoryPackage('categ', IA_CURRENT_PACKAGE);

if (iaView::REQUEST_JSON == $iaView->getRequestType() && isset($_GET['get']) && 'tree' == $_GET['get'])
{
	$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : $iaDb->one('id', '`parent_id` = -1', iaCateg::getTable());

	$output = array();
	$entries = $iaDb->all(
		array('id', 'title', 'title_alias', 'locked', 'child', 'value' => 'id'),
		"`parent_id` = $categoryId && `status` = 'active' ORDER BY `title`",
		null, null, iaCateg::getTable());

	foreach ($entries as $row)
	{
		$entry = array('id' => $row['id'], 'text' => $row['title']);
		empty($row['locked']) || $entry['state'] = array('disabled' => true);
		$entry['children'] = $row['child'] && $row['child'] != $row['id'];

		$output[] = $entry;
	}

	$iaView->assign($output);
}

if (iaView::REQUEST_HTML == $iaView->getRequestType())
{
	$iaField = $iaCore->factory('field');
	$iaUtil = $iaCore->factory('util');

	$iaListing = $iaCore->factoryPackage('listing', IA_CURRENT_PACKAGE);

	iaBreadcrumb::replace(iaLanguage::get(IA_CURRENT_PACKAGE), $iaCore->packagesData[IA_CURRENT_PACKAGE]['url'], 2);

	switch ($pageAction)
	{
		case iaCore::ACTION_ADD:
			$listing = array();

			break;
		case iaCore::ACTION_EDIT:
		case iaCore::ACTION_DELETE:
			$listingId = (int)end($iaCore->requestPath);

			if (empty($listingId))
			{
				return iaView::errorPage(iaView::ERROR_NOT_FOUND);
			}
			else
			{
				$listing = $iaListing->getById($listingId);
				if (empty($listing))
				{
					return iaView::errorPage(iaView::ERROR_NOT_FOUND);
				}
				else
				{
					if (!iaUsers::hasIdentity() || $listing['member_id'] != iaUsers::getIdentity()->id)
					{
						return iaView::accessDenied();
					}
				}

				$category = $iaCateg->getCategory(iaDb::convertIds($listing['category_id']));
			}

			if (iaCore::ACTION_DELETE == $pageAction)
			{

				$iaView->disableLayout();

				$iaListing->delete($listing)
					? iaUtil::redirect(iaLanguage::get('wait_redirect'), iaLanguage::get('listing_removed'), $iaListing->url('my'))
					: iaUtil::redirect(iaLanguage::get('wait_redirect'), iaLanguage::get('cant_remove_listing'), $iaListing->url('view', $listing));
			}
	}

	$where = ''; // used to ignore some fields
	if (!$iaCore->get('reciprocal_check'))
	{
		$where .= " f.`name` != 'reciprocal_url' ";
	}

	$iaPlan = $iaCore->factory('plan');
	$plans = $iaPlan->getPlans($iaListing->getItemName());
	$iaView->assign('plans', $plans);

	if (isset($_POST['data-listing']))
	{
		$fields = iaField::getAcoFieldsList(null, $iaListing->getItemName(), $where, true);
		$item = false;
		$error = false;
		$plan = false;
		$messages = array();

		list($item, $error, $messages) = $iaField->parsePost($fields, $listing);

		if (!iaUsers::hasIdentity() && !iaValidate::isCaptchaValid())
		{
			$error = true;
			$messages[] = iaLanguage::get('confirmation_code_incorrect');
		}

		if (isset($item['url']) && $item['url'] && !iaValidate::isUrl($item['url']))
		{
			$error = true;
			$messages[] = iaLanguage::get('error_url');
		}

		if (isset($item['email']) && $item['email'] && !iaValidate::isEmail($item['email']))
		{
			$error = true;
			$messages[] = iaLanguage::get('error_email_incorrect');
		}
		elseif (!iaUsers::hasIdentity() && (!isset($item['email']) || empty($item['email'])))
		{
			$error = true;
			$messages[] = iaLanguage::getf('field_is_empty', array('field' => iaLanguage::get('email')));
		}

		$item['ip'] = $iaUtil->getIp();
		$item['member_id'] = 0;
		if (iaUsers::hasIdentity())
		{
			$item['member_id'] = iaUsers::getIdentity()->id;
		}
		elseif($iaCore->get('listing_tie_to_member'))
		{
			$iaUsers = $iaCore->factory('users');
			$member = $iaUsers->getInfo($item['email'], 'email');

			$item['member_id'] = ($member) ? $member['id'] : 0;
		}

		$item['category_id'] = (int)$_POST['category_id'];
		$item['status'] = $iaCore->get('listing_auto_approval') ? iaCore::STATUS_ACTIVE : iaCore::STATUS_APPROVAL;
		$item['short_description'] = iaSanitize::snippet($_POST['description'], $iaCore->get('directory_summary_length'));

		if ($iaCore->get('listing_crossed'))
		{
			$item['crossed_links'] = $_POST['crossed_links'] ? $_POST['crossed_links'] : false;
		}

		$item['title_alias'] = iaSanitize::alias($item['title']);

		$category = $iaCateg->getCategory(iaDb::convertIds($item['category_id']));
		if (!$category)
		{
			$error = true;
			$messages[] = iaLanguage::get('invalid_category');
		}
		elseif ($category['locked'])
		{
			$error = true;
			$messages[] = iaLanguage::get('error_locked_category');
		}

		if (!$iaListing->isSubmissionAllowed($item['member_id']))
		{
			$error = true;
			$messages[] = iaLanguage::get('limit_is_exceeded');
		}

		if (!$error)
		{
			// get domain name and URL status
			$item['domain'] = $iaListing->getDomain($item['url']);

			if (iaValidate::isUrl($item['url']))
			{
				// check alexa
				if ($iaCore->get('directory_enable_alexarank'))
				{
					include IA_PACKAGES . $iaCore->packagesData['directory']['name'] . IA_DS . 'includes' . IA_DS . 'alexarank.inc.php';
					$iaAlexaRank = new iaAlexaRank();

					$alexaData = $iaAlexaRank->getAlexa($item['domain']);
					$data['alexa_rank'] = $alexaData['rank'];
				}

				if ($iaCore->get('directory_enable_pagerank'))
				{
					include IA_PACKAGES . $iaCore->packagesData['directory']['name'] . IA_DS . 'includes' . IA_DS . 'pagerank.inc.php';

					$item['pagerank'] = PageRank::getPageRank($item['domain']);
				}
			}

			// check if listing already exists
			if (iaCore::ACTION_ADD == $pageAction && $iaCore->get('duplicate_checking'))
			{
				$check = $iaCore->get('duplicate_type') == 0 ? $item['domain'] : $item['url'];
				$countDuplicateList = $iaListing->checkDuplicateListings($item['domain'], $check);
				if ($countDuplicateList > 0)
				{
					$error = true;
					$messages[] = iaLanguage::get('error_banned');
				}
				elseif ($countDuplicateList)
				{
					$error = true;
					$messages[] = iaLanguage::get('error_listing_present');
				}
			}
		}

		if (!$error)
		{
			$iaCore->startHook('phpDirectoryBeforeListingSubmit', array('item' => &$item));

			if (iaCore::ACTION_ADD == $pageAction)
			{
				$item['id'] = $iaListing->insert($item);
				if (!$item['id'])
				{
					$error = true;
					$messages[] = iaLanguage::get('error_add_listing');
				}
				else
				{
					$messages[] = ($item['status'] == iaCore::STATUS_ACTIVE ? iaLanguage::get('listing_added') : iaLanguage::get('listing_added_waiting'));
				}
			}
			else
			{
				$item['id'] = $listing['id'];

				$affected = $iaListing->update($item, $listing);
				if (!$affected)
				{
					$error = true;
					$messages[] = iaLanguage::get('error_update_listing');
				}
				else
				{
					$messages[] = ($item['status'] == iaCore::STATUS_ACTIVE ? iaLanguage::get('listing_updated') : iaLanguage::get('listing_updated_waiting'));
				}
			}
		}
		else
		{
			iaField::keepValues($listing, $fields);
		}

		if (!$error)
		{
			$item['category_alias'] = $category['title_alias'];
			$url = (iaCore::STATUS_ACTIVE == $item['status'] ||
				(iaUsers::hasIdentity() && iaCore::STATUS_APPROVAL == $item['status']))
				? $iaListing->url('view', $item) : $iaCore->packagesData[$iaListing->getPackageName()]['url'];

			// if plan is chosen
			if (isset($_POST['plan_id']) && !empty($_POST['plan_id']))
			{
				$plan = $iaPlan->getById($_POST['plan_id']);

				if ($plan['cost'] > 0)
				{
					$url = $iaPlan->prePayment($iaListing->getItemName(), $item, $plan['id'], $url);
				}
			}

			$iaCore->startHook('phpAddItemAfterAll', array(
				'type' => iaCore::ADMIN,
				'listing' => $item['id'],
				'item' => $iaListing->getItemName(),
				'data' => $item,
				'old' => $listing
			));

			if (iaCore::ACTION_EDIT != $pageAction || $plan)
			{
				$iaView->setMessages($messages, iaView::SUCCESS);
				iaUtil::go_to($url);
			}
		}

		$iaView->setMessages($messages, $error ? iaView::ERROR : iaView::SUCCESS);

		$listing = array_merge((array)$listing, $item);
	}

	$category = empty($category) ? array('id' => 0, 'parents' => '') : $category;
	empty($listing['id']) || $category['crossed'] = $iaCateg->getCrossedByListingId($listing['id']);

	if (iaCore::ACTION_EDIT == $pageAction)
	{
		$iaCore->factory('item')->setItemTools(array(
			'id' => 'action-visit',
			'title' => iaLanguage::get('view'),
			'attributes' => array(
				'href' => $iaListing->url('view', $listing),
			)
		));
	}
	elseif (isset($_GET['category']) && is_numeric($_GET['category']))
	{
		$category = $iaCateg->getById($_GET['category']);
	}

	$listing['item'] = $iaListing->getItemName();

	// get fieldgroups
	list($tabs, $fieldgroups) = $iaField->generateTabs($iaField->filterByGroup($item, $iaListing->getItemName()));
	// compose tabs
	$sections = array_merge(array('common' => $fieldgroups), $tabs);

	$iaView->assign('sections', $sections);
	$iaView->assign('item', $listing);
	$iaView->assign('category', $category);

	$iaView->title(iaLanguage::get('page_title_' . $pageAction . '_listing'));

	$iaView->display('manage');
}