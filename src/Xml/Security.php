<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Xml;

use DOMDocument;
use SimpleXMLElement;

/**
 * Minimal XML security helper (replaces Zend_Xml_Security).
 * Prevents XXE (XML External Entity) attacks.
 */
class Security
{
    public static function scan(string $xml): SimpleXMLElement|false
    {
        if (str_contains($xml, '<!DOCTYPE') || str_contains($xml, '<!ENTITY')) {
            return false;
        }

        $previousValue = libxml_disable_entity_loader(true);
        $previousErrors = libxml_use_internal_errors(true);

        try {
            $result = simplexml_load_string($xml);
            return $result !== false ? $result : false;
        } finally {
            libxml_disable_entity_loader($previousValue);
            libxml_use_internal_errors($previousErrors);
        }
    }
}
