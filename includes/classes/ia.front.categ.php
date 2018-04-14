<?php
/******************************************************************************
 *
 * Subrion Web Directory Script
 * Copyright (C) 2018 Intelliants, LLC <https://intelliants.com>
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

class iaCateg extends iaAbstractFrontHelperCategoryFlat
{
    protected static $_table = 'categs';
    protected static $_tableCrossed = 'categs_crossed';

    protected $_moduleName = 'directory';

    protected $_itemName = 'categ';

    public $coreSearchEnabled = true;
    public $coreSearchOptions = [
        'regularSearchFields' => ['title']
    ];


    public function getUrl(array $data)
    {
        $baseUrl = ($this->getModuleName() == $this->iaCore->get('default_package'))
            ? IA_URL
            : $this->iaCore->modulesData[$this->getModuleName()]['url'];

        $slug = isset($data['category_slug'])
            ? $data['category_slug']
            : $data['slug'];

        return $baseUrl . $slug;
    }

    public static function getTableCrossed()
    {
        return self::$_tableCrossed;
    }

    public function getBySlug($slug)
    {
        $where = '`status` = :status AND `slug` = :slug';
        $this->iaDb->bind($where, ['status' => iaCore::STATUS_ACTIVE, 'slug' => $slug]);

        return $this->getOne($where);
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

    protected function _processValues(&$rows, $singleRow = false, $fieldNames = [])
    {
        parent::_processValues($rows, $singleRow, ['breadcrumb']);
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
        $where = sprintf('c.`id` = lc.`category_id` AND lc.`listing_id` = %d', $listingId);

        return $this->_getCrossed($where);
    }

    public function getCrossedByIds($ids)
    {
        // sanitizing
        $array = [];
        foreach (explode(',', $ids) as $id) {
            if (trim($id)) {
                $array[] = (int)$id;
            }
        }

        $where = sprintf('c.`id` IN (%s)', implode(',', $array));

        return $this->_getCrossed($where);
    }

    protected function _getCrossed($where)
    {
        $this->iaCore->factoryItem('listing');

        $sql = <<<SQL
SELECT c.`id`, c.`title_:lang` `title` 
	FROM `:prefix:table_categories` c, `:prefix:table_listings_categories` lc
WHERE :where
SQL;
        $sql = iaDb::printf($sql, [
            'prefix' => $this->iaDb->prefix,
            'table_categories' => self::getTable(),
            'table_listings_categories' => iaListing::getTableCrossed(),
            'lang' => $this->iaCore->language['iso'],
            'where' => $where
        ]);

        return $this->iaDb->getKeyValue($sql);
    }
}
