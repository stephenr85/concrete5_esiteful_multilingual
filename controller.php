<?php

	namespace Concrete\Package\EsitefulMultilingual;

	use \Loader;
	use Route;
	use \Events;

	use Package;
	use Concrete\Core\Page\Page;

	use Concrete\Package\EsitefulMultilingual\Helpers\PackageHelper;

	/**
	 * This is the main controller for the package which controls the functionality like Install/Uninstall etc.
	 *
	 * @author Stephen Rushing, eSiteful
	 */
	class Controller extends Package {

	/**
	* Protected data members for controlling the instance of the package
	*/
	protected $pkgHandle = 'esiteful_multilingual';
	protected $appVersionRequired = '8.0.1';
	protected $pkgVersion = '0.0.1';

	/**
	 * This function returns the functionality description ofthe package.
	 *
	 * @param void
	 * @return string $description
	 * @author Stephen Rushing, eSiteful
	 */
	public function getPackageDescription()
	{
	    return t("Custom package for multilingual functionality.");
	}

	/**
	 * This function returns the name of the package.
	 *
	 * @param void
	 * @return string $name
	 * @author Stephen Rushing, eSiteful
	 */
	public function getPackageName()
	{
	    return t("eSiteful Multilingual");
	}

	public function getPackageHelper()
	{
		$pkg = Package::getByHandle($this->getPackageHandle());
		$helper = new PackageHelper($pkg);
		$helper->setApplication($this->app);
		return $helper;
	}




	public function on_start(){

		$this->setupAutoloader();


        \Events::addListener('on_multilingual_page_relate', function($event) {

            $page = $event->getPageObject();
            //$page->getAttributeValueObject('page_language', true)->getController()->pullMultilingualData();

        });
	}

	/**
     * Configure the autoloader
     */
    private function setupAutoloader()
    {
        if (file_exists($this->getPackagePath() . '/vendor')) {
            require_once $this->getPackagePath() . '/vendor/autoload.php';
        }
    }

	/**
	 * This function is executed during initial installation of the package.
	 *
	 * @param void
	 * @return void
	 * @author Stephen Rushing, eSiteful
	 */
	public function install()
	{
		$this->setupAutoloader();

	    $pkg = parent::install();

	    // Install Package Items
	    $this->install_attribute_types($pkg);
	    $this->install_file_attributes($pkg);
	    $this->install_page_attributes($pkg);
	}

	/**
	 * This function is executed during upgrade of the package.
	 *
	 * @param void
	 * @return void
	 * @author Stephen Rushing, eSiteful
	 */
	public function upgrade()
	{
		parent::upgrade();
		$pkg = Package::getByHandle($this->getPackageHandle());
	    // Install Package Items
	    $this->install_attribute_types($pkg);
	    $this->install_file_attributes($pkg);
	    $this->install_page_attributes($pkg);
	}

	/**
	 * This function is executed during uninstallation of the package.
	 *
	 * @param void
	 * @return void
	 * @author Stephen Rushing, eSiteful
	 */
	public function uninstall()
	{
	    $pkg = parent::uninstall();
	}


	/**
	 * This function is used to install attribute types.
	 *
	 * @param type $pkg
	 * @return void
	 * @author Stephen Rushing, eSiteful
	 */
	function install_attribute_types($pkg){
		$pkgHelper = $this->getPackageHelper();

		$pkgHelper->upsertAttributeType('language', t('Language'), ['collection', 'file', 'event']);
	}


	/**
	 * This function is used to install file attributes.
	 *
	 * @param type $pkg
	 * @return void
	 * @author Stephen Rushing, eSiteful
	 */
	function install_file_attributes($pkg){
		$pkgHelper = $this->getPackageHelper();

		$pkgHelper->upsertAttributeKey('file', null, 'language', array(
			'akHandle'=>'file_language',
			'akName'=>t('Language'),
		));

	}




	/**
	 * This function is used to install page attributes.
	 *
	 * @param type $pkg
	 * @return void
	 * @author Stephen Rushing, eSiteful
	 */
	function install_page_attributes($pkg){
		$pkgHelper = $this->getPackageHelper();

		$pkgHelper->upsertAttributeKey('collection', null, 'language', array(
			'akHandle'=>'page_language',
			'akName'=>t('Language'),
		));

	}



}
