<?php
//##copyright##

class iaListing extends abstractDirectoryPackageAdmin
{
	protected static $_table = 'listings';
	protected static $_tableCrossed = 'listings_categs';

	protected $_activityLog = true;

	protected $_itemName = 'listings';

	protected $_statuses = array(iaCore::STATUS_ACTIVE, iaCore::STATUS_APPROVAL, self::STATUS_BANNED, self::STATUS_SUSPENDED);

	private $_urlPatterns = array(
		'default' => ':base:action/:id/',
		'view' => ':base:category_alias:id:title_alias.html',
		'edit' => ':baseedit/:id/',
		'add' => ':baseadd/',
		'my' => ':baseprofile/listings/'
	);

	public $dashboardStatistics = array('icon' => 'link');


	public static function getTableCrossed()
	{
		return self::$_tableCrossed;
	}

	public function url($action, array $data)
	{
		$data['base'] = $this->getInfo('url');
		$data['action'] = $action;
		$data['category_alias'] = (!isset($data['category_alias']) ? '' : $data['category_alias']);
		$data['title_alias'] = (!isset($data['title_alias']) ? '' : '-' . $data['title_alias']);

		unset($data['title']);
		unset($data['category']);

		if (!isset($this->_urlPatterns[$action]))
		{
			$action = 'default';
		}

		return iaDb::printf($this->_urlPatterns[$action], $data);
	}

	private function _trim($text, $len = 600)
	{
		if (strlen($text) > $len)
		{
			return iaSanitize::snippet($text);
		}

		return $text;
	}

	public function insert(array $entryData)
	{
		$crossed = false;

		$entryData['date_added'] = date(iaDb::DATETIME_FORMAT);
		$entryData['date_modified'] = date(iaDb::DATETIME_FORMAT);

		if (isset($entryData['description']))
		{
			$entryData['short_description'] = $this->_trim($entryData['description']);
		}

		if (isset($entryData['crossed_links']))
		{
			$crossed = $entryData['crossed_links'];
			unset($entryData['crossed_links']);
		}

		$entryId = parent::insert($entryData);

		if ($entryId)
		{
			if ($this->iaCore->get('listing_crossed'))
			{
				if (isset($crossed) && !empty($crossed))
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
							$crossedInput[] = array('listing_id' => $entryId, 'category_id' => (int)$crossed[$i]);
						}
					}

					if (count($crossedInput) > 0)
					{
						$this->iaDb->insert($crossedInput);
					}

					$this->iaDb->resetTable();
				}
			}
		}

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

		return $entryId;
	}

	public function update(array $entryData, $id)
	{
		$crossed = false;

		$aOldListing = $this->iaDb->row(iaDb::ALL_COLUMNS_SELECTION, iaDb::convertIds($id), self::getTable());
		$status = isset($entryData['status']) ? $entryData['status'] : false;
		$categ = isset($entryData['category_id']) ? $entryData['category_id'] : $aOldListing['category_id'];

		if ($this->iaCore->get('listing_crossed'))
		{
			$crossed = $this->iaDb->onefield('category_id', iaDb::convertIds($id, 'listing_id'), 0, null, self::getTableCrossed());

			if (isset($entryData['crossed_links']))
			{
				$crossed = $entryData['crossed_links'];
				unset($entryData['crossed_links']);
			}

			if (isset($crossed) && $crossed)
			{
				$this->iaDb->setTable(self::getTableCrossed());

				$this->iaDb->delete(iaDb::convertIds($id, 'listing_id'));

				$crossedLimit = $this->iaCore->get('listing_crossed_limit', 5);

				if (!is_array($crossed))
				{
					$crossed = explode(',', $crossed);
				}

				$count = count($crossed) > $crossedLimit ? $crossedLimit : count($crossed);
				$crossedInput = array();

				for ($i = 0; $i < $count; $i++)
				{
					if ($crossed[$i] != $entryData['category_id'])
					{
						$crossedInput[] = array('listing_id' => $id, 'category_id' => (int)$crossed[$i]);
					}
				}

				if (count($crossedInput) > 0)
				{
					$this->iaDb->insert($crossedInput);
				}

				$this->iaDb->resetTable();
			}
		}
		if (isset($entryData['description']))
		{
			$entryData['short_description'] = $this->_trim($entryData['description']);
		}

		$entryData['date_modified'] = date(iaDb::DATETIME_FORMAT);

		$result = parent::update($entryData, $id);

		// If status changed
		if ($categ == $aOldListing['category_id'])
		{
			if (iaCore::STATUS_ACTIVE == $aOldListing['status'] && iaCore::STATUS_ACTIVE != $status)
			{
				$this->_changeNumListing($categ, -1);
			}
			elseif (iaCore::STATUS_ACTIVE != $aOldListing['status'] && iaCore::STATUS_ACTIVE == $status)
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
			if (iaCore::STATUS_ACTIVE == $aOldListing['status'])
			{
				$this->_changeNumListing($aOldListing['category_id'], -1);
			}
		}

		if ((iaCore::STATUS_ACTIVE == $aOldListing['status'] && iaCore::STATUS_ACTIVE != $status)
			|| (iaCore::STATUS_ACTIVE != $aOldListing['status'] && iaCore::STATUS_ACTIVE == $status))
		{
			if (isset($aOldListing['member_id']) && !isset($entryData['member_id']))
			{
				$entryData['member_id'] = $aOldListing['member_id'];
			}

			if (isset($aOldListing['title_alias']) && !isset($entryData['title_alias']))
			{
				$entryData['title_alias'] = $aOldListing['title_alias'];
			}

			if (isset($aOldListing['category_alias']) && !isset($entryData['category_alias']))
			{
				$entryData['category_alias'] = $aOldListing['category_alias'];
			}

			if (isset($aOldListing['title']) && !isset($entryData['title']))
			{
				$entryData['title'] = $aOldListing['title'];
			}
			$entryData['email'] = (isset($aOldListing['email']) && $aOldListing['email']) ? $aOldListing['email'] : '';

			if ($crossed)
			{
				$diff = (iaCore::STATUS_ACTIVE == $status) ? 1 : -1;

				foreach ($crossedInput as $entry)
				{
					$this->_changeNumListing($entry['category_id'], $diff);
				}
			}
			$entryData['id'] = $id;
			$this->_sendUserNotification($entryData);
		}

		return $result;
	}

	public function delete($listingId)
	{
		$listingData = $this->getById($listingId);
		$result = parent::delete($listingId);

		if ($result)
		{
			if ($this->iaCore->get('listing_crossed'))
			{
				$stmt = iaDb::convertIds($listingId, 'listing_id');
				$crossed = $this->iaDb->onefield('category_id', $stmt, 0, null, self::getTableCrossed());

				foreach ($crossed as $ccid)
				{
					$this->_changeNumListing($ccid, -1);
				}

				$this->iaDb->delete($stmt, self::getTableCrossed());
			}

			$this->_changeNumListing($listingData['category_id'], -1);

			$listingData['status'] = 'removed';
			$this->_sendUserNotification($listingData);
		}

		return $result;
	}

	protected function _changeNumListing($aCatId, $aInt = 1)
	{
		$sql  = "UPDATE `{$this->iaDb->prefix}categs` ";
		// `num_listings` changed only for ONE category
		$sql .= "SET `num_listings`=if (`id` = $aCatId, `num_listings` + {$aInt}, `num_listings`) ";
		$sql .= ", `num_all_listings` = `num_all_listings` + {$aInt} ";
		$sql .= "WHERE FIND_IN_SET({$aCatId}, `child`) ";

		return $this->iaDb->query($sql);
	}

	protected function _sendUserNotification(array $listingData)
	{
		if ($this->iaCore->get('listing_' . $listingData['status']))
		{
			$email = ($listingData['email']) ? $listingData['email'] : $this->iaDb->one('email', iaDb::convertIds($listingData['member_id']), iaUsers::getTable());

			if ($email)
			{
				$iaMailer = $this->iaCore->factory('mailer');

				$iaMailer->loadTemplate('listing_' . $listingData['status']);
				$iaMailer->setReplacements(array(
					'title' => $listingData['title'],
					'url' => $this->url('view', $listingData)
				));
				$iaMailer->addAddress($email);

				return $iaMailer->send();
			}
		}

		return false;
	}


	public function recountListingsNum()
	{
		$this->iaDb->setTable('categs');
		$rows = $this->iaDb->all(array('id', 'parent_id', 'child'));

		$categories = array();
		foreach ($rows as $c)
		{
			$c['child'] = explode(',', $c['child']);
			$categories[$c['id']] = $c;
		}
		unset($rows);

		$sql  = "SELECT art.`category_id`, COUNT(art.`id`) ";
		$sql .= "FROM `{$this->iaDb->prefix}listings` AS art ";
		$sql .= "LEFT JOIN `{$this->iaDb->prefix}members` AS acc ON art.`member_id`=acc.`id` ";
		$sql .= "WHERE art.`status`= 'active' AND (acc.`status` = 'active' OR acc.`status` IS NULL) ";
		$sql .= "GROUP BY art.`category_id` ";
		$num_listings = $this->iaDb->getKeyValue($sql);

		foreach ($categories AS $cat)
		{
			$_id = $cat['id'];
			$_num_listings = isset($num_listings[$_id]) ? $num_listings[$_id] : 0;
			$_num_all_listings = 0;

			if (!empty($cat['child']))
			{
				foreach ($cat['child'] AS $i)
				{
					$_num_all_listings += isset($num_listings[$i]) ? $num_listings[$i] : 0;
				}
			}

			$this->iaDb->update(array("num_listings" => $_num_listings, "num_all_listings" => $_num_all_listings), "`id` = '{$_id}' LIMIT 1");
		}

		$crossed = $this->iaDb->all('`category_id`, COUNT(`category_id`) `num`', '1 GROUP BY `category_id`', 0, null, 'listings_categs');

		foreach ($crossed as $cc)
		{
			$this->_changeNumListing($cc['category_id'], $cc['num']);
		}

		$this->iaDb->resetTable();

	}

	public function getById($aId)
	{
		$sql = "SELECT t1.*, ";
		$sql .= "if (t2.`fullname` <> '', t2.`fullname`, t2.`username`) `member` ";
		$sql .= "FROM `" . self::getTable(true) . "` t1 ";
		$sql .= "LEFT JOIN `{$this->iaDb->prefix}members` t2 ON (t1.`member_id` = t2.`id`) ";
		$sql .= "WHERE t1.`id` = '{$aId}'";

		return $this->iaDb->getRow($sql);
	}

	public function get($aWhere = null, $aStart = 0, $aLimit = '', $aOrder = '',
		$fields = 't1.`id`, t1.`title`, t1.`title_alias`, t1.`reported_as_broken`, t1.`reported_as_broken_comments`, t1.`date_added`, t1.`date_modified`, t1.`status`')
	{
		$sql = "SELECT SQL_CALC_FOUND_ROWS $fields, '1' `update`, '1' `delete`, ";
		$sql .= "t2.`title` `category_title`, t2.`title_alias` `category_alias`, ";
		$sql .= "if (t3.`fullname` <> '', t3.`fullname`, t3.`username`) `member` ";
		$sql .= "FROM `" . self::getTable(true) . "` t1 ";
		$sql .= "LEFT JOIN `{$this->iaDb->prefix}categs` t2 ";
		$sql .= "ON t1.`category_id` = t2.`id` ";
		$sql .= "LEFT JOIN `{$this->iaDb->prefix}members` t3 ";
		$sql .= "ON t1.`member_id` = t3.`id` ";
		$sql .= $aWhere ? "WHERE $aWhere " : '';
		$sql .= $aOrder ? " ORDER BY $aOrder " : '';
		$sql .= $aStart || $aLimit ? " LIMIT $aStart, $aLimit " : '';

		return $this->iaDb->getAll($sql);
	}

	public function getSitemapEntries()
	{
		$result = array();

		$stmt = 't1.`status` = :status';
		$this->iaDb->bind($stmt, array('status' => iaCore::STATUS_ACTIVE));
		if ($entries = $this->get($stmt, null, null, 't1.`date_modified` DESC'))
		{
			foreach ($entries as $entry)
			{
				$result[] = $this->url('view', $entry);
			}
		}

		return $result;
	}

	public function titleAlias($title, $convertLowercase = false)
	{
		$title = iaSanitize::alias($title);

		if ($this->iaCore->get('directory_lowercase_urls', true) && !$convertLowercase)
		{
			$title = strtolower($title);
		}

		return $title;
	}

	public function getDomain($aUrl = '')
	{
		if (preg_match('/^(?:http|https|ftp):\/\/((?:[A-Z0-9][A-Z0-9_-]*)(?:\.[A-Z0-9][A-Z0-9_-]*)+)(:(\d+))?/i', $aUrl, $m))
		{
			return $m[1];
		}

		return false;
	}

	public function gridRead($params, array $filterParams = array(), array $persistentConditions = array())
	{
		$params || $params = array();

		$start = isset($params['start']) ? (int)$params['start'] : 0;
		$limit = isset($params['limit']) ? (int)$params['limit'] : 15;

		$sort = $params['sort'];
		$dir = in_array($params['dir'], array(iaDb::ORDER_ASC, iaDb::ORDER_DESC)) ? $params['dir'] : iaDb::ORDER_ASC;
		$order = ($sort && $dir) ? "`{$sort}` {$dir}" : '';

		$where = $values = array();
		foreach ($filterParams as $name => $type)
		{
			if (isset($params[$name]) && $params[$name])
			{
				$value = iaSanitize::sql($params[$name]);

				switch ($type)
				{
					case 'equal':
						$where[] = sprintf('t1.`%s` = :%s', $name, $name);
						$values[$name] = $value;
						break;
					case 'like':
						$where[] = sprintf('t1.`%s` LIKE :%s', $name, $name);
						$values[$name] = '%' . $value . '%';
				}
			}
		}

		$where = array_merge($where, $persistentConditions);
		$where || $where[] = iaDb::EMPTY_CONDITION;
		$where = implode(' AND ', $where);
		$this->iaDb->bind($where, $values);

		return array(
			'data' => $this->get($where, $start, $limit, $order),
			'total' => (int)$this->iaDb->foundRows()
		);
	}
}