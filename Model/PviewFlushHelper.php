<?php
/**
 * Copyright (c) 2026 Lobbster. See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\Model;

use Magento\Eav\Model\Entity\Attribute\Group;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Helper for pview-related cache flush: detect if a group/set is pview-prefixed.
 */
class PviewFlushHelper
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var GroupCollectionFactory
     */
    private $groupCollectionFactory;

    /**
     * @var string[]|null
     */
    private $allPrefixesLower;

    /**
     * @var array<int, bool> group_id => is_pview
     */
    private $groupIdIsPviewCache = [];

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param GroupCollectionFactory $groupCollectionFactory
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        GroupCollectionFactory $groupCollectionFactory
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->groupCollectionFactory = $groupCollectionFactory;
    }

    /**
     * All configured prefixes (lowercase + underscore and hyphen variants).
     *
     * @return string[]
     */
    public function getAllPrefixesLower(): array
    {
        if ($this->allPrefixesLower !== null) {
            return $this->allPrefixesLower;
        }
        $prefixes = [];
        $storeIds = [0];
        foreach ($this->storeManager->getStores() as $store) {
            $storeIds[] = (int) $store->getId();
        }
        foreach (array_unique($storeIds) as $storeId) {
            $p = (string) $this->config->getPrefix($storeId);
            if ($p === '') {
                continue;
            }
            $p = mb_strtolower($p, 'UTF-8');
            $prefixes[] = $p;
            $prefixes[] = str_replace('_', '-', $p);
        }
        $prefixes[] = 'pview_';
        $prefixes[] = 'pview-';
        $this->allPrefixesLower = array_values(array_unique($prefixes));
        return $this->allPrefixesLower;
    }

    /**
     * Whether the group name matches any pview prefix.
     *
     * @param string $name
     * @return bool
     */
    public function isPviewGroupName(string $name): bool
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        foreach ($this->getAllPrefixesLower() as $p) {
            if ($p !== '' && str_starts_with($name, $p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the given group ID belongs to a pview-prefixed group (memoized per request).
     *
     * @param int $groupId
     * @return bool
     */
    public function groupIdIsPview(int $groupId): bool
    {
        if ($groupId <= 0) {
            return false;
        }
        if (isset($this->groupIdIsPviewCache[$groupId])) {
            return $this->groupIdIsPviewCache[$groupId];
        }
        $collection = $this->groupCollectionFactory->create();
        $collection->addFieldToFilter('attribute_group_id', ['eq' => $groupId]);
        $collection->setPageSize(1);
        $collection->setCurPage(1);
        $group = $collection->getFirstItem();
        $name = $group instanceof Group
            ? (string) ($group->getAttributeGroupName() ?? $group->getAttributeGroupCode() ?? '')
            : '';
        $this->groupIdIsPviewCache[$groupId] = $this->isPviewGroupName($name);
        return $this->groupIdIsPviewCache[$groupId];
    }

    /**
     * Whether the attribute set contains any pview-prefixed group.
     *
     * @param int $attributeSetId
     * @return bool
     */
    public function attributeSetHasPviewGroups(int $attributeSetId): bool
    {
        if ($attributeSetId <= 0) {
            return false;
        }
        $collection = $this->groupCollectionFactory->create();
        $collection->setAttributeSetFilter($attributeSetId);
        foreach ($collection->getItems() as $group) {
            /** @var Group $group */
            $name = (string) ($group->getAttributeGroupName() ?? $group->getAttributeGroupCode() ?? '');
            if ($this->isPviewGroupName($name)) {
                return true;
            }
        }
        return false;
    }
}
