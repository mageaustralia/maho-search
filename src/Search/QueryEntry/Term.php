<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Search\QueryEntry;

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

/** \Maho\Search\Lucene\Search\QueryEntry */
// require_once 'Zend/Search/Lucene/Search/QueryEntry.php';

/**
 * @category   Zend
 * @package    \Maho\Search\Lucene\Lucene
 * @subpackage Search
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Term extends \Maho\Search\Lucene\Search\QueryEntry
{
    /**
     * Term value
     *
     * @var string
     */
    private $_term;

    /**
     * Field
     *
     * @var string|null
     */
    private $_field;

    /**
     * Fuzzy search query
     *
     * @var boolean
     */
    private $_fuzzyQuery = false;

    /**
     * Similarity
     *
     * @var float
     */
    private $_similarity = 1.;

    /**
     * Object constractor
     *
     * @param string $term
     * @param string $field
     */
    public function __construct($term, $field)
    {
        $this->_term  = $term;
        $this->_field = $field;
    }

    /**
     * Process modifier ('~')
     *
     * @param mixed $parameter
     */
    public function processFuzzyProximityModifier($parameter = null)
    {
        $this->_fuzzyQuery = true;

        if ($parameter !== null) {
            $this->_similarity = $parameter;
        } else {
            /** \Maho\Search\Lucene\Search\Query\Fuzzy */
            // require_once 'Zend/Search/Lucene/Search/Query/Fuzzy.php';
            $this->_similarity = \Maho\Search\Lucene\Search\Query\Fuzzy::DEFAULT_MIN_SIMILARITY;
        }
    }

    /**
     * Transform entry to a subquery
     *
     * @param string $encoding
     * @return \Maho\Search\Lucene\Search\Query
     * @throws \Maho\Search\Lucene\Search\QueryParserException
     */
    public function getQuery($encoding)
    {
        if ($this->_fuzzyQuery) {
            /** \Maho\Search\Lucene\Search\Query\Preprocessing\Fuzzy */
            // require_once 'Zend/Search/Lucene/Search/Query/Preprocessing/Fuzzy.php';
            $query = new \Maho\Search\Lucene\Search\Query\Preprocessing\Fuzzy($this->_term,
                                                                             $encoding,
                                                                             ($this->_field !== null)?
                                                                                  iconv($encoding, 'UTF-8', $this->_field) :
                                                                                  null,
                                                                             $this->_similarity
                                                                             );
            $query->setBoost($this->_boost);
            return $query;
        }

        /** \Maho\Search\Lucene\Search\Query\Preprocessing\Term */
        // require_once 'Zend/Search/Lucene/Search/Query/Preprocessing/Term.php';
        $query = new \Maho\Search\Lucene\Search\Query\Preprocessing\Term($this->_term,
                                                                        $encoding,
                                                                        ($this->_field !== null)?
                                                                              iconv($encoding, 'UTF-8', $this->_field) :
                                                                              null
                                                                        );
        $query->setBoost($this->_boost);
        return $query;
    }
}
