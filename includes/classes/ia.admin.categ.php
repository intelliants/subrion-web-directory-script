<?php
//##copyright##

class iaCateg extends abstractDirectoryPackageAdmin
{
	protected static $_table = 'categs';
	protected $_tableFlat = 'categs_flat';
	protected static $_tableCrossed = 'categs_crossed';

	protected $_activityLog = ['item' => 'category'];

	protected $_itemName = 'categs';
	protected $_statuses = [iaCore::STATUS_ACTIVE, iaCore::STATUS_INACTIVE];

	private $_urlPatterns = [
		'default' => ':base:title_alias'
	];

	public $dashboardStatistics = ['icon' => 'folder', 'url' => 'directory/categories/'];


	public function getSitemapEntries()
	{
		$result = [];

		$where = '`status` = :status AND `parent_id` != -1 ORDER BY `level`, `title`';
		$this->iaDb->bind($where, ['status' => iaCore::STATUS_ACTIVE]);

		if ($entries = $this->iaDb->all(['title_alias'], $where, null, null, self::getTable()))
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
		if ($result = parent::delete($itemId))
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


	public function get($columns, $where, $order = '', $start = null, $limit = null)
	{
		$sql = <<<SQL
SELECT :columns, p.`title_:lang` `parent_title`
	FROM `:table_categories` c 
LEFT JOIN `:table_categories` p ON (c.`parent_id` = p.`id`) 
WHERE :where :order 
LIMIT :start, :limit
SQL;
		$sql = iaDb::printf($sql, [
			'lang' => $this->iaCore->language['iso'],
			'table_categories' => iaCateg::getTable(true),
			'columns' => $columns,
			'where' => $where,
			'order' => $order,
			'start' => $start,
			'limit' => $limit
		]);

		return $this->iaDb->getAll($sql);
	}

	public static function getTableCrossed()
	{
		return self::$_tableCrossed;
	}

	public function url($action, $params)
	{
		$data = [];

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
			? (bool)$this->iaDb->exists('`title_alias` = :alias AND `parent_id` = :parent', ['alias' => $alias, 'parent' => $parentId], self::getTable())
			: (bool)$this->iaDb->exists('`title_alias` = :alias AND `parent_id` = :parent AND `id` != :id', ['alias' => $alias, 'parent' => $parentId, 'id' => $id], self::getTable());
	}

	public function getRoot()
	{
		return $this->iaDb->row(iaDb::ALL_COLUMNS_SELECTION, '`parent_id` = -1', self::getTable());
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

		$iaDb->query('SET SESSION group_concat_max_len = 65535');

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

	/**
	 * Change category listings counter.
	 * Parent categories counter will be changed too.
	 *
	 * @param int $categoryId category Id
	 * @param int $increment
	 *
	 * @return mixed
	 */
	public function changeNumListing($categoryId, $increment = 1)
	{
		$sql = <<<SQL
UPDATE `:table_categs` 
SET `num_listings` = IF(`id` = :category, `num_listings` + :increment, `num_listings`),
	`num_all_listings` = `num_all_listings` + :increment
WHERE FIND_IN_SET(:category, `child`)
SQL;

		$sql = iaDb::printf($sql, [
			'table_categs' => self::getTable(true),
			'category' => (int)$categoryId,
			'increment' => (int)$increment
		]);

		return $this->iaDb->query($sql);
	}

	public function recountListingsNum($start = 0, $limit = 10)
	{
		$this->iaDb->setTable(self::getTable());

		$categories = $this->iaDb->all(['id', 'parent_id', 'child'], '1 ORDER BY `level` DESC', $start, $limit);
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

				$this->iaDb->update(['num_listings' => $_num_listings, 'num_all_listings' => $_num_all_listings], iaDb::convertIds($_id));
			}
		}

		$this->iaDb->resetTable();

		return true;
	}

	public function getCount()
	{
		return $this->iaDb->one(iaDb::STMT_COUNT_ROWS, null, self::getTable());
	}

	public function getSlug($title, $parentId = null, $parent = null)
	{
		if (-1 == $parentId)
		{
			return '';
		}

		$slug = iaSanitize::alias($title);

		if ('category' == $slug)
		{
			$id = $this->iaDb->getNextId(self::getTable());
			$slug.= '-' . $id;
		}

		$parent || $parent = $this->getById($parentId, false);

		$slug = ltrim($parent['title_alias'] . $slug . IA_URL_DELIMITER, IA_URL_DELIMITER);
		$this->iaCore->get('directory_lowercase_urls', true) && $slug = strtolower($slug);

		return $slug;
	}

	public function syncLinkingData($categoryId = null)
	{
		if (is_null($categoryId))
		{
			$root = $this->getRoot();
			$categoryId = $root['id'];
		}
		else
		{
			$category = $this->getById($categoryId, false);
			$parent = $this->getById($category['parent_id'], false);

			$this->_updateBreadcrumbs($category, $parent);

			$this->iaDb->update($category, iaDb::convertIds($categoryId), null, self::getTable());
		}

		$children = $this->iaDb->all(iaDb::ALL_COLUMNS_SELECTION, iaDb::convertIds($categoryId, 'parent_id'), null, null, self::getTable());

		foreach ($children as $child)
		{
			foreach (explode(',', $child['child']) as $id)
			{
				if (!trim($id) || (!$category = $this->getById($id, false))) continue;

				$parent = $this->getById($category['parent_id'], false);

				$this->_updateSlug($category, $parent);
				$this->_updateBreadcrumbs($category, $parent);

				$this->iaDb->update($category, iaDb::convertIds($id), null, self::getTable());
			}
		}
	}

	protected function _updateSlug(array &$category, array $parent)
	{
		$category['title_alias'] = $this->getSlug($category['title_' . $this->iaView->language], $category['parent_id'], $parent);
	}

	protected function _updateBreadcrumbs(array &$category, array $parent)
	{
		$breadcrumbs = [];

		$titleKey = 'title_' . $this->iaView->language;
		$baseUrl = str_replace(IA_URL, '', $this->getInfo('url'));

		if (!empty($parent['parents']))
		{
			$parents = $this->iaDb->all([$titleKey, 'title_alias'],
				"`id` IN({$parent['parents']}) && `parent_id` != -1 && `status` = 'active' ORDER BY `level`",
				null, null, self::getTable());

			foreach ($parents as $p)
				$breadcrumbs[$p[$titleKey]] = $baseUrl . $p['title_alias'];
		}

		$breadcrumbs[$category[$titleKey]] = $baseUrl . $category['title_alias'];

		$category['breadcrumb'] = serialize($breadcrumbs);
	}
}