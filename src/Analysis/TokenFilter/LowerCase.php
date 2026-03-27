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
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Analysis
 */

/** \Maho\Search\Lucene\Analysis\TokenFilter */

/**
 * Lower case Token filter.
 *
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Analysis
 */

class LowerCase extends \Maho\Search\Lucene\Analysis\TokenFilter
{
    /**
     * Normalize Token or remove it (if null is returned)
     *
     * @param \Maho\Search\Lucene\Analysis\Token $srcToken
     * @return \Maho\Search\Lucene\Analysis\Token
     */
    public function normalize(\Maho\Search\Lucene\Analysis\Token $srcToken)
    {
        $srcToken->setTermText(strtolower($srcToken->getTermText()));
        return $srcToken;
    }
}

