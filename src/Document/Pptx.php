<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Document;

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
 * @subpackage Document
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/** \Maho\Search\Lucene\Xml\Security */
// require_once 'Zend/Xml/Security.php';

/** \Maho\Search\Lucene\Document\OpenXml */
// require_once 'Zend/Search/Lucene/Document/OpenXml.php';

/**
 * Pptx document.
 *
 * @category   Zend
 * @package    \Maho\Search\Lucene\Lucene
 * @subpackage Document
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Pptx extends \Maho\Search\Lucene\Document\OpenXml
{
    /**
     * Xml Schema - PresentationML
     *
     * @var string
     */
    const SCHEMA_PRESENTATIONML = 'http://schemas.openxmlformats.org/presentationml/2006/main';

    /**
     * Xml Schema - DrawingML
     *
     * @var string
     */
    const SCHEMA_DRAWINGML = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    /**
     * Xml Schema - Slide relation
     *
     * @var string
     */
    const SCHEMA_SLIDERELATION = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide';

    /**
     * Xml Schema - Slide notes relation
     *
     * @var string
     */
    const SCHEMA_SLIDENOTESRELATION = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesSlide';

    /**
     * Object constructor
     *
     * @param string  $fileName
     * @param boolean $storeContent
     * @throws \Maho\Search\Lucene\Exception
     */
    private function __construct($fileName, $storeContent)
    {
        if (!class_exists('ZipArchive', false)) {
            // require_once 'Zend/Search/Lucene/Exception.php';
            throw new \Maho\Search\Lucene\Exception('MS Office documents processing functionality requires Zip extension to be loaded');
        }

        // Document data holders
        $slides = array();
        $slideNotes = array();
        $documentBody = array();
        $coreProperties = array();

        // Open OpenXML package
        $package = new ZipArchive();
        $package->open($fileName);

        // Read relations and search for officeDocument
        $relationsXml = $package->getFromName('_rels/.rels');
        if ($relationsXml === false) {
            // require_once 'Zend/Search/Lucene/Exception.php';
            throw new \Maho\Search\Lucene\Exception('Invalid archive or corrupted .pptx file.');
        }
        $relations = \Maho\Search\Lucene\Xml\Security::scan($relationsXml);
        foreach ($relations->Relationship as $rel) {
            if ($rel["Type"] == \Maho\Search\Lucene\Document\OpenXml::SCHEMA_OFFICEDOCUMENT) {
                // Found office document! Search for slides...
                $slideRelations = \Maho\Search\Lucene\Xml\Security::scan($package->getFromName( $this->absoluteZipPath(dirname($rel["Target"]) . "/_rels/" . basename($rel["Target"]) . ".rels")) );
                foreach ($slideRelations->Relationship as $slideRel) {
                    if ($slideRel["Type"] == \Maho\Search\Lucene\Document\Pptx::SCHEMA_SLIDERELATION) {
                        // Found slide!
                        $slides[ str_replace( 'rId', '', (string)$slideRel["Id"] ) ] = \Maho\Search\Lucene\Xml\Security::scan(
                            $package->getFromName( $this->absoluteZipPath(dirname($rel["Target"]) . "/" . dirname($slideRel["Target"]) . "/" . basename($slideRel["Target"])) )
                        );

                        // Search for slide notes
                        $slideNotesRelations = \Maho\Search\Lucene\Xml\Security::scan($package->getFromName( $this->absoluteZipPath(dirname($rel["Target"]) . "/" . dirname($slideRel["Target"]) . "/_rels/" . basename($slideRel["Target"]) . ".rels")) );
                        foreach ($slideNotesRelations->Relationship as $slideNoteRel) {
                            if ($slideNoteRel["Type"] == \Maho\Search\Lucene\Document\Pptx::SCHEMA_SLIDENOTESRELATION) {
                                // Found slide notes!
                                $slideNotes[ str_replace( 'rId', '', (string)$slideRel["Id"] ) ] = \Maho\Search\Lucene\Xml\Security::scan(
                                    $package->getFromName( $this->absoluteZipPath(dirname($rel["Target"]) . "/" . dirname($slideRel["Target"]) . "/" . dirname($slideNoteRel["Target"]) . "/" . basename($slideNoteRel["Target"])) )
                                );

                                break;
                            }
                        }
                    }
                }

                break;
            }
        }

        // Sort slides
        ksort($slides);
        ksort($slideNotes);

        // Extract contents from slides
        foreach ($slides as $slideKey => $slide) {
            // Register namespaces
            $slide->registerXPathNamespace("p", \Maho\Search\Lucene\Document\Pptx::SCHEMA_PRESENTATIONML);
            $slide->registerXPathNamespace("a", \Maho\Search\Lucene\Document\Pptx::SCHEMA_DRAWINGML);

            // Fetch all text
            $textElements = $slide->xpath('//a:t');
            foreach ($textElements as $textElement) {
                $documentBody[] = (string)$textElement;
            }

            // Extract contents from slide notes
            if (isset($slideNotes[$slideKey])) {
                // Fetch slide note
                $slideNote = $slideNotes[$slideKey];

                // Register namespaces
                $slideNote->registerXPathNamespace("p", \Maho\Search\Lucene\Document\Pptx::SCHEMA_PRESENTATIONML);
                $slideNote->registerXPathNamespace("a", \Maho\Search\Lucene\Document\Pptx::SCHEMA_DRAWINGML);

                // Fetch all text
                $textElements = $slideNote->xpath('//a:t');
                foreach ($textElements as $textElement) {
                    $documentBody[] = (string)$textElement;
                }
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
            $this->addField(\Maho\Search\Lucene\Field::Text('body', implode(' ', $documentBody), 'UTF-8'));
        } else {
            $this->addField(\Maho\Search\Lucene\Field::UnStored('body', implode(' ', $documentBody), 'UTF-8'));
        }

        // Store meta data properties
        foreach ($coreProperties as $key => $value)
        {
            $this->addField(\Maho\Search\Lucene\Field::Text($key, $value, 'UTF-8'));
        }

        // Store title (if not present in meta data)
        if (!isset($coreProperties['title']))
        {
            $this->addField(\Maho\Search\Lucene\Field::Text('title', $fileName, 'UTF-8'));
        }
    }

    /**
     * Load Pptx document from a file
     *
     * @param string  $fileName
     * @param boolean $storeContent
     * @return \Maho\Search\Lucene\Document\Pptx
     */
    public static function loadPptxFile($fileName, $storeContent = false)
    {
        return new \Maho\Search\Lucene\Document\Pptx($fileName, $storeContent);
    }
}
