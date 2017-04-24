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

class iaListing extends abstractDirectoryModuleAdmin implements iaDirectoryModule
{
    protected static $_table = 'listings';
    protected static $_tableCrossed = 'listings_categs';

    protected $_itemName = 'listings';

    protected $_statuses = [iaCore::STATUS_ACTIVE, iaCore::STATUS_INACTIVE, iaCore::STATUS_APPROVAL, self::STATUS_BANNED, self::STATUS_SUSPENDED];

    private $_urlPatterns = [
        'default' => ':base:action/:id/',
        'view' => ':base:category_alias:id:title_alias.html',
        'edit' => ':baseedit/:id/',
        'add' => ':baseadd/',
        'my' => ':baseprofile/listings/'
    ];

    public $dashboardStatistics = ['icon' => 'link'];


    public function insert(array $itemData)
    {
        $itemData['date_added'] = date(iaDb::DATETIME_FORMAT);
        $itemData['date_modified'] = date(iaDb::DATETIME_FORMAT);

        return parent::insert($itemData);
    }

    public function update(array $itemData, $id)
    {
        $itemData['date_modified'] = date(iaDb::DATETIME_FORMAT);

        return parent::update($itemData, $id);
    }

    public function delete($itemId)
    {
        $result = parent::delete($itemId);

        if ($result && $this->iaCore->get('listing_crossed')) {
            $this->iaDb->delete(iaDb::convertIds($itemId, 'listing_id'), self::getTableCrossed());
        }

        return $result;
    }

    public function updateCounters($itemId, array $itemData, $action, $previousData = null)
    {
        $this->_checkIfCountersNeedUpdate($action, $itemData, $previousData,
            $this->iaCore->factoryModule('categ', $this->getModuleName(), iaCore::ADMIN));
        $this->_checkCrossedLinksCounters($itemId, $itemData, $previousData, $action);

        switch ($action) {
            case iaCore::ACTION_EDIT:
                $ownerId = empty($itemData['member_id']) ? $previousData['member_id'] : $itemData['member_id'];

                if ($ownerId && iaUsers::getIdentity()->id != $ownerId) {
                    $this->sendUserNotification($itemData, $itemId);
                }

                break;

            case iaCore::ACTION_DELETE:
                $previousData['status'] = 'removed';
                $this->sendUserNotification($previousData);
        }
    }

    protected function _checkCrossedLinksCounters($itemId, array $itemData, $previousData, $action)
    {
        if (!$this->iaCore->get('listing_crossed') || !isset($itemData['status'])) {
            return;
        }

        $crossedLinks = $this->iaDb->all(['category_id'], iaDb::convertIds($itemId, 'listing_id'), null, null, self::getTableCrossed());

        if (!$crossedLinks) {
            return;
        }

        $newStatus = $itemData['status'];
        $oldStatus = $previousData ? $previousData['status'] : $newStatus;

        if ((iaCore::ACTION_DELETE == $action && iaCore::STATUS_ACTIVE == $newStatus)
            || (iaCore::STATUS_ACTIVE == $oldStatus && iaCore::STATUS_ACTIVE != $newStatus)) {
            $diff = -1;
        } elseif (iaCore::STATUS_ACTIVE == $newStatus && iaCore::STATUS_ACTIVE != $oldStatus) {
            $diff = 1;
        }

        if (isset($diff)) {
            $iaCateg = $this->iaCore->factoryModule('categ', $this->getModuleName(), iaCore::ADMIN);

            foreach ($crossedLinks as $entry) {
                $iaCateg->recountById($entry['category_id'], $diff);
            }
        }
    }

    public function saveCrossedLinks($itemId, $status, $mainCategoryId, $data)
    {
        $iaCateg = $this->iaCore->factoryModule('categ', $this->getModuleName(), iaCore::ADMIN);

        $this->iaDb->setTable(self::getTableCrossed());

        // first, correctly remove previously assigned crossed links
        if (iaCore::STATUS_ACTIVE == $status) {
            $crossedLinks = $this->iaDb->all(['category_id'], iaDb::convertIds($itemId, 'listing_id'),
                null, null, self::getTableCrossed());

            if ($crossedLinks) {
                foreach ($crossedLinks as $row) {
                    $iaCateg->recountById($row['category_id'], -1);
                }
            }
        }

        $this->iaDb->delete(iaDb::convertIds($itemId, 'listing_id'));

        // then, save
        if (!is_array($data)) {
            $data = explode(',', $data);
        }

        $count = min($this->iaCore->get('listing_crossed_limit', 5), count($data));
        $crossedInput = [];

        for ($i = 0; $i < $count; $i++) {
            $categoryId = trim($data[$i]);
            if ($categoryId && $categoryId != $mainCategoryId) {
                $crossedInput[] = ['listing_id' => $itemId, 'category_id' => (int)$categoryId];
            }
        }

        $this->iaDb->insert($crossedInput);

        // update crossed counters
        if (iaCore::STATUS_ACTIVE == $status) {
            foreach ($crossedInput as $entry) {
                $iaCateg->recountById($entry['category_id']);
            }
        }

        $this->iaDb->resetTable();
    }

    public function getSitemapEntries()
    {
        $result = [];

        $stmt = 'l.`status` = :status';
        $this->iaDb->bind($stmt, ['status' => iaCore::STATUS_ACTIVE]);

        if ($entries = $this->get('l.`id`, l.`title_alias`', $stmt, 'ORDER BY l.`date_modified` DESC')) {
            foreach ($entries as $entry) {
                $result[] = $this->url('view', $entry);
            }
        }

        return $result;
    }


    public static function getTableCrossed()
    {
        return self::$_tableCrossed;
    }

    public function url($action, array $data)
    {
        $data['base'] = $this->getInfo('url') . ('view' == $action ? 'listing/' : '');
        $data['action'] = $action;
        $data['category_alias'] = (!isset($data['category_alias']) ? '' : $data['category_alias']);
        $data['title_alias'] = (!isset($data['title_alias']) ? '' : '-' . $data['title_alias']);

        unset($data['title'], $data['category']);

        isset($this->_urlPatterns[$action]) || $action = 'default';

        return iaDb::printf($this->_urlPatterns[$action], $data);
    }

    public function getById($id, $process = true)
    {
        $row = $this->get('l.*', 'l.`id` = ' . (int)$id, null, 0, 1);
        $row && $row = $row[0];

        $process && $this->_processValues($row, true);

        return $row;
    }

    public function get($columns, $where, $order, $start = null, $limit = null)
    {
        $sql = <<<SQL
SELECT :columns, c.`title_:lang` `category_title`, c.`title_alias` `category_alias`, m.`fullname` `member` 
	FROM `:prefix:table_listings` l 
LEFT JOIN `:prefix:table_categories` c ON (l.`category_id` = c.`id`) 
LEFT JOIN `:prefix:table_members` m ON (l.`member_id` = m.`id`) 
WHERE :where :order :limit
SQL;
        $sql = iaDb::printf($sql, [
            'lang' => $this->iaCore->language['iso'],
            'prefix' => $this->iaDb->prefix,
            'table_listings' => $this->getTable(),
            'table_categories' => 'categs',
            'table_members' => iaUsers::getTable(),
            'columns' => $columns,
            'where' => $where,
            'order' => $order,
            'start' => $start,
            'limit' => $start || $limit ? sprintf('LIMIT %d, %d', $start, $limit) : ''
        ]);

        return $this->iaDb->getAll($sql);
    }

    public function sendUserNotification(array $listingData, $listingId = null)
    {
        if (isset($listingData['status']) && $this->iaCore->get('listing_' . $listingData['status'])) {
            if ($listingId) {
                $listingData = $this->getById($listingId);
            }

            $email = empty($listingData['email'])
                ? $this->iaDb->one('email', iaDb::convertIds($listingData['member_id']), iaUsers::getTable())
                : $listingData['email'];

            if ($email) {
                $iaMailer = $this->iaCore->factory('mailer');

                $iaMailer->loadTemplate('listing_' . $listingData['status']);
                $iaMailer->setReplacements([
                    'title' => $listingData['title'],
                    'url' => $this->url('view', $listingData)
                ]);
                $iaMailer->addAddress($email);

                return $iaMailer->send();
            }
        }

        return false;
    }

    public function getDomain($aUrl = '')
    {
        if (preg_match('/^(?:http|https|ftp):\/\/((?:[A-Z0-9][A-Z0-9_-]*)(?:\.[A-Z0-9][A-Z0-9_-]*)+)(:(\d+))?/i', $aUrl, $m)) {
            return $m[1];
        }

        return false;
    }

    public function getTreeVars(array $entryData)
    {
        $iaCateg = $this->iaCore->factoryModule('categ', $this->getModuleName(), iaCore::ADMIN);

        $category = empty($entryData['category_id'])
            ? $iaCateg->getRoot()
            : $iaCateg->getById($entryData['category_id']);

        $nodes = $iaCateg->getParents($category['id'], true);

        return [
            'url' => IA_ADMIN_URL . 'directory/categories/tree.json?noroot',
            'nodes' => implode(',', $nodes),
            'id' => $category['id'],
            'title' => $category['title']
        ];
    }
}