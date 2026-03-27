<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Search;

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

/**
 * @category   Zend
 * @package    \Maho\Search\Lucene\Lucene
 * @subpackage Search
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class QueryHit
{
    /**
     * Object handle of the index
     * @var \Maho\Search\Lucene\LuceneInterface
     */
    protected $_index = null;

    /**
     * Object handle of the document associated with this hit
     * @var \Maho\Search\Lucene\Document
     */
    protected $_document = null;

    /**
     * Number of the document in the index
     * @var integer
     */
    public $id;

    /**
     * Score of the hit
     * @var float
     */
    public $score;

    /**
     * Constructor - pass object handle of \Maho\Search\Lucene\LuceneInterface index that produced
     * the hit so the document can be retrieved easily from the hit.
     *
     * @param \Maho\Search\Lucene\LuceneInterface $index
     */

    public function __construct(\Maho\Search\Lucene\LuceneInterface $index)
    {
        // require_once 'Zend/Search/Lucene/Proxy.php';
        $this->_index = new \Maho\Search\Lucene\Proxy($index);
    }

    /**
     * Convenience function for getting fields from the document
     * associated with this hit.
     *
     * @param string $offset
     * @return string
     */
    public function __get($offset)
    {
        return $this->getDocument()->getFieldValue($offset);
    }

    /**
     * Return the document object for this hit
     *
     * @return \Maho\Search\Lucene\Document
     */
    public function getDocument()
    {
        if (!$this->_document instanceof \Maho\Search\Lucene\Document) {
            $this->_document = $this->_index->getDocument($this->id);
        }

        return $this->_document;
    }

    /**
     * Return the index object for this hit
     *
     * @return \Maho\Search\Lucene\LuceneInterface
     */
    public function getIndex()
    {
        return $this->_index;
    }
}

