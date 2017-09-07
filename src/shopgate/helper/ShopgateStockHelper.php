<?php

/**
 * This file is part of the Shopgate integration for GambioGX
 *
 * Copyright Shopgate Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Shopgate Inc, 804 Congress Ave, Austin, Texas 78701 <interfaces@shopgate.com>
 * @copyright Shopgate Inc
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, Version 2.0
 */
class ShopgateStockHelper
{
    /**
     * @var array
     */
    private $itemCache = array();

    /**
     * Clears internal cache that is used when items are loaded from the database
     */
    public function clearItemCache()
    {
        $this->itemCache = array();
    }

    /**
     * Returns the stock quantity or null if no stock is used is used and forced. Uses internal caching.
     *
     * @param ShopgateOrderItem $orderItem
     * @param array             $productItem
     * @param bool              $forceStock Returns a stock quantity, even if no stock is used
     *
     * @return int|null
     *
     * @throws ShopgateLibraryException
     */
    public function getStockForOrderItem(ShopgateOrderItem $orderItem, array $productItem, $forceStock = false)
    {
        // no language id required in this context
        $cartItemModel      = new ShopgateItemCartModel(null);
        $itemAttributeModel = new ShopgateItemAttributeModel();

        $internalOrderInfo = json_decode($orderItem->getInternalOrderInfo(), true);

        $productType   = ShopgateItemModel::ITEM_TYPE_SIMPLE;
        $selectionData = null;
        $variationData = array();

        $isChild = strlen($orderItem->getParentItemNumber()) > 0 || !empty($internalOrderInfo);
        if ($isChild) {
            $parentId = $cartItemModel->getProductsIdFromCartItem($orderItem);
            if (!empty($internalOrderInfo['is_property_attribute'])) {
                $productType   = ShopgateItemModel::ITEM_TYPE_CHILD_PROPERTY_COMBINATION;
                $selectionData = $internalOrderInfo['products_properties_combis_id'];
                if (empty($this->itemCache[$parentId])) {
                    $this->itemCache[$parentId] = $itemAttributeModel->getPropertyCombinationsFromDatabase($parentId);
                }
            } else {
                $productType   = ShopgateItemModel::ITEM_TYPE_CHILD_ATTRIBUTE;
                $selectionData = $cartItemModel->getCartItemAttributeSelection($orderItem);
                if (empty($this->itemCache[$parentId])) {
                    // normalized keys are required, because attribute id change when changing attributes in the backend
                    $this->itemCache[$parentId] = $itemAttributeModel->getAttributesFromDatabase($parentId, true);
                }
            }
            $variationData = $this->itemCache[$parentId];
        }

        return $this->getStockQuantity($productItem, $productType, $selectionData, $variationData, $forceStock);
    }

    /**
     * Returns the stock quantity or null if no stock is used is used and forced
     *
     * @param array $productItem
     * @param array $childData
     * @param bool  $forceStock Returns a stock quantity, even if no stock is used
     *
     * @return int|null
     *
     * @throws ShopgateLibraryException
     */
    public function getStockForExportItem(array $productItem, array $childData, $forceStock = false)
    {
        $productType   = ShopgateItemModel::ITEM_TYPE_SIMPLE;
        $selectionData = null;
        $variationData = array();

        if (!empty($childData)) {
            $productType = !empty($childData['variation_type'])
                ? $childData['variation_type']
                : ShopgateItemModel::ITEM_TYPE_CHILD_ATTRIBUTE;

            switch ($productType) {
                case ShopgateItemModel::ITEM_TYPE_CHILD_PROPERTY_COMBINATION:
                    $selectionData = $childData['raw_variation_data']['products_properties_combis_id'];
                    $variationData = array(
                        // only the current variation required
                        $selectionData => $childData['raw_variation_data'],
                    );
                    break;
                case ShopgateItemModel::ITEM_TYPE_CHILD_ATTRIBUTE:
                default:
                    $selectionData = array();
                    $variationData = array();
                    foreach ($childData as $optionValueSelection) {
                        $attributeId                 = $optionValueSelection['products_attributes_id'];
                        $selectionData[]             = $attributeId;
                        $variationData[$attributeId] = $optionValueSelection;
                    }
                    break;
            }
        }

        return $this->getStockQuantity($productItem, $productType, $selectionData, $variationData, $forceStock);
    }

    /**
     * Returns the stock quantity or null if no stock is used and forced
     *
     * @param array     $productItem
     * @param string    $productType
     * @param int|array $variationSelection
     * @param array     $variationData
     * @param bool      $forceStock Returns a stock quantity, even if no stock is used
     *
     * @return int|null
     *
     * @throws ShopgateLibraryException
     */
    public function getStockQuantity(
        array $productItem,
        $productType = ShopgateItemModel::ITEM_TYPE_SIMPLE,
        $variationSelection = null,
        array $variationData = array(),
        $forceStock = false
    ) {
        if (!$forceStock && (STOCK_ALLOW_CHECKOUT == 'true' || STOCK_CHECK != 'true')) {
            return null;
        }

        $parentQuantity = (int)$productItem["products_quantity"];

        switch ($productType) {
            case ShopgateItemModel::ITEM_TYPE_CHILD_PROPERTY_COMBINATION:
                $quantity = $this->getProductPropertiesStock(
                    $parentQuantity,
                    $variationData[$variationSelection],
                    $productItem,
                    $forceStock
                );
                break;
            case ShopgateItemModel::ITEM_TYPE_CHILD_ATTRIBUTE:
                $quantity = $this->getProductAttributeStock(
                    $parentQuantity,
                    $variationSelection,
                    $variationData
                );
                break;
            case ShopgateItemModel::ITEM_TYPE_SIMPLE:
            default:
                $quantity = $parentQuantity;
                break;
        }

        return $quantity;
    }

    /**
     * @param int   $parentQuantity
     * @param array $variationSelection
     * @param array $variationData
     *
     * @return int
     *
     * @throws ShopgateLibraryException
     */
    public function getProductAttributeStock(
        $parentQuantity,
        array $variationSelection,
        array $productAttributes
    ) {
        if (ATTRIBUTE_STOCK_CHECK != 'true') {
            return $parentQuantity;
        }

        return $this->calculateProductAttributeStock($parentQuantity, $variationSelection, $productAttributes);
    }

    /**
     * Takes a product, it's attributes and a list of selected values per option. It returns the stock of the selection.
     *
     * @param int   $parentQuantity
     * @param array $attributeSelections
     * @param array $attributes
     *
     * @return int
     *
     * @throws ShopgateLibraryException
     */
    public function calculateProductAttributeStock($parentQuantity, array $attributeSelections, array $attributes)
    {
        $quantity = $parentQuantity;

        // make sure there are any attributes to work with
        if (empty($attributes)) {
            // an attribute not being available is essentially the same as the product not being found
            throw new ShopgateLibraryException(ShopgateLibraryException::CART_ITEM_PRODUCT_NOT_FOUND);
        }

        foreach ($attributeSelections as $attributeSelection) {
            // take only lowest available quantity across all available attributes
            if ($quantity > $attributes[$attributeSelection]['attributes_stock']) {
                $quantity = $attributes[$attributeSelection]['attributes_stock'];
            }
        }

        return $quantity;
    }

    /**
     * Returns the stock quantity or null if no stock is used and forced
     *
     * @param int   $parentQuantity
     * @param array $propertyCombination
     * @param array $productProperySettings
     * @param bool  $forceStock Returns a stock quantity, even if no stock is used
     *
     * @return int|null
     */
    public function getProductPropertiesStock(
        $parentQuantity,
        array $propertyCombination,
        array $productProperySettings,
        $forceStock = false
    ) {
        switch ($productProperySettings['use_properties_combis_quantity']) {
            case '1': // use parents quantity
                return $parentQuantity;
            case '3': // no check
                if ($forceStock) {
                    return $propertyCombination['combi_quantity'];
                }
                break;
            case '0': // default value (use combination quantity)
            case '2': // use combination quantity
            default: // do same as '0'
                return $propertyCombination['combi_quantity'];
        }

        return null;
    }
}
