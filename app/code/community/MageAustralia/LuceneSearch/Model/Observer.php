<?php

declare(strict_types=1);

/**
 * Maho Lucene Search
 *
 * @category   MageAustralia
 * @package    MageAustralia_LuceneSearch
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php
 */

use Maho\Event\Observer as MahoObserver;

/**
 * Event observer — triggers incremental index updates on entity save/delete.
 */
class MageAustralia_LuceneSearch_Model_Observer
{
    private function _getHelper(): MageAustralia_LuceneSearch_Helper_Data
    {
        return Mage::helper('lucenesearch');
    }

    private function _getIndexer(): MageAustralia_LuceneSearch_Model_Indexer
    {
        return Mage::getModel('lucenesearch/indexer');
    }

    #[\Maho\Config\Observer('catalog_product_save_after', id: 'lucenesearch_product_save')]
    public function onProductSave(MahoObserver $observer): void
    {
        if (!$this->_getHelper()->isEnabled()) {
            return;
        }

        try {
            $product = $observer->getEvent()->getProduct();
            if ($product instanceof Mage_Catalog_Model_Product) {
                $this->_getIndexer()->getProductIndexer()->reindexProduct($product);
            }
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    #[\Maho\Config\Observer('catalog_product_delete_before', id: 'lucenesearch_product_delete')]
    public function onProductDelete(MahoObserver $observer): void
    {
        if (!$this->_getHelper()->isEnabled()) {
            return;
        }

        try {
            $product = $observer->getEvent()->getProduct();
            if ($product instanceof Mage_Catalog_Model_Product) {
                $indexer = $this->_getIndexer();
                foreach (Mage::app()->getStores() as $store) {
                    $indexer->removeDocument($store->getCode(), 'product', (int) $product->getId());
                }
            }
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    #[\Maho\Config\Observer('catalog_category_save_after', id: 'lucenesearch_category_save')]
    public function onCategorySave(MahoObserver $observer): void
    {
        if (!$this->_getHelper()->isEnabled()) {
            return;
        }

        try {
            $category = $observer->getEvent()->getCategory();
            if ($category instanceof Mage_Catalog_Model_Category) {
                $this->_getIndexer()->getCategoryIndexer()->reindexCategory($category);
            }
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    #[\Maho\Config\Observer('catalog_category_delete_before', id: 'lucenesearch_category_delete')]
    public function onCategoryDelete(MahoObserver $observer): void
    {
        if (!$this->_getHelper()->isEnabled()) {
            return;
        }

        try {
            $category = $observer->getEvent()->getCategory();
            if ($category instanceof Mage_Catalog_Model_Category) {
                $indexer = $this->_getIndexer();
                foreach (Mage::app()->getStores() as $store) {
                    $indexer->removeDocument($store->getCode(), 'category', (int) $category->getId());
                }
            }
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    #[\Maho\Config\Observer('cms_page_save_after', id: 'lucenesearch_cms_save')]
    public function onCmsPageSave(MahoObserver $observer): void
    {
        if (!$this->_getHelper()->isEnabled()) {
            return;
        }

        try {
            $page = $observer->getEvent()->getObject();
            if ($page instanceof Mage_Cms_Model_Page) {
                $this->_getIndexer()->getCmsPageIndexer()->reindexCmsPage($page);
            }
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    #[\Maho\Config\Observer('cms_page_delete_before', id: 'lucenesearch_cms_delete')]
    public function onCmsPageDelete(MahoObserver $observer): void
    {
        if (!$this->_getHelper()->isEnabled()) {
            return;
        }

        try {
            $page = $observer->getEvent()->getObject();
            if ($page instanceof Mage_Cms_Model_Page) {
                $indexer = $this->_getIndexer();
                foreach (Mage::app()->getStores() as $store) {
                    $indexer->removeDocument($store->getCode(), 'cms_page', (int) $page->getId());
                }
            }
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    /**
     * Inject search config into the StoreConfig API response.
     * The storefront reads this to configure the search backend.
     */
    #[\Maho\Config\Observer('api_store_config_dto_build', id: 'lucenesearch_store_config')]
    public function onStoreConfigBuild(MahoObserver $observer): void
    {
        $dto = $observer->getEvent()->getDto();
        if (!$dto || !property_exists($dto, 'extensions')) {
            return;
        }

        $helper = $this->_getHelper();
        $storeId = (int) Mage::app()->getStore()->getId();

        $searchConfig = [
            'backend' => 'lucene',
            'enabled' => $helper->isEnabled($storeId),
        ];

        // If Meilisearch module is installed, include its config for direct browser queries
        if (Mage::helper('core')->isModuleEnabled('Meilisearch_Search')) {
            $msHost = Mage::getStoreConfig('maho_api/meilisearch/host', $storeId);
            $msApiKey = Mage::getStoreConfig('maho_api/meilisearch/search_api_key', $storeId);
            $msPrefix = Mage::getStoreConfig('maho_api/meilisearch/index_prefix', $storeId) ?: 'maho';
            $storeCode = Mage::app()->getStore($storeId)->getCode();

            if ($msHost) {
                $searchConfig['backend'] = 'meilisearch';
                $searchConfig['meilisearch'] = [
                    'host' => $msHost,
                    'apiKey' => $msApiKey ?: '',
                    'indexPrefix' => $msPrefix . '_' . $storeCode,
                ];
            }
        }

        $dto->extensions['search'] = $searchConfig;
    }

    /**
     * Cron: optimize all store indexes (merge segments).
     */
    #[\Maho\Config\CronJob('lucenesearch_optimize', schedule: '0 3 * * *')]
    public function cronOptimize(): void
    {
        if (!$this->_getHelper()->isEnabled()) {
            return;
        }

        try {
            $indexer = $this->_getIndexer();
            foreach (Mage::app()->getStores() as $store) {
                $indexer->optimize($store->getCode());
            }
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }
}
