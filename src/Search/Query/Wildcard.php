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
class Wildcard extends \Maho\Search\Lucene\Search\Query
{
    /**
     * Search pattern.
     *
     * Field has to be fully specified or has to be null
     * Text may contain '*' or '?' symbols
     *
     * @var \Maho\Search\Lucene\Index\Term
     */
    private $_pattern;

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
     * Minimum term prefix length (number of minimum non-wildcard characters)
     *
     * @var integer
     */
    private static $_minPrefixLength = 3;

    /**
     * \Maho\Search\Lucene\Search\Query\Wildcard constructor.
     *
     * @param \Maho\Search\Lucene\Index\Term $pattern
     */
    public function __construct(\Maho\Search\Lucene\Index\Term $pattern)
    {
        $this->_pattern = $pattern;
    }

    /**
     * Get minimum prefix length
     *
     * @return integer
     */
    public static function getMinPrefixLength()
    {
        return self::$_minPrefixLength;
    }

    /**
     * Set minimum prefix length
     *
     * @param integer $minPrefixLength
     */
    public static function setMinPrefixLength($minPrefixLength)
    {
        self::$_minPrefixLength = $minPrefixLength;
    }

    /**
     * Get terms prefix
     *
     * @param string $word
     * @return string
     */
    private static function _getPrefix($word)
    {
        $questionMarkPosition = strpos($word, '?');
        $astrericPosition     = strpos($word, '*');

        if ($questionMarkPosition !== false) {
            if ($astrericPosition !== false) {
                return substr($word, 0, min($questionMarkPosition, $astrericPosition));
            }

            return substr($word, 0, $questionMarkPosition);
        } else if ($astrericPosition !== false) {
            return substr($word, 0, $astrericPosition);
        }

        return $word;
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
        $this->_matches = array();

        if ($this->_pattern->field === null) {
            // Search through all fields
            $fields = $index->getFieldNames(true /* indexed fields list */);
        } else {
            $fields = array($this->_pattern->field);
        }

        $prefix          = self::_getPrefix($this->_pattern->text);
        $prefixLength    = strlen($prefix);
        $matchExpression = '/^' . str_replace(array('\\?', '\\*'), array('.', '.*') , preg_quote($this->_pattern->text, '/')) . '$/';

        if ($prefixLength < self::$_minPrefixLength) {
            // require_once 'Zend/Search/Lucene/Exception.php';
            throw new \Maho\Search\Lucene\Exception('At least ' . self::$_minPrefixLength . ' non-wildcard characters are required at the beginning of pattern.');
        }

        /** @todo check for PCRE unicode support may be performed through Zend_Environment in some future */
        if (@preg_match('/\pL/u', 'a') == 1) {
            // PCRE unicode support is turned on
            // add Unicode modifier to the match expression
            $matchExpression .= 'u';
        }

        $maxTerms = \Maho\Search\Lucene\Lucene::getTermsPerQueryLimit();
        foreach ($fields as $field) {
            $index->resetTermsStream();

            // require_once 'Zend/Search/Lucene/Index/Term.php';
            if ($prefix != '') {
                $index->skipTo(new \Maho\Search\Lucene\Index\Term($prefix, $field));

                while ($index->currentTerm() !== null          &&
                       $index->currentTerm()->field == $field  &&
                       substr($index->currentTerm()->text, 0, $prefixLength) == $prefix) {
                    if (preg_match($matchExpression, $index->currentTerm()->text) === 1) {
                        $this->_matches[] = $index->currentTerm();

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
                    if (preg_match($matchExpression, $index->currentTerm()->text) === 1) {
                        $this->_matches[] = $index->currentTerm();

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
            // require_once 'Zend/Search/Lucene/Search/Query/MultiTerm.php';
            $rewrittenQuery = new \Maho\Search\Lucene\Search\Query\MultiTerm();

            foreach ($this->_matches as $matchedTerm) {
                $rewrittenQuery->addTerm($matchedTerm);
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
        throw new \Maho\Search\Lucene\Exception('Wildcard query should not be directly used for search. Use $query->rewrite($index)');
    }

    /**
     * Returns query pattern
     *
     * @return \Maho\Search\Lucene\Index\Term
     */
    public function getPattern()
    {
        return $this->_pattern;
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
            throw new \Maho\Search\Lucene\Exception('Search has to be performed first to get matched terms');
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
        throw new \Maho\Search\Lucene\Exception('Wildcard query should not be directly used for search. Use $query->rewrite($index)');
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
        throw new \Maho\Search\Lucene\Exception('Wildcard query should not be directly used for search. Use $query->rewrite($index)');
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
        throw new \Maho\Search\Lucene\Exception('Wildcard query should not be directly used for search. Use $query->rewrite($index)');
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
        throw new \Maho\Search\Lucene\Exception('Wildcard query should not be directly used for search. Use $query->rewrite($index)');
    }

    /**
     * Query specific matches highlighting
     *
     * @param \Maho\Search\Lucene\Search\Highlighter\HighlighterInterface $highlighter  Highlighter object (also contains doc for highlighting)
     */
    protected function _highlightMatches(\Maho\Search\Lucene\Search\Highlighter\HighlighterInterface $highlighter)
    {
        $words = array();

        $matchExpression = '/^' . str_replace(array('\\?', '\\*'), array('.', '.*') , preg_quote($this->_pattern->text, '/')) . '$/';
        if (@preg_match('/\pL/u', 'a') == 1) {
            // PCRE unicode support is turned on
            // add Unicode modifier to the match expression
            $matchExpression .= 'u';
        }

        $docBody = $highlighter->getDocument()->getFieldUtf8Value('body');
        // require_once 'Zend/Search/Lucene/Analysis/Analyzer.php';
        $tokens = \Maho\Search\Lucene\Analysis\Analyzer::getDefault()->tokenize($docBody, 'UTF-8');
        foreach ($tokens as $token) {
            if (preg_match($matchExpression, $token->getTermText()) === 1) {
                $words[] = $token->getTermText();
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
        if ($this->_pattern->field !== null) {
            $query = $this->_pattern->field . ':';
        } else {
            $query = '';
        }

        $query .= $this->_pattern->text;

        if ($this->getBoost() != 1) {
            $query = $query . '^' . round($this->getBoost(), 4);
        }

        return $query;
    }
}

