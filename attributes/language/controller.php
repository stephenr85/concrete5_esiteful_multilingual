<?php
namespace Concrete\Package\EsitefulMultilingual\Attribute\Language;

use Concrete\Core\Attribute\Controller as AttributeTypeController;
use Concrete\Core\Attribute\SimpleTextExportableAttributeInterface;
use Concrete\Core\Multilingual\Page\Section\Section;
use Concrete\Core\Entity\Attribute\Key\Settings\EmptySettings;

class Controller extends AttributeTypeController implements SimpleTextExportableAttributeInterface
{

	protected $helpers = [
		'form'
	];

	public function getAttributeKeySettingsClass()
    {
        return EmptySettings::class;
    }

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

	public function getAttributeValueTextRepresentation()
	{
		$vals = [$this->getValue()];
		// TODO
		return implode(',', $vals);
	}

	public function updateAttributeValueFromTextRepresentation($textRepresentation, \Concrete\Core\Error\ErrorList\ErrorList $warnings)
	{
		\Log::addInfo(t(__CLASS__.'::updateAttributeValueFromTextRepresentation: %s', $textRepresentation));
		$vals = explode(',', $textRepresentation);
		//first value is main value
		$data['value'] = array_shift($vals);
		foreach($vals as $relation) {
			$relation = explod('=', $relation);
			if(strpos('{', $relation[1]) !== false) {
				//transform

			}
			$data[$relation[1]] = $relation[0];
		}

		return $data;
	}

	public function importValue(\SimpleXMLElement $akv)
	{
		return $this->updateAttributeValueFromTextRepresentation($akv->value);
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
		$attrValue = $this->getAttributeValue();

		// If the current object is a page, derive its language
		//$ms = Section::getByID($this->request->request->get('section'));
		$akcHandle = $this->getAttributeKeyCategoryHandle();
		$this->set('value', $this->getValue());
		$this->set('akcHandle', $akcHandle);
		$this->set('isValueInferred', $this->isValueInferred());
		$this->set('localeOptions', $this->getAvailableLocaleOptions());
		$this->set('availableLocales', $this->getAvailableLocales());
		
		if(!$attrValue) {
			$this->set('relations', $this->getRelationsHash());
		}		
	}

	public function saveValue($value)
	{
		//throw new Exception(__CLASS__.'::saveValue');
		//dd($value);
		$data = null;
		if(is_array($value)) {
			$data = $value;
			$value = $data['value'];
			unset($data['value']);

			$relationID = $data['relationID'];
			unset($data['relationID']);
		}
		
		if(!$relationID) {
			$relationID = $this->getRelationID();
		}

		$db = \Database::get();
		$db->Replace('atLanguage', [
			'avID' => $this->getAttributeValueID(),
			'value' => $value,
			'relationID' => $relationID
		], 'avID', true);

		if(is_array($data)) {
			$oID = $this->getAttributeValueOwnerID();
			
			$hash = $this->getRelationsHash();
			foreach($data as $lang => $id) {
				$ValueOwnerClass = $this->getAttributeValueOwnerClass();
				if($id > 0) {
					$this->saveRelation($lang, $id);
				} else if(is_object($hash[$lang])) {
					$hash[$lang]->getAttributeValueObject($this->getAttributeKey(), true)->getController()->deleteRelation();
				}
				
			}
		}
	}

	public function saveRelation($language, $owner)
	{
		if(!is_object($owner)) {
			$ValueOwnerClass = $this->getAttributeValueOwnerClass();
			$owner = $ValueOwnerClass::getByID($owner);
		}
		$owner->setAttribute($this->getAttributeKey(), [
			'value'=> $language,
			'relationID' => $this->getRelationID()
		]);	
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

	public function getAttributeValueOwnerReferenceTableName()
	{
		if(!$this->getAttributeValueID()) return null;

		$akcHandle = $this->getAttributeKeyCategoryHandle();
		$metadata = $this->entityManager->getClassMetadata(get_class($this->getAttributeValue()));

		return $metadata->getTableName();
	}

	public function getAttributeValueOwnerReferenceRow()
	{
		if(!$this->getAttributeValueID()) return null;

		$valueRefRow = \Database::get()->GetRow('select * from ' . $this->getAttributeValueOwnerReferenceTableName() .' where avID = ' . $this->getAttributeValueID());
		return $valueRefRow;
	}

	public function getAttributeValueOwnerID()
	{
		$valueRefRow = $this->getAttributeValueOwnerReferenceRow();
		$akcHandle = $this->getAttributeKeyCategoryHandle();

		if($akcHandle == 'collection')
		{
			return $valueRefRow ? $valueRefRow['cID'] : $this->request->get('cID');
		} 
		else if($akcHandle == 'file')
		{
			return $valueRefRow ? $valueRefRow['fID'] : $this->request->get('fID');
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

	public function getAttributeValueOwnerClass()
	{
		$owner = $this->getAttributeValueOwnerObject();
		return get_class($owner);
	}

	public function getRelationID($autoCreate = true)
	{
		$db = \Database::get();
		$avID = $this->getAttributeValueID();

		if($avID) {
			$relationID = $db->GetOne("select relationID from atLanguage where avID = ?", [$avID]);
		}
		
		if(!$relationID && $autoCreate) {
			$relationID = (new \Doctrine\ORM\Id\UuidGenerator())->generate($this->entityManager, $this->getAttributeValueID());
		}
		return $relationID;
	}

	public function setRelationID($relationID)
	{
		$db = \Database::get();
		$db->Execute('update atLanguage where avID = ? set (relationID = ?)', [
			$this->getAttributeValueID(),
			$relationID
		]);
	}

	public function deleteRelation()
	{
		$this->setRelationID(null);
	}

	public function getRelationOwnerIDs()
	{
		$avID = $this->getAttributeValueID();
		if(!$avID) return [];

		$db = \Database::get();	
		$valueReferences = $db->GetAll("select * from ". $this->getAttributeValueOwnerReferenceTableName() ." avRefTable right join (select avID from atLanguage where relationID = ? and avID <> ?) atLanguage on avRefTable.avID = atLanguage.avID", array($this->getRelationID(), $this->getAttributeValueID()));
		//$relations = $this->getAttributeKeyCategory()->getAttributeValueRepository()->findBy(['avID' => $relationValueIDs]);
		// GET OBJECTS BY avID
		
		$akcHandle = $this->getAttributeKeyCategoryHandle();
		$relations = [];
		foreach($valueReferences as $valueRef) {
			if($akcHandle == 'collection') {
				$relations[] = $valueRef['cID'];
			} else if ($akcHandle == 'file') {
				$relations[] = $valueRef['fID'];
			} else if ($akcHandle == 'event') {
				throw new \Exception('event not implemented for language attribute');
			}
		}

		return $relations;
	}

	public function getRelationsHash($asObjects = true)
	{
		$relations = $this->getRelationOwnerIDs();
		$ValueOwnerClass = $this->getAttributeValueOwnerClass();
		$hash = [];
		foreach($relations as $relationOwnerID){
			$relationObject = $ValueOwnerClass::getByID($relationOwnerID);
			$lang = $relationObject->getAttribute($this->getAttributeKey()->getAttributeKeyHandle());
			$hash[$lang] = $asObjects ? $relationObject : $relationOwnerID;
		}
		return $hash;
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