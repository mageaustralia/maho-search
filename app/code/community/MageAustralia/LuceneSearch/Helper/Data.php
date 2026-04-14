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

class MageAustralia_LuceneSearch_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'MageAustralia_LuceneSearch';

    public function isEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag('lucenesearch/general/enabled', $storeId);
    }

    public function getIndexPath(?int $storeId = null): string
    {
        $basePath = Mage::getStoreConfig('lucenesearch/general/index_path', $storeId) ?: 'var/lucene';
        return Mage::getBaseDir() . DS . $basePath;
    }

    public function getStoreIndexPath(string $storeCode): string
    {
        return $this->getIndexPath() . DS . $storeCode;
    }

    // Product config

    public function isProductIndexEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && Mage::getStoreConfigFlag('lucenesearch/products/enabled', $storeId);
    }

    public function getProductSearchableAttributes(?int $storeId = null): array
    {
        $value = Mage::getStoreConfig('lucenesearch/products/searchable_attributes', $storeId);
        return array_filter(array_map('trim', explode(',', (string) $value)));
    }

    public function getProductAttributeBoost(string $attributeCode, ?int $storeId = null): float
    {
        $value = Mage::getStoreConfig("lucenesearch/products/boost_{$attributeCode}", $storeId);
        return $value !== null ? (float) $value : 1.0;
    }

    public function isProductCategoryPathsEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag('lucenesearch/products/index_category_paths', $storeId);
    }

    public function getProductCategoryPathsBoost(?int $storeId = null): float
    {
        return (float) (Mage::getStoreConfig('lucenesearch/products/boost_category_paths', $storeId) ?: 1.0);
    }

    // Category config

    public function isCategoryIndexEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && Mage::getStoreConfigFlag('lucenesearch/categories/enabled', $storeId);
    }

    public function getCategorySearchableAttributes(?int $storeId = null): array
    {
        $value = Mage::getStoreConfig('lucenesearch/categories/searchable_attributes', $storeId);
        return array_filter(array_map('trim', explode(',', (string) $value)));
    }

    public function getCategoryAttributeBoost(string $attributeCode, ?int $storeId = null): float
    {
        $value = Mage::getStoreConfig("lucenesearch/categories/boost_{$attributeCode}", $storeId);
        return $value !== null ? (float) $value : 1.0;
    }

    // CMS config

    public function isCmsIndexEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && Mage::getStoreConfigFlag('lucenesearch/cms/enabled', $storeId);
    }

    public function getCmsSearchableAttributes(?int $storeId = null): array
    {
        $value = Mage::getStoreConfig('lucenesearch/cms/searchable_attributes', $storeId);
        return array_filter(array_map('trim', explode(',', (string) $value)));
    }

    public function getCmsExcludedPages(?int $storeId = null): array
    {
        $value = Mage::getStoreConfig('lucenesearch/cms/excluded_pages', $storeId);
        return array_filter(array_map('trim', explode(',', (string) $value)));
    }

    public function getCmsAttributeBoost(string $attributeCode, ?int $storeId = null): float
    {
        $value = Mage::getStoreConfig("lucenesearch/cms/boost_{$attributeCode}", $storeId);
        return $value !== null ? (float) $value : 1.0;
    }

    // Search config

    public function getResultLimit(?int $storeId = null): int
    {
        return (int) (Mage::getStoreConfig('lucenesearch/search/result_limit', $storeId) ?: 500);
    }

    public function getSuggestLimit(?int $storeId = null): int
    {
        return (int) (Mage::getStoreConfig('lucenesearch/search/suggest_limit', $storeId) ?: 10);
    }

    public function isFuzzyFallbackEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag('lucenesearch/search/fuzzy_fallback', $storeId);
    }

    /**
     * Get the configured scoring algorithm: 'tfidf' (classic Lucene) or 'bm25'.
     */
    public function getSimilarityAlgorithm(?int $storeId = null): string
    {
        $value = Mage::getStoreConfig('lucenesearch/search/similarity', $storeId);
        return $value === 'bm25' ? 'bm25' : 'tfidf';
    }

    /**
     * Install the configured similarity implementation as the Lucene default.
     * Called from initAnalyzer() so all index/search code paths get the same
     * scorer. Switching between TF-IDF and BM25 requires a reindex because
     * lengthNorm values stored in the index differ between algorithms.
     */
    public function initSimilarity(?int $storeId = null): void
    {
        if ($this->getSimilarityAlgorithm($storeId) === 'bm25') {
            $k1 = (float) (Mage::getStoreConfig('lucenesearch/search/bm25_k1', $storeId) ?: 1.2);
            $b = (float) (Mage::getStoreConfig('lucenesearch/search/bm25_b', $storeId) ?: 0.75);
            $avgLen = (float) (Mage::getStoreConfig('lucenesearch/search/bm25_avg_field_length', $storeId) ?: 10.0);
            \Maho\Search\Lucene\Search\Similarity::setDefault(
                new \Maho\Search\Lucene\Search\Similarity\BM25Similarity($k1, $b, $avgLen)
            );
        } else {
            \Maho\Search\Lucene\Search\Similarity::setDefault(
                new \Maho\Search\Lucene\Search\Similarity\DefaultSimilarity()
            );
        }
    }

    /**
     * Configure the default Lucene analyzer with stemmer, stop words, and ASCII folding.
     * Must be called before any index operations.
     */
    public function initAnalyzer(): void
    {
        $this->initSimilarity();
        $analyzer = new \Maho\Search\Lucene\Analysis\Analyzer\Common\Utf8Num\CaseInsensitive();
        $analyzer->addFilter(new \Maho\Search\Lucene\Analysis\TokenFilter\AsciiFolding());
        $analyzer->addFilter(new \Maho\Search\Lucene\Analysis\TokenFilter\StopWords([
            'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to',
            'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be',
            'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
            'will', 'would', 'could', 'should', 'may', 'might', 'shall',
            'can', 'it', 'its', 'not', 'no', 'nor', 'so', 'if', 'then',
            'than', 'that', 'this', 'these', 'those', 'from', 'into',
            'about', 'up', 'out', 'off', 'over', 'under', 'again',
            'further', 'once', 'here', 'there', 'when', 'where', 'why',
            'how', 'all', 'each', 'every', 'both', 'few', 'more', 'most',
            'other', 'some', 'such', 'only', 'own', 'same', 'just',
        ]));
        $analyzer->addFilter(new \Maho\Search\Lucene\Analysis\TokenFilter\PorterStemmer());
        \Maho\Search\Lucene\Analysis\Analyzer::setDefault($analyzer);
    }

    /**
     * Get the synonym expander instance with configured synonym groups.
     */
    public function getSynonymFilter(): \Maho\Search\Lucene\Analysis\TokenFilter\Synonym
    {
        $synonyms = new \Maho\Search\Lucene\Analysis\TokenFilter\Synonym($this->getSynonymGroups());

        // Load from file if configured
        $synonymFile = Mage::getStoreConfig('lucenesearch/search/synonym_file');
        if ($synonymFile) {
            $filePath = Mage::getBaseDir() . DS . $synonymFile;
            if (file_exists($filePath)) {
                $synonyms->loadFromFile($filePath);
            }
        }

        return $synonyms;
    }

    /**
     * Get synonym groups from config.
     * Format in config: one group per line, comma-separated terms.
     */
    public function getSynonymGroups(): array
    {
        $config = Mage::getStoreConfig('lucenesearch/search/synonyms');
        if (!$config) {
            return [];
        }

        $groups = [];
        $lines = preg_split('/\r?\n/', trim($config));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $group = array_filter(array_map('trim', explode(',', $line)));
            if (count($group) >= 2) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * Get the edge n-gram expander instance.
     */
    public function getEdgeNgramFilter(): \Maho\Search\Lucene\Analysis\TokenFilter\EdgeNgram
    {
        return new \Maho\Search\Lucene\Analysis\TokenFilter\EdgeNgram(3, 15);
    }
}
