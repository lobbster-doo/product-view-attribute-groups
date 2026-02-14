<?php
/**
 * Copyright (c) 2026 Lobbster. See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\Model;

use Lobbster\ProductViewAttributeGroups\Service\GroupProvider;
use Magento\Framework\App\CacheInterface;
use Magento\PageCache\Model\Cache\Type as FullPageCacheType;

/**
 * Single point to flush pview-related caches (structure + FPC) by attribute set.
 */
class PviewCacheFlusher
{
    /**
     * @param CacheInterface $cache
     * @param FullPageCacheType $fullPageCache
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly FullPageCacheType $fullPageCache
    ) {
    }

    /**
     * Flush structure cache and full page cache for the given attribute set.
     *
     * @param int $setId
     * @return void
     */
    public function flushByAttributeSetId(int $setId): void
    {
        if ($setId <= 0) {
            return;
        }
        $tags = GroupProvider::getCacheTagsForAttributeSet($setId);
        if ($tags === []) {
            return;
        }
        $this->cache->clean($tags);
        $this->fullPageCache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, $tags);
    }
}
