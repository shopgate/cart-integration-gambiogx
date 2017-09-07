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
class ShopgateCustomerOrderModel
{
    const DEFAULT_CUSTOMER_ADDRESS_IDENTIFIER_DELIVERY = 'delivery';
    const DEFAULT_CUSTOMER_ADDRESS_IDENTIFIER_INVOICE  = 'invoice';

    /**
     * @var mixed
     */
    protected $_customerId = false;

    /**
     * @var bool
     */
    protected $splitStreetHouseNumber;

    /**
     * @var bool
     */
    protected $useStreet2;

    /**
     * @param string $customerId
     * @param bool   $splitStreetHouseNumber
     * @param bool   $useStreet2
     *
     * @throws ShopgateLibraryException
     */
    public function __construct($customerId, $splitStreetHouseNumber = false, $useStreet2 = false)
    {
        $this->_customerId            = $customerId;
        $this->splitStreetHouseNumber = $splitStreetHouseNumber;
        $this->useStreet2             = $useStreet2;
    }

    /**
     * @param        $customerLanguage
     * @param int    $limit
     * @param int    $offset
     * @param string $orderDateFrom
     * @param string $sortOrder
     *
     * @return array
     */
    public function getOrders(
        $customerLanguage,
        $limit = 10,
        $offset = 0,
        $orderDateFrom = '',
        $sortOrder = 'created_desc'
    ) {
        $result = array();

        $getOrdersResource = $this->_getOrdersResource(
            ShopgatePluginInitHelper::getLanguageIdByIsoCode($customerLanguage),
            $limit,
            $offset,
            $orderDateFrom,
            $sortOrder
        );

        while ($order = xtc_db_fetch_array($getOrdersResource)) {

            /** @var order_ORIGIN $orderDetail */
            $orderDetail         = new order($order['orders_id']);
            $shopgateOrder       = new ShopgateExternalOrder();
            $orderTotalsResource = $this->_getOrderTotalsResource($order['orders_id']);
            $orderTotals         = array();

            while ($_orderTotal = xtc_db_fetch_array($orderTotalsResource)) {
                $orderTotals[$_orderTotal['class']][] = $_orderTotal;
            }

            $shopgateOrder->setOrderNumber($this->_getShopgateOrderId($order['orders_id']));
            $shopgateOrder->setExternalOrderNumber($order['orders_id']);
            $shopgateOrder->setExternalOrderId($order['orders_id']);

            $shopgateOrder->setStatusName($order['orders_status_name']);

            $shopgateOrder->setCreatedTime($order['date_purchased']);
            $shopgateOrder->setMail($order['customers_email_address']);
            $shopgateOrder->setPhone($order['customers_telephone']);
            $shopgateOrder->setCurrency($order['currency']);
            $shopgateOrder->setPaymentMethod($order['payment_method']);
            $shopgateOrder->setAmountComplete(round($orderDetail->info['pp_total'], 2));

            /**
             * add shipping info
             */
            $shopgateOrder = $this->_setShippingInfo($shopgateOrder, $customerLanguage);

            /**
             * add extra cost
             */
            $shopgateOrder->setExtraCosts($this->_getExtraCosts($orderDetail->info['pp_shipping']));

            /**
             * add delivery notes
             */
            $shopgateOrder->setDeliveryNotes($this->_geDeliveryNotes($order['orders_id']));

            /**
             * add coupons
             */
            $shopgateOrder->setExternalCoupons($this->_getExternalCoupons($orderTotals, $shopgateOrder->getCurrency()));

            /**
             * add addresses
             */
            $shopgateOrder->setDeliveryAddress(
                $this->_getAddress(
                    $orderDetail->delivery,
                    ShopgateCustomerOrderModel::DEFAULT_CUSTOMER_ADDRESS_IDENTIFIER_DELIVERY
                )
            );
            $shopgateOrder->setInvoiceAddress(
                $this->_getAddress(
                    $orderDetail->billing,
                    ShopgateCustomerOrderModel::DEFAULT_CUSTOMER_ADDRESS_IDENTIFIER_INVOICE
                )
            );

            /**
             * set order taxes
             */
            $shopgateOrder->setOrderTaxes($this->_getOrderTaxes($orderTotals));

            /**
             * add order items
             */
            $orderItems = array();

            foreach ($orderDetail->products as $product) {
                $orderItem = new ShopgateExternalOrderItem();
                $orderItem->setItemNumber($product['id']);
                $orderItem->setItemNumberPublic($product['model']);
                $orderItem->setQuantity((int)$product['qty']);
                $orderItem->setName($product['name']);
                $orderItem->setUnitAmount(round($product['price'] / ($product['tax'] / 100 + 1), 2));
                $orderItem->setUnitAmountWithTax(round($product['price'], 2));
                $orderItem->setTaxPercent((float)$product['tax']);
                $orderItem->setCurrency($order['currency']);

                /**
                 * add property information
                 */
                if (isset($product['properties']) && count($product['properties']) > 0) {
                    $properties = array();
                    foreach ($product['properties'] as $property) {
                        array_push($properties, $property['properties_name'] . ': ' . $property['values_name']);
                    }
                    $orderItem->setName($orderItem->getName() . ' - ' . implode(', ', $properties));
                }

                array_push($orderItems, $orderItem);
            }

            $shopgateOrder->setItems($orderItems);

            array_push($result, $shopgateOrder);
        }

        return $result;
    }

    /**
     * @param int $orderShippingCost
     *
     * @return array
     */
    protected function _getExtraCosts($orderShippingCost = 0)
    {
        $result = array();
        if ($orderShippingCost > 0) {
            $extraCostItem = new ShopgateExternalOrderExtraCost();
            $extraCostItem->setType('shipping');
            $extraCostItem->setAmount(round($orderShippingCost, 2));
            array_push($result, $extraCostItem);
        }

        return $result;
    }

    /**
     * @param array  $orderTotals
     * @param string $currency
     *
     * @return array
     */
    protected function _getExternalCoupons($orderTotals, $currency)
    {
        $result = array();

        if (array_key_exists('ot_coupon', $orderTotals) && is_array($orderTotals['ot_coupon'])) {
            foreach ($orderTotals['ot_coupon'] as $couponItem) {

                /**
                 * prepare data
                 */
                $couponCodePartials = explode(':', $couponItem['title']);

                if (array_key_exists(1, $couponCodePartials)) {
                    $shopgateCoupon = new ShopgateExternalCoupon();
                    $shopgateCoupon->setOrderIndex($couponItem['sort_order']);
                    $shopgateCoupon->setCode(trim($couponCodePartials[1]));
                    $shopgateCoupon->setAmount(round(abs($couponItem['value']), 2));
                    $shopgateCoupon->setCurrency($currency);
                    $shopgateCoupon->setName(trim($couponCodePartials[0]));

                    array_push($result, $shopgateCoupon);
                }
            }
        }

        return $result;
    }

    /**
     * @param array  $address
     * @param string $addressType
     *
     * @return ShopgateAddress
     */
    protected function _getAddress(
        $address,
        $addressType = ShopgateCustomerOrderModel::DEFAULT_CUSTOMER_ADDRESS_IDENTIFIER_DELIVERY
    ) {
        $resultAddress = new ShopgateAddress();

        switch ($addressType) {
            case ShopgateCustomerOrderModel::DEFAULT_CUSTOMER_ADDRESS_IDENTIFIER_DELIVERY:
                $resultAddress->setIsDeliveryAddress(true);
                break;
            case ShopgateCustomerOrderModel::DEFAULT_CUSTOMER_ADDRESS_IDENTIFIER_INVOICE:
                $resultAddress->setIsInvoiceAddress(true);
                break;
        }

        $resultAddress->setFirstName($address['firstname']);
        $resultAddress->setLastName($address['lastname']);
        $resultAddress->setGender($address['gender']);
        $resultAddress->setCompany($address['company']);
        $resultAddress->setStreet1($address['street_address']);
        $resultAddress->setZipcode($address['postcode']);
        $resultAddress->setCity($address['city']);
        $resultAddress->setCountry($address['country_iso_2']);

        $resultAddress->setStreet1(
            $address['street_address'] .
            (
            (($this->splitStreetHouseNumber) && ('' != $address['house_number']))
                ? ' ' . $address['house_number']
                : ''
            )
        );

        if ($this->useStreet2) {
            // billing_additional_info / delivery_additional_info is manually mapped to additional_address_info in the
            // Gambio order class (see includes/classes/order.php).
            $resultAddress->setStreet2($address['additional_address_info']);
        }

        return $resultAddress;
    }

    /**
     * @param array $orderTaxes
     *
     * @return array
     */
    protected function _getOrderTaxes($orderTaxes)
    {
        $result = array();

        if (isset($orderTaxes['ot_tax']) && is_array($orderTaxes['ot_tax'])) {
            foreach ($orderTaxes['ot_tax'] as $orderTax) {
                $orderTaxItem = new ShopgateExternalOrderTax();

                $taxParts = explode(' ', ($orderTax['title']));

                $orderTaxItem->setLabel(
                    isset($taxParts[1])
                        ? $taxParts['1']
                        : 'undefined'
                );
                //$orderTaxItem->setTaxPercent((int)$taxPercent);
                $orderTaxItem->setAmount(round($orderTax['value'], 2));
                array_push($result, $orderTaxItem);
            }
        }

        return $result;
    }

    /**
     * @param        $customerLanguageId
     * @param int    $limit
     * @param int    $offset
     * @param string $orderDateFrom
     * @param string $sortOrder
     *
     * @return bool|resource
     */
    protected function _getOrdersResource(
        $customerLanguageId,
        $limit = 10,
        $offset = 0,
        $orderDateFrom = '',
        $sortOrder = 'created_desc'
    ) {
        switch ($sortOrder) {
            case 'created_desc':
                $sortBy = 'date_purchased DESC';
                break;
            case 'created_asc':
                $sortBy = 'date_purchased ASC';
                break;
            default:
                $sortBy = 'orders_id DESC';
        }

        $dateSelector = $orderDateFrom != ''
            ? " AND o.date_purchased >= '" . $orderDateFrom . "'"
            : '';

        $query
            = "SELECT
						o.*,
						s.*
					FROM
						" . TABLE_ORDERS . " o,
						" . TABLE_ORDERS_TOTAL . " ot,
						" . TABLE_ORDERS_STATUS . " s,
						" . TABLE_CUSTOMERS_INFO . " ci
					WHERE
						o.customers_id = '" . $this->_customerId . "' AND
						o.orders_id = ot.orders_id AND
						ot.class = 'ot_total' AND
						o.orders_status = s.orders_status_id AND
						s.language_id = '" . $customerLanguageId . "' AND
						o.customers_id = ci.customers_info_id AND
						o.date_purchased >= ci.customers_info_date_account_created
						" . $dateSelector . "
					ORDER BY " . $sortBy . "
					LIMIT " . $limit . " OFFSET " . $offset . "
					";

        return xtc_db_query($query);
    }

    /**
     * @param $orderId
     *
     * @return bool|resource
     */
    protected function _getOrderTotalsResource($orderId)
    {
        /** @var AccountContentView $coo_account_history_view */
        $query
            = "SELECT *
                    FROM
						" . TABLE_ORDERS_TOTAL . "
					WHERE
						orders_id = " . $orderId . "
					ORDER BY orders_id DESC";

        return xtc_db_query($query);
    }

    /**
     * @param int $orderId
     *
     * @return bool|resource
     */
    protected function _getDeliveryNotesResource($orderId)
    {
        $query
            = "SELECT *
					FROM
						" . 'orders_parcel_tracking_codes' . "
					WHERE
						order_id = " . $orderId . "
					ORDER BY order_id DESC";

        return xtc_db_query($query);
    }

    /**
     * @param int $orderId
     *
     * @return array
     */
    protected function _geDeliveryNotes($orderId)
    {
        $result                = array();
        $deliveryNotesResource = $this->_getDeliveryNotesResource($orderId);

        while ($deliveryNote = xtc_db_fetch_array($deliveryNotesResource)) {
            $deliveryNoteItem = new ShopgateDeliveryNote();
            $deliveryNoteItem->setShippingServiceId($deliveryNote['parcel_service_name']);
            $deliveryNoteItem->setTrackingNumber($deliveryNote['tracking_code']);
            $deliveryNoteItem->setShippingTime($deliveryNote['creation_date']);
            array_push($result, $deliveryNoteItem);
        }

        return $result;
    }

    /**
     * @param $orderId
     *
     * @return null
     */
    protected function _getShopgateOrderId($orderId)
    {
        // get currencies
        $qry = xtc_db_query(
            "SELECT *
		    FROM `" . TABLE_ORDERS_SHOPGATE_ORDER . "`"
        );

        while ($row = xtc_db_fetch_array($qry)) {
            if ($row['orders_id'] == $orderId) {
                return $row['shopgate_order_number'];
            }
        }

        return null;
    }

    /**
     * @param ShopgateOrder $shopgateOrder
     * @param string        $langIsoCode
     *
     * @return mixed
     * @throws ShopgateLibraryException
     */
    protected function _setShippingInfo($shopgateOrder, $langIsoCode)
    {
        $isoCodeParts = explode('_', $langIsoCode);
        $langId       = isset($isoCodeParts[0])
            ? $isoCodeParts[0]
            : $langIsoCode;

        $config = new ShopgateConfigGambioGx();
        $config->loadByLanguage($langId);
        $shippedStatusId = $config->getOrderStatusShipped();

        $query
            = "SELECT *
					FROM
						" . TABLE_ORDERS_STATUS_HISTORY . "
					WHERE
						orders_id = " . $shopgateOrder->getExternalOrderNumber() . "
					    AND orders_status_id = " . $shippedStatusId;

        while ($item = xtc_db_fetch_array(xtc_db_query($query))) {
            $shopgateOrder->setIsShippingCompleted(true);
            $shopgateOrder->setShippingCompletedTime($item['date_added']);
            break;
        }

        return $shopgateOrder;
    }
}
