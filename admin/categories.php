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

class iaBackendController extends iaAbstractControllerModuleBackend
{
    protected $_name = 'categories';
    protected $_itemName = 'categs';

    protected $_helperName = 'categ';

    protected $_gridColumns = ['parent_id', 'title', 'title_alias', 'num_all_listings', 'locked', 'level', 'date_added', 'date_modified', 'status'];
    protected $_gridFilters = ['title' => self::LIKE, 'status' => self::EQUAL];
    protected $_gridQueryMainTableAlias = 'c';

    protected $_tooltipsEnabled = true;

    protected $_activityLog = ['item' => 'category'];

    protected $_phraseAddSuccess = 'category_added';
    protected $_phraseGridEntryDeleted = 'category_deleted';

    private $_root;


    public function init()
    {
        $this->_root = $this->getHelper()->getRoot();
    }

    public function _gridQuery($columns, $where, $order, $start, $limit)
    {
        return $this->getHelper()->get($columns, $where, $order, $start, $limit);
    }

    protected function _setPageTitle(&$iaView, array $entryData, $action)
    {
        $iaView->title(iaLanguage::get($action . '_category', $iaView->title()));
    }

    protected function _entryAdd(array $entryData)
    {
        $entryData['date_added'] = date(iaDb::DATE_FORMAT);
        $entryData['date_modified'] = date(iaDb::DATE_FORMAT);

        return parent::_entryAdd($entryData);
    }

    protected function _entryUpdate(array $entryData, $entryId)
    {
        $entryData['date_modified'] = date(iaDb::DATE_FORMAT);

        if (empty($entryId) && '0' !== $entryId) {
            return false;
        }

        $currentData = $this->getById($entryId);

        if (empty($currentData)) {
            return false;
        }

        $result = $this->_update($entryData, $entryId);

        if ($result) {
            $this->_writeLog(iaCore::ACTION_EDIT, $entryData, $entryId);
            $this->updateCounters($entryId, $entryData, iaCore::ACTION_EDIT, $currentData);

            $this->_iaCore->startHook('phpListingUpdated', [
                'itemId' => $entryId,
                'itemName' => $this->getItemName(),
                'itemData' => $entryData,
                'previousData' => $currentData
            ]);
        }

        return $result;
    }

    protected function _entryDelete($entryId)
    {
        return ($this->_root['id'] == $entryId) ? false : (bool)$this->getHelper()->delete($entryId);
    }

    protected function _setDefaultValues(array &$entry)
    {
        $entry = [
            'parent_id' => $this->_root['id'],
            'parents' => 0,
            'locked' => false,
            'featured' => false,
            'status' => iaCore::STATUS_ACTIVE,
            'title_alias' => ''
        ];
    }

    protected function _preSaveEntry(array &$entry, array $data, $action)
    {
        parent::_preSaveEntry($entry, $data, $action);

        $entry['parent_id'] = empty($data['tree_id']) ? $this->_root['id'] : $data['tree_id'];
        $entry['locked'] = (int)$data['locked'];
        $entry['status'] = $data['status'];
        $entry['order'] = $this->_iaDb->getMaxOrder() + 1;

        $entry['title_alias'] = empty($data['title_alias']) ? $data['title'][$this->_iaCore->language['iso']] : $data['title_alias'];
        $entry['title_alias'] = $this->getHelper()->getSlug($entry['title_alias'], $entry['parent_id']);

        if ($this->getHelper()->exists($entry['title_alias'], $entry['parent_id'], $this->getEntryId())) {
            $this->addMessage('directory_category_already_exists');
        }

        return !$this->getMessages();
    }

    protected function _postSaveEntry(array &$entry, array $data, $action)
    {
        $this->_iaDb->setTable($this->getHelper()->getTableCrossed());

        if (iaCore::ACTION_EDIT == $action) {
            $this->_iaDb->delete(iaDb::convertIds($this->getEntryId(), 'category_id'));
        }

        if (!empty($data['crossed'])) {
            foreach (explode(',', $data['crossed']) as $id) {
                if (!$id) {
                    continue;
                }
                $this->_iaDb->insert(['category_id' => $this->getEntryId(), 'crossed_id' => $id]);
            }
        }

        $this->_iaDb->resetTable();
    }

    public function updateCounters($entryId, array $entryData, $action, $previousData = null)
    {
        $this->getHelper()->rebuildRelation();
        $this->getHelper()->syncLinkingData($entryId);
    }

    protected function _assignValues(&$iaView, array &$entryData)
    {
        parent::_assignValues($iaView, $entryData);

        $alias = explode(IA_URL_DELIMITER, substr($entryData['title_alias'], 0, -1));
        $entryData['title_alias'] = end($alias);

        $parent = $this->getHelper()->getById($entryData['parent_id']);

        $entryData['crossed'] = $this->_fetchCrossed();

        $iaView->assign('parent', $parent);
    }

    protected function _getJsonTree(array $data)
    {
        $output = [];

        $dynamicLoadMode = ((int)$this->_iaDb->one(iaDb::STMT_COUNT_ROWS) > 150);
        $noRootMode = isset($data['noroot']) && '' == $data['noroot'];

        $rootId = $noRootMode ? 0 : -1;
        $parentId = isset($data['id']) && is_numeric($data['id'])
            ? (int)$data['id']
            : $rootId;

        $where = $dynamicLoadMode
            ? '`parent_id` = ' . $parentId
            : ($noRootMode ? '`id` != ' . $rootId : iaDb::EMPTY_CONDITION);

        // TODO: better solution should be found here. this code will break jstree composition in case if
        // category to be excluded from the list has children of 2 and more levels deeper
        empty($data['cid']) || $where.= ' AND `id` != ' . (int)$data['cid'] . ' AND `parent_id` != ' . (int)$data['cid'];

        $where.= ' ORDER BY `title`';

        $rows = $this->_iaDb->all(['id', 'title' => 'title_' . $this->_iaCore->language['iso'], 'parent_id', 'child'], $where);

        foreach ($rows as $row) {
            $entry = ['id' => $row['id'], 'text' => $row['title']];

            if ($dynamicLoadMode) {
                $entry['children'] = ($row['child'] && $row['child'] != $row['id']) || 0 === (int)$row['id'];
            } else {
                $entry['state'] = [];
                $entry['parent'] = $noRootMode
                    ? (0 == $row['parent_id'] ? '#' : $row['parent_id'])
                    : (0 == $row['id'] ? '#' : $row['parent_id']);
            }

            $output[] = $entry;
        }

        return $output;
    }


    protected function _fetchCrossed()
    {
        $sql = <<<SQL
SELECT c.`id`, c.`title_:lang` 
	FROM `:prefix:table_categories` c, `:prefix:table_crossed` cr 
WHERE c.`id` = cr.`crossed_id` && cr.`category_id` = :id
SQL;
        $sql = iaDb::printf($sql, [
            'lang' => $this->_iaCore->language['iso'],
            'prefix' => $this->_iaDb->prefix,
            'table_categories' => self::getTable(),
            'table_crossed' => $this->getHelper()->getTableCrossed(),
            'id' => $this->getEntryId()
        ]);

        return $this->_iaDb->getKeyValue($sql);
    }

    protected function _getJsonSlug(array $data)
    {
        $title = $this->getHelper()->getSlug($data['title'], (int)$data['category']);

        return ['data' => $this->getHelper()->url('default', ['title_alias' => $title])];
    }

    protected function _getJsonConsistency(array $data)
    {
        $output = [];

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'recount_listings':
                    $this->getHelper()->recountListingsNum((int)$_POST['start'], (int)$_POST['limit']);
                    break;

                case 'pre_recount_listings':
                    $output['total'] = $this->getHelper()->getCount();
            }
        }

        return $output;
    }
}
