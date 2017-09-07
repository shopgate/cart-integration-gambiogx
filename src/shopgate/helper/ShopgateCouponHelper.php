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
class ShopgateCouponHelper
{
    /**
     * If the current order item (product) is a child product the item number was
     * generated in the schema <productId>_<attributeId>.
     * This function returns the shop system product id
     *
     * @param ShopgateOrderItem $sgOrderItem
     *
     * @return string
     */
    public static function getProductIdFromCartItem(ShopgateOrderItem $sgOrderItem)
    {
        $id = $sgOrderItem->getParentItemNumber();
        if (empty($id)) {
            $ids   = $sgOrderItem->getItemNumber();
            $idArr = explode('_', $ids);
            $id    = $idArr[0];
        }

        return $id;
    }

    /**
     * Calculate the complete amount of all items in a specific shopping cart
     *
     * @param ShopgateCart $cart
     *
     * @return float|int
     */
    public static function getCompleteAmount(ShopgateCart $cart)
    {
        $completeAmount = 0;
        foreach ($cart->getItems() as $item) {
            // It seems to happen that unit_amount_with_tax is not set in every case for method checkCart
            if ($item->getUnitAmountWithTax()) {
                $itemAmount = $item->getUnitAmountWithTax();
            } elseif ($item->getTaxPercent() > 0) {
                $itemAmount = $item->getUnitAmount() * (1 + ($item->getTaxPercent() / 100));
            } else {
                $itemAmount = $item->getUnitAmount();
            }

            $completeAmount += $itemAmount * $item->getQuantity();
        }

        return $completeAmount;
    }
}
