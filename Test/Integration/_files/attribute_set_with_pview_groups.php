<?php
/**
 * Copyright (c) 2026 Lobbster. See LICENSE for license details.
 */
declare(strict_types=1);

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Eav\Api\AttributeManagementInterface;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Api\Data\AttributeGroupInterface;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSetModel;
use Magento\Eav\Model\Entity\Attribute\GroupFactory;
use Magento\Eav\Model\Entity\Type;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Store\Model\Store;
use Magento\TestFramework\Eav\Model\GetAttributeGroupByName;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

/** @var ProductAttributeRepositoryInterface $productAttributeRepository */
$productAttributeRepository = $objectManager->get(ProductAttributeRepositoryInterface::class);
foreach (['meta_description', 'meta_keyword'] as $attrCode) {
    $attr = $productAttributeRepository->get($attrCode);
    $attr->setIsVisibleOnFront(true);
    $productAttributeRepository->save($attr);
}

/** @var Type $entityType */
$entityType = $objectManager->create(Type::class)->loadByCode(ProductAttributeInterface::ENTITY_TYPE_CODE);
/** @var AttributeSetRepositoryInterface $attributeSetRepository */
$attributeSetRepository = $objectManager->get(AttributeSetRepositoryInterface::class);
/** @var AttributeManagementInterface $attributeManagement */
$attributeManagement = $objectManager->get(AttributeManagementInterface::class);
/** @var GetAttributeGroupByName $getAttributeGroupByName */
$getAttributeGroupByName = $objectManager->get(GetAttributeGroupByName::class);

/** @var AttributeSetModel $attributeSet getGroups/setGroups exist on concrete Set model */
$attributeSet = $objectManager->create(AttributeSetModel::class, [
    'data' => [
        'id' => null,
        'attribute_set_name' => 'Pview Test Set',
        'entity_type_id' => $entityType->getId(),
        'sort_order' => 400,
    ],
]);
$attributeSet->isObjectNew(true);
$attributeSet->setHasDataChanges(true);
$attributeSet->validate();
$attributeSetRepository->save($attributeSet);
$attributeSet->initFromSkeleton($entityType->getDefaultAttributeSetId());

/** @var AttributeGroupInterface $groupTruck */
$groupTruck = $objectManager->get(GroupFactory::class)->create();
$groupTruck->setId(null)
    ->setAttributeGroupName('pview_Truck')
    ->setAttributeSetId($attributeSet->getAttributeSetId())
    ->setSortOrder(10)
    ->setAttributes([]);
/** @var AttributeGroupInterface $groupTrailer */
$groupTrailer = $objectManager->get(GroupFactory::class)->create();
$groupTrailer->setId(null)
    ->setAttributeGroupName('pview_Trailer')
    ->setAttributeSetId($attributeSet->getAttributeSetId())
    ->setSortOrder(20)
    ->setAttributes([]);
/** @var AttributeGroupInterface $groupEmpty */
$groupEmpty = $objectManager->get(GroupFactory::class)->create();
$groupEmpty->setId(null)
    ->setAttributeGroupName('pview_Empty')
    ->setAttributeSetId($attributeSet->getAttributeSetId())
    ->setSortOrder(30)
    ->setAttributes([]);

$groups = $attributeSet->getGroups();
array_push($groups, $groupTruck, $groupTrailer, $groupEmpty);
$attributeSet->setGroups($groups);
$attributeSetRepository->save($attributeSet);

$setId = (int) $attributeSet->getAttributeSetId();
$truckGroup = $getAttributeGroupByName->execute($setId, 'pview_Truck');
$trailerGroup = $getAttributeGroupByName->execute($setId, 'pview_Trailer');

$entityTypeCode = ProductAttributeInterface::ENTITY_TYPE_CODE;
$attributeManagement->assign($entityTypeCode, $setId, $truckGroup->getAttributeGroupId(), 'meta_description', 1);
$attributeManagement->assign($entityTypeCode, $setId, $trailerGroup->getAttributeGroupId(), 'meta_keyword', 1);

/** @var ProductFactory $productFactory */
$productFactory = $objectManager->get(ProductFactory::class);
/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);

$product = $productFactory->create();
$product->setTypeId('simple')
    ->setAttributeSetId($setId)
    ->setWebsiteIds([1])
    ->setStoreId(Store::DEFAULT_STORE_ID)
    ->setName('Pview Test Product')
    ->setSku('pview-test-product')
    ->setPrice(10)
    ->setMetaTitle('meta title')
    ->setMetaKeyword('Trailer value')
    ->setMetaDescription('Truck value')
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setStockData(['use_config_manage_stock' => 1, 'qty' => 10, 'is_in_stock' => 1]);
$productRepository->save($product);
