<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Analysis\Analyzer\Common\Text;

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
 * @subpackage Analysis
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/** \Maho\Search\Lucene\Analysis\Analyzer\Common\Text */
// require_once 'Zend/Search/Lucene/Analysis/Analyzer/Common/Text.php';

/** \Maho\Search\Lucene\Analysis\TokenFilter\LowerCase */
// require_once 'Zend/Search/Lucene/Analysis/TokenFilter/LowerCase.php';

/**
 * @category   Zend
 * @package    \Maho\Search\Lucene\Lucene
 * @subpackage Analysis
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

class CaseInsensitive extends \Maho\Search\Lucene\Analysis\Analyzer\Common\Text
{
    public function __construct()
    {
        $this->addFilter(new \Maho\Search\Lucene\Analysis\TokenFilter\LowerCase());
    }
}

