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

class iaBackendController extends iaAbstractControllerModuleBackend
{
    protected $_name = 'listings';

    protected $_helperName = 'listing';

    protected $_gridColumns = ['title', 'slug', 'url', 'date_added', 'date_modified', 'reported_as_broken', 'reported_as_broken_comments', 'status'];
    protected $_gridFilters = ['title' => self::LIKE, 'status' => self::EQUAL];
    protected $_gridSorting = ['member' => ['fullname', 'm'], 'category_title' => ['title', 'c', 'categ']];
    protected $_gridQueryMainTableAlias = 'l';

    protected $_tooltipsEnabled = true;

    protected $_phraseAddSuccess = 'listing_added';

    protected $_activityLog = ['item' => 'listing'];

    private $_iaCateg;


    public function init()
    {
        $this->_iaCateg = $this->_iaCore->factoryItem('categ');
    }

    protected function _gridModifyParams(&$conditions, &$values, array $params)
    {
        if (!empty($params['text'])) {
            $langCode = $this->_iaCore->language['iso'];
            $conditions[] = "(l.`title_{$langCode}` LIKE :text OR l.`description_{$langCode}` LIKE :text)";
            $values['text'] = '%' . iaSanitize::sql($params['text']) . '%';
        }

        if (isset($params['no_owner'])) {
            $conditions[] = 'l.`member_id` = 0';
        } elseif (!empty($params['member'])) {
            $conditions[] = '(m.`fullname` LIKE :member OR m.`username` LIKE :member)';
            $values['member'] = '%' . iaSanitize::sql($params['member']) . '%';
        }

        isset($params['reported_as_broken']) && $conditions[] = 'l.`reported_as_broken` = 1';
    }

    public function _gridQuery($columns, $where, $order, $start, $limit)
    {
        return $this->getHelper()->get($columns, $where, $order, $start, $limit);
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
        return (bool)$this->getHelper()->delete($entryId);
    }

    protected function _setDefaultValues(array &$entry)
    {
        $entry = [
            'member_id' => iaUsers::getIdentity()->id,
            'category_id' => 0,
            'crossed' => false,
            'sponsored' => false,
            'featured' => false,
            'status' => iaCore::STATUS_ACTIVE
        ];
    }

    protected function _preSaveEntry(array &$entry, array $data, $action)
    {
        parent::_preSaveEntry($entry, $data, $action);

        if (isset($data['reported_as_broken'])) {
            $entry['reported_as_broken'] = (int)$data['reported_as_broken'];
            $data['reported_as_broken'] || $entry['reported_as_broken_comments'] = '';
        }

        if (!empty($entry['url']) && !iaValidate::isUrl($entry['url'])) {
            if (iaValidate::isUrl($entry['url'], false)) {
                $entry['url'] = 'http://' . $entry['url'];
            } else {
                $this->addMessage('error_url');
            }
        }

        $entry['domain'] = $this->getHelper()->getDomain($entry['url']);

        $entry['rank'] = min(5, max(0, (int)$data['rank']));
        $entry['category_id'] = (int)$data['tree_id'];

        $entry['slug'] = empty($data['slug'])
            ? $data['title'][iaLanguage::getMasterLanguage()->code]
            : $data['slug'];
        $entry['slug'] = $this->_getSlug($entry['slug']);

        if (iaValidate::isUrl($entry['url'])) {
            // check alexa
            if ($this->_iaCore->get('directory_enable_alexarank')) {
                include IA_MODULES . 'directory/includes/alexarank.inc.php';

                if ($alexaData = (new iaAlexaRank())->getAlexa($entry['domain'])) {
                    $entry['alexa_rank'] = $alexaData['rank'];
                }
            }
        }

        return !$this->getMessages();
    }

    protected function _postSaveEntry(array &$entry, array $data, $action)
    {
        if (isset($data['crossed_links'])) {
            $this->getHelper()->saveCrossedLinks($this->getEntryId(), $entry['status'], $entry['category_id'], $data['crossed_links']);
        }
    }

    protected function _assignValues(&$iaView, array &$entryData)
    {
        parent::_assignValues($iaView, $entryData);

        $iaView->assign('crossed', $this->_fetchCrossedCategories($this->getEntryId()));
        $iaView->assign('statuses', $this->getHelper()->getStatuses());
        $iaView->assign('tree', $this->getHelper()->getTreeVars($entryData));
    }

    protected function _fetchCrossedCategories($entryId)
    {
        $sql = <<<SQL
SELECT c.`id`, c.`title_:lang`
  FROM `:prefix:table_categories` c, `:prefix:table_crossed` cr 
WHERE c.`id` = cr.`category_id` && cr.`listing_id` = :id
SQL;
        $sql = iaDb::printf($sql, [
            'prefix' => $this->_iaDb->prefix,
            'table_categories' => iaCateg::getTable(),
            'table_crossed' => 'listings_categs',
            'lang' => $this->_iaCore->language['iso'],
            'id' => (int)$entryId
        ]);

        return $this->_iaDb->getKeyValue($sql);
    }

    protected function _getSlug($title, $convertLowercase = false)
    {
        $title = iaSanitize::alias($title);

        if ($this->_iaCore->get('directory_lowercase_urls', true) && !$convertLowercase) {
            $title = strtolower($title);
        }

        return $title;
    }

    protected function _getJsonSlug(array $data)
    {
        $title = $this->_getSlug(isset($data['title']) ? $data['title'] : '', isset($data['slug']));
        $categorySlug = empty($data['category'])
            ? ''
            : $this->_iaDb->one('slug', iaDb::convertIds($data['category']), iaCateg::getTable());

        $slug = $this->getHelper()->url('view', [
            'id' => empty($data['id']) ? $this->_iaDb->getNextId() : $data['id'],
            'slug' => $title,
            'category_slug' => $categorySlug
        ]);

        return ['data' => $slug];
    }
}
