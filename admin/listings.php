<?php
//##copyright##

class iaBackendController extends iaAbstractControllerPackageBackend
{
	protected $_name = 'listings';

	protected $_helperName = 'listing';

	protected $_phraseAddSuccess = 'listing_added';

	protected $_activityLog = array('item' => 'listing');

	public function init()
	{
		$this->_iaCateg = $this->_iaCore->factoryPackage('categ', $this->getPackageName(), iaCore::ADMIN);
	}

	public function _gridRead($params, array $filterParams = array(), array $persistentConditions = array())
	{
		$params || $params = array();

		$start = isset($params['start']) ? (int)$params['start'] : 0;
		$limit = isset($params['limit']) ? (int)$params['limit'] : 15;

		$sort = $params['sort'];
		$dir = in_array($params['dir'], array(iaDb::ORDER_ASC, iaDb::ORDER_DESC)) ? $params['dir'] : iaDb::ORDER_ASC;
		$order = ($sort && $dir) ? "`{$sort}` {$dir}" : '';

		$where = $values = array();
		foreach ($filterParams as $name => $type)
		{
			if (isset($params[$name]) && $params[$name])
			{
				$value = iaSanitize::sql($params[$name]);

				switch ($type)
				{
					case 'equal':
						$where[] = sprintf('t1.`%s` = :%s', $name, $name);
						$values[$name] = $value;
						break;
					case 'like':
						$where[] = sprintf('t1.`%s` LIKE :%s', $name, $name);
						$values[$name] = '%' . $value . '%';
				}
			}
		}

		$where = array_merge($where, $persistentConditions);
		$where || $where[] = iaDb::EMPTY_CONDITION;
		$where = implode(' AND ', $where);
		$this->_iaDb->bind($where, $values);

		return array(
			'data' => $this->getHelper()->get($where, $start, $limit, $order),
			'total' => (int)$this->_iaDb->foundRows()
		);
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
		$fields = $this->_iaField->getByItemName($this->getHelper()->getItemName());
		list($entry, , $this->_messages, ) = $this->_iaField->parsePost($fields, $entry);

		if (isset($data['reported_as_broken']))
		{
			$entry['reported_as_broken'] = $_POST['reported_as_broken'];
			if (!$data['reported_as_broken'])
			{
				$entry['reported_as_broken_comments'] = '';
			}
		}

		$entry['domain'] = $this->getHelper()->getDomain($entry['url']);

		$entry['rank'] = min(5, max(0, (int)$data['rank']));
		$entry['category_id'] = (int)$data['category_id'];
		$entry['title_alias'] = empty($data['title_alias']) ? $data['title'] : $data['title_alias'];
		$entry['title_alias'] = $this->getHelper()->titleAlias($entry['title_alias']);

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
		if (isset($data['crossed_links']))
		{
			$crossed = $data['crossed_links'];
			unset($data['crossed_links']);
		}

		if (isset($crossed) && $crossed)
		{
			$listing = $this->getHelper()->getById((int)$this->_iaCore->requestPath[0]);

			$this->_iaDb->setTable($this->getHelper()->getTableCrossed());

			$this->_iaDb->delete(iaDb::convertIds($listing['id'], 'listing_id'));

			$crossedLimit = $this->_iaCore->get('listing_crossed_limit', 5);

			if (!is_array($crossed))
			{
				$crossed = explode(',', $crossed);
			}

			$count = count($crossed) > $crossedLimit ? $crossedLimit : count($crossed);
			$crossedInput = array();

			for ($i = 0; $i < $count; $i++)
			{
				if ($crossed[$i] != $data['category_id'])
				{
					$crossedInput[] = array('listing_id' => $listing['id'], 'category_id' => (int)$crossed[$i]);
				}
			}

			if (count($crossedInput) > 0)
			{
				$this->_iaDb->insert($crossedInput);
			}

			$this->_iaDb->resetTable();
		}
	}

	protected function _assignValues(&$iaView, array &$entryData)
	{
		parent::_assignValues($iaView, $entryData);

		$category = $this->_iaDb->row(array('id', 'title', 'parent_id', 'parents'), iaDb::convertIds($entryData['category_id']), $this->getHelper()->getTable());

		if (!empty($this->_iaCore->requestPath[0])) {
			$listing = $this->getHelper()->getById((int)$this->_iaCore->requestPath[0]);

			$crossed = $this->_iaDb->getAll("SELECT t.`id`, t.`title`
				FROM `{$this->_iaCore->iaDb->prefix}categs` t, `{$this->_iaCore->iaDb->prefix}listings_categs` cr
				WHERE t.`id` = cr.`category_id` AND cr.`listing_id` = '{$listing['id']}'");

			$category['crossed'] = array();

			foreach ($crossed as $val)
			{
				$category['crossed'][$val['id']] = $val['title'];
			}
		}

		$iaView->assign('category', $category);
		$iaView->assign('statuses', $this->getHelper()->getStatuses());
	}
}