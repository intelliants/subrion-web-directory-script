<?php
//##copyright##

$iaCateg = $iaCore->factoryPackage('categ', IA_CURRENT_PACKAGE);

if (iaView::REQUEST_JSON == $iaView->getRequestType())
{
	if (1 == count($iaCore->requestPath) && 'tree' == $iaCore->requestPath[0])
	{
		$categoryId = empty($_GET['id']) ? 0 : (int)$_GET['id'];

		$output = [];

		$where = "`parent_id` = $categoryId AND `status` = 'active'";
		$where.= ' ORDER BY `title`';

		$entries = $iaCateg->getAll($where, ['id', 'title' => 'title_' . $iaCore->language['iso'],
			'title_alias', 'locked', 'child', 'value' => 'id']);

		foreach ($entries as $row)
		{
			$entry = ['id' => $row['id'], 'text' => $row['title']];
			empty($row['locked']) || $entry['state'] = ['disabled' => true];
			$entry['children'] = $row['child'] && $row['child'] != $row['id'] || empty($row['child']);

			$output[] = $entry;
		}

		$iaView->assign($output);
	}
}

if (iaView::REQUEST_HTML == $iaView->getRequestType())
{
	$iaField = $iaCore->factory('field');
	$iaUtil = $iaCore->factory('util');

	$iaListing = $iaCore->factoryPackage('listing', IA_CURRENT_PACKAGE);

	iaBreadcrumb::replace(iaLanguage::get(IA_CURRENT_PACKAGE), $iaCore->factory('page', iaCore::FRONT)->getUrlByName('directory_home'), 2);

	switch ($pageAction)
	{
		case iaCore::ACTION_ADD:
			$listing = [
				'category_id' => 0
			];

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

				$category = $iaCateg->getOne(iaDb::convertIds($listing['category_id']));
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
		$item = false;
		$error = false;
		$plan = false;
		$messages = [];

		list($item, $error, $messages) = $iaField->parsePost($iaListing->getItemName(), $listing);

		if (!iaUsers::hasIdentity() && !iaValidate::isCaptchaValid())
		{
			$error = true;
			$messages[] = iaLanguage::get('confirmation_code_incorrect');
		}

		if (!empty($item['url']) && !iaValidate::isUrl($item['url']))
		{
			if (iaValidate::isUrl($item['url'], false))
			{
				$item['url'] = 'http://' . $item['url'];
			}
			else
			{
				$error = true;
				$messages[] = iaLanguage::get('error_url');
			}
		}

		if (!empty($item['email']) && !iaValidate::isEmail($item['email']))
		{
			$error = true;
			$messages[] = iaLanguage::get('error_email_incorrect');
		}
		elseif (!iaUsers::hasIdentity() && (!isset($item['email']) || empty($item['email'])))
		{
			$error = true;
			$messages[] = iaLanguage::getf('field_is_empty', ['field' => iaLanguage::get('email')]);
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

		$item['category_id'] = (int)$_POST['tree_id'];
		$item['status'] = $iaCore->get('listing_auto_approval') ? iaCore::STATUS_ACTIVE : iaCore::STATUS_APPROVAL;

		if ($iaCore->get('listing_crossed'))
		{
			$item['crossed_links'] = $_POST['crossed_links'] ? $_POST['crossed_links'] : false;
		}

		$item['title_alias'] = iaSanitize::alias($_POST['title'][$iaView->language]);

		$category = $iaCateg->getById($item['category_id']);

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
			}

			// check if listing with specified field already exists
			if (iaCore::ACTION_ADD == $pageAction && $iaCore->get('directory_duplicate_check'))
			{
				if ($field = $iaListing->checkDuplicateListings($item))
				{
					$error = true;
					$messages[] = iaLanguage::getf('error_duplicate_field', ['field' => $field]);
				}
			}
		}

		if (!$error)
		{
			$iaCore->startHook('phpDirectoryBeforeListingSubmit', ['item' => &$item]);

			if (iaCore::ACTION_ADD == $pageAction)
			{
				$id = $iaListing->insert($item);

				if (!$id)
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
				$id = $listing['id'];
				$result = $iaListing->update($item, $id);

				if (!$result)
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
			$listing = $item; // keep user input
		}

		if (!$error)
		{
			$item['category_alias'] = $category['title_alias'];
			$url = (iaCore::STATUS_ACTIVE == $item['status'] ||
				(iaUsers::hasIdentity() && iaCore::STATUS_APPROVAL == $item['status']))
				? $iaListing->url('view', $iaListing->getById($id)) : $iaCore->packagesData[$iaListing->getPackageName()]['url'];

			// if plan is chosen
			if (isset($_POST['plan_id']) && $_POST['plan_id'] && $_POST['plan_id'] != $listing['sponsored_plan_id'])
			{
				$plan = $iaPlan->getById((int)$_POST['plan_id']);

				if ($plan['cost'] > 0)
				{
					$url = $iaPlan->prePayment($iaListing->getItemName(), $item, $plan['id'], $url);
				}

				else
				{
					$iaTransaction = $iaCore->factory('transaction');
					$transactionId = $iaTransaction->create(null, 0, $iaListing->getItemName(), $item, '', (int)$_POST['plan_id'], true);
					$transaction = $iaTransaction->getBy('sec_key', $transactionId);
					$iaPlan->setPaid($transaction);
				}
			}

			$iaCore->startHook('phpAddItemAfterAll', [
				'type' => iaCore::ADMIN,
				'listing' => $id,
				'item' => $iaListing->getItemName(),
				'data' => $item,
				'old' => $listing
			]);

			if (iaCore::ACTION_EDIT != $pageAction || $plan)
			{
				$iaView->setMessages($messages, iaView::SUCCESS);
				iaUtil::go_to($url);
			}
		}

		$iaView->setMessages($messages, $error ? iaView::ERROR : iaView::SUCCESS);

		$listing = array_merge((array)$listing, $item);
	}

	$category = empty($category) ? ['id' => 0, 'parents' => ''] : $category;
	empty($id) || $category['crossed'] = $iaCateg->getCrossedByListingId($id);

	if (iaCore::ACTION_EDIT == $pageAction)
	{
		$iaCore->factory('item')->setItemTools([
			'id' => 'action-visit',
			'title' => iaLanguage::get('view'),
			'attributes' => [
				'href' => $iaListing->url('view', $listing),
			]
		]);
	}
	elseif (isset($_GET['category']) && is_numeric($_GET['category']))
	{
		$category = $iaCateg->getById($_GET['category']);
	}

	$listing['item'] = $iaListing->getItemName();

	$sections = $iaField->getTabs($iaListing->getItemName(), $listing);

	$iaView->assign('sections', $sections);
	$iaView->assign('item', $listing);
	$iaView->assign('category', $category);

	//$iaView->title(iaLanguage::get('page_title_' . $pageAction . '_listing')); /*-- @batry commented out --*/

	$iaView->display('manage');
}