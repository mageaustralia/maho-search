<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Search\Query;

/**
 * Zend Framework
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
 * @category   Zend
 * @package    \Maho\Search\Lucene\Lucene
 * @subpackage Search
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/** \Maho\Search\Lucene\Search\Query */
// require_once 'Zend/Search/Lucene/Search/Query.php';

/**
 * @category   Zend
 * @package    \Maho\Search\Lucene\Lucene
 * @subpackage Search
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Fuzzy extends \Maho\Search\Lucene\Search\Query
{
    /** Default minimum similarity */
    const DEFAULT_MIN_SIMILARITY = 0.5;

    /**
     * Maximum number of matched terms.
     * Apache Lucene defines this limitation as boolean query maximum number of clauses:
     * org.apache.lucene.search.BooleanQuery.getMaxClauseCount()
     */
    const MAX_CLAUSE_COUNT = 1024;

    /**
     * Array of precalculated max distances
     *
     * keys are integers representing a word size
     */
    private $_maxDistances = array();

    /**
     * Base searching term.
     *
     * @var \Maho\Search\Lucene\Index\Term
     */
    private $_term;

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
     * The length of common (non-fuzzy) prefix
     *
     * @var integer
     */
    private $_prefixLength;

    /**
     * Matched terms.
     *
     * Matched terms list.
     * It's filled during the search (rewrite operation) and may be used for search result
     * post-processing
     *
     * Array of \Maho\Search\Lucene\Index\Term objects
     *
     * @var array
     */
    private $_matches = null;

    /**
     * Matched terms scores
     *
     * @var array
     */
    private $_scores = null;

    /**
     * Array of the term keys.
     * Used to sort terms in alphabetical order if terms have the same socres
     *
     * @var array
     */
    private $_termKeys = null;

    /**
     * Default non-fuzzy prefix length
     *
     * @var integer
     */
    private static $_defaultPrefixLength = 3;

    /**
     * \Maho\Search\Lucene\Search\Query\Wildcard constructor.
     *
     * @param \Maho\Search\Lucene\Index\Term $term
     * @param float   $minimumSimilarity
     * @param integer $prefixLength
     * @throws \Maho\Search\Lucene\Exception
     */
    public function __construct(\Maho\Search\Lucene\Index\Term $term, $minimumSimilarity = self::DEFAULT_MIN_SIMILARITY, $prefixLength = null)
    {
        if ($minimumSimilarity < 0) {
            // require_once 'Zend/Search/Lucene/Exception.php';
            throw new \Maho\Search\Lucene\Exception('minimumSimilarity cannot be less than 0');
        }
        if ($minimumSimilarity >= 1) {
            // require_once 'Zend/Search/Lucene/Exception.php';
            throw new \Maho\Search\Lucene\Exception('minimumSimilarity cannot be greater than or equal to 1');
        }
        if ($prefixLength < 0) {
            // require_once 'Zend/Search/Lucene/Exception.php';
            throw new \Maho\Search\Lucene\Exception('prefixLength cannot be less than 0');
        }

        $this->_term              = $term;
        $this->_minimumSimilarity = $minimumSimilarity;
        $this->_prefixLength      = ($prefixLength !== null)? $prefixLength : self::$_defaultPrefixLength;
    }

    /**
     * Get default non-fuzzy prefix length
     *
     * @return integer
     */
    public static function getDefaultPrefixLength()
    {
        return self::$_defaultPrefixLength;
    }

    /**
     * Set default non-fuzzy prefix length
     *
     * @param integer $defaultPrefixLength
     */
    public static function setDefaultPrefixLength($defaultPrefixLength)
    {
        self::$_defaultPrefixLength = $defaultPrefixLength;
    }

    /**
     * Calculate maximum distance for specified word length
     *
     * @param integer $prefixLength
     * @param integer $termLength
     * @param integer $length
     * @return integer
     */
    private function _calculateMaxDistance($prefixLength, $termLength, $length)
    {
        $this->_maxDistances[$length] = (int) ((1 - $this->_minimumSimilarity)*(min($termLength, $length) + $prefixLength));
        return $this->_maxDistances[$length];
    }

    /**
     * Re-write query into primitive queries in the context of specified index
     *
     * @param \Maho\Search\Lucene\LuceneInterface $index
     * @return \Maho\Search\Lucene\Search\Query
     * @throws \Maho\Search\Lucene\Exception
     */
    public function rewrite(\Maho\Search\Lucene\LuceneInterface $index)
    {
        $this->_matches  = array();
        $this->_scores   = array();
        $this->_termKeys = array();

        if ($this->_term->field === null) {
            // Search through all fields
            $fields = $index->getFieldNames(true /* indexed fields list */);
        } else {
            $fields = array($this->_term->field);
        }

        // require_once 'Zend/Search/Lucene/Index/Term.php';
        $prefix           = \Maho\Search\Lucene\Index\Term::getPrefix($this->_term->text, $this->_prefixLength);
        $prefixByteLength = strlen($prefix);
        $prefixUtf8Length = \Maho\Search\Lucene\Index\Term::getLength($prefix);

        $termLength       = \Maho\Search\Lucene\Index\Term::getLength($this->_term->text);

        $termRest         = substr($this->_term->text, $prefixByteLength);
        // we calculate length of the rest in bytes since levenshtein() is not UTF-8 compatible
        $termRestLength   = strlen($termRest);

        $scaleFactor = 1/(1 - $this->_minimumSimilarity);

        // require_once 'Zend/Search/Lucene.php';
        $maxTerms = \Maho\Search\Lucene\Lucene::getTermsPerQueryLimit();
        foreach ($fields as $field) {
            $index->resetTermsStream();

            // require_once 'Zend/Search/Lucene/Index/Term.php';
            if ($prefix != '') {
                $index->skipTo(new \Maho\Search\Lucene\Index\Term($prefix, $field));

                while ($index->currentTerm() !== null          &&
                       $index->currentTerm()->field == $field  &&
                       substr($index->currentTerm()->text, 0, $prefixByteLength) == $prefix) {
                    // Calculate similarity
                    $target = substr($index->currentTerm()->text, $prefixByteLength);

                    $maxDistance = isset($this->_maxDistances[strlen($target)])?
                                       $this->_maxDistances[strlen($target)] :
                                       $this->_calculateMaxDistance($prefixUtf8Length, $termRestLength, strlen($target));

                    if ($termRestLength == 0) {
                        // we don't have anything to compare.  That means if we just add
                        // the letters for current term we get the new word
                        $similarity = (($prefixUtf8Length == 0)? 0 : 1 - strlen($target)/$prefixUtf8Length);
                    } else if (strlen($target) == 0) {
                        $similarity = (($prefixUtf8Length == 0)? 0 : 1 - $termRestLength/$prefixUtf8Length);
                    } else if ($maxDistance < abs($termRestLength - strlen($target))){
                        //just adding the characters of term to target or vice-versa results in too many edits
                        //for example "pre" length is 3 and "prefixes" length is 8.  We can see that
                        //given this optimal circumstance, the edit distance cannot be less than 5.
                        //which is 8-3 or more precisesly abs(3-8).
                        //if our maximum edit distance is 4, then we can discard this word
                        //without looking at it.
                        $similarity = 0;
                    } else {
                        $similarity = 1 - levenshtein($termRest, $target)/($prefixUtf8Length + min($termRestLength, strlen($target)));
                    }

                    if ($similarity > $this->_minimumSimilarity) {
                        $this->_matches[]  = $index->currentTerm();
                        $this->_termKeys[] = $index->currentTerm()->key();
                        $this->_scores[]   = ($similarity - $this->_minimumSimilarity)*$scaleFactor;

                        if ($maxTerms != 0  &&  count($this->_matches) > $maxTerms) {
                            // require_once 'Zend/Search/Lucene/Exception.php';
                            throw new \Maho\Search\Lucene\Exception('Terms per query limit is reached.');
                        }
                    }

                    $index->nextTerm();
                }
            } else {
                $index->skipTo(new \Maho\Search\Lucene\Index\Term('', $field));

                while ($index->currentTerm() !== null  &&  $index->currentTerm()->field == $field) {
                    // Calculate similarity
                    $target = $index->currentTerm()->text;

                    $maxDistance = isset($this->_maxDistances[strlen($target)])?
                                       $this->_maxDistances[strlen($target)] :
                                       $this->_calculateMaxDistance(0, $termRestLength, strlen($target));

                    if ($maxDistance < abs($termRestLength - strlen($target))){
                        //just adding the characters of term to target or vice-versa results in too many edits
                        //for example "pre" length is 3 and "prefixes" length is 8.  We can see that
                        //given this optimal circumstance, the edit distance cannot be less than 5.
                        //which is 8-3 or more precisesly abs(3-8).
                        //if our maximum edit distance is 4, then we can discard this word
                        //without looking at it.
                        $similarity = 0;
                    } else {
                        $similarity = 1 - levenshtein($termRest, $target)/min($termRestLength, strlen($target));
                    }

                    if ($similarity > $this->_minimumSimilarity) {
                        $this->_matches[]  = $index->currentTerm();
                        $this->_termKeys[] = $index->currentTerm()->key();
                        $this->_scores[]   = ($similarity - $this->_minimumSimilarity)*$scaleFactor;

                        if ($maxTerms != 0  &&  count($this->_matches) > $maxTerms) {
                            // require_once 'Zend/Search/Lucene/Exception.php';
                            throw new \Maho\Search\Lucene\Exception('Terms per query limit is reached.');
                        }
                    }

                    $index->nextTerm();
                }
            }

            $index->closeTermsStream();
        }

        if (count($this->_matches) == 0) {
            // require_once 'Zend/Search/Lucene/Search/Query/Empty.php';
            return new \Maho\Search\Lucene\Search\Query\EmptyQuery();
        } else if (count($this->_matches) == 1) {
            // require_once 'Zend/Search/Lucene/Search/Query/Term.php';
            return new \Maho\Search\Lucene\Search\Query\Term(reset($this->_matches));
        } else {
            // require_once 'Zend/Search/Lucene/Search/Query/Boolean.php';
            $rewrittenQuery = new \Maho\Search\Lucene\Search\Query\BooleanQuery();

            array_multisort($this->_scores,   SORT_DESC, SORT_NUMERIC,
                            $this->_termKeys, SORT_ASC,  SORT_STRING,
                            $this->_matches);

            $termCount = 0;
            // require_once 'Zend/Search/Lucene/Search/Query/Term.php';
            foreach ($this->_matches as $id => $matchedTerm) {
                $subquery = new \Maho\Search\Lucene\Search\Query\Term($matchedTerm);
                $subquery->setBoost($this->_scores[$id]);

                $rewrittenQuery->addSubquery($subquery);

                $termCount++;
                if ($termCount >= self::MAX_CLAUSE_COUNT) {
                    break;
                }
            }

            return $rewrittenQuery;
        }
    }

    /**
     * Optimize query in the context of specified index
     *
     * @param \Maho\Search\Lucene\LuceneInterface $index
     * @return \Maho\Search\Lucene\Search\Query
     */
    public function optimize(\Maho\Search\Lucene\LuceneInterface $index)
    {
        // require_once 'Zend/Search/Lucene/Exception.php';
        throw new \Maho\Search\Lucene\Exception('Fuzzy query should not be directly used for search. Use $query->rewrite($index)');
    }

    /**
     * Return query terms
     *
     * @return array
     * @throws \Maho\Search\Lucene\Exception
     */
    public function getQueryTerms()
    {
        if ($this->_matches === null) {
            // require_once 'Zend/Search/Lucene/Exception.php';
            throw new \Maho\Search\Lucene\Exception('Search or rewrite operations have to be performed before.');
        }

        return $this->_matches;
    }

    /**
     * Constructs an appropriate Weight implementation for this query.
     *
     * @param \Maho\Search\Lucene\LuceneInterface $reader
     * @return \Maho\Search\Lucene\Search\Weight
     * @throws \Maho\Search\Lucene\Exception
     */
    public function createWeight(\Maho\Search\Lucene\LuceneInterface $reader)
    {
        // require_once 'Zend/Search/Lucene/Exception.php';
        throw new \Maho\Search\Lucene\Exception('Fuzzy query should not be directly used for search. Use $query->rewrite($index)');
    }

    /**
     * Execute query in context of index reader
     * It also initializes necessary internal structures
     *
     * @param \Maho\Search\Lucene\LuceneInterface $reader
     * @param \Maho\Search\Lucene\Index\DocsFilter|null $docsFilter
     * @throws \Maho\Search\Lucene\Exception
     */
    public function execute(\Maho\Search\Lucene\LuceneInterface $reader, $docsFilter = null)
    {
        // require_once 'Zend/Search/Lucene/Exception.php';
        throw new \Maho\Search\Lucene\Exception('Fuzzy query should not be directly used for search. Use $query->rewrite($index)');
    }

    /**
     * Get document ids likely matching the query
     *
     * It's an array with document ids as keys (performance considerations)
     *
     * @return array
     * @throws \Maho\Search\Lucene\Exception
     */
    public function matchedDocs()
    {
        // require_once 'Zend/Search/Lucene/Exception.php';
        throw new \Maho\Search\Lucene\Exception('Fuzzy query should not be directly used for search. Use $query->rewrite($index)');
    }

    /**
     * Score specified document
     *
     * @param integer $docId
     * @param \Maho\Search\Lucene\LuceneInterface $reader
     * @return float
     * @throws \Maho\Search\Lucene\Exception
     */
    public function score($docId, \Maho\Search\Lucene\LuceneInterface $reader)
    {
        // require_once 'Zend/Search/Lucene/Exception.php';
        throw new \Maho\Search\Lucene\Exception('Fuzzy query should not be directly used for search. Use $query->rewrite($index)');
    }

    /**
     * Query specific matches highlighting
     *
     * @param \Maho\Search\Lucene\Search\Highlighter\HighlighterInterface $highlighter  Highlighter object (also contains doc for highlighting)
     */
    protected function _highlightMatches(\Maho\Search\Lucene\Search\Highlighter\HighlighterInterface $highlighter)
    {
        $words = array();

        // require_once 'Zend/Search/Lucene/Index/Term.php';
        $prefix           = \Maho\Search\Lucene\Index\Term::getPrefix($this->_term->text, $this->_prefixLength);
        $prefixByteLength = strlen($prefix);
        $prefixUtf8Length = \Maho\Search\Lucene\Index\Term::getLength($prefix);

        $termLength       = \Maho\Search\Lucene\Index\Term::getLength($this->_term->text);

        $termRest         = substr($this->_term->text, $prefixByteLength);
        // we calculate length of the rest in bytes since levenshtein() is not UTF-8 compatible
        $termRestLength   = strlen($termRest);

        $scaleFactor = 1/(1 - $this->_minimumSimilarity);

        $docBody = $highlighter->getDocument()->getFieldUtf8Value('body');
        // require_once 'Zend/Search/Lucene/Analysis/Analyzer.php';
        $tokens = \Maho\Search\Lucene\Analysis\Analyzer::getDefault()->tokenize($docBody, 'UTF-8');
        foreach ($tokens as $token) {
            $termText = $token->getTermText();

            if (substr($termText, 0, $prefixByteLength) == $prefix) {
                // Calculate similarity
                $target = substr($termText, $prefixByteLength);

                $maxDistance = isset($this->_maxDistances[strlen($target)])?
                                   $this->_maxDistances[strlen($target)] :
                                   $this->_calculateMaxDistance($prefixUtf8Length, $termRestLength, strlen($target));

                if ($termRestLength == 0) {
                    // we don't have anything to compare.  That means if we just add
                    // the letters for current term we get the new word
                    $similarity = (($prefixUtf8Length == 0)? 0 : 1 - strlen($target)/$prefixUtf8Length);
                } else if (strlen($target) == 0) {
                    $similarity = (($prefixUtf8Length == 0)? 0 : 1 - $termRestLength/$prefixUtf8Length);
                } else if ($maxDistance < abs($termRestLength - strlen($target))){
                    //just adding the characters of term to target or vice-versa results in too many edits
                    //for example "pre" length is 3 and "prefixes" length is 8.  We can see that
                    //given this optimal circumstance, the edit distance cannot be less than 5.
                    //which is 8-3 or more precisesly abs(3-8).
                    //if our maximum edit distance is 4, then we can discard this word
                    //without looking at it.
                    $similarity = 0;
                } else {
                    $similarity = 1 - levenshtein($termRest, $target)/($prefixUtf8Length + min($termRestLength, strlen($target)));
                }

                if ($similarity > $this->_minimumSimilarity) {
                    $words[] = $termText;
                }
            }
        }

        $highlighter->highlight($words);
    }

    /**
     * Print a query
     *
     * @return string
     */
    public function __toString()
    {
        // It's used only for query visualisation, so we don't care about characters escaping
        return (($this->_term->field === null)? '' : $this->_term->field . ':')
             . $this->_term->text . '~'
             . (($this->_minimumSimilarity != self::DEFAULT_MIN_SIMILARITY)? round($this->_minimumSimilarity, 4) : '')
             . (($this->getBoost() != 1)? '^' . round($this->getBoost(), 4) : '');
    }
}

