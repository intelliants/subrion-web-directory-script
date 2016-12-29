<?php
//##copyright##

class iaBackendController extends iaAbstractControllerPackageBackend
{
	protected $_name = 'categories';

	protected $_helperName = 'categ';

	protected $_gridColumns = array('title', 'title_alias', 'num_all_listings', 'locked', 'date_added', 'date_modified', 'status');
	protected $_gridFilters = array('title' => self::LIKE, 'status' => self::EQUAL);

	protected $_activityLog = array('item' => 'category');

	protected $_phraseAddSuccess = 'category_added';
	protected $_phraseGridEntryDeleted = 'category_deleted';

	private $_root;


	public function init()
	{
		$this->_root = $this->getHelper()->getRoot();
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

		if (empty($entryId) && '0' !== $entryId)
		{
			return false;
		}

		$currentData = $this->getById($entryId);

		if (empty($currentData))
		{
			return false;
		}

		$result = $this->_update($entryData, $entryId);

		if ($result)
		{
			$this->_writeLog(iaCore::ACTION_EDIT, $entryData, $entryId);
			$this->updateCounters($entryId, $entryData, iaCore::ACTION_EDIT, $currentData);

			$this->_iaCore->startHook('phpListingUpdated', array(
				'itemId' => $entryId,
				'itemName' => $this->getItemName(),
				'itemData' => $entryData,
				'previousData' => $currentData
			));
		}

		return $result;
	}

	protected function _entryDelete($entryId)
	{
		return ($this->_root['id'] == $entryId) ? false : (bool)$this->getHelper()->delete($entryId);
	}

	protected function _setDefaultValues(array &$entry)
	{
		$entry = array(
			'parent_id' => $this->_root['id'],
			'parents' => 0,
			'locked' => false,
			'featured' => false,
			'status' => iaCore::STATUS_ACTIVE,
			'title_alias' => ''
		);
	}

	protected function _preSaveEntry(array &$entry, array $data, $action)
	{
		parent::_preSaveEntry($entry, $data, $action);

		$entry['parent_id'] = empty($data['parent_id']) ? $this->_root['id'] : $data['parent_id'];
		$entry['locked'] = (int)$data['locked'];
		$entry['status'] = $data['status'];
		$entry['order'] = $this->_iaDb->getMaxOrder() + 1;

		$entry['title_alias'] = empty($data['title_alias']) ? $data['title'] : $data['title_alias'];
		$entry['title_alias'] = $this->getHelper()->getTitleAlias($entry);

		if ($this->getHelper()->exists($entry['title_alias'], $entry['parent_id'], $this->getEntryId()))
		{
			$this->addMessage('directory_category_already_exists');
		}

		return !$this->getMessages();
	}

	protected function _postSaveEntry(array &$entry, array $data, $action)
	{
		$this->_iaDb->setTable($this->getHelper()->getTableCrossed());

		if (iaCore::ACTION_EDIT == $action)
		{
			$this->_iaDb->delete(iaDb::convertIds($this->getEntryId(), 'category_id'));
		}

		if (!empty($data['crossed']))
		{
			foreach (explode(',', $data['crossed']) as $id)
			{
				if (!$id) continue;
				$this->_iaDb->insert(array('category_id' => $this->getEntryId(), 'crossed_id' => $id));
			}
		}

		$this->_iaDb->resetTable();
	}

	public function updateCounters($entryId, array $entryData, $action, $previousData = null)
	{
		$this->getHelper()->rebuildRelation();
		$this->getHelper()->updateAliases($entryId);
	}

	protected function _assignValues(&$iaView, array &$entryData)
	{
		parent::_assignValues($iaView, $entryData);

		$entryData['title_alias'] = end(explode(IA_URL_DELIMITER, substr($entryData['title_alias'], 0, -1)));

		$parent = $this->_iaDb->row(array('id', 'title', 'parents', 'child'), iaDb::convertIds($entryData['parent_id']));

		$entryData['crossed'] = $this->_fetchCrossed();

		$iaView->assign('parent', $parent);
	}

	protected function _fetchCrossed()
	{
		$sql = 'SELECT c.`id`, c.`title` '
			. 'FROM `:prefix:table_categories` c, `:prefix:table_crossed` cr '
			. 'WHERE c.`id` = cr.`crossed_id` AND cr.`category_id` = :id';

		$sql = iaDb::printf($sql, array(
			'prefix' => $this->_iaDb->prefix,
			'table_categories' => self::getTable(),
			'table_crossed' => $this->getHelper()->getTableCrossed(),
			'id' => $this->getEntryId()
		));

		return $this->_iaDb->getKeyValue($sql);
	}

	protected function _getJsonConsistency(array $params)
	{
		$output = array();

		if (isset($_POST['action']))
		{
			switch ($_POST['action'])
			{
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