<?php
/**
 * Copyright (c) Lobbster
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * Resolves frontend display value for product attributes.
 */
class AttributeValueResolver
{
    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(PriceCurrencyInterface $priceCurrency)
    {
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * Get frontend display value for an attribute. Returns null if empty/no value.
     *
     * @param AbstractAttribute $attribute
     * @param ProductInterface $product
     * @return string|null
     */
    public function getFrontendValue(AbstractAttribute $attribute, ProductInterface $product): ?string
    {
        /** @var \Magento\Framework\DataObject $product frontend getValue() expects DataObject; Product extends it */
        $value = $attribute->getFrontend()->getValue($product);

        if ($value === null) {
            return null;
        }

        if ($value instanceof Phrase) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            $value = $value === false ? '' : (string) $value;
        }

        if ($attribute->getFrontendInput() === 'price' && is_numeric($value) && $value !== '') {
            $value = $this->priceCurrency->convertAndFormat((float) $value);
        }

        $value = trim($value);
        return $this->isEmptyValue($attribute, $value, $product) ? null : $value;
    }

    /**
     * Whether the resolved frontend value should be considered empty for display.
     *
     * @param AbstractAttribute $attribute
     * @param string $frontendValue
     * @param ProductInterface $product
     * @return bool
     */
    private function isEmptyValue(AbstractAttribute $attribute, string $frontendValue, ProductInterface $product): bool
    {
        if ($frontendValue !== '') {
            return false;
        }

        $input = $attribute->getFrontendInput();
        if ($input === 'boolean') {
            $raw = $product->getData($attribute->getAttributeCode());
            return $raw === null || $raw === '';
        }

        return true;
    }
}
