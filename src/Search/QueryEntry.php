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
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Search
 */

/**
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Search
 */
abstract class QueryEntry
{
    /**
     * Query entry boost factor
     *
     * @var float
     */
    protected $_boost = 1.0;

    /**
     * Process modifier ('~')
     *
     * @param mixed $parameter
     */
    abstract public function processFuzzyProximityModifier($parameter = null);

    /**
     * Transform entry to a subquery
     *
     * @param string $encoding
     * @return \Maho\Search\Lucene\Search\Query
     */
    abstract public function getQuery($encoding);

    /**
     * Boost query entry
     *
     * @param float $boostFactor
     */
    public function boost($boostFactor)
    {
        $this->_boost *= $boostFactor;
    }

}
