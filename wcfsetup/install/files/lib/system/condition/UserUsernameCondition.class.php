<?php
namespace wcf\system\condition;
use wcf\data\condition\Condition;
use wcf\data\user\User;
use wcf\data\user\UserList;
use wcf\system\WCF;
use wcf\util\StringUtil;

/**
 * Condition implementation for the username of a user.
 * 
 * @author	Matthias Schmidt
 * @copyright	2001-2014 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	system.condition
 * @category	Community Framework
 */
class UserUsernameCondition extends AbstractTextCondition implements INoticeCondition, IUserCondition {
	/**
	 * @see	\wcf\system\condition\AbstractTextCondition::$fieldName
	 */
	protected $fieldName = 'username';
	
	/**
	 * @see	\wcf\system\condition\AbstractSingleFieldCondition::$label
	 */
	protected $label = 'wcf.user.username';
	
	/**
	 * @see	\wcf\system\condition\IUserCondition::addUserCondition()
	 */
	public function addUserCondition(Condition $condition, UserList $userList) {
		$userList->getConditionBuilder()->add('user_table.username LIKE ?', array('%'.addcslashes($condition->username, '_%').'%'));
	}
	
	/**
	 * @see	\wcf\system\condition\IUserCondition::checkUser()
	 */
	public function checkUser(Condition $condition, User $user) {
		return mb_strpos($user->username, $condition->username) !== false;
	}
	
	/**
	 * @see	\wcf\system\condition\INoticeCondition::showNotice()
	 */
	public function showNotice(Condition $condition) {
		if (!WCF::getUser()->userID) return false;
		
		return $this->checkUser($condition, WCF::getUser());
	}
}
