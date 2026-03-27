<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Analysis\Analyzer;

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
 * @subpackage Analysis
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/** Define constant used to provide correct file processing order    */
/** @todo Section should be removed with ZF 2.0 release as obsolete  */
if (!defined('ZEND_SEARCH_LUCENE_COMMON_ANALYZER_PROCESSED')) {
    define('ZEND_SEARCH_LUCENE_COMMON_ANALYZER_PROCESSED', true);
}

/** \Maho\Search\Lucene\Analysis\Analyzer */
// require_once 'Zend/Search/Lucene/Analysis/Analyzer.php';

/** \Maho\Search\Lucene\Analysis\Token */
// require_once 'Zend/Search/Lucene/Analysis/Token.php';

/** \Maho\Search\Lucene\Analysis\TokenFilter */
// require_once 'Zend/Search/Lucene/Analysis/TokenFilter.php';

/**
 * Common implementation of the \Maho\Search\Lucene\Analysis\Analyzer interface.
 * There are several standard standard subclasses provided by \Maho\Search\Lucene\Lucene/Analysis
 * subpackage: \Maho\Search\Lucene\Analysis\Analyzer\Common\Text, ZSearchHTMLAnalyzer, ZSearchXMLAnalyzer.
 *
 * @todo ZSearchHTMLAnalyzer and ZSearchXMLAnalyzer implementation
 *
 * @category   Zend
 * @package    \Maho\Search\Lucene\Lucene
 * @subpackage Analysis
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Common extends \Maho\Search\Lucene\Analysis\Analyzer
{
    /**
     * The set of Token filters applied to the Token stream.
     * Array of \Maho\Search\Lucene\Analysis\TokenFilter objects.
     *
     * @var array
     */
    private $_filters = array();

    /**
     * Add Token filter to the Analyzer
     *
     * @param \Maho\Search\Lucene\Analysis\TokenFilter $filter
     */
    public function addFilter(\Maho\Search\Lucene\Analysis\TokenFilter $filter)
    {
        $this->_filters[] = $filter;
    }

    /**
     * Apply filters to the token. Can return null when the token was removed.
     *
     * @param \Maho\Search\Lucene\Analysis\Token $token
     * @return \Maho\Search\Lucene\Analysis\Token
     */
    public function normalize(\Maho\Search\Lucene\Analysis\Token $token)
    {
        foreach ($this->_filters as $filter) {
            $token = $filter->normalize($token);

            // resulting token can be null if the filter removes it
            if ($token === null) {
                return null;
            }
        }

        return $token;
    }
}

