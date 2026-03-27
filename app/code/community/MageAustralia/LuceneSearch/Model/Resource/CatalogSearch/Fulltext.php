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

use Maho\Search\Lucene\Search\QueryParser;

/**
 * Rewrites CatalogSearch Fulltext resource model to use Lucene index
 * instead of MySQL fulltext search for prepareResult().
 *
 * All other methods (rebuildIndex, cleanIndex, etc.) delegate to parent
 * so the standard MySQL-based catalogsearch_fulltext index still works
 * for layered navigation and other features.
 */
class MageAustralia_LuceneSearch_Model_Resource_CatalogSearch_Fulltext
    extends Mage_CatalogSearch_Model_Resource_Fulltext
{
    /**
     * Prepare results for query using Lucene index.
     *
     * Falls back to parent MySQL fulltext if Lucene is disabled or fails.
     *
     * @param Mage_CatalogSearch_Model_Fulltext $object
     * @param string $queryText
     * @param Mage_CatalogSearch_Model_Query $query
     * @return $this
     */
    #[\Override]
    public function prepareResult($object, $queryText, $query)
    {
        $storeId = (int) $query->getStoreId();

        /** @var MageAustralia_LuceneSearch_Helper_Data $helper */
        $helper = Mage::helper('lucenesearch');

        if (!$helper->isEnabled($storeId) || !$helper->isProductIndexEnabled($storeId)) {
            return parent::prepareResult($object, $queryText, $query);
        }

        try {
            $this->_foundData = $this->_luceneSearch($queryText, $storeId);
        } catch (\Throwable $e) {
            Mage::logException($e);
            // Fall back to MySQL fulltext
            return parent::prepareResult($object, $queryText, $query);
        }

        return $this;
    }

    /**
     * Query Lucene index and return [product_id => relevance] pairs.
     */
    private function _luceneSearch(string $queryText, int $storeId): array
    {
        $helper = Mage::helper('lucenesearch');
        $store = Mage::app()->getStore($storeId);
        $storeCode = $store->getCode();

        $helper->initAnalyzer();

        /** @var MageAustralia_LuceneSearch_Model_Indexer $indexer */
        $indexer = Mage::getModel('lucenesearch/indexer');
        $index = $indexer->getIndex($storeCode);

        // Sanitize query
        $sanitized = preg_replace('/[+\-!(){}\[\]^"~*?:\\\\\/&|]/', ' ', $queryText);
        $sanitized = trim(preg_replace('/\s+/', ' ', $sanitized));

        if ($sanitized === '') {
            return [];
        }

        $results = [];

        // 1. Try AND mode
        try {
            QueryParser::setDefaultOperator(QueryParser::B_AND);
            $query = QueryParser::parse($sanitized);
            $hits = $index->find($query);

            foreach ($hits as $hit) {
                $doc = $hit->getDocument();
                if ($doc->getFieldValue('_type') === 'product') {
                    $productId = (int) $doc->getFieldValue('_entity_id');
                    $results[$productId] = (int) ($hit->score * 1000);
                }
            }

            if (!empty($results)) {
                return $results;
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        // 2. Try OR mode
        try {
            QueryParser::setDefaultOperator(QueryParser::B_OR);
            $query = QueryParser::parse($sanitized);
            $hits = $index->find($query);

            foreach ($hits as $hit) {
                $doc = $hit->getDocument();
                if ($doc->getFieldValue('_type') === 'product') {
                    $productId = (int) $doc->getFieldValue('_entity_id');
                    $results[$productId] = (int) ($hit->score * 1000);
                }
            }

            if (!empty($results)) {
                return $results;
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        // 3. Try fuzzy
        if ($helper->isFuzzyFallbackEnabled($storeId)) {
            try {
                $terms = preg_split('/\s+/', $sanitized);
                $fuzzyQuery = implode(' ', array_map(fn($t) => $t . '~', $terms));
                QueryParser::setDefaultOperator(QueryParser::B_OR);
                $query = QueryParser::parse($fuzzyQuery);
                $hits = $index->find($query);

                foreach ($hits as $hit) {
                    $doc = $hit->getDocument();
                    if ($doc->getFieldValue('_type') === 'product') {
                        $productId = (int) $doc->getFieldValue('_entity_id');
                        $results[$productId] = (int) ($hit->score * 1000);
                    }
                }
            } catch (\Throwable $e) {
                Mage::logException($e);
            }
        }

        return $results;
    }
}
