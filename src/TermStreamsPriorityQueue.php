<?php

declare(strict_types=1);

namespace Maho\Search\Lucene;

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

/** \Maho\Search\Lucene\Index\TermsStream\TermsStreamInterface */

/**
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Index
 */
class TermStreamsPriorityQueue implements \Maho\Search\Lucene\Index\TermsStream\TermsStreamInterface
{
    /**
     * Array of term streams (\Maho\Search\Lucene\Index\TermsStream\TermsStreamInterface objects)
     *
     * @var array
     */
    protected $_termStreams;

    /**
     * Terms stream queue
     *
     * @var \Maho\Search\Lucene\Index\TermsPriorityQueue
     */
    protected $_termsStreamQueue = null;

    /**
     * Last Term in a terms stream
     *
     * @var \Maho\Search\Lucene\Index\Term
     */
    protected $_lastTerm = null;

    /**
     * Object constructor
     *
     * @param array $termStreams  array of term streams (\Maho\Search\Lucene\Index\TermsStream\TermsStreamInterface objects)
     */
    public function __construct(array $termStreams)
    {
        $this->_termStreams = $termStreams;

        $this->resetTermsStream();
    }

    /**
     * Reset terms stream.
     */
    public function resetTermsStream()
    {
        /** \Maho\Search\Lucene\Index\TermsPriorityQueue */

        $this->_termsStreamQueue = new \Maho\Search\Lucene\Index\TermsPriorityQueue();

        foreach ($this->_termStreams as $termStream) {
            $termStream->resetTermsStream();

            // Skip "empty" containers
            if ($termStream->currentTerm() !== null) {
                $this->_termsStreamQueue->put($termStream);
            }
        }

        $this->nextTerm();
    }

    /**
     * Skip terms stream up to the specified term preffix.
     *
     * Prefix contains fully specified field info and portion of searched term
     *
     * @param \Maho\Search\Lucene\Index\Term $prefix
     */
    public function skipTo(\Maho\Search\Lucene\Index\Term $prefix)
    {
        $this->_termsStreamQueue = new \Maho\Search\Lucene\Index\TermsPriorityQueue();

        foreach ($this->_termStreams as $termStream) {
            $termStream->skipTo($prefix);

            if ($termStream->currentTerm() !== null) {
                $this->_termsStreamQueue->put($termStream);
            }
        }

        return $this->nextTerm();
    }

    /**
     * Scans term streams and returns next term
     *
     * @return \Maho\Search\Lucene\Index\Term|null
     */
    public function nextTerm()
    {
        while (($termStream = $this->_termsStreamQueue->pop()) !== null) {
            if ($this->_termsStreamQueue->top() === null ||
                $this->_termsStreamQueue->top()->currentTerm()->key() !=
                            $termStream->currentTerm()->key()) {
                // We got new term
                $this->_lastTerm = $termStream->currentTerm();

                if ($termStream->nextTerm() !== null) {
                    // Put segment back into the priority queue
                    $this->_termsStreamQueue->put($termStream);
                }

                return $this->_lastTerm;
            }

            if ($termStream->nextTerm() !== null) {
                // Put segment back into the priority queue
                $this->_termsStreamQueue->put($termStream);
            }
        }

        // End of stream
        $this->_lastTerm = null;

        return null;
    }

    /**
     * Returns term in current position
     *
     * @return \Maho\Search\Lucene\Index\Term|null
     */
    public function currentTerm()
    {
        return $this->_lastTerm;
    }

    /**
     * Close terms stream
     *
     * Should be used for resources clean up if stream is not read up to the end
     */
    public function closeTermsStream()
    {
        while (($termStream = $this->_termsStreamQueue->pop()) !== null) {
            $termStream->closeTermsStream();
        }

        $this->_termsStreamQueue = null;
        $this->_lastTerm         = null;
    }
}
