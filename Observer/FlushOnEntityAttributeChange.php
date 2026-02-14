<?php
/**
 * Copyright (c) 2026 Lobbster. See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\Observer;

use Lobbster\ProductViewAttributeGroups\Model\PviewCacheFlusher;
use Lobbster\ProductViewAttributeGroups\Model\PviewFlushHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Flush pview structure cache when entity attribute (assignment to set/group) is saved or deleted.
 */
class FlushOnEntityAttributeChange implements ObserverInterface
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

    /** @var array<int, true> set IDs already flushed this request (guard against double dispatch) */
    private static array $flushedSetIds = [];

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $ea = $event->getData('data_object') ?: $event->getData('attribute');
        if (!is_object($ea) || !method_exists($ea, 'getAttributeSetId') || !method_exists($ea, 'getAttributeGroupId')) {
            return;
        }

        $setId = (int) $ea->getAttributeSetId();
        if ($setId <= 0 || isset(self::$flushedSetIds[$setId])) {
            return;
        }

        $newGroupId = (int) $ea->getAttributeGroupId();
        $oldGroupId = (int) ($ea->getOrigData('attribute_group_id') ?? 0);

        if ($this->flushHelper->groupIdIsPview($newGroupId) || $this->flushHelper->groupIdIsPview($oldGroupId)) {
            $this->cacheFlusher->flushByAttributeSetId($setId);
            self::$flushedSetIds[$setId] = true;
        }
    }
}
