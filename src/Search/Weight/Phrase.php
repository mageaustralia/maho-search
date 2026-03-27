<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Search\Weight;

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

/**
 * \Maho\Search\Lucene\Search\Weight
 */
// require_once 'Zend/Search/Lucene/Search/Weight.php';

/**
 * @category   Zend
 * @package    \Maho\Search\Lucene\Lucene
 * @subpackage Search
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Phrase extends \Maho\Search\Lucene\Search\Weight
{
    /**
     * @var float
     */
    private $_queryWeight;

    /**
     * IndexReader.
     *
     * @var \Maho\Search\Lucene\LuceneInterface
     */
    private $_reader;

    /**
     * The query that this concerns.
     *
     * @var \Maho\Search\Lucene\Search\Query\Phrase
     */
    private $_query;

    /**
     * Score factor
     *
     * @var float
     */
    private $_idf;

    /**
     * \Maho\Search\Lucene\Search\Weight\Phrase constructor
     *
     * @param \Maho\Search\Lucene\Search\Query\Phrase $query
     * @param \Maho\Search\Lucene\LuceneInterface           $reader
     */
    public function __construct(\Maho\Search\Lucene\Search\Query\Phrase $query,
                                \Maho\Search\Lucene\LuceneInterface           $reader)
    {
        $this->_query  = $query;
        $this->_reader = $reader;
    }

    /**
     * The sum of squared weights of contained query clauses.
     *
     * @return float
     */
    public function sumOfSquaredWeights()
    {
        // compute idf
        $this->_idf = $this->_reader->getSimilarity()->idf($this->_query->getTerms(), $this->_reader);

        // compute query weight
        $this->_queryWeight = $this->_idf * $this->_query->getBoost();

        // square it
        return $this->_queryWeight * $this->_queryWeight;
    }

    /**
     * Assigns the query normalization factor to this.
     *
     * @param float $queryNorm
     */
    public function normalize($queryNorm)
    {
        $this->_queryNorm = $queryNorm;

        // normalize query weight
        $this->_queryWeight *= $queryNorm;

        // idf for documents
        $this->_value = $this->_queryWeight * $this->_idf;
    }
}

