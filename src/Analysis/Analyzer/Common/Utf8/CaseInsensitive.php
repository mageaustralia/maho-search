<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Analysis\Analyzer\Common\Utf8;

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

/** \Maho\Search\Lucene\Analysis\Analyzer\Common\Utf8 */

/** \Maho\Search\Lucene\Analysis\TokenFilter\LowerCaseUtf8 */

/**
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Analysis
 */

class CaseInsensitive extends \Maho\Search\Lucene\Analysis\Analyzer\Common\Utf8
{
    public function __construct()
    {
        parent::__construct();

        $this->addFilter(new \Maho\Search\Lucene\Analysis\TokenFilter\LowerCaseUtf8());
    }
}

