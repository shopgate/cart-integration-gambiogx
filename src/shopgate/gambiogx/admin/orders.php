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

(defined('_VALID_XTC') || defined('_GM_VALID_CALL')) or die('Direct Access to this location is not allowed.');

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    include_once __DIR__ . '/../../vendor/autoload.php';
}

include_once DIR_FS_CATALOG . '/shopgate/plugin.php';

/**
 * Wrapper for setShopgateOrderlistStatus() with only one order.
 *
 * For compatibility reasons.
 *
 * @param int $orderId The ID of the order in the shop system.
 * @param int $status  The ID of the order status that has been set in the shopping system.
 */
function setShopgateOrderStatus($orderId, $status)
{
    if (empty($orderId)) {
        return;
    }

    setShopgateOrderlistStatus(array($orderId), $status);
}

/**
 * Wrapper for ShopgatePluginGambioGX::updateOrdersStatus(). Set the shipping status for a list of order IDs.
 *
 * @param int[] $orderIds The IDs of the orders in the shop system.
 * @param int   $status   The ID of the order status that has been set in the shopping system.
 */
function setShopgateOrderlistStatus($orderIds, $status)
{
    if (empty($orderIds) || !is_array($orderIds)) {
        return;
    }

    $plugin = new ShopgatePluginGambioGX();
    $plugin->updateOrdersStatus($orderIds, $status);
}
