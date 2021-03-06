<?php
namespace wcf\acp\form;
use wcf\data\package\installation\queue\PackageInstallationQueue;
use wcf\data\package\installation\queue\PackageInstallationQueueEditor;
use wcf\data\package\Package;
use wcf\form\AbstractForm;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\exception\SystemException;
use wcf\system\exception\UserInputException;
use wcf\system\package\validation\PackageValidationManager;
use wcf\system\package\PackageArchive;
use wcf\system\package\PackageInstallationDispatcher;
use wcf\system\WCF;
use wcf\system\WCFACP;
use wcf\util\FileUtil;
use wcf\util\StringUtil;

/**
 * Shows the package install and update form.
 * 
 * @author	Marcel Werk
 * @copyright	2001-2014 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	acp.form
 * @category	Community Framework
 */
class PackageStartInstallForm extends AbstractForm {
	/**
	 * @see	\wcf\page\AbstractPage::$activeMenuItem
	 */
	public $activeMenuItem = 'wcf.acp.menu.link.package.install';
	
	/**
	 * updated package object
	 * @var	\wcf\system\package\Package
	 */
	public $package = null;
	
	/**
	 * url to the package to download
	 * @var	string
	 */
	public $downloadPackage = '';
	
	/**
	 * data of the uploaded package
	 * @var	array<string>
	 */
	public $uploadPackage = '';
	
	/**
	 * archive of the installation/update package
	 * @var	\wcf\system\package\PackageArchive
	 */
	public $archive = null;
	
	/**
	 * package installation/update queue
	 * @var	\wcf\data\package\installation\queue\PackageInstallationQueue
	 */
	public $queue = null;
	
	/**
	 * location of the package uploaded via style import
	 * @var	string
	 */
	public $stylePackageImportLocation = '';
	
	/**
	 * @see	\wcf\page\IPage::readParameters()
	 */
	public function readParameters() {
		parent::readParameters();
		
		$this->stylePackageImportLocation = WCF::getSession()->getVar('stylePackageImportLocation');
		if ($this->stylePackageImportLocation) {
			$_POST['t'] = WCF::getSession()->getSecurityToken();
		}
	}
	
	/**
	 * @see	\wcf\form\IForm::readFormParameters()
	 */
	public function readFormParameters() {
		parent::readFormParameters();
		
		if (!$this->stylePackageImportLocation) {
			if (isset($_POST['downloadPackage'])) $this->downloadPackage = StringUtil::trim($_POST['downloadPackage']);
			if (isset($_FILES['uploadPackage'])) $this->uploadPackage = $_FILES['uploadPackage'];
		}
	}
	
	/**
	 * @see	\wcf\form\IForm::validate()
	 */
	public function validate() {
		parent::validate();
		
		if ($this->stylePackageImportLocation) {
			$this->archive = new PackageArchive($this->stylePackageImportLocation, $this->package);
			
			try {
				$this->validateArchive('uploadPackage');
			}
			catch (UserInputException $e) {
				WCF::getSession()->unregister('stylePackageImportLocation');
				
				throw $e;
			}
		}
		else if (!empty($this->uploadPackage['name'])) {
			$this->validateUploadPackage();
		}
		else if (!empty($this->downloadPackage)) {
			$this->validateDownloadPackage();
		}
		else {
			throw new UserInputException('uploadPackage');
		}
	}
	
	/**
	 * Validates the upload package input.
	 */
	protected function validateUploadPackage() {
		$this->activeTabMenuItem = 'upload';
		
		if (empty($this->uploadPackage['tmp_name'])) {
			throw new UserInputException('uploadPackage', 'uploadFailed');
		}
		
		// get filename
		$this->uploadPackage['name'] = FileUtil::getTemporaryFilename('package_', preg_replace('!^.*(?=\.(?:tar\.gz|tgz|tar)$)!i', '', basename($this->uploadPackage['name'])));
		
		if (!@move_uploaded_file($this->uploadPackage['tmp_name'], $this->uploadPackage['name'])) {
			throw new UserInputException('uploadPackage', 'uploadFailed');
		}
		
		if (!PackageValidationManager::getInstance()->validate($this->uploadPackage['name'], false)) {
			// TODO: do something
			die("validation failed: " . PackageValidationManager::getInstance()->getExceptionMessage());
		}
		
		$this->package = PackageValidationManager::getInstance()->getPackageValidationArchive()->getPackage();
	}
	
	/**
	 * Validates the download package input.
	 */
	protected function validateDownloadPackage() {
		$this->activeTabMenuItem = 'upload';
		
		if (FileUtil::isURL($this->downloadPackage)) {
			// download package
			$this->archive = new PackageArchive($this->downloadPackage, $this->package);
			
			try {
				$this->downloadPackage = $this->archive->downloadArchive();
			}
			catch (SystemException $e) {
				throw new UserInputException('downloadPackage', 'downloadFailed');
			}
		}
		else {
			// probably local path
			if (!file_exists($this->downloadPackage)) {
				throw new UserInputException('downloadPackage', 'downloadFailed');
			}
			
			$this->archive = new PackageArchive($this->downloadPackage, $this->package);
		}
		
		$this->validateArchive('downloadPackage');
	}
	
	/**
	 * Validates the package archive.
	 * 
	 * @param	string		$type		upload or download package
	 */
	protected function validateArchive($type) {
		// try to open the archive
		try {
			// TODO: Exceptions thrown within openArchive() are discarded, resulting in
			// the meaningless message 'not a valid package'
			$this->archive->openArchive();
		}
		catch (SystemException $e) {
			throw new UserInputException($type, 'noValidPackage');
		}
		
		// validate php requirements
		$errors = PackageInstallationDispatcher::validatePHPRequirements($this->archive->getPhpRequirements());
		if (!empty($errors)) {
			WCF::getTPL()->assign('phpRequirements', $errors);
			throw new UserInputException($type, 'phpRequirements');
		}
		
		// try to find existing package
		$sql = "SELECT	*
			FROM	wcf".WCF_N."_package
			WHERE	package = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute(array($this->archive->getPackageInfo('name')));
		$row = $statement->fetchArray();
		if ($row !== false) {
			$this->package = new Package(null, $row);
		}
		
		// check update or install support
		if ($this->package !== null) {
			WCF::getSession()->checkPermissions(array('admin.system.package.canUpdatePackage'));
			$this->activeMenuItem = 'wcf.acp.menu.link.package';
			
			if (!$this->archive->isValidUpdate($this->package)) {
				throw new UserInputException($type, 'noValidUpdate');
			}
		}
		else {
			WCF::getSession()->checkPermissions(array('admin.system.package.canInstallPackage'));
			
			if (!$this->archive->isValidInstall()) {
				throw new UserInputException($type, 'noValidInstall');
			}
			else if ($this->archive->isAlreadyInstalled()) {
				throw new UserInputException($type, 'uniqueAlreadyInstalled');
			}
			else if ($this->archive->getPackageInfo('isApplication') && $this->archive->hasUniqueAbbreviation()) {
				throw new UserInputException($type, 'noUniqueAbbrevation');
			}
		}
	}
	
	/**
	 * @see	\wcf\form\IForm::save()
	 */
	public function save() {
		parent::save();
		
		// get new process no
		$processNo = PackageInstallationQueue::getNewProcessNo();
		
		// obey foreign key
		$packageID = ($this->package) ? $this->package->packageID : null;
		
		$archive = $this->downloadPackage;
		if ($this->stylePackageImportLocation) {
			$archive = $this->stylePackageImportLocation;
		}
		else if (!empty($this->uploadPackage['tmp_name'])) {
			$archive = $this->uploadPackage['name'];
		}
		
		// insert queue
		$isApplication = PackageValidationManager::getInstance()->getPackageValidationArchive()->getArchive()->getPackageInfo('isApplication');
		$this->queue = PackageInstallationQueueEditor::create(array(
			'processNo' => $processNo,
			'userID' => WCF::getUser()->userID,
			'package' => PackageValidationManager::getInstance()->getPackageValidationArchive()->getArchive()->getPackageInfo('name'),
			'packageName' => PackageValidationManager::getInstance()->getPackageValidationArchive()->getArchive()->getLocalizedPackageInfo('packageName'),
			'packageID' => $packageID,
			'archive' => $archive,
			'action' => ($this->package != null ? 'update' : 'install'),
			'isApplication' => (!$isApplication ? '0' : '1')
		));
		
		$this->saved();
		
		// open queue
		PackageInstallationDispatcher::openQueue(0, $processNo);
	}
	
	/**
	 * @see	\wcf\page\IPage::assignVariables()
	 */
	public function assignVariables() {
		parent::assignVariables();
		
		WCF::getTPL()->assign(array(
			'package' => $this->package,
			'installingImportedStyle' => $this->stylePackageImportLocation != ''
		));
	}
	
	/**
	 * @see	\wcf\page\IPage::show()
	 */
	public function show() {
		if (!WCF::getSession()->getPermission('admin.system.package.canInstallPackage') && !WCF::getSession()->getPermission('admin.system.package.canUpdatePackage')) {
			throw new PermissionDeniedException();
		}
		
		// check master password
		WCFACP::checkMasterPassword();
		
		parent::show();
	}
}
