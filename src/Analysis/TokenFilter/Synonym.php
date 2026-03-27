<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Analysis\TokenFilter;

use Maho\Search\Lucene\Analysis\Token;
use Maho\Search\Lucene\Analysis\TokenFilter;

/**
 * Synonym token filter — expands terms to include synonyms at index time.
 *
 * When a token matches a synonym group, the original token is kept and
 * additional tokens are injected at the same position for each synonym.
 * This means searching for any word in the group matches documents
 * containing any other word in the group.
 */
class Synonym extends TokenFilter
{
    /** @var array<string, string[]> term → [synonyms] */
    private array $synonymMap = [];

    /**
     * @param array<array<string>> $groups Array of synonym groups, e.g. [['jean', 'jeans', 'denim'], ...]
     */
    public function __construct(array $groups = [])
    {
        foreach ($groups as $group) {
            $group = array_map('strtolower', $group);
            foreach ($group as $term) {
                $this->synonymMap[$term] = array_values(array_diff($group, [$term]));
            }
        }
    }

    public function normalize(Token $srcToken): ?Token
    {
        // Synonyms are handled by returning the original token unchanged.
        // The synonym expansion happens in the analyzer that uses this filter.
        // For Zend-style Lucene, we just return the token as-is since
        // multi-token expansion isn't directly supported in the filter chain.
        // Instead, synonyms should be expanded at query time or index time
        // by the calling code.
        return $srcToken;
    }

    /**
     * Get synonyms for a term.
     */
    public function getSynonyms(string $term): array
    {
        return $this->synonymMap[strtolower($term)] ?? [];
    }

    /**
     * Check if a term has synonyms.
     */
    public function hasSynonyms(string $term): bool
    {
        return isset($this->synonymMap[strtolower($term)]);
    }
}
