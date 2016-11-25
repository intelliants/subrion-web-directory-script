<?php
//##copyright##

class iaBackendController extends iaAbstractControllerPackageBackend
{
	protected $_name = 'listings';

	protected $_helperName = 'listing';

	protected $_gridColumns = array('title', 'title_alias', 'date_added', 'date_modified', 'reported_as_broken', 'reported_as_broken_comments', 'status');
	protected $_gridFilters = array('title' => self::LIKE, 'status' => self::EQUAL);
	protected $_gridQueryMainTableAlias = 'l';

	protected $_phraseAddSuccess = 'listing_added';

	protected $_activityLog = array('item' => 'listing');

	private $_iaCateg;


	public function init()
	{
		$this->_iaCateg = $this->_iaCore->factoryPackage('categ', $this->getPackageName(), iaCore::ADMIN);
	}

	protected function _modifyGridParams(&$conditions, &$values, array $params)
	{
		if (!empty($params['text']))
		{
			$conditions[] = '(l.`title` LIKE :text OR l.`description` LIKE :text)';
			$values['text'] = '%' . iaSanitize::sql($params['text']) . '%';
		}

		if(isset($params['no_owner']))
		{
			$conditions[] = 'l.`member_id` = 0';
		}
		elseif (!empty($params['member']))
		{
			$conditions[] = '(m.`fullname` LIKE :member OR m.`username` LIKE :member)';
			$values['member'] = '%' . iaSanitize::sql($params['member']) . '%';
		}

		isset($params['reported_as_broken']) && $conditions[] = 'l.`reported_as_broken` = 1';
	}

	public function _gridQuery($columns, $where, $order, $start, $limit)
	{
		return $this->getHelper()->get($columns, $where, $order, $start, $limit);
	}

	protected function _entryAdd(array $entryData)
	{
		$entryData['date_added'] = date(iaDb::DATETIME_FORMAT);
		$entryData['date_modified'] = date(iaDb::DATETIME_FORMAT);

		return parent::_entryAdd($entryData);
	}

	protected function _entryUpdate(array $entryData, $entryId)
	{
		$entryData['date_modified'] = date(iaDb::DATETIME_FORMAT);

		return parent::_entryUpdate($entryData, $entryId);
	}

	protected function _entryDelete($entryId)
	{
		return (bool)$this->getHelper()->delete($entryId);
	}

	protected function _setDefaultValues(array &$entry)
	{
		$entry = array(
			'member_id' => iaUsers::getIdentity()->id,
			'category_id' => 0,
			'crossed' => false,
			'sponsored' => false,
			'featured' => false,
			'status' => iaCore::STATUS_ACTIVE
		);
	}

	protected function _preSaveEntry(array &$entry, array $data, $action)
	{
		parent::_preSaveEntry($entry, $data, $action);

		if (isset($data['reported_as_broken']))
		{
			$entry['reported_as_broken'] = (int)$data['reported_as_broken'];
			$data['reported_as_broken'] || $entry['reported_as_broken_comments'] = '';
		}

		if (!empty($entry['url']) && !iaValidate::isUrl($entry['url']))
		{
			if (iaValidate::isUrl($entry['url'], false))
			{
				$entry['url'] = 'http://' . $entry['url'];
			}
			else
			{
				$this->addMessage('error_url');
			}
		}

		$entry['domain'] = $this->getHelper()->getDomain($entry['url']);

		$entry['rank'] = min(5, max(0, (int)$data['rank']));
		$entry['category_id'] = (int)$data['category_id'];

		$entry['title_alias'] = empty($data['title_alias']) ? $data['title'] : $data['title_alias'];
		$entry['title_alias'] = $this->getHelper()->getTitleAlias($entry['title_alias']);

		if (iaValidate::isUrl($entry['url']))
		{
			// check alexa
			if ($this->_iaCore->get('directory_enable_alexarank'))
			{
				include IA_PACKAGES . 'directory' . IA_DS . 'includes' . IA_DS . 'alexarank.inc.php';
				$iaAlexaRank = new iaAlexaRank();

				if ($alexaData = $iaAlexaRank->getAlexa($entry['domain']))
				{
					$entry['alexa_rank'] = $alexaData['rank'];
				}
			}
		}

		return !$this->getMessages();
	}

	protected function _postSaveEntry(array &$entry, array $data, $action)
	{
		$this->_iaDb->setTable($this->getHelper()->getTableCrossed());

		$this->_iaDb->delete(iaDb::convertIds($this->getEntryId(), 'listing_id'));

		if (!empty($data['crossed_links']))
		{
			foreach (explode(',', $data['crossed_links']) as $categoryId)
			{
				$this->_iaDb->insert(array('listing_id' => $this->getEntryId(), 'category_id' => $categoryId));
				$this->_iaCateg->changeNumListing($categoryId);
			}
		}

		$this->_iaDb->resetTable();
	}

	protected function _assignValues(&$iaView, array &$entryData)
	{
		parent::_assignValues($iaView, $entryData);

		$category = $this->_iaDb->row(array('id', 'title', 'parent_id', 'parents'), iaDb::convertIds($entryData['category_id']),  iaCateg::getTable());
		$crossed = $this->_fetchCrossedCategories();

		$iaView->assign('category', $category);
		$iaView->assign('crossed', $crossed);
		$iaView->assign('statuses', $this->getHelper()->getStatuses());
	}

	protected function _fetchCrossedCategories()
	{
		$sql = 'SELECT c.`id`, c.`title` '
			. 'FROM `:prefix:table_categories` c, `:prefix:table_crossed` cr '
			. 'WHERE c.`id` = cr.`category_id` AND cr.`listing_id` = :id';

		$sql = iaDb::printf($sql, array(
			'prefix' => $this->_iaDb->prefix,
			'table_categories' => iaCateg::getTable(),
			'table_crossed' => 'listings_categs',
			'id' => $this->getEntryId()
		));

		return $this->_iaDb->getKeyValue($sql);
	}
}