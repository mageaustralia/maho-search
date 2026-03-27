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
 * It's an internal abstract class intended to finalize ase a query processing after query parsing.
 * This type of query is not actually involved into query execution.
 *
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Search
 * @internal
 */
abstract class Preprocessing extends \Maho\Search\Lucene\Search\Query
{
    /**
     * Matched terms.
     *
     * Matched terms list.
     * It's filled during rewrite operation and may be used for search result highlighting
     *
     * Array of \Maho\Search\Lucene\Index\Term objects
     *
     * @var array
     */
    protected $_matches = null;

    /**
     * Optimize query in the context of specified index
     *
     * @param \Maho\Search\Lucene\LuceneInterface $index
     * @return \Maho\Search\Lucene\Search\Query
     */
    public function optimize(\Maho\Search\Lucene\LuceneInterface $index)
    {
        throw new \Maho\Search\Lucene\Exception('This query is not intended to be executed.');
    }

    /**
     * Constructs an appropriate Weight implementation for this query.
     *
     * @param \Maho\Search\Lucene\LuceneInterface $reader
     * @return \Maho\Search\Lucene\Search\Weight
     */
    public function createWeight(\Maho\Search\Lucene\LuceneInterface $reader)
    {
        throw new \Maho\Search\Lucene\Exception('This query is not intended to be executed.');
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
        throw new \Maho\Search\Lucene\Exception('This query is not intended to be executed.');
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
        throw new \Maho\Search\Lucene\Exception('This query is not intended to be executed.');
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
        throw new \Maho\Search\Lucene\Exception('This query is not intended to be executed.');
    }

    /**
     * Return query terms
     *
     * @return array
     */
    public function getQueryTerms()
    {
        throw new \Maho\Search\Lucene\Exception('Rewrite operation has to be done before retrieving query terms.');
    }
}

