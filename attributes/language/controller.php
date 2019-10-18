<?php
namespace Concrete\Package\EsitefulMultilingual\Attribute\Language;

use Concrete\Core\Multilingual\Page\Section\Section;

class Controller extends \Concrete\Attribute\Text\Controller
{

	public function getValue($mode = false)
	{		
		if($this->isValueInferred()) {
			$value = $this->getInferredValue();
		} else {
			$value = $this->getRawValue();
		}		
		return $value;
	}

	public function getRawValue()
	{
		$db = \Database::get();
		return $db->GetOne("select value from atLanguage where avID = ?", array($this->getAttributeValueID()));
	}

	public function getInferredValue()
	{
		$akcHandle = $this->getAttributeKeyCategoryHandle();
		if($akcHandle == 'collection') {
			$page = $this->getAttributeValueOwnerObject();
			return Section::getBySectionOfSite($page)->getLocale();
		}
		return null;
	}

	public function isValueInferred()
	{
		return in_array($this->getAttributeKeyCategoryHandle(), ['collection']);
	}

	public function inc($fileToInclude, $args = [])
    {
        extract($args);
        extract($this->getSets());
        $env = \Environment::get();
        include $env->getPath(
            DIRNAME_ATTRIBUTES . '/' . $this->attributeType->getAttributeTypeHandle() . '/' . $fileToInclude,
            $this->attributeType->getPackageHandle()
        );
    }

	public function form()
	{
		// If the current object is a page, derive its language
		//$ms = Section::getByID($this->request->request->get('section'));
		$akcHandle = $this->getAttributeKeyCategoryHandle();
		$this->set('value', $this->getValue());
		$this->set('akcHandle', $akcHandle);
		$this->set('isValueInferred', $this->isValueInferred());
		$this->set('localeOptions', $this->getAvailableLocaleOptions());
		$this->set('availableLocales', $this->getAvailableLocales());
	}

	public function saveValue($value)
	{
		//throw new Exception(__CLASS__.'::saveValue');
		
		$db = \Database::get();
		$db->Replace('atLanguage', array('avID' => $this->getAttributeValueID(), 'value' => $value), 'avID', true);


	}
	
	public function saveForm($data)
	{
		return $this->saveValue($data);
	}

	public function getSite()
	{
		return $this->app->make('site')->getSite();
	}

	public function getAttributeKeyCategory(){
		return $this->getAttributeKey()->getAttributeCategory();
	}

	public function getAttributeKeyCategoryHandle(){
		return $this->getAttributeKey()->getAttributeKeyCategoryHandle();
	}

	public function getAttributeValueOwnerReferenceRow()
	{
		$akcHandle = $this->getAttributeKeyCategoryHandle();
		$metadata = $this->entityManager->getClassMetadata(get_class($this->getAttributeValue()));

		$valueRefRow = \Database::get()->GetRow('select * from ' . $metadata->getTableName() .' where avID = ' . $this->getAttributeValueID());
		return $valueRefRow;
	}

	public function getAttributeValueOwnerID()
	{
		$valueRefRow = $this->getAttributeValueOwnerReferenceRow();
		$akcHandle = $this->getAttributeKeyCategoryHandle();

		if($akcHandle == 'collection')
		{
			return $valueRefRow['cID'];
		} 
		else if($akcHandle == 'file')
		{
			return $valueRefRow['fID'];
		}
	}

	public function getAttributeValueOwnerObject()
	{
		
		$akcHandle = $this->getAttributeKeyCategoryHandle();
		$oID = $this->getAttributeValueOwnerID();
		if($akcHandle == 'collection')
		{
			return \Page::getByID($oID);
		}
		else if($akcHandle == 'file') {
			return \File::getByID($oID);
		}
		else {
			throw Exception('Language attribute not implemented for '.$akcHandle);
		}
	}

	public function getAvailableLocaleOptions()
	{
		$locales = $this->getAvailableLocales();
		$options = [];
		foreach($locales as $locale) {
			$options[$locale->getLocale()] = $locale->getLanguageText();
		}
		return $options;
	}

	public function getAvailableLocales()
	{
		$site = $this->getSite();
		return $site->getLocales();

		foreach ($site->getLocales() as $_locale) {
            if ($_locale->getLocale() == $localeCode) {
                $locale = $_locale;
            }
        }
	}
}