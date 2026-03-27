<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Search\Weight;

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

/** \Maho\Search\Lucene\Search\Weight */

/**
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Search
 */
class BooleanWeight extends \Maho\Search\Lucene\Search\Weight
{
    /**
     * IndexReader.
     *
     * @var \Maho\Search\Lucene\LuceneInterface
     */
    private $_reader;

    /**
     * The query that this concerns.
     *
     * @var \Maho\Search\Lucene\Search\Query
     */
    private $_query;

    /**
     * Queries weights
     * Array of \Maho\Search\Lucene\Search\Weight
     *
     * @var array
     */
    private $_weights;

    /**
     * \Maho\Search\Lucene\Search\Weight\BooleanWeight constructor
     * query - the query that this concerns.
     * reader - index reader
     *
     * @param \Maho\Search\Lucene\Search\Query $query
     * @param \Maho\Search\Lucene\LuceneInterface    $reader
     */
    public function __construct(\Maho\Search\Lucene\Search\Query $query,
                                \Maho\Search\Lucene\LuceneInterface    $reader)
    {
        $this->_query   = $query;
        $this->_reader  = $reader;
        $this->_weights = array();

        $signs = $query->getSigns();

        foreach ($query->getSubqueries() as $num => $subquery) {
            if ($signs === null || $signs[$num] === null || $signs[$num]) {
                $this->_weights[$num] = $subquery->createWeight($reader);
            }
        }
    }

    /**
     * The weight for this query
     * Standard Weight::$_value is not used for boolean queries
     *
     * @return float
     */
    public function getValue()
    {
        return $this->_query->getBoost();
    }

    /**
     * The sum of squared weights of contained query clauses.
     *
     * @return float
     */
    public function sumOfSquaredWeights()
    {
        $sum = 0;
        foreach ($this->_weights as $weight) {
            // sum sub weights
            $sum += $weight->sumOfSquaredWeights();
        }

        // boost each sub-weight
        $sum *= $this->_query->getBoost() * $this->_query->getBoost();

        // check for empty query (like '-something -another')
        if ($sum == 0) {
            $sum = 1.0;
        }
        return $sum;
    }

    /**
     * Assigns the query normalization factor to this.
     *
     * @param float $queryNorm
     */
    public function normalize($queryNorm)
    {
        // incorporate boost
        $queryNorm *= $this->_query->getBoost();

        foreach ($this->_weights as $weight) {
            $weight->normalize($queryNorm);
        }
    }
}

