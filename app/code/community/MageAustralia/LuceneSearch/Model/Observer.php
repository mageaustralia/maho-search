<?php

declare(strict_types=1);

/**
 * Maho Lucene Search
 *
 * @category   MageAustralia
 * @package    MageAustralia_LuceneSearch
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/AGPL-3.0
 */

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

    public function onProductSave(Varien_Event_Observer $observer): void
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

    public function onProductDelete(Varien_Event_Observer $observer): void
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

    public function onCategorySave(Varien_Event_Observer $observer): void
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

    public function onCategoryDelete(Varien_Event_Observer $observer): void
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

    public function onCmsPageSave(Varien_Event_Observer $observer): void
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

    public function onCmsPageDelete(Varien_Event_Observer $observer): void
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
     * Cron: optimize all store indexes (merge segments).
     */
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
