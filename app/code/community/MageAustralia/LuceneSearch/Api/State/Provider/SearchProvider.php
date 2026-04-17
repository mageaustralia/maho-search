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

namespace MageAustralia\LuceneSearch\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\Service\StoreContext;
use MageAustralia\LuceneSearch\Api\Resource\SearchResult;

class SearchProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $context['request'] ?? null;
        $query = $request ? ($request->query->get('q') ?? '') : '';
        $limit = $request ? (int) ($request->query->get('limit') ?? 0) : 0;
        $typesParam = $request ? ($request->query->get('types') ?? '') : '';

        $storeId = StoreContext::ensureStore();

        /** @var \MageAustralia_LuceneSearch_Helper_Data $helper */
        $helper = \Mage::helper('lucenesearch');

        if (!$helper->isEnabled($storeId)) {
            return $this->buildResult([]);
        }

        if (!$limit) {
            $limit = $helper->getSuggestLimit($storeId);
        }

        $options = ['limit' => $limit, 'page' => 1];
        if ($typesParam !== '') {
            $options['types'] = array_map('trim', explode(',', $typesParam));
        }

        /** @var \MageAustralia_LuceneSearch_Model_Search $search */
        $search = \Mage::getModel('lucenesearch/search');
        $results = $search->search($query, $storeId, $options);

        return $this->buildResult($results);
    }

    private function buildResult(array $results): SearchResult
    {
        $result = new SearchResult();
        $result->products = $results['products'] ?? [];
        $result->totalItems = $results['totalItems'] ?? 0;
        $result->categories = $results['categories'] ?? [];
        $result->blogPosts = [];
        $result->cmsPages = $results['cmsPages'] ?? [];
        return $result;
    }
}
