<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Search\Query;

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

/** \Maho\Search\Lucene\Search\Query */

/**
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Search
 */
class Term extends \Maho\Search\Lucene\Search\Query
{
    /**
     * Term to find.
     *
     * @var \Maho\Search\Lucene\Index\Term
     */
    private $_term;

    /**
     * Documents vector.
     *
     * @var array
     */
    private $_docVector = null;

    /**
     * Term freqs vector.
     * array(docId => freq, ...)
     *
     * @var array
     */
    private $_termFreqs;

    /**
     * \Maho\Search\Lucene\Search\Query\Term constructor
     *
     * @param \Maho\Search\Lucene\Index\Term $term
     * @param boolean $sign
     */
    public function __construct(\Maho\Search\Lucene\Index\Term $term)
    {
        $this->_term = $term;
    }

    /**
     * Re-write query into primitive queries in the context of specified index
     *
     * @param \Maho\Search\Lucene\LuceneInterface $index
     * @return \Maho\Search\Lucene\Search\Query
     */
    public function rewrite(\Maho\Search\Lucene\LuceneInterface $index)
    {
        if ($this->_term->field != null) {
            return $this;
        } else {
            $query = new \Maho\Search\Lucene\Search\Query\MultiTerm();
            $query->setBoost($this->getBoost());

            foreach ($index->getFieldNames(true) as $fieldName) {
                $term = new \Maho\Search\Lucene\Index\Term($this->_term->text, $fieldName);

                $query->addTerm($term);
            }

            return $query->rewrite($index);
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
        // Check, that index contains specified term
        if (!$index->hasTerm($this->_term)) {
            return new \Maho\Search\Lucene\Search\Query\EmptyQuery();
        }

        return $this;
    }

    /**
     * Constructs an appropriate Weight implementation for this query.
     *
     * @param \Maho\Search\Lucene\LuceneInterface $reader
     * @return \Maho\Search\Lucene\Search\Weight
     */
    public function createWeight(\Maho\Search\Lucene\LuceneInterface $reader)
    {
        $this->_weight = new \Maho\Search\Lucene\Search\Weight\Term($this->_term, $this, $reader);
        return $this->_weight;
    }

    /**
     * Execute query in context of index reader
     * It also initializes necessary internal structures
     *
     * @param \Maho\Search\Lucene\LuceneInterface $reader
     * @param \Maho\Search\Lucene\Index\DocsFilter|null $docsFilter
     */
    public function execute(\Maho\Search\Lucene\LuceneInterface $reader, $docsFilter = null)
    {
        $this->_docVector = array_flip($reader->termDocs($this->_term, $docsFilter));
        $this->_termFreqs = $reader->termFreqs($this->_term, $docsFilter);

        // Initialize weight if it's not done yet
        $this->_initWeight($reader);
    }

    /**
     * Get document ids likely matching the query
     *
     * It's an array with document ids as keys (performance considerations)
     *
     * @return array
     */
    public function matchedDocs()
    {
        return $this->_docVector;
    }

    /**
     * Score specified document
     *
     * @param integer $docId
     * @param \Maho\Search\Lucene\LuceneInterface $reader
     * @return float
     */
    public function score($docId, \Maho\Search\Lucene\LuceneInterface $reader)
    {
        if (isset($this->_docVector[$docId])) {
            return $reader->getSimilarity()->tf($this->_termFreqs[$docId]) *
                   $this->_weight->getValue() *
                   $reader->norm($docId, $this->_term->field) *
                   $this->getBoost();
        } else {
            return 0;
        }
    }

    /**
     * Return query terms
     *
     * @return array
     */
    public function getQueryTerms()
    {
        return array($this->_term);
    }

    /**
     * Return query term
     *
     * @return \Maho\Search\Lucene\Index\Term
     */
    public function getTerm()
    {
        return $this->_term;
    }

    /**
     * Query specific matches highlighting
     *
     * @param \Maho\Search\Lucene\Search\Highlighter\HighlighterInterface $highlighter  Highlighter object (also contains doc for highlighting)
     */
    protected function _highlightMatches(\Maho\Search\Lucene\Search\Highlighter\HighlighterInterface $highlighter)
    {
        $highlighter->highlight($this->_term->text);
    }

    /**
     * Print a query
     *
     * @return string
     */
    public function __toString()
    {
        // It's used only for query visualisation, so we don't care about characters escaping
        if ($this->_term->field !== null) {
            $query = $this->_term->field . ':';
        } else {
            $query = '';
        }

        $query .= $this->_term->text;

        if ($this->getBoost() != 1) {
            $query = $query . '^' . round($this->getBoost(), 4);
        }

        return $query;
    }
}

