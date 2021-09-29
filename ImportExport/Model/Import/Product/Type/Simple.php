<?php
namespace Zhixing\ImportExport\Model\Import\Product\Type;

/**
 * Class Simple
 * @package Zhixing\ImportExport\Model\Import\Product\Type
 */
class Simple extends \Magento\CatalogImportExport\Model\Import\Product\Type\Simple
{
    public function refreshCacheAttributes()
    {
        self::$commonAttributesCache = [];
        $this->_initAttributes();
    }

    /**
     * @param array $rowData
     * @param bool $withDefaultValue
     * @return array
     */
    public function prepareAttributesWithDefaultValueForSave(array $rowData, $withDefaultValue = true)
    {
        $resultAttrs = [];
        $attrSetCode = $rowData['_attribute_set'] ?? 'Default';
        foreach ($this->_getProductAttributes($attrSetCode) as $attrCode => $attrParams) {
            if ($attrParams['is_static']) {
                continue;
            }
            if (isset($rowData[$attrCode]) && strlen(trim($rowData[$attrCode]))) {
                if ('select' == $attrParams['type']) {
                    $va = explode('--', trim($rowData[$attrCode]));
                    if (count($va) > 2 && strlen(trim($va[0]))) {
                        $rowData[$attrCode] = strtolower(trim($va[0]));
                        $option['type'] = $va[1];
                        $option['swatch_value'] = $va[2];
                    }
                    if (!isset($attrParams['options'][strtolower(trim($rowData[$attrCode]))])) {
                        $option['value'] = trim($rowData[$attrCode]);
                        $option['attribute_id'] = $attrParams['id'];
                        $optionId = $this->_addAttributeOption($option);
                        if ($optionId) {
                            $attrParams['options'] = $attrParams['options'] + [strtolower(trim($rowData[$attrCode])) => $optionId];
                            $this->_addAttributeParams($attrSetCode, $attrParams, '');
                        }
                    }
                    $resultAttrs[$attrCode] = $attrParams['options'][strtolower(trim($rowData[$attrCode]))] ?: null;
                } elseif ('boolean' == $attrParams['type']) {
                    $boolValue = $rowData[$attrCode] ? 'yes' : 'no';
                    if (!isset($attrParams['options'][$boolValue])) {
                        $option = [
                            'value' => trim($rowData[$attrCode]),
                            'attribute_id' => $attrParams['id']
                        ];
                        $optionId = $this->_addAttributeOption($option);
                        if ($optionId) {
                            $attrParams['options'] = $attrParams['options'] + [$boolValue => $optionId];
                            $this->_addAttributeParams($attrSetCode, $attrParams, '');
                        }
                    }
                    $resultAttrs[$attrCode] = $attrParams['options'][$boolValue] ?: null;
                } elseif ('multiselect' == $attrParams['type']) {
                    $resultAttrs[$attrCode] = [];
                    foreach (explode('|', $rowData[$attrCode]) as $value) {
                        if (!isset($attrParams['options'][strtolower(trim($value))])) {
                            $option = [
                                'values' => trim($value),
                                'attribute_id' => $attrParams['id']
                            ];
                            $optionId = $this->_addAttributeOption($option, true);
                            if ($optionId) {
                                $attrParams['options'] = $attrParams['options'] + [strtolower(trim($value)) => $optionId];
                                $this->_addAttributeParams($attrSetCode, $attrParams, '');
                            }
                        }
                        $resultAttrs[$attrCode][] = $attrParams['options'][strtolower(trim($value))] ?: null;
                    }
                    $resultAttrs[$attrCode] = implode(',', $resultAttrs[$attrCode]);
                } else {
                    $resultAttrs[$attrCode] = trim($rowData[$attrCode]);
                }
            } elseif (array_key_exists($attrCode, $rowData)) {
                $resultAttrs[$attrCode] = $rowData[$attrCode];
            } elseif ($withDefaultValue && null !== $attrParams['default_value']) {
                $resultAttrs[$attrCode] = $attrParams['default_value'];
            }
        }

        return $resultAttrs;
    }

    /**
     * add attribute option
     *
     * @param array $option
     * @param bool $isSelect
     * @return mixed
     */
    private function _addAttributeOption(array $option, $multiselect = false)
    {
        $optionData = ['attribute_id' => $option['attribute_id'], 'sort_order' => 0];
        $this->connection->insert('eav_attribute_option', $optionData);
        $insertOptionId = $this->connection->lastInsertId('eav_attribute_option');
        $optionValueData = [
            'option_id' => $insertOptionId,
            'store_id' => 0,
            'value' => ($multiselect ? $option['values'] : $option['value'])
        ];
        $this->connection->insert('eav_attribute_option_value', $optionValueData);
        if (isset($option['swatch_value'])) {
            $swatchData = [
                'option_id' => $insertOptionId,
                'store_id' => 0,
                'type' => $option['type'],
                'value' => $option['swatch_value'],
            ];
            $this->connection->insert('eav_attribute_option_swatch', $swatchData);
        }
        return $insertOptionId;
    }
}
