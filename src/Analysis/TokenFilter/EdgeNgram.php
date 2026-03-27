<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Analysis\TokenFilter;

use Maho\Search\Lucene\Analysis\Token;
use Maho\Search\Lucene\Analysis\TokenFilter;

/**
 * Edge N-gram token filter — generates prefix tokens from the beginning of each term.
 *
 * At index time, "bathroom" generates tokens: "bat", "bath", "bathr", "bathro", "bathro", "bathroo", "bathroom"
 * At search time, the query "bath" will then match documents containing "bathroom", "bathrobe", etc.
 *
 * This filter should only be applied at INDEX time, not at search time.
 * The search query should use the original terms so "bath" matches the "bath" n-gram
 * that was generated from "bathroom".
 */
class EdgeNgram extends TokenFilter
{
    private int $minGram;
    private int $maxGram;

    /** @var Token[] buffered tokens to emit */
    private array $buffer = [];

    public function __construct(int $minGram = 3, int $maxGram = 15)
    {
        $this->minGram = $minGram;
        $this->maxGram = $maxGram;
    }

    /**
     * For Zend-style Lucene, the filter chain processes one token at a time
     * and can only return one token. We can't inject additional tokens.
     *
     * Instead, we append the n-gram variations to the token text separated by spaces.
     * The analyzer's tokenizer will have already split on spaces, so this approach
     * won't work directly in the filter chain.
     *
     * The practical approach: this filter modifies nothing in the chain.
     * Instead, the indexer should call generateNgrams() to create additional
     * field content at index time.
     */
    public function normalize(Token $srcToken): ?Token
    {
        // Pass through unchanged — n-gram expansion is done at the indexer level
        return $srcToken;
    }

    /**
     * Generate edge n-grams for a text string.
     * Returns the original text plus all edge n-gram prefixes, space-separated.
     *
     * Example: "bathroom" with minGram=3 → "bathroom bat bath bathr bathro bathro bathroo"
     */
    public function expandForIndex(string $text): string
    {
        $words = preg_split('/\s+/', trim($text));
        $expanded = [];

        foreach ($words as $word) {
            $word = strtolower($word);
            $expanded[] = $word; // Always include the full word

            $len = mb_strlen($word);
            if ($len <= $this->minGram) {
                continue; // Word is already at or below min gram size
            }

            // Generate prefixes from minGram up to min(maxGram, len-1)
            // We skip the full word length since it's already included
            $upperBound = min($this->maxGram, $len - 1);
            for ($i = $this->minGram; $i <= $upperBound; $i++) {
                $ngram = mb_substr($word, 0, $i);
                $expanded[] = $ngram;
            }
        }

        return implode(' ', array_unique($expanded));
    }
}
