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

namespace MageAustralia\LuceneSearch\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use MageAustralia\LuceneSearch\Api\State\Provider\SearchProvider;

#[ApiResource(
    shortName: 'SearchResult',
    description: 'Search across products, categories, and CMS pages',
    provider: SearchProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/search/suggest',
            description: 'Search suggest endpoint for autocomplete and search results',
            security: 'true',
            paginationEnabled: false,
        ),
    ],
)]
class SearchResult
{
    public array $products = [];
    public int $totalItems = 0;
    public array $categories = [];
    public array $blogPosts = [];
    public array $cmsPages = [];
}
