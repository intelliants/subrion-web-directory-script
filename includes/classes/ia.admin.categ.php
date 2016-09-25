<?php
//##copyright##

class iaCateg extends abstractDirectoryPackageAdmin
{
	protected static $_table = 'categs';
	protected $_tableFlat = 'categs_flat';
	protected static $_tableCrossed = 'categs_crossed';

	protected $_activityLog = array('item' => 'category');

	protected $_itemName = 'categs';
	protected $_statuses = array(iaCore::STATUS_ACTIVE, iaCore::STATUS_INACTIVE);

	protected $_moduleUrl = 'directory/categories/';

	private $_urlPatterns = array(
		'default' => ':base:title_alias'
	);

	public static function getTableCrossed()
	{
		return self::$_tableCrossed;
	}

	public function getLastId()
	{
		return $this->iaDb->one('MAX(`id`)', null, self::$_table);
	}

	public function url($action, $params)
	{
		$data = array();

		$data['base'] = IA_URL_DELIMITER != $this->getInfo('url') ? $this->getInfo('url') : '';
		$data['action'] = $action;

		if (isset($params['prefix']) && !empty($params['prefix']))
		{
			$data['title'] = $data[$params['prefix'] . 'title'];
			$data['title_alias'] = $data[$params['prefix'] . 'alias'];
		}

		$data['title_alias'] = (!isset($params['title_alias']) ? '' : $params['title_alias']);
		$data['title_alias'] = (!isset($params['category_alias']) ? $params['title_alias'] : $params['category_alias']);

		if (!isset($this->_urlPatterns[$action]))
		{
			$action = 'default';
		}

		return iaDb::printf($this->_urlPatterns[$action], $data);
	}

	public function exists($alias, $parentId, $id = null)
	{
		return is_null($id)
			? (bool)$this->iaDb->exists('`title_alias` = :alias AND `parent_id` = :parent', array('alias' => $alias, 'parent' => $parentId), self::getTable())
			: (bool)$this->iaDb->exists('`title_alias` = :alias AND `parent_id` = :parent AND `id` != :id', array('alias' => $alias, 'parent' => $parentId, 'id' => $id), self::getTable());
	}

	public function getRoot()
	{
		return $this->iaDb->row(iaDb::ALL_COLUMNS_SELECTION, '`parent_id` = -1', self::getTable());
	}

	public function getRootId()
	{
		return $this->iaDb->one(iaDb::ID_COLUMN_SELECTION, '`parent_id` = -1', self::getTable());
	}

	public function getSitemapEntries()
	{
		$result = array();

		$stmt = '`status` = :status AND `parent_id` != -1';
		$this->iaDb->bind($stmt, array('status' => iaCore::STATUS_ACTIVE));
		if ($entries = $this->get($stmt))
		{
			$baseUrl = $this->getInfo('url');

			foreach ($entries as $entry)
			{
				$result[] = $baseUrl . $entry['title_alias'];
			}
		}

		return $result;
	}

	public function get($where = '', $start = 0, $limit = null, $fields = '*')
	{
		$where || $where = iaDb::EMPTY_CONDITION;
		$fields .= ', `num_all_listings` `num`';

		return $this->iaDb->all($fields, $where . ' ORDER BY `level`, `title`', $start, $limit, self::getTable());
	}

	public function getCategory($aWhere, $aFields = '*')
	{
		return $this->iaDb->row($aFields, $aWhere, self::getTable());
	}

	public function update(array $itemData, $id)
	{
		$currentData = $this->getById($id);

		if (empty($currentData))
		{
			return false;
		}

		$result = $this->iaDb->update($itemData, iaDb::convertIds($id), null, self::getTable());

		if ($result)
		{
			$this->_writeLog(iaCore::ACTION_EDIT, $itemData, $id);

			$this->updateCounters($id, $itemData, iaCore::ACTION_EDIT, $currentData);

			$this->iaCore->startHook('phpListingUpdated', array(
				'itemId' => $id,
				'itemName' => $this->getItemName(),
				'itemData' => $itemData,
				'previousData' => $currentData
			));
		}

		return $result;
	}

	public function delete($itemId)
	{
		$result = parent::delete($itemId);

		if ($result)
		{
			$stmt = iaDb::convertIds($itemId, 'category_id');
			$this->iaDb->delete($stmt, 'listings_categs');

			$stmt = iaDb::convertIds($itemId, 'category_id');
			$this->iaDb->delete($stmt, self::getTableCrossed());

			$stmt = iaDb::convertIds($itemId, 'crossed_id');
			$this->iaDb->delete($stmt, self::getTableCrossed());

			$stmt = sprintf('`id` IN (SELECT `category_id` FROM `%s%s` WHERE `parent_id` = %d)',
			$this->iaDb->prefix, $this->_tableFlat, $itemId);

			$this->iaDb->delete($stmt, self::getTable());
			$this->iaDb->delete(iaDb::convertIds($itemId, 'parent_id'), $this->_tableFlat);
		}

		return $result;
	}

	public function updateCounters($itemId, array $itemData, $action, $previousData = null)
	{
		$this->rebuildRelation();
	}

	/**
	 * Rebuild categories relations
	 * Fields that will be updated: parents, child, level, title_alias
	 */
	public function rebuildRelation()
	{
		$tableFlat = $this->iaDb->prefix . 'categs_flat';
		$table = self::getTable(true);

		$insert_second = 'INSERT INTO ' . $tableFlat . ' (`parent_id`, `category_id`) SELECT t.`parent_id`, t.`id` FROM ' . $table . ' t WHERE t.`parent_id` != -1';
		$insert_first = 'INSERT INTO ' . $tableFlat . ' (`parent_id`, `category_id`) SELECT t.`id`, t.`id` FROM ' . $table . ' t WHERE t.`parent_id` != -1';
		$update_level = 'UPDATE ' . $table . ' s SET `level` = (SELECT COUNT(`category_id`)-1 FROM ' . $tableFlat . ' f WHERE f.`category_id` = s.`id`) WHERE s.`parent_id` != -1;';
		$update_child = 'UPDATE ' . $table . ' s SET `child` = (SELECT GROUP_CONCAT(`category_id`) FROM ' . $tableFlat . ' f WHERE f.`parent_id` = s.`id` AND s.`parent_id` != -1);';
		$update_parent = 'UPDATE ' . $table . ' s SET `parents` = (SELECT GROUP_CONCAT(`parent_id`) FROM ' . $tableFlat . ' f WHERE f.`category_id` = s.`id` AND f.`parent_id` != 0);';

		$num = 1;
		$count = 0;

		$iaDb = &$this->iaDb;
		$iaDb->truncate($tableFlat);
		$iaDb->query($insert_first);
		$iaDb->query($insert_second);

		while ($num > 0 && $count < 10)
		{
			$count++;
			$num = 0;
			$sql = 'INSERT INTO ' . $tableFlat . ' (`parent_id`, `category_id`) '
					. 'SELECT DISTINCT t.`id`, h' . $count . '.`id` FROM ' . $table . ' t, ' . $table . ' h0 ';
			$where = ' WHERE h0.`parent_id` = t.`id` ';

			for ($i = 1; $i <= $count; $i++)
			{
				$sql .= 'LEFT JOIN ' . $table . ' h' . $i . ' ON (h' . $i . '.`parent_id` = h' . ($i - 1) . '.`id`) ';
				$where .= ' AND h' . $i . '.`id` IS NOT NULL';
			}
			if ($iaDb->query($sql . $where))
			{
				$num = $iaDb->getAffected();
			}
		}

		$iaDb->query($update_level);
		$iaDb->query($update_child);
		$iaDb->query($update_parent);
	}

	public function changeNumListing($categoryId, $aInt = 1)
	{
		$sql =
			"UPDATE `{$this->iaDb->prefix}categs` " .
			"SET `num_listings` = IF(`id` = $categoryId, `num_listings` + {$aInt}, `num_listings`) " .
			", `num_all_listings`=`num_all_listings` + {$aInt} " .
			"WHERE FIND_IN_SET({$categoryId}, `child`) ";

		return $this->iaDb->query($sql);
	}

	public function recountListingsNum($start = 0, $limit = 10)
	{
		$this->iaDb->setTable(self::getTable());

		$categories = $this->iaDb->all(array('id', 'parent_id', 'child'), '1 ORDER BY `level` DESC', $start, $limit);
		foreach ($categories as $cat)
		{
			if (-1 != $cat['parent_id'])
			{
				$_id = $cat['id'];

				$sql  = 'SELECT COUNT(l.`id`) `num`';
				$sql .= "FROM `{$this->iaDb->prefix}listings` l ";
				$sql .= "LEFT JOIN `{$this->iaDb->prefix}members` acc ON (l.`member_id` = acc.`id`) ";
				$sql .= "WHERE l.`status`= 'active' AND (acc.`status` = 'active' OR acc.`status` IS NULL) ";
				$sql .= "AND l.`category_id` = {$_id}";

				$num_listings = $this->iaDb->getOne($sql);
				$_num_listings = $num_listings ? $num_listings : 0;
				$_num_all_listings = 0;

				if (!empty($cat['child']) && $cat['child'] != $cat['id'])
				{
					$_num_all_listings = $this->iaDb->one('SUM(`num_listings`)', "`id` IN ({$cat['child']})", iaCateg::getTable());
				}

				$_num_all_listings += $_num_listings;

				$crossed = $this->iaDb->one('COUNT(`category_id`) `num`', iaDb::convertIds($_id, 'category_id'), 'listings_categs');

				if ($crossed)
				{
					$_num_listings += $crossed;
					$_num_all_listings += $crossed;
				}

				$this->iaDb->update(array('num_listings' => $_num_listings, 'num_all_listings' => $_num_all_listings), iaDb::convertIds($_id));
			}
		}

		$this->iaDb->resetTable();

		return true;
	}

	public function clearListingsNum()
	{
		$this->iaDb->update(array('num_listings' => 0, 'num_all_listings' => 0), iaDb::EMPTY_CONDITION, self::getTable());
	}

	public function getCount()
	{
		return $this->iaDb->one(iaDb::STMT_COUNT_ROWS, null, self::getTable());
	}

	public function getTitleAlias($category, $parent = array())
	{
		if (-1 == $category['parent_id'])
		{
			return '';
		}

		$title = iaSanitize::alias($category['title_alias']);

		if ('category' == $title)
		{
			$id = $this->iaDb->getNextId();
			$title .= '-' . $id;
		}

		if (empty($parent) || $category['parent_id'] != $parent['id'])
		{
			$parent = $this->getCategory("`id` = {$category['parent_id']}", "`id`, `title_alias`");
		}

		$title = ltrim($parent['title_alias'] . $title . IA_URL_DELIMITER, IA_URL_DELIMITER);

		if ($this->iaCore->get('directory_lowercase_urls', true))
		{
			$title = strtolower($title);
		}

		return $title;
	}
}