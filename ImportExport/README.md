# Summary
- Module Name: Zhixing_ImportExport
- create not existed option via csv file while import product in admin
- if attribute options has visual swatch with file, notice type=2, and upload images to destination folder
- grid export add header with BOM incase Chinese mess code in excel

## Installation
- Enable Zhixing/ImportExport Module
- You can add this module by php bin/mangeto:
```
    php bin/magento module:enable Zhixing_ImportExport
    php bin/magento setup:upgrade
    php bin/magento setup:di:compile
    php bin/magento cache:flush
```

## Configuration



## The Version Of History

### 0.0.1
- init module
