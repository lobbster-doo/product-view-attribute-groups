<?php
/**
 * Copyright (c) 2026 Lobbster. See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\ViewModel\Product;

use Lobbster\ProductViewAttributeGroups\Model\Config;
use Lobbster\ProductViewAttributeGroups\Service\GroupProvider;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * View model for product view attribute groups block.
 */
class AttributeGroups implements ArgumentInterface
{
    /**
     * @var GroupProvider
     */
    private $groupProvider;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var OutputHelper
     */
    private $outputHelper;

    /**
     * @var CatalogHelper
     */
    private $catalogHelper;

    /**
     * @param GroupProvider $groupProvider
     * @param Config $config
     * @param OutputHelper $outputHelper
     * @param CatalogHelper $catalogHelper
     */
    public function __construct(
        GroupProvider $groupProvider,
        Config $config,
        OutputHelper $outputHelper,
        CatalogHelper $catalogHelper
    ) {
        $this->groupProvider = $groupProvider;
        $this->config = $config;
        $this->outputHelper = $outputHelper;
        $this->catalogHelper = $catalogHelper;
    }

    /**
     * Get current product from catalog helper.
     *
     * @return ProductInterface|null
     */
    public function getProduct(): ?ProductInterface
    {
        return $this->catalogHelper->getProduct();
    }

    /**
     * Get catalog Output helper for rendering attribute values.
     *
     * @return OutputHelper
     */
    public function getOutputHelper(): OutputHelper
    {
        return $this->outputHelper;
    }

    /**
     * Get display groups with attributes for the current product. Empty if module disabled or no matching groups.
     *
     * @return array
     */
    public function getGroups(): array
    {
        $product = $this->getProduct();
        if (!$product || !$product->getId()) {
            return [];
        }

        if (!$this->config->isEnabled((int) $product->getStoreId())) {
            return [];
        }

        return $this->groupProvider->getGroupsForProduct($product, (int) $product->getStoreId());
    }

    /**
     * Prefix for attribute group names (for block cache key).
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPrefix(?int $storeId = null): string
    {
        return $this->config->getPrefix($storeId);
    }

    /**
     * Whether to require visible on front (for block cache key).
     *
     * @param int|null $storeId
     * @return bool
     */
    public function getRequireVisibleOnFront(?int $storeId = null): bool
    {
        return $this->config->getRequireVisibleOnFront($storeId);
    }

    /**
     * Denylist of attribute codes (for block cache key).
     *
     * @param int|null $storeId
     * @return string[]
     */
    public function getDenylist(?int $storeId = null): array
    {
        return $this->config->getDenylist($storeId);
    }
}
