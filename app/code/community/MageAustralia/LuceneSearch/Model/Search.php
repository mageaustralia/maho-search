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
use Maho\Search\Lucene\Search\Query\BooleanQuery;

/**
 * Search service — queries the Lucene index and returns structured results.
 */
class MageAustralia_LuceneSearch_Model_Search
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
     * Search across all entity types.
     *
     * @param string $query     Search query string
     * @param int    $storeId   Store ID
     * @param array  $options   Optional: types, limit, page
     * @return array{products: array, categories: array, cmsPages: array, totalItems: int}
     */
    public function search(string $query, int $storeId, array $options = []): array
    {
        $helper = $this->_getHelper();
        if (!$helper->isEnabled($storeId)) {
            return $this->_emptyResult();
        }

        $query = trim($query);
        if (strlen($query) < 2) {
            return $this->_emptyResult();
        }

        $types = $options['types'] ?? ['product', 'category', 'cms_page'];
        $limit = $options['limit'] ?? $helper->getResultLimit($storeId);
        $page = $options['page'] ?? 1;

        $store = Mage::app()->getStore($storeId);
        $storeCode = $store->getCode();

        // Try search with fallback chain
        $hits = $this->_executeSearch($query, $storeCode, $storeId);

        // Separate by type
        $products = [];
        $categories = [];
        $cmsPages = [];

        foreach ($hits as $hit) {
            try {
                $doc = $hit->getDocument();
                $type = $doc->getFieldValue('_type');
                $entityId = (int) $doc->getFieldValue('_entity_id');

                if (!in_array($type, $types, true)) {
                    continue;
                }

                switch ($type) {
                    case 'product':
                        $products[] = [
                            'id' => $entityId,
                            'sku' => $doc->getFieldValue('sku_stored'),
                            'name' => $doc->getFieldValue('name_stored'),
                            'urlKey' => $doc->getFieldValue('url_key'),
                            'price' => (float) $doc->getFieldValue('price'),
                            'finalPrice' => (float) $doc->getFieldValue('final_price'),
                            'thumbnailUrl' => $this->_getProductImageUrl($doc->getFieldValue('thumbnail'), $storeId),
                            'score' => $hit->score,
                        ];
                        break;

                    case 'category':
                        $categories[] = [
                            'id' => $entityId,
                            'name' => $doc->getFieldValue('name_stored'),
                            'urlKey' => $doc->getFieldValue('url_key'),
                            'score' => $hit->score,
                        ];
                        break;

                    case 'cms_page':
                        $cmsPages[] = [
                            'id' => $entityId,
                            'title' => $doc->getFieldValue('title_stored'),
                            'identifier' => $doc->getFieldValue('identifier'),
                            'score' => $hit->score,
                        ];
                        break;
                }
            } catch (\Throwable $e) {
                // Skip documents with missing fields
                continue;
            }
        }

        // Apply pagination to products (primary result set)
        $totalProducts = count($products);
        if ($limit > 0) {
            $offset = ($page - 1) * $limit;
            $products = array_slice($products, $offset, $limit);
        }

        return [
            'products' => $products,
            'totalItems' => $totalProducts,
            'categories' => array_slice($categories, 0, 5),
            'cmsPages' => array_slice($cmsPages, 0, 5),
        ];
    }

    /**
     * Execute search with fallback chain: AND → OR → fuzzy.
     *
     * @return \Maho\Search\Lucene\Search\QueryHit[]
     */
    private function _executeSearch(string $queryString, string $storeCode, int $storeId): array
    {
        $helper = $this->_getHelper();
        $indexer = $this->_getIndexer();

        try {
            $index = $indexer->getIndex($storeCode);
        } catch (\Throwable $e) {
            Mage::logException($e);
            return [];
        }

        // Sanitize user query (strip special Lucene characters)
        $sanitized = $this->_sanitizeQuery($queryString);

        // Expand with synonyms for OR/fuzzy fallback
        $synonymFilter = $helper->getSynonymFilter();
        $synonymExpanded = $synonymFilter->expandQuery($sanitized);

        // 1. Try AND mode with original terms (all must match)
        try {
            QueryParser::setDefaultOperator(QueryParser::B_AND);
            $query = QueryParser::parse($sanitized);
            $hits = $index->find($query);
            if (!empty($hits)) {
                return $hits;
            }
        } catch (\Throwable $e) {
            // Fall through to OR mode
        }

        // 2. Try OR mode with synonym-expanded query
        try {
            QueryParser::setDefaultOperator(QueryParser::B_OR);
            $query = QueryParser::parse($synonymExpanded);
            $hits = $index->find($query);
            if (!empty($hits)) {
                return $hits;
            }
        } catch (\Throwable $e) {
            // Fall through to fuzzy
        }

        // 3. Try fuzzy search with synonym-expanded terms
        if ($helper->isFuzzyFallbackEnabled($storeId)) {
            try {
                $terms = preg_split('/\s+/', $synonymExpanded);
                $fuzzyQuery = implode(' ', array_map(fn($t) => $t . '~', $terms));
                QueryParser::setDefaultOperator(QueryParser::B_OR);
                $query = QueryParser::parse($fuzzyQuery);
                return $index->find($query);
            } catch (\Throwable $e) {
                Mage::logException($e);
            }
        }

        return [];
    }

    /**
     * Sanitize user query — remove characters that break query parsing.
     * We strip rather than escape, as escaped special chars can still cause issues.
     */
    private function _sanitizeQuery(string $query): string
    {
        // Remove Lucene special characters that users don't intend
        $query = preg_replace('/[+\-!(){}\[\]^"~*?:\\\\\/&|]/', ' ', $query);
        // Collapse whitespace
        return trim(preg_replace('/\s+/', ' ', $query));
    }

    private function _getProductImageUrl(?string $image, int $storeId): ?string
    {
        if (!$image || $image === 'no_selection') {
            return null;
        }
        $baseUrl = Mage::app()->getStore($storeId)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
        return $baseUrl . 'catalog/product' . $image;
    }

    private function _emptyResult(): array
    {
        return [
            'products' => [],
            'totalItems' => 0,
            'categories' => [],
            'cmsPages' => [],
        ];
    }
}
