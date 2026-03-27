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

use Maho\Search\Lucene\Lucene;
use Maho\Search\Lucene\LuceneInterface;

/**
 * Core indexer — manages Lucene index lifecycle per store.
 */
class MageAustralia_LuceneSearch_Model_Indexer
{
    /** @var array<string, LuceneInterface> */
    private array $_indexes = [];

    private function _getHelper(): MageAustralia_LuceneSearch_Helper_Data
    {
        return Mage::helper('lucenesearch');
    }

    /**
     * Get or create a Lucene index for a store.
     */
    public function getIndex(string $storeCode): LuceneInterface
    {
        if (!isset($this->_indexes[$storeCode])) {
            $path = $this->_getHelper()->getStoreIndexPath($storeCode);

            $this->_getHelper()->initAnalyzer();

            if (is_dir($path) && file_exists($path . '/segments.gen')) {
                $this->_indexes[$storeCode] = Lucene::open($path);
            } else {
                @mkdir($path, 0755, true);
                $this->_indexes[$storeCode] = Lucene::create($path);
            }
        }

        return $this->_indexes[$storeCode];
    }

    /**
     * Rebuild the entire index for a store.
     */
    public function reindexStore(int $storeId): array
    {
        $store = Mage::app()->getStore($storeId);
        $storeCode = $store->getCode();
        $helper = $this->_getHelper();

        // Delete existing index
        $path = $helper->getStoreIndexPath($storeCode);
        if (is_dir($path)) {
            $this->_removeDirectory($path);
        }

        // Reset cached index
        unset($this->_indexes[$storeCode]);

        $stats = ['products' => 0, 'categories' => 0, 'cms_pages' => 0];

        if ($helper->isProductIndexEnabled($storeId)) {
            $stats['products'] = $this->getProductIndexer()->reindexAll($storeId, $storeCode);
        }

        if ($helper->isCategoryIndexEnabled($storeId)) {
            $stats['categories'] = $this->getCategoryIndexer()->reindexAll($storeId, $storeCode);
        }

        if ($helper->isCmsIndexEnabled($storeId)) {
            $stats['cms_pages'] = $this->getCmsPageIndexer()->reindexAll($storeId, $storeCode);
        }

        // Commit and optimize
        $index = $this->getIndex($storeCode);
        $index->commit();
        $index->optimize();

        return $stats;
    }

    /**
     * Rebuild indexes for all stores.
     */
    public function reindexAll(): array
    {
        $results = [];
        foreach (Mage::app()->getStores() as $store) {
            $results[$store->getCode()] = $this->reindexStore((int) $store->getId());
        }
        return $results;
    }

    /**
     * Remove a document from the index by type and entity ID.
     */
    public function removeDocument(string $storeCode, string $type, int $entityId): void
    {
        $index = $this->getIndex($storeCode);
        $term = new \Maho\Search\Lucene\Index\Term(
            $type . '_' . $entityId,
            '_uid'
        );

        $hits = $index->termDocs($term);
        foreach ($hits as $docId) {
            $index->delete($docId);
        }
        $index->commit();
    }

    /**
     * Optimize index for a store (merge segments).
     */
    public function optimize(string $storeCode): void
    {
        $index = $this->getIndex($storeCode);
        $index->optimize();
    }

    public function getProductIndexer(): MageAustralia_LuceneSearch_Model_Indexer_Product
    {
        return Mage::getModel('lucenesearch/indexer_product');
    }

    public function getCategoryIndexer(): MageAustralia_LuceneSearch_Model_Indexer_Category
    {
        return Mage::getModel('lucenesearch/indexer_category');
    }

    public function getCmsPageIndexer(): MageAustralia_LuceneSearch_Model_Indexer_CmsPage
    {
        return Mage::getModel('lucenesearch/indexer_cmsPage');
    }

    private function _removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
