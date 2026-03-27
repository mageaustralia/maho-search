<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Analysis\TokenFilter;

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
 * @subpackage Analysis
 */

/** \Maho\Search\Lucene\Analysis\TokenFilter */

/**
 * Token filter that removes stop words. These words must be provided as array (set), example:
 * $stopwords = array('the' => 1, 'an' => '1');
 *
 * We do recommend to provide all words in lowercase and concatenate this class after the lowercase filter.
 *
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Analysis
 */

class StopWords extends \Maho\Search\Lucene\Analysis\TokenFilter
{
    /**
     * Stop Words
     * @var array
     */
    private $_stopSet;

    /**
     * Constructs new instance of this filter.
     *
     * @param array $stopwords array (set) of words that will be filtered out
     */
    public function __construct($stopwords = array()) {
        $this->_stopSet = array_flip($stopwords);
    }

    /**
     * Normalize Token or remove it (if null is returned)
     *
     * @param \Maho\Search\Lucene\Analysis\Token $srcToken
     * @return \Maho\Search\Lucene\Analysis\Token
     */
    public function normalize(\Maho\Search\Lucene\Analysis\Token $srcToken) {
        if (array_key_exists($srcToken->getTermText(), $this->_stopSet)) {
            return null;
        } else {
            return $srcToken;
        }
    }

    /**
     * Fills stopwords set from a text file. Each line contains one stopword, lines with '#' in the first
     * column are ignored (as comments).
     *
     * You can call this method one or more times. New stopwords are always added to current set.
     *
     * @param string $filepath full path for text file with stopwords
     * @throws \Maho\Search\Lucene\Exception When the file doesn`t exists or is not readable.
     */
    public function loadFromFile($filepath = null) {
        if (! $filepath || ! file_exists($filepath)) {
            throw new \Maho\Search\Lucene\Exception('You have to provide valid file path');
        }
        $fd = fopen($filepath, "r");
        if (! $fd) {
            throw new \Maho\Search\Lucene\Exception('Cannot open file ' . $filepath);
        }
        while (!feof ($fd)) {
            $buffer = trim(fgets($fd));
            if (strlen($buffer) > 0 && $buffer[0] != '#') {
                $this->_stopSet[$buffer] = 1;
            }
        }
        if (!fclose($fd)) {
            throw new \Maho\Search\Lucene\Exception('Cannot close file ' . $filepath);
        }
    }
}

