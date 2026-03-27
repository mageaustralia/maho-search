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
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (https://www.zend.com)
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/BSD-3-Clause  BSD 3-Clause License
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Analysis
 */

/** \Maho\Search\Lucene\Analysis\Token */

/**
 * Token filter converts (normalizes) Token ore removes it from a token stream.
 *
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Analysis
 */
abstract class TokenFilter
{
    /**
     * Normalize Token or remove it (if null is returned)
     *
     * @param \Maho\Search\Lucene\Analysis\Token $srcToken
     * @return \Maho\Search\Lucene\Analysis\Token
     */
    abstract public function normalize(\Maho\Search\Lucene\Analysis\Token $srcToken);
}

