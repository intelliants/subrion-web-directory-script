<?php
//##copyright##

$iaListing = $iaCore->factoryPackage('listing', IA_CURRENT_PACKAGE, iaCore::ADMIN);

$iaDb->setTable(iaListing::getTable());

if (iaView::REQUEST_JSON == $iaView->getRequestType())
{
	$action = isset($_GET['get']) && in_array($_GET['get'], array('alias', 'members')) ? $_GET['get'] : $pageAction;
	$output = array();

	switch ($action)
	{
		case 'alias':
			$title = $iaListing->titleAlias(isset($_GET['title']) ? $_GET['title'] : '', isset($_GET['alias']));

			$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
			$category_alias = false;
			if ($category > 0)
			{
				$category_alias = $iaDb->one('title_alias', '`id` = ' . $category, 'categs');
			}

			$data = array(
				'id' => (isset($_GET['id']) && (int)$_GET['id'] > 0 ? (int)$_GET['id'] : $iaDb->getNextId(iaListing::getTable(true))),
				'title_alias' => $title,
				'category_alias' => $category_alias ? $category_alias : '',
			);

			$output['data'] = $iaListing->url('view', $data);

			break;

		case 'members':
			if (isset($_GET['q']))
			{
				$where = "`fullname` LIKE '{$_GET['q']}%' OR  `username` LIKE '{$_GET['q']}%' ORDER BY `name` ASC ";
				if ($rows = $iaDb->all("IF(`fullname` != '', `fullname`, `username`) `name` ", $where, 0, 15, iaUsers::getTable()))
				{
					foreach ($rows as $row)
					{
						$output['options'][] = $row['name'];
					}
				}
			}

			break;

		case iaCore::ACTION_READ:
			$filterParams = array(
				'status' => 'equal',
			);

			$persistentConditions = array();

			if (isset($_GET['text']) && $_GET['text'])
			{
				$stmt = '(t1.`title` LIKE :text OR t1.`description` LIKE :text)';
				$iaDb->bind($stmt, array('text' => '%' . $_GET['text'] . '%'));

				$persistentConditions[] = $stmt;
			}

			if (isset($_GET['member']) && $_GET['member'])
			{
				$stmt = '(t3.`fullname` LIKE :member OR t3.`username` LIKE :member)';
				$iaDb->bind($stmt, array('member' => '%' . $_GET['member'] . '%'));

				$persistentConditions[] = $stmt;
			}

			if (isset($_GET['no_owner']) && $_GET['no_owner'])
			{
				$stmt = '(t1.`member_id` = 0 OR t1.`member_id` IS NULL)';

				$persistentConditions[] = $stmt;
			}

			if (isset($_GET['reported_as_broken']) && $_GET['reported_as_broken'])
			{
				$stmt = 't1.`reported_as_broken` = 1';

				$persistentConditions[] = $stmt;
			}

			$output = $iaListing->gridRead($_GET, $filterParams, $persistentConditions);

			break;

		case iaCore::ACTION_EDIT:
			$output = $iaListing->gridUpdate($_POST);

			break;

		case iaCore::ACTION_DELETE:
			$output = $iaListing->gridDelete($_POST);
	}

	$iaView->assign($output);
}

if (iaView::REQUEST_HTML == $iaView->getRequestType())
{
	$iaListing = $iaCore->factoryPackage('listing', IA_CURRENT_PACKAGE, iaCore::ADMIN);

	if (iaCore::ACTION_READ == $pageAction)
	{
		$iaView->grid('_IA_URL_packages/directory/js/admin/listings');
	}
	else
	{
		$iaField = $iaCore->factory('field');
		$iaPlan = $iaCore->factory('plan');
		$plans = $iaPlan->getPlans($iaListing->getItemName());
		$iaView->assign('plans', $plans);

		$rootCategory = $iaDb->row(array('id', 'title', 'parents'), '`parent_id` = -1', 'categs');
		$iaView->assign('root_cat', $rootCategory);

		// temporary
		iaBreadcrumb::remove(iaBreadcrumb::POSITION_LAST);
		iaBreadcrumb::preEnd(iaLanguage::get('listings'), $iaListing->getModuleUrl());
		iaBreadcrumb::remove(3);
		iaBreadcrumb::replaceEnd($iaView->get('title'), IA_SELF);

		$options = array('list' => 'go_to_list', 'add' => 'add_another_one', 'stay' => 'stay_here');
		$iaView->assign('goto', $options);
		//

		if ($pageAction == iaCore::ACTION_ADD)
		{
			$listing = array(
				'category_id' => $rootCategory['id'],
				'member_id' => iaUsers::getIdentity()->id,
				'crossed' => false,
				'featured' => false,
				'sponsored' => false,
				'sponsored_plan_id' => 0,
				'sponsored_end' => null,
				'status' => iaCore::STATUS_ACTIVE
			);
		}
		else
		{
			if (empty($iaCore->requestPath[0]))
			{
				return iaView::errorPage(iaView::ERROR_NOT_FOUND);
			}

			$listing = $iaListing->getById((int)$iaCore->requestPath[0]);

			$iaView->assign('id', $listing['id']);
		}

		$iaCore->startHook('editItemSetSystemDefaults', array('item' => &$listing));

		if ($_POST)
		{
			$plan = false;
			$error = false;
			$errorFields = array();
			$messages = array();

			$fields = $iaField->getByItemName($iaListing->getItemName());

			if (isset($_POST['owner']) && empty($_POST['owner'])) // Bug #1761
			{
				unset($_POST['owner']);
			}

			list($data, $error, $messages, $errorFields) = $iaField->parsePost($fields, $listing);

			if (isset($data['url']) && $data['url'] && !iaValidate::isUrl($data['url']))
			{
				$error = true;
				$messages[] = iaLanguage::get('error_url');
			}

			if (isset($data['email']) && $data['email'] && !iaValidate::isEmail($data['email']))
			{
				$error = true;
				$messages[] = iaLanguage::get('error_email_incorrect');
			}

			$data['rank'] = min(5, max(0, (int)$_POST['rank']));
			$data['category_id'] = iaUtil::checkPostParam('category_id');
			$data['title_alias'] = !empty($_POST['title_alias']) ? $_POST['title_alias'] : $_POST['title'];
			$data['title_alias'] = $iaListing->titleAlias($data['title_alias'], !empty($_POST['title_alias']));

			if ($iaCore->get('listing_crossed'))
			{
				$data['crossed_links'] = $_POST['crossed_links'] ? $_POST['crossed_links'] : false;
			}

			if ('listing' == $data['title_alias'])
			{
				$data['title_alias'] = '';
			}

			// get domain name and URL status
			$data['domain'] = $iaListing->getDomain($data['url']);

			if (iaValidate::isUrl($data['url']))
			{
				// check alexa
				if ($iaCore->get('directory_enable_alexarank'))
				{
					include IA_PACKAGES . 'directory' . IA_DS . 'includes' . IA_DS . 'alexarank.inc.php';
					$iaAlexaRank = new iaAlexaRank();

					if ($alexaData = $iaAlexaRank->getAlexa($data['domain']))
					{
						$data['alexa_rank'] = $alexaData['rank'];
					}
				}

				// check pagerank
				if ($iaCore->get('directory_enable_pagerank'))
				{
					include IA_PACKAGES . 'directory' . IA_DS . 'includes' . IA_DS . 'pagerank.inc.php';

					$data['pagerank'] = PageRank::getPageRank($data['domain']);
				}
			}

			if ($error)
			{
				iaField::keepValues($listing, $fields);

				$listing['rank'] = (int)$_POST['rank'];
				$listing['category_id'] = (int)$_POST['category_id'];
			}
			else
			{
				$iaCore->startHook('phpAdminBeforeListingSubmit');

				if (iaCore::ACTION_ADD == $pageAction)
				{
					$iaCore->startHook('phpAdminBeforeListingAdd');

					$listingId = $iaListing->insert($data);

					if ($listingId)
					{
						$messages[] = iaLanguage::get('listing_added');
					}
					else
					{
						$error = true;
						$messages[] = iaLanguage::get('db_error');
					}
				}
				else
				{
					$listingId = $listing['id'];
					$iaListing->update($data, $listingId);

					$messages = iaLanguage::get('saved');
				}

				if (!$error)
				{
					if ($plan)
					{
						$iaPlan->setPaid(array('item' => $iaListing->getItemName(), 'plan_id' => $plan, 'item_id' => $listingId, 'id' => 0));
					}

					$iaCore->startHook('phpAddItemAfterAll', array(
						'type' => iaCore::ADMIN,
						'listing' => $listingId,
						'item' => $iaListing->getItemName(),
						'data' => $data,
						'old' => $listing
					));

					$iaView->setMessages($messages, iaView::SUCCESS);

					$iaCore->factory('util');
					$url = IA_ADMIN_URL . $iaListing->getModuleUrl();
					iaUtil::post_goto(array(
						'add' => $url . 'add/',
						'list' => $url,
						'stay' => $url . 'edit/' . $listingId . '/'
					));
				}

				$listing = $iaListing->getById($listingId);
			}

			$iaView->setMessages($messages, iaView::ERROR);
		}

		$category = empty($listing['category_id'])
			? $rootCategory
			: $iaDb->row(array('id', 'title', 'parents'), iaDb::convertIds($listing['category_id']), 'categs');
		if ($category && isset($listing['id']))
		{
			$crossed = $iaDb->getAll("SELECT t.`id`, t.`title`
				FROM `{$iaCore->iaDb->prefix}categs` t, `{$iaCore->iaDb->prefix}listings_categs` cr
				WHERE t.`id` = cr.`category_id` AND cr.`listing_id` = '{$listing['id']}'");
			$category['crossed'] = array();
			foreach ($crossed as $val)
			{
				$category['crossed'][$val['id']] = $val['title'];
			}
		}
		if (isset($_POST['crossed_links']))
		{
			$crossedLimit = $iaCore->get('listing_crossed_limit', 5);
			$crossed = explode(',', $_POST['crossed_links']);
			$count = count($crossed) > $crossedLimit ? $crossedLimit : count($crossed);
			$add = array();
			for ($i = 0; $i < $count; $i++)
			{
				$add[] = (int)$crossed[$i];
			}
			if ($add)
			{
				$category['crossed'] = $iaDb->keyvalue(array('id', 'title'), "`id` IN (" . implode(',', $add) . ")", 'categs');
			}
		}

		$listing['item'] = $iaListing->getItemName();

		$sections = $iaField->filterByGroup($listing, $iaListing->getItemName());

		$iaView->assign('category', $category);
		$iaView->assign('item', $listing);
		$iaView->assign('sections', $sections);
		$iaView->assign('statuses', $iaListing->getStatuses());

		$iaView->display('listings');
	}

	$iaView->assign('quick_search_item', $iaListing->getItemName());
}