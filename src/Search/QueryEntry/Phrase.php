<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Search\QueryEntry;

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
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Search
 */

/** \Maho\Search\Lucene\Search\QueryEntry */

/**
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Search
 */
class Phrase extends \Maho\Search\Lucene\Search\QueryEntry
{
    /**
     * Phrase value
     *
     * @var string
     */
    private $_phrase;

    /**
     * Field
     *
     * @var string|null
     */
    private $_field;

    /**
     * Proximity phrase query
     *
     * @var boolean
     */
    private $_proximityQuery = false;

    /**
     * Words distance, used for proximiti queries
     *
     * @var integer
     */
    private $_wordsDistance = 0;

    /**
     * Object constractor
     *
     * @param string $phrase
     * @param string $field
     */
    public function __construct($phrase, $field)
    {
        $this->_phrase = $phrase;
        $this->_field  = $field;
    }

    /**
     * Process modifier ('~')
     *
     * @param mixed $parameter
     */
    public function processFuzzyProximityModifier($parameter = null)
    {
        $this->_proximityQuery = true;

        if ($parameter !== null) {
            $this->_wordsDistance = $parameter;
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
        /** \Maho\Search\Lucene\Search\Query\Preprocessing\Phrase */
        $query = new \Maho\Search\Lucene\Search\Query\Preprocessing\Phrase($this->_phrase,
                                                                          $encoding,
                                                                          ($this->_field !== null)?
                                                                              iconv($encoding, 'UTF-8', $this->_field) :
                                                                              null);

        if ($this->_proximityQuery) {
            $query->setSlop($this->_wordsDistance);
        }

        $query->setBoost($this->_boost);

        return $query;
    }
}
