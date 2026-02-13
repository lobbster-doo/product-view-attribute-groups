<?php
/**
 * Copyright (c) Lobbster
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\Service;

use Lobbster\ProductViewAttributeGroups\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\Group;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory as GroupCollectionFactory;

/**
 * Provides product attribute groups (by pview_ prefix) with resolved attributes for product view.
 */
class GroupProvider
{
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
     * @param Config $config
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param AttributeCollectionFactory $attributeCollectionFactory
     * @param EavConfig $eavConfig
     * @param TitleFormatter $titleFormatter
     * @param AttributeValueResolver $valueResolver
     */
    public function __construct(
        Config $config,
        GroupCollectionFactory $groupCollectionFactory,
        AttributeCollectionFactory $attributeCollectionFactory,
        EavConfig $eavConfig,
        TitleFormatter $titleFormatter,
        AttributeValueResolver $valueResolver
    ) {
        $this->config = $config;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->eavConfig = $eavConfig;
        $this->titleFormatter = $titleFormatter;
        $this->valueResolver = $valueResolver;
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

        return $this->buildGroups($product, $attributeSetId, $storeId, $prefix, $requireVisible, $denylist);
    }

    /**
     * Build display groups for product from EAV groups and attributes.
     *
     * @param ProductInterface $product
     * @param int $attributeSetId
     * @param int $storeId
     * @param string $prefix
     * @param bool $requireVisibleOnFront
     * @param string[] $denylist
     * @return array<int, array{code: string, title: string, sort_order: int, attributes: array}>
     */
    private function buildGroups(
        ProductInterface $product,
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
            $startsWithPrefix = str_starts_with($groupNameLower, $prefixLower)
                || str_starts_with($groupNameLower, $prefixAlt);
            if ($groupName === '' || !$startsWithPrefix) {
                continue;
            }

            $attributes = $this->getAttributesWithValuesForGroup(
                $product,
                (int) $group->getAttributeGroupId(),
                $attributeSetId,
                $entityTypeId,
                $requireVisibleOnFront,
                $denylist
            );
            if (empty($attributes)) {
                continue;
            }

            $title = $this->titleFormatter->format((string) $groupName, $prefix);
            $result[] = [
                'code' => strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $groupName)),
                'title' => $title !== '' ? $title : (string) $groupName,
                'sort_order' => (int) $group->getSortOrder(),
                'attributes' => $attributes,
            ];
        }

        usort($result, static function ($a, $b) {
            return $a['sort_order'] <=> $b['sort_order'];
        });

        return array_values($result);
    }

    /**
     * Get attributes in group that have a non-empty frontend value for the product.
     *
     * @param ProductInterface $product
     * @param int $groupId
     * @param int $setId
     * @param int $entityTypeId
     * @param bool $requireVisibleOnFront
     * @param string[] $denylist
     * @return array<int, array{code: string, label: string, value: string}>
     */
    private function getAttributesWithValuesForGroup(
        ProductInterface $product,
        int $groupId,
        int $setId,
        int $entityTypeId,
        bool $requireVisibleOnFront,
        array $denylist
    ): array {
        $collection = $this->attributeCollectionFactory->create();
        $collection->setEntityTypeFilter($entityTypeId);
        $collection->setAttributeSetFilter($setId);
        $collection->addFieldToFilter('entity_attribute.attribute_group_id', ['eq' => $groupId]);
        $collection->getSelect()->columns(['entity_sort_order' => 'entity_attribute.sort_order']);

        $attributes = [];
        foreach ($collection->getItems() as $attribute) {
            /** @var AbstractAttribute $attribute */
            if ($requireVisibleOnFront && !$attribute->getIsVisibleOnFront()) {
                continue;
            }
            if (in_array($attribute->getAttributeCode(), $denylist, true)) {
                continue;
            }

            $value = $this->valueResolver->getFrontendValue($attribute, $product);
            if ($value === null || $value === '') {
                continue;
            }

            $sortOrder = (int) ($attribute->getData('entity_sort_order') ?? 0);
            $attributes[] = [
                'code' => $attribute->getAttributeCode(),
                'label' => (string) $attribute->getStoreLabel(),
                'value' => $value,
                'sort_order' => $sortOrder,
            ];
        }

        usort($attributes, static function ($a, $b) {
            return $a['sort_order'] <=> $b['sort_order'];
        });

        return array_map(static function ($a) {
            unset($a['sort_order']);
            return $a;
        }, $attributes);
    }
}
