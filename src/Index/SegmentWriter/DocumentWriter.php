<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Index\SegmentWriter;

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
 * @subpackage Index
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/** \Maho\Search\Lucene\Index\SegmentWriter */
// require_once 'Zend/Search/Lucene/Index/SegmentWriter.php';

/**
 * @category   Zend
 * @package    \Maho\Search\Lucene\Lucene
 * @subpackage Index
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class DocumentWriter extends \Maho\Search\Lucene\Index\SegmentWriter
{
    /**
     * Term Dictionary
     * Array of the \Maho\Search\Lucene\Index\Term objects
     * Corresponding \Maho\Search\Lucene\Index\TermInfo object stored in the $_termDictionaryInfos
     *
     * @var array
     */
    protected $_termDictionary;

    /**
     * Documents, which contain the term
     *
     * @var array
     */
    protected $_termDocs;

    /**
     * Object constructor.
     *
     * @param \Maho\Search\Lucene\Storage\Directory $directory
     * @param string $name
     */
    public function __construct(\Maho\Search\Lucene\Storage\Directory $directory, $name)
    {
        parent::__construct($directory, $name);

        $this->_termDocs       = array();
        $this->_termDictionary = array();
    }

    /**
     * Adds a document to this segment.
     *
     * @param \Maho\Search\Lucene\Document $document
     * @throws \Maho\Search\Lucene\Exception
     */
    public function addDocument(\Maho\Search\Lucene\Document $document)
    {
        /** \Maho\Search\Lucene\Search\Similarity */
        // require_once 'Zend/Search/Lucene/Search/Similarity.php';

        $storedFields = array();
        $docNorms     = array();
        $similarity   = \Maho\Search\Lucene\Search\Similarity::getDefault();

        foreach ($document->getFieldNames() as $fieldName) {
            $field = $document->getField($fieldName);

            if ($field->storeTermVector) {
                /**
                 * @todo term vector storing support
                 */
                // require_once 'Zend/Search/Lucene/Exception.php';
                throw new \Maho\Search\Lucene\Exception('Store term vector functionality is not supported yet.');
            }

            if ($field->isIndexed) {
                if ($field->isTokenized) {
                    /** \Maho\Search\Lucene\Analysis\Analyzer */
                    // require_once 'Zend/Search/Lucene/Analysis/Analyzer.php';

                    $analyzer = \Maho\Search\Lucene\Analysis\Analyzer::getDefault();
                    $analyzer->setInput($field->value, $field->encoding);

                    $position     = 0;
                    $tokenCounter = 0;
                    while (($token = $analyzer->nextToken()) !== null) {
                        $tokenCounter++;

                        $term = new \Maho\Search\Lucene\Index\Term($token->getTermText(), $field->name);
                        $termKey = $term->key();

                        if (!isset($this->_termDictionary[$termKey])) {
                            // New term
                            $this->_termDictionary[$termKey] = $term;
                            $this->_termDocs[$termKey] = array();
                            $this->_termDocs[$termKey][$this->_docCount] = array();
                        } else if (!isset($this->_termDocs[$termKey][$this->_docCount])) {
                            // Existing term, but new term entry
                            $this->_termDocs[$termKey][$this->_docCount] = array();
                        }
                        $position += $token->getPositionIncrement();
                        $this->_termDocs[$termKey][$this->_docCount][] = $position;
                    }

                    if ($tokenCounter == 0) {
                        // Field contains empty value. Treat it as non-indexed and non-tokenized
                        $field = clone($field);
                        $field->isIndexed = $field->isTokenized = false;
                    } else {
                        $docNorms[$field->name] = chr($similarity->encodeNorm( $similarity->lengthNorm($field->name,
                                                                                                       $tokenCounter)*
                                                                               $document->boost*
                                                                               $field->boost ));
                    }
                } else if (($fieldUtf8Value = $field->getUtf8Value()) == '') {
                    // Field contains empty value. Treat it as non-indexed and non-tokenized
                    $field = clone($field);
                    $field->isIndexed = $field->isTokenized = false;
                } else {
                    $term = new \Maho\Search\Lucene\Index\Term($fieldUtf8Value, $field->name);
                    $termKey = $term->key();

                    if (!isset($this->_termDictionary[$termKey])) {
                        // New term
                        $this->_termDictionary[$termKey] = $term;
                        $this->_termDocs[$termKey] = array();
                        $this->_termDocs[$termKey][$this->_docCount] = array();
                    } else if (!isset($this->_termDocs[$termKey][$this->_docCount])) {
                        // Existing term, but new term entry
                        $this->_termDocs[$termKey][$this->_docCount] = array();
                    }
                    $this->_termDocs[$termKey][$this->_docCount][] = 0; // position

                    $docNorms[$field->name] = chr($similarity->encodeNorm( $similarity->lengthNorm($field->name, 1)*
                                                                           $document->boost*
                                                                           $field->boost ));
                }
            }

            if ($field->isStored) {
                $storedFields[] = $field;
            }

            $this->addField($field);
        }

        foreach ($this->_fields as $fieldName => $field) {
            if (!$field->isIndexed) {
                continue;
            }

            if (!isset($this->_norms[$fieldName])) {
                $this->_norms[$fieldName] = str_repeat(chr($similarity->encodeNorm( $similarity->lengthNorm($fieldName, 0) )),
                                                       $this->_docCount);
            }

            if (isset($docNorms[$fieldName])){
                $this->_norms[$fieldName] .= $docNorms[$fieldName];
            } else {
                $this->_norms[$fieldName] .= chr($similarity->encodeNorm( $similarity->lengthNorm($fieldName, 0) ));
            }
        }

        $this->addStoredFields($storedFields);
    }

    /**
     * Dump Term Dictionary (.tis) and Term Dictionary Index (.tii) segment files
     */
    protected function _dumpDictionary()
    {
        ksort($this->_termDictionary, SORT_STRING);

        $this->initializeDictionaryFiles();

        foreach ($this->_termDictionary as $termId => $term) {
            $this->addTerm($term, $this->_termDocs[$termId]);
        }

        $this->closeDictionaryFiles();
    }

    /**
     * Close segment, write it to disk and return segment info
     *
     * @return \Maho\Search\Lucene\Index\SegmentInfo
     */
    public function close()
    {
        if ($this->_docCount == 0) {
            return null;
        }

        $this->_dumpFNM();
        $this->_dumpDictionary();

        $this->_generateCFS();

        /** \Maho\Search\Lucene\Index\SegmentInfo */
        // require_once 'Zend/Search/Lucene/Index/SegmentInfo.php';

        return new \Maho\Search\Lucene\Index\SegmentInfo($this->_directory,
                                                        $this->_name,
                                                        $this->_docCount,
                                                        -1,
                                                        null,
                                                        true,
                                                        true);
    }

}

