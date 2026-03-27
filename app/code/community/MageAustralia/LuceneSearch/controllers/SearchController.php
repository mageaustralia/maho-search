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
 * Search API controller — provides /api/search/suggest endpoint.
 *
 * Registered as a frontend route so it's accessible without admin auth.
 * Returns JSON matching the storefront's expected response format.
 */
class MageAustralia_LuceneSearch_SearchController extends Mage_Core_Controller_Front_Action
{
    /**
     * GET /lucenesearch/search/suggest?q=...&types=...&limit=...
     */
    public function suggestAction(): void
    {
        $helper = Mage::helper('lucenesearch');
        $storeId = (int) Mage::app()->getStore()->getId();

        if (!$helper->isEnabled($storeId)) {
            $this->_sendJson(['error' => 'Search is disabled'], 503);
            return;
        }

        $query = $this->getRequest()->getParam('q', '');
        $limit = (int) ($this->getRequest()->getParam('limit') ?: $helper->getSuggestLimit($storeId));

        $typesParam = $this->getRequest()->getParam('types', '');
        $types = $typesParam ? array_map('trim', explode(',', $typesParam)) : null;

        $options = ['limit' => $limit, 'page' => 1];
        if ($types !== null) {
            $options['types'] = $types;
        }

        /** @var MageAustralia_LuceneSearch_Model_Search $search */
        $search = Mage::getModel('lucenesearch/search');
        $results = $search->search($query, $storeId, $options);

        // Match storefront expected format
        $response = [
            'products' => $results['products'],
            'totalItems' => $results['totalItems'],
            'categories' => $results['categories'],
            'blogPosts' => [], // Lucene doesn't index blog yet — future enhancement
            'cmsPages' => $results['cmsPages'],
        ];

        $this->_sendJson($response);
    }

    private function _sendJson(array $data, int $httpCode = 200): void
    {
        $this->getResponse()
            ->setHttpResponseCode($httpCode)
            ->setHeader('Content-Type', 'application/json', true)
            ->setHeader('Access-Control-Allow-Origin', '*', true)
            ->setBody(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
