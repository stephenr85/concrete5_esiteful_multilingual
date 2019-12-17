<?php
namespace Concrete\Package\EsitefulMultilingual\Helpers;

use Concrete\Core\Multilingual\Page\Section\Section;
use Database;

class MultilingualHelper
{

    public function getCurrentSection()
    {
        return Section::getCurrentSection();
    }

    public function getCurrentLocale()
    {
        return $this->getCurrentSection()->getLocale();
    }

    public function getDefaultSection()
    {
        return Section::getDefaultSection();
    }

    public function getDefaultLocale()
    {
        return $this->getDefaultSection()->getLocale();
    }

    public function filterFileListByLocale($fileList, $locale = true, $includeEmpty = null)
    {
        return $this->queryWhereLocale($fileList->getQueryObject(), 'fsi.ak_file_language_code', $locale, $includeEmpty);
    }

    public function queryWhereLocale($query, $column, $locale, $includeEmpty)
    {
        if($locale === true) {
            $locale = $this->getCurrentLocale();
        }

        if($includeEmpty === null && $locale == $this->getDefaultLocale()) {
            // Include empty values if the given locale is the default locale
            $includeEmpty = true;
        }
        $db = Database::get();
        $conditions = [
            is_array($locale) ? $query->expr()->in($column, array_map([$db, 'quote'], $locale)) : $query->expr()->eq($column, $db->quote($locale))
        ];

        if($includeEmpty) {
            $conditions []= $query->expr()->eq($column, $db->quote(''));
            $conditions []= $query->expr()->isNull($column);
        }
        $where = call_user_func_array([$query->expr(), 'orX'], $conditions);
        $query->andWhere($where);
    }
}
