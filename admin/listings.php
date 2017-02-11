<?php
//##copyright##

class iaBackendController extends iaAbstractControllerPackageBackend
{
	protected $_name = 'listings';

	protected $_helperName = 'listing';

	protected $_gridColumns = ['title', 'title_alias', 'url', 'date_added', 'date_modified', 'reported_as_broken', 'reported_as_broken_comments', 'status'];
	protected $_gridFilters = ['title' => self::LIKE, 'status' => self::EQUAL];
	protected $_gridQueryMainTableAlias = 'l';

	protected $_tooltipsEnabled = true;

	protected $_phraseAddSuccess = 'listing_added';

	protected $_activityLog = ['item' => 'listing'];

	private $_iaCateg;


	public function init()
	{
		$this->_iaCateg = $this->_iaCore->factoryModule('categ', $this->getPackageName(), iaCore::ADMIN);
	}

	protected function _modifyGridParams(&$conditions, &$values, array $params)
	{
		if (!empty($params['text']))
		{
			$langCode = $this->_iaCore->language['iso'];
			$conditions[] = "(l.`title_{$langCode}` LIKE :text OR l.`description_{$langCode}` LIKE :text)";
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
		$result = parent::_entryUpdate($entryData, $entryId);
		!$result || $this->getHelper()->sendUserNotification($entryData, $entryId);

		return $result;
	}

	protected function _entryDelete($entryId)
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
		$entry['category_id'] = (int)$data['tree_id'];

		$entry['title_alias'] = empty($data['title_alias']) ? $data['title'][$this->_iaCore->language['iso']] : $data['title_alias'];
		$entry['title_alias'] = $this->_getTitleAlias($entry['title_alias']);

		if (iaValidate::isUrl($entry['url']))
		{
			// check alexa
			if ($this->_iaCore->get('directory_enable_alexarank'))
			{
				include IA_MODULES . 'directory' . IA_DS . 'includes' . IA_DS . 'alexarank.inc.php';

				if ($alexaData = (new iaAlexaRank())->getAlexa($entry['domain']))
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
				$this->_iaDb->insert(['listing_id' => $this->getEntryId(), 'category_id' => $categoryId]);
				$this->_iaCateg->changeNumListing($categoryId);
			}
		}

		$this->_iaDb->resetTable();
	}

	public function updateCounters($entryId, array $entryData, $action, $previousData = null)
	{
		switch ($action)
		{
			case iaCore::ACTION_EDIT:
				if ($entryData['category_id'] == $previousData['category_id'])
				{
					if (iaCore::STATUS_ACTIVE == $previousData['status'] && iaCore::STATUS_ACTIVE != $entryData['status'])
					{
						$this->_iaCateg->changeNumListing($entryData['category_id'], -1);
					}
					elseif (iaCore::STATUS_ACTIVE != $previousData['status'] && iaCore::STATUS_ACTIVE == $entryData['status'])
					{
						$this->_iaCateg->changeNumListing($entryData['category_id']);
					}
				}
				else // category changed
				{
					iaCore::STATUS_ACTIVE == $entryData['status']
						&& $this->_iaCateg->changeNumListing($entryData['category_id']);
					iaCore::STATUS_ACTIVE == $previousData['status']
						&& $this->_iaCateg->changeNumListing($previousData['category_id'], -1);
				}
				break;
			case iaCore::ACTION_ADD:
				$entryData['status'] == iaCore::STATUS_ACTIVE
					&& $this->_iaCateg->changeNumListing($entryData['category_id']);
				break;
			case iaCore::ACTION_DELETE:
				$entryData['status'] == iaCore::STATUS_ACTIVE
					&& $this->_iaCateg->changeNumListing($entryData['category_id'], -1);
		}
	}

	protected function _assignValues(&$iaView, array &$entryData)
	{
		parent::_assignValues($iaView, $entryData);

		$category = $this->_iaCateg->getById($entryData['category_id']);
		$crossed = $this->_fetchCrossedCategories();

		$entryData['parents'] = $category['parents'];

		$iaView->assign('parent', $category);
		$iaView->assign('crossed', $crossed);
		$iaView->assign('statuses', $this->getHelper()->getStatuses());
	}


	protected function _fetchCrossedCategories()
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
			'id' => $this->getEntryId()
		]);

		return $this->_iaDb->getKeyValue($sql);
	}

	protected function _getTitleAlias($title, $convertLowercase = false)
	{
		$title = iaSanitize::alias($title);

		if ($this->_iaCore->get('directory_lowercase_urls', true) && !$convertLowercase)
		{
			$title = strtolower($title);
		}

		return $title;
	}

	protected function _getJsonSlug(array $data)
	{
		$title = $this->_getTitleAlias(isset($data['title']) ? $data['title'] : '', isset($data['alias']));
		$categorySlug = empty($data['category'])
			? ''
			: $this->_iaDb->one('title_alias', iaDb::convertIds($data['category']), iaCateg::getTable());

		$slug = $this->getHelper()->url('view', [
			'id' => empty($data['id']) ? $this->_iaDb->getNextId() : $data['id'],
			'title_alias' => $title,
			'category_alias' => $categorySlug
		]);

		return ['data' => $slug];
	}
}