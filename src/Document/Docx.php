<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Document;

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
 * @subpackage Document
 */

/** \Maho\Search\Lucene\Document\OpenXml */

/** \Maho\Search\Lucene\Xml\Security */

/**
 * Docx document.
 *
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Document
 */
class Docx extends \Maho\Search\Lucene\Document\OpenXml {
    /**
     * Xml Schema - WordprocessingML
     *
     * @var string
     */
    const SCHEMA_WORDPROCESSINGML = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Object constructor
     *
     * @param string  $fileName
     * @param boolean $storeContent
     * @throws \Maho\Search\Lucene\Exception
     */
    private function __construct($fileName, $storeContent) {
        if (!class_exists('ZipArchive', false)) {
            throw new \Maho\Search\Lucene\Exception('MS Office documents processing functionality requires Zip extension to be loaded');
        }

        // Document data holders
        $documentBody = array();
        $coreProperties = array();

        // Open OpenXML package
        $package = new ZipArchive();
        $package->open($fileName);

        // Read relations and search for officeDocument
        $relationsXml = $package->getFromName('_rels/.rels');
        if ($relationsXml === false) {
            throw new \Maho\Search\Lucene\Exception('Invalid archive or corrupted .docx file.');
        }
        $relations = \Maho\Search\Lucene\Xml\Security::scan($relationsXml);
        foreach($relations->Relationship as $rel) {
            if ($rel ["Type"] == \Maho\Search\Lucene\Document\OpenXml::SCHEMA_OFFICEDOCUMENT) {
                // Found office document! Read in contents...
                $contents = \Maho\Search\Lucene\Xml\Security::scan($package->getFromName(
                                                                $this->absoluteZipPath(dirname($rel['Target'])
                                                              . '/'
                                                              . basename($rel['Target']))
                                                                       ));

                $contents->registerXPathNamespace('w', \Maho\Search\Lucene\Document\Docx::SCHEMA_WORDPROCESSINGML);
                $paragraphs = $contents->xpath('//w:body/w:p');

                foreach ($paragraphs as $paragraph) {
                    $runs = $paragraph->xpath('.//w:r/*[name() = "w:t" or name() = "w:br"]');

                    if ($runs === false) {
                        // Paragraph doesn't contain any text or breaks
                        continue;
                    }

                    foreach ($runs as $run) {
                     if ($run->getName() == 'br') {
                         // Break element
                         $documentBody[] = ' ';
                     } else {
                         $documentBody[] = (string)$run;
                     }
                    }

                    // Add space after each paragraph. So they are not bound together.
                    $documentBody[] = ' ';
                }

                break;
            }
        }

        // Read core properties
        $coreProperties = $this->extractMetaData($package);

        // Close file
        $package->close();

        // Store filename
        $this->addField(\Maho\Search\Lucene\Field::Text('filename', $fileName, 'UTF-8'));

        // Store contents
        if ($storeContent) {
            $this->addField(\Maho\Search\Lucene\Field::Text('body', implode('', $documentBody), 'UTF-8'));
        } else {
            $this->addField(\Maho\Search\Lucene\Field::UnStored('body', implode('', $documentBody), 'UTF-8'));
        }

        // Store meta data properties
        foreach ($coreProperties as $key => $value) {
            $this->addField(\Maho\Search\Lucene\Field::Text($key, $value, 'UTF-8'));
        }

        // Store title (if not present in meta data)
        if (! isset($coreProperties['title'])) {
            $this->addField(\Maho\Search\Lucene\Field::Text('title', $fileName, 'UTF-8'));
        }
    }

    /**
     * Load Docx document from a file
     *
     * @param string  $fileName
     * @param boolean $storeContent
     * @return \Maho\Search\Lucene\Document\Docx
     * @throws \Maho\Search\Lucene\Document\Exception
     */
    public static function loadDocxFile($fileName, $storeContent = false) {
        if (!is_readable($fileName)) {
            throw new \Maho\Search\Lucene\Document\Exception('Provided file \'' . $fileName . '\' is not readable.');
        }

        return new \Maho\Search\Lucene\Document\Docx($fileName, $storeContent);
    }
}
