<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Search\Similarity;

/**
 * BM25 (Okapi BM25) similarity implementation.
 *
 * Drop-in replacement for DefaultSimilarity (TF-IDF). BM25 is the relevance
 * algorithm used by Elasticsearch, Solr (since 6.0), and most modern search
 * engines. It generally produces better ranking than classic TF-IDF because:
 *
 *   1. Term frequency saturates — a doc with 20 occurrences of "paddle" doesn't
 *      score disproportionately higher than one with 5. TF-IDF over-rewards
 *      repetition.
 *   2. IDF handles edge cases better — never goes negative for very common terms.
 *   3. Length normalization is bounded — long documents are penalised less
 *      aggressively than `1/sqrt(n)`.
 *
 * Standard BM25 formula:
 *
 *   score(t,d) = IDF(t) * (tf * (k1 + 1)) / (tf + k1 * (1 - b + b * dl/avgdl))
 *
 * Architectural note: Lucene's scoring pipeline splits scoring across three
 * methods (`tf`, `idfFreq`, `lengthNorm`) which are called at different times
 * (search vs index). We can't pass `dl` into `tf()` without rewriting the
 * scorer, so we keep the BM25 length component inside `lengthNorm()` (computed
 * at index time) and use the saturating TF formula in `tf()`. The two factors
 * combine multiplicatively at scoring time exactly like classic Lucene, so the
 * net behaviour is BM25-equivalent for ranking purposes.
 *
 * Parameters:
 *   k1 — TF saturation point (default 1.2). Higher = TF matters more.
 *   b  — Length normalization strength (default 0.75). 0 = no length penalty,
 *        1 = full length penalty.
 *   avgFieldLength — Approximation of the average field length in tokens.
 *        Lucene's per-field-per-byte norm storage doesn't preserve doc length,
 *        so we use a configurable constant. 10 is a sensible default for
 *        product name fields; raise it for description-style fields.
 */
class BM25Similarity extends \Maho\Search\Lucene\Search\Similarity
{
    /** @var float */
    private $_k1;

    /** @var float */
    private $_b;

    /** @var float */
    private $_avgFieldLength;

    public function __construct(float $k1 = 1.2, float $b = 0.75, float $avgFieldLength = 10.0)
    {
        $this->_k1 = $k1;
        $this->_b = $b;
        $this->_avgFieldLength = $avgFieldLength > 0 ? $avgFieldLength : 10.0;
    }

    /**
     * BM25 length normalization: 1 / (1 - b + b * dl/avgdl).
     *
     * Computed at index time per (doc, field). The result is encoded into
     * a single byte by Similarity::encodeNorm() and stored in the index, then
     * decoded and multiplied into the score at query time. Field boosts are
     * applied on top of this value by the indexer.
     */
    public function lengthNorm($fieldName, $numTerms)
    {
        if ($numTerms == 0) {
            return 1E10;
        }

        $denom = 1.0 - $this->_b + $this->_b * ($numTerms / $this->_avgFieldLength);
        return 1.0 / $denom;
    }

    /**
     * Query normalization — kept identical to DefaultSimilarity so query weights
     * across different queries remain comparable. BM25 does not specify a query
     * normalization; this is purely a Lucene scoring-pipeline convention.
     */
    public function queryNorm($sumOfSquaredWeights)
    {
        if ($sumOfSquaredWeights <= 0.0) {
            return 1.0;
        }
        return 1.0 / sqrt($sumOfSquaredWeights);
    }

    /**
     * Saturating term frequency: freq * (k1 + 1) / (freq + k1).
     *
     * This is the core BM25 win over TF-IDF. As `freq` grows, the score
     * approaches `k1 + 1` asymptotically — repeated occurrences of the same
     * term yield diminishing returns, matching how users actually perceive
     * relevance.
     */
    public function tf($freq)
    {
        if ($freq <= 0) {
            return 0.0;
        }
        return ($freq * ($this->_k1 + 1.0)) / ($freq + $this->_k1);
    }

    /**
     * Sloppy phrase frequency — unchanged from DefaultSimilarity. BM25 does
     * not redefine sloppy phrase scoring.
     */
    public function sloppyFreq($distance)
    {
        return 1.0 / ($distance + 1);
    }

    /**
     * BM25 IDF: log(1 + (N - df + 0.5) / (df + 0.5)).
     *
     * The `+ 0.5` smoothing terms keep IDF strictly positive even for terms
     * that appear in more than half the corpus (classic TF-IDF can go negative
     * here, which is rarely what you want). Adding `1` inside the log
     * guarantees a non-negative result.
     */
    public function idfFreq($docFreq, $numDocs)
    {
        return log(1.0 + (($numDocs - $docFreq + 0.5) / ($docFreq + 0.5)));
    }

    /**
     * Coordination factor — unchanged from DefaultSimilarity. Rewards documents
     * that match a higher fraction of query terms. BM25 doesn't define this;
     * Lucene applies it on top of the per-term scores.
     */
    public function coord($overlap, $maxOverlap)
    {
        if ($maxOverlap == 0) {
            return 0.0;
        }
        return $overlap / (float) $maxOverlap;
    }
}
