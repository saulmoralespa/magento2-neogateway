# magento2-neogateway

**Integration of neogateway with Magento 2**

## Description ##
Integration of Neogateway payment method with Magento 2
Neogateway is a means of payment available for Panama that processes card payments which are tokenized

## Installation ##
* In the root directory of the installation of magento 2, execute the following commands:

`composer require saulmoralespa/magento2-neogateway`

`bin/magento module:enable Smp_Neogateway --clear-static-content`

`bin/magento setup:upgrade`

* Cool your browser window where Magento's store is open
 
**If you have an error in the process, execute the command:**


`php bin/magento setup:di:compile`
