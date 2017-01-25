<?php
//##copyright##

class iaListing extends abstractDirectoryPackageAdmin
{
	protected static $_table = 'listings';
	protected static $_tableCrossed = 'listings_categs';

	protected $_itemName = 'listings';

	protected $_statuses = array(iaCore::STATUS_ACTIVE, iaCore::STATUS_INACTIVE, iaCore::STATUS_APPROVAL, self::STATUS_BANNED, self::STATUS_SUSPENDED);

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

		unset($data['title'], $data['category']);

		if (!isset($this->_urlPatterns[$action]))
		{
			$action = 'default';
		}

		return iaDb::printf($this->_urlPatterns[$action], $data);
	}

	public function get($columns, $where, $order = '', $start = null, $limit = null)
	{
		$sql = <<<SQL
SELECT :columns, c.`title_:lang` `category_title`, c.`title_alias` `category_alias`, m.`fullname` `member` 
	FROM `:prefix:table_listings` l 
LEFT JOIN `:prefix:table_categories` c ON (l.`category_id` = c.`id`) 
LEFT JOIN `:prefix:table_members` m ON (l.`member_id` = m.`id`) 
WHERE :where :order
SQL;
		$sql .= $start || $limit ? ' LIMIT :start, :limit' : '';

		$sql = iaDb::printf($sql, array(
			'lang' => $this->iaCore->language['iso'],
			'prefix' => $this->iaDb->prefix,
			'table_listings' => $this->getTable(),
			'table_categories' => 'categs',
			'table_members' => iaUsers::getTable(),
			'columns' => $columns,
			'where' => $where,
			'order' => $order,
			'start' => $start,
			'limit' => $limit
		));

		return $this->iaDb->getAll($sql);
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
			$this->sendUserNotification($listingData);
		}

		return $result;
	}

	protected function _changeNumListing($categoryId, $aInt = 1)
	{
		$sql = <<<SQL
UPDATE `{$this->iaDb->prefix}categs` 
	SET `num_listings`=if (`id` = $categoryId, `num_listings` + {$aInt}, `num_listings`),
	`num_all_listings` = `num_all_listings` + {$aInt} 
WHERE FIND_IN_SET({$categoryId}, `child`) 
SQL;

		return $this->iaDb->query($sql);
	}

	public function sendUserNotification(array $listingData, $listingId = null)
	{
		if ($this->iaCore->get('listing_' . $listingData['status']))
		{
			$email = ($listingData['email']) ? $listingData['email'] : $this->iaDb->one('email', iaDb::convertIds($listingData['member_id']), iaUsers::getTable());

			if ($email)
			{
				if ($listingId)
				{
					$listing = $this->get('l.`id`, l.`title`, l.`title_alias`, l.`status` ', 'l.`id` = ' . $listingId);
					$listingData = is_array($listing) && $listing ? array_shift($listing) : $listingData;
				}
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

		$sql = <<<SQL
SELECT l.`category_id`, COUNT(l.`id`) 
	FROM `{$this->iaDb->prefix}listings` `l` 
LEFT JOIN `{$this->iaDb->prefix}members` `m` ON l.`member_id`= m.`id` 
WHERE l.`status`= 'active' AND (m.`status` = 'active' OR m.`status` IS NULL) 
GROUP BY l.`category_id`
SQL;
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

	public function getSitemapEntries()
	{
		$result = array();

		$stmt = 'l.`status` = :status';
		$this->iaDb->bind($stmt, array('status' => iaCore::STATUS_ACTIVE));

		if ($entries = $this->get('l.`title_alias`', $stmt, 'l.`date_modified` DESC'))
		{
			foreach ($entries as $entry)
			{
				$result[] = $this->url('view', $entry);
			}
		}

		return $result;
	}

	public function getDomain($aUrl = '')
	{
		if (preg_match('/^(?:http|https|ftp):\/\/((?:[A-Z0-9][A-Z0-9_-]*)(?:\.[A-Z0-9][A-Z0-9_-]*)+)(:(\d+))?/i', $aUrl, $m))
		{
			return $m[1];
		}

		return false;
	}
}