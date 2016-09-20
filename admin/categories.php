<?php
//##copyright##

class iaBackendController extends iaAbstractControllerPackageBackend
{
	protected $_name = 'categories';

	protected $_helperName = 'categ';

	protected $_gridColumns = array('title', 'title_alias', 'num_all_listings', 'date_added', 'date_modified', 'status');
	protected $_gridFilters = array('title' => self::LIKE, 'status' => self::EQUAL);

	protected $_activityLog = array('item' => 'category');
	protected $_setQuickSearch = false;

	protected $_phraseAddSuccess = 'category_added';
	protected $_phraseGridEntryDeleted = 'category_deleted';

	private $_root;

	public function __construct()
	{
		parent::__construct();

		$this->_root = $this->getHelper()->getRoot();
	}

	protected function _entryAdd(array $entryData)
	{
		$entryData['date_added'] = date(iaDb::DATETIME_FORMAT);

		return $this->getHelper()->insert($entryData);
	}

	protected function _entryUpdate(array $entryData, $entryId)
	{
		$this->getHelper()->update($entryData, $entryId);

		return (0 === $this->_iaDb->getErrorNumber());
	}

	protected function _entryDelete($entryId)
	{
		return (bool)$this->getHelper()->delete($entryId);
	}

	protected function _setDefaultValues(array &$entry)
	{
		$entry = array(
			'member_id' => iaUsers::getIdentity()->id,
			'parent_id' => $this->_root['id'],
			'parents' => 0,
			'locked' => 0,
			'featured' => false,
			'icon' => false,
			'status' => iaCore::STATUS_ACTIVE,
			'title_alias' => ''
		);
	}

	protected function _preSaveEntry(array &$entry, array $data, $action)
	{
		$fields = $this->_iaField->getByItemName($this->getHelper()->getItemName());
		list($entry, , $this->_messages, ) = $this->_iaField->parsePost($fields, $entry);

		$entry['parent_id'] = $data['parent_id'] ? (int)$data['parent_id'] : $this->_root['id'];
		$entry['locked'] = (int)$data['locked'];
		$entry['status'] = $data['status'];
		$entry['title_alias'] = empty($_POST['title_alias']) ? htmlspecialchars_decode($data['title']) : $data['title_alias'];
		$entry['title_alias'] = $this->getHelper()->getTitleAlias($entry);
		$entry['order'] = $this->_iaDb->getMaxOrder() + 1;

		if ($this->getHelper()->exists($entry['title_alias'], $entry['parent_id'], $this->getEntryId()))
		{
			$this->addMessage('directory_category_already_exists');
		}

		return !$this->getMessages();
	}

	protected function _assignValues(&$iaView, array &$entryData)
	{
		parent::_assignValues($iaView, $entryData);

		$entryData['title_alias'] = end(explode(IA_URL_DELIMITER, $entryData['title_alias']));

		$parent = $this->_iaDb->row(array('id', 'title', 'parents', 'child'), iaDb::convertIds($entryData['parent_id']));

		$iaView->assign('parent', $parent);
	}
}