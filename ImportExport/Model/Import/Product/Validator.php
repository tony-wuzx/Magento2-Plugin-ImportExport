<?php
namespace Zhixing\ImportExport\Model\Import\Product;

use Magento\CatalogImportExport\Model\Import\Product as Product;
use Magento\CatalogImportExport\Model\Import\Product\RowValidatorInterface as RowValidatorInterface;

/**
 * Class Validator
 * @package Zhixing\ImportExport\Model\Import\Product
 */
class Validator extends \Magento\CatalogImportExport\Model\Import\Product\Validator
{
    /**
     * @var array
     */
    protected $dynamicallyOptionAdded = [];

    /**
     * @var array
     */
    protected $dynamicallyOptionMapped = [];

    /**
     * @var \Magento\Catalog\Model\Product\Attribute\OptionManagement
     */
    protected $optionManagement;

    /**
     * @var \Magento\Eav\Api\Data\AttributeOptionInterfaceFactory
     */
    protected $optionDataFactory;

    /**
     * @var \Magento\Framework\Api\DataObjectHelper
     */
    protected $dataObjectHelper;

    /**
     * Validator constructor.
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param \Magento\Catalog\Model\Product\Attribute\OptionManagement $optionManagement
     * @param \Magento\Eav\Api\Data\AttributeOptionInterfaceFactory $optionDataFactory
     * @param \Magento\Framework\Api\DataObjectHelper $dataObjectHelper
     * @param array $validators
     */
    public function __construct(
        \Magento\Framework\Stdlib\StringUtils $string,
        \Magento\Catalog\Model\Product\Attribute\OptionManagement $optionManagement,
        \Magento\Eav\Api\Data\AttributeOptionInterfaceFactory $optionDataFactory,
        \Magento\Framework\Api\DataObjectHelper $dataObjectHelper,
        $validators = []
    ) {
        $this->optionManagement = $optionManagement;
        $this->optionDataFactory = $optionDataFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        parent::__construct($string, $validators);
    }

    /**
     * Is attribute valid
     *
     * @param string $attrCode
     * @param array $attrParams
     * @param array $rowData
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function isAttributeValid($attrCode, array $attrParams, array $rowData)
    {
        $this->_rowData = $rowData;
        if (isset($rowData['product_type']) && !empty($attrParams['apply_to'])
            && !in_array($rowData['product_type'], $attrParams['apply_to'])
        ) {
            return true;
        }

        if (!$this->isRequiredAttributeValid($attrCode, $attrParams, $rowData)) {
            $valid = false;
            $this->_addMessages(
                [
                    sprintf(
                        $this->context->retrieveMessageTemplate(
                            RowValidatorInterface::ERROR_VALUE_IS_REQUIRED
                        ),
                        $attrCode
                    )
                ]
            );
            return $valid;
        }

        if (!strlen(trim($rowData[$attrCode]))) {
            return true;
        }
        switch ($attrParams['type']) {
            case 'varchar':
            case 'text':
                $valid = $this->textValidation($attrCode, $attrParams['type']);
                break;
            case 'decimal':
            case 'int':
                $valid = $this->numericValidation($attrCode, $attrParams['type']);
                break;
            case 'select':
            case 'boolean':
            case 'multiselect':
                $values = explode(Product::PSEUDO_MULTI_LINE_SEPARATOR, $rowData[$attrCode]);
                $valid = true;

                // Start custom
                foreach ($values as $value) {
                    $option = [];
                    $va = explode('--', $value);
                    if (count($va) > 2 && strlen(trim($va[0]))) {
                        $this->dynamicallyOptionMapped[$value] = $va[0];
                        $value = $va[0];
                        $option['type'] = $va[1];
                        $option['value'] = $va[2];
                    }
                    $option['label'] = $value;
                    $option['sort_order'] = 100;
                    $option['is_default'] = false;

                    if (!empty($value) && !isset($attrParams['options'][strtolower($value)]) && !isset($this->dynamicallyOptionAdded[$attrCode][strtolower($value)])) {
                        $optionDataObject = $this->optionDataFactory->create();
                        $this->dataObjectHelper->populateWithArray(
                            $optionDataObject,
                            $option,
                            '\Magento\Eav\Api\Data\AttributeOptionInterface'
                        );
                        if ($this->optionManagement->add($attrCode, $optionDataObject)) {
                            $entityTypeModel = $this->context->retrieveProductTypeByName($rowData['product_type']);
                            $configurableEntityTypeModel = $this->context->retrieveProductTypeByName('configurable');

                            if ($entityTypeModel) {
                                $entityTypeModel->refreshCacheAttributes();
                            }
                            if ($configurableEntityTypeModel) {
                                $configurableEntityTypeModel->refreshCacheAttributes();
                            }

                            $this->dynamicallyOptionAdded[$attrCode][strtolower($value)] = true;
                            $attrParams['options'][strtolower($value)] = true;
                        }
                    }
                }

                if (isset($this->dynamicallyOptionAdded[$attrCode])) {
                    foreach ($this->dynamicallyOptionAdded[$attrCode] as $key => $value) {
                        $attrParams['options'][$key] = $value;
                    }
                }
                // end custom

                foreach ($values as $value) {
                    if (isset($this->dynamicallyOptionMapped[$value])) {
                        $value = $this->dynamicallyOptionMapped[$value];
                    }
                    $valid = $valid && isset($attrParams['options'][strtolower($value)]);
                }
                if (!$valid) {
                    $this->_addMessages(
                        [
                            sprintf(
                                $this->context->retrieveMessageTemplate(
                                    RowValidatorInterface::ERROR_INVALID_ATTRIBUTE_OPTION
                                ),
                                $attrCode
                            )
                        ]
                    );
                }

                break;
            case 'datetime':
                $val = trim($rowData[$attrCode]);
                $valid = strtotime($val) !== false;
                if (!$valid) {
                    $this->_addMessages([RowValidatorInterface::ERROR_INVALID_ATTRIBUTE_TYPE]);
                }
                break;
            default:
                $valid = true;
                break;
        }

        if ($valid && !empty($attrParams['is_unique'])) {
            if (isset($this->_uniqueAttributes[$attrCode][$rowData[$attrCode]])
                && ($this->_uniqueAttributes[$attrCode][$rowData[$attrCode]] != $rowData[Product::COL_SKU])) {
                $this->_addMessages([RowValidatorInterface::ERROR_DUPLICATE_UNIQUE_ATTRIBUTE]);
                return false;
            }
            $this->_uniqueAttributes[$attrCode][$rowData[$attrCode]] = $rowData[Product::COL_SKU];
        }

        if (!$valid) {
            $this->setInvalidAttribute($attrCode);
        }

        return (bool)$valid;

    }
}
