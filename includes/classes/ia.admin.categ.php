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

	public $dashboardStatistics = array('icon' => 'folder');


	public static function getTableCrossed()
	{
		return self::$_tableCrossed;
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

		$where = '`status` = :status AND `parent_id` != -1 ORDER BY `level`, `title`';
		$this->iaDb->bind($where, array('status' => iaCore::STATUS_ACTIVE));

		if ($entries = $this->iaDb->all(array('title_alias'), $where, null, null, self::getTable()))
		{
			$baseUrl = $this->getInfo('url');

			foreach ($entries as $entry)
			{
				$result[] = $baseUrl . $entry['title_alias'];
			}
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
		$sql = <<<SQL
UPDATE `{$this->iaDb->prefix}categs` 
SET `num_listings` = IF(`id` = $categoryId, `num_listings` + {$aInt}, `num_listings`),
	`num_all_listings`=`num_all_listings` + {$aInt}
WHERE FIND_IN_SET({$categoryId}, `child`)
SQL;

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

				$sql = <<<SQL
SELECT COUNT(l.`id`) `num`
	FROM `{$this->iaDb->prefix}listings` l 
LEFT JOIN `{$this->iaDb->prefix}members` acc ON (l.`member_id` = acc.`id`) 
WHERE l.`status`= 'active' AND (acc.`status` = 'active' OR acc.`status` IS NULL) 
AND l.`category_id` = {$_id}
SQL;
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

	public function getCount()
	{
		return $this->iaDb->one(iaDb::STMT_COUNT_ROWS, null, self::getTable());
	}

	public function updateAliases($categoryId)
	{
		if (!$child = $this->getById($categoryId))
		{
			return;
		}

		foreach(explode(',', $child['child']) as $id)
		{
			if (!trim($id)) continue;
			$this->_updateAliasById($id);
		}
	}

	protected function _updateAliasById($categoryId)
	{
		$category = $this->getById($categoryId);
		$parent = $this->getById($category['parent_id']);

		$breadcrumbs = array();
		$baseUrl = str_replace(IA_URL, '', $this->getInfo('url'));

		if (!empty($parent['parents']))
		{
			$parents = $this->iaDb->all(array('title', 'title_alias'), "`id` IN({$parent['parents']}) AND `parent_id` != -1 AND `status` = 'active' ORDER BY `level`");

			foreach ($parents as $p)
				$breadcrumbs[$p['title']] = $baseUrl . $p['title_alias'];
		}

		$breadcrumbs[$category['title']] = str_replace(IA_URL, '', $baseUrl . $category['title_alias']);

		$values = array(
			//'title_alias' => $this->getTitleAlias($category, $parent),
			'breadcrumb' => serialize($breadcrumbs)
		);

		$this->iaDb->update($values, iaDb::convertIds($categoryId), null, self::getTable());
	}
}