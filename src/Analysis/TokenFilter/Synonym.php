<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Analysis\TokenFilter;

use Maho\Search\Lucene\Analysis\Token;
use Maho\Search\Lucene\Analysis\TokenFilter;

/**
 * Synonym expansion utility.
 *
 * Since Zend-style Lucene's token filter chain can only return one token per input,
 * synonym expansion is done at the query/index level rather than in the filter chain.
 *
 * Usage:
 *   $synonyms = new Synonym([['jean', 'jeans', 'denim'], ['tee', 't-shirt', 'tshirt']]);
 *   $expanded = $synonyms->expandQuery('blue jean');  // "blue jean jeans denim"
 *   $expanded = $synonyms->expandForIndex('Nice tee'); // "Nice tee t-shirt tshirt"
 */
class Synonym extends TokenFilter
{
    /** @var array<string, string[]> term → [synonyms] */
    private array $synonymMap = [];

    /**
     * @param array<array<string>> $groups Array of synonym groups
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

    /**
     * Pass-through — synonym expansion happens at query/index level.
     */
    public function normalize(Token $srcToken): ?Token
    {
        return $srcToken;
    }

    /**
     * Expand a search query with synonyms.
     * Each word that has synonyms gets its synonyms appended.
     *
     * "blue jean" → "blue jean jeans denim"
     */
    public function expandQuery(string $query): string
    {
        $words = preg_split('/\s+/', trim(strtolower($query)));
        $expanded = [];

        foreach ($words as $word) {
            $expanded[] = $word;
            if (isset($this->synonymMap[$word])) {
                foreach ($this->synonymMap[$word] as $synonym) {
                    $expanded[] = $synonym;
                }
            }
        }

        return implode(' ', array_unique($expanded));
    }

    /**
     * Expand text for indexing — adds synonyms so documents match on any variant.
     */
    public function expandForIndex(string $text): string
    {
        return $this->expandQuery($text);
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

    /**
     * Load synonym groups from a text file.
     * Format: one group per line, comma-separated terms.
     * Lines starting with # are comments.
     *
     * Example file:
     *   jean, jeans, denim
     *   tee, t-shirt, tshirt
     *   # This is a comment
     *   sneaker, runner, trainer, tennis shoe
     */
    public function loadFromFile(string $filepath): void
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw new \Maho\Search\Lucene\Exception("Cannot read synonym file: {$filepath}");
        }

        $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $group = array_filter(array_map('trim', explode(',', strtolower($line))));
            if (count($group) < 2) {
                continue;
            }
            foreach ($group as $term) {
                $this->synonymMap[$term] = array_values(array_diff($group, [$term]));
            }
        }
    }
}
