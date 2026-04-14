<?php

declare(strict_types=1);

/**
 * Maho Lucene Search
 *
 * @category   MageAustralia
 * @package    MageAustralia_LuceneSearch
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/AGPL-3.0
 */

class MageAustralia_LuceneSearch_Model_System_Config_Source_Similarity
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'tfidf', 'label' => Mage::helper('lucenesearch')->__('TF-IDF (classic Lucene)')],
            ['value' => 'bm25',  'label' => Mage::helper('lucenesearch')->__('BM25 (recommended)')],
        ];
    }
}
