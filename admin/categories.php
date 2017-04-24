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

    protected $_gridColumns = ['title', 'title_alias', 'num_all_listings', 'locked', 'level', 'status'];
    protected $_gridFilters = ['title' => self::LIKE, 'status' => self::EQUAL];
    protected $_gridQueryMainTableAlias = 'c';

    protected $_tooltipsEnabled = true;

    protected $_activityLog = ['item' => 'category'];

    protected $_phraseAddSuccess = 'category_added';
    protected $_phraseGridEntryDeleted = 'category_deleted';


    public function init()
    {
        $this->_gridColumns['parent_id'] = iaCateg::COL_PARENT_ID;
    }

    public function _gridQuery($columns, $where, $order, $start, $limit)
    {
        return $this->getHelper()->get($columns, $where, $order, $start, $limit);
    }

    protected function _setPageTitle(&$iaView, array $entryData, $action)
    {
        $iaView->title(iaLanguage::get($action . '_category', $iaView->title()));
    }

    protected function _insert(array $entryData)
    {
        return $this->getHelper()->insert($entryData);
    }

    protected function _update(array $entryData, $entryId)
    {
        return $this->getHelper()->update($entryData, $entryId);
    }

    protected function _delete($entryId)
    {
        return $this->getHelper()->delete($entryId);
    }

    protected function _setDefaultValues(array &$entry)
    {
        $entry = [
            'title_alias' => '',
            'status' => iaCore::STATUS_ACTIVE,
            'featured' => false,
            'locked' => false,

            iaCateg::COL_PARENT_ID => $this->getHelper()->getRootId()
        ];
    }

    protected function _preSaveEntry(array &$entry, array $data, $action)
    {
        parent::_preSaveEntry($entry, $data, $action);

        $entry['locked'] = (int)$data['locked'];
        $entry['status'] = $data['status'];
        $entry['order'] = $this->_iaDb->getMaxOrder() + 1;

        $entry[iaCateg::COL_PARENT_ID] = isset($data['tree_id']) ? (int)$data['tree_id'] : $this->getHelper()->getRootId();

        $entry['title_alias'] = empty($data['title_alias']) ? $data['title'][iaLanguage::getMasterLanguage()->code] : $data['title_alias'];
        $entry['title_alias'] = $this->getHelper()->getSlug($entry['title_alias'], $entry[iaCateg::COL_PARENT_ID]);

        if ($this->getHelper()->exists($entry['title_alias'], $entry[iaCateg::COL_PARENT_ID], $this->getEntryId())) {
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

    protected function _assignValues(&$iaView, array &$entryData)
    {
        parent::_assignValues($iaView, $entryData);

        $alias = explode(IA_URL_DELIMITER, substr($entryData['title_alias'], 0, -1));
        $entryData['title_alias'] = end($alias);

        $iaView->assign('crossed', $this->_fetchCrossed());
        $iaView->assign('tree', $this->getHelper()->getTreeVars($this->getEntryId(), $entryData, $this->getPath()));
    }

    protected function _fetchCrossed()
    {
        $sql = <<<SQL
SELECT c.`id`, c.`title_:lang` 
	FROM `:prefix:table_categories` c, `:prefix:table_crossed` cr 
WHERE c.`id` = cr.`crossed_id` AND cr.`category_id` = :id
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
                    $this->getHelper()->recount($_POST['start'], $_POST['limit']);
                    break;

                case 'pre_recount_listings':
                    $this->getHelper()->resetCounters();

                    $this->_iaCore->factoryModule('listing', $this->getModuleName(), iaCore::ADMIN);
                    $output['total'] = $this->_iaDb->one(iaDb::STMT_COUNT_ROWS,
                        iaDb::convertIds(iaCore::STATUS_ACTIVE, 'status'), iaListing::getTable());
            }
        }

        return $output;
    }
}