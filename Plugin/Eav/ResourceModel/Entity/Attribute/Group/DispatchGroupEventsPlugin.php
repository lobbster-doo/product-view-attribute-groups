<?php
/**
 * Copyright (c) 2026 Lobbster. See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\Plugin\Eav\ResourceModel\Entity\Attribute\Group;

use Lobbster\ProductViewAttributeGroups\Model\PviewCacheFlusher;
use Lobbster\ProductViewAttributeGroups\Model\PviewFlushHelper;
use Magento\Eav\Model\Entity\Attribute\Group as AttributeGroup;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group as GroupResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Flush pview structure cache when an attribute group (name/sort) is saved or deleted.
 * Core Group model does not fire model events, so we plug the resource and clear by tag directly.
 */
class DispatchGroupEventsPlugin
{
    /**
     * @param PviewCacheFlusher $cacheFlusher
     * @param PviewFlushHelper $flushHelper
     */
    public function __construct(
        private readonly PviewCacheFlusher $cacheFlusher,
        private readonly PviewFlushHelper $flushHelper
    ) {
    }

    /**
     * Flush pview cache when attribute group is saved.
     *
     * @param GroupResource $subject
     * @param bool $result
     * @param AbstractModel $object
     * @return bool
     */
    public function afterSave(GroupResource $subject, $result, AbstractModel $object): bool
    {
        if ($object instanceof AttributeGroup) {
            $this->flushIfPviewGroup($object);
        }
        return (bool) $result;
    }

    /**
     * Flush pview cache when attribute group is deleted.
     *
     * @param GroupResource $subject
     * @param GroupResource $result
     * @param AbstractModel $object
     * @return GroupResource
     */
    public function afterDelete(GroupResource $subject, $result, AbstractModel $object): GroupResource
    {
        if ($object instanceof AttributeGroup) {
            $this->flushIfPviewGroup($object);
        }
        return $result;
    }

    /**
     * Flush cache if the group is or was a pview group (name/code changed).
     *
     * @param AttributeGroup $group
     * @return void
     */
    private function flushIfPviewGroup(AttributeGroup $group): void
    {
        $setId = (int) $group->getAttributeSetId();
        if ($setId <= 0) {
            return;
        }

        $newName = (string) ($group->getAttributeGroupName() ?? $group->getAttributeGroupCode() ?? '');
        $oldName = (string) (
            $group->getOrigData('attribute_group_name') ?? $group->getOrigData('attribute_group_code') ?? ''
        );

        if ($this->flushHelper->isPviewGroupName($newName) || $this->flushHelper->isPviewGroupName($oldName)) {
            $this->cacheFlusher->flushByAttributeSetId($setId);
        }
    }
}
