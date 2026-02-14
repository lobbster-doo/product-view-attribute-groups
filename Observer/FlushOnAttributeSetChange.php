<?php
/**
 * Copyright (c) 2026 Lobbster. See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\Observer;

use Lobbster\ProductViewAttributeGroups\Model\PviewCacheFlusher;
use Magento\Eav\Model\Entity\Attribute\Set;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Flush pview structure cache when an attribute set is saved or deleted.
 */
class FlushOnAttributeSetChange implements ObserverInterface
{
    /**
     * @param PviewCacheFlusher $cacheFlusher
     */
    public function __construct(
        private readonly PviewCacheFlusher $cacheFlusher
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
        $set = $event->getData('data_object') ?: $event->getData('object');
        if (!$set instanceof Set) {
            return;
        }

        $setId = (int) $set->getAttributeSetId();
        if ($setId <= 0 || isset(self::$flushedSetIds[$setId])) {
            return;
        }

        $this->cacheFlusher->flushByAttributeSetId($setId);
        self::$flushedSetIds[$setId] = true;
    }
}
