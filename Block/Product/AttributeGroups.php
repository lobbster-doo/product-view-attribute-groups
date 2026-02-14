<?php
/**
 * Copyright (c) 2026 Lobbster. See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\Block\Product;

use Lobbster\ProductViewAttributeGroups\Service\GroupProvider;
use Lobbster\ProductViewAttributeGroups\ViewModel\Product\AttributeGroups as AttributeGroupsViewModel;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;

/**
 * Block for product view attribute groups (pview_ groups) section.
 *
 * Handles caching only. All logic delegated to ViewModel.
 */
class AttributeGroups extends Template implements IdentityInterface
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
     * Block cache varies by product, store, attribute set and module config (prefix/denylist/requireVisible).
     *
     * @return array
     */
    public function getCacheKeyInfo(): array
    {
        $cacheKey = '_cache_key_info';
        if ($this->getData($cacheKey) === null) {
            $keys = parent::getCacheKeyInfo();
            $product = $this->getProduct();
            $viewModel = $this->getViewModel();
            if ($product && $product->getId()) {
                $keys['product_id'] = $product->getId();
                $keys['store_id'] = $product->getStoreId();
                $keys['attribute_set_id'] = $product->getAttributeSetId();
                if ($viewModel) {
                    $storeId = (int) $product->getStoreId();
                    $denylist = $viewModel->getDenylist($storeId);
                    sort($denylist);
                    $keys['pview_cfg'] = substr(
                        hash('sha256', (string) json_encode([
                            $viewModel->getPrefix($storeId),
                            $viewModel->getRequireVisibleOnFront($storeId),
                            $denylist,
                        ])),
                        0,
                        32
                    );
                }
            }
            $this->setData($cacheKey, $keys);
        }
        return (array) $this->getData($cacheKey);
    }

    /**
     * Identities: product (Product::CACHE_TAG), pview set tag.
     *
     * Never call $product->getIdentities() to avoid ConfigurableProduct/stock plugins and heavy identity logic.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        $product = $this->getProduct();
        if (!$product || !$product->getId()) {
            return [];
        }
        $ids = [
            Product::CACHE_TAG . '_' . (int) $product->getId(),
        ];
        $setId = (int) $product->getAttributeSetId();
        if ($setId > 0) {
            $ids[] = GroupProvider::CACHE_TAG_SET_PREFIX . $setId;
        }
        return $ids;
    }
}
