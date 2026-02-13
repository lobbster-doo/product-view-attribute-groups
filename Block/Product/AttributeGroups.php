<?php
/**
 * Copyright (c) Lobbster
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\Block\Product;

use Lobbster\ProductViewAttributeGroups\ViewModel\Product\AttributeGroups as AttributeGroupsViewModel;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\View\Element\Template;

/**
 * Block for product view attribute groups (pview_ groups) section.
 *
 * Handles caching only. All logic delegated to ViewModel.
 */
class AttributeGroups extends Template
{
    /**
     * Get current product from ViewModel.
     *
     * @return ProductInterface|null
     */
    public function getProduct(): ?ProductInterface
    {
        $viewModel = $this->getViewModel();
        return $viewModel ? $viewModel->getProduct() : null;
    }

    /**
     * Get ViewModel instance.
     *
     * @return AttributeGroupsViewModel|null
     */
    public function getViewModel(): ?AttributeGroupsViewModel
    {
        $viewModel = $this->getData('view_model');
        return $viewModel instanceof AttributeGroupsViewModel ? $viewModel : null;
    }

    /**
     * Block cache varies by product, store and attribute set.
     *
     * @return array
     */
    public function getCacheKeyInfo(): array
    {
        $keys = parent::getCacheKeyInfo();
        $product = $this->getProduct();
        
        if ($product && $product->getId()) {
            $keys['product_id'] = $product->getId();
            $keys['store_id'] = $product->getStoreId();
            $keys['attribute_set_id'] = $product->getAttributeSetId();
        }
        
        return $keys;
    }

    /**
     * Cache tags for product-specific invalidation.
     *
     * @return array
     */
    public function getCacheTags(): array
    {
        $tags = parent::getCacheTags();
        $product = $this->getProduct();
        
        if ($product && $product->getId()) {
            $tags[] = 'catalog_product_' . $product->getId();
        }
        
        return $tags;
    }
}
