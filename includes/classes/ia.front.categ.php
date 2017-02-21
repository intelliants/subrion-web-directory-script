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

class iaCateg extends abstractDirectoryDirectoryFront
{
	protected static $_table = 'categs';
	protected static $_tableCrossed = 'categs_crossed';

	protected $_itemName = 'categs';

	protected $_urlPatterns = [
		'default' => ':base:title_alias'
	];

	public $coreSearchEnabled = true;
	public $coreSearchOptions = [
		'regularSearchFields' => ['title']
	];


	public function url($action, array $data)
	{
		$baseUrl = ($this->getModuleName() == $this->iaCore->get('default_package'))
			? IA_URL
			: $this->iaCore->modulesData[$this->getModuleName()]['url'];
		$slug = isset($data['category_alias'])
			? $data['category_alias']
			: $data['title_alias'];

		return $baseUrl . $slug;
	}

	public static function getTableCrossed()
	{
		return self::$_tableCrossed;
	}

	public function get($where = '', $catId = '0', $start = 0, $limit = null, $fields = 'c.*', $order = null)
	{
		$where || $where = iaDb::EMPTY_CONDITION;
		$fields.= ', c.`num_all_listings` `num`';

		$sql = <<<SQL
(SELECT :fields, '0' `crossed` FROM `:prefix:table_categories` c 
	WHERE :where ORDER BY c.`level`, c.`title_:lang`) 
UNION ALL 
(SELECT :fields, '1' `crossed` FROM `:prefix:table_categories` c 
LEFT JOIN `:prefix:table_crossed_categories` cr ON (c.`id` = cr.`crossed_id`) 
WHERE cr.`category_id` = :id ORDER BY c.`title_:lang`) 
ORDER BY `:order`
SQL;
		$sql = iaDb::printf($sql, [
			'fields' => $fields,
			'prefix' => $this->iaDb->prefix,
			'table_categories' => self::getTable(),
			'table_crossed_categories' => self::getTableCrossed(),
			'id' => (int)$catId,
			'lang' => $this->iaCore->language['iso'],
			'where' => $where,
			'order' => $order ? $order : 'title_' . $this->iaCore->language['iso']
		]);

		$result = $this->iaDb->getAll($sql, $start, $limit);

		$this->_processValues($result);

		return $result;
	}

	/**
	 * Rebuild categories relations
	 * Fields will be updated: parents, child, level, title_alias
	 */
	public function rebuildRelation()
	{
		$table_flat = $this->iaDb->prefix . 'categs_flat';
		$table = self::getTable(true);

		$insert_second = 'INSERT INTO ' . $table_flat . ' (`parent_id`, `category_id`) SELECT t.`parent_id`, t.`id` FROM ' . $table . ' t WHERE t.`parent_id` != -1';
		$insert_first = 'INSERT INTO ' . $table_flat . ' (`parent_id`, `category_id`) SELECT t.`id`, t.`id` FROM ' . $table . ' t WHERE t.`parent_id` != -1';
		$update_level = 'UPDATE ' . $table . ' s SET `level` = (SELECT COUNT(`category_id`)-1 FROM ' . $table_flat . ' f WHERE f.`category_id` = s.`id`) WHERE s.`parent_id` != -1;';
		$update_child = 'UPDATE ' . $table . ' s SET `child` = (SELECT GROUP_CONCAT(`category_id`) FROM ' . $table_flat . ' f WHERE f.`parent_id` = s.`id` AND s.`parent_id` != -1);';
		$update_parent = 'UPDATE ' . $table . ' s SET `parents` = (SELECT GROUP_CONCAT(`parent_id`) FROM ' . $table_flat . ' f WHERE f.`category_id` = s.`id` AND f.`parent_id` != 0);';

		$num = 1;
		$count = 0;

		$iaDb = &$this->iaDb;
		$iaDb->truncate($table_flat);
		$iaDb->query($insert_first);
		$iaDb->query($insert_second);

		while($num > 0 && $count < 10)
		{
			$count++;
			$num = 0;
			$sql = 'INSERT INTO ' . $table_flat . ' (`parent_id`, `category_id`) '
					. 'SELECT DISTINCT t.`id`, h' . $count . '.`id` FROM ' . $table . ' t, ' . $table . ' h0 ';
			$where = ' WHERE h0.`parent_id` = t.`id` ';

			for($i = 1; $i <= $count; $i++)
			{
				$sql.= 'LEFT JOIN ' . $table . ' h' . $i . ' ON (h' . $i . '.`parent_id` = h' . ($i - 1) . '.`id`) ';
				$where.= ' AND h' . $i . '.`id` IS NOT NULL';
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
	 * Returns crossed categories array by a given listing id
	 *
	 * @param int $listingId listing id
	 *
	 * @return mixed
	 */
	public function getCrossedByListingId($listingId)
	{
		$this->iaCore->factoryModule('listing', $this->getModuleName());

		$sql = <<<SQL
SELECT c.`id`, c.`title_:lang` `title` 
	FROM `:prefix:table_categories` c, `:prefix:table_listings_categories` lc
WHERE c.`id` = lc.`category_id` AND lc.`listing_id` = :id
SQL;
		$sql = iaDb::printf($sql, [
			'prefix' => $this->iaDb->prefix,
			'table_categories' => self::getTable(),
			'table_listings_categories' => iaListing::getTableCrossed(),
			'lang' => $this->iaCore->language['iso'],
			'id' => (int)$listingId
		]);

		return $this->iaDb->getKeyValue($sql);
	}
}