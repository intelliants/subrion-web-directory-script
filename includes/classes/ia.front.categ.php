<?php
//##copyright##

class iaCateg extends abstractDirectoryPackageFront
{
	protected static $_table = 'categs';

	protected $_itemName = 'categs';

	protected $_urlPatterns = array(
		'default' => ':base:title_alias'
	);

	public $coreSearchEnabled = true;
	public $coreSearchOptions = array(
		'regularSearchStatements' => array("`title` LIKE '%:query%'"),
	);


	public function url($action, $params)
	{
		$data = array();

		$data['base'] = $this->iaCore->packagesData[$this->getPackageName()]['url'];
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

	public function getCategory($aWhere, $aFields = '*')
	{
		return $this->iaDb->row($aFields, $aWhere, self::getTable());
	}

	public function get($where = '', $aStart = 0, $aLimit = null, $fields = '*')
	{
		if (empty($where))
		{
			$where = iaDb::EMPTY_CONDITION;
		}
		$fields .= ', `num_all_listings` `num`';
		$return = $this->iaDb->all($fields, $where . ' ORDER BY `level`, `title`', $aStart, $aLimit, self::getTable());

		if ($return)
		{
			foreach ($return as &$entry)
			{
				empty($entry['icon']) || $entry['icon'] = unserialize($entry['icon']);
			}
		}

		return $return;
	}

	/*
	 * Rebuild categories relations.
	 * Filds will be updated: parents, child, level, title_alias
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
				$sql .= 'LEFT JOIN ' . $table . ' h' . $i . ' ON (h' . $i . '.`parent_id` = h' . ($i - 1) . '.`id`) ';
				$where .= ' AND h' . $i . '.`id` is not null';
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
	 * @param int $id listing id
	 *
	 * @return mixed
	 */
	public function getCrossed($id)
	{
		return $this->iaDb->getAll("SELECT t.`id`, t.`title`
			FROM `{$this->iaDb->prefix}categs` t, `{$this->iaDb->prefix}listings_categs` cr
			WHERE t.`id` = cr.`category_id` AND cr.`listing_id` = '{$id}'");
	}
}