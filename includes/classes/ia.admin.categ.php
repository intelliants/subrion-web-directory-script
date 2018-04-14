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

class iaCateg extends iaAbstractHelperCategoryFlat implements iaDirectoryModule
{
    protected static $_table = 'categs';
    protected static $_tableCrossed = 'categs_crossed';

    protected $_moduleName = 'directory';

    protected $_itemName = 'categ';
    protected $_statuses = [iaCore::STATUS_ACTIVE, iaCore::STATUS_INACTIVE];

    protected $_activityLog = ['item' => 'category'];

    protected $_recountOptions = [
        'listingsTable' => 'listings'
    ];

    private $_urlPatterns = [
        'default' => ':base:slug'
    ];

    //public $dashboardStatistics = ['icon' => 'folder', 'url' => 'directory/categories/'];


    public function getSitemapEntries()
    {
        $result = [];

        $where = $this->_cols('`status` = :status AND `:col_pid` != :root_pid ORDER BY `level`, `title_' . $this->iaView->language . '`');
        $this->iaDb->bind($where, ['status' => iaCore::STATUS_ACTIVE]);

        if ($entries = $this->iaDb->all(['slug'], $where, null, null, self::getTable())) {
            $baseUrl = $this->getInfo('url');

            foreach ($entries as $entry) {
                $result[] = $baseUrl . $entry['slug'];
            }
        }

        return $result;
    }

    public function delete($itemId)
    {
        if ($result = parent::delete($itemId)) {
            $this->iaDb->delete(iaDb::convertIds($itemId, 'category_id'), 'listings_categs');
            $this->iaDb->delete('`category_id` = :id OR `crossed_id` = :id', self::getTableCrossed(), ['id' => $itemId]);

            // set 'Trash' status to all the listings in this category and subcategories
            //$where = '`id` IN (SELECT `category_id` ';

            //$this->iaDb->update(['status' => self::STATUS_TRASH]);
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

        $data['slug'] = isset($params['slug']) ? $params['slug'] : '';
        $data['slug'] = isset($params['category_slug']) ? $params['category_slug'] : $params['slug'];

        isset($this->_urlPatterns[$action]) || $action = 'default';

        return iaDb::printf($this->_urlPatterns[$action], $data);
    }

    public function exists($slug, $parentId, $id = null)
    {
        $wherePattern = self::_cols('`slug` = :slug AND `:col_pid` = :parent');

        empty($id)
            ? (bool)$this->iaDb->exists($wherePattern, ['slug' => $slug, 'parent' => $parentId], self::getTable())
            : (bool)$this->iaDb->exists($wherePattern . ' AND `id` != :id', ['slug' => $slug, 'parent' => $parentId, 'id' => $id], self::getTable());
    }

    public function recount($start, $limit)
    {
        parent::recount($start, $limit);

        $where = '`status` = :status ORDER BY `id`';
        $this->iaDb->bind($where, ['status' => iaCore::STATUS_ACTIVE]);

        $rows = $this->iaDb->all(['id'], $where, (int)$start, (int)$limit, $this->_recountOptions['listingsTable']);

        if ($rows) {
            $this->iaDb->setTable('listings_categs');

            foreach ($rows as $row) {
                if ($crossed = $this->iaDb->onefield('category_id', iaDb::convertIds($row['id'], 'listing_id'))) {
                    foreach ($crossed as $categoryId) {
                        $this->recountById($categoryId);
                    }
                }
            }

            $this->iaDb->resetTable();
        }
    }

    public function getSlug($title, $parentId = null, $parent = null)
    {
        $slug = iaSanitize::alias($title);

        if ('category' == $slug) {
            $id = $this->iaDb->getNextId(self::getTable());
            $slug .= '-' . $id;
        }

        $slug .= IA_URL_DELIMITER;

        if (self::ROOT_PARENT_ID != $parentId) {
            if (!$parent) {
                $parent = $this->getById($parentId, false);
            }
            if (!empty($parent['slug'])) {
                $slug = $parent['slug'] . $slug;
            }
        }

        $this->iaCore->get('directory_lowercase_urls', true) && $slug = strtolower($slug);

        return $slug;
    }

    public function updateCounters($itemId, array $itemData, $action, $previousData = null)
    {
        parent::updateCounters($itemId, $itemData, $action, $previousData);

        if (iaCore::ACTION_DELETE != $action) {
            $this->syncLinkingData($itemId);
        }
    }

    public function syncLinkingData($categoryId = null)
    {
        if (is_null($categoryId)) {
            $root = $this->getRoot();
            $categoryId = $root['id'];
        } else {
            if ($category = $this->getById($categoryId, false)) {
                $this->_updateBreadcrumbs($category);
            }

            $this->iaDb->update($category, iaDb::convertIds($categoryId), null, self::getTable());
        }

        foreach ($this->getChildren($categoryId) as $category) {
            $this->_updateSlug($category);
            $this->_updateBreadcrumbs($category);

            $this->iaDb->update($category, iaDb::convertIds($category['id']), null, self::getTable());
        }
    }

    protected function _updateSlug(array &$category)
    {
        $category['slug'] = $this->getSlug($category['title_' . $this->iaView->language], $category[self::COL_PARENT_ID]);
    }

    protected function _updateBreadcrumbs(array &$category)
    {
        $breadcrumbs = [];

        $titleKey = 'title_' . $this->iaView->language;
        $baseUrl = str_replace(IA_URL, '', $this->getInfo('url'));

        foreach ($this->getParents($category['id']) as $p) {
            if ($p[self::COL_PARENT_ID] != self::ROOT_PARENT_ID) {
                $breadcrumbs[$p[$titleKey]] = $baseUrl . $p['slug'];
            }
        }

        $breadcrumbs[$category[$titleKey]] = $baseUrl . $category['slug'];

        $category['breadcrumb'] = serialize($breadcrumbs);
    }
}
