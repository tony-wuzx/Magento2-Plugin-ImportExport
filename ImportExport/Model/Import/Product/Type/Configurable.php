<?php
namespace Zhixing\ImportExport\Model\Import\Product\Type;

/**
 * Class Configurable
 * @package Zhixing\ImportExport\Model\Import\Product\Type
 */
class Configurable extends \Magento\ConfigurableImportExport\Model\Import\Product\Type\Configurable
{
    public function refreshCacheAttributes()
    {
        self::$commonAttributesCache = [];
        $this->_initAttributes();
    }
}
