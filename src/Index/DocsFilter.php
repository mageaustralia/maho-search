<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Index;

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
 * @subpackage Index
 */

/**
 * A \Maho\Search\Lucene\Index\DocsFilter is used to filter documents while searching.
 *
 * It may or _may_not_ be used for actual filtering, so it's just a hint that upper query limits
 * search result by specified list.
 *
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Index
 */
class DocsFilter
{
    /**
     * Set of segment filters:
     *  array( <segmentName> => array(<docId> => <undefined_value>,
     *                                <docId> => <undefined_value>,
     *                                <docId> => <undefined_value>,
     *                                ...                          ),
     *         <segmentName> => array(<docId> => <undefined_value>,
     *                                <docId> => <undefined_value>,
     *                                <docId> => <undefined_value>,
     *                                ...                          ),
     *         <segmentName> => array(<docId> => <undefined_value>,
     *                                <docId> => <undefined_value>,
     *                                <docId> => <undefined_value>,
     *                                ...                          ),
     *         ...
     *       )
     *
     * @var array
     */
    public $segmentFilters = array();
}

