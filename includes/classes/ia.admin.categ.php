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

class iaCateg extends iaAbstractHelperCategoryHybrid
{
    protected static $_table = 'categs';
    protected $_tableFlat = 'categs_flat';
    protected static $_tableCrossed = 'categs_crossed';

    protected $_moduleName = 'directory';

    protected $_itemName = 'categs';
    protected $_statuses = [iaCore::STATUS_ACTIVE, iaCore::STATUS_INACTIVE];

    protected $_flatStructureEnabled = true;

    protected $_activityLog = ['item' => 'category'];

    private $_urlPatterns = [
        'default' => ':base:title_alias'
    ];

    public $dashboardStatistics = ['icon' => 'folder', 'url' => 'directory/categories/'];


    public function getSitemapEntries()
    {
        $result = [];

        $where = '`status` = :status AND `parent_id` != -1 ORDER BY `level`, `title`';
        $this->iaDb->bind($where, ['status' => iaCore::STATUS_ACTIVE]);

        if ($entries = $this->iaDb->all(['title_alias'], $where, null, null, self::getTable())) {
            $baseUrl = $this->getInfo('url');

            foreach ($entries as $entry) {
                $result[] = $baseUrl . $entry['title_alias'];
            }
        }

        return $result;
    }

    public function delete($itemId)
    {
        if ($result = parent::delete($itemId)) {
            $stmt = iaDb::convertIds($itemId, 'category_id');
            $this->iaDb->delete($stmt, 'listings_categs');

            $stmt = iaDb::convertIds($itemId, 'category_id');
            $this->iaDb->delete($stmt, self::getTableCrossed());

            $stmt = iaDb::convertIds($itemId, 'crossed_id');
            $this->iaDb->delete($stmt, self::getTableCrossed());

            $stmt = sprintf('`id` IN (SELECT `category_id` FROM `%s%s` WHERE `parent_id` = %d)',
            $this->iaDb->prefix, $this->_tableFlat, $itemId);

            $this->iaDb->delete($stmt, self::getTable());
            $this->iaDb->delete(iaDb::convertIds($itemId, self::COL_PARENT_ID), $this->_tableFlat);
        }

        return $result;
    }

    public function get($columns, $where, $order = '', $start = null, $limit = null)
    {
        $sql = <<<SQL
SELECT :columns, p.`title_:lang` `parent_title`
	FROM `:table_categories` c 
LEFT JOIN `:table_categories` p ON (c.`:col_pid` = p.`id`) 
WHERE :where :order 
LIMIT :start, :limit
SQL;
        $sql = iaDb::printf($sql, [
            'lang' => $this->iaCore->language['iso'],
            'table_categories' => iaCateg::getTable(true),
            'columns' => $columns,
            'col_pid' => self::COL_PARENT_ID,
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

        $data['title_alias'] = isset($params['title_alias']) ? $params['title_alias'] : '';
        $data['title_alias'] = isset($params['category_alias']) ? $params['category_alias'] : $params['title_alias'];

        isset($this->_urlPatterns[$action]) || $action = 'default';

        return iaDb::printf($this->_urlPatterns[$action], $data);
    }

    public function exists($slug, $parentId, $id = null)
    {
        $wherePattern = sprintf('`title_alias` = :slug AND `%s` = :parent', self::COL_PARENT_ID);

        return is_null($id)
            ? (bool)$this->iaDb->exists($wherePattern, ['slug' => $slug, 'parent' => $parentId], self::getTable())
            : (bool)$this->iaDb->exists($wherePattern . ' AND `id` != :id', ['slug' => $slug, 'parent' => $parentId, 'id' => $id], self::getTable());
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
UPDATE `:table_categs` SET `num_listings` = IF(`id` = :category, `num_listings` + :increment, `num_listings`),
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

        $categories = $this->iaDb->all(['id', self::COL_PARENT_ID, self::COL_CHILDREN], '1 ORDER BY `level` DESC', $start, $limit);
        foreach ($categories as $cat) {
            if (self::ROOT_PARENT_ID == $cat[self::COL_PARENT_ID]) {
                continue;
            }

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

            if (!empty($cat[self::COL_CHILDREN]) && $cat[self::COL_CHILDREN] != $cat['id']) {
                $_num_all_listings = $this->iaDb->one('SUM(`num_listings`)', "`id` IN ({$cat[self::COL_CHILDREN]})", iaCateg::getTable());
            }

            $_num_all_listings+= $_num_listings;

            $crossed = $this->iaDb->one('COUNT(`category_id`) `num`', iaDb::convertIds($_id, 'category_id'), 'listings_categs');

            if ($crossed) {
                $_num_listings+= $crossed;
                $_num_all_listings+= $crossed;
            }

            $this->iaDb->update(['num_listings' => $_num_listings, 'num_all_listings' => $_num_all_listings], iaDb::convertIds($_id));
        }

        $this->iaDb->resetTable();

        return true;
    }

    public function getSlug($title, $parentId = null, $parent = null)
    {
        if (self::ROOT_PARENT_ID == $parentId) {
            return '';
        }

        $slug = iaSanitize::alias($title);

        if ('category' == $slug) {
            $id = $this->iaDb->getNextId(self::getTable());
            $slug.= '-' . $id;
        }

        $parent || $parent = $this->getById($parentId, false);

        $slug = ltrim($parent['title_alias'] . $slug . IA_URL_DELIMITER, IA_URL_DELIMITER);
        $this->iaCore->get('directory_lowercase_urls', true) && $slug = strtolower($slug);

        return $slug;
    }

    public function updateCounters($itemId, array $itemData, $action, $previousData = null)
    {
        parent::updateCounters($itemId, $itemData, $action, $previousData);
        $this->syncLinkingData($itemId);
    }

    public function syncLinkingData($categoryId = null)
    {
        if (is_null($categoryId)) {
            $root = $this->getRoot();
            $categoryId = $root['id'];
        } else {
            $category = $this->getById($categoryId, false);
            $parent = $this->getById($category[self::COL_PARENT_ID], false);

            $this->_updateBreadcrumbs($category, $parent);

            $this->iaDb->update($category, iaDb::convertIds($categoryId), null, self::getTable());
        }

        $children = $this->iaDb->all(iaDb::ALL_COLUMNS_SELECTION, iaDb::convertIds($categoryId, self::COL_PARENT_ID), null, null, self::getTable());

        foreach ($children as $child) {
            foreach (explode(',', $child[self::COL_CHILDREN]) as $id) {
                if (!trim($id) || (!$category = $this->getById($id, false))) {
                    continue;
                }

                $parent = $this->getById($category[self::COL_PARENT_ID], false);

                $this->_updateSlug($category, $parent);
                $this->_updateBreadcrumbs($category, $parent);

                $this->iaDb->update($category, iaDb::convertIds($id), null, self::getTable());
            }
        }
    }

    protected function _updateSlug(array &$category, array $parent)
    {
        $category['title_alias'] = $this->getSlug($category['title_' . $this->iaView->language], $category[self::COL_PARENT_ID], $parent);
    }

    protected function _updateBreadcrumbs(array &$category, array $parent)
    {
        $breadcrumbs = [];

        $titleKey = 'title_' . $this->iaView->language;
        $baseUrl = str_replace(IA_URL, '', $this->getInfo('url'));

        if (!empty($parent[self::COL_PARENTS])) {
            $parents = $this->iaDb->all([$titleKey, 'title_alias'],
                "`id` IN({$parent[self::COL_PARENTS]}) && `" . self::COL_PARENT_ID . "` != " . self::ROOT_PARENT_ID . " && `status` = 'active' ORDER BY `level`",
                null, null, self::getTable());

            foreach ($parents as $p) {
                $breadcrumbs[$p[$titleKey]] = $baseUrl . $p['title_alias'];
            }
        }

        $breadcrumbs[$category[$titleKey]] = $baseUrl . $category['title_alias'];

        $category['breadcrumb'] = serialize($breadcrumbs);
    }
}
