<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Search\Highlighter;

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
interface HighlighterInterface
{
    /**
     * Set document for highlighting.
     *
     * @param \Maho\Search\Lucene\Document\Html $document
     */
    public function setDocument(\Maho\Search\Lucene\Document\Html $document);

    /**
     * Get document for highlighting.
     *
     * @return \Maho\Search\Lucene\Document\Html $document
     */
    public function getDocument();

    /**
     * Highlight specified words (method is invoked once per subquery)
     *
     * @param string|array $words  Words to highlight. They could be organized using the array or string.
     */
    public function highlight($words);
}
