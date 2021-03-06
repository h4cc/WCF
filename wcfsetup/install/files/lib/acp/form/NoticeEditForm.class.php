<?php
namespace wcf\acp\form;
use wcf\data\notice\Notice;
use wcf\data\notice\NoticeAction;
use wcf\data\notice\NoticeEditor;
use wcf\form\AbstractForm;
use wcf\system\condition\ConditionHandler;
use wcf\system\language\I18nHandler;
use wcf\system\exception\IllegalLinkException;
use wcf\system\user\storage\UserStorageHandler;
use wcf\system\WCF;

/**
 * Shows the form to edit an existing notice.
 * 
 * @author	Matthias Schmidt
 * @copyright	2001-2014 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	acp.form
 * @category	Community Framework
 */
class NoticeEditForm extends NoticeAddForm {
	/**
	 * @see	\wcf\page\AbstractPage::$activeMenuItem
	 */
	public $activeMenuItem = 'wcf.acp.menu.link.notice';
	
	/**
	 * edited notice object
	 * @var	\wcf\data\notice\Notice
	 */
	public $notice = null;
	
	/**
	 * id of the edited notice object
	 * @var	integer
	 */
	public $noticeID = 0;
	
	/**
	 * 1 if the notice will be displayed for all users again
	 * @var unknown
	 */
	public $resetIsDismissed = 0;
	
	/**
	 * @see	\wcf\page\IPage::assignVariables()
	 */
	public function assignVariables() {
		parent::assignVariables();
		
		I18nHandler::getInstance()->assignVariables(!empty($_POST));
		
		WCF::getTPL()->assign(array(
			'action' => 'edit',
			'notice' => $this->notice,
			'resetIsDismissed' => $this->resetIsDismissed
		));
	}
	
	/**
	 * @see	\wcf\page\IPage::readData()
	 */
	public function readData() {
		parent::readData();
		
		if (empty($_POST)) {
			I18nHandler::getInstance()->setOptions('notice', 1, $this->notice->notice, 'wcf.notice.notice.notice\d+');
			
			$this->isDisabled = $this->notice->isDisabled;
			$this->isDismissible = $this->notice->isDismissible;
			$this->noticeName = $this->notice->noticeName;
			$this->noticeUseHtml = $this->notice->noticeUseHtml;
			$this->showOrder = $this->notice->showOrder;
			
			$conditions = $this->notice->getConditions();
			$conditionsByObjectTypeID = array();
			foreach ($conditions as $condition) {
				$conditionsByObjectTypeID[$condition->objectTypeID] = $condition;
			}
			
			foreach ($this->groupedConditionObjectTypes as $objectTypes1) {
				foreach ($objectTypes1 as $objectTypes2) {
					if (is_array($objectTypes2)) {
						foreach ($objectTypes2 as $objectType) {
							if (isset($conditionsByObjectTypeID[$objectType->objectTypeID])) {
								$conditionsByObjectTypeID[$objectType->objectTypeID]->getObjectType()->getProcessor()->setData($conditionsByObjectTypeID[$objectType->objectTypeID]);
							}
						}
					}
					else if (isset($conditionsByObjectTypeID[$objectTypes2->objectTypeID])) {
						$conditionsByObjectTypeID[$objectTypes2->objectTypeID]->getObjectType()->getProcessor()->setData($conditionsByObjectTypeID[$objectTypes2->objectTypeID]);
					}
				}
			}
		}
	}
	
	/**
	 * @see	\wcf\form\IForm::readFormParameters()
	 */
	public function readFormParameters() {
		parent::readFormParameters();
		
		if (isset($_POST['resetIsDismissed'])) $this->resetIsDismissed = 1;
	}
	
	/**
	 * @see	\wcf\page\IPage::readParameters()
	 */
	public function readParameters() {
		parent::readParameters();
		
		if (isset($_REQUEST['id'])) $this->noticeID = intval($_REQUEST['id']);
		$this->notice = new Notice($this->noticeID);
		if (!$this->notice->noticeID) {
			throw new IllegalLinkException();
		}
	}
	
	/**
	 * @see	\wcf\form\IForm::save()
	 */
	public function save() {
		AbstractForm::save();
		
		$this->objectAction = new NoticeAction(array($this->notice), 'update', array(
			'data' => array(
				'isDisabled' => $this->isDisabled,
				'isDismissible' => $this->isDismissible,
				'notice' => I18nHandler::getInstance()->isPlainValue('notice') ? I18nHandler::getInstance()->getValue('notice') : 'wcf.notice.notice.notice'.$this->notice->noticeID,
				'noticeName' => $this->noticeName,
				'noticeUseHtml' => $this->noticeUseHtml,
				'showOrder' => $this->showOrder
			)
		));
		$this->objectAction->executeAction();
		
		if (I18nHandler::getInstance()->isPlainValue('notice')) {
			if ($this->notice->notice == 'wcf.notice.notice.notice'.$this->notice->noticeID) {
				I18nHandler::getInstance()->remove($this->notice->notice);
			}
		}
		else {
			I18nHandler::getInstance()->save('notice', 'wcf.notice.notice.notice'.$this->notice->noticeID, 'wcf.notice', 1);
		}
		
		// transform conditions array into one-dimensional array
		$conditions = array();
		foreach ($this->groupedConditionObjectTypes as $groupedObjectTypes) {
			foreach ($groupedObjectTypes as $objectTypes) {
				if (is_array($objectTypes)) {
					$conditions = array_merge($conditions, $objectTypes);
				}
				else {
					$conditions[] = $objectTypes;
				}
			}
		}
		
		ConditionHandler::getInstance()->updateConditions($this->notice->noticeID, $this->notice->getConditions(), $conditions);
		
		if ($this->resetIsDismissed) {
			$sql = "DELETE FROM	wcf".WCF_N."_notice_dismissed
				WHERE		noticeID = ?";
			$statement = WCF::getDB()->prepareStatement($sql);
			$statement->execute(array(
				$this->notice->noticeID
			));
			
			$this->resetIsDismissed = 0;
			
			UserStorageHandler::getInstance()->resetAll('dismissedNotices');
		}
		
		$this->saved();
		
		// reload notice object for proper 'isDismissible' value
		$this->notice = new Notice($this->noticeID);
		
		WCF::getTPL()->assign('success', true);
	}
}
