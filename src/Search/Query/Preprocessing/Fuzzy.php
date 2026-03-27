<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Search\Query\Preprocessing;

/**
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (https://www.zend.com)
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/BSD-3-Clause  BSD 3-Clause License
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Search
 */

/** Zend_Search_Lucene_Search_Query_Processing */

/**
 * It's an internal abstract class intended to finalize ase a query processing after query parsing.
 * This type of query is not actually involved into query execution.
 *
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Search
 * @internal
 */
class Fuzzy extends \Maho\Search\Lucene\Search\Query\Preprocessing
{
    /**
     * word (query parser lexeme) to find.
     *
     * @var string
     */
    private $_word;

    /**
     * Word encoding (field name is always provided using UTF-8 encoding since it may be retrieved from index).
     *
     * @var string
     */
    private $_encoding;

    /**
     * Field name.
     *
     * @var string
     */
    private $_field;

    /**
     * A value between 0 and 1 to set the required similarity
     *  between the query term and the matching terms. For example, for a
     *  _minimumSimilarity of 0.5 a term of the same length
     *  as the query term is considered similar to the query term if the edit distance
     *  between both terms is less than length(term)*0.5
     *
     * @var float
     */
    private $_minimumSimilarity;

    /**
     * Class constructor.  Create a new preprocessing object for prase query.
     *
     * @param string $word       Non-tokenized word (query parser lexeme) to search.
     * @param string $encoding   Word encoding.
     * @param string $fieldName  Field name.
     * @param float  $minimumSimilarity minimum similarity
     */
    public function __construct($word, $encoding, $fieldName, $minimumSimilarity)
    {
        $this->_word     = $word;
        $this->_encoding = $encoding;
        $this->_field    = $fieldName;
        $this->_minimumSimilarity = $minimumSimilarity;
    }

    /**
     * Re-write query into primitive queries in the context of specified index
     *
     * @param \Maho\Search\Lucene\LuceneInterface $index
     * @return \Maho\Search\Lucene\Search\Query
     */
    public function rewrite(\Maho\Search\Lucene\LuceneInterface $index)
    {
        if ($this->_field === null) {
            $query = new \Maho\Search\Lucene\Search\Query\BooleanQuery();

            $hasInsignificantSubqueries = false;

            if (\Maho\Search\Lucene\Lucene::getDefaultSearchField() === null) {
                $searchFields = $index->getFieldNames(true);
            } else {
                $searchFields = array(\Maho\Search\Lucene\Lucene::getDefaultSearchField());
            }

            foreach ($searchFields as $fieldName) {
                $subquery = new \Maho\Search\Lucene\Search\Query\Preprocessing\Fuzzy($this->_word,
                                                                                    $this->_encoding,
                                                                                    $fieldName,
                                                                                    $this->_minimumSimilarity);

                $rewrittenSubquery = $subquery->rewrite($index);

                if ( !($rewrittenSubquery instanceof \Maho\Search\Lucene\Search\Query\Insignificant  ||
                       $rewrittenSubquery instanceof \Maho\Search\Lucene\Search\Query\EmptyQuery) ) {
                    $query->addSubquery($rewrittenSubquery);
                }

                if ($rewrittenSubquery instanceof \Maho\Search\Lucene\Search\Query\Insignificant) {
                    $hasInsignificantSubqueries = true;
                }
            }

            $subqueries = $query->getSubqueries();

            if (count($subqueries) == 0) {
                $this->_matches = array();
                if ($hasInsignificantSubqueries) {
                    return new \Maho\Search\Lucene\Search\Query\Insignificant();
                } else {
                    return new \Maho\Search\Lucene\Search\Query\EmptyQuery();
                }
            }

            if (count($subqueries) == 1) {
                $query = reset($subqueries);
            }

            $query->setBoost($this->getBoost());

            $this->_matches = $query->getQueryTerms();
            return $query;
        }

        // -------------------------------------
        // Recognize exact term matching (it corresponds to Keyword fields stored in the index)
        // encoding is not used since we expect binary matching
        $term = new \Maho\Search\Lucene\Index\Term($this->_word, $this->_field);
        if ($index->hasTerm($term)) {
            $query = new \Maho\Search\Lucene\Search\Query\Fuzzy($term, $this->_minimumSimilarity);
            $query->setBoost($this->getBoost());

            // Get rewritten query. Important! It also fills terms matching container.
            $rewrittenQuery = $query->rewrite($index);
            $this->_matches = $query->getQueryTerms();

            return $rewrittenQuery;
        }

        // -------------------------------------
        // Recognize wildcard queries

        /** @todo check for PCRE unicode support may be performed through Zend_Environment in some future */
        if (@preg_match('/\pL/u', 'a') == 1) {
            $subPatterns = preg_split('/[*?]/u', iconv($this->_encoding, 'UTF-8', $this->_word));
        } else {
            $subPatterns = preg_split('/[*?]/', $this->_word);
        }
        if (count($subPatterns) > 1) {
            throw new \Maho\Search\Lucene\Search\QueryParserException('Fuzzy search doesn\'t support wildcards (except within Keyword fields).');
        }

        // -------------------------------------
        // Recognize one-term multi-term and "insignificant" queries
        $tokens = \Maho\Search\Lucene\Analysis\Analyzer::getDefault()->tokenize($this->_word, $this->_encoding);

        if (count($tokens) == 0) {
            $this->_matches = array();
            return new \Maho\Search\Lucene\Search\Query\Insignificant();
        }

        if (count($tokens) == 1) {
            $term  = new \Maho\Search\Lucene\Index\Term($tokens[0]->getTermText(), $this->_field);
            $query = new \Maho\Search\Lucene\Search\Query\Fuzzy($term, $this->_minimumSimilarity);
            $query->setBoost($this->getBoost());

            // Get rewritten query. Important! It also fills terms matching container.
            $rewrittenQuery = $query->rewrite($index);
            $this->_matches = $query->getQueryTerms();

            return $rewrittenQuery;
        }

        // Word is tokenized into several tokens
        throw new \Maho\Search\Lucene\Search\QueryParserException('Fuzzy search is supported only for non-multiple word terms');
    }

    /**
     * Query specific matches highlighting
     *
     * @param \Maho\Search\Lucene\Search\Highlighter\HighlighterInterface $highlighter  Highlighter object (also contains doc for highlighting)
     */
    protected function _highlightMatches(\Maho\Search\Lucene\Search\Highlighter\HighlighterInterface $highlighter)
    {
        /** Skip fields detection. We don't need it, since we expect all fields presented in the HTML body and don't differentiate them */

        /** Skip exact term matching recognition, keyword fields highlighting is not supported */

        // -------------------------------------
        // Recognize wildcard queries

        /** @todo check for PCRE unicode support may be performed through Zend_Environment in some future */
        if (@preg_match('/\pL/u', 'a') == 1) {
            $subPatterns = preg_split('/[*?]/u', iconv($this->_encoding, 'UTF-8', $this->_word));
        } else {
            $subPatterns = preg_split('/[*?]/', $this->_word);
        }
        if (count($subPatterns) > 1) {
            // Do nothing
            return;
        }

        // -------------------------------------
        // Recognize one-term multi-term and "insignificant" queries
        $tokens = \Maho\Search\Lucene\Analysis\Analyzer::getDefault()->tokenize($this->_word, $this->_encoding);
        if (count($tokens) == 0) {
            // Do nothing
            return;
        }
        if (count($tokens) == 1) {
            $term  = new \Maho\Search\Lucene\Index\Term($tokens[0]->getTermText(), $this->_field);
            $query = new \Maho\Search\Lucene\Search\Query\Fuzzy($term, $this->_minimumSimilarity);

            $query->_highlightMatches($highlighter);
            return;
        }

        // Word is tokenized into several tokens
        // But fuzzy search is supported only for non-multiple word terms
        // Do nothing
    }

    /**
     * Print a query
     *
     * @return string
     */
    public function __toString()
    {
        // It's used only for query visualisation, so we don't care about characters escaping
        if ($this->_field !== null) {
            $query = $this->_field . ':';
        } else {
            $query = '';
        }

        $query .= $this->_word;

        if ($this->getBoost() != 1) {
            $query .= '^' . round($this->getBoost(), 4);
        }

        return $query;
    }
}
