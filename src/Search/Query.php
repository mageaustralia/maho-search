<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Search;

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

/**
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Search
 */
abstract class Query
{
    /**
     * query boost factor
     *
     * @var float
     */
    private $_boost = 1;

    /**
     * Query weight
     *
     * @var \Maho\Search\Lucene\Search\Weight
     */
    protected $_weight = null;

    /**
     * Current highlight color
     *
     * @var integer
     */
    private $_currentColorIndex = 0;

    /**
     * Gets the boost for this clause.  Documents matching
     * this clause will (in addition to the normal weightings) have their score
     * multiplied by boost.   The boost is 1.0 by default.
     *
     * @return float
     */
    public function getBoost()
    {
        return $this->_boost;
    }

    /**
     * Sets the boost for this query clause to $boost.
     *
     * @param float $boost
     */
    public function setBoost($boost)
    {
        $this->_boost = $boost;
    }

    /**
     * Score specified document
     *
     * @param integer $docId
     * @param \Maho\Search\Lucene\LuceneInterface $reader
     * @return float
     */
    abstract public function score($docId, \Maho\Search\Lucene\LuceneInterface $reader);

    /**
     * Get document ids likely matching the query
     *
     * It's an array with document ids as keys (performance considerations)
     *
     * @return array
     */
    abstract public function matchedDocs();

    /**
     * Execute query in context of index reader
     * It also initializes necessary internal structures
     *
     * Query specific implementation
     *
     * @param \Maho\Search\Lucene\LuceneInterface $reader
     * @param \Maho\Search\Lucene\Index\DocsFilter|null $docsFilter
     */
    abstract public function execute(\Maho\Search\Lucene\LuceneInterface $reader, $docsFilter = null);

    /**
     * Constructs an appropriate Weight implementation for this query.
     *
     * @param \Maho\Search\Lucene\LuceneInterface $reader
     * @return \Maho\Search\Lucene\Search\Weight
     */
    abstract public function createWeight(\Maho\Search\Lucene\LuceneInterface $reader);

    /**
     * Constructs an initializes a Weight for a _top-level_query_.
     *
     * @param \Maho\Search\Lucene\LuceneInterface $reader
     */
    protected function _initWeight(\Maho\Search\Lucene\LuceneInterface $reader)
    {
        // Check, that it's a top-level query and query weight is not initialized yet.
        if ($this->_weight !== null) {
            return $this->_weight;
        }

        $this->createWeight($reader);
        $sum = $this->_weight->sumOfSquaredWeights();
        $queryNorm = $reader->getSimilarity()->queryNorm($sum);
        $this->_weight->normalize($queryNorm);
    }

    /**
     * Re-write query into primitive queries in the context of specified index
     *
     * @param \Maho\Search\Lucene\LuceneInterface $index
     * @return \Maho\Search\Lucene\Search\Query
     */
    abstract public function rewrite(\Maho\Search\Lucene\LuceneInterface $index);

    /**
     * Optimize query in the context of specified index
     *
     * @param \Maho\Search\Lucene\LuceneInterface $index
     * @return \Maho\Search\Lucene\Search\Query
     */
    abstract public function optimize(\Maho\Search\Lucene\LuceneInterface $index);

    /**
     * Reset query, so it can be reused within other queries or
     * with other indeces
     */
    public function reset()
    {
        $this->_weight = null;
    }

    /**
     * Print a query
     *
     * @return string
     */
    abstract public function __toString();

    /**
     * Return query terms
     *
     * @return array
     */
    abstract public function getQueryTerms();

    /**
     * Query specific matches highlighting
     *
     * @param \Maho\Search\Lucene\Search\Highlighter\HighlighterInterface $highlighter  Highlighter object (also contains doc for highlighting)
     */
    abstract protected function _highlightMatches(\Maho\Search\Lucene\Search\Highlighter\HighlighterInterface $highlighter);

    /**
     * Highlight matches in $inputHTML
     *
     * @param string $inputHTML
     * @param string  $defaultEncoding   HTML encoding, is used if it's not specified using Content-type HTTP-EQUIV meta tag.
     * @param \Maho\Search\Lucene\Search\Highlighter\HighlighterInterface|null $highlighter
     * @return string
     */
    public function highlightMatches($inputHTML, $defaultEncoding = '', $highlighter = null)
    {
        if ($highlighter === null) {
            $highlighter = new \Maho\Search\Lucene\Search\Highlighter\DefaultHighlighter();
        }

        /** \Maho\Search\Lucene\Document\Html */

        $doc = \Maho\Search\Lucene\Document\Html::loadHTML($inputHTML, false, $defaultEncoding);
        $highlighter->setDocument($doc);

        $this->_highlightMatches($highlighter);

        return $doc->getHTML();
    }

    /**
     * Highlight matches in $inputHtmlFragment and return it (without HTML header and body tag)
     *
     * @param string $inputHtmlFragment
     * @param string  $encoding   Input HTML string encoding
     * @param \Maho\Search\Lucene\Search\Highlighter\HighlighterInterface|null $highlighter
     * @return string
     */
    public function htmlFragmentHighlightMatches($inputHtmlFragment, $encoding = 'UTF-8', $highlighter = null)
    {
        if ($highlighter === null) {
            $highlighter = new \Maho\Search\Lucene\Search\Highlighter\DefaultHighlighter();
        }

        $inputHTML = '<html><head><META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=UTF-8"/></head><body>'
                   . iconv($encoding, 'UTF-8//IGNORE', $inputHtmlFragment) . '</body></html>';

        /** \Maho\Search\Lucene\Document\Html */

        $doc = \Maho\Search\Lucene\Document\Html::loadHTML($inputHTML);
        $highlighter->setDocument($doc);

        $this->_highlightMatches($highlighter);

        return $doc->getHtmlBody();
    }
}

