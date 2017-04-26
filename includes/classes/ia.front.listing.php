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

class iaListing extends abstractDirectoryDirectoryFront implements iaDirectoryModule
{
    protected static $_table = 'listings';
    protected static $_tableCrossed = 'listings_categs';

    protected $_itemName = 'listings';

    protected $_statuses = [iaCore::STATUS_ACTIVE, iaCore::STATUS_INACTIVE, iaCore::STATUS_APPROVAL, self::STATUS_BANNED, self::STATUS_SUSPENDED];

    public $coreSearchEnabled = true;
    public $coreSearchOptions = [
        'tableAlias' => 'l',
        'columnAlias' => ['date' => 'date_modified'],
        'regularSearchFields' => ['title', 'domain', 'description'],
        'customColumns' => ['keywords', 'c', 'sc']
    ];

    private $_urlPatterns = [
        'default' => ':base:action/:id/',
        'view' => ':base:category_alias:id:title_alias.html',
        'edit' => ':baseedit/:id/',
        'add' => ':baseadd/',
        'my' => ':iaurlprofile/listings/'
    ];

    protected $_foundRows = 0;

    private $_iaCateg;

    private $_baseUrl = '';


    public function init()
    {
        parent::init();

        $this->_iaCateg = $this->iaCore->factoryModule('categ', $this->getModuleName());

        $this->_baseUrl = $this->getModuleName() == $this->iaCore->get('default_package')
            ? IA_URL
            : $this->iaCore->modulesData[$this->getModuleName()]['url'];
    }

    public static function getTableCrossed()
    {
        return self::$_tableCrossed;
    }

    public function url($action, array $data)
    {
        $data['base'] = $this->_baseUrl . ('view' == $action ? 'listing/' : '');
        $data['iaurl'] = IA_URL;
        $data['action'] = $action;
        $data['category_alias'] = (!isset($data['category_alias']) ? '' : $data['category_alias']);
        $data['title_alias'] = (!isset($data['title_alias']) ? '' : '-' . $data['title_alias']);

        unset($data['title'], $data['category']);

        isset($this->_urlPatterns[$action]) || $action = 'default';

        return iaDb::printf($this->_urlPatterns[$action], $data);
    }

    public function getFoundRows()
    {
        return $this->_foundRows;
    }

    public function get($where, $start = null, $limit = null, $order = null, $prioritizedSorting = false)
    {
        $sql = 'SELECT SQL_CALC_FOUND_ROWS '
                . 'l.*, '
                . "c.`title_{$this->iaCore->language['iso']}` `category_title`, c.`title_alias` `category_alias`, c.`breadcrumb` `category_breadcrumb`, "
                . 'm.`fullname` `member`, m.`username` `account_username` '
            . 'FROM `' . self::getTable(true) . '` l '
            . "LEFT JOIN `{$this->iaDb->prefix}categs` c ON (l.`category_id` = c.`id`) "
            . "LEFT JOIN `{$this->iaDb->prefix}members` m ON (l.`member_id` = m.`id`) "
            . 'WHERE ' . ($where ? $where . ' AND' : '') . " l.`status` != 'banned' "
            . 'ORDER BY ' . ($prioritizedSorting ? 'l.`sponsored` DESC, l.`featured` DESC, ' : '')
            . ($order ? $order : 'l.`date_modified` DESC') . ' '
            . ($start || $limit ? "LIMIT $start, $limit" : '');

        $rows = $this->iaDb->getAll($sql);
        $this->_foundRows = $this->iaDb->foundRows();

        $this->_processValues($rows);

        return $rows;
    }

    public function coreSearch($stmt, $start, $limit, $order)
    {
        $stmt .= " AND l.`status` = '" . iaCore::STATUS_ACTIVE . "'";

        $rows = $this->get($stmt, $start, $limit, $order, true);
        $count = $this->getFoundRows();

        $count || iaLanguage::set('no_web_listings2', iaLanguage::getf('no_web_listings2', ['url' => $this->getInfo('url') . 'add/']));

        return [$count, $rows];
    }

    public function coreSearchTranslateColumn($column, $value)
    {
        switch ($column) {
            case 'keywords':
                $lang = $this->iaView->language;

                $fields = ['title_' . $lang, 'description_' . $lang, 'url'];
                $value = "'%" . iaSanitize::sql($value) . "%'";

                $result = [];
                foreach ($fields as $fieldName) {
                    $result[] = ['col' => ':column', 'cond' => 'LIKE', 'val' => $value, 'field' => $fieldName];
                }

                return $result;

            case 'c':
            case 'sc':
                $subQuery = sprintf('SELECT `child_id` FROM `%s` WHERE `parent_id` = %d',
                    $this->_iaCateg->getTableFlat(true), $value);

                return ['col' => ':column', 'cond' => 'IN', 'val' => '(' . $subQuery . ')', 'field' => 'category_id'];
        }
    }

    public function accountActions($params)
    {
        return [$this->url(iaCore::ACTION_EDIT, $params['item']), ''];
    }

    /**
     * Get member listings on View Member page
     *
     * @param int $memberId member id
     * @param int $start
     * @param int $limit
     *
     * @return array
     */
    public function fetchMemberListings($memberId, $start, $limit)
    {
        $stmtWhere = 'l.`status` = :status AND l.`member_id` = :member';
        $this->iaDb->bind($stmtWhere, [
            'status' => iaCore::STATUS_ACTIVE,
            'member' => (int)$memberId
        ]);

        return [
            'items' => $this->get($stmtWhere, $start, $limit),
            'total_number' => $this->getFoundRows()
        ];
    }

    public function postPayment($listingId, $plan)
    {
        iaCore::instance()->startHook('phpDirectoryListingSetPlan', ['transaction' => $listingId, 'plan' => $plan]);

        return true;
    }

    public function getByCategoryId($categoryId, $start, $limit, $order)
    {
        $where = $this->iaCore->get('display_children_listing')
            ? 'li.`category_id` IN (SELECT `child_id` FROM `' . $this->_iaCateg->getTableFlat(true) . '` WHERE `parent_id` = ' . (int)$categoryId . ')'
            : 'li.`category_id` = ' . (int)$categoryId;

        $sql =
            'SELECT SQL_CALC_FOUND_ROWS li.*, '
                //. 'IF(li.`category_id` IN( ' . $cat_list . ' ), li.`category_id`, cr.`category_id`) `category`, '
                //. 'IF(li.`category_id` = ' . $cat_id . ', 0, 1) `crossed`, '
                . 'ca.`title_' . $this->iaCore->language['iso'] . '` `category_title`, ca.`title_alias` `category_alias`, ca.`breadcrumb` `category_breadcrumb`, '
                . 'ac.`fullname` `member`, ac.`username` `account_username` '
            . 'FROM `' . $this->iaDb->prefix . 'categs` ca, ' . self::getTable(true) . ' li '
                . 'LEFT JOIN `' . $this->iaDb->prefix . 'listings_categs` cr ON (cr.`listing_id` = li.`id` AND cr.`category_id` = ' . (int)$categoryId . ') '
                . 'LEFT JOIN `' . $this->iaDb->prefix . 'members` ac ON (ac.`id` = li.`member_id`) '
            . 'WHERE li.`status` = \'active\' '
                . '&& (' .  $where . ' OR cr.`category_id` IS NOT NULL) && ca.`id` = li.`category_id` '
                . " ORDER BY `sponsored` DESC, `featured` DESC, $order "
                . ($start || $limit ? " LIMIT $start, $limit " : '');

        $rows = $this->iaDb->getAll($sql);
        $this->_foundRows = $this->iaDb->foundRows();

        $this->_processValues($rows);

        return $rows;
    }

    /**
     * Returns domain name by a given URL
     *
     * @param string $url
     *
     * @return bool
     */
    public function getDomain($url = '')
    {
        if (preg_match('/^(?:http|https|ftp):\/\/((?:[A-Z0-9][A-Z0-9_-]*)(?:\.[A-Z0-9][A-Z0-9_-]*)+)(:(\d+))?/i', $url, $m)) {
            return $m[1];
        }

        return false;
    }

    public function checkDuplicateListings($listing)
    {
        $field = $this->iaCore->get('directory_duplicate_check_field');

        return $this->iaDb->one('id', iaDb::convertIds($listing[$field], $field), self::getTable()) ? $field : false;
    }

    public function insert(array $itemData)
    {
        $itemData['date_added'] = date(iaDb::DATETIME_FORMAT);
        $itemData['date_modified'] = date(iaDb::DATETIME_FORMAT);

        if ($this->iaCore->get('directory_lowercase_urls')) {
            $itemData['title_alias'] = strtolower($itemData['title_alias']);
        }

        if ($id = parent::insert($itemData)) {
            $this->_sendEmailNotification($id);
        }

        return $id;
    }

    public function update(array $itemData, $id)
    {
        $itemData['date_modified'] = date(iaDb::DATETIME_FORMAT);

        return parent::update($itemData, $id);
    }

    public function updateCounters($itemId, array $itemData, $action, $previousData = null)
    {
        $this->_checkIfCountersNeedUpdate($action, $itemData, $previousData, $this->_iaCateg);
        $this->_saveCrossed($itemId, $itemData, $previousData, $action);
    }

    protected function _saveCrossed($itemId, array $itemData, $previousData, $action)
    {
        if (!$this->iaCore->get('listing_crossed') || !isset($itemData['category_id'])) {
            return;
        }

        $this->iaDb->setTable(self::getTableCrossed());

        if (!$previousData) {
            $previousData['status'] = iaCore::STATUS_INACTIVE;
        }

        if ((iaCore::ACTION_EDIT == $action && iaCore::STATUS_ACTIVE == $previousData['status'])
            || (iaCore::ACTION_DELETE == $action && isset($itemData['status']) && iaCore::STATUS_ACTIVE == $itemData['status'])) {
            $crossed = $this->iaDb->all(['category_id'], iaDb::convertIds($itemId, 'listing_id'));

            foreach ($crossed as $entry) {
                $this->_iaCateg->recountById($entry['category_id'], -1);
            }
        }

        $this->iaDb->delete(iaDb::convertIds($itemId, 'listing_id'));

        if (isset($_POST['crossed_links'])) {
            $data = $_POST['crossed_links'];
            if (!is_array($data)) {
                $data = explode(',', $data);
            }

            $count = min($this->iaCore->get('listing_crossed_limit', 5), count($data));
            $crossedInput = [];

            for ($i = 0; $i < $count; $i++) {
                if (trim($data[$i]) && $data[$i] != $itemData['category_id']) {
                    $crossedInput[] = ['listing_id' => $itemId, 'category_id' => (int)$data[$i]];
                }
            }

            $this->iaDb->insert($crossedInput);

            // update crossed counters
            if (isset($itemData['status']) && iaCore::STATUS_ACTIVE == $itemData['status']) {
                foreach ($crossedInput as $entry) {
                    $this->_iaCateg->recountById($entry['category_id']);
                }
            }
        }

        $this->iaDb->resetTable();
    }

    protected function _fetchEmailAddress(array $listingData)
    {
        if (empty($listingData['email']) && empty($listingData['member_id'])) {
            return false;
        }

        $email = $listingData['email'];
        $name = null;

        if ($owner = $this->iaCore->factory('users')->getInfo($listingData['member_id'])) {
            empty($email) && $email = $owner['email'];
            $name = $owner['fullname'];
        }

        return [$email, $name];
    }

    /**
     * Sends email notification to administrator once a new listing is created
     *
     * @param int $listingId listing id
     */
    protected function _sendEmailNotification($listingId)
    {
        $listingData = $this->getById($listingId);

        if (!$listingData) {
            return;
        }

        $iaMailer = $this->iaCore->factory('mailer');

        if ($this->iaCore->get('new_listing_admin')) {
            $iaMailer->loadTemplate('new_listing_admin');
            $iaMailer->setReplacements([
                'title' => $listingData['title'],
                'url' => IA_ADMIN_URL . 'directory/listings/edit/' . $listingData['id'] . '/'
            ]);

            $iaMailer->sendToAdministrators();
        }

        $emailTemplate = iaCore::STATUS_ACTIVE == $listingData['status'] ? 'active' : 'approval';
        $emailTemplate = sprintf('new_%s_listing', $emailTemplate);;

        if ($this->iaCore->get($emailTemplate)) {
            if ($addressee = $this->_fetchEmailAddress($listingData)) {
                $iaMailer->loadTemplate($emailTemplate);
                $iaMailer->addAddress($addressee[0], $addressee[1]);
                $iaMailer->setReplacements(['title' => $listingData['title'], 'url' => $listingData['link']]);

                $iaMailer->send();
            }
        }
    }

    protected function _processValues(&$rows, $singleRow = false, $fieldNames = [])
    {
        parent::_processValues($rows, $singleRow, $fieldNames);

        if ($rows) {
            foreach ($rows as &$row) {
                $row['breadcrumb'] = empty($row['category_breadcrumb']) ? [] : unserialize($row['category_breadcrumb']);
            }
        }

        return $rows;
    }

    /**
     * Get listing details by id
     *
     * @param int $itemId listing id
     *
     * @return array
     */
    public function getById($itemId, $decorate = true)
    {
        $listings = $this->get('l.`id` = ' . (int)$itemId, 0, 1);

        return $listings ? $listings[0] : [];
    }

    public function getTop($limit = 10, $start = 0)
    {
        return $this->get("l.`status` = 'active'", $start, $limit, 'l.`rank` DESC');
    }

    public function getPopular($limit = 10, $start = 0)
    {
        return $this->get("l.`status` = 'active'", $start, $limit, 'l.`views_num` DESC');
    }

    public function getLatest($limit = 10, $start = 0)
    {
        return $this->get("l.`status` = 'active'", $start, $limit, 'l.`date_added` DESC');
    }

    public function getRandom($limit = 10, $start = 0)
    {
        return $this->get("l.`status` = 'active'", $start, $limit, iaDb::FUNCTION_RAND);
    }

    public function isSubmissionAllowed($memberId)
    {
        $result = true;

        if (iaUsers::MEMBERSHIP_ADMINISTRATOR != iaUsers::getIdentity()->usergroup_id) {
            $listingCount = $this->iaDb->one_bind(iaDb::STMT_COUNT_ROWS, '`member_id` = :member', ['member' => $memberId], self::getTable());

            $result = ($listingCount < $this->iaCore->get('directory_listing_limit'));
        }

        return $result;
    }
}
