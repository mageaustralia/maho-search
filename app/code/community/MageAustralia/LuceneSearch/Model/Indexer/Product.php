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

use Maho\Search\Lucene\Document;
use Maho\Search\Lucene\Field;

/**
 * Product document builder — converts Maho products into Lucene documents.
 */
class MageAustralia_LuceneSearch_Model_Indexer_Product
{
    private function _getHelper(): MageAustralia_LuceneSearch_Helper_Data
    {
        return Mage::helper('lucenesearch');
    }

    private function _getIndexer(): MageAustralia_LuceneSearch_Model_Indexer
    {
        return Mage::getModel('lucenesearch/indexer');
    }

    /**
     * Reindex all products for a store.
     */
    public function reindexAll(int $storeId, string $storeCode): int
    {
        $helper = $this->_getHelper();
        $index = $this->_getIndexer()->getIndex($storeCode);
        $attributes = $helper->getProductSearchableAttributes($storeId);

        $collection = Mage::getModel('catalog/product')->getCollection()
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => [
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            ]]);

        foreach ($attributes as $attr) {
            $collection->addAttributeToSelect($attr);
        }

        if ($helper->isProductCategoryPathsEnabled($storeId)) {
            $collection->addCategoryIds();
        }

        $count = 0;
        foreach ($collection as $product) {
            $doc = $this->buildDocument($product, $storeId, $attributes);
            if ($doc !== null) {
                $index->addDocument($doc);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Reindex a single product across all stores.
     */
    public function reindexProduct(Mage_Catalog_Model_Product $product): void
    {
        $helper = $this->_getHelper();
        $indexer = $this->_getIndexer();

        foreach (Mage::app()->getStores() as $store) {
            $storeId = (int) $store->getId();
            if (!$helper->isProductIndexEnabled($storeId)) {
                continue;
            }

            $storeCode = $store->getCode();

            // Remove old document
            $indexer->removeDocument($storeCode, 'product', (int) $product->getId());

            // Reload product in store context
            $storeProduct = Mage::getModel('catalog/product')
                ->setStoreId($storeId)
                ->load($product->getId());

            if ((int) $storeProduct->getStatus() !== Mage_Catalog_Model_Product_Status::STATUS_ENABLED) {
                continue;
            }

            $visibility = (int) $storeProduct->getVisibility();
            if (!in_array($visibility, [
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            ], true)) {
                continue;
            }

            $attributes = $helper->getProductSearchableAttributes($storeId);
            $doc = $this->buildDocument($storeProduct, $storeId, $attributes);
            if ($doc !== null) {
                $index = $indexer->getIndex($storeCode);
                $index->addDocument($doc);
                $index->commit();
            }
        }
    }

    /**
     * Build a Lucene document from a product.
     */
    public function buildDocument(
        Mage_Catalog_Model_Product $product,
        int $storeId,
        array $attributes
    ): ?Document {
        $helper = $this->_getHelper();
        $doc = new Document();

        // UID for document identification (type + entity_id)
        $doc->addField(Field::keyword('_uid', 'product_' . $product->getId()));
        $doc->addField(Field::keyword('_type', 'product'));
        $doc->addField(Field::keyword('_entity_id', (string) $product->getId()));

        // Store product data as unindexed fields for retrieval
        // Use _stored suffix to avoid collision with searchable fields of same name
        $doc->addField(Field::unIndexed('sku_stored', (string) $product->getSku()));
        $doc->addField(Field::unIndexed('name_stored', (string) $product->getName()));
        $doc->addField(Field::unIndexed('url_key', (string) $product->getUrlKey()));
        $doc->addField(Field::unIndexed('price', (string) $product->getPrice()));
        $doc->addField(Field::unIndexed('final_price', (string) $product->getFinalPrice()));
        $doc->addField(Field::unIndexed('small_image', (string) $product->getSmallImage()));
        $doc->addField(Field::unIndexed('thumbnail', (string) $product->getThumbnail()));

        // Index searchable attributes with boost
        foreach ($attributes as $attrCode) {
            $value = $this->_getAttributeValue($product, $attrCode);
            if ($value === '') {
                continue;
            }

            $field = Field::unstored($attrCode, $value);
            $field->boost = $helper->getProductAttributeBoost($attrCode, $storeId);
            $doc->addField($field);
        }

        // Index category paths
        if ($helper->isProductCategoryPathsEnabled($storeId)) {
            $categoryPaths = $this->_getCategoryPaths($product, $storeId);
            if ($categoryPaths !== '') {
                $field = Field::unstored('category_paths', $categoryPaths);
                $field->boost = $helper->getProductCategoryPathsBoost($storeId);
                $doc->addField($field);
            }
        }

        return $doc;
    }

    /**
     * Get a text value for a product attribute, handling selects/multiselects.
     */
    private function _getAttributeValue(Mage_Catalog_Model_Product $product, string $attrCode): string
    {
        $value = $product->getData($attrCode);

        if ($value === null || $value === '') {
            return '';
        }

        // For select/multiselect, get the text label
        $attribute = $product->getResource()->getAttribute($attrCode);
        if ($attribute && in_array($attribute->getFrontendInput(), ['select', 'multiselect'], true)) {
            $textValue = $product->getAttributeText($attrCode);
            if (is_array($textValue)) {
                return implode(' ', $textValue);
            }
            return (string) ($textValue ?: '');
        }

        // Strip HTML from text attributes
        return strip_tags((string) $value);
    }

    /**
     * Get category path names for a product (e.g. "Tennis > Racquets > Control").
     */
    private function _getCategoryPaths(Mage_Catalog_Model_Product $product, int $storeId): string
    {
        $categoryIds = $product->getCategoryIds();
        if (empty($categoryIds)) {
            return '';
        }

        $paths = [];
        foreach ($categoryIds as $categoryId) {
            $category = Mage::getModel('catalog/category')
                ->setStoreId($storeId)
                ->load($categoryId);

            if (!$category->getId() || !$category->getIsActive()) {
                continue;
            }

            $pathNames = [];
            foreach ($category->getPathIds() as $pathId) {
                if ((int) $pathId <= 1) {
                    continue; // Skip root categories
                }
                $pathCategory = Mage::getModel('catalog/category')
                    ->setStoreId($storeId)
                    ->load($pathId);
                if ($pathCategory->getName()) {
                    $pathNames[] = $pathCategory->getName();
                }
            }
            if (!empty($pathNames)) {
                $paths[] = implode(' > ', $pathNames);
            }
        }

        return implode(' ', $paths);
    }
}
