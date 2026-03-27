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
class Subquery extends \Maho\Search\Lucene\Search\QueryEntry
{
    /**
     * Query
     *
     * @var \Maho\Search\Lucene\Search\Query
     */
    private $_query;

    /**
     * Object constractor
     *
     * @param \Maho\Search\Lucene\Search\Query $query
     */
    public function __construct(\Maho\Search\Lucene\Search\Query $query)
    {
        $this->_query = $query;
    }

    /**
     * Process modifier ('~')
     *
     * @param mixed $parameter
     * @throws \Maho\Search\Lucene\Search\QueryParserException
     */
    public function processFuzzyProximityModifier($parameter = null)
    {
        // require_once 'Zend/Search/Lucene/Search/QueryParserException.php';
        throw new \Maho\Search\Lucene\Search\QueryParserException('\'~\' sign must follow term or phrase');
    }

    /**
     * Transform entry to a subquery
     *
     * @param string $encoding
     * @return \Maho\Search\Lucene\Search\Query
     */
    public function getQuery($encoding)
    {
        $this->_query->setBoost($this->_boost);

        return $this->_query;
    }
}
