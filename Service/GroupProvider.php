<?php
/**
 * Copyright (c) 2026 Lobbster. See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\Service;

use Lobbster\ProductViewAttributeGroups\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\Group;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory as GroupCollectionFactory;

/**
 * Provides product attribute groups (by pview_ prefix) with resolved attributes for product view.
 * Group structure (groups + attribute metadata per attribute set/store) is cached to avoid
 * repeated group/attribute collection queries for products sharing the same attribute set.
 */
class GroupProvider
{
    public const CACHE_TAG = 'pview_attribute_groups';
    public const CACHE_TAG_SET_PREFIX = 'pview_as_';
    private const CACHE_LIFETIME = 86400;

    // phpcs:disable Magento2.Functions.StaticFunction.StaticFunction -- tag helper, no instance state
    /**
     * Cache tags to clear when attribute set / group structure changes (pview structure cache).
     *
     * @param int $setId
     * @return string[]
     */
    public static function getCacheTagsForAttributeSet(int $setId): array
    {
        if ($setId <= 0) {
            return [];
        }
        return [self::CACHE_TAG_SET_PREFIX . $setId];
    }
    // phpcs:enable Magento2.Functions.StaticFunction.StaticFunction

    private const CACHE_KEY_PREFIX = 'pview_group_structure_';

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var GroupCollectionFactory
     */
    private $groupCollectionFactory;

    /**
     * @var AttributeCollectionFactory
     */
    private $attributeCollectionFactory;

    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @var TitleFormatter
     */
    private $titleFormatter;

    /**
     * @var AttributeValueResolver
     */
    private $valueResolver;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param Config $config
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param AttributeCollectionFactory $attributeCollectionFactory
     * @param EavConfig $eavConfig
     * @param TitleFormatter $titleFormatter
     * @param AttributeValueResolver $valueResolver
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Config $config,
        GroupCollectionFactory $groupCollectionFactory,
        AttributeCollectionFactory $attributeCollectionFactory,
        EavConfig $eavConfig,
        TitleFormatter $titleFormatter,
        AttributeValueResolver $valueResolver,
        CacheInterface $cache,
        SerializerInterface $serializer
    ) {
        $this->config = $config;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->eavConfig = $eavConfig;
        $this->titleFormatter = $titleFormatter;
        $this->valueResolver = $valueResolver;
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    /**
     * Get display groups with attributes for the product. Only groups with at least one attribute value are returned.
     *
     * @param ProductInterface $product
     * @param int|null $storeId
     * @return array
     */
    public function getGroupsForProduct(ProductInterface $product, ?int $storeId = null): array
    {
        $storeId = $storeId ?? (int) $product->getStoreId();
        $attributeSetId = (int) $product->getAttributeSetId();
        
        if ($attributeSetId <= 0) {
            return [];
        }

        $prefix = $this->config->getPrefix($storeId);
        $requireVisible = $this->config->getRequireVisibleOnFront($storeId);
        $denylist = $this->config->getDenylist($storeId);

        $structure = $this->getCachedStructure($attributeSetId, $storeId, $prefix, $requireVisible, $denylist);
        $entityType = $this->eavConfig->getEntityType(\Magento\Catalog\Model\Product::ENTITY);
        $entityTypeId = $entityType ? (int) $entityType->getId() : 0;
        return $this->buildGroupsFromStructure($product, $structure, $storeId, $entityTypeId);
    }

    /**
     * Load group structure (groups + attribute metadata, no values) from cache or build and cache.
     *
     * Keyed by attribute_set_id + store + config so products with same set reuse it.
     *
     * @param int $attributeSetId
     * @param int $storeId
     * @param string $prefix
     * @param bool $requireVisibleOnFront
     * @param array $denylist
     * @return array Group structure (code, title, sort_order, attributes)
     */
    private function getCachedStructure(
        int $attributeSetId,
        int $storeId,
        string $prefix,
        bool $requireVisibleOnFront,
        array $denylist
    ): array {
        $denylistKey = substr(hash('sha256', implode(',', $denylist)), 0, 32);
        $prefixKey = preg_replace('/[^a-z0-9_\-]/i', '_', $prefix);
        $cacheKey = self::CACHE_KEY_PREFIX . $attributeSetId . '_' . $storeId . '_' . $prefixKey . '_'
            . ($requireVisibleOnFront ? '1' : '0') . '_' . $denylistKey;
        $cached = $this->cache->load($cacheKey);
        if ($cached !== false) {
            try {
                $decoded = $this->serializer->unserialize($cached);
                return is_array($decoded) ? $decoded : [];
            } catch (\Throwable $e) {
                return [];
            }
        } else {
            $structure = $this->buildStructure($attributeSetId, $storeId, $prefix, $requireVisibleOnFront, $denylist);
            $tags = [
                self::CACHE_TAG,
                self::CACHE_TAG_SET_PREFIX . $attributeSetId,
            ];
            $this->cache->save($this->serializer->serialize($structure), $cacheKey, $tags, self::CACHE_LIFETIME);
            return $structure;
        }
    }

    /**
     * Build group structure (no product values) from EAV. Used to populate cache.
     *
     * @param int $attributeSetId
     * @param int $storeId
     * @param string $prefix
     * @param bool $requireVisibleOnFront
     * @param array $denylist
     * @return array Group structure (code, title, sort_order, attributes)
     */
    private function buildStructure(
        int $attributeSetId,
        int $storeId,
        string $prefix,
        bool $requireVisibleOnFront,
        array $denylist
    ): array {
        $entityType = $this->eavConfig->getEntityType(\Magento\Catalog\Model\Product::ENTITY);
        if (!$entityType) {
            return [];
        }
        $entityTypeId = (int) $entityType->getId();
        $groupCollection = $this->groupCollectionFactory->create();
        $groupCollection->setAttributeSetFilter($attributeSetId);

        $result = [];
        foreach ($groupCollection->getItems() as $group) {
            /** @var Group $group */
            $groupName = $group->getAttributeGroupName() ?? $group->getAttributeGroupCode() ?? '';
            $groupNameLower = mb_strtolower((string) $groupName, 'UTF-8');
            $prefixLower = mb_strtolower($prefix, 'UTF-8');
            $prefixAlt = str_replace('_', '-', $prefixLower);
            $matchesPrefix = str_starts_with($groupNameLower, $prefixLower)
                || str_starts_with($groupNameLower, $prefixAlt);
            if ($groupName === '' || !$matchesPrefix) {
                continue;
            }
            $attributeMeta = $this->getAttributeMetadataForGroup(
                (int) $group->getAttributeGroupId(),
                $attributeSetId,
                $entityTypeId,
                $storeId,
                $requireVisibleOnFront,
                $denylist
            );
            if (empty($attributeMeta)) {
                continue;
            }
            $title = $this->titleFormatter->format((string) $groupName, $prefix);
            $result[] = [
                'code' => strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $groupName)),
                'title' => $title !== '' ? $title : (string) $groupName,
                'sort_order' => (int) $group->getSortOrder(),
                'attributes' => $attributeMeta,
            ];
        }
        usort($result, static fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
        return array_values($result);
    }

    /**
     * Attribute metadata for a group (code, label, sort_order) without product values.
     *
     * @param int $groupId
     * @param int $setId
     * @param int $entityTypeId
     * @param int $storeId
     * @param bool $requireVisibleOnFront
     * @param array $denylist
     * @return list<array{code: string, label: string, sort_order: int}>
     */
    private function getAttributeMetadataForGroup(
        int $groupId,
        int $setId,
        int $entityTypeId,
        int $storeId,
        bool $requireVisibleOnFront,
        array $denylist
    ): array {
        $collection = $this->attributeCollectionFactory->create();
        $collection->setEntityTypeFilter($entityTypeId);
        $collection->setAttributeSetFilter($setId);
        $collection->addFieldToFilter(
            'entity_attribute.attribute_group_id',
            ['eq' => $groupId]
        );
        $collection->addStoreLabel($storeId);
        $collection->getSelect()->columns(['entity_sort_order' => 'entity_attribute.sort_order']);

        $out = [];
        foreach ($collection->getItems() as $attribute) {
            /** @var AbstractAttribute $attribute */
            if ($requireVisibleOnFront && !$attribute->getIsVisibleOnFront()) {
                continue;
            }
            if (in_array($attribute->getAttributeCode(), $denylist, true)) {
                continue;
            }
            $out[] = [
                'code' => $attribute->getAttributeCode(),
                'label' => (string) $attribute->getStoreLabel(),
                'sort_order' => (int) ($attribute->getData('entity_sort_order') ?? 0),
            ];
        }
        usort($out, static fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
        return $out;
    }

    /**
     * Build display groups from cached structure, resolving only frontend values for the product.
     *
     * Bulk-loads attribute models by code to avoid N× getAttribute() calls.
     *
     * @param ProductInterface $product
     * @param array $structure
     * @param int $storeId
     * @param int $entityTypeId
     * @return array
     */
    private function buildGroupsFromStructure(
        ProductInterface $product,
        array $structure,
        int $storeId,
        int $entityTypeId
    ): array {
        $codes = [];
        foreach ($structure as $group) {
            foreach ($group['attributes'] as $attrMeta) {
                $codes[$attrMeta['code']] = true;
            }
        }
        $codes = array_keys($codes);
        $attributesByCode = $this->loadAttributesByCode($entityTypeId, $codes, $storeId);

        $result = [];
        foreach ($structure as $group) {
            $attributes = [];
            foreach ($group['attributes'] as $attrMeta) {
                $attribute = $attributesByCode[$attrMeta['code']] ?? null;
                if ($attribute === null) {
                    continue;
                }
                $value = $this->valueResolver->getFrontendValue($attribute, $product, $storeId);
                if ($value === null || $value === '') {
                    continue;
                }
                $attributes[] = [
                    'code' => $attrMeta['code'],
                    'label' => $attrMeta['label'],
                    'value' => $value,
                ];
            }
            if (empty($attributes)) {
                continue;
            }
            $result[] = [
                'code' => $group['code'],
                'title' => $group['title'],
                'sort_order' => $group['sort_order'],
                'attributes' => $attributes,
            ];
        }
        return $result;
    }

    /**
     * Load product attributes by codes (one collection), index by code. Store-aware labels.
     *
     * @param int $entityTypeId
     * @param string[] $codes
     * @param int $storeId
     * @return array<string, AbstractAttribute> code => attribute
     */
    private function loadAttributesByCode(int $entityTypeId, array $codes, int $storeId): array
    {
        if ($codes === []) {
            return [];
        }
        $collection = $this->attributeCollectionFactory->create();
        $collection->setEntityTypeFilter($entityTypeId);
        $collection->addFieldToFilter('attribute_code', ['in' => $codes]);
        $collection->addStoreLabel($storeId);
        $out = [];
        foreach ($collection->getItems() as $attribute) {
            /** @var AbstractAttribute $attribute */
            $attribute->setStoreId($storeId);
            $out[$attribute->getAttributeCode()] = $attribute;
        }
        return $out;
    }
}
