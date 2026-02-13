<?php
/**
 * Copyright (c) Lobbster
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\Test\Integration\Service;

use Lobbster\ProductViewAttributeGroups\Service\GroupProvider;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for GroupProvider: prefixed groups, titles, only attributes with values, empty group excluded.
 *
 * @magentoDbIsolation enabled
 * @magentoDataFixture Lobbster_ProductViewAttributeGroups::Test/Integration/_files/attribute_set_with_pview_groups.php
 */
class GroupProviderTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var GroupProvider
     */
    private $groupProvider;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
        $this->groupProvider = $this->objectManager->get(GroupProvider::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
    }

    /**
     * Two prefixed groups returned with correct titles; only attributes with values; empty group excluded.
     */
    public function testGetGroupsForProductReturnsPrefixedGroupsWithValuesOnly(): void
    {
        $product = $this->productRepository->get('pview-test-product');
        $storeId = (int) $product->getStoreId();
        $groups = $this->groupProvider->getGroupsForProduct($product, $storeId);

        $msg = 'Expected two groups (Truck and Trailer); pview_Empty must be excluded (no attributes with values).';
        $this->assertCount(2, $groups, $msg);

        $codes = array_column($groups, 'code');
        $titles = array_column($groups, 'title');
        $this->assertContains('pview_truck', $codes);
        $this->assertContains('pview_trailer', $codes);
        $this->assertContains('Truck', $titles);
        $this->assertContains('Trailer', $titles);
        $this->assertNotContains('pview_empty', $codes, 'Empty group must not be returned.');

        foreach ($groups as $group) {
            $this->assertArrayHasKey('attributes', $group);
            $this->assertNotEmpty($group['attributes'], 'Each returned group must have at least one attribute.');
            foreach ($group['attributes'] as $attr) {
                $this->assertArrayHasKey('code', $attr);
                $this->assertArrayHasKey('label', $attr);
                $this->assertArrayHasKey('value', $attr);
                $this->assertNotEmpty(trim((string) $attr['value']));
            }
        }

        $truckGroup = null;
        $trailerGroup = null;
        foreach ($groups as $g) {
            if ($g['title'] === 'Truck') {
                $truckGroup = $g;
            }
            if ($g['title'] === 'Trailer') {
                $trailerGroup = $g;
            }
        }
        $this->assertNotNull($truckGroup);
        $this->assertNotNull($trailerGroup);
        $truckCodes = array_column($truckGroup['attributes'], 'code');
        $trailerCodes = array_column($trailerGroup['attributes'], 'code');
        $this->assertContains('meta_description', $truckCodes);
        $this->assertContains('meta_keyword', $trailerCodes);
        $truckMetaDesc = null;
        foreach ($truckGroup['attributes'] as $a) {
            if ($a['code'] === 'meta_description') {
                $truckMetaDesc = $a['value'];
                break;
            }
        }
        $this->assertSame('Truck value', $truckMetaDesc);
    }

    /**
     * Non-prefixed groups are never shown (custom set used has only pview_ groups).
     */
    public function testNonPrefixedGroupNotReturned(): void
    {
        $product = $this->productRepository->get('pview-test-product');
        $groups = $this->groupProvider->getGroupsForProduct($product, (int) $product->getStoreId());
        foreach ($groups as $group) {
            $code = $group['code'] ?? '';
            $this->assertStringStartsWith('pview_', $code, 'Only prefixed groups should appear.');
        }
    }
}
