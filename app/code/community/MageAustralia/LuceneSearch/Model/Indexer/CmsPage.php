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

use Maho\Search\Lucene\Document;
use Maho\Search\Lucene\Field;

/**
 * CMS Page document builder — converts CMS pages into Lucene documents.
 */
class MageAustralia_LuceneSearch_Model_Indexer_CmsPage
{
    private function _getHelper(): MageAustralia_LuceneSearch_Helper_Data
    {
        return Mage::helper('lucenesearch');
    }

    private function _getIndexer(): MageAustralia_LuceneSearch_Model_Indexer
    {
        return Mage::getModel('lucenesearch/indexer');
    }

    /**
     * Reindex all CMS pages for a store.
     */
    public function reindexAll(int $storeId, string $storeCode): int
    {
        $helper = $this->_getHelper();
        $index = $this->_getIndexer()->getIndex($storeCode);
        $excludedPages = $helper->getCmsExcludedPages($storeId);

        $collection = Mage::getModel('cms/page')->getCollection()
            ->addStoreFilter($storeId)
            ->addFieldToFilter('is_active', 1);

        if (!empty($excludedPages)) {
            $collection->addFieldToFilter('identifier', ['nin' => $excludedPages]);
        }

        $count = 0;
        foreach ($collection as $page) {
            $doc = $this->buildDocument($page, $storeId);
            if ($doc !== null) {
                $index->addDocument($doc);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Reindex a single CMS page across all stores.
     */
    public function reindexCmsPage(Mage_Cms_Model_Page $page): void
    {
        $helper = $this->_getHelper();
        $indexer = $this->_getIndexer();

        foreach (Mage::app()->getStores() as $store) {
            $storeId = (int) $store->getId();
            if (!$helper->isCmsIndexEnabled($storeId)) {
                continue;
            }

            $storeCode = $store->getCode();
            $indexer->removeDocument($storeCode, 'cms_page', (int) $page->getId());

            // Check if page is assigned to this store
            $storeIds = $page->getStoreId();
            if (is_array($storeIds) && !in_array(0, $storeIds) && !in_array($storeId, $storeIds)) {
                continue;
            }

            if (!$page->getIsActive()) {
                continue;
            }

            $excludedPages = $helper->getCmsExcludedPages($storeId);
            if (in_array($page->getIdentifier(), $excludedPages, true)) {
                continue;
            }

            $doc = $this->buildDocument($page, $storeId);
            if ($doc !== null) {
                $index = $indexer->getIndex($storeCode);
                $index->addDocument($doc);
                $index->commit();
            }
        }
    }

    public function buildDocument(Mage_Cms_Model_Page $page, int $storeId): ?Document
    {
        $helper = $this->_getHelper();
        $doc = new Document();

        $doc->addField(Field::keyword('_uid', 'cms_page_' . $page->getId()));
        $doc->addField(Field::keyword('_type', 'cms_page'));
        $doc->addField(Field::keyword('_entity_id', (string) $page->getId()));

        // Stored fields for retrieval
        $doc->addField(Field::unIndexed('title_stored', (string) $page->getTitle()));
        $doc->addField(Field::unIndexed('identifier', (string) $page->getIdentifier()));

        $synonymFilter = $helper->getSynonymFilter();
        $ngramFilter = $helper->getEdgeNgramFilter();
        $allSearchableText = [];

        // Searchable fields
        $attributes = $helper->getCmsSearchableAttributes($storeId);
        foreach ($attributes as $attrCode) {
            $value = $page->getData($attrCode);
            if ($value === null || $value === '') {
                continue;
            }

            // Strip HTML from content
            $value = strip_tags((string) $value);

            $expandedValue = $synonymFilter->expandForIndex($value);
            $field = Field::unstored($attrCode, $expandedValue);
            $field->boost = $helper->getCmsAttributeBoost($attrCode, $storeId);
            $doc->addField($field);
            $allSearchableText[] = $value;
        }

        // Edge n-gram field for prefix matching
        if (!empty($allSearchableText)) {
            $ngramContent = $ngramFilter->expandForIndex(implode(' ', $allSearchableText));
            $ngramField = Field::unstored('_ngrams', $ngramContent);
            $ngramField->boost = 0.5;
            $doc->addField($ngramField);
        }

        return $doc;
    }
}
