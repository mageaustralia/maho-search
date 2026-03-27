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

use Maho\Search\Lucene\Document;
use Maho\Search\Lucene\Field;

/**
 * Category document builder — converts Maho categories into Lucene documents.
 */
class MageAustralia_LuceneSearch_Model_Indexer_Category
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
     * Reindex all categories for a store.
     */
    public function reindexAll(int $storeId, string $storeCode): int
    {
        $helper = $this->_getHelper();
        $index = $this->_getIndexer()->getIndex($storeCode);

        $searchableAttributes = $helper->getCategorySearchableAttributes($storeId);

        $collection = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId($storeId)
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('level', ['gt' => 1])
            ->addAttributeToSelect('url_key')
            ->addAttributeToSelect('url_path');

        foreach ($searchableAttributes as $attr) {
            $collection->addAttributeToSelect($attr);
        }

        $count = 0;
        foreach ($collection as $category) {
            $doc = $this->buildDocument($category, $storeId);
            if ($doc !== null) {
                $index->addDocument($doc);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Reindex a single category across all stores.
     */
    public function reindexCategory(Mage_Catalog_Model_Category $category): void
    {
        $helper = $this->_getHelper();
        $indexer = $this->_getIndexer();

        foreach (Mage::app()->getStores() as $store) {
            $storeId = (int) $store->getId();
            if (!$helper->isCategoryIndexEnabled($storeId)) {
                continue;
            }

            $storeCode = $store->getCode();
            $indexer->removeDocument($storeCode, 'category', (int) $category->getId());

            $storeCategory = Mage::getModel('catalog/category')
                ->setStoreId($storeId)
                ->load($category->getId());

            if (!$storeCategory->getIsActive() || (int) $storeCategory->getLevel() <= 1) {
                continue;
            }

            $doc = $this->buildDocument($storeCategory, $storeId);
            if ($doc !== null) {
                $index = $indexer->getIndex($storeCode);
                $index->addDocument($doc);
                $index->commit();
            }
        }
    }

    public function buildDocument(Mage_Catalog_Model_Category $category, int $storeId): ?Document
    {
        $helper = $this->_getHelper();
        $doc = new Document();

        $doc->addField(Field::keyword('_uid', 'category_' . $category->getId()));
        $doc->addField(Field::keyword('_type', 'category'));
        $doc->addField(Field::keyword('_entity_id', (string) $category->getId()));

        // Stored fields for retrieval
        $doc->addField(Field::unIndexed('name_stored', (string) $category->getName()));
        $urlKey = $category->getUrlPath() ?: $category->getUrlKey();
        $doc->addField(Field::unIndexed('url_key', (string) $urlKey));

        $synonymFilter = $helper->getSynonymFilter();
        $ngramFilter = $helper->getEdgeNgramFilter();
        $allSearchableText = [];

        // Searchable fields
        $attributes = $helper->getCategorySearchableAttributes($storeId);
        foreach ($attributes as $attrCode) {
            $value = strip_tags((string) $category->getData($attrCode));
            if ($value === '') {
                continue;
            }

            $expandedValue = $synonymFilter->expandForIndex($value);
            $field = Field::unstored($attrCode, $expandedValue);
            $field->boost = $helper->getCategoryAttributeBoost($attrCode, $storeId);
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
