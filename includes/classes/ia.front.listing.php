<?php
//##copyright##

class iaListing extends abstractDirectoryPackageFront
{
	protected static $_table = 'listings';
	protected static $_tableCrossed = 'listings_categs';

	protected $_itemName = 'listings';

	protected $_statuses = array(iaCore::STATUS_ACTIVE, iaCore::STATUS_APPROVAL, self::STATUS_BANNED, self::STATUS_SUSPENDED);

	public $coreSearchEnabled = true;
	public $coreSearchOptions = array(
		'tableAlias' => 't1',
		'columnAlias' => array(
			'date' => 'date_added'
		),
		'regularSearchStatements' => array("(t1.`title` LIKE '%:query%' OR t1.`domain` LIKE '%:query%') AND t1.`status` != 'banned'"),
		'customColumns' => array('keywords', 'c', 'sc')
	);

	private $_urlPatterns = array(
		'default' => ':base:action/:id/',
		'view' => ':base:category_alias:id:title_alias.html',
		'edit' => ':baseedit/:id/',
		'add' => ':baseadd/',
		'my' => ':iaurlprofile/listings/'
	);


	public static function getTableCrossed()
	{
		return self::$_tableCrossed;
	}

	public function url($action, array $data)
	{
		$data['base'] = $this->iaCore->packagesData[$this->getPackageName()]['url'];
		$data['iaurl'] = IA_URL;
		$data['action'] = $action;
		$data['category_alias'] = (!isset($data['category_alias']) ? '' : $data['category_alias']);
		$data['title_alias'] = (!isset($data['title_alias']) ? '' : '-' . $data['title_alias']);

		unset($data['title'], $data['category']);

		if (!isset($this->_urlPatterns[$action]))
		{
			$action = 'default';
		}

		return iaDb::printf($this->_urlPatterns[$action], $data);
	}

	public function get($where, $start = 0, $limit = 0, $order = false)
	{
		$sql = 'SELECT SQL_CALC_FOUND_ROWS t1.*, ';
		$sql .= 'cat.`title` `category_title`, cat.`title_alias` `category_alias`, `cat`.`parents` `category_parents`, ';
		$sql .= 't3.`fullname` `member`, t3.`username` `account_username` ';
		$sql .= 'FROM `' . self::getTable(true) . '` t1 ';
		$sql .= "LEFT JOIN `{$this->iaDb->prefix}categs` cat ON t1.`category_id` = cat.`id` ";
		$sql .= "LEFT JOIN `{$this->iaDb->prefix}members` t3 ON t1.`member_id` = t3.`id` ";

		$sql .= $where ? "WHERE ($where) AND t1.`status` != 'banned'" : "WHERE t1.`status` != 'banned'";
		$sql .= " ORDER BY `sponsored` DESC, `featured` DESC " . ($order ? ", $order " : '');
		$sql .= $start || $limit ? " LIMIT $start, $limit" : '';

		$rows = $this->iaDb->getAll($sql);

		return $this->_process($rows);
	}

	public function coreSearch($stmt, $start, $limit, $order)
	{
		$rows = $this->get($stmt, $start, $limit, $order);

		return array($this->iaDb->foundRows(), $rows);
	}

	public function coreSearchTranslateColumn($column, $value)
	{
		switch ($column)
		{
			case 'keywords':
				$fields = array('title', 'description', 'url');
				$value = "'%" . iaSanitize::sql($value) . "%'";

				$result = array();
				foreach ($fields as $fieldName)
				{
					$result[] = array('col' => ':column', 'cond' => 'LIKE', 'val' => $value, 'field' => $fieldName);
				}

				return $result;

			case 'c':
				$iaCateg = $this->iaCore->factoryPackage('categ', $this->getPackageName());

				$sql = sprintf('SELECT `id` FROM `%s` WHERE `parent_id` = %d', $iaCateg::getTable(true), $value);

				return array('col' => ':column', 'cond' => 'IN', 'val' => '(' . $sql . ')', 'field' => 'category_id');

			case 'sc':
				return array('col' => ':column', 'cond' => '=', 'val' => (int)$value, 'field' => 'category_id');
		}
	}

	public function accountActions($params)
	{
		return array($this->url(iaCore::ACTION_EDIT, $params['item']), '');
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
		$stmtWhere = 't1.`status` = :status AND t1.`member_id` = :member';
		$this->iaDb->bind($stmtWhere, array(
			'status' => iaCore::STATUS_ACTIVE,
			'member' => (int)$memberId
		));

		return array(
			'items' => $this->get($stmtWhere, $start, $limit),
			'total_number' => $this->iaDb->foundRows()
		);
	}

	public function postPayment($listingId, $plan)
	{
		iaCore::instance()->startHook('phpDirectoryListingSetPlan', array('transaction' => $listingId, 'plan' => $plan));

		return true;
	}

	public function getByCategoryId($cat_list, $where, $start = 0, $limit = 0, $order = false, $status = iaCore::STATUS_ACTIVE)
	{
		$tmp = explode(',', $cat_list);
		$cat_id = $tmp[0];
		$sql =
			'SELECT SQL_CALC_FOUND_ROWS  `li`.*,'
				. 'IF(li.`category_id`  IN( ' . $cat_list . ' ), li.`category_id`, cr.`category_id`) `category`, '
				. 'IF(li.`category_id` = ' . $cat_id . ', 0, 1) `crossed`, '
				. 'ca.`title` `category_title`, ca.`title_alias` `category_alias`, `ca`.`parents` `category_parents`, '
				. 'ac.`fullname` `member`, ac.`username` `account_username` '
			. 'FROM `' . $this->iaDb->prefix . 'categs` ca, ' . self::getTable(true) . ' li '
				. 'LEFT JOIN `' . $this->iaDb->prefix . 'listings_categs` cr ON (cr.`listing_id` = li.`id` AND cr.`category_id` = ' . $cat_id . ') '
				. 'LEFT JOIN `' . $this->iaDb->prefix . 'members` ac ON (ac.`id` = li.`member_id`) '
			. 'WHERE li.`status` = \'' . $status . '\' '
				. '&& (li.`category_id` IN( ' . $cat_list . ' ) OR cr.`category_id` is not NULL) && ca.`id` = li.`category_id` '
				. $where
				. ($order ? " ORDER BY `sponsored` DESC, `featured` DESC, $order " : '')
				. ($start || $limit ? " LIMIT $start, $limit " : '');

		$rows = $this->iaDb->getAll($sql);

		return $this->_process($rows);
	}

	public function getTitleAlias($title, $convertLowercase = false)
	{
		$title = iaSanitize::alias($title);

		if ($this->iaCore->get('directory_lowercase_urls', true) && !$convertLowercase)
		{
			$title = strtolower($title);
		}

		return $title;
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

		$rawValues = array(
			'date_added' => iaDb::FUNCTION_NOW,
			'date_modified' => iaDb::FUNCTION_NOW
		);

		if (isset($entryData['crossed_links']))
		{
			$crossed = $entryData['crossed_links'];

			unset($entryData['crossed_links']);
		}

		!$this->iaCore->get('directory_lowercase_urls') || $entryData['title_alias'] = strtolower($entryData['title_alias']);

		$entryData['id'] = $this->iaDb->insert($entryData, $rawValues, self::getTable());

		if ($entryData['id'])
		{
			if ($this->iaCore->get('listing_crossed'))
			{
				if (isset($crossed) && $crossed)
				{
					$this->iaDb->setTable(self::getTableCrossed());

					$crossedLimit = $this->iaCore->get('listing_crossed_limit', 5);
					$crossed = explode(',', $crossed);
					$count = count($crossed) > $crossedLimit ? $crossedLimit : count($crossed);
					$crossedInput = array();

					for ($i = 0; $i < $count; $i++)
					{
						if ($crossed[$i] != $entryData['category_id'])
						{
							$crossedInput[] = array('listing_id' => $entryData['id'], 'category_id' => (int)$crossed[$i]);
						}
					}

					if (count($crossedInput) > 0)
					{
						$this->iaDb->insert($crossedInput);
					}

					$this->iaDb->resetTable();
				}
			}

			// update category counter
			if (iaCore::STATUS_ACTIVE == $entryData['status'])
			{
				if ($crossed)
				{
					foreach ($crossedInput as $entry)
					{
						$this->_changeNumListing($entry['category_id'], 1);
					}
				}

				$this->_changeNumListing($entryData['category_id'], 1);
			}

			$this->_sendAdminNotification($entryData['id']);
		}

		return $entryData['id'];
	}

	/**
	 * Updates listing data
	 *
	 * @param array $listing new listing details
	 * @param array $oldData previous listing details
	 *
	 * @return mixed
	 */
	public function update(array $listing, array $oldData)
	{
		$iaDb = &$this->iaDb;
		$oldData = $oldData
			? $oldData
			: $iaDb->row(iaDb::ALL_COLUMNS_SELECTION, iaDb::convertIds($listing['id']), self::getTable());
		$status = isset($listing['status']) ? $listing['status'] : false;
		$categ = isset($listing['category_id']) ? $listing['category_id'] : $oldData['category_id'];
		if ($this->iaCore->get('listing_crossed'))
		{
			$crossed = $this->iaDb->onefield('category_id', "`listing_id` = '{$listing['id']}'", 0, null, self::getTableCrossed());

			if (isset($listing['crossed_links']))
			{
				$crossed = $listing['crossed_links'];

				unset($listing['crossed_links']);
			}

			if ($crossed)
			{
				$this->iaDb->setTable(self::getTableCrossed());

				$this->iaDb->delete("`listing_id` = '{$listing['id']}'");

				$crossedLimit = $this->iaCore->get('listing_crossed_limit', 5);

				if (!is_array($crossed))
				{
					$crossed = explode(',', $crossed);
				}

				$count = count($crossed) > $crossedLimit ? $crossedLimit : count($crossed);
				$crossedInput = array();

				for ($i = 0; $i < $count; $i++)
				{
					if ($crossed[$i] != $listing['category_id'])
					{
						$crossedInput[] = array('listing_id' => $listing['id'], 'category_id' => (int)$crossed[$i]);
					}
				}

				if (count($crossedInput) > 0)
				{
					$this->iaDb->insert($crossedInput);
				}

				$this->iaDb->resetTable();
			}
		}

		$return = $iaDb->update($listing, iaDb::convertIds($listing['id']), array('date_modified' => iaDb::FUNCTION_NOW), self::getTable());

		// If status changed
		if ($categ == $oldData['category_id'])
		{
			if (iaCore::STATUS_ACTIVE == $oldData['status'] && iaCore::STATUS_ACTIVE != $status)
			{
				$this->_changeNumListing($categ, -1);
			}
			elseif (iaCore::STATUS_ACTIVE != $oldData['status'] && iaCore::STATUS_ACTIVE == $status)
			{
				$this->_changeNumListing($categ, 1);
			}
		}
		else // If category changed
		{
			if (iaCore::STATUS_ACTIVE == $status)
			{
				$this->_changeNumListing($categ, 1);
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
				{
					$this->_changeNumListing($entry['category_id'], $diff);
				}
			}
		}

		return $return;
	}

	/**
	 * Delete listing record
	 *
	 * @param $listingData listing details
	 *
	 * @return bool
	 */
	public function delete($listingData)
	{
		$result = (bool)$this->iaDb->delete('`id` = :id', self::getTable(), array('id' => $listingData['id']));

		if ($result)
		{
			if ($this->iaCore->get('listing_crossed'))
			{
				$crossed = $this->iaDb->onefield('category_id', "`listing_id` = '{$listingData['id']}'", 0, null, self::getTableCrossed());

				foreach ($crossed as $ccid)
				{
					$this->_changeNumListing($ccid, -1);
				}

				$this->iaDb->delete("`listing_id` = '{$listingData['id']}'", self::getTableCrossed());
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
			$iaMailer->setReplacements(array(
				'title' => $listingData['title'],
				'url' => IA_ADMIN_URL . 'directory/listings/edit/' . $listingData['id']
			));

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
		$sql  = "UPDATE `{$this->iaDb->prefix}categs` ";
		// `num_listings` changed only for ONE category
		$sql .= "SET `num_listings`=IF(`id` = $categoryId, `num_listings` + {$increment}, `num_listings`) ";
		$sql .= ", `num_all_listings`=`num_all_listings` + {$increment} ";
		$sql .= "WHERE FIND_IN_SET({$categoryId}, `child`) ";

		return $this->iaDb->query($sql);
	}

	protected function _process($rows)
	{
		foreach ($rows as &$row)
		{
			$iaCateg = $this->iaCore->factoryPackage('categ', $this->getPackageName());

			if ($row['category_parents'])
			{
				$parents = $iaCateg->get("`id` IN({$row['category_parents']}) AND `parent_id` > -1");

				foreach ($parents as $parent)
				{
					$row['breadcrumb'][] = array(
						'title' => $parent['title'],
						'url' => $iaCateg->url('view', $parent)
					);
				}
			}
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
	public function getById($itemId)
	{
		$listings = $this->get("t1.`id` = '{$itemId}'", 0, 1);

		return $listings ? $listings[0] : array();
	}

	public function getTop($limit = 10, $start = 0)
	{
		return $this->get("t1.`status` = 'active'", $start, $limit, "t1.`rank` DESC");
	}

	public function getPopular($limit = 10, $start = 0)
	{
		return $this->get("t1.`status` = 'active'", $start, $limit, "t1.`views_num` DESC");
	}

	public function getLatest($limit = 10, $start = 0)
	{
		return $this->get("t1.`status` = 'active'", $start, $limit, "t1.`date_added` DESC");
	}

	public function getRandom($limit = 10, $start = 0)
	{
		return $this->get("t1.`status` = 'active'", $start, $limit, iaDb::FUNCTION_RAND);
	}

	public function isSubmissionAllowed($memberId)
	{
		$result = true;

		if (iaUsers::MEMBERSHIP_ADMINISTRATOR != iaUsers::getIdentity()->usergroup_id)
		{
			$listingCount = $this->iaDb->one_bind(iaDb::STMT_COUNT_ROWS, '`member_id` = :member', array('member' => $memberId), self::getTable());

			$result = ($listingCount < $this->iaCore->get('directory_listing_limit'));
		}

		return $result;
	}
}