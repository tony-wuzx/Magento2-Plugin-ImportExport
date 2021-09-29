<?php
namespace Zhixing\ImportExport\Plugin;

/**
 * Class MetadataProviderPlugin
 * @package Zhixing\ImportExport\Plugin
 */
class MetadataProviderPlugin
{
    /**
     * @param \Magento\Ui\Model\Export\MetadataProvider $metadataProvider
     * @param $row
     * @return string[]
     */
    public function afterGetHeaders(
        \Magento\Ui\Model\Export\MetadataProvider $metadataProvider,
        $row
    ) {
        $rowWithBom = [];
        foreach ($row as $_row) {
            $rowWithBom[] = "\xEF\xBB\xBF".$_row;
        }
        return $rowWithBom;
    }
}
