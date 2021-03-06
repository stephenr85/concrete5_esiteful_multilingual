<?php
namespace Concrete\Package\EsitefulMultilingual\Attribute\Language;

use Core;
use Concrete\Core\Attribute\Controller as AttributeTypeController;
use Concrete\Core\Attribute\SimpleTextExportableAttributeInterface;
use Concrete\Core\Multilingual\Page\Section\Section;
use Concrete\Core\Entity\Attribute\Key\Settings\EmptySettings;
use Concrete\Core\File\File;
use Concrete\Core\Page\Page;
use Concrete\Core\Backup\ContentImporter;
use Concrete\Core\Search\ItemList\Database\AttributedItemList;

class Controller extends AttributeTypeController implements SimpleTextExportableAttributeInterface
{

	protected $helpers = [
		'form'
	];

	protected $searchIndexFieldDefinition = [
        'code' => [
            'type' => 'string',
            'options' => ['length' => '32', 'default' => '', 'notnull' => false],
        ],
        'relation_id' => [
            'type' => 'guid',
            'options' => ['notnull' => false],
        ],
    ];

	public function getAttributeKeySettingsClass()
    {
        return EmptySettings::class;
    }

	public function getValue($mode = false)
	{
		$value = $this->getInferredValue();
        if($value === false) {
            $value = $this->getRawValue();
        }
		return $value;
	}

	public function getDisplayValue()
	{
		return $this->getValue();
	}

	public function getRawValue()
	{
		$db = \Database::get();
		$avID = $this->getAttributeValueID();
		$value = $db->GetOne("select value from atLanguage where avID = ?", array($avID));
		return $value;
	}

	public function getInferredValue()
	{
		$akcHandle = $this->getAttributeKeyCategoryHandle();
		if($akcHandle == 'collection') {
			$page = $this->getAttributeValueOwnerObject();
            $section = Section::getBySectionOfSite($page);
			return is_object($section) ? $section->getLocale() : false;
		}
		return false;
	}

	public function isValueInferred()
	{
		return in_array($this->getAttributeKeyCategoryHandle(), ['collection']);
	}

	public function getSearchIndexValue()
	{
		return [
			'code' => $this->getValue(),
			'relation_id' => $this->getRelationID()
		];
	}

    public function filterByAttribute(AttributedItemList $list, $value, $comparison = '=')
    {
        $query = $list->getQueryObject();
        $column = 'ak_' . $this->attributeKey->getAttributeKeyHandle();
        $isGuid = preg_match('/^-?\d{1,2}\.\d{6,}\s*,\s*-?\d{1,2}\.\d{6,}$/', $value);
        if($isGuid) $column .= '_relation_id';
        else $column .= '_code';

        if(is_array($value)) {
            if($comparison == '=') {
                $query->andWhere($query->expr()->in($column, $query->createNamedParameter($value)));
            } else {
                $query->andWhere($query->expr()->notIn($column, $query->createNamedParameter($value)));
            }
        } else {
            if($comparison == '=') {
                $query->andWhere($query->expr()->eq($column, $query->createNamedParameter($value)));
            } else {
                $query->andWhere($query->expr()->neq($column, $query->createNamedParameter($value)));
            }
        }

    }

	public function getAttributeValueTextRepresentation()
	{
		$vals = [$this->getValue()];
		// TODO
		return implode(',', $vals);
	}

	public function parseAttributeValueTextRepresentation($textRepresentation)
	{
		$vals = explode(',', $textRepresentation);
		//first value is main value
		$data['value'] = array_shift($vals);
		foreach($vals as $relation) {
			$relation = explode('=', $relation);
			if(strpos($relation[1], '{') !== false) {
				//transform
				$inspector = \Core::make('import/value_inspector');
	            $result = $inspector->inspect($relation[1]);
	            $oID = $result->getReplacedValue();
	            if($oID) {
	            	$relation[1] = $oID;
	            }
			}
			$data[$relation[0]] = $relation[1];
		}
		//\Log::addInfo(t(__CLASS__.'::parseAttributeValueTextRepresentation: %s'."\n%s", $textRepresentation, print_r($data, true)));
		return $data;
	}

	public function updateAttributeValueFromTextRepresentation($textRepresentation, \Concrete\Core\Error\ErrorList\ErrorList $warnings)
	{
		$data = $this->parseAttributeValueTextRepresentation($textRepresentation);
		return $this->createAttributeValue($data);
	}

	public function importValue(\SimpleXMLElement $akv)
	{
		$data = $this->parseAttributeValueTextRepresentation($akv->value);
		return $data;
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
		$this->set('relationID', $this->getRelationID());

		if($attrValue) {
			$this->set('relations', $this->getRelationsHash());
		}
	}

    public function searchForm($list)
    {
        $this->filterByAttribute($list, $this->request('value'));
        return $list;
    }

    public function searchKeywords($keywords, $queryBuilder)
    {
        return null;
    }

    public function search()
    {
        $f = Core::make('helper/form');
        echo $f->text($this->field('value'), $this->request('value'));
    }

	// If a language or country code is passed, normalize it
	public function normalizeValue($value)
	{
		$availableLocales = $this->getAvailableLocales();
		$valueSplit = explode('_', strtolower($value));
		foreach($availableLocales as $availableLocale) {
			$compare = $availableLocale->getLocale();
			if(strtolower($value) == strtolower($compare)) {
				return $compare;
			}
			$compareSplit = explode('_', strtolower($compare));
			if($valueSplit[0] == $compareSplit[0]) {
				return $compare;
			}
		}
		if($value == 'es') return 'es_MX';
		elseif($value == 'en') return 'en_US';

		return $value;
	}

	public function saveValue($value)
	{
		//throw new Exception(__CLASS__.'::saveValue');
		//dd($value);
		//\Log::addInfo(print_r($value, true));
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

        $akcHandle = $this->getAttributeKeyCategoryHandle();
		$value = $this->normalizeValue($value);

		$db = \Database::get();
		$db->Replace('atLanguage', [
			'avID' => $this->getAttributeValueID(),
			'value' => $value,
			'akcHandle' => $akcHandle,
			'relationID' => $relationID
		], 'avID', true);

		if(is_array($data)) {
			$oID = $this->getAttributeValueOwnerID();

			$hash = $this->getRelationsHash();
			foreach($data as $locale => $id) {
				if($id > 0) {
					$this->saveRelation($locale, $id);
				} else if(is_object($hash[$locale])) {
					$hash[$locale]->getAttributeValueObject($this->getAttributeKey(), true)->getController()->deleteRelation();
				}

			}
		}


	}

    public function pushMultilingualData()
    {
        $akcHandle = $this->getAttributeKeyCategoryHandle();
        if($akcHandle == 'collection') {
            $page = $this->getAttributeValueOwnerObject();
            $mpRelationID = Section::registerPage($page);
            $hash = $this->getRelationsHash();
            foreach($hash as $locale => $otherPage) {
                Section::relatePage($page, $otherPage, $locale);
            }
        }
    }

    public function pullMultilingualData()
    {
        $akcHandle = $this->getAttributeKeyCategoryHandle();
        if($akcHandle == 'collection') {
            $page = $this->getAttributeValueOwnerObject();
            $mpRelationID = Section::getMultilingualPageRelationID($page->getCollectionID());
            $locale = Section::getBySectionOfSite($page)->getLocale();
            $db = \Database::get();

            $multilingualRelations = $db->GetAll('select cID, mpLocale from MultilingualPageRelations where mpRelationID = ?', [$mpRelationID]);

            foreach($multilingualRelations as $mr) {
                $this->saveRelation($mr['mpLocale'], Page::getByID($mr['cID']));
            }
        }
    }

	public function saveForm($data)
	{
		$return = $this->saveValue($data);
        $this->pushMultilingualData();

        return $return;
	}

	public function saveRelation($language, $owner)
	{
		if(!is_object($owner)) {
			$owner = $this->getAttributeValueOwnerByID($owner);
		}
		$owner->setAttribute($this->getAttributeKey(), [
			'value'=> $language,
			'relationID' => $this->getRelationID()
		]);
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
        //if(!$this->getAttributeValueObject()) return null;

		$akcHandle = $this->getAttributeKeyCategoryHandle();
		$metadata = $this->entityManager->getClassMetadata(get_class($this->getAttributeValue()));

		return $metadata->getTableName();
	}

	public function getAttributeValueOwnerReferenceRow()
	{
		if(!$this->getAttributeValueID()) return null;
        //if(!$this->getAttributeValueObject()) return null;

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
			return Page::getByID($oID);
		}
		else if($akcHandle == 'file') {
			return File::getByID($oID);
		}
		else {
			throw Exception('Language attribute not implemented for '.$akcHandle);
		}
	}

	public function getAttributeValueOwnerClass()
	{
		$owner = $this->getAttributeValueOwnerObject();
		$class = get_class($owner);
		return $class;
	}

	public function getAttributeValueOwnerByID($id, $class = null)
	{
		if(!$class) {
			$class = $this->getAttributeValueOwnerClass();
		}

		if(strpos($class, 'Entity') !== false) {
			$meta = $this->entityManager->getClassMetadata($class);
			$idField = reset($meta->identifier);
			$repo = $this->entityManager->getRepository($class);
			return $repo->findOneBy([$idField => $id]);
		} else {
			$owner = $class::getByID($id);
            if(!is_object($owner) || method_exists($owner, 'isError') && $owner->isError()) {
                return null;
            }
            return $owner;
		}
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

    public function getAttributeValueID()
    {
        $val = $this->getAttributeValue();
        if(!is_object($val) || !is_object($val->getGenericValue())){
            return null;
        }
        //\Doctrine\Common\Util\Debug::dump($this->getAttributeValue());die;
        return parent::getAttributeValueID();
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
		$hash = [];
		foreach($relations as $relationOwnerID){
			$relationObject = $this->getAttributeValueOwnerByID($relationOwnerID);
			if($relationObject) {
				$lang = $relationObject->getAttribute($this->getAttributeKey()->getAttributeKeyHandle());
				$hash[$lang] = $asObjects ? $relationObject : $relationOwnerID;
			}
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
	}
}
