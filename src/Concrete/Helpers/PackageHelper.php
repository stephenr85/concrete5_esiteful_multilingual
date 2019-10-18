<?php
namespace Concrete\Package\EsitefulMultilingual\Helpers;

use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Application\ApplicationAwareTrait;
use Concrete\Core\Attribute\SetFactory as AttributeSetFactory;
use Concrete\Core\Attribute\TypeFactory as AttributeTypeFactory;
use Concrete\Core\Attribute\Type as AttributeType;
use Concrete\Core\Attribute\Key\Category  as AttributeKeyCategory;
use Concrete\Core\Attribute\Key\Key as AttributeKey;
use Concrete\Core\Job\Job;
use Concrete\Core\User\Group\Group as UserGroup;
use Concrete\Core\Database\Connection\Connection;

class PackageHelper
{
    use ApplicationAwareTrait;

    protected $pkg;

    public function __construct($pkg)
    {
        $this->pkg = $pkg;
    }

    //region General

    public function getApplication()
    {
        if(!$this->app)
        {
            $this->app = \Concrete\Core\Support\Facade\Application::getFacadeApplication();
        }
        return $this->app;
    }

    public function getDatabaseConnection()
    {
        return $this->getApplication()->make(Connection::class);
    }

    public function getSite()
    {
        return $this->getApplication()->make('site')->getSite();
    }

    public function getPackage()
    {
        return $this->pkg;
    }

    public function setPackage($pkg)
    {
        $this->pkg = $pkg;
    }

    public function getEntityManager()
    {
        return $this->getApplication()->make('Doctrine\ORM\EntityManager');
    }

    //endregion General

    //region Attribute Keys

    public function getAttributeKeyCategory($akcHandle)
    {
        if(is_string($akcHandle)){
            $service = $this->app->make('Concrete\Core\Attribute\Category\CategoryService');
            $categoryEntity = $service->getByHandle($akcHandle);
            $category = $categoryEntity->getController();
            return $category;
        }else if(is_object($akcHandle)) {
            return $akcHandle;
        }
    }

    public function upsertAttributeKey($category, $attrSet, $keyType, $data)    {
        $category = $this->getAttributeKeyCategory($category);

        if(is_string($keyType)) {
            $keyTypeHandle = $keyType;

            $keyType = $this->getAttributeType($keyTypeHandle);

            $typeController = $keyType->getController();
            $defaultKeySettings = $typeController->getAttributeKeySettings();
            
        }     
        
        $attrKeyHandle = $data['akHandle'];
        $attrKey = $category->getByHandle($attrKeyHandle);      
        if (!$attrKey || !intval($attrKey->getAttributeKeyID())) {
            $attrKey = $category->add($keyType, $data, $defaultKeySettings, $this->pkg);
        }
        //$attrKey->saveKey($data);
        $attrKeyController = $attrKey->getController();
        foreach($data as $dataKey => $dataValue) {
            if(method_exists($attrKey, $dataKey)){
                $attrKey->$dataKey($dataValue);
            } else if(method_exists($attrKeyController, $dataKey)) {
                $attrKeyController->$dataKey($dataValue);
            }
        }
        $this->getEntityManager()->persist($attrKey);
        $this->getEntityManager()->persist($attrKeyController->getAttributeKeySettings());

        // Add attribute key to set
        if(is_string($attrSet)){
            $attrSet = $this->upsertAttributeSet($category, $attrSet, $attrSet, $this->pkg);
        }
        if(is_object($attrSet)){
            $attrSetManager = $category->getSetManager();
            $attrSetManager->addKey($attrSet, $attrKey);
        }
        return $attrKey;
    }

    //endregion Attribute Keys

    //region Attribute Sets

    public function upsertAttributeSet($category, $setHandle, $setName)    {
        $category = $this->getAttributeKeyCategory($category);

        $factory = $this->app->make('Concrete\Core\Attribute\SetFactory');
        $attrSet = $factory->getByHandle($setHandle);
        if(!is_object($attrSet)){
            $attrSetManager = $category->getSetManager();
            $attrSet = $attrSetManager->addSet($setHandle, $setName, $this->pkg);
        }
        return $attrSet;
    }

    //endregion Attribute Sets


    //endregion Page Types

    //region Attribute Types

    public function getAttributeType($atHandle)
    {
        if (is_string($atHandle)) {
            $typeFactory = $this->app->make(AttributeTypeFactory::class);
            /* @var TypeFactory $typeFactory */
            $type = $typeFactory->getByHandle($atHandle);
            return $type;
        }
    }

    public function upsertAttributeType($atHandle, $atName, $akcHandle)    {
        $type = AttributeType::getByHandle($atHandle);
        if(!$type) {
            $type = AttributeType::add($atHandle, $atName, $this->pkg);          
        }
        // associate this attribute type with all category keys
        if(is_string($akcHandle)) $akcHandle = [$akcHandle];
        if(is_array($akcHandle)) {
            foreach($akcHandle as $akch) {
                $cKey = AttributeKeyCategory::getByHandle($akch);
                $cKey->associateAttributeKeyType($type);
            }
        }  
        return $type;
        
    }

    //endregion AttributeTypes


    //region Jobs

    public function upsertJob($jobHandle)
    {
        $job = Job::getByHandle($jobHandle);
        if(!$job){
            $job = Job::installByPackage($jobHandle, $this->pkg);
        }   
        return $job;     
    }
    //endregion Jobs

}
