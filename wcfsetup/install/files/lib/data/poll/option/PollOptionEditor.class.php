<?php
namespace wcf\data\poll\option;
use wcf\data\DatabaseObjectEditor;

/**
 * Extends the poll option object with functions to create, update and delete poll options.
 * 
 * @author	Alexander Ebert
 * @copyright	2001-2014 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	data.poll.option
 * @category	Community Framework
 */
class PollOptionEditor extends DatabaseObjectEditor {
	/**
	 * @see	\wcf\data\DatabaseObjectEditor::$baseClass
	 */
	protected static $baseClass = 'wcf\data\poll\option\PollOption';
}
