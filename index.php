<?php
//##copyright##

$iaListing = $iaCore->factoryPackage('listing', IA_CURRENT_PACKAGE);

if (iaView::REQUEST_JSON == $iaView->getRequestType())
{
	$data = array();

	$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : $iaDb->one('id', '`parent_id` = -1', 'categs');

	$where = "`parent_id` = $categoryId && `status` = 'active'";
	if ($iaCore->get('directory_hide_empty_categories'))
	{
		$where .= " AND `num_all_listings` != 0";
	}
	$where .= " ORDER BY `title`";

	$data = array();
	$rows = $iaDb->all(array('id', 'title', 'title_alias', 'locked', 'child'), $where, null, null, 'categs');

	foreach ($rows as &$row)
	{
		$data[] = array(
			'id' => (int)$row['id'],
			'text' => $row['title'],
			'children' => $row['child'] && $row['child'] != $row['id']
		);
	}

	if (isset($_GET['title']) && isset($_GET['category']) && isset($_GET['get']) && 'alias' == $_GET['get'])
	{
		$iaCateg = $iaCore->factoryPackage('categ', IA_CURRENT_PACKAGE, iaCore::ADMIN);

		$iaDb->setTable(iaCateg::getTable());

		$title = $iaCateg->getTitleAlias(array('title_alias' => $_GET['title'], 'parent_id' => (int)$_GET['category']));

		$data['data'] = $iaCateg->url('default', array('title_alias' => $title));

		$iaDb->resetTable();
	}

	if ('pre_recount_listings' == $_POST['action'])
	{
		$iaCateg = $iaCore->factoryPackage('categ', IA_CURRENT_PACKAGE, iaCore::ADMIN);

		$iaCateg->clearListingsNum();

		$data['categories_total'] = $iaCateg->getCount();
	}

	if ('recount_listings' == $_POST['action'])
	{
		$iaCateg = $iaCore->factoryPackage('categ', IA_CURRENT_PACKAGE, iaCore::ADMIN);

		$data = $iaCateg->recountListingsNum($_POST['start'], $_POST['limit']);
	}

	$iaView->assign($data);
}

if (iaView::REQUEST_HTML == $iaView->getRequestType())
{
	$pageName = $iaView->name();

	if (isset($iaCore->requestPath[0]))
	{
		if ($value = $iaDb->one_bind('name', '`alias` = :alias', array('alias' => iaSanitize::sql($iaCore->requestPath[0]) . "/'"), 'pages'))
		{
			array_shift($iaCore->requestPath);
			$pageName = $value;
		}
	}

	$order = '';
	$page = isset($_GET['page']) && 1 < $_GET['page'] ? (int)$_GET['page'] : 1;
	$per_page = $iaCore->get('directory_listings_perpage', 10);
	$start = ($page - 1) * $per_page;

	$listings = array();
	$totalListings = 0;
	$orders = array('date_added-asc', 'date_added-desc', 'rank-desc', 'rank-asc', 'title-desc', 'title-asc');

	if (!isset($_SESSION['d_order']))
	{
		$_SESSION['d_order'] = $orders[0];
	}
	list($d_sort, $d_type) = explode('-', $_SESSION['d_order']);

	if (isset($_GET['sort_by']))
	{
		$d_sort = $_GET['sort_by'];
		$_POST['sort_by'] = $d_sort . '-' . $d_type;
	}
	if (isset($_GET['order_type']))
	{
		$d_type = $_GET['order_type'];
		$_POST['sort_by'] = $d_sort . '-' . $d_type;
	}

	// sort by, and save in session
	if (isset($_POST['sort_by']) && in_array($_POST['sort_by'], $orders))
	{
		$_SESSION['d_order'] = $_POST['sort_by'];
	}

	if (!in_array($_SESSION['d_order'], $orders))
	{
		$_SESSION['d_order'] = $orders[0];
	}

	if (isset($_SESSION['d_order']))
	{
		list($sort, $type) = explode('-', $_SESSION['d_order']);
		$iaView->assign('sort_name', $sort);
		$iaView->assign('sort_type', $type);
		$order = ' `' . $sort . '` ' . $type;
	}

	$rssFeed = false;

	switch ($pageName)
	{
		case 'my_listings':

			if (!iaUsers::hasIdentity())
			{
				return iaView::errorPage(iaView::ERROR_UNAUTHORIZED);
			}

			$listings = $iaListing->get(' t3.`id` = ' . iaUsers::getIdentity()->id . ' ', $start, $per_page, $order);
			iaLanguage::set('no_web_listings', iaLanguage::get('no_my_listings'));

			break;

		case 'top_listings':
			$rssFeed = 'top';

			$listings = $iaListing->getTop($per_page, $start);
			iaLanguage::set('no_web_listings', iaLanguage::get('no_web_listings2'));

			break;

		case 'new_listings':
			$rssFeed = 'latest';

			$listings = $iaListing->getLatest($per_page, $start);
			iaLanguage::set('no_web_listings', iaLanguage::get('no_web_listings2'));

			break;

		case 'popular_listings':
			$rssFeed = 'popular';

			$listings = $iaListing->getPopular($per_page, $start);
			iaLanguage::set('no_web_listings', iaLanguage::get('no_web_listings2'));

			break;

		case 'random_listings':
			$listings = $iaListing->getRandom($per_page, $start);
			iaLanguage::set('no_web_listings', iaLanguage::get('no_web_listings2'));

			break;

		default:
			$categoryAlias = empty($iaCore->requestPath) ? false : implode(IA_URL_DELIMITER, $iaCore->requestPath) . IA_URL_DELIMITER;

			$rssFeed = empty($iaCore->requestPath) ? false : implode(IA_URL_DELIMITER, $iaCore->requestPath);

			$category = $iaDb->row_bind(array('id', 'child', 'parent_id', 'title', 'parents', 'description', 'meta_description', 'meta_keywords', 'title_alias'), '`title_alias`= :alias', array('alias' => $categoryAlias), 'categs');

			// requested category not found
			if ($categoryAlias && (-1 == $category['parent_id']))
			{
				return iaView::errorPage(iaView::ERROR_NOT_FOUND);
			}
			$iaView->set('subpage', $category['id']);

			// start breadcrumb
			$iaCateg = $iaCore->factoryPackage('categ', IA_CURRENT_PACKAGE);
			if ($category && trim($category['parents']))
			{
				$condition = "`id` IN({$category['parents']}) AND `parent_id` != -1 AND `status` = 'active'";
				$parents = $iaCateg->get($condition);

				foreach ($parents as $key => $parent)
				{
					$url = $iaCateg->url('default', $parent);

					if (IA_CURRENT_PACKAGE == $iaCore->get('default_package') && 0 == $key)
					{
						iaBreadcrumb::replaceEnd($parent['title'], $url);
					}
					else
					{
						iaBreadcrumb::toEnd($parent['title'], $url);
					}
				}
			}
			// end

			$children = (empty($category['child']) || empty($category['parents']) || !$iaCore->get('display_children_listing'))
				? $category['id']
				: $category['id'] . ',' . $category['child'];

			$iaCateg->incrementViewsCounter($category['id']);

			$listings = $iaListing->getByCategoryId($children, '', $start, $per_page, $order);

			if (-1 != $category['parent_id'])
			{
				$iaView->set('description', $category['meta_description']);
				$iaView->set('keywords', $category['meta_keywords']);
			}
			$iaView->assign('category', $category);

			if (isset($category) && -1 != $category['parent_id'] && isset($category['title']))
			{
				$iaView->title($category['title']);
			}
	}

	$totalListings = $iaDb->foundRows();

	iaLanguage::set('no_web_listings', str_replace('{%URL%}', IA_PACKAGE_URL . 'add/' . (isset($category) && $category ? '?category=' . $category['id'] : ''), iaLanguage::get('no_web_listings')));

	if ($listings)
	{
		$listings = $iaCore->factory('item')->updateItemsFavorites($listings, $iaListing->getItemName());

		$iaCore->factory('field')->filter($listings, $iaListing->getItemName());
	}

	if ($iaAcl->isAccessible('add_listing', iaCore::ACTION_ADD))
	{
		$pageActions[] = array(
			'icon' => 'plus-square',
			'title' => iaLanguage::get('add_listing'),
			'url' => IA_PACKAGE_URL . 'add/' . (empty($category['id']) ? '' : '?category=' . $category['id'])
		);
	}

	$rssFeed = $rssFeed ? $rssFeed . '.' . iaCore::EXTENSION_XML : 'latest.' . iaCore::EXTENSION_XML;

	if ('my_listings' != $pageName && 'random_listings' != $pageName)
	{
		$pageActions[] = array(
			'icon' => 'rss ',
			'title' => null,
			'url' => IA_PACKAGE_URL . 'rss/' . $rssFeed,
			'classes' => 'btn-warning'
		);
	}

	$iaView->set('actions', $pageActions);
	$iaView->set('filtersItemName', $iaListing->getItemName());

	$iaView->assign('rss_feed', $rssFeed);
	$iaView->assign('listings', $listings);
	$iaView->assign('items', $listings);
	$iaView->assign('aTotal', $totalListings);
	$iaView->assign('aItemsPerPage', $iaCore->get('directory_listings_perpage'));
	$iaView->assign('aTemplate', IA_SELF . '?page={page}');

	$iaView->display('index');
}

if (iaView::REQUEST_XML == $iaView->getRequestType())
{
	$stmt = '';
	$order = ' ORDER BY t1.`date_added` DESC';
	$limit = (int)$iaCore->get('directory_listings_perpage', 10);

	if (isset($iaCore->requestPath[0]) && $iaCore->requestPath[0] == 'top')
	{
		$listings = $iaListing->getTop($limit);
	}
	elseif (isset($iaCore->requestPath[0]) && $iaCore->requestPath[0] == 'latest')
	{
		$listings = $iaListing->getLatest($limit);
	}
	else
	{
		$stmt = "cat.`title_alias` = '" . (implode(IA_URL_DELIMITER, $iaCore->requestPath) . IA_URL_DELIMITER) . "'" . $stmt;
		$listings = $iaListing->get($stmt, 0, $limit);
	}

	$output = array(
		'title' => $iaCore->get('site'),
		'description' => '',
		'url' => IA_URL,
		'item' => array()
	);

	foreach ($listings as $listing)
	{
		$output['item'][] = array(
			'title' => $listing['title'],
			'guid' => $iaListing->url('view', $listing),
			'link' => $iaListing->url('view', $listing),
			'pubDate' => date('D, d M Y H:i:s T', strtotime($listing['date_added'])),
			'description' => iaSanitize::tags($listing['summary']),
			'category' => isset($category) ? $category : $listing['category_title']
		);
	}

	$iaView->assign('channel', $output);
}