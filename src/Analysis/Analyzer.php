<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Analysis;

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
 * @subpackage Analysis
 */

/** User land classes and interfaces turned on by Zend/Search/Analyzer.php file inclusion. */
/** @todo Section should be removed with ZF 2.0 release as obsolete                      */
if (!defined('ZEND_SEARCH_LUCENE_COMMON_ANALYZER_PROCESSED')) {
    /** \Maho\Search\Lucene\Analysis\Analyzer\Common\Utf8 */

    /** \Maho\Search\Lucene\Analysis\Analyzer\Common\Utf8\CaseInsensitive */

    /** \Maho\Search\Lucene\Analysis\Analyzer\Common\Utf8Num */

    /** \Maho\Search\Lucene\Analysis\Analyzer\Common\Utf8Num\CaseInsensitive */

    /** \Maho\Search\Lucene\Analysis\Analyzer\Common\Text */

    /** \Maho\Search\Lucene\Analysis\Analyzer\Common\Text\CaseInsensitive */

    /** \Maho\Search\Lucene\Analysis\Analyzer\Common\TextNum */

    /** \Maho\Search\Lucene\Analysis\Analyzer\Common\TextNum\CaseInsensitive */
}

/**
 * An Analyzer is used to analyze text.
 * It thus represents a policy for extracting index terms from text.
 *
 * Note:
 * Lucene Java implementation is oriented to streams. It provides effective work
 * with a huge documents (more then 20Mb).
 * But engine itself is not oriented such documents.
 * Thus \Maho\Search\Lucene\Lucene analysis API works with data strings and sets (arrays).
 *
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Analysis
 */

abstract class Analyzer
{
    /**
     * The Analyzer implementation used by default.
     *
     * @var \Maho\Search\Lucene\Analysis\Analyzer
     */
    private static $_defaultImpl;

    /**
     * Input string
     *
     * @var string
     */
    protected $_input = null;

    /**
     * Input string encoding
     *
     * @var string
     */
    protected $_encoding = '';

    /**
     * Tokenize text to a terms
     * Returns array of \Maho\Search\Lucene\Analysis\Token objects
     *
     * Tokens are returned in UTF-8 (internal \Maho\Search\Lucene\Lucene encoding)
     *
     * @param string $data
     * @return array
     */
    public function tokenize($data, $encoding = '')
    {
        $this->setInput($data, $encoding);

        $tokenList = array();
        while (($nextToken = $this->nextToken()) !== null) {
            $tokenList[] = $nextToken;
        }

        return $tokenList;
    }

    /**
     * Tokenization stream API
     * Set input
     *
     * @param string $data
     */
    public function setInput($data, $encoding = '')
    {
        $this->_input    = $data;
        $this->_encoding = $encoding;
        $this->reset();
    }

    /**
     * Reset token stream
     */
    abstract public function reset();

    /**
     * Tokenization stream API
     * Get next token
     * Returns null at the end of stream
     *
     * Tokens are returned in UTF-8 (internal \Maho\Search\Lucene\Lucene encoding)
     *
     * @return \Maho\Search\Lucene\Analysis\Token|null
     */
    abstract public function nextToken();

    /**
     * Set the default Analyzer implementation used by indexing code.
     *
     * @param \Maho\Search\Lucene\Analysis\Analyzer $similarity
     */
    public static function setDefault(\Maho\Search\Lucene\Analysis\Analyzer $analyzer)
    {
        self::$_defaultImpl = $analyzer;
    }

    /**
     * Return the default Analyzer implementation used by indexing code.
     *
     * @return \Maho\Search\Lucene\Analysis\Analyzer
     */
    public static function getDefault()
    {
        /** \Maho\Search\Lucene\Analysis\Analyzer\Common\Text\CaseInsensitive */

        if (!self::$_defaultImpl instanceof \Maho\Search\Lucene\Analysis\Analyzer) {
            self::$_defaultImpl = new \Maho\Search\Lucene\Analysis\Analyzer\Common\Text\CaseInsensitive();
        }

        return self::$_defaultImpl;
    }
}

