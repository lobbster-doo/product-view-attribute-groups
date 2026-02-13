<?php
/**
 * Copyright (c) Lobbster
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads module configuration for product view attribute groups.
 */
class Config
{
    private const XML_PATH_ENABLED = 'catalog/product_view_attribute_groups/enabled';
    private const XML_PATH_PREFIX = 'catalog/product_view_attribute_groups/prefix';
    private const XML_PATH_REQUIRE_VISIBLE_ON_FRONT = 'catalog/product_view_attribute_groups/require_visible_on_front';
    private const XML_PATH_DENYLIST_CSV = 'catalog/product_view_attribute_groups/denylist_csv';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Whether product view attribute groups are enabled.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Attribute group name prefix (e.g. pview_).
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPrefix(?int $storeId = null): string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value !== null ? (string) $value : 'pview_';
    }

    /**
     * Whether to require is_visible_on_front for attributes.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function getRequireVisibleOnFront(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_REQUIRE_VISIBLE_ON_FRONT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Comma-separated denylist of attribute codes parsed as array.
     *
     * @param int|null $storeId
     * @return string[]
     */
    public function getDenylist(?int $storeId = null): array
    {
        $csv = $this->scopeConfig->getValue(
            self::XML_PATH_DENYLIST_CSV,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($csv === null || trim((string) $csv) === '') {
            return [];
        }
        $codes = array_map('trim', explode(',', (string) $csv));
        return array_values(array_filter($codes));
    }
}
