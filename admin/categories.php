<?php
//##copyright##

$iaCateg = $iaCore->factoryPackage('categ', IA_CURRENT_PACKAGE, iaCore::ADMIN);

$iaDb->setTable(iaCateg::getTable());

if (iaView::REQUEST_JSON == $iaView->getRequestType())
{
	$action = isset($_GET['get']) && in_array($_GET['get'], array('alias', 'tree')) ? $_GET['get'] : $pageAction;

	$output = array();

	switch ($action)
	{
		case 'alias':
			$title = $iaCateg->getTitleAlias(array('title_alias' => $_GET['title'], 'parent_id' => (int)$_GET['category']));

			$output['data'] = $iaCateg->url('default', array('title_alias' => $title));

			break;

		case 'tree':
			$output = array();

			$categoryId = (isset($_GET['id']) && is_numeric($_GET['id'])) ? (int)$_GET['id'] : 0;
			$categoryId = (isset($_POST['node']) && is_numeric($_POST['node'])) ? (int)$_POST['node'] : $categoryId;

			$clause = '`parent_id` = ' . $categoryId . ' ORDER BY `title`';
			$entries = $iaDb->all(array('id', 'title', 'child'), $clause, null, null, iaCateg::getTable());

			foreach ($entries as $entry)
			{
				$output[] = array(
					'id' => (int)$entry['id'],
					'text' => $entry['title'],
					'children' => $entry['child'] && $entry['child'] != $entry['id']
				);
			}

			break;

		case iaCore::ACTION_READ:
			if (isset($_POST['action']))
			{
				if ('pre_recount_listings' == $_POST['action'])
				{
					$iaCateg->clearListingsNum();

					$output['categories_total'] = $iaCateg->getCount();
				}

				if ('recount_listings' == $_POST['action'])
				{
					$output = $iaCateg->recountListingsNum($_POST['start'], $_POST['limit']);
				}
			}
			else
			{
				$columns = array('title', 'title_alias', 'date_added', 'date_modified', 'num_all_listings', 'status');
				$filterParams = array(
					'title' => 'like',
					'status' => 'equal'
				);

				$output = $iaCateg->gridRead($_GET, $columns, $filterParams);
			}

			break;

		case iaCore::ACTION_EDIT:
			$output = $iaCateg->gridUpdate($_POST);

			break;

		case iaCore::ACTION_DELETE:
			$output = $iaCateg->gridDelete($_POST);
	}

	$iaView->assign($output);
}

if (iaView::REQUEST_HTML == $iaView->getRequestType())
{
	// process actions for manage categories page
	if (iaCore::ACTION_READ == $pageAction)
	{
		// use filter for categories status
		if (isset($_GET['status']))
		{
			if (in_array($_GET['status'], array(iaCore::STATUS_ACTIVE, iaCore::STATUS_INACTIVE)))
			{
				$_SESSION['categ_status'] = $_GET['status'];
			}
		}
		elseif (isset($_SESSION['categ_status']))
		{
			unset($_SESSION['categ_status']);
		}

		$iaView->grid('_IA_URL_packages/' . $iaCateg->getPackageName() . '/js/admin/categories');
	}
	else
	{
		// temporary
		iaBreadcrumb::remove(iaBreadcrumb::POSITION_LAST);
		iaBreadcrumb::preEnd(iaLanguage::get('categories'), $iaCateg->getModuleUrl());
		iaBreadcrumb::remove(3);
		iaBreadcrumb::replaceEnd($iaView->get('title'), IA_SELF);

		$options = array('list' => 'go_to_list', 'add' => 'add_another_one', 'stay' => 'stay_here');
		$iaView->assign('goto', $options);
		//

		$categories = $iaCateg->get();

		$rootCategory = $iaCateg->getCategory('`parent_id` = -1');
		$iaView->assign('root_cat', $rootCategory);

		$category = array(
			'parent_id' => $rootCategory['id'],
			'parents' => $rootCategory['parents'],
			'crossed' => false,
			'locked' => 0,
			'icon' => false,
			'status' => iaCore::STATUS_ACTIVE,
			'title_alias' => ''
		);

		if (iaCore::ACTION_EDIT == $pageAction)
		{
			$category = $iaCateg->getById(((int)$iaCore->requestPath[0]));

			$iaView->title($iaView->get('title') . ': "' . $category['title'] . '"');
			$iaView->assign('id', $category['id']);
			if (empty($category))
			{
				return iaView::errorPage(iaView::ERROR_NOT_FOUND);
			}
		}

		$iaField = $iaCore->factory('field');

		$fieldsGroups = $iaField->getGroups($iaCateg->getItemName());

		if ($_POST)
		{
			$error = false;
			$errorFields = array();
			$messages = array();

			// get categories fields
			$fields = $iaField->getByItemName($iaCateg->getItemName());

			if ($fields)
			{
				list($data, $error, $messages, $errorFields) = $iaField->parsePost($fields, $category);
			}

			if (!$error)
			{
				$iaCore->startHook('phpAdminBeforeCategorySubmit');

				$data['parent_id'] = iaUtil::checkPostParam('parent_id', $rootCategory['id']);
				$data['status'] = iaUtil::checkPostParam('status', iaCore::STATUS_ACTIVE);
				$data['locked'] = iaUtil::checkPostParam('locked');
				$data['title_alias'] = empty($_POST['title_alias']) ? htmlspecialchars_decode($data['title']) : $_POST['title_alias'];
				$data['title_alias'] = $iaCateg->getTitleAlias($data);
				$data['crossed'] = $_POST['crossed'] ? $_POST['crossed'] : false;

				if (iaCore::ACTION_ADD == $pageAction)
				{
					// add category
					$iaCore->startHook('phpAdminBeforeCategoryAdd');

					$data['id'] = $iaCateg->insert($data, $rootCategory);
					$messages = iaLanguage::get('category_added');
				}
				else
				{
					// update category information
					$data['id'] = $category['id'];

					$iaCore->startHook('phpAdminBeforeCategoryUpdate');

					$iaCateg->update($data, $data['id']);

					$messages = iaLanguage::get('saved');
				}

				// get updated category information
				$category = $iaCateg->getCategory("`id` = {$data['id']}");

				// redirect to correct page
				$iaView->setMessages($error ? iaLanguage::get('error_while_saving') : $messages, $error ? iaView::ERROR : iaView::SUCCESS);

				$url = IA_ADMIN_URL . 'directory/categories/';
				$goto = array('add'	=> $url . 'add/', 'list' => $url, 'stay' => $url . 'edit/' . $data['id']);

				iaUtil::post_goto($goto);
			}
			else
			{
				iaField::keepValues($category, $fields);

				$category['parent_id'] = (int)$_POST['parent_id'];
			}

			$iaView->setMessages($messages, $error ? iaView::ERROR : iaView::SUCCESS);
		}

		if (isset($category['id']))
		{
			$sql = <<<SQL
SELECT t.`id`, t.`title`
FROM `{$iaCore->iaDb->prefix}categs` t, `{$iaCore->iaDb->prefix}categs_crossed` cr
WHERE t.`id` = cr.`crossed_id` && cr.`category_id` = '{$category['id']}'
SQL;
			$crossed = $iaDb->getAll($sql);

			$category['crossed'] = array();
			foreach ($crossed as $val)
			{
				$category['crossed'][$val['id']] = $val['title'];
			}
		}

		if (isset($_POST['crossed']))
		{
			$crossed = explode(',', $_POST['crossed']);
			$count = count($crossed);
			$add = array();
			for ($i = 0; $i < $count; $i++)
			{
				$add[] = (int)$crossed[$i];
			}
			if ($add)
			{
				$category['crossed'] = $iaDb->keyvalue(array('id', 'title'), "`id` IN (" . implode(',', $add) . ")", iaCateg::getTable());
			}
		}

		if (isset($category['parent_id']) && $category['parent_id'] != $rootCategory['parent_id'])
		{
			$parent = $iaDb->row(array('id', 'parent_id', 'title', 'title_alias', 'parents'), iaDb::convertIds($category['parent_id']), iaCateg::getTable());
			$category['title_alias'] = end(explode(IA_URL_DELIMITER, trim($category['title_alias'], IA_URL_DELIMITER)));
		}
		else
		{
			$parent = $rootCategory;
		}

		$sections = $iaField->filterByGroup($category, $iaCateg->getItemName(), array('page' => iaCore::ADMIN, 'order' => '`order`'));

		$iaView->assign('fields_groups', $fieldsGroups);
		$iaView->assign('sections', $sections);
		$iaView->assign('parent', $parent);
		$iaView->assign('item', $category);
		$iaView->assign('statuses', $iaCateg->getStatuses());

		$iaView->display('categories');
	}

	$iaView->assign('quick_search_item', $iaCateg->getItemName());
}
$iaDb->resetTable();