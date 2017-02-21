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

class iaListing extends abstractDirectoryDirectoryFront
{
	protected static $_table = 'listings';
	protected static $_tableCrossed = 'listings_categs';

	protected $_itemName = 'listings';

	protected $_statuses = [iaCore::STATUS_ACTIVE, iaCore::STATUS_INACTIVE, iaCore::STATUS_APPROVAL, self::STATUS_BANNED, self::STATUS_SUSPENDED];

	public $coreSearchEnabled = true;
	public $coreSearchOptions = [
		'tableAlias' => 'l',
		'columnAlias' => ['date' => 'date_modified'],
		'regularSearchFields' => ['title', 'domain', 'description'],
		'customColumns' => ['keywords', 'c', 'sc']
	];

	private $_urlPatterns = [
		'default' => ':base:action/:id/',
		'view' => ':base:category_alias:id:title_alias.html',
		'edit' => ':baseedit/:id/',
		'add' => ':baseadd/',
		'my' => ':iaurlprofile/listings/'
	];

	protected $_foundRows = 0;

	private $_baseUrl = '';


	public function init()
	{
		parent::init();

		$this->_baseUrl = $this->getModuleName() == $this->iaCore->get('default_package')
			? IA_URL
			: $this->iaCore->modulesData[$this->getModuleName()]['url'];
	}

	public static function getTableCrossed()
	{
		return self::$_tableCrossed;
	}

	public function url($action, array $data)
	{
		$data['base'] = $this->_baseUrl . ('view' == $action ? 'listing/' : '');
		$data['iaurl'] = IA_URL;
		$data['action'] = $action;
		$data['category_alias'] = (!isset($data['category_alias']) ? '' : $data['category_alias']);
		$data['title_alias'] = (!isset($data['title_alias']) ? '' : '-' . $data['title_alias']);

		unset($data['title'], $data['category']);

		isset($this->_urlPatterns[$action]) || $action = 'default';

		return iaDb::printf($this->_urlPatterns[$action], $data);
	}

	public function getFoundRows()
	{
		return $this->_foundRows;
	}

	public function get($where, $start = null, $limit = null, $order = null, $prioritizedSorting = false)
	{
		$sql = 'SELECT SQL_CALC_FOUND_ROWS '
				. 'l.*, '
				. "c.`title_{$this->iaCore->language['iso']}` `category_title`, c.`title_alias` `category_alias`, c.`parents` `category_parents`, c.`breadcrumb` `category_breadcrumb`, "
				. 'm.`fullname` `member`, m.`username` `account_username` '
			. 'FROM `' . self::getTable(true) . '` l '
			. "LEFT JOIN `{$this->iaDb->prefix}categs` c ON (l.`category_id` = c.`id`) "
			. "LEFT JOIN `{$this->iaDb->prefix}members` m ON (l.`member_id` = m.`id`) "
			. 'WHERE ' . ($where ? $where . ' AND' : '') . " l.`status` != 'banned' "
			. 'ORDER BY ' . ($prioritizedSorting ? 'l.`sponsored` DESC, l.`featured` DESC, ' : '')
			. ($order ? $order : 'l.`date_modified` DESC') . ' '
			. ($start || $limit ? "LIMIT $start, $limit" : '');

		$rows = $this->iaDb->getAll($sql);
		$this->_foundRows = $this->iaDb->foundRows();

		$this->_processValues($rows);

		return $rows;
	}

	public function coreSearch($stmt, $start, $limit, $order)
	{
		$rows = $this->get($stmt, $start, $limit, $order, true);
		$count = $this->getFoundRows();

		$count || iaLanguage::set('no_web_listings2', iaLanguage::getf('no_web_listings2', ['url' => $this->getInfo('url') . 'add/']));

		return [$count, $rows];
	}

	public function coreSearchTranslateColumn($column, $value)
	{
		switch ($column)
		{
			case 'keywords':
				$lang = $this->iaView->language;

				$fields = ['title_' . $lang, 'description_' . $lang, 'url'];
				$value = "'%" . iaSanitize::sql($value) . "%'";

				$result = [];
				foreach ($fields as $fieldName)
				{
					$result[] = ['col' => ':column', 'cond' => 'LIKE', 'val' => $value, 'field' => $fieldName];
				}

				return $result;

			case 'c':
			case 'sc':
				$iaCateg = $this->iaCore->factoryModule('categ', $this->getModuleName());

				$child = $this->iaDb->one('child', iaDb::convertIds((int)$value), $iaCateg::getTable());

				if (!$child) // it's abnormal situation if the value is empty, it probably means that DB structure is not valid/updated
				{
					return ['col' => ':column', 'cond' => '=', 'val' => (int)$value, 'field' => 'category_id'];
				}

				return ['col' => ':column', 'cond' => 'IN', 'val' => '(' . $child . ')', 'field' => 'category_id'];
		}
	}

	public function accountActions($params)
	{
		return [$this->url(iaCore::ACTION_EDIT, $params['item']), ''];
	}

	/**
	 * Get member listings on View Member page
	 *
	 * @param int $memberId member id
	 * @param int $start
	 * @param int $limit
	 *
	 * @return array
	 */
	public function fetchMemberListings($memberId, $start, $limit)
	{
		$stmtWhere = 'l.`status` = :status AND l.`member_id` = :member';
		$this->iaDb->bind($stmtWhere, [
			'status' => iaCore::STATUS_ACTIVE,
			'member' => (int)$memberId
		]);

		return [
			'items' => $this->get($stmtWhere, $start, $limit),
			'total_number' => $this->iaDb->foundRows()
		];
	}

	public function postPayment($listingId, $plan)
	{
		iaCore::instance()->startHook('phpDirectoryListingSetPlan', ['transaction' => $listingId, 'plan' => $plan]);

		return true;
	}

	public function getByCategoryId($cat_list, $where, $start = 0, $limit = 0, $order = false, $status = iaCore::STATUS_ACTIVE)
	{
		$tmp = explode(',', $cat_list);
		$cat_id = $tmp[0];
		$sql =
			'SELECT SQL_CALC_FOUND_ROWS li.*,'
				. 'IF(li.`category_id` IN( ' . $cat_list . ' ), li.`category_id`, cr.`category_id`) `category`, '
				//. 'IF(li.`category_id` = ' . $cat_id . ', 0, 1) `crossed`, '
				. 'ca.`title_' . $this->iaCore->language['iso'] . '` `category_title`, ca.`title_alias` `category_alias`, ca.`parents` `category_parents`, ca.`breadcrumb` `category_breadcrumb`, '
				. 'ac.`fullname` `member`, ac.`username` `account_username` '
			. 'FROM `' . $this->iaDb->prefix . 'categs` ca, ' . self::getTable(true) . ' li '
				. 'LEFT JOIN `' . $this->iaDb->prefix . 'listings_categs` cr ON (cr.`listing_id` = li.`id` AND cr.`category_id` = ' . $cat_id . ') '
				. 'LEFT JOIN `' . $this->iaDb->prefix . 'members` ac ON (ac.`id` = li.`member_id`) '
			. 'WHERE li.`status` = \'' . $status . '\' '
				. '&& (li.`category_id` IN( ' . $cat_list . ' ) OR cr.`category_id` is not NULL) && ca.`id` = li.`category_id` '
				. $where
				. " ORDER BY `sponsored` DESC, `featured` DESC, $order "
				. ($start || $limit ? " LIMIT $start, $limit " : '');

		$rows = $this->iaDb->getAll($sql);
		$this->_foundRows = $this->iaDb->foundRows();

		$this->_processValues($rows);

		return $rows;
	}

	/**
	 * Returns domain name by a given URL
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	public function getDomain($url = '')
	{
		if (preg_match('/^(?:http|https|ftp):\/\/((?:[A-Z0-9][A-Z0-9_-]*)(?:\.[A-Z0-9][A-Z0-9_-]*)+)(:(\d+))?/i', $url, $m))
		{
			return $m[1];
		}

		return false;
	}

	public function checkDuplicateListings($listing)
	{
		$field = $this->iaCore->get('directory_duplicate_check_field');

		return $this->iaDb->one('id', iaDb::convertIds($listing[$field], $field), self::getTable()) ? $field : false;
	}

	public function insert(array $entryData)
	{
		$crossed = false;

		$rawValues = [
			'date_added' => iaDb::FUNCTION_NOW,
			'date_modified' => iaDb::FUNCTION_NOW
		];

		if (isset($entryData['crossed_links']))
		{
			$crossed = $entryData['crossed_links'];

			unset($entryData['crossed_links']);
		}

		$this->iaCore->get('directory_lowercase_urls') && $entryData['title_alias'] = strtolower($entryData['title_alias']);

		$entryData['id'] = $this->iaDb->insert($entryData, $rawValues, self::getTable());

		if ($entryData['id'])
		{
			if ($this->iaCore->get('listing_crossed'))
			{
				if ($crossed)
				{
					$this->iaDb->setTable(self::getTableCrossed());

					$crossedLimit = $this->iaCore->get('listing_crossed_limit', 5);
					$crossed = explode(',', $crossed);
					$count = count($crossed) > $crossedLimit ? $crossedLimit : count($crossed);
					$crossedInput = [];

					for ($i = 0; $i < $count; $i++)
					{
						if ($crossed[$i] != $entryData['category_id'])
						{
							$crossedInput[] = ['listing_id' => $entryData['id'], 'category_id' => (int)$crossed[$i]];
						}
					}

					$crossedInput && $this->iaDb->insert($crossedInput);

					$this->iaDb->resetTable();
				}
			}

			// update category counter
			if (iaCore::STATUS_ACTIVE == $entryData['status'])
			{
				if (!empty($crossedInput))
				{
					foreach ($crossedInput as $entry)
					{
						$this->_changeNumListing($entry['category_id']);
					}
				}

				$this->_changeNumListing($entryData['category_id']);
			}

			$this->_sendAdminNotification($entryData['id']);
		}

		return $entryData['id'];
	}

	public function update(array $listing, $id)
	{
		// prevent accidental update
		if (!$id) return false;

		$oldData = $this->getById($id);
		$status = isset($listing['status']) ? $listing['status'] : false;
		$categ = isset($listing['category_id']) ? $listing['category_id'] : $oldData['category_id'];

		if ($this->iaCore->get('listing_crossed'))
		{
			$crossed = $this->iaDb->onefield('category_id', iaDb::convertIds($id, 'listing_id'), 0, null, self::getTableCrossed());

			if (isset($listing['crossed_links']))
			{
				$crossed = $listing['crossed_links'];

				unset($listing['crossed_links']);
			}

			if ($crossed)
			{
				$this->iaDb->setTable(self::getTableCrossed());

				$this->iaDb->delete(iaDb::convertIds($id, 'listing_id'));

				$crossedLimit = $this->iaCore->get('listing_crossed_limit', 5);

				is_array($crossed) || $crossed = explode(',', $crossed);

				$count = count($crossed) > $crossedLimit ? $crossedLimit : count($crossed);
				$crossedInput = [];

				for ($i = 0; $i < $count; $i++)
				{
					if ($crossed[$i] != $listing['category_id'])
					{
						$crossedInput[] = ['listing_id' => $id, 'category_id' => (int)$crossed[$i]];
					}
				}

				$crossedInput && $this->iaDb->insert($crossedInput);

				$this->iaDb->resetTable();
			}
		}

		$return = $this->iaDb->update($listing, iaDb::convertIds($id), ['date_modified' => iaDb::FUNCTION_NOW], self::getTable());

		// If status changed
		if ($categ == $oldData['category_id'])
		{
			if (iaCore::STATUS_ACTIVE == $oldData['status'] && iaCore::STATUS_ACTIVE != $status)
			{
				$this->_changeNumListing($categ, -1);
			}
			elseif (iaCore::STATUS_ACTIVE != $oldData['status'] && iaCore::STATUS_ACTIVE == $status)
			{
				$this->_changeNumListing($categ);
			}
		}
		else // If category changed
		{
			if (iaCore::STATUS_ACTIVE == $status)
			{
				$this->_changeNumListing($categ);
			}
			if (iaCore::STATUS_ACTIVE == $oldData['status'])
			{
				$this->_changeNumListing($oldData['category_id'], -1);
			}
		}

		if ((iaCore::STATUS_ACTIVE == $oldData['status'] && iaCore::STATUS_ACTIVE != $status)
			|| (iaCore::STATUS_ACTIVE != $oldData['status'] && iaCore::STATUS_ACTIVE == $status))
		{
			if (isset($oldData['member_id']) && !isset($listing['member_id']))
			{
				$listing['member_id'] = $oldData['member_id'];
			}

			if (isset($oldData['title_alias']) && !isset($listing['title_alias']))
			{
				$listing['title_alias'] = $oldData['title_alias'];
			}

			if (isset($oldData['category_alias']) && !isset($listing['category_alias']))
			{
				$listing['category_alias'] = $oldData['category_alias'];
			}

			if ($crossed)
			{
				$diff = ($status == iaCore::STATUS_ACTIVE) ? 1 : -1;
				foreach ($crossedInput as $entry)
					$this->_changeNumListing($entry['category_id'], $diff);
			}
		}

		return $return;
	}

	/**
	 * Delete listing record
	 *
	 * @param array $listingData listing details
	 *
	 * @return bool
	 */
	public function delete($listingData)
	{
		$result = (bool)$this->iaDb->delete('`id` = :id', self::getTable(), ['id' => $listingData['id']]);

		if ($result)
		{
			if ($this->iaCore->get('listing_crossed'))
			{
				$crossed = $this->iaDb->onefield('category_id', "`listing_id` = '{$listingData['id']}'", 0, null, self::getTableCrossed());

				foreach ($crossed as $ccid)
					$this->_changeNumListing($ccid, -1);

				$this->iaDb->delete(iaDb::convertIds($listingData['id'], 'listing_id'), self::getTableCrossed());
			}

			$this->_changeNumListing($listingData['category_id'], -1);
		}

		return $result;
	}

	/**
	 * Sends email notification to administrator once a new listing is created
	 *
	 * @param int $listingId listing id
	 */
	protected function _sendAdminNotification($listingId)
	{
		if ($this->iaCore->get('new_listing'))
		{
			$listingData = $this->getById($listingId);
			$iaMailer = $this->iaCore->factory('mailer');

			$iaMailer->loadTemplate('new_listing');
			$iaMailer->setReplacements([
				'title' => $listingData['title'],
				'url' => IA_ADMIN_URL . 'directory/listings/edit/' . $listingData['id']
			]);

			$iaMailer->sendToAdministrators();
		}
	}

	/**
	 * Change category listings counter.
	 * Parent categories counter will be changed too.
	 *
	 * @param int $categoryId category Id
	 * @param int $increment
	 *
	 * @return mixed
	 */
	protected function _changeNumListing($categoryId, $increment = 1)
	{
		$sql = <<<SQL
UPDATE `:table_categs` 
SET `num_listings` = IF(`id` = :category, `num_listings` + :increment, `num_listings`),
	`num_all_listings` = `num_all_listings` + :increment
WHERE FIND_IN_SET(:category, `child`)
SQL;

		$sql = iaDb::printf($sql, [
			'table_categs' => $this->iaDb->prefix . 'categs',
			'category' => (int)$categoryId,
			'increment' => (int)$increment
		]);

		return $this->iaDb->query($sql);
	}

	protected function _processValues(&$rows, $singleRow = false, $fieldNames = [])
	{
		parent::_processValues($rows, $singleRow, $fieldNames);

		if ($rows)
		{
			foreach ($rows as &$row)
				$row['breadcrumb'] = empty($row['category_breadcrumb']) ? [] : unserialize($row['category_breadcrumb']);
		}

		return $rows;
	}

	/**
	 * Get listing details by id
	 *
	 * @param int $itemId listing id
	 *
	 * @return array
	 */
	public function getById($itemId, $decorate = true)
	{
		$listings = $this->get('l.`id` = ' . (int)$itemId, 0, 1);

		return $listings ? $listings[0] : [];
	}

	public function getTop($limit = 10, $start = 0)
	{
		return $this->get("l.`status` = 'active'", $start, $limit, 'l.`rank` DESC');
	}

	public function getPopular($limit = 10, $start = 0)
	{
		return $this->get("l.`status` = 'active'", $start, $limit, 'l.`views_num` DESC');
	}

	public function getLatest($limit = 10, $start = 0)
	{
		return $this->get("l.`status` = 'active'", $start, $limit, 'l.`date_added` DESC');
	}

	public function getRandom($limit = 10, $start = 0)
	{
		return $this->get("l.`status` = 'active'", $start, $limit, iaDb::FUNCTION_RAND);
	}

	public function isSubmissionAllowed($memberId)
	{
		$result = true;

		if (iaUsers::MEMBERSHIP_ADMINISTRATOR != iaUsers::getIdentity()->usergroup_id)
		{
			$listingCount = $this->iaDb->one_bind(iaDb::STMT_COUNT_ROWS, '`member_id` = :member', ['member' => $memberId], self::getTable());

			$result = ($listingCount < $this->iaCore->get('directory_listing_limit'));
		}

		return $result;
	}
}