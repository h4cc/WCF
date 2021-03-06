<?php
namespace wcf\system\option;
use wcf\data\option\Option;
use wcf\system\exception\UserInputException;
use wcf\system\WCF;

/**
 * Option type implementation for the 'about me' text field.
 *
 * @author	Marcel Werk
 * @copyright	2001-2014 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	system.option
 * @category	Community Framework
 */
class AboutMeOptionType extends MessageOptionType {
	/**
	 * @see	\wcf\system\option\IOptionType::validate()
	 */
	public function validate(Option $option, $newValue) {
		parent::validate($option, $newValue);
	
		if (WCF::getSession()->getPermission('user.profile.aboutMeMaxLength') < mb_strlen($newValue)) {
			throw new UserInputException($option->optionName, 'tooLong');
		}
	}
}
