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
define("SHOPGATE_PLUGIN_VERSION", "2.9.52");

/**
 * GambioGX Plugin for Shopgate
 */
class ShopgatePluginGambioGX extends ShopgatePlugin
{
    const SG_CF_ORDER                  = 'SG_CF_ORDER';
    const SG_CF_ORDER_INVOICE_ADDRESS  = 'SG_CF_ORDER_INVOICE_ADDRESS';
    const SG_CF_ORDER_SHIPPING_ADDRESS = 'SG_CF_ORDER_SHIPPING_ADDRESS';

    /**
     * @var ShopgateConfigGambioGx
     */
    protected $config;

    /**
     * @var int
     */
    private $languageId;

    /**
     * @var int
     */
    private $countryId;

    /**
     * @var int
     */
    private $zoneId;

    /**
     * @var array
     */
    private $currency;

    /**
     * @var string
     */
    private $language = "german";

    /**
     * @var string
     */
    private $gambioGXVersion;

    /**
     * @var array
     */
    private $optionFieldArray = array();

    /**
     * @var GMSEOBoost_ORIGIN
     */
    private $gmSEOBoost;

    /**
     * @var bool
     */
    private $gmSEOBoostProductsEnabled;

    /**
     * @var int
     */
    private $currencyId;

    /**
     * @var bool reflects the ACCOUNT_SPLIT_STREET_INFORMATION setting available since 3.1.1.0
     */
    private $splitStreetHouseNumber;

    /**
     * @var bool reflects the ACCOUNT_ADDITIONAL_INFO setting available since 3.1.1.0
     */
    private $useStreet2;

    public function startup()
    {
        $this->requireFiles();

        $initHelper   = new ShopgatePluginInitHelper();
        $this->config = $initHelper->getShopgateConfig();
        $initHelper->initDatabaseConstants();
        $initHelper->initShopgateDatabaseConstants();
        $this->gambioGXVersion = ShopgateTools::getGambioVersion();
        $initHelper->checkShopgateTable();

        // prevent IDE warnings (these symbols are defined dynamically  using database values in initialization code)
        if (!defined('STOCK_CHECK')) {
            define('STOCK_CHECK', 'true');
        }
        if (!defined('ATTRIBUTE_STOCK_CHECK')) {
            define('ATTRIBUTE_STOCK_CHECK', 'true');
        }
        if (!defined('STOCK_ALLOW_CHECKOUT')) {
            define('STOCK_ALLOW_CHECKOUT', 'true');
        }
        if (!defined('ACCOUNT_SPLIT_STREET_INFORMATION')) {
            define('ACCOUNT_SPLIT_STREET_INFORMATION', 'true');
        }
        if (!defined('ACCOUNT_ADDITIONAL_INFO')) {
            define('ACCOUNT_ADDITIONAL_INFO', 'true');
        }
        if (!defined('DEFAULT_CUSTOMERS_STATUS_ID_GUEST')) {
            define('DEFAULT_CUSTOMERS_STATUS_ID_GUEST', '1');
        }
        if (!defined('DEFAULT_CUSTOMERS_STATUS_ID')) {
            define('DEFAULT_CUSTOMERS_STATUS_ID', '2');
        }

        // evaluate the "split street name and house number" setting available since GGX3 3.1.1.0
        $this->splitStreetHouseNumber = (ShopgateTools::isGambioVersionLowerThan('3.1.1.0'))
            ? false
            : ('true' == strtolower(ACCOUNT_SPLIT_STREET_INFORMATION));

        // evaluate the "additional address information" setting available since GGX3 3.1.1.0
        $this->useStreet2 = (ShopgateTools::isGambioVersionLowerThan('3.1.1.0'))
            ? false
            : ('true' == strtolower(ACCOUNT_ADDITIONAL_INFO));

        // fetch country
        $this->countryId = $initHelper->getShopCountryIdFromDatabase();

        //fetch language
        $language         = $initHelper->getShopLanguageFromDatabase();
        $this->languageId = !empty($language['languages_id'])
            ? $language['languages_id']
            : 2;
        $this->language   = !empty($language['directory'])
            ? $language['directory']
            : 'german';

        // fetch currency
        $currency           = $initHelper->getShopCurrencyFromDatabase();
        $this->exchangeRate = !empty($currency['value'])
            ? $currency['value']
            : 1;
        $this->currencyId   = !empty($currency['currencies_id'])
            ? $currency['currencies_id']
            : 1;
        $this->currency     = !empty($currency)
            ? $currency
            : array(
                'code'            => 'EUR',
                'symbol_left'     => '',
                'symbol_right'    => ' EUR',
                'decimal_point'   => ',',
                'thousands_point' => '.',
                'decimal_places'  => '2',
                'value'           => 1.0,
            );

        $this->zoneId = $this->config->getTaxZoneId();

        $initHelper->includeLanguageFiles($this->language, $this->gambioGXVersion);

        $initHelper->includeShopgateLanguageFile($this->language);

        return true;
    }

    /**
     * @param string           $user
     * @param string           $pass
     * @param ShopgateCustomer $customer
     *
     * @throws ShopgateLibraryException
     */
    public function registerCustomer($user, $pass, ShopgateCustomer $customer)
    {

        /** @var ShopgateCustomer $customer */
        $customer        = $customer->utf8Decode($this->config->getEncoding());
        $user            = $this->stringFromUtf8($user, $this->config->getEncoding());
        $plainPass       = $this->stringFromUtf8($pass, $this->config->getEncoding());
        $userExistResult =
            xtc_db_query(
                "SELECT count(1) AS exist FROM " . TABLE_CUSTOMERS
                . " AS c WHERE c.customers_email_address = '{$user}';"
            );
        $userCount       = xtc_db_fetch_array($userExistResult);
        $userCount       = $userCount['exist'];
        $encPass         = md5($plainPass);
        $date            = date("Y-m-d H:i:s", strtotime($customer->getRegistrationDate()));

        if ((int)$userCount >= 1) {
            throw new ShopgateLibraryException(ShopgateLibraryException::REGISTER_USER_ALREADY_EXISTS, '', true);
        }

        if (!defined('TABLE_CUSTOMERS_MEMO')) {
            define(TABLE_CUSTOMERS_MEMO, "customers_memo");
        }

        if (!defined('TABLE_CUSTOMERS_INFO')) {
            define(TABLE_CUSTOMERS_INFO, "customers_info");
        }

        $customerData = array(
            'customers_firstname'     => $customer->getFirstName(),
            'customers_dob'           => date("Y-m-d H:i:s", strtotime($customer->getBirthday())),
            'customers_lastname'      => $customer->getLastName(),
            'customers_email_address' => $customer->getMail(),
            'customers_telephone'     => $customer->getPhone(),
            'customers_newsletter'    => 0,
            "customers_gender"        => $customer->getGender(),
            'customers_password'      => $encPass,
            'customers_date_added'    => $date,
            'customers_last_modified' => $date,
            'delete_user'             => 0,
            'customers_status'        => DEFAULT_CUSTOMERS_STATUS_ID,
        );

        if (count($customFieldsToMap = $this->getCustomFieldsMap(TABLE_CUSTOMERS, $customer->getCustomFields())) > 0) {
            $customerData = array_merge($customerData, $customFieldsToMap);
        }

        xtc_db_perform(TABLE_CUSTOMERS, $customerData);
        $userId = xtc_db_insert_id();

        $userCidColumnExist = false;
        $qry                = "SHOW COLUMNS FROM `" . TABLE_CUSTOMERS . "`";
        $result             = xtc_db_query($qry);
        while ($row = xtc_db_fetch_array($result)) {
            if ($row['Field'] == 'customers_cid') {
                $userCidColumnExist = true;
                break;
            }
        }

        if ($userCidColumnExist) {
            $query = "UPDATE " . TABLE_CUSTOMERS . " SET customers_cid = {$userId} WHERE customers_id = {$userId};";
            xtc_db_query($query);
        }

        $customersInfo = array(
            'customers_info_id'                         => $userId,
            'customers_info_number_of_logons'           => 0,
            'customers_info_date_account_created'       => $date,
            'customers_info_date_account_last_modified' => $date,
        );
        xtc_db_perform(TABLE_CUSTOMERS_INFO, $customersInfo);

        $memoData = array(
            'customers_id' => $userId,
            'memo_date'    => $date,
            'memo_title'   => 'Shopgate - Account angelegt',
            'memo_text'    => 'Account wurde von Shopgate angelegt',
        );
        xtc_db_perform(TABLE_CUSTOMERS_MEMO, $memoData);

        /** @var ShopgateAddress[] $addresses */
        $addresses  = array();
        $adressList = $customer->getAddresses();

        foreach ($adressList as $key => $address) {
            foreach ($adressList as $secondKey => $secondAddress) {
                if ($address->equals($secondAddress) && $secondKey < $key) {
                    $key = $secondKey;
                }
            }
            $addresses[$key] = $address;
        }

        $defaultAddress = true;
        foreach ($addresses as $address) {
            $stateCode     = ShopgateXtcMapper::getXtcStateCode($address->getState());
            $zoneQuery     =
                xtc_db_query(
                    "SELECT z.zone_id,z.zone_name FROM " . TABLE_ZONES . " AS z WHERE z.zone_code = '{$stateCode}'"
                );
            $zoneResult    = xtc_db_fetch_array($zoneQuery);
            $countryQuery  = xtc_db_query(
                "SELECT c.countries_id FROM " . TABLE_COUNTRIES .
                " AS c WHERE c.countries_iso_code_2 ='{$address->getCountry()}'"
            );
            $countryResult = xtc_db_fetch_array($countryQuery);
            $addressData   = array(
                "customers_id"          => $userId,
                "entry_company"         => $address->getCompany(),
                "entry_zone_id"         => $zoneResult['zone_id'],
                "entry_country_id"      => $countryResult['countries_id'],
                "entry_firstname"       => $address->getFirstName(),
                "entry_lastname"        => $address->getLastName(),
                "entry_gender"          => $address->getGender(),
                "entry_postcode"        => $address->getZipcode(),
                "entry_city"            => $address->getCity(),
                "entry_state"           => $zoneResult['zone_name'],
                "address_date_added"    => "now()",
                "address_last_modified" => "now()",
            );

            if ($this->splitStreetHouseNumber) {
                $addressData['entry_street_address'] = $address->getStreetName1();
                $addressData['entry_house_number']   = $address->getStreetNumber1();
            } else {
                $addressData['entry_street_address'] = $address->getStreet1();
            }

            if ($this->useStreet2) {
                $addressData['entry_additional_info'] = $address->getStreet2();
            } else {
                $addressData['entry_street_address'] .= ('' != $address->getStreet2())
                    ? ' ' . $address->getStreet2()
                    : '';
            }

            if (count($customFieldsToMap = $this->getCustomFieldsMap(TABLE_ADDRESS_BOOK, $address->getCustomFields()))
                > 0
            ) {
                $addressData = array_merge($addressData, $customFieldsToMap);
            }

            xtc_db_perform(TABLE_ADDRESS_BOOK, $addressData);
            if ($defaultAddress) {
                $addressId = xtc_db_insert_id();
                $query     = "UPDATE " . TABLE_CUSTOMERS
                    . " as c SET customers_default_address_id = {$addressId} WHERE c.customers_id={$userId}";
                xtc_db_query($query);
                $defaultAddress = false;
            }
        }
    }

    /**
     * @param string $table
     * @param array  $customFields
     * @param string $fieldPrefix
     * @param array  $blacklist
     *
     * @return array
     */
    private function getCustomFieldsMap($table, array $customFields, $fieldPrefix = '', array $blacklist = array())
    {
        $result    = array();
        $qrySuffix = array();

        $qry = "SHOW COLUMNS FROM " . $table;

        if (!empty($fieldPrefix)) {
            $qrySuffix[] = "`Field` LIKE '{$fieldPrefix}_%'";
        }

        if (count($blacklist) > 0) {
            $formattedPrefix = trim($fieldPrefix, '_') . '_';
            $excludeFields   = "'" . $formattedPrefix . implode("','{$formattedPrefix}", $blacklist) . "'";
            $qrySuffix[]     = "`Field` NOT IN ({$excludeFields})";
        }
        $qry       .= !empty($qrySuffix)
            ? ' WHERE ' . implode(' AND ', $qrySuffix)
            : '';
        $qryResult = xtc_db_query($qry);

        $orderFieldList = array();

        while ($field = xtc_db_fetch_array($qryResult)) {
            $orderFieldList[$field['Field']] = 1;
        }

        /** @var ShopgateOrderCustomField $customField */
        foreach ($customFields as $customField) {
            if (!empty($orderFieldList[$customField->getInternalFieldName()])) {
                $result[$customField->getInternalFieldName()] = $customField->getValue();
            }
        }

        return $result;
    }

    public function createPluginInfo()
    {
        $aInfo = array();

        if (file_exists(dirname(__FILE__) . '/../release_info.php')) {
            include(dirname(__FILE__) . '/../release_info.php');
        }

        if (isset($gx_version)) {
            $aInfo['shop_version'] = $gx_version;
        }

        $aInfo['module_is_installed'] =
            defined('MODULE_PAYMENT_INSTALLED') && strpos(MODULE_PAYMENT_INSTALLED, 'shopgate.php') !== false;
        $aInfo['module_is_active']    =
            defined('MODULE_PAYMENT_SHOPGATE_STATUS') && MODULE_PAYMENT_SHOPGATE_STATUS === 'True';

        return $aInfo;
    }

    public function checkCart(ShopgateCart $cart)
    {
        $result = array();

        $cartItemModel = new ShopgateItemCartModel($this->languageId);
        $locationModel = new ShopgateLocationModel($this->config);
        $customerModel = new ShopgateCustomerModel($this->config, $this->languageId);

        $result['shipping_methods'] = $this->getShipping($cart, $cartItemModel, $locationModel);
        $result['currency']         = $this->config->getCurrency();
        $result['items']            = $this->checkCartItems($cart, $cartItemModel, $locationModel);
        $result['external_coupons'] = $this->checkCoupons($cart, $customerModel);
        $result['customer']         = $this->checkCartCustomer($cart, $customerModel);

        return $result;
    }

    /**
     * check the validity of coupons
     *
     * @param ShopgateCart          $cart
     * @param ShopgateCustomerModel $customerModel
     *
     * @return ShopgateExternalCoupon[]
     */
    public function checkCoupons(ShopgateCart $cart, ShopgateCustomerModel $customerModel)
    {
        $orderAmount     = ShopgateCouponHelper::getCompleteAmount($cart);
        $customer        = $customerModel->getCustomerById($cart->getExternalCustomerId());
        $customerGroupId =
            (isset($customer['customers_status']))
                ? $customer['customers_status']
                : DEFAULT_CUSTOMERS_STATUS_ID_GUEST;
        $customerGroup   = $customerModel->getCustomerGroup($customerGroupId);
        $couponModel     =
            new ShopgateCouponModel(
                $this->config, $this->languageId, $this->language, $this->currency, $this->countryId
            );

        $coupons = $couponModel->removeCartRuleCoupons($cart->getExternalCoupons(), $returnEmptyCoupon);
        $coupons = $couponModel->validateCoupons($coupons, $cart, $orderAmount, $customerGroupId);
        $coupons = $couponModel->addCartRuleCoupons($coupons, $customerGroup, $orderAmount, $returnEmptyCoupon);

        return $coupons;
    }

    /**
     * get the customer group data from the cart by email or customers uid
     *
     * @param ShopgateCart          $cart
     * @param ShopgateCustomerModel $customerModel
     *
     * @return ShopgateCartCustomer
     */
    public function checkCartCustomer(ShopgateCart $cart, ShopgateCustomerModel $customerModel)
    {
        $customersUid        = $cart->getCustomerNumber();
        $groupId             = $cart->getExternalCustomerGroupId();
        $email               = $cart->getMail();
        $sgCustomer          = new ShopgateCartCustomer();
        $sgCartCustomerGroup = new ShopgateCartCustomerGroup();
        $customerGroupId     = "";

        if (!empty($groupId) && !empty($email)) {
            if ($customerModel->customerHasGroup($groupId, $email)) {
                $customerGroupId = $groupId;
            }
        }

        $customerResult = $customerModel->getCustomerByEmail($email);
        $customerData   = xtc_db_fetch_array($customerResult);

        if (!empty($customerData['customers_status_id'])) {
            $customerGroupId = $customerData['customers_status_id'];
        } elseif (!empty($customersUid)) {
            $customerGroup   = $customerModel->getGroupToCustomer($customersUid);
            $customerGroupId = $customerGroup['id'];
        } else {
            $address = $cart->getInvoiceAddress();
            if (!empty($address)) {
                $email           = $address->getMail();
                $customerResult  = $customerModel->getCustomerByEmail($email);
                $customerData    = xtc_db_fetch_array($customerResult);
                $customerGroupId = $customerData['customers_status_id'];
            }
        }

        if (empty($customerGroupId)) {
            $customerGroupId = DEFAULT_CUSTOMERS_STATUS_ID_GUEST;
        }

        $sgCartCustomerGroup->setId($customerGroupId);
        $sgCustomer->setCustomerGroups(array($sgCartCustomerGroup));

        return $sgCustomer;
    }

    /**
     * gather shipping data
     *
     * @param ShopgateCart          $shopgateCart
     * @param ShopgateItemCartModel $cartItemModel
     * @param ShopgateLocationModel $locationModel
     *
     * @return array
     */
    private function getShipping(
        ShopgateCart $shopgateCart,
        ShopgateItemCartModel $cartItemModel,
        ShopgateLocationModel $locationModel
    ) {
        /** @var xtcPrice_ORIGIN $xtPrice */
        global $xtPrice, $total_count, $shipping_weight, $total_weight, $shipping_num_boxes, $cart, $sendto, $billto;

        if (!defined('MODULE_SHIPPING_INSTALLED') || MODULE_SHIPPING_INSTALLED == "") {
            return array();
        }

        /** @var shoppingCart_ORIGIN $cart */
        $cart         = new shoppingCart();
        $cart->weight = $cartItemModel->getProductsWeight($shopgateCart->getItems());

        $resultShippingMethods = array();
        $total_count           = count($shopgateCart->getItems());
        $total_weight          = $cart->weight;
        $shipping_weight       = $cart->weight;
        $shipping_num_boxes    = 1;
        $completeAmount        = 0;

        $cartContents = array();
        foreach ($shopgateCart->getItems() as $item) {
            $productId         = $cartItemModel->getProductsIdFromCartItem($item);
            $completeAmount    += $item->getUnitAmount() * $item->getQuantity();
            $internalOrderInfo = json_decode(stripslashes($item->getInternalOrderInfo()), true);
            $attributes        = $item->getAttributes();

            if (!empty($attributes)) {
                $itemNumber = $internalOrderInfo['base_item_number'];
                $sgOptions  = array();
                for ($i = 1; $i <= 100 && isset($internalOrderInfo["attribute_$i"]); $i++) {
                    $option = $internalOrderInfo["attribute_$i"];
                    if (!is_array($option)) {
                        continue;
                    }

                    foreach ($option as $optionIds) {
                        $values                              = $xtPrice->xtcGetOptionPrice(
                            $productId,
                            $optionIds["options_id"],
                            $optionIds["options_values_id"]
                        );
                        $completeAmount                      += $values["price"];
                        $sgOptions[$optionIds['options_id']] = $optionIds['options_values_id'];
                    }
                }
            } else {
                $sgOptions  = '';
                $itemNumber = $item->getItemNumber();
            }

            $cart->add_cart($itemNumber, $item->getQuantity(), $sgOptions);
            $cartContents[$itemNumber] = array('qty' => $item->getQuantity());
        }

        $_SESSION['actual_content'] = $cartContents;
        $cart->contents             = $cartContents;
        $_SESSION['cart']           = $cart;
        $sgDeliverAddress           = $shopgateCart->getDeliveryAddress();

        if (!empty($sgDeliverAddress)) {
            $country = $locationModel->getCountryByIso2Name($sgDeliverAddress->getCountry());
            $zone    = $locationModel->getZoneByCountryId($country["countries_id"]);

            $sendto = array(
                "firstname"         => $sgDeliverAddress->getFirstName(),
                "lastname"          => $sgDeliverAddress->getLastName(),
                "company"           => $sgDeliverAddress->getCompany(),
                "street_address"    => $sgDeliverAddress->getStreet1(),
                "suburb"            => "",
                "postcode"          => $sgDeliverAddress->getZipcode(),
                "city"              => $sgDeliverAddress->getCity(),
                "zone_id"           => $zone["zone_id"],
                "zone_name"         => $zone["zone_name"],
                "country"           => array(
                    "id"         => $country["countries_id"],
                    "title"      => $country['countries_name'],
                    "iso_code_2" => $country["countries_iso_code_2"],
                    "iso_code_3" => $country["countries_iso_code_3"],
                ),
                "country_id"        => $country["countries_id"],
                "address_format_id" => "",
            );

            $_SESSION['delivery_zone'] = $country['countries_iso_code_2'];
        }

        $sgInvoiceAddress = $shopgateCart->getInvoiceAddress();

        if (empty($sgInvoiceAddress)) {
            $billto = $sendto;
        } else {
            $country = $locationModel->getCountryByIso2Name(
                $sgInvoiceAddress->getCountry()
            );
            $zone    = $locationModel->getZoneByCountryId(
                $country["countries_id"]
            );

            $billto = array(
                "firstname"         => $sgInvoiceAddress->getFirstName(),
                "lastname"          => $sgInvoiceAddress->getLastName(),
                "company"           => $sgInvoiceAddress->getCompany(),
                "street_address"    => $sgInvoiceAddress->getStreet1(),
                "suburb"            => "",
                "postcode"          => $sgInvoiceAddress->getZipcode(),
                "city"              => $sgInvoiceAddress->getCity(),
                "zone_id"           => $zone["zone_id"],
                "zone_name"         => $zone["zone_name"],
                "country"           => array(
                    "id"         => $country["countries_id"],
                    "title"      => $country['countries_name'],
                    "iso_code_2" => $country["countries_iso_code_2"],
                    "iso_code_3" => $country["countries_iso_code_3"],
                ),
                "country_id"        => $country["countries_id"],
                "address_format_id" => "",
            );
        }

        if (defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING')
            && (MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true')
        ) {
            $pass = false;
            switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
                case 'national':
                    if (isset($country["countries_id"]) && $country["countries_id"] == STORE_COUNTRY) {
                        $pass = true;
                    }
                    break;
                case 'international':
                    if (isset($country["countries_id"]) && $country["countries_id"] != STORE_COUNTRY) {
                        $pass = true;
                    }
                    break;
                case 'both':
                    $pass = true;
                    break;
            }
            $free_shipping        = false;
            $t_shipping_free_over = (double)MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER;
            if ((int)MODULE_ORDER_TOTAL_SHIPPING_TAX_CLASS > 0) {
                $t_shipping_free_over =
                    $t_shipping_free_over / (1 + $xtPrice->TAX[MODULE_ORDER_TOTAL_SHIPPING_TAX_CLASS] / 100);
            }
            if (($pass == true) && ($completeAmount >= $xtPrice->xtcFormat($t_shipping_free_over, false, 0, true))) {
                $free_shipping = true;
                include(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/order_total/ot_shipping.php');
            }
        } else {
            $free_shipping = false;
        }

        include_once(rtrim(DIR_WS_CLASSES, "/") . "/order.php");
        global $order;
        $order           = new order();
        $order->customer = $sendto;
        $order->delivery = $sendto;
        $order->billing  = $billto;

        $shipping        = new shipping;
        $shippingModules = $shipping->quote();
        // if shipping is free all other shipping methods will be ignored
        if ($free_shipping) {
            $sgShippingMethod = new ShopgateShippingMethod();
            $sgShippingMethod->setDescription(
                "Total amount over " . MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER . " is free"
            );
            $sgShippingMethod->setTitle("Free Shipping");
            $sgShippingMethod->setAmount(0);

            return array($sgShippingMethod);
        }

        foreach ($shippingModules as $shippingModule) {
            //we dont support usps as shopgate plugin shipping method
            //also on error continue
            if (strpos($shippingModule['module'], "United States Postal Service") !== false
                || !empty($shippingModule['error'])
            ) {
                continue;
            }

            if (!empty($shippingModule["methods"]) && is_array($shippingModule["methods"])) {
                foreach ($shippingModule["methods"] as $method) {
                    $sgShippingMethod = new ShopgateShippingMethod();
                    $sgShippingMethod->setId($shippingModule["id"]);
                    $sgShippingMethod->setTitle($shippingModule["module"]);
                    $sgShippingMethod->setTaxPercent($shippingModule["tax"]);

                    if (isset($shippingModule["tax"]) && !empty($shippingModule["tax"])) {
                        $sgShippingMethod->setTaxClass($locationModel->getTaxClassByValue($shippingModule["tax"]));
                    }

                    $sgShippingMethod->setId($shippingModule["id"] . '_' . $method['id']);
                    $sgShippingMethod->setTitle($shippingModule["module"] . ' - ' . $method['title']);

                    $cost = $method["cost"];

                    if (isset($shippingModule["tax"]) && !empty($shippingModule["tax"])) {
                        $costWithTax = $this->formatPriceNumber($cost * (1 + ($shippingModule["tax"] / 100)), 2);
                        $sgShippingMethod->setAmountWithTax((float)$costWithTax);
                        $sgShippingMethod->setAmount($cost);
                    } else {
                        $sgShippingMethod->setAmountWithTax($cost);
                    }

                    $resultShippingMethods[] = $sgShippingMethod;
                }
            }
        }

        return $resultShippingMethods;
    }

    /**
     * @param ShopgateCart          $cart
     * @param ShopgateItemCartModel $cartItemModel
     * @param ShopgateLocationModel $locationModel
     *
     * @return array
     * @throws ShopgateLibraryException
     */
    private function checkCartItems(
        ShopgateCart $cart,
        ShopgateItemCartModel $cartItemModel,
        ShopgateLocationModel $locationModel
    ) {
        $resultArr  = array();
        $cartItems  = $cart->getItems();
        $priceModel = new ShopgatePriceModel($this->config, $this->languageId, $this->exchangeRate);

        /* @var $cartItem ShopgateOrderItem */
        foreach ($cartItems as $cartItem) {
            $sgOrderItem = new ShopgateCartItem();

            /**
             * load product from database
             */
            $productItem = $cartItemModel->getCartItemFromDatabase($cartItem);

            try {
                $useStock    = true;
                $stockHelper = new ShopgateStockHelper();
                $quantity    = $stockHelper->getStockForOrderItem($cartItem, $productItem);
                if (is_null($quantity)) {
                    $useStock = false;
                    $quantity = $stockHelper->getStockForOrderItem($cartItem, $productItem, true);
                }
            } catch (ShopgateLibraryException $e) {
                $sgOrderItem->setError($e->getCode());
                array_push($resultArr, $sgOrderItem);
                continue;
            }

            /**
             * init tax rate for product
             */
            $tax_rate = $locationModel->getTaxRateToProduct(
                $productItem['products_tax_class_id'],
                false,
                $this->gambioGXVersion,
                $this->countryId,
                $this->zoneId
            );

            /**
             * init price model
             */
            $productDiscount = 0;
            $oldPrice        = 0;
            $price           = 0;
            $priceModel->getPriceToItem($productItem, $tax_rate, $price, $oldPrice, $productDiscount);

            $sgOrderItem->setItemNumber($cartItem->getItemNumber());
            if ($useStock) {
                $sgOrderItem->setStockQuantity($quantity);
                $sgOrderItem->setQtyBuyable(min($quantity, (int)$productItem['products_quantity']));
            } else {
                $sgOrderItem->setStockQuantity($cartItem->getQuantity());
                $sgOrderItem->setQtyBuyable($cartItem->getQuantity());
            }
            $sgOrderItem->setUnitAmountWithTax($this->formatPriceNumber($price));
            $sgOrderItem->setUnitAmount($this->formatPriceNumber($price / (1 + ($tax_rate / 100))));

            if (empty($productItem)) {
                /**
                 * cart item not found
                 */
                $sgOrderItem->setError(ShopgateLibraryException::CART_ITEM_PRODUCT_NOT_FOUND);
                array_push($resultArr, $sgOrderItem);
                continue;
            } else {
                if (!$useStock) {
                    /**
                     * always salable because no stock check or order without check
                     */
                    $sgOrderItem->setIsBuyable(true);
                } else {
                    if ($productItem['products_status'] == 1) {
                        if ($quantity < $cartItem->getQuantity()) {
                            /**
                             * requested quantity not in stock
                             */
                            $sgOrderItem->setError(
                                ShopgateLibraryException::CART_ITEM_REQUESTED_QUANTITY_NOT_AVAILABLE
                            );
                            $sgOrderItem->setIsBuyable(true);
                        } else {
                            /**
                             * requested quantity in stock
                             */
                            $sgOrderItem->setIsBuyable(true);
                        }
                    } else {
                        /**
                         * not in stock
                         */
                        $sgOrderItem->setError(ShopgateLibraryException::CART_ITEM_OUT_OF_STOCK);
                        $sgOrderItem->setIsBuyable(false);
                    }
                }
            }

            array_push($resultArr, $sgOrderItem);
        }

        return $resultArr;
    }

    public function cron($jobname, $params, &$message, &$errorcount)
    {
        switch ($jobname) {
            case 'set_shipping_completed':
                $this->cronSetOrdersShippingCompleted($message, $errorcount);
                break;
            case 'cancel_orders':
                $this->cronSetOrdersCancellations($message, $errorcount);
                break;
            default:
                throw new ShopgateLibraryException(
                    ShopgateLibraryException::PLUGIN_CRON_UNSUPPORTED_JOB, 'Job name: "' . $jobname . '"', true
                );
        }
    }

    /**
     * Marks shipped orders as "shipped" at Shopgate.
     *
     * This will find all orders that are marked "shipped" in the shop system but not at Shopgate yet and marks them
     * "shipped" at Shopgate via Shopgate Merchant API.
     *
     * @param string $message    Process log will be appended to this reference.
     * @param int    $errorCount This reference gets incremented on errors.
     */
    protected function cronSetOrdersShippingCompleted(&$message, &$errorCount)
    {
        $query
                = "SELECT `sgo`.`orders_id`, `sgo`.`shopgate_order_number` " .
            "FROM `" . TABLE_ORDERS_SHOPGATE_ORDER . "` sgo " .
            "INNER JOIN `" . TABLE_ORDERS . "` xto ON (`xto`.`orders_id` = `sgo`.`orders_id`) " .
            "INNER JOIN `" . TABLE_LANGUAGES . "` xtl ON (`xtl`.`directory` = `xto`.`language`) " .
            "WHERE `sgo`.`is_sent_to_shopgate` = 0 " .
            "AND `xto`.`orders_status` = " . xtc_db_input($this->config->getOrderStatusShipped()) . " " .
            "AND `xtl`.`code` = '" . xtc_db_input($this->config->getLanguage()) . "';";
        $result = xtc_db_query($query);

        if (empty($result)) {
            return;
        }

        while ($shopgateOrder = xtc_db_fetch_array($result)) {
            if (!$this->setOrderShippingCompleted(
                $shopgateOrder['shopgate_order_number'],
                $shopgateOrder['orders_id'],
                $this->merchantApi
            )
            ) {
                $errorCount++;
                $message .= 'Shopgate order number "' . $shopgateOrder['shopgate_order_number'] . '": error' . "\n";
            }
        }
    }

    /**
     * Sets the order status of a Shopgate order to "shipped" via Shopgate Merchant API
     *
     * @param string                       $shopgateOrderNumber The number of the order at Shopgate.
     * @param int                          $orderId             The ID of the order in the shop system.
     * @param ShopgateMerchantApiInterface $merchantApi         The SMA object to use for the request.
     *
     * @return bool true on success, false on failure.
     */
    protected function setOrderShippingCompleted(
        $shopgateOrderNumber,
        $orderId,
        ShopgateMerchantApiInterface &$merchantApi
    ) {
        $success = false;

        // These are expected and should not be added to error count:
        $ignoreCodes = array(
            ShopgateMerchantApiException::ORDER_ALREADY_COMPLETED,
            ShopgateMerchantApiException::ORDER_SHIPPING_STATUS_ALREADY_COMPLETED,
        );

        try {
            $merchantApi->setOrderShippingCompleted($shopgateOrderNumber);

            $statusArr = array(
                "orders_id"         => $orderId,
                "orders_status_id"  => $this->config->getOrderStatusShipped(),
                "date_added"        => date('Y-m-d H:i:s'),
                "customer_notified" => 1,
                "comments"          => "[Shopgate] Bestellung wurde bei Shopgate als versendet markiert",
            );

            xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $statusArr);

            $success = true;
        } catch (ShopgateLibraryException $e) {
            $response = $this->stringFromUtf8($e->getAdditionalInformation(), $this->config->getEncoding());

            $statusArr = array(
                "orders_id"         => $orderId,
                "orders_status_id"  => $this->config->getOrderStatusShipped(),
                "date_added"        => date('Y-m-d H:i:s'),
                "customer_notified" => 0,
                "comments"          => "[Shopgate] Ein Fehler ist im Shopgate Modul aufgetreten ({$e->getCode()}): {$response}",
            );

            xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $statusArr);
        } catch (ShopgateMerchantApiException $e) {
            $response = $this->stringFromUtf8($e->getMessage(), $this->config->getEncoding());

            $statusArr = array(
                "orders_id"         => $orderId,
                "orders_status_id"  => $this->config->getOrderStatusShipped(),
                "date_added"        => date('Y-m-d H:i:s'),
                "customer_notified" => 0,
                "comments"          => "[Shopgate] Ein Fehler ist bei Shopgate aufgetreten ({$e->getCode()}): {$response}",
            );

            xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $statusArr);

            $success = (in_array($e->getCode(), $ignoreCodes))
                ? true
                : false;
        } catch (Exception $e) {
            $response = $this->stringFromUtf8($e->getMessage(), $this->config->getEncoding());

            $statusArr = array(
                "orders_id"         => $orderId,
                "orders_status_id"  => $this->config->getOrderStatusShipped(),
                "date_added"        => date('Y-m-d H:i:s'),
                "customer_notified" => 0,
                "comments"          => "[Shopgate] Ein unbekannter Fehler ist aufgetreten ({$e->getCode()}): {$response}",
            );

            xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $statusArr);
        }

        // Update shopgate order on success
        if ($success) {
            $qry = 'UPDATE `' . TABLE_ORDERS_SHOPGATE_ORDER
                . '` SET `is_sent_to_shopgate` = 1 WHERE `shopgate_order_number` = ' . $shopgateOrderNumber . ';';
            xtc_db_query($qry);
        }

        return $success;
    }

    /**
     * @param string $message
     * @param int    $errorCount
     *
     * @return bool
     */
    protected function cronSetOrdersCancellations(&$message, &$errorCount)
    {
        $query = "SELECT `sgo`.`orders_id`, `sgo`.`shopgate_order_number`" .
            " FROM `" . TABLE_ORDERS_SHOPGATE_ORDER . "` sgo" .
            " INNER JOIN `" . TABLE_ORDERS . "` xto ON (`xto`.`orders_id` = `sgo`.`orders_id`) " .
            " INNER JOIN `" . TABLE_LANGUAGES . "` xtl ON (`xtl`.`directory` = `xto`.`language`) " .
            " WHERE `sgo`.`is_cancelled` = 0" .
            " AND `xto`.`orders_status` = " . xtc_db_input($this->config->getOrderStatusCanceled()) .
            " AND `xtl`.`code` = '" . xtc_db_input($this->config->getLanguage()) . "';";

        $result = xtc_db_query($query);
        while ($shopgateOrder = xtc_db_fetch_array($result)) {
            try {
                $this->sendOrderCancellation($shopgateOrder['shopgate_order_number'], $this->merchantApi);
                $message .= "full cancellation sent for shopgate order: "
                    . $shopgateOrder['shopgate_order_number'] . "\n";
            } catch (Exception $e) {
                $errorCount++;
                $message .= "Shopgate order number " . $shopgateOrder['shopgate_order_number']
                    . " error: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * send a cancellation request from an order to shopgate
     *
     * @param int                          $shopgateOrderNumber
     * @param ShopgateMerchantApiInterface $merchantApi
     *
     * @throws ShopgateMerchantApiException|Exception
     */
    protected function sendOrderCancellation($shopgateOrderNumber, ShopgateMerchantApiInterface &$merchantApi)
    {
        try {
            $merchantApi->cancelOrder($shopgateOrderNumber, true);
        } catch (ShopgateMerchantApiException $e) {
            if ($e->getCode() != ShopgateMerchantApiException::ORDER_ALREADY_CANCELLED) {
                throw $e;
            }
        }

        $updateQuery = "UPDATE `" . TABLE_ORDERS_SHOPGATE_ORDER
            . "` SET `is_cancelled` = 1 WHERE `shopgate_order_number` = '{$shopgateOrderNumber}'";
        $this->log($updateQuery, ShopgateLogger::LOGTYPE_DEBUG);
        xtc_db_query($updateQuery);
    }

    public function getCustomer($user, $pass)
    {
        $customerModel = new ShopgateCustomerModel($this->config, $this->languageId);
        // save the UTF-8 version for logging etc.
        $userUtf8 = $user;

        // decode the parameters if necessary to make them work with xtc_* functions
        $user = $this->stringFromUtf8($user, $this->config->getEncoding());
        $pass = $this->stringFromUtf8($pass, $this->config->getEncoding());

        $customerResult = $customerModel->getCustomerByEmail($user);

        if (empty($customerResult)) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_WRONG_USERNAME_OR_PASSWORD, 'User: ' . $userUtf8
            );
        }

        // password's correct?
        $customerData = xtc_db_fetch_array($customerResult);
        if (!xtc_validate_password($pass, $customerData['customers_password'])) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_WRONG_USERNAME_OR_PASSWORD, 'User: ' . $userUtf8
            );
        }

        $addressResult = $customerModel->getCustomerAdressData($customerData['customers_id']);
        if (empty($addressResult)) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_NO_ADDRESSES_FOUND, 'User: ' . $userUtf8
            );
        }

        $addresses = array();
        while ($addressData = xtc_db_fetch_array($addressResult)) {
            try {
                $stateCode = ShopgateXtcMapper::getShopgateStateCode(
                    $addressData["countries_iso_code_2"],
                    $addressData["zone_code"]
                );
            } catch (ShopgateLibraryException $e) {
                // if state code can't be mapped to ISO use xtc3 state code
                $stateCode = $addressData['zone_code'];
            }

            $address = new ShopgateAddress();
            $address->setId($addressData['address_book_id']);
            $address->setAddressType(ShopgateAddress::BOTH); // xtc3 doesn't make a difference
            $address->setGender($addressData["entry_gender"]);
            $address->setFirstName($addressData["entry_firstname"]);
            $address->setLastName($addressData["entry_lastname"]);
            $address->setCompany($addressData["entry_company"]);
            $address->setZipcode($addressData["entry_postcode"]);
            $address->setCity($addressData["entry_city"]);
            $address->setCountry($addressData["countries_iso_code_2"]);
            $address->setState($stateCode);

            $address->setStreet1(
                $addressData['entry_street_address'] .
                (
                (($this->splitStreetHouseNumber) && ('' != $addressData['entry_house_number']))
                    ? ' ' . $addressData['entry_house_number']
                    : ''
                )
            );

            if ($this->useStreet2) {
                $address->setStreet2($addressData['entry_additional_info']);
            }

            // put default address in front, append the others
            if ($address->getId() == $customerData['customers_default_address_id']) {
                array_unshift($addresses, $address);
            } else {
                $addresses[] = $address;
            }
        }

        $customer = new ShopgateCustomer();
        $customer->setCustomerId($customerData["customers_id"]);
        $customer->setCustomerNumber($customerData["customers_cid"]);
        $customer->setCustomerGroup($customerData['customers_status_name']);
        $customer->setCustomerGroupId($customerData['customers_status_id']);
        $customer->setGender($customerData["customers_gender"]);
        $customer->setFirstName($customerData["customers_firstname"]);
        $customer->setLastName($customerData["customers_lastname"]);
        $customer->setBirthday($customerData["customers_birthday"]);
        $customer->setPhone($customerData["customers_telephone"]);
        $customer->setMail($customerData["customers_email_address"]);
        $customer->setAddresses($addresses);
        $customer->setCustomerToken($this->getCustomerToken($customerData));

        try {
            // utf-8 encode the values recursively
            $customer = $customer->utf8Encode($this->config->getEncoding());
        } catch (ShopgateLibraryException $e) {
            // don't abort here
        }

        // a customer can be assigned to one customer group only in Gambio
        $customerGroup       = $customerModel->getGroupToCustomer($customerData['customers_id']);
        $customerGroupResult = array();

        if (!empty($customerGroup)) {
            $sgGroup = new ShopgateCustomerGroup();
            $sgGroup->setId($customerGroup['id']);
            $sgGroup->setName($customerGroup['name']);
            $customerGroupResult[] = $sgGroup;
        }
        $customer->setCustomerGroups($customerGroupResult);

        return $customer;
    }

    /**
     * @param array $customerData
     *
     * @return bool|string
     */
    private function getCustomerToken($customerData)
    {
        $customerModel = new ShopgateCustomerModel($this->config, $this->languageId);

        if (!$customerModel->hasCustomerToken($customerData["customers_id"])) {
            return $customerModel->insertToken(
                $customerData["customers_id"],
                $customerData["customers_email_address"]
            );
        }

        return $customerModel->getCustomerToken($customerData["customers_id"]);
    }

    public function addOrder(ShopgateOrder $order)
    {
        // save UTF-8 payment info (to build proper json)
        $paymentInfoUtf8 = $order->getPaymentInfos();
        $couponModel     = new ShopgateCouponModel(
            $this->config, $this->languageId, $this->language, $this->currency, $this->countryId
        );
        $this->log('start add_order()', ShopgateLogger::LOGTYPE_DEBUG);

        // data needs to be utf-8 decoded for äöüß and the like to be saved correctly
        /** @var ShopgateOrder $order */
        $order = $order->utf8Decode($this->config->getEncoding());

        $this->log('db: duplicate_order', ShopgateLogger::LOGTYPE_DEBUG);

        // check that the order is not imported already
        $qry
                 = "
            SELECT
            o.*,
            so.shopgate_order_number
            FROM " . TABLE_ORDERS . " o
            INNER JOIN " . TABLE_ORDERS_SHOPGATE_ORDER . " so ON (so.orders_id = o.orders_id)
            WHERE so.shopgate_order_number = '{$order->getOrderNumber()}'
        ";
        $result  = xtc_db_query($qry);
        $dbOrder = xtc_db_fetch_array($result);

        if (!empty($dbOrder)) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DUPLICATE_ORDER, 'external_order_number: ' . $dbOrder["orders_id"],
                true
            );
        }

        // retrieve address information
        $delivery = $order->getDeliveryAddress();
        $invoice  = $order->getInvoiceAddress();

        // find customer
        $customerId = $order->getExternalCustomerId();

        $shopCustomer = array();
        if (!empty($customerId)) {
            $this->log('db: customer', ShopgateLogger::LOGTYPE_DEBUG);
            $result       = xtc_db_query("SELECT * FROM " . TABLE_CUSTOMERS . " WHERE customers_id = '{$customerId}'");
            $shopCustomer = xtc_db_fetch_array($result);
        }
        if (empty($shopCustomer)) {
            $this->log('create Guest User', ShopgateLogger::LOGTYPE_DEBUG);
            $shopCustomer = $this->createGuestUser($order);
        }

        // get customers address
        $qry
                          = "
            SELECT
                *
            FROM `" . TABLE_ADDRESS_BOOK . "` AS `ab`
            WHERE `ab`.`customers_id` = '{$shopCustomer['customers_id']}'"
            . (!empty($shopCustomer['customers_default_address_id'])
                ? ("
                AND `ab`.`address_book_id` = '{$shopCustomer['customers_default_address_id']}'")
                : "") . "
        ;";
        $qryResult        = xtc_db_query($qry);
        $customersAddress = xtc_db_fetch_array($qryResult);
        // get address format
        if (!empty($customersAddress)) {
            $addressFormatCustomer = $this->getAddressFormatId(null, $customersAddress['entry_country_id']);
        } else {
            $customersAddress = array(
                'entry_gender'         => $shopCustomer['customers_gender'],
                'entry_company'        => '',
                'entry_firstname'      => $shopCustomer['customers_firstname'],
                'entry_lastname'       => $shopCustomer['customers_lastname'],
                'entry_street_address' => '',
                'entry_suburb'         => '',
                'entry_postcode'       => '',
                'entry_city'           => '',
                'entry_state'          => '',
                'entry_country_id'     => '',
                'entry_zone_id'        => '',
            );

            if (!ShopgateTools::isGambioVersionLowerThan('3.1.1.0')) {
                $customersAddress['entry_house_number']    = '';
                $customersAddress['entry_additional_info'] = '';
            }
        }
        $addressFormatDelivery = $this->getAddressFormatId($delivery->getCountry());
        $addressFormatInvoice  = $this->getAddressFormatId($invoice->getCountry());
        if (empty($addressFormatCustomer)) {
            $addressFormatCustomer = $addressFormatInvoice;
        }
        if (empty($addressFormatCustomer)) {
            $addressFormatCustomer = $addressFormatDelivery;
        }

        $this->log('db: customer_status', ShopgateLogger::LOGTYPE_DEBUG);

        $result          = xtc_db_query(
            "SELECT * FROM " . TABLE_CUSTOMERS_STATUS
            . " WHERE language_id = '{$this->languageId}' AND customers_status_id = '{$shopCustomer["customers_status"]}'"
        );
        $customersStatus = xtc_db_fetch_array($result);
        if (empty($customersStatus)) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_NO_CUSTOMER_GROUP_FOUND, print_r($shopCustomer, true)
            );
        }

        $this->log('before ShopgateMapper', ShopgateLogger::LOGTYPE_DEBUG);

        // map state codes (called "zone id" in shopsystem)
        $customersStateCode = $customersAddress['entry_state'];
        $invoiceStateCode   = $invoice->getState();
        $deliveryStateCode  = $delivery->getState();

        $this->log('db: countries', ShopgateLogger::LOGTYPE_DEBUG);

        $result           = xtc_db_query(
            "SELECT * FROM " . TABLE_COUNTRIES . " WHERE countries_id = '{$customersAddress['entry_country_id']}'"
        );
        $customersCountry = xtc_db_fetch_array($result);
        $result           = xtc_db_query(
            "SELECT * FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = '{$delivery->getCountry()}'"
        );
        $deliveryCountry  = xtc_db_fetch_array($result);
        $result           = xtc_db_query(
            "SELECT * FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = '{$invoice->getCountry()}'"
        );
        $invoiceCountry   = xtc_db_fetch_array($result);
        if (empty($customersCountry)) {
            $customersCountry = $invoiceCountry;
        }
        if (empty($customersCountry)) {
            $customersCountry = $deliveryCountry;
        }

        ///////////////////////////////////////////////////////////////////////
        // Save order
        ///////////////////////////////////////////////////////////////////////

        $orderData                              = array();
        $orderData["customers_id"]              = $shopCustomer["customers_id"];
        $orderData["customers_cid"]             = $shopCustomer["customers_cid"];
        $orderData["customers_vat_id"]          = $shopCustomer["customers_vat_id"];
        $orderData["customers_status"]          = $customersStatus["customers_status_id"];
        $orderData["customers_status_name"]     = $customersStatus["customers_status_name"];
        $orderData["customers_status_image"]    = $customersStatus["customers_status_image"];
        $orderData["customers_status_discount"] = 0;

        $orderData["customers_name"]           =
            $customersAddress['entry_firstname'] . " " . $customersAddress['entry_lastname'];
        $orderData["customers_firstname"]      = $customersAddress['entry_firstname'];
        $orderData["customers_lastname"]       = $customersAddress['entry_lastname'];
        $orderData["customers_company"]        = $customersAddress['entry_company'];
        $orderData["customers_street_address"] = $customersAddress['entry_street_address'];
        if (!ShopgateTools::isGambioVersionLowerThan('3.1.1.0')) {
            $orderData["customers_house_number"]    = $customersAddress['entry_house_number'];
            $orderData["customers_additional_info"] = $customersAddress['entry_additional_info'];
        }
        $orderData["customers_suburb"]            = $customersAddress['entry_suburb'];
        $orderData["customers_city"]              = $customersAddress['entry_city'];
        $orderData["customers_postcode"]          = $customersAddress['entry_postcode'];
        $orderData["customers_state"]             = $customersStateCode;
        $orderData["customers_country"]           = $customersCountry['countries_name'];
        $orderData["customers_telephone"]         = $shopCustomer['customers_telephone'];
        $orderData["customers_email_address"]     = $shopCustomer['customers_email_address'];
        $orderData["customers_address_format_id"] = $addressFormatCustomer;

        $orderData["delivery_name"]               = $delivery->getFirstName() . " " . $delivery->getLastName();
        $orderData["delivery_firstname"]          = $delivery->getFirstName();
        $orderData["delivery_lastname"]           = $delivery->getLastName();
        $orderData["delivery_company"]            = $delivery->getCompany();
        $orderData["delivery_street_address"]     = $delivery->getStreet1();
        $orderData["delivery_suburb"]             = "";
        $orderData["delivery_city"]               = $delivery->getCity();
        $orderData["delivery_postcode"]           = $delivery->getZipcode();
        $orderData["delivery_state"]              = $deliveryStateCode;
        $orderData["delivery_country"]            = $deliveryCountry["countries_name"];
        $orderData["delivery_country_iso_code_2"] = $delivery->getCountry();
        $orderData["delivery_address_format_id"]  = $addressFormatDelivery;

        if ($this->splitStreetHouseNumber) {
            $orderData['delivery_street_address'] = $delivery->getStreetName1();
            $orderData['delivery_house_number']   = $delivery->getStreetNumber1();
        } else {
            $orderData['delivery_street_address'] = $delivery->getStreet1();
        }

        if ($this->useStreet2) {
            $orderData['delivery_additional_info'] = $delivery->getStreet2();
        } else {
            $orderData['delivery_street_address'] .= ('' != $delivery->getStreet2())
                ? ' ' . $delivery->getStreet2()
                : '';
        }

        $orderData["billing_name"]               = $invoice->getFirstName() . " " . $invoice->getLastName();
        $orderData["billing_firstname"]          = $invoice->getFirstName();
        $orderData["billing_lastname"]           = $invoice->getLastName();
        $orderData["billing_company"]            = $invoice->getCompany();
        $orderData["billing_suburb"]             = "";
        $orderData["billing_city"]               = $invoice->getCity();
        $orderData["billing_postcode"]           = $invoice->getZipcode();
        $orderData["billing_state"]              = $invoiceStateCode;
        $orderData["billing_country"]            = $invoiceCountry["countries_name"];
        $orderData["billing_country_iso_code_2"] = $invoice->getCountry();
        $orderData["billing_address_format_id"]  = $addressFormatInvoice;

        if ($this->splitStreetHouseNumber) {
            $orderData['billing_street_address'] = $invoice->getStreetName1();
            $orderData['billing_house_number']   = $invoice->getStreetNumber1();
        } else {
            $orderData['billing_street_address'] = $invoice->getStreet1();
        }

        if ($this->useStreet2) {
            $orderData['billing_additional_info'] = $invoice->getStreet2();
        } else {
            $orderData['billing_street_address'] .= ('' != $invoice->getStreet2())
                ? ' ' . $invoice->getStreet2()
                : '';
        }

        $shippingInfos = $order->getShippingInfos();

        $orderData["shipping_method"] =
            $shippingInfos->getDisplayName()
                ? $shippingInfos->getDisplayName()
                : MODULE_PAYMENT_SHOPGATE_TITLE_BLANKET;
        $orderData["shipping_class"]  = $shippingInfos->getName()
            ? $shippingInfos->getName()
            : "flat_flat";
        $orderData["order_total_weight"] = $shippingInfos->getWeight() > 0
            ? $shippingInfos->getWeight() / 1000
            : 0;

        $orderData["cc_type"]    = "";
        $orderData["cc_owner"]   = "";
        $orderData["cc_number"]  = "";
        $orderData["cc_expires"] = "";
        $orderData["cc_start"]   = "";
        $orderData["cc_issue"]   = "";
        $orderData["cc_cvv"]     = "";
        $orderData["comments"]   = "";

        $orderData["last_modified"]  = date('Y-m-d H:i:s');
        $orderData["date_purchased"] = $order->getCreatedTime('Y-m-d H:i:s');

        $orderData["currency"]       = $order->getCurrency();
        $orderData["currency_value"] = $this->exchangeRate;

        $orderData["account_type"] = "";

        $orderData["payment_method"] = "shopgate";
        $orderData["payment_class"]  = "shopgate";

        $orderData["customers_ip"] = "";
        $orderData["language"]     = $this->language;

        $orderData["afterbuy_success"] = 0;
        $orderData["afterbuy_id"]      = 0;

        $orderData["refferers_id"]    = 0;
        $orderData["conversion_type"] = "2";

        $orderData["orders_status"] = $this->config->getOrderStatusOpen();

        $orderData["orders_date_finished"] = 'null';

        $orderData["gm_order_send_date"]   = $orderData["last_modified"];
        $orderData["gm_send_order_status"] = '1';

        $this->log('db: save order', ShopgateLogger::LOGTYPE_DEBUG);

        // stores the order data
        xtc_db_perform(TABLE_ORDERS, $orderData);
        $dbOrderId = xtc_db_insert_id();

        $this->log('db: save', ShopgateLogger::LOGTYPE_DEBUG);

        $ordersShopgateOrder = array(
            "orders_id"             => $dbOrderId,
            "shopgate_order_number" => $order->getOrderNumber(),
            "is_paid"               => $order->getIsPaid(),
            "is_shipping_blocked"   => $order->getIsShippingBlocked(),
            'is_cancelled'          => $order->getIsStorno(),
            "payment_infos"         => $this->jsonEncode($paymentInfoUtf8),
            "is_sent_to_shopgate"   => 0,
            "modified"              => "now()",
            "created"               => "now()",
        );
        xtc_db_perform(TABLE_ORDERS_SHOPGATE_ORDER, $ordersShopgateOrder);

        $this->log('method: _insertStatusHistory() ', ShopgateLogger::LOGTYPE_DEBUG);
        $this->insertStatusHistory($order, $dbOrderId, $orderData['orders_status']);

        $this->log('method: _setOrderPayment() ', ShopgateLogger::LOGTYPE_DEBUG);
        $mappedPaymentMethod = $this->setOrderPayment($order, $dbOrderId, $orderData['orders_status']);

        $this->log('method: _insertOrderItems() ', ShopgateLogger::LOGTYPE_DEBUG);
        $this->insertOrderItems($order, $dbOrderId, $orderData['orders_status'], $couponModel);

        $this->log('method: _insertOrderTotal() ', ShopgateLogger::LOGTYPE_DEBUG);
        $this->insertOrderTotal($order, $dbOrderId, $couponModel);

        $this->log('db: update order ', ShopgateLogger::LOGTYPE_DEBUG);

        /**
         * prepare / store custom fields
         */
        $orderUpdateData = $this->storeCustomFields($order, $dbOrderId, $orderData["gm_send_order_status"]);

        // Save status in order
        $orderUpdateData["orders_status"] = $orderData["orders_status"];
        $orderUpdateData["last_modified"] = date('Y-m-d H:i:s');
        xtc_db_perform(TABLE_ORDERS, $orderUpdateData, "update", "orders_id = {$dbOrderId}");

        $this->log('method: _pushOrderToAfterbuy', ShopgateLogger::LOGTYPE_DEBUG);
        $this->pushOrderToAfterbuy($dbOrderId, $order);
        $this->log('method: _pushOrderToDreamRobot', ShopgateLogger::LOGTYPE_DEBUG);
        $this->pushOrderToDreamRobot($dbOrderId, $order);

        if ($this->config->getSendOrderConfirmationMail()) {
            $this->log('method: sendOrderConfirmationMail', ShopgateLogger::LOGTYPE_DEBUG);
            $this->sendOrderConfirmationMail($dbOrderId, $orderData, $mappedPaymentMethod);
        }

        $this->log('return: end addOrder()', ShopgateLogger::LOGTYPE_DEBUG);

        return array(
            'external_order_id'     => $dbOrderId,
            'external_order_number' => $dbOrderId,
        );
    }

    /**
     * Sends order confirmation mail depending on Gambio version
     *
     * @param int    $orderId
     * @param array  $orderData
     * @param string $mappedPaymentMethod
     */
    protected function sendOrderConfirmationMail($orderId, $orderData, $mappedPaymentMethod)
    {
        $gambioVersion            = $this->gambioGXVersion;
        $gambioCompareVersion     = $gambioVersion['main_version'] . '.' . $gambioVersion['sub_version'];
        $_SESSION['languages_id'] = $this->languageId;
        $_SESSION['language']     = $this->language;
        $_SESSION['customer_id']  = $orderData['customers_id'];

        if (version_compare($gambioCompareVersion, '2.1', '<')) {
            $_GET['oID'] = $orderId;
            $insert_id   = $orderId;
            $smarty      = new Smarty;
            include(DIR_WS_CLASSES . 'order.php');
            include('send_order.php');
        } else {
            // init language-dependent mail template constants
            /** @var LanguageTextManager $languageTextcManager */
            $languageTextManager = MainFactory::create_object('LanguageTextManager', array(), true);
            $languageTextManager->init_from_lang_file(
                'lang/' . $this->language . '/modules/payment/' . "{$mappedPaymentMethod}.php"
            );

            /** @var SendOrderProcess $sendOrderProcess */
            $sendOrderProcess = MainFactory::create_object('SendOrderProcess');
            $sendOrderProcess->set_('order_id', $orderId);
            $sendOrderProcess->proceed();
        }
    }

    /**
     * create guest user data if a customer has done an order whithout registration
     *
     * @param ShopgateOrder $order
     *
     * @return array
     */
    private function createGuestUser(ShopgateOrder $order)
    {
        $address  = $order->getInvoiceAddress();
        $birthday = $address->getBirthday();

        $customer                                 = array();
        $customer["customers_vat_id_status"]      = 0;
        $customer["customers_status"]             = DEFAULT_CUSTOMERS_STATUS_ID_GUEST;
        $customer["customers_gender"]             = $address->getGender();
        $customer["customers_firstname"]          = $address->getFirstName();
        $customer["customers_lastname"]           = $address->getLastName();
        $customer["customers_email_address"]      = $order->getMail();
        $customer["customers_default_address_id"] = "";
        $customer["customers_telephone"]          = $order->getPhone();
        $customer["customers_fax"]                = "";
        $customer["customers_password"]           = md5(time() . rand(1, 999000));
        $customer["customers_newsletter"]         = 0;
        $customer["customers_newsletter_mode"]    = 0;
        $customer["member_flag"]                  = 0;
        $customer["delete_user"]                  = 1;
        $customer["account_type"]                 = 1;
        $customer["refferers_id"]                 = 0;
        $customer["customers_date_added"]         = date('Y-m-d H:i:s');
        $customer["customers_last_modified"]      = date('Y-m-d H:i:s');
        if (!empty($birthday)) {
            $customer['customers_dob'] = date("Y-m-d H:i:s", strtotime($birthday));
        }

        xtc_db_perform(TABLE_CUSTOMERS, $customer);
        $customerId = xtc_db_insert_id();

        $qry     = "SELECT countries_id FROM " . TABLE_COUNTRIES
            . " WHERE UPPER(countries_iso_code_2) = UPPER('{$address->getCountry()}')";
        $qry     = xtc_db_query($qry);
        $country = xtc_db_fetch_array($qry);
        if (empty($country)) {
            $country = array(
                'countries_id' => 81,
            );
        }

        $qry  = "SELECT zone_id, zone_name FROM " . TABLE_ZONES
            . " WHERE zone_country_id = {$country['countries_id']} AND zone_code = '"
            . ShopgateXtcMapper::getXtcStateCode($address->getState()) . "'";
        $qry  = xtc_db_query($qry);
        $zone = xtc_db_fetch_array($qry);
        if (empty($zone)) {
            $zone = array(
                'zone_id'   => null,
                'zone_name' => $address->getState(),
            );
        }

        $_address = array(
            "customers_id"          => $customerId,
            "entry_gender"          => $address->getGender(),
            "entry_company"         => $address->getCompany(),
            "entry_firstname"       => $address->getFirstName(),
            "entry_lastname"        => $address->getLastName(),
            "entry_suburb"          => "",
            "entry_postcode"        => $address->getZipcode(),
            "entry_city"            => $address->getCity(),
            "entry_state"           => $zone['zone_name'],
            "entry_country_id"      => $country["countries_id"],
            "entry_zone_id"         => $zone['zone_id'],
            "address_date_added"    => date('Y-m-d H:i:s'),
            "address_last_modified" => date('Y-m-d H:i:s'),
        );

        if ($this->splitStreetHouseNumber) {
            $_address['entry_street_address'] =
                $address->getStreetName1() .
                (('' != $address->getStreet2())
                    ? ' ' . $address->getStreet2()
                    : ''
                );
            $_address['entry_house_number']   = $address->getStreetNumber1();
        } else {
            $_address['entry_street_address'] =
                $address->getStreet1() .
                (('' != $address->getStreet2())
                    ? ' ' . $address->getStreet2()
                    : ''
                );
        }

        xtc_db_perform(TABLE_ADDRESS_BOOK, $_address);
        $addressId = xtc_db_insert_id();

        $customer = array(
            "customers_default_address_id" => $addressId,
            "customers_cid"                => $customerId,
        );
        xtc_db_perform(TABLE_CUSTOMERS, $customer, "update", "customers_id = $customerId");

        $orderDate = date('Y-m-d H:i:s', (strtotime($order->getCreatedTime('Y-m-d H:i:s')) - 1));
        $_info     = array(
            "customers_info_id"                         => $customerId,
            "customers_info_date_of_last_logon"         => $orderDate,
            "customers_info_number_of_logons"           => '1',
            "customers_info_date_account_created"       => $orderDate,
            "customers_info_date_account_last_modified" => $orderDate,
            "global_product_notifications"              => 0,
        );
        xtc_db_perform(TABLE_CUSTOMERS_INFO, $_info);

        $customerMemo                 = array();
        $customerMemo["customers_id"] = $customerId;
        $customerMemo["memo_date"]    = date('Y-m-d');
        $customerMemo["memo_title"]   = "Shopgate - Account angelegt";
        $customerMemo["memo_text"]    = "Account wurde von Shopgate angelegt";
        $customerMemo["poster_id"]    = null;
        xtc_db_perform("customers_memo", $customerMemo);

        $result   = xtc_db_query("SELECT * FROM " . TABLE_CUSTOMERS . " WHERE customers_id = {$customerId}");
        $customer = xtc_db_fetch_array($result);

        return $customer;
    }

    /**
     * read the address format id from the database by iso-2 code
     *
     * @param string $isoCode2
     * @param null   $countryId
     *
     * @return mixed
     */
    private function getAddressFormatId($isoCode2 = 'DE', $countryId = null)
    {
        $isoCode2 = strtoupper($isoCode2);
        if (!empty($countryId)) {
            $qry
                = "
                SELECT c.address_format_id
                FROM " . TABLE_COUNTRIES . " c
                WHERE c.countries_id = '$countryId'
            ";
        } else {
            $qry
                = "
                SELECT c.address_format_id
                FROM " . TABLE_COUNTRIES . " c
                WHERE UPPER(c.countries_iso_code_2) = '$isoCode2'
            ";
        }

        $result = xtc_db_query($qry);
        $item   = xtc_db_fetch_array($result);

        return $item["address_format_id"];
    }

    /**
     * stores the order comments in the database
     *
     * @param ShopgateOrder $order
     * @param int           $dbOrderId
     * @param string        $currentOrderStatus
     */
    private function insertStatusHistory(ShopgateOrder $order, $dbOrderId, &$currentOrderStatus)
    {
        $comment = "";
        if ($order->getIsTest()) {
            $comment .= "#### DIES IST EINE TESTBESTELLUNG ####\n";
        }
        $comment .= "Bestellung durch Shopgate hinzugefügt.";
        $comment .= "\nBestellnummer: " . $order->getOrderNumber();

        $paymentTransactionNumber = $order->getPaymentTransactionNumber();
        if (!empty($paymentTransactionNumber)) {
            $comment .= "\nPayment-Transaktionsnummer: " . $paymentTransactionNumber . "\n";
        }

        if ($order->getIsShippingBlocked() == 0) {
            $comment .= "\nHinweis: Der Versand der Bestellung ist bei Shopgate nicht blockiert!";
        } else {
            $comment            .= "\nHinweis: Der Versand der Bestellung ist bei Shopgate blockiert!";
            $currentOrderStatus = $this->config->getOrderStatusShippingBlocked();
        }
        if ($order->getIsCustomerInvoiceBlocked()) {
            $comment .= "\nHinweis: Für diese Bestellung darf keine Rechnung versendet werden!";
        }

        foreach ($order->getCustomFields() as $customField) {
            $comment .= "\n" . $customField->getLabel() . ": " . $customField->getValue();
        }

        $comment = $this->stringFromUtf8($comment, $this->config->getEncoding());

        $histories = array(
            array(
                "orders_id"         => $dbOrderId,
                "orders_status_id"  => $currentOrderStatus,
                "date_added"        => date('Y-m-d H:i:s'),
                "customer_notified" => false,
                "comments"          => xtc_db_prepare_input($comment),
            ),
        );

        foreach ($histories as $history) {
            xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $history);
        }
    }

    /**
     * @param ShopgateOrder $order
     * @param int           $dbOrderId
     * @param string        $currentOrderStatus
     *
     * @return string
     */
    private function setOrderPayment(ShopgateOrder $order, $dbOrderId, &$currentOrderStatus)
    {
        $payment     = $order->getPaymentMethod();
        $paymentInfo = $order->getPaymentInfos();

        $orderData = array(
            'payment_method' => 'shopgate',
        );

        $histories = array();

        $paymentName      = '';
        $paymentWasMapped = false;
        $paymentMapping   = array();

        $paymentMappingStrings = explode(';', $this->config->getPaymentNameMapping());
        foreach ($paymentMappingStrings as $paymentMappingString) {
            $paymentMappingArray = explode('=', $paymentMappingString);
            if (isset($paymentMappingArray[1])) {
                $paymentMapping[$paymentMappingArray[0]] = $paymentMappingArray[1];
            }
        }
        if (isset($paymentMapping[$payment])) {
            $comments         = $this->stringFromUtf8(
                "Zahlungsweise '" . $payment . "' durch '" . $paymentMapping[$payment] . "' ersetzt",
                $this->config->getEncoding()
            );
            $histories[]      = array(
                "orders_id"         => $dbOrderId,
                "orders_status_id"  => $currentOrderStatus,
                "date_added"        => date('Y-m-d H:i:s'),
                "customer_notified" => false,
                "comments"          => xtc_db_prepare_input($comments),
            );
            $paymentName      = $paymentMapping[$payment];
            $paymentWasMapped = true;
        }

        switch ($payment) {
            case ShopgateOrder::SHOPGATE:
                $orderData["payment_method"] = ($paymentWasMapped)
                    ? $paymentName
                    : "shopgate";
                $orderData["payment_class"]  = "shopgate";

                break;
            case ShopgateOrder::PREPAY:
                $paymentMethod = $this->determinePaymentMethod(array('eustandardtransfer', 'moneyorder'));

                $orderData["payment_method"] = ($paymentWasMapped)
                    ? $paymentName
                    : $paymentMethod;
                $orderData["payment_class"]  = $paymentMethod;

                if (!$order->getIsPaid()) {
                    $comments = $this->stringFromUtf8(
                        "Der Kunde wurde angewiesen Ihnen das Geld mit dem Verwendungszweck \"",
                        $this->config->getEncoding()
                    );
                    $comments .= $paymentInfo['purpose'];
                    $comments .= $this->stringFromUtf8(
                        "\" auf Ihr Bankkonto zu überweisen",
                        $this->config->getEncoding()
                    );

                    // Order is not paid yet
                    $histories[] = array(
                        "orders_id"         => $dbOrderId,
                        "orders_status_id"  => $currentOrderStatus,
                        "date_added"        => date('Y-m-d H:i:s'),
                        "customer_notified" => false,
                        "comments"          => xtc_db_prepare_input($comments),
                    );
                }

                break;
            case ShopgateOrder::INVOICE:
                $orderData["payment_method"] = ($paymentWasMapped)
                    ? $paymentName
                    : "invoice";
                $orderData["payment_class"]  = "invoice";

                break;
            case ShopgateOrder::COD:
                $orderData["payment_method"] = ($paymentWasMapped)
                    ? $paymentName
                    : "cod";
                $orderData["payment_class"]  = "cod";

                break;
            case ShopgateOrder::DEBIT:
                $orderData["payment_method"] = ($paymentWasMapped)
                    ? $paymentName
                    : "banktransfer";
                $orderData["payment_class"]  = "banktransfer";

                $bankTransferData                          = array();
                $bankTransferData["orders_id"]             = $dbOrderId;
                $bankTransferData["banktransfer_owner"]    = $paymentInfo["bank_account_holder"];
                $bankTransferData["banktransfer_number"]   = $paymentInfo["bank_account_number"];
                $bankTransferData["banktransfer_bankname"] = $paymentInfo["bank_name"];
                $bankTransferData["banktransfer_blz"]      = $paymentInfo["bank_code"];
                $bankTransferData["banktransfer_status"]   = "0";
                $bankTransferData["banktransfer_prz"]      = $dbOrderId;
                $bankTransferData["banktransfer_fax"]      = null;
                xtc_db_perform("banktransfer", $bankTransferData);

                $comments = $this->stringFromUtf8(
                    "Sie müssen nun den Geldbetrag per Lastschrift von dem Bankkonto des Kunden abbuchen: \n\n",
                    $this->config->getEncoding()
                );
                $comments .= $this->createPaymentInfo($paymentInfo, $dbOrderId, $currentOrderStatus, false);

                $histories[] = array(
                    "orders_id"         => $dbOrderId,
                    "orders_status_id"  => $currentOrderStatus,
                    "date_added"        => date('Y-m-d H:i:s'),
                    "customer_notified" => false,
                    "comments"          => xtc_db_prepare_input($comments),
                );

                break;
            case ShopgateOrder::PAYPAL:
                $paypalModuleName            = $this->updatePaypalOrder($order, $dbOrderId);
                $orderData["payment_method"] = ($paymentWasMapped)
                    ? $paymentName
                    : $paypalModuleName;
                $orderData["payment_class"]  = $paypalModuleName;

                // Save paymentinfos in history
                $histories[] = $this->createPaymentInfo($paymentInfo, $dbOrderId, $currentOrderStatus);

                break;
            default:
                $orderData["payment_method"] = ($paymentWasMapped)
                    ? $paymentName
                    : "mobile_payment";
                $orderData["payment_class"]  = "shopgate";

                // Save paymentinfos in history
                $histories[] = $this->createPaymentInfo($paymentInfo, $dbOrderId, $currentOrderStatus);

                break;
        }

        foreach ($histories as $history) {
            xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $history);
        }
        xtc_db_perform(TABLE_ORDERS, $orderData, "update", "orders_id = {$dbOrderId}");

        return $orderData["payment_method"];
    }

    /**
     * Parse the paymentInfo - array and get as output a array or a string
     *
     * @param array   $paymentInfo
     * @param Integer $dbOrderId
     * @param Integer $currentOrderStatus
     * @param bool    $asArray
     *
     * @return mixed History-Array or String
     */
    private function createPaymentInfo($paymentInfo, $dbOrderId, $currentOrderStatus, $asArray = true)
    {
        $paymentInformation = '';
        foreach ($paymentInfo as $key => $value) {
            $paymentInformation .= $key . ': ' . $value . "\n";
        }

        if ($asArray) {
            return array(
                "orders_id"         => $dbOrderId,
                "orders_status_id"  => $currentOrderStatus,
                "date_added"        => date('Y-m-d H:i:s'),
                "customer_notified" => false,
                "comments"          => xtc_db_prepare_input($paymentInformation),
            );
        } else {
            return $paymentInformation;
        }
    }

    /**
     * @param ShopgateOrder $order
     * @param int           $dbOrderId
     *
     * @return string payment method that should be used
     */
    private function updatePaypalOrder(ShopgateOrder $order, $dbOrderId)
    {
        $databaseHelper = new ShopgateDatabaseHelper();
        $oldTableExists = (defined('TABLE_PAYPAL') && $databaseHelper->checkTable(TABLE_PAYPAL));
        $newTablesExist =
            ($databaseHelper->checkTable('paypal_ipn') && $databaseHelper->checkTable('paypal_transactions'));

        if (!$oldTableExists && !$newTablesExist) {
            return 'paypal';
        }

        $address     = $order->getDeliveryAddress();
        $paymentInfo = $order->getPaymentInfos();

        $paypal_ipn = array(
            'txn_type'        => (!empty($paymentInfo['txn_type'])
                ? $paymentInfo['txn_type']
                : ''),
            'reason_code'     => 'None',
            'payment_type'    => (!empty($paymentInfo['payment_type'])
                ? $paymentInfo['payment_type']
                : ''),
            'payment_status'  => (!empty($paymentInfo['payment_status'])
                ? $paymentInfo['payment_status']
                : ''),
            'pending_reason'  => 'None',
            'invoice'         => (!empty($paymentInfo['invnum'])
                ? $paymentInfo['invnum']
                : null),
            'mc_currency'     => (!empty($paymentInfo['mc_currency'])
                ? $paymentInfo['mc_currency']
                : ''),
            'first_name'      => (!empty($paymentInfo['first_name'])
                ? $paymentInfo['first_name']
                : ''),
            'last_name'       => (!empty($paymentInfo['last_name'])
                ? $paymentInfo['last_name']
                : ''),
            'address_name'    => (!empty($paymentInfo['address_name'])
                ? $paymentInfo['address_name']
                : null),
            'address_street'  => (!empty($paymentInfo['address_street'])
                ? $paymentInfo['address_street']
                : null),
            'address_city'    => (!empty($paymentInfo['address_city'])
                ? $paymentInfo['address_city']
                : null),
            'address_state'   => (!empty($paymentInfo['address_state'])
                ? $paymentInfo['address_state']
                : null),
            'address_zip'     => (!empty($paymentInfo['address_zip'])
                ? $paymentInfo['address_zip']
                : null),
            'address_country' => (!empty($paymentInfo['address_country'])
                ? $paymentInfo['address_country']
                : null),
            'address_status'  => (!empty($paymentInfo['address_status'])
                ? $paymentInfo['address_status']
                : null),
            'payer_email'     => (!empty($paymentInfo['payer_email'])
                ? $paymentInfo['payer_email']
                : ''),
            'payer_id'        => (!empty($paymentInfo['payer_id'])
                ? $paymentInfo['payer_id']
                : ''),
            'payer_status'    => (!empty($paymentInfo['payer_status'])
                ? $paymentInfo['payer_status']
                : ''),
            'payment_date'    => ($order->getPaymentTime()
                ? $order->getPaymentTime('Y-m-d H:i:s')
                : ''),
            'business'        => '',
            'receiver_email'  => (!empty($paymentInfo['receiver_email'])
                ? $paymentInfo['receiver_email']
                : ''),
            'receiver_id'     => (!empty($paymentInfo['receiver_id'])
                ? $paymentInfo['receiver_id']
                : ''),
            'txn_id'          => (!empty($paymentInfo['transaction_id'])
                ? $paymentInfo['transaction_id']
                : ''),
            'mc_gross'        => (!empty($paymentInfo['mc_gross'])
                ? $paymentInfo['mc_gross']
                : '0.00'),
            'mc_fee'          => (!empty($paymentInfo['mc_fee'])
                ? $paymentInfo['mc_fee']
                : '0.00'),
            'notify_version'  => '3.7',
            'verify_sign'     => '',
        );

        $paypal = array(
            'xtc_order_id'        => $dbOrderId,
            'payer_business_name' => null,
            'num_cart_items'      => (!empty($paymentInfo['num_cart_items'])
                ? $paymentInfo['num_cart_items']
                : '0'),
            'settle_currency'     => '',
            'memo'                => '',
            'mc_authorization'    => '0.00',
            'mc_captured'         => '0.00',
            'payment_gross'       => (!empty($paymentInfo['payment_gross'])
                ? $paymentInfo['payment_gross']
                : null),
            'payment_fee'         => (!empty($paymentInfo['payment_fee'])
                ? $paymentInfo['payment_fee']
                : null),
            'exchange_rate'       => '0.00',
        );

        if ($newTablesExist) {
            // GambioGX >= 2.1
            $paypal_ipn_new = array(
                'received_time'        => $order->getCreatedTime(),
                'address_country_code' => $address->getCountry(),
                'custom'               => '',
                'item_name'            => '',
                'item_number'          => '',
                'mc_gross1'            => '',
                'mc_handling'          => '',
                'receipt_ID'           => null,
                'residence_country'    => null,
                'shipping'             => '',
                'tax'                  => '',
                'test_ipn'             => '',
                'ipn_raw'              => print_r($order->getPaymentInfos(), true),
                'date_test'            => '',
                'mc_shipping'          => (!empty($paymentInfo['mc_shipping'])
                    ? $paymentInfo['mc_shipping']
                    : '0.00'),
            );

            $paypal_transactions = array(
                'orders_id'        => $dbOrderId,
                'exchange_rate'    => '0.00',
                'raw_response'     => '',
                'grossamount'      => (!empty($paymentInfo['mc_gross'])
                    ? $paymentInfo['mc_gross']
                    : '0.00'),
                'feeamount'        => (!empty($paymentInfo['mc_fee'])
                    ? $paymentInfo['mc_fee']
                    : '0.00'),
                'payment_type'     => (!empty($paymentInfo['payment_type'])
                    ? $paymentInfo['payment_type']
                    : ''),
                'transaction_id'   => (!empty($paymentInfo['transaction_id'])
                    ? $paymentInfo['transaction_id']
                    : ''),
                'payment_date'     => (!empty($paymentInfo['payment_date'])
                    ? $paymentInfo['payment_date']
                    : ''),
                'transaction_type' => (!empty($paymentInfo['txn_type'])
                    ? $paymentInfo['txn_type']
                    : ''),
                'taxamount'        => '0.00 EUR',
            );

            $orders_paypal = array(
                'orders_id'      => $dbOrderId,
                'correlation_id' => null,
                'payer_id'       => (!empty($paymentInfo['payer_id'])
                    ? $paymentInfo['payer_id']
                    : ''),
                'token'          => (!empty($paymentInfo['token'])
                    ? $paymentInfo['token']
                    : ''),
                'paymentaction'  => '',
            );

            // look up existing entry and insert or update
            $result = xtc_db_query("SELECT p.orders_id FROM orders_paypal p WHERE p.orders_id = '{$dbOrderId}'");

            if (!(empty($result) || (xtc_db_num_rows($result) <= 0))) {
                xtc_db_perform('orders_paypal', $orders_paypal, "update", "orders_id = '{$dbOrderId}'");
            } else {
                xtc_db_perform('orders_paypal', $orders_paypal, "insert");
            }

            // look up existing TRANSACTION entry and insert or update
            $result =
                xtc_db_query("SELECT p.transaction_id FROM paypal_transactions p WHERE p.orders_id = '{$dbOrderId}'");

            if (!empty($result) && (xtc_db_num_rows($result) > 0)) {
                xtc_db_perform('paypal_transactions', $paypal_transactions, "update", "orders_id = '{$dbOrderId}'");
                $txnData = xtc_db_fetch_array($result);
                $txnId   = $txnData['transaction_id'];
            } else {
                xtc_db_perform('paypal_transactions', $paypal_transactions, "insert");
                $txnId = xtc_db_insert_id();
            }

            // update the PayPal IPN table
            $dbData = array_merge($paypal_ipn, $paypal_ipn_new);

            // look up existing TRANSACTION entry and insert or update
            $result = xtc_db_query("SELECT p.paypal_ipn_id FROM paypal_ipn p WHERE p.txn_id = '{$txnId}'");

            if (!empty($result) && (xtc_db_num_rows($result) > 0)) {
                xtc_db_perform('paypal_ipn', $dbData, "update", "txn_id = '{$txnId}'");
            } else {
                xtc_db_perform('paypal_ipn', $dbData, "insert");
            }

            return 'paypalng';
        } elseif ($oldTableExists) {
            // Gambio < 2.1

            $dbData = array_merge($paypal, $paypal_ipn);

            if ($this->checkColumn('mc_shipping', TABLE_PAYPAL)) {
                $dbData['mc_shipping'] = (!empty($paymentInfo['mc_shipping'])
                    ? $paymentInfo['mc_shipping']
                    : '0.00');
            }

            // look up existing entry and insert or update
            $result = xtc_db_query(
                "SELECT p.paypal_ipn_id FROM " . TABLE_PAYPAL . " p WHERE p.xtc_order_id = '{$dbOrderId}'"
            );

            if (!empty($result) && (xtc_db_num_rows($result) > 0)) {
                xtc_db_perform(TABLE_PAYPAL, $dbData, "update", "xtc_order_id = '{$dbOrderId}'");
            } else {
                xtc_db_perform(TABLE_PAYPAL, $dbData, "insert");
            }

            return 'paypal';
        }

        return "";
    }

    /**
     * Check if the column exists in the specified table
     *
     * @param string $columnName
     * @param string $table
     *
     * @return bool
     */
    private function checkColumn($columnName, $table = TABLE_ORDERS_SHOPGATE_ORDER)
    {
        $result = xtc_db_query("show columns from `{$table}`");

        $exists = false;
        while ($field = xtc_db_fetch_array($result)) {
            if ($field['Field'] == $columnName) {
                $exists = true;
                break;
            }
        }

        return $exists;
    }

    /**
     * stores product information to an order in the database
     *
     * @param ShopgateOrder       $order
     * @param int                 $dbOrderId
     * @param string              $currentOrderStatus
     * @param ShopgateCouponModel $couponModel
     *
     * @throws ShopgateLibraryException
     */
    private function insertOrderItems(
        ShopgateOrder $order,
        $dbOrderId,
        &$currentOrderStatus,
        ShopgateCouponModel $couponModel
    ) {
        $dbHelper = new ShopgateDatabaseHelper();

        $itemModel = new ShopgateItemModel(
            $dbHelper,
            new ShopgateItemPropertyHelper($dbHelper, $this->config, $this->languageId),
            new ShopgateCustomizerSetHelper(),
            $this->config,
            $this->languageId,
            $this->currency,
            $this->countryId,
            $this->zoneId,
            $this->exchangeRate
        );
        $errors    = '';

        foreach ($order->getItems() as $orderItem) {
            $order_info = $this->jsonDecode($orderItem->getInternalOrderInfo(), true);
            unset($updateItemStock);

            // The product is possibly stacked
            $stackQuantity = !empty($order_info['stack_quantity'])
                ? $order_info['stack_quantity']
                : 1;
            $item_number   = isset($order_info["base_item_number"])
                ? $order_info["base_item_number"]
                : $orderItem->getItemNumber();

            $this->log('db: get product ', ShopgateLogger::LOGTYPE_DEBUG);

            $qry = xtc_db_query(
                "SELECT p.*, pd.products_name, ss.shipping_status_name FROM " . TABLE_PRODUCTS . " p"
                . " INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION
                . " pd ON (pd.products_id=p.products_id AND pd.language_id='$this->languageId')"
                . " LEFT JOIN " . TABLE_SHIPPING_STATUS
                . " ss ON (ss.shipping_status_id=p.products_shippingtime AND ss.language_id='$this->languageId')"
                . " WHERE"
                . " p.products_id = '" . $item_number . "'"
                . " LIMIT 1"
            );

            $dbProduct = xtc_db_fetch_array($qry);
            if (empty($dbProduct) && ($item_number == 'COUPON' || $item_number == 'PAYMENT_FEE')) {
                $this->log('product is COUPON or PAYMENTFEE', ShopgateLogger::LOGTYPE_DEBUG);

                // workaround for shopgate coupons
                $dbProduct                   = array();
                $dbProduct['products_id']    = 0;
                $dbProduct['products_model'] = $item_number;
            } elseif (empty($dbProduct)) {
                $this->log('no product found', ShopgateLogger::LOGTYPE_DEBUG);

                $this->log(
                    ShopgateLibraryException::buildLogMessageFor(
                        ShopgateLibraryException::PLUGIN_ORDER_ITEM_NOT_FOUND,
                        'Shopgate-Order-Number: ' . $order->getOrderNumber() . ', DB-Order-Id: ' . $dbOrderId
                        . '; item (item_number: ' . $item_number
                        . '). The item will be skipped.'
                    )
                );
                $errors .= "\nItem (item_number: " . $item_number
                    . ") can not be found in your shopping system. Please contact Shopgate. The item will be skipped.";

                $dbProduct['products_id']    = 0;
                $dbProduct['products_model'] = $item_number;
            }

            $sku = $dbProduct['products_model'];
            if (!empty($order_info['is_property_attribute']) && $orderItem->getItemNumberPublic()) {
                $sku = $orderItem->getItemNumberPublic();
            }
            $this->log('db: orders_products', ShopgateLogger::LOGTYPE_DEBUG);

            $shippingTime      = $dbProduct['shipping_status_name'];
            $internalOrderInfo = $this->jsonDecode($orderItem->getInternalOrderInfo(), true);
            if (!empty($internalOrderInfo['is_property_attribute']) && !empty($dbProduct['use_properties_combis_shipping_time'])) {
                $qry = xtc_db_query(
                    "SELECT `s`.`shipping_status_name` FROM " . TABLE_SHIPPING_STATUS . " AS `s`"
                    . " INNER JOIN " . TABLE_PRODUCTS_PROPERTIES_COMBIS . " AS `ppc`"
                    . " ON (`s`.`shipping_status_id`=`ppc`.`combi_shipping_status_id` AND `s`.`language_id`='"
                    . $this->languageId
                    . "')"
                    . " WHERE"
                    . " `ppc`.`products_properties_combis_id` = '"
                    . $internalOrderInfo['products_properties_combis_id']
                    . "'"
                    . " LIMIT 1"
                );

                $dbShippingTime = xtc_db_fetch_array($qry);
                if (!empty($dbShippingTime['shipping_status_name'])) {
                    $shippingTime = $dbShippingTime['shipping_status_name'];
                } else {
                    $this->log(
                        ShopgateLibraryException::buildLogMessageFor(
                            ShopgateLibraryException::PLUGIN_ORDER_ITEM_NOT_FOUND,
                            'Shopgate-Order-Number: ' . $order->getOrderNumber()
                            . ', DB-Order-Id: ' . $dbOrderId
                            . '; item (item_number: ' . $item_number . ').'
                            . '; Property combi id: item (item_number: '
                            . $internalOrderInfo['products_properties_combis_id']
                            . ').'
                            . '; Shipping status id: See product property combi above for "combi_shipping_status_id".'
                        )
                    );
                    $errors .= "\nA child item or the assigned shipping status could not be found in the system to "
                        . "read the shipping time.\n"
                        . "Defaulting to parents shipping time.";
                }
            }

            $productData = array(
                "orders_id"              => $dbOrderId,
                "products_model"         => $sku,
                "products_id"            => $item_number,
                "products_name"          => ($stackQuantity > 1)
                    ? $dbProduct['products_name']
                    : xtc_db_prepare_input(
                        $orderItem->getName()
                    ),
                // The name contains additional text on quantity-stacked products
                "products_price"         => $orderItem->getUnitAmountWithTax() / floatval($stackQuantity),
                // calculate the price of a single, non stacked, product
                "products_discount_made" => 0,
                "final_price"            => $orderItem->getQuantity() * ($orderItem->getUnitAmountWithTax()),
                "products_shipping_time" => xtc_db_prepare_input($shippingTime),
                "products_tax"           => $orderItem->getTaxPercent(),
                "products_quantity"      => $orderItem->getQuantity() * $stackQuantity,
                "allow_tax"              => 1,
            );

            xtc_db_perform(TABLE_ORDERS_PRODUCTS, $productData);
            $productsOrderId = xtc_db_insert_id();

            $options    = $orderItem->getOptions();
            $userInputs = $orderItem->getInputs();

            // Separate all gx-customizer Inputs from the default inputs
            if (!empty($userInputs)) {
                /** @var ShopgateOrderItemInput[] $gxCustomizerInputs */
                $gxCustomizerInputs = array();
                /** @var ShopgateOrderItemInput[] $gxCustomizerInputs */
                $nonGxCustomizerInputs = array();
                // do only if the customizer is available (customizer set table exists in that case)
                if (!isset($gxCustomizerEnable)) {
                    $gxCustomizerEnable = $dbHelper->tableExists('gm_gprint_surfaces_groups');
                }
                foreach ($userInputs as $userInput) {
                    if ($gxCustomizerEnable
                        && strpos(strtolower($userInput->getInputNumber()), 'gxcust_el_val_id_') !== false
                    ) {
                        $gxCustomizerInputs[] = $userInput;
                    } else {
                        $nonGxCustomizerInputs[] = $userInput;
                    }
                }

                // Import all gx Customizer data for the ordered product
                if (!empty($gxCustomizerInputs)) {
                    // load gx customizer sets first to be able to create a structure for the customizer order structure
                    // do only once, since it loads all customizer sets in a single call (data is constant after loading and this call is inside a loop)
                    if (empty($gxCustomizerSets)) {
                        $gxCustomizerSets = $itemModel->getGxCustomizerSets();
                    }

                    // Maximum of one customizer set per ordered product
                    $gxCustomizerOrderSet = array();
                    // each set can contain multiple surfaces
                    $gxCustomizerOrderSurfaces = array();
                    //
                    $gxCustomizerOrderValuesPerSurface = array();

                    foreach ($gxCustomizerInputs as $userInput) {
                        $customizerSurfaceId = "";
                        // left and right part are separated by the hash-sign (#); the right part contains a pattern with the id's
                        $splitInputNumber = explode('#', $userInput->getInputNumber());
                        // the indexing is done using the following pattern: <customizer_set_id>.<surface_id>.<element_id>
                        $customizerIndexSet = explode('.', $splitInputNumber[1]);
                        if (!empty($customizerIndexSet[0])) {
                            $customizerSetId = $customizerIndexSet[0];
                        }
                        if (!empty($customizerIndexSet[1])) {
                            $customizerSurfaceId = $customizerIndexSet[1];
                        }
                        if (!empty($customizerIndexSet[2])) {
                            $customizerSurfaceElementId = $customizerIndexSet[2];
                        }

                        // Every id must be set and every id must exist inside the current customizer set as well as the customizer set must exist
                        if (empty($customizerSetId) || empty($customizerSurfaceId) || empty($customizerSurfaceElementId)
                            || empty($gxCustomizerSets[$customizerSetId])
                            || empty($gxCustomizerSets[$customizerSetId][$customizerSurfaceId])
                        ) {
                            // add the user input to an alternate way for expressing user inputs (by using the orders comments)
                            $nonGxCustomizerInputs[] = $userInput;
                        } else {
                            // save each customizer set only once
                            if (empty($gxCustomizerOrderSet)) {
                                // "surfaces_groups_name" is identical in each array of a surface in a customizer set (array-elements are indexed -> just use the first element to get the set-name)
                                $gxCustomizerOrderSet['name']               =
                                    $gxCustomizerSets[$customizerSetId][$customizerSurfaceId][0]['surfaces_groups_name'];
                                $gxCustomizerOrderSet['orders_products_id'] = $productsOrderId;

                                // save set to db
                                xtc_db_perform('gm_gprint_orders_surfaces_groups', $gxCustomizerOrderSet);
                                $gxCustomizerOrderSet['gm_gprint_orders_surfaces_groups_id'] = xtc_db_insert_id();
                            }

                            // save each surface only once
                            if (empty($gxCustomizerOrderSurfaces[$customizerSurfaceId])) {
                                $gxCustomizerOrderSurfaces[$customizerSurfaceId] = array(
                                    // take the first value-element in each surface since the surface data is identical in each surface_values-element
                                    'gm_gprint_orders_surfaces_groups_id' => $gxCustomizerOrderSet['gm_gprint_orders_surfaces_groups_id'],
                                    'name'                                => $gxCustomizerSets[$customizerSetId][$customizerSurfaceId][0]['surfaces_name'],
                                    'width'                               => $gxCustomizerSets[$customizerSetId][$customizerSurfaceId][0]['surfaces_width'],
                                    'height'                              => $gxCustomizerSets[$customizerSetId][$customizerSurfaceId][0]['surfaces_height'],
                                );

                                // save surface directly to db
                                xtc_db_perform(
                                    'gm_gprint_orders_surfaces',
                                    $gxCustomizerOrderSurfaces[$customizerSurfaceId]
                                );
                                $gxCustomizerOrderSurfaces[$customizerSurfaceId]['gm_gprint_orders_surfaces_id'] =
                                    xtc_db_insert_id();
                            }

                            // buffer all value-elements, grouped by containing surfaces (unsupported elements will be saved with empty value)
                            if (empty($gxCustomizerOrderValuesPerSurface[$customizerSurfaceId])) {
                                $gxCustomizerOrderValuesPerSurface[$customizerSurfaceId] = array();
                            }
                            // insert all elements and /or fill with input data
                            $processedGroups = array();
                            foreach (
                                $gxCustomizerSets[$customizerSetId][$customizerSurfaceId] as $key =>
                                $gxCustomSurfaceValue
                            ) {
                                // create each surface-element-value (unsupported elements will include dummy data); TEXT-field elements will include the right data, since the text is predefined
                                if (empty($gxCustomizerOrderValuesPerSurface[$customizerSurfaceId][$gxCustomSurfaceValue['elements_values_id']])
                                    && empty($processedGroups[$gxCustomSurfaceValue['elements_groups_id']])
                                ) {
                                    $gxCustomizerOrderValuesPerSurface[$customizerSurfaceId][$gxCustomSurfaceValue['elements_values_id']] =
                                        array(
                                            'gm_gprint_orders_surfaces_id' => $gxCustomizerOrderSurfaces[$customizerSurfaceId]['gm_gprint_orders_surfaces_id'],
                                            'position_x'                   => $gxCustomSurfaceValue['elements_position_x'],
                                            'position_y'                   => $gxCustomSurfaceValue['elements_position_y'],
                                            'height'                       => $gxCustomSurfaceValue['elements_position_height'],
                                            'width'                        => $gxCustomSurfaceValue['elements_position_width'],
                                            'z_index'                      => $gxCustomSurfaceValue['elements_position_z_index'],
                                            'show_name'                    => $gxCustomSurfaceValue['elements_show_name'],
                                            'group_type'                   => $gxCustomSurfaceValue['elements_group_type'],
                                            'name'                         => $gxCustomSurfaceValue['elements_values_name'],
                                            'elements_value'               => '',
                                            // leave empty at first
                                            //'gm_gprint_uploads_id'       => null, // not to be set; so this field will be a NULL sql-value
                                        );
                                    if (strtolower($gxCustomSurfaceValue['elements_group_type']) == 'text') {
                                        $gxCustomizerOrderValuesPerSurface[$customizerSurfaceId][$gxCustomSurfaceValue['elements_values_id']]['elements_value']
                                            = $gxCustomSurfaceValue['elements_values_value'];
                                    }
                                }

                                // allow only one "empty" value per product for dropdown groups
                                if (strtolower($gxCustomSurfaceValue['elements_group_type']) == 'dropdown') {
                                    $processedGroups[$gxCustomSurfaceValue['elements_groups_id']] =
                                        $gxCustomSurfaceValue['elements_groups_id'];
                                }
                            }

                            // Insert the value for the actual input field after all available surface_value-elements are preset
                            if (!empty($gxCustomizerOrderValuesPerSurface[$customizerSurfaceId][$customizerSurfaceElementId])) {
                                $gxCustomizerOrderValuesPerSurface[$customizerSurfaceId][$customizerSurfaceElementId]['elements_value']
                                    = xtc_db_input($userInput->getUserInput());
                            }
                        }
                    }

                    // Save all surface-value elements into db if there are any
                    if (!empty($gxCustomizerOrderValuesPerSurface)) {
                        foreach ($gxCustomizerOrderValuesPerSurface as $gxCustSurfaceId => $gxCustomSurfaceElements) {
                            foreach ($gxCustomSurfaceElements as $gxCustElementId => $gxCustomElement) {
                                // save surface directly to db
                                xtc_db_perform('gm_gprint_orders_elements', $gxCustomElement);
                                $gxCustomizerOrderValuesPerSurface[$gxCustSurfaceId][$gxCustElementId] =
                                    xtc_db_insert_id();
                            }
                        }

                        // need to insert a "dummy item variation" to enable the display on the admin
                        // page if there are no item variations available
                        $attributesAvailable = false;
                        if (!empty($options)) {
                            $attributesAvailable = true;
                        } elseif (empty($order_info['is_property_attribute'])
                            || $order_info['is_property_attribute'] == 0
                        ) {
                            // checks attributes only if it is not a "property attribute", because attributes
                            // and properties can't be used at the same time for a single product
                            for ($i = 1; $i <= 10; $i++) {
                                if (!empty($order_info["attribute_$i"])) {
                                    $attributesAvailable = true;
                                    break;
                                }
                            }
                        }
                        // insert the dummy attribute if needed
                        if (!$attributesAvailable) {
                            $productAttributeData = array(
                                "orders_id"               => $dbOrderId,
                                "orders_products_id"      => $productsOrderId,
                                "products_options"        => '',
                                "products_options_values" => '',
                                "options_values_price"    => '0.0000',
                                "price_prefix"            => '',
                            );
                            xtc_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $productAttributeData);
                        }
                    }
                }

                if (!empty($nonGxCustomizerInputs)) {
                    /* @var ShopgateOrderItemInput $input */
                    foreach ($nonGxCustomizerInputs as $input) {
                        $productAttributeData = array(
                            "orders_id"               => $dbOrderId,
                            "orders_products_id"      => $productsOrderId,
                            "products_options"        => $input->getLabel(),
                            "products_options_values" => $input->getUserInput(),
                            "options_values_price"    => $input->getAdditionalAmountWithTax(),
                            "price_prefix"            => ($input->getAdditionalAmountWithTax() < 0)
                                ? "-"
                                : "+",
                        );
                        xtc_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $productAttributeData);
                    }
                }
            }

            if (!empty($options)) {
                $this->log('process options', ShopgateLogger::LOGTYPE_DEBUG);
                foreach ($options as $option) {
                    $attribute_model  = $option->getValueNumber();
                    $attribute_number = $option->getOptionNumber();

                    $this->log('db: get attributes', ShopgateLogger::LOGTYPE_DEBUG);

                    // Hole das Attribut aus der Datenbank
                    $qry
                        = "
                        SELECT
                            po.products_options_name,
                            pov.products_options_values_name,
                            pa.options_values_price,
                            pa.price_prefix
                        FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                        INNER JOIN " . TABLE_PRODUCTS_OPTIONS . " po ON pa.options_id = po.products_options_id AND po.language_id = $this->languageId
                        INNER JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " povtpo ON povtpo.products_options_id = po.products_options_id
                        INNER JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov ON (povtpo.products_options_values_id = pov.products_options_values_id AND pa.options_values_id = pov.products_options_values_id AND pov.language_id = $this->languageId)
                        WHERE pa.products_id = '" . $dbProduct["products_id"] . "'
                        " . (!empty($attribute_number)
                            ? "AND pa.options_id = '{$attribute_number}'
                        "
                            : "") .
                        "AND pa.options_values_id = '{$attribute_model}'
                        LIMIT 1
                    ";

                    $qry         = xtc_db_query($qry);
                    $dbattribute = xtc_db_fetch_array($qry);
                    if (empty($dbattribute)) {
                        continue;
                    } //Fehler

                    $this->log('db: save order product attributes', ShopgateLogger::LOGTYPE_DEBUG);

                    $productAttributeData = array(
                        "orders_id"               => $dbOrderId,
                        "orders_products_id"      => $productsOrderId,
                        "products_options"        => $dbattribute['products_options_name'],
                        "products_options_values" => $dbattribute["products_options_values_name"],
                        "options_values_price"    => $dbattribute["options_values_price"],
                        "price_prefix"            => $dbattribute["price_prefix"],
                    );
                    xtc_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $productAttributeData);
                }
            } else {
                // Variations depend on variation type, that has been exported
                if (!empty($order_info['is_property_attribute'])) {
                    $this->log('properties?', ShopgateLogger::LOGTYPE_DEBUG);
                    $combinationId = $order_info['products_properties_combis_id'];

                    $cartHelper            = new ShopgateCartHelper();
                    $combinationProperties = $cartHelper->getPropertyCombinations(
                        $combinationId,
                        $item_number,
                        $this->languageId
                    );

                    foreach ($combinationProperties as $dbProperty) {
                        $productPropertyData = array(
                            'orders_products_id'            => $productsOrderId,
                            'products_properties_combis_id' => $combinationId,
                            'properties_name'               => $dbProperty['properties_name'],
                            'values_name'                   => $dbProperty['values_name'],
                            'properties_price_type'         => '',
                            'properties_price'              => '0',
                        );
                        xtc_db_perform(TABLE_ORDERS_PRODUCTS_PROPERTIES, $productPropertyData);
                    }

                    $combisQtySetting = $cartHelper->getPropertyStockSetting($item_number);
                    list($updateItemStock, $updatePropertiesStock) = $cartHelper->getStockReductionSettings(
                        $combisQtySetting
                    );

                    // Update properties stock
                    if (STOCK_LIMITED == 'true' && !empty($combinationProperties) && !empty($updatePropertiesStock)) {
                        $cartHelper->reducePropertyStockQty($combinationId, $orderItem->getQuantity());
                    }

                    $this->log('db: save order product property combi', ShopgateLogger::LOGTYPE_DEBUG);
                } else {
                    $this->log('attributes?', ShopgateLogger::LOGTYPE_DEBUG);

                    for ($i = 1; $i <= 10; $i++) {
                        if (!isset($order_info["attribute_$i"])) {
                            break;
                        }
                        $tmpAttr = $order_info["attribute_$i"];
                        // Code for support of the old internal_order_info structure
                        if (!is_array($tmpAttr)) {
                            $attribute_model = $tmpAttr;
                        } else {
                            // Den ersten und einzigen key nutzen (zur Sicherheit auf den start des Arrays setzen)
                            reset($tmpAttr);
                            $attribute_number = $tmpAttr[key($tmpAttr)]['options_id'];
                            $attribute_model  = $tmpAttr[key($tmpAttr)]['options_values_id'];
                        }

                        $this->log('db: get attribute', ShopgateLogger::LOGTYPE_DEBUG);

                        // Hole das Attribut aus der Datenbank
                        $qry
                            = "
                            SELECT
                                po.products_options_name,
                                pov.products_options_values_name,
                                pa.options_values_price,
                                pa.price_prefix
                            FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                            INNER JOIN " . TABLE_PRODUCTS_OPTIONS . " po ON pa.options_id = po.products_options_id AND po.language_id = $this->languageId
                            INNER JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " povtpo ON povtpo.products_options_id = po.products_options_id
                            INNER JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov ON (povtpo.products_options_values_id = pov.products_options_values_id AND pa.options_values_id = pov.products_options_values_id AND pov.language_id = $this->languageId)
                            WHERE pa.products_id = '" . $dbProduct["products_id"] . "'
                            " .
                            // Still support the old internal_order_info structure
                            (!empty($attribute_id)
                                ? "AND pa.products_attributes_id = '" . $attribute_model . "'"
                                : "AND pa.options_id = '{$attribute_number}' AND pa.options_values_id = '{$attribute_model}'  ")
                            . " LIMIT 1";

                        $qry         = xtc_db_query($qry);
                        $dbattribute = xtc_db_fetch_array($qry);
                        if (empty($dbattribute)) {
                            continue;
                        } //Fehler

                        $this->log('db: save order product attributes', ShopgateLogger::LOGTYPE_DEBUG);

                        $productAttributeData = array(
                            "orders_id"               => $dbOrderId,
                            "orders_products_id"      => $productsOrderId,
                            "products_options"        => $dbattribute["products_options_name"],
                            "products_options_values" => $dbattribute["products_options_values_name"],
                            "options_values_price"    => $dbattribute["options_values_price"],
                            "price_prefix"            => $dbattribute["price_prefix"],
                        );
                        xtc_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $productAttributeData);
                    }
                }
            }
            $this->log('method: updateItemStock', ShopgateLogger::LOGTYPE_DEBUG);
            if (!isset($updateItemStock)) {
                $updateItemStock = true;
                $noUpdateAttributes = false;
            } else {
                // Never update attributes stock when items stock is not updated
                $noUpdateAttributes = true;
            }
            // Specials have to be reduced always
            $noUpdateSpecials = false;

            $this->updateItemStock($orderItem, $updateItemStock, $noUpdateAttributes, $noUpdateSpecials);
        }

        $coupons = $order->getExternalCoupons();
        foreach ($coupons as $coupon) {
            $couponModel->redeemCoupon($coupon, $order->getExternalCustomerId());
        }

        if (!empty($errors)) {
            $this->log('db: save errors in history', ShopgateLogger::LOGTYPE_DEBUG);
            $comments = $this->stringFromUtf8(
                'Es sind Fehler beim Importieren der Bestellung aufgetreten: ',
                $this->config->getEncoding()
            );
            $comments .= $errors;

            $history = array(
                "orders_id"         => $dbOrderId,
                "orders_status_id"  => $currentOrderStatus,
                "date_added"        => date("Y-m-d H:i:s", time() - 5),
                // "-5" Damit diese Meldung als erstes oben angezeigt wird
                "customer_notified" => false,
                "comments"          => xtc_db_prepare_input($comments),
            );

            xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $history);
        }
    }

    /**
     * @param ShopgateOrderItem $item
     * @param bool              $updateParentStock
     * @param bool              $ignoreAttributes
     * @param bool              $ignoreSpecials
     */
    private function updateItemStock(
        ShopgateOrderItem $item,
        $updateParentStock = true,
        $ignoreAttributes = false,
        $ignoreSpecials = false,
        $products = array()
    ) {
        // Skip "coupon" and "payment_fee" items
        if ($item->getItemNumber() == 'COUPON' || $item->getItemNumber() == 'PAYMENT_FEE') {
            return;
        }

        // Attribute ids are set inside the internal order info
        $internalOrderInfo = $this->jsonDecode($item->getInternalOrderInfo(), true);

        $usesProductsAttributes = false;

        // Get id (parent id for child products)
        $productId = $item->getItemNumber();
        if (!empty($internalOrderInfo['base_item_number'])) {
            $productId              = $internalOrderInfo['base_item_number'];
            $usesProductsAttributes = true;
        }

        $itemOptions = $item->getOptions();
        if (!empty($itemOptions)) {
            $usesProductsAttributes = true;
        }

        // Update "products_ordered" field that is used to display all bestsellers (always update this)
        $qry
            = "
                UPDATE `" . TABLE_PRODUCTS . "` AS `p`
                    SET `p`.`products_ordered` = `p`.`products_ordered` + " . ($item->getQuantity()) . "
                WHERE `p`.`products_id` = '{$productId}'
            ;";
        xtc_db_query($qry);

        // Update products stock if reduction enabled
        if (STOCK_LIMITED == 'true' && $updateParentStock) {
            // Reduce products stock
            $qry
                = "
                    UPDATE `" . TABLE_PRODUCTS . "` AS `p`
                        SET `p`.`products_quantity` = `p`.`products_quantity` - {$item->getQuantity()}
                    WHERE `p`.`products_id` = '{$productId}'
                ;";
            xtc_db_query($qry);

            $qry        = "select products_quantity FROM `" . TABLE_PRODUCTS
                . "` AS `p` WHERE `p`.`products_id` = '{$productId}';";
            $result     = xtc_db_query($qry);
            $result     = xtc_db_fetch_array($result);
            $stock_left = $result["products_quantity"];

            if ($stock_left <= STOCK_REORDER_LEVEL) {
                $gm_get_products_name = xtc_db_query(
                    "SELECT products_name
                            FROM products_description
                            WHERE
                                products_id = '" . xtc_get_prid($productId) . "'
                                AND language_id = '" . $_SESSION['languages_id'] . "'"
                );
                $gm_stock_data        = xtc_db_fetch_array($gm_get_products_name);

                $gm_subject = GM_OUT_OF_STOCK_NOTIFY_TEXT . ' ' . $gm_stock_data['products_name'];
                $gm_body    = GM_OUT_OF_STOCK_NOTIFY_TEXT . ': ' . (double)$stock_left . "\n\n" .
                    HTTP_SERVER . DIR_WS_CATALOG . 'product_info.php?info=p' . xtc_get_prid($productId);
                $gm_body    = $this->stringToUtf8($gm_body, $this->config->getEncoding());

                // send mail
                $this->log("seding information via email about low stock", ShopgateLogger::LOGTYPE_DEBUG);
                xtc_php_mail(
                    STORE_OWNER_EMAIL_ADDRESS,
                    STORE_NAME,
                    STORE_OWNER_EMAIL_ADDRESS,
                    STORE_NAME,
                    '',
                    STORE_OWNER_EMAIL_ADDRESS,
                    STORE_NAME,
                    '',
                    '',
                    $gm_subject,
                    nl2br(htmlentities($gm_body)),
                    $gm_body
                );
            }

            // Deactivate product if checkout is not allowed and the stock level reaches zero
            if (STOCK_ALLOW_CHECKOUT == 'false') {
                // gambiogx has an additional constant that tells if the product may be deactivated (GM_SET_OUT_OF_STOCK_PRODUCTS)
                if (GM_SET_OUT_OF_STOCK_PRODUCTS == 'true') { // don't update if defined and not true
                    $qry
                        = "
                            UPDATE `" . TABLE_PRODUCTS . "` AS `p`
                                SET `p`.`products_status` = '0'
                            WHERE `p`.`products_id` = '{$productId}' AND `p`.`products_quantity` <= 0
                        ;";
                    xtc_db_query($qry);
                }
            }
        }

        // Attribute items also need to be reduced in stock
        if ($usesProductsAttributes && !$ignoreAttributes) {
            // Build additional SQL snippets to update the attributes stock (not using the products_attributes_id because they all change on each update of any attribute in the backend of the shoppingsystem)
            $attributeSQLQueryParts = array();
            if (!empty($internalOrderInfo['base_item_number'])) {
                for ($i = 1; $i <= 10; $i++) {
                    if (!empty($internalOrderInfo["attribute_{$i}"])) {
                        $tmpAttr = $internalOrderInfo["attribute_{$i}"];
                        if (!is_array($tmpAttr)) {
                            $attributeSQLQueryParts[] = " ATTRIBUTES_ID='{$tmpAttr}'";
                        } else {
                            // Only the first element is relevant since there can only be one per attribute-number
                            reset($tmpAttr);
                            $attributeSQLQueryParts[] = 'OPTIONS_ID=\'' . $tmpAttr[key($tmpAttr)]['options_id']
                                . '\' AND OPTIONS_VALUES_ID=\''
                                . $tmpAttr[key($tmpAttr)]['options_values_id'] . '\'';
                        }
                    }
                }
            } else {
                // Attributes was exported as options
                foreach ($itemOptions as $itemOption) {
                    $attributeSQLQueryParts[] =
                        'OPTIONS_ID=\'' . $itemOption->getOptionNumber() . '\' AND OPTIONS_VALUES_ID=\''
                        . $itemOption->getValueNumber()
                        . '\'';
                }
            }
            if (!empty($attributeSQLQueryParts)) {
                // Attribute stock is ALWAYS reduced (no matter what is set as STOCK_LIMITED or the other constants)!
                $attributeSQLConditionSnippet = '(' . str_replace(
                        array('OPTIONS_ID', 'OPTIONS_VALUES_ID', 'ATTRIBUTES_ID'),
                        array('`pa`.`options_id`', '`pa`.`options_values_id`', '`pa`.`products_attributes_id`'),
                        implode(') OR (', $attributeSQLQueryParts)
                    ) . ')';

                // Update attributes stock
                $qry
                    = "
                        UPDATE `" . TABLE_PRODUCTS_ATTRIBUTES . "` AS `pa`
                            SET `pa`.`attributes_stock` = `pa`.`attributes_stock` - {$item->getQuantity()}
                        WHERE `pa`.`products_id` = '{$productId}'
                            AND ({$attributeSQLConditionSnippet})
                    ;";
                xtc_db_query($qry);
            }

            for ($i = 1; ($i <= 10 && !empty($internalOrderInfo["attribute_{$i}"])); $i++) {
                foreach ($internalOrderInfo["attribute_{$i}"] as $attribute) {
                    $optionId                = $attribute["options_id"];
                    $optionValueId           = $attribute["options_values_id"];
                    $gm_get_attributes_stock = xtc_db_query(
                        "SELECT
                                                            pd.products_name,
                                                            pa.attributes_stock,
                                                            po.products_options_name,
                                                            pov.products_options_values_name
                                                        FROM
                                                            products_description pd,
                                                            products_attributes pa,
                                                            products_options po,
                                                            products_options_values pov
                                                        WHERE pa.products_id = '" . $productId . "'
                                                            AND pa.options_values_id = '" . $optionValueId . "'
                                                            AND pa.options_id = '" . $optionId . "'
                                                            AND po.products_options_id = '" . $optionId . "'
                                                            AND po.language_id = '" . $this->languageId . "'
                                                            AND pov.products_options_values_id = '" . $optionValueId . "'
                                                            AND pov.language_id = '" . $this->languageId . "'
                                                            AND pd.products_id = '" . $productId . "'
                                                            AND pd.language_id = '" . $this->languageId . "'"
                    );
                    if (xtc_db_num_rows($gm_get_attributes_stock) == 1) {
                        $gm_attributes_stock_data = xtc_db_fetch_array($gm_get_attributes_stock);

                        if ($gm_attributes_stock_data['attributes_stock'] <= STOCK_REORDER_LEVEL) {
                            $gm_subject =
                                GM_OUT_OF_STOCK_NOTIFY_TEXT . ' ' . $gm_attributes_stock_data['products_name']
                                . ' - '
                                . $gm_attributes_stock_data['products_options_name'] . ': '
                                . $gm_attributes_stock_data['products_options_values_name'];
                            $gm_body    = GM_OUT_OF_STOCK_NOTIFY_TEXT . ': '
                                . (double)$gm_attributes_stock_data['attributes_stock'] . ' ('
                                . $gm_attributes_stock_data['products_name'] . ' - '
                                . $gm_attributes_stock_data['products_options_name'] . ': '
                                . $gm_attributes_stock_data['products_options_values_name'] . ")\n\n" .
                                HTTP_SERVER . DIR_WS_CATALOG . 'product_info.php?info=p' . xtc_get_prid(
                                    $item->getItemNumber()
                                );

                            $gm_body = $this->stringToUtf8($gm_body, $this->config->getEncoding());
                            xtc_php_mail(
                                STORE_OWNER_EMAIL_ADDRESS,
                                STORE_NAME,
                                STORE_OWNER_EMAIL_ADDRESS,
                                STORE_NAME,
                                '',
                                STORE_OWNER_EMAIL_ADDRESS,
                                STORE_NAME,
                                '',
                                '',
                                $gm_subject,
                                nl2br(htmlentities($gm_body)),
                                $gm_body
                            );
                        }
                    }
                }
            }
        }

        // Specials stock and active status
        if (!empty($internalOrderInfo['is_special_price']) && !$ignoreSpecials) {
            // Always update specials quantity if it is a special
            $qry
                = "
                    UPDATE `" . TABLE_SPECIALS . "` AS `s`
                        SET `s`.`specials_quantity` = `s`.`specials_quantity` - {$item->getQuantity()}
                    WHERE `s`.`products_id` = '{$productId}'
                ;";
            xtc_db_query($qry);

            $reduceQuantitySqlSnippet = '';
            if (STOCK_CHECK == 'true') {
                // only if stock check is active we have to deactivate specials
                $reduceQuantitySqlSnippet = " OR `s`.specials_quantity <= 0 AND `s`.`products_id` = '{$productId}'";
            }

            // Always deactivate specials that have turned to a value equal to or less than zero and deactivate all specials that are expired
            $qry
                = "
                    UPDATE `" . TABLE_SPECIALS . "` AS `s`
                        SET `s`.`status` = 0
                    WHERE
                        `s`.`status` != 0
                        AND
                            (`s`.`expires_date` < NOW() AND `s`.`expires_date` > '1000-01-01 00:00:00' AND `s`.`expires_date` IS NOT NULL
                        " . $reduceQuantitySqlSnippet . ")
                ;";
            xtc_db_query($qry);
        }

    }

    /**
     * stores the complete order amount in the database
     *
     * @param ShopgateOrder       $order
     * @param int                 $dbOrderId
     * @param ShopgateCouponModel $couponModel
     */
    private function insertOrderTotal(ShopgateOrder $order, $dbOrderId, ShopgateCouponModel $couponModel)
    {
        $amountWithTax   = $order->getAmountComplete();
        $shippingTaxRate = $order->getShippingTaxPercent();
        $taxes           = $this->getOrderTaxes($order, $shippingTaxRate);
        /** @var xtcPrice_ORIGIN $xtPrice */
        $xtPrice       = new xtcPrice($this->currency["code"], 1);
        $shippingCosts = $order->getAmountShipping();
        $shippingInfos = $order->getShippingInfos();

        $this->log('_insertOrderTotal(): add subtotal', ShopgateLogger::LOGTYPE_DEBUG);

        $sort = 10;

        $ordersTotal               = array();
        $ordersTotal["orders_id"]  = $dbOrderId;
        $ordersTotal["title"]      = xtc_db_prepare_input(MODULE_PAYMENT_SHOPGATE_ORDER_LINE_TEXT_SUBTOTAL . ":");
        $ordersTotal["text"]       = $xtPrice->xtcFormat($order->getAmountItems(), true);
        $ordersTotal["value"]      = $order->getAmountItems();
        $ordersTotal["class"]      = "ot_subtotal";
        $ordersTotal["sort_order"] = $sort++;
        xtc_db_perform(TABLE_ORDERS_TOTAL, $ordersTotal);

        $this->log('_insertOrderTotal(): add shipping costs total', ShopgateLogger::LOGTYPE_DEBUG);

        $couponAmount = 0;
        $coupons      = $order->getExternalCoupons();
        if (!empty($coupons)) {
            foreach ($coupons as $coupon) {
                $couponAmount += $couponModel->insertOrderTotal($dbOrderId, $coupon, $sort++);
            }
        }

        $ordersTotal               = array();
        $ordersTotal["orders_id"]  = $dbOrderId;
        $ordersTotal["title"]      = xtc_db_prepare_input(
            MODULE_PAYMENT_SHOPGATE_ORDER_LINE_TEXT_SHIPPING . ($shippingInfos && $shippingInfos->getDisplayName()
                ? ' ('
                . $shippingInfos->getDisplayName() . ')'
                : ($shippingInfos && $shippingInfos->getName()
                    ? ' (' . $shippingInfos->getName() . ')'
                    : '')) . ':'
        );
        $ordersTotal["text"]       = $xtPrice->xtcFormat($shippingCosts, true);
        $ordersTotal["value"]      = $shippingCosts;
        $ordersTotal["class"]      = "ot_shipping";
        $ordersTotal["sort_order"] = $sort++;
        xtc_db_perform(TABLE_ORDERS_TOTAL, $ordersTotal);

        // insert payment costs.
        //
        //WARNING: On modify: Change the taxes calculation too!
        if ($order->getAmountShopPayment() != 0) {
            $this->log('db: save payment fee', ShopgateLogger::LOGTYPE_DEBUG);

            $paymentInfos    = $order->getPaymentInfos();
            $orderTotalClass = $order->getPaymentMethod() == ShopgateOrder::COD
                ? 'ot_cod_fee'
                : 'ot_shipping';

            $ordersTotal               = array();
            $ordersTotal["orders_id"]  = $dbOrderId;
            $ordersTotal["title"]      = xtc_db_prepare_input(
                MODULE_PAYMENT_SHOPGATE_ORDER_LINE_TEXT_PAYMENTFEE . (!empty($paymentInfos['shopgate_payment_name'])
                    ? ' ('
                    . $paymentInfos['shopgate_payment_name'] . '):'
                    : '')
            );
            $ordersTotal["text"]       = $xtPrice->xtcFormat($order->getAmountShopPayment(), true);
            $ordersTotal["value"]      = $order->getAmountShopPayment();
            $ordersTotal["class"]      = $orderTotalClass;
            $ordersTotal["sort_order"] = $sort++;
            xtc_db_perform(TABLE_ORDERS_TOTAL, $ordersTotal);
        }

        $this->log('_insertOrderTotal(): add tax totals', ShopgateLogger::LOGTYPE_DEBUG);

        // Shipping taxes are already stored in $taxes!
        $unitAmountWithoutTax = $amountWithTax;
        foreach ($taxes as $percent => $tax_value) {
            $ordersTotal               = array();
            $ordersTotal["orders_id"]  = $dbOrderId;
            $ordersTotal["title"]      = "inkl. UST {$percent} %:";
            $ordersTotal["text"]       = $xtPrice->xtcFormat($tax_value, true);
            $ordersTotal["value"]      = $tax_value;
            $ordersTotal["class"]      = "ot_tax";
            $ordersTotal["sort_order"] = $sort++;
            xtc_db_perform(TABLE_ORDERS_TOTAL, $ordersTotal);

            $unitAmountWithoutTax -= $tax_value;
        }

        $this->log('_insertOrderTotal(): add order total', ShopgateLogger::LOGTYPE_DEBUG);

        $ordersTotal               = array();
        $ordersTotal["orders_id"]  = $dbOrderId;
        $ordersTotal["title"]      = xtc_db_prepare_input(MODULE_PAYMENT_SHOPGATE_ORDER_LINE_TEXT_TOTAL_WITHOUT_TAX);
        $ordersTotal["text"]       = $xtPrice->xtcFormat($unitAmountWithoutTax, true);
        $ordersTotal["value"]      = $unitAmountWithoutTax;
        $ordersTotal["class"]      = "ot_total_netto";
        $ordersTotal["sort_order"] = $sort++;
        xtc_db_perform(TABLE_ORDERS_TOTAL, $ordersTotal);

        $ordersTotal               = array();
        $ordersTotal["orders_id"]  = $dbOrderId;
        $ordersTotal["title"]      = "<b>" . MODULE_PAYMENT_SHOPGATE_ORDER_LINE_TEXT_TOTAL . ":</b>";
        $ordersTotal["text"]       = "<b>" . $xtPrice->xtcFormat($amountWithTax, true) . "</b>";
        $ordersTotal["value"]      = $amountWithTax;
        $ordersTotal["class"]      = "ot_total";
        $ordersTotal["sort_order"] = $sort++;
        xtc_db_perform(TABLE_ORDERS_TOTAL, $ordersTotal);
    }

    /**
     * calculate the tax amount to an order
     *
     * @param ShopgateOrder $order
     * @param int           $shippingTaxRate
     *
     * @return array
     */
    private function getOrderTaxes(ShopgateOrder $order, $shippingTaxRate = 0)
    {
        $this->log('_getOrderTaxes(): start', ShopgateLogger::LOGTYPE_DEBUG);

        $taxes = $taxRates = array();

        foreach ($order->getItems() as $orderItem) {
            $tax       = $orderItem->getTaxPercent();
            $tax       = (string)(round($tax * 100) / 100);
            $tax_value = $orderItem->getUnitAmountWithTax() - ($orderItem->getUnitAmountWithTax() / (1 + $tax / 100));

            if (!isset($taxes[$tax])) {
                $taxes[$tax]    = 0;
                $taxRates[$tax] = $tax;
            }
            $taxes[$tax] += $tax_value * $orderItem->getQuantity();
        }

        $couponTax = 0;
        foreach ($order->getExternalCoupons() as $externalCoupon) {
            $couponTax += $externalCoupon->getAmountGross() - $externalCoupon->getAmountNet();
        }

        while (($couponTax > 0) && (count($taxRates) > 0)) {
            $maxRate = max($taxRates);
            unset($taxRates[$maxRate]);
            if ($taxes[$maxRate] > $couponTax) {
                $taxes[$maxRate] -= $couponTax;
                $couponTax       = 0;
            } else {
                $couponTax       -= $taxes[$maxRate];
                $taxes[$maxRate] = 0;
            }
        }

        foreach ($taxes as $key => $taxval) {
            $taxes[$key] = round($taxval * 100) / 100;
        }

        if (!empty($shippingTaxRate)) {
            $shippingTaxRate = (string)(round($shippingTaxRate * 100) / 100);
            if (!isset($taxes[$shippingTaxRate])) {
                $taxes[$shippingTaxRate] = 0;
            }
            $taxes[$shippingTaxRate] += $order->getAmountShipping() - $this->getOrderShippingAmountWithoutTax(
                    $order,
                    $shippingTaxRate
                );
        }

        // set taxes for payment method
        if ($order->getAmountShopPayment() != 0) {
            $tax       = $order->getPaymentTaxPercent();
            $tax       = (string)(round($tax * 100) / 100);
            $tax_value = $order->getAmountShopPayment() - round(
                    ($order->getAmountShopPayment() * 100) / ($order->getPaymentTaxPercent() + 100),
                    2
                );

            if (!isset($taxes[$tax])) {
                $taxes[$tax] = 0;
            }
            $taxes[$tax] += $tax_value;
        }
        $this->log('_getOrderTaxes(): end', ShopgateLogger::LOGTYPE_DEBUG);

        return $taxes;
    }

    /**
     * calculate the shipping amount to an order with taxes
     *
     * @param ShopgateOrder $order
     * @param int           $shippingTaxRate
     *
     * @return float|int
     */
    private function getOrderShippingAmountWithoutTax(ShopgateOrder $order, $shippingTaxRate = 0)
    {
        $shippingAmountWithoutTax = $order->getAmountShipping();

        // Check if a shipping method is set in config
        if (!empty($shippingTaxRate)) {
            // remove tax from shipping costs
            $shippingAmountWithoutTax /= 1 + $shippingTaxRate / 100;
        }

        return $shippingAmountWithoutTax;
    }

    /**
     * store custom field data to an order into the database
     *
     * @param ShopgateOrder $order
     * @param int           $dbOrderId
     * @param int           $orderStatusId
     *
     * @return array
     */
    private function storeCustomFields($order, $dbOrderId, $orderStatusId)
    {
        $orderData             = array();
        $addressFieldBlacklist = array();

        if (count(
                $customFieldsMapOrder = $this->getCustomFieldsMap(
                    TABLE_ORDERS,
                    $order->getCustomFields(),
                    '',
                    $addressFieldBlacklist
                )
            ) > 0
        ) {
            $orderData = array_merge($orderData, $customFieldsMapOrder);
        }

        if (count(
                $customFieldsMapShipping = $this->getCustomFieldsMap(
                    TABLE_ORDERS,
                    $order->getDeliveryAddress()->getCustomFields(),
                    'delivery',
                    $addressFieldBlacklist
                )
            ) > 0
        ) {
            $orderData = array_merge($orderData, $customFieldsMapShipping);
        }
        if (count(
                $customFieldsMapInvoice = $this->getCustomFieldsMap(
                    TABLE_ORDERS,
                    $order->getInvoiceAddress()->getCustomFields(),
                    'billing',
                    $addressFieldBlacklist
                )
            ) > 0
        ) {
            $orderData = array_merge($orderData, $customFieldsMapInvoice);
        }
        $ordersData        = array();
        $customFieldsOrder = $this->buildCustomFieldsRows($order->getCustomFields(), $customFieldsMapOrder);
        if (!empty($customFieldsOrder)) {
            $ordersData[self::SG_CF_ORDER] = $customFieldsOrder;
        }
        $customFieldsInvoiceAddress =
            $this->buildCustomFieldsRows($order->getInvoiceAddress()->getCustomFields(), $customFieldsMapInvoice);
        if (!empty($customFieldsInvoiceAddress)) {
            $ordersData[self::SG_CF_ORDER_INVOICE_ADDRESS] = $customFieldsInvoiceAddress;
        }
        $customFieldsShippingAddress =
            $this->buildCustomFieldsRows($order->getDeliveryAddress()->getCustomFields(), $customFieldsMapShipping);
        if (!empty($customFieldsShippingAddress)) {
            $ordersData[self::SG_CF_ORDER_SHIPPING_ADDRESS] = $customFieldsShippingAddress;
        }

        $histories = array();

        foreach ($ordersData as $key => $value) {
            switch ($key) {
                case self::SG_CF_ORDER:
                    array_push($histories, SHOPGATE_ORDER_ORDER . "\n" . $value);
                    break;
                case self::SG_CF_ORDER_INVOICE_ADDRESS:
                    array_push($histories, SHOPGATE_ORDER_INVOICE_ADDRESS . "\n" . $value);
                    break;
                case self::SG_CF_ORDER_SHIPPING_ADDRESS:
                    array_push($histories, SHOPGATE_ORDER_SHIPPING_ADDRESS . "\n" . $value);
                    break;
            }
        }

        foreach ($histories as $historyValue) {
            $history = array(
                "orders_id"         => $dbOrderId,
                "orders_status_id"  => $orderStatusId,
                "date_added"        => date('Y-m-d H:i:s'),
                "customer_notified" => false,
                "comments"          => xtc_db_prepare_input($historyValue),
            );

            xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $history);
        }

        return $orderData;
    }

    /**
     * generate a string which contains custom field data <label>:<value>
     *
     * @param array $customFields
     * @param array $customFieldBlacklist
     *
     * @return string
     */
    private function buildCustomFieldsRows(array $customFields, array $customFieldBlacklist = array())
    {
        $customFieldRows = array();

        foreach ($customFields as $customField) {
            /** @var ShopgateOrderCustomField $customField */
            if (array_key_exists($customField->getInternalFieldName(), $customFieldBlacklist)) {
                continue;
            }
            $customFieldRows[] = $customField->getLabel() . ': ' . $customField->getValue();
        }

        return implode("\n", $customFieldRows);
    }

    /**
     * send order data to afterbuy
     *
     * @param int           $iOrderId
     * @param ShopgateOrder $order
     */
    private function pushOrderToAfterbuy($iOrderId, ShopgateOrder $order)
    {
        if (!$order->getIsShippingBlocked() && defined('AFTERBUY_ACTIVATED') && AFTERBUY_ACTIVATED == 'true') {
            $this->log("START TO SEND ORDER TO AFTERBUY", ShopgateLogger::LOGTYPE_ACCESS);

            require_once(DIR_WS_CLASSES . 'afterbuy.php');
            /** @var xtc_afterbuy_functions_ORIGIN $aBUY */
            $aBUY = new xtc_afterbuy_functions($iOrderId);
            if ($aBUY->order_send()) {
                $aBUY->process_order();
                $this->log("SUCCESSFUL ORDER SEND TO AFTERBUY", ShopgateLogger::LOGTYPE_ACCESS);
            } else {
                $this->log("ORDER ALREADY SEND TO AFTERBUY", ShopgateLogger::LOGTYPE_ACCESS);
            }

            $this->log("FINISH SEND ORDER TO AFTERBUY", ShopgateLogger::LOGTYPE_ACCESS);
        }
    }

    /**
     * send order data to dreambot
     *
     * @param int           $dbOrderId
     * @param ShopgateOrder $shopgateOrder
     *
     * @throws ShopgateLibraryException
     */
    private function pushOrderToDreamRobot($dbOrderId, ShopgateOrder $shopgateOrder)
    {
        if (!$shopgateOrder->getIsShippingBlocked() && file_exists(DIR_FS_CATALOG . 'dreamrobot_checkout.inc.php')) {
            require_once(DIR_FS_CATALOG . 'includes/classes/order.php');
            $this->log("START TO SEND ORDER TO DREAMROBOT", ShopgateLogger::LOGTYPE_ACCESS);

            if (class_exists('order')) {
                $order = new order($dbOrderId);
            } elseif (class_exists('order_ORIGIN')) {
                /** @var order_ORIGIN $order */
                $order = new order_ORIGIN($dbOrderId);
            } else {
                throw new ShopgateLibraryException(
                    ShopgateLibraryException::UNKNOWN_ERROR_CODE, '_pushOrderToDreamRobot(): class order not found',
                    true
                );
            }
            $order->info['shipping_cost'] = $shopgateOrder->getAmountShipping();
            $_SESSION['tmp_oID']          = $dbOrderId;
            include_once('./dreamrobot_checkout.inc.php');

            $this->log("FINISH SEND ORDER TO DREAMROBOT", ShopgateLogger::LOGTYPE_ACCESS);
        }
    }

    public function updateOrder(ShopgateOrder $order)
    {
        // save UTF-8 payment infos (to build proper json)
        $paymentInfoUtf8 = $order->getPaymentInfos();

        // data needs to be utf-8 decoded for äöüß and the like to be saved correctly
        $order = $order->utf8Decode($this->config->getEncoding());
        if ($order instanceof ShopgateOrder) {
            ;
        } // for Eclipse auto-completion

        $qry
                 = "
        SELECT
            o.*,
            so.shopgate_order_id,
            so.shopgate_order_number,
            so.is_paid,
            so.is_shipping_blocked,
            so.payment_infos
        FROM " . TABLE_ORDERS . " o
        INNER JOIN " . TABLE_ORDERS_SHOPGATE_ORDER . " so ON (so.orders_id = o.orders_id)
        WHERE so.shopgate_order_number = '{$order->getOrderNumber()}'
        ";
        $result  = xtc_db_query($qry);
        $dbOrder = xtc_db_fetch_array($result);

        if ($dbOrder == false) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_ORDER_NOT_FOUND, "Shopgate order number: '{$order->getOrderNumber()}'."
            );
        }

        $errorOrderStatusIsSent                     = false;
        $errorOrderStatusAlreadySet                 = array();
        $statusShoppingsystemOrderIsPaid            = $dbOrder['is_paid'];
        $statusShoppingsystemOrderIsShippingBlocked = $dbOrder['is_shipping_blocked'];
        $status                                     = $dbOrder["orders_status"];

        // check if shipping is already done, then throw at end of method a OrderStatusIsSent - Exception
        if ($status == $this->config->getOrderStatusShipped()
            && ($statusShoppingsystemOrderIsShippingBlocked
                || $order->getIsShippingBlocked())
        ) {
            $errorOrderStatusIsSent = true;
        }

        if ($order->getUpdatePayment() == 1) {
            if (!is_null($statusShoppingsystemOrderIsPaid) && $order->getIsPaid() == $statusShoppingsystemOrderIsPaid
                && !is_null($dbOrder['payment_infos'])
                && $dbOrder['payment_infos'] == $this->jsonEncode($paymentInfoUtf8)
            ) {
                $errorOrderStatusAlreadySet[] = 'payment';
            }

            if (!is_null($statusShoppingsystemOrderIsPaid) && $order->getIsPaid() == $statusShoppingsystemOrderIsPaid) {
                // do not update is_paid
            } else {

                // Save order status
                $orderStatus                      = array();
                $orderStatus["orders_id"]         = $dbOrder["orders_id"];
                $orderStatus["orders_status_id"]  = $status;
                $orderStatus["date_added"]        = date('Y-m-d H:i:s');
                $orderStatus["customer_notified"] = false;

                if ($order->getIsPaid()) {
                    $orderStatus['comments'] = 'Bestellstatus von Shopgate geändert: Zahlung erhalten';
                } else {
                    $orderStatus['comments'] = 'Bestellstatus von Shopgate geändert: Zahlung noch nicht erhalten';
                }

                $orderStatus['comments'] =
                    $this->stringFromUtf8($orderStatus['comments'], $this->config->getEncoding());

                xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $orderStatus);

                // update the shopgate order status information
                $ordersShopgateOrder = array(
                    "is_paid"  => (int)$order->getIsPaid(),
                    "modified" => "now()",
                );
                xtc_db_perform(
                    TABLE_ORDERS_SHOPGATE_ORDER,
                    $ordersShopgateOrder,
                    "update",
                    "shopgate_order_id = {$dbOrder['shopgate_order_id']}"
                );

                // Save status in order
                $orderData                  = array();
                $orderData["orders_status"] = $status;
                $orderData["last_modified"] = date('Y-m-d H:i:s');
                xtc_db_perform(TABLE_ORDERS, $orderData, "update", "orders_id = {$dbOrder['orders_id']}");
            }

            // update paymentinfos
            if (!is_null($dbOrder['payment_infos'])
                && $dbOrder['payment_infos'] != $this->jsonEncode(
                    $paymentInfoUtf8
                )
            ) {
                $dbPaymentInfos = $this->jsonDecode($dbOrder['payment_infos'], true);
                $paymentInfos   = $order->getPaymentInfos();
                $histories      = array();

                switch ($order->getPaymentMethod()) {
                    case ShopgateOrder::SHOPGATE:
                    case ShopgateOrder::INVOICE:
                    case ShopgateOrder::COD:
                        break;
                    case ShopgateOrder::PREPAY:
                        if (isset($dbPaymentInfos['purpose'])
                            && $paymentInfos['purpose'] != $dbPaymentInfos['purpose']
                        ) {
                            $comments
                                      = $this->stringFromUtf8(
                                "Shopgate: Zahlungsinformationen wurden aktualisiert: \n\nDer Kunde wurde angewiesen Ihnen das Geld mit dem Verwendungszweck \"",
                                $this->config->getEncoding()
                            );
                            $comments .= $paymentInfos["purpose"];
                            $comments .= $this->stringFromUtf8(
                                "\" auf Ihr Bankkonto zu überweisen",
                                $this->config->getEncoding()
                            );

                            // Order is not paid yet
                            $histories[] = array(
                                "orders_id"         => $dbOrder["orders_id"],
                                "orders_status_id"  => $status,
                                "date_added"        => date('Y-m-d H:i:s'),
                                "customer_notified" => false,
                                "comments"          => xtc_db_prepare_input($comments),
                            );
                        }

                        break;
                    case ShopgateOrder::DEBIT:
                        $qry
                                        = "
                            SELECT
                                *
                            FROM banktransfer b
                            WHERE b.orders_id = '{$dbOrder['orders_id']}'";
                        $result         = xtc_db_query($qry);
                        $dbBanktransfer = xtc_db_fetch_array($result);

                        if (!empty($dbBanktransfer)) {
                            $banktransferData                          = array();
                            $banktransferData["banktransfer_owner"]    = $paymentInfos["bank_account_holder"];
                            $banktransferData["banktransfer_number"]   = $paymentInfos["bank_account_number"];
                            $banktransferData["banktransfer_bankname"] = $paymentInfos["bank_name"];
                            $banktransferData["banktransfer_blz"]      = $paymentInfos["bank_code"];
                            xtc_db_perform(
                                "banktransfer",
                                $banktransferData,
                                "update",
                                "orders_id = {$dbOrder['orders_id']}"
                            );

                            $comments = $this->stringFromUtf8(
                                "Shopgate: Zahlungsinformationen wurden aktualisiert: \n\n",
                                $this->config->getEncoding()
                            );
                            $comments .= $this->createPaymentInfo(
                                $paymentInfos,
                                $dbOrder['orders_id'],
                                $status,
                                false
                            );

                            $histories[] = array(
                                "orders_id"         => $dbOrder["orders_id"],
                                "orders_status_id"  => $status,
                                "date_added"        => date('Y-m-d H:i:s'),
                                "customer_notified" => false,
                                "comments"          => xtc_db_prepare_input($comments),
                            );
                        }

                        break;
                    case ShopgateOrder::PAYPAL:

                        // Save paymentinfos in history
                        $history             =
                            $this->createPaymentInfo($paymentInfos, $dbOrder["orders_id"], $status);
                        $history['comments'] = $this->stringFromUtf8(
                                "Shopgate: Zahlungsinformationen wurden aktualisiert: \n\n",
                                $this->config->getEncoding()
                            )
                            . $history['comments'];
                        $histories[]         = $history;

                        $this->updatePaypalOrder($order, $dbOrder["orders_id"]);

                        break;
                    default:
                        // mobile_payment

                        // Save paymentinfos in history
                        $history             =
                            $this->createPaymentInfo($paymentInfos, $dbOrder["orders_id"], $status);
                        $history['comments'] = $this->stringFromUtf8(
                                "Shopgate: Zahlungsinformationen wurden aktualisiert: \n\n",
                                $this->config->getEncoding()
                            )
                            . $history['comments'];
                        $histories[]         = $history;

                        break;
                }

                foreach ($histories as $history) {
                    xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $history);
                }
            }

            $ordersShopgateOrder = array(
                "payment_infos" => $this->jsonEncode($paymentInfoUtf8),
                "modified"      => "now()",
            );
            xtc_db_perform(
                TABLE_ORDERS_SHOPGATE_ORDER,
                $ordersShopgateOrder,
                "update",
                "shopgate_order_id = {$dbOrder['shopgate_order_id']}"
            );
        }

        if ($order->getUpdateShipping() == 1) {
            if (!is_null($statusShoppingsystemOrderIsShippingBlocked)
                && $order->getIsShippingBlocked() == $statusShoppingsystemOrderIsShippingBlocked
            ) {
                $errorOrderStatusAlreadySet[] = 'shipping';
            } else {
                if ($status != $this->config->getOrderStatusShipped()) {
                    if ($order->getIsShippingBlocked() == 1) {
                        $status = $this->config->getOrderStatusShippingBlocked();
                    } else {
                        $status = $this->config->getOrderStatusOpen();
                    }
                }

                $orderStatus                      = array();
                $orderStatus["orders_id"]         = $dbOrder["orders_id"];
                $orderStatus["date_added"]        = date('Y-m-d H:i:s');
                $orderStatus["customer_notified"] = false;
                $orderStatus['orders_status_id']  = $status;

                if ($order->getIsShippingBlocked() == 0) {
                    $orderStatus["comments"] = "Bestellstatus von Shopgate geändert: Versand ist nicht mehr blockiert!";
                } else {
                    $orderStatus['comments'] = 'Bestellstatus von Shopgate geändert: Versand ist blockiert!';
                }

                $orderStatus['comments'] =
                    $this->stringFromUtf8($orderStatus['comments'], $this->config->getEncoding());

                xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $orderStatus);

                $ordersShopgateOrder = array(
                    "is_shipping_blocked" => (int)$order->getIsShippingBlocked(),
                    "modified"            => "now()",
                );
                xtc_db_perform(
                    TABLE_ORDERS_SHOPGATE_ORDER,
                    $ordersShopgateOrder,
                    "update",
                    "shopgate_order_id = {$dbOrder['shopgate_order_id']}"
                );

                // Save status in order
                $orderData                  = array();
                $orderData["orders_status"] = $status;
                $orderData["last_modified"] = date('Y-m-d H:i:s');
                xtc_db_perform(TABLE_ORDERS, $orderData, "update", "orders_id = {$dbOrder['orders_id']}");

                $this->pushOrderToAfterbuy($dbOrder["orders_id"], $order);
                $this->pushOrderToDreamRobot($dbOrder["orders_id"], $order);
            }
        }

        if ($errorOrderStatusIsSent) {
            throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_ORDER_STATUS_IS_SENT);
        }

        if (!empty($errorOrderStatusAlreadySet)) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_ORDER_ALREADY_UP_TO_DATE, implode(',', $errorOrderStatusAlreadySet),
                true
            );
        }

        return array(
            'external_order_id'     => $dbOrder["orders_id"],
            'external_order_number' => $dbOrder["orders_id"],
        );
    }

    public function getOrders(
        $customerToken,
        $customerLanguage,
        $limit = 10,
        $offset = 0,
        $orderDateFrom = '',
        $sortOrder = 'created_desc'
    ) {
        $customerModel = new ShopgateCustomerModel($this->config, $this->languageId);
        $customerId    = $customerModel->getCustomerIdByToken($customerToken);

        if (empty($customerId)) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_WRONG_USERNAME_OR_PASSWORD, 'Username or password is incorrect'
            );
        }

        $customerOrderModel = new ShopgateCustomerOrderModel(
            $customerId,
            $this->splitStreetHouseNumber,
            $this->useStreet2
        );

        return $customerOrderModel->getOrders($customerLanguage, $limit, $offset, $orderDateFrom, $sortOrder);
    }

    public function syncFavouriteList($customerToken, $items)
    {
        // TODO: Implement syncFavouriteList() method.
    }

    public function redeemCoupons(ShopgateCart $cart)
    {
        // TODO: Implement redeemCoupons() method.
    }

    /**
     * generate an array which contains tax and customer group information to the merchants shop
     *
     * @return array
     *
     * @throws ShopgateLibraryException
     */
    public function getSettings()
    {
        $locationModel       = new ShopgateLocationModel($this->config);
        $customerModel       = new ShopgateCustomerModel($this->config, $this->languageId);
        $taxRatesAndTaxRules = $locationModel->getTaxRatesAndTaxRules();

        $customerTaxClass = array(
            'id'         => "1",
            'key'        => 'default',
            'is_default' => "1",
        );

        $settings = array(
            "customer_groups"            => $customerModel->getCustomerGroups(),
            "tax"                        => array(
                "product_tax_classes"  => $locationModel->getTaxClasses(),
                "customer_tax_classes" => array($customerTaxClass),
                "tax_rates"            => $taxRatesAndTaxRules['tax_rates'],
                "tax_rules"            => $taxRatesAndTaxRules['tax_rules'],
            ),
            "allowed_address_countries"  => $this->getAllowedAddressCountries(),
            "allowed_shipping_countries" => $this->getAllowedShippingCountries(),
        );

        return $settings;
    }

    /**
     * read allowed address countries from the database
     *
     * @return array
     * @throws ShopgateLibraryException
     */
    private function getAllowedAddressCountries()
    {
        require_once(DIR_FS_INC . 'xtc_get_countries.inc.php');
        require_once(DIR_FS_INC . 'xtc_get_geo_zone_code.inc.php');
        $locationModel = new ShopgateLocationModel($this->config);
        $countryList   = xtc_get_countriesList();
        $resultArr     = array();
        foreach ($countryList as $country) {
            $geoCode = xtc_get_geo_zone_code($country["countries_id"]);
            if (!empty($geoCode) && !empty($country["countries_id"])) {
                $stateArray  = $locationModel->getZonesByZoneIdAndCountryId($geoCode, $country["countries_id"]);
                $resultArr[] = array(
                    "country" => $country,
                    "state"   => $stateArray,
                );
            }
        }

        return $resultArr;
    }

    /**
     * read allowed shipping countries from the database
     *
     * @return array
     */
    private function getAllowedShippingCountries()
    {
        $resultArr     = array();
        $shippingModel = new ShopgateShippingModel();
        $result        = $shippingModel->getShippingCountriesFromConstants();
        $locationModel = new ShopgateLocationModel($this->config);

        while ($shippingCountries = xtc_db_fetch_array($result)) {
            foreach ($shippingCountries as &$shippingCountry) {
                $shippingCountry = $this->stringToUtf8($shippingCountry, $this->config->getEncoding());
            }
            $tmpCountries = explode(",", $shippingCountries["countries"]);
            foreach ($tmpCountries as $tmpCountry) {
                // $resultArr passed as ref
                $locationModel->getZonesByCountryIsoCode2($tmpCountry, $resultArr);
            }
        }
        $resultArr = array_values($resultArr);

        return $resultArr;
    }

    public function createShopInfo()
    {
        $shopInfo = array();

        $productCountQuery      = "SELECT count(*) cnt FROM `" . TABLE_PRODUCTS . "` AS p WHERE p.products_status = 1";
        $result                 = xtc_db_query($productCountQuery);
        $row                    = xtc_db_fetch_array($result);
        $shopInfo['item_count'] = $row['cnt'];

        $catQry                     = "SELECT count(*) cnt FROM `" . TABLE_CATEGORIES . "`";
        $result                     = xtc_db_query($catQry);
        $row                        = xtc_db_fetch_array($result);
        $shopInfo['category_count'] = $row['cnt'];

        $revQry                   = "SELECT COUNT(*) AS cnt FROM `" . TABLE_REVIEWS . "`";
        $result                   = xtc_db_query($revQry);
        $row                      = xtc_db_fetch_array($result);
        $shopInfo['review_count'] = $row['cnt'];

        // Not provided by Osc
        $shopInfo['plugins_installed '] = array();

        return $shopInfo;
    }

    public function checkStock(ShopgateCart $cart)
    {
        $result        = array();
        $cartItemModel = new ShopgateItemCartModel($this->languageId);
        $dbItems       = $cartItemModel->getCartItemsFromDatabase($cart);
        $stockHelper   = new ShopgateStockHelper();

        foreach ($cart->getItems() as $cartItem) {
            $productsId = $cartItemModel->getProductsIdFromCartItem($cartItem);
            $itemRow    = !empty($dbItems[$productsId])
                ? $dbItems[$productsId]
                : array();

            $tmpItem = new ShopgateCartItem();
            $tmpItem->setItemNumber($cartItem->getItemNumber());

            // Create "error" item if not found
            if (empty($itemRow)) {
                $tmpItem->setError(ShopgateLibraryException::CART_ITEM_PRODUCT_NOT_FOUND);
                $tmpItem->setErrorText(
                    sprintf(
                        'The item with item number %d and products id %d was not found.',
                        $cartItem->getItemNumber(),
                        $productsId
                    )
                );
                $tmpItem->setIsBuyable(false);
                $tmpItem->setStockQuantity(0);
            } else {
                $quantity = $stockHelper->getStockForOrderItem($cartItem, $dbItems[$productsId], false);
                $tmpItem->setIsBuyable(true);
                if (null === $quantity) {
                    $quantity = $stockHelper->getStockForOrderItem($cartItem, $dbItems[$productsId], true);
                } elseif ($quantity <= 0) {
                    $tmpItem->setIsBuyable(false);
                }

                $tmpItem->setStockQuantity($quantity);
            }

            // Keep given option and attribute data
            $tmpItem->setOptions($cartItem->getOptions());
            $tmpItem->setInputs($cartItem->getInputs());
            $tmpItem->setAttributes($cartItem->getAttributes());

            $result[] = $tmpItem;
        }

        return $result;
    }

    /**
     * Set the shipping status for a list of order IDs.
     *
     * @param int[] $orderIds The IDs of the orders in the shop system.
     * @param int   $status   The ID of the order status that has been set in the shopping system.
     */
    public function updateOrdersStatus($orderIds, $status)
    {
        $query  = xtc_db_input(
            "SELECT `sgo`.`orders_id`, `sgo`.`shopgate_order_number`, `xtl`.`code` " .
            "FROM `" . TABLE_ORDERS_SHOPGATE_ORDER . "` sgo " .
            "INNER JOIN `" . TABLE_ORDERS . "` xto ON (`xto`.`orders_id` = `sgo`.`orders_id`) " .
            "INNER JOIN `" . TABLE_LANGUAGES . "` xtl ON (`xtl`.`directory` = `xto`.`language`) " .
            "WHERE `sgo`.`orders_id` IN (" . xtc_db_input(implode(", ", $orderIds)) . ")"
        );
        $result = xtc_db_query($query);

        if (empty($result)) {
            return;
        }

        /** @var ShopgateConfigGambioGx[] $configurations */
        $configurations = array();
        $merchantApis   = array();
        while ($shopgateOrder = xtc_db_fetch_array($result)) {
            $language = $shopgateOrder['code'];

            if (empty($merchantApis[$language])) {
                try {
                    $config = new ShopgateConfigGambioGx();
                    $config->loadByLanguage($language);
                    $builder                   = new ShopgateBuilder($config);
                    $merchantApis[$language]   = &$builder->buildMerchantApi();
                    $configurations[$language] = $config;
                } catch (ShopgateLibraryException $e) {
                    // do not abort. the error will be logged
                }
            }

            if ($status == $configurations[$language]->getOrderStatusShipped()) {
                $this->setOrderShippingCompleted(
                    $shopgateOrder['shopgate_order_number'],
                    $shopgateOrder['orders_id'],
                    $merchantApis[$language]
                );
            }
            if ($status == $configurations[$language]->getOrderStatusCanceled()) {
                $this->sendOrderCancellation($shopgateOrder['shopgate_order_number'], $merchantApis[$language]);
            }
        }
    }

    protected function createCategoriesCsv()
    {
        $this->log("[csv] Begin export categories", ShopgateLogger::LOGTYPE_DEBUG);

        $model = new ShopgateCategoryModel($this->config, $this->languageId);
        $model->setDefaultCategoryRow($this->buildDefaultCategoryRow());
        $categories = $model->getAllCategories($this->exportLimit, $this->exportOffset);

        foreach ($categories as $categorie) {
            $this->addCategoryRow($categorie);
        }

        $this->log("[csv] End export categories", ShopgateLogger::LOGTYPE_DEBUG);
    }

    protected function createCategories($limit = null, $offset = null, array $uids = array())
    {
        $this->log("[xml] Begin export categories", ShopgateLogger::LOGTYPE_DEBUG);

        $model = new ShopgateCategoryXmlModel($this->config, $this->languageId);
        $model->setDefaultCategoryRow($this->buildDefaultCategoryRow());
        $categories = $model->getAllCategories($limit, $offset, $uids);

        foreach ($categories as $category) {
            $actualModel = clone $model;
            $actualModel->setItem($category);
            $this->addCategoryModel($actualModel->generateData());
        }

        $this->log("[xml] End export categories", ShopgateLogger::LOGTYPE_DEBUG);
    }

    protected function createReviewsCsv()
    {
        $this->log("[csv] Begin export reviews", ShopgateLogger::LOGTYPE_DEBUG);

        $model   = new ShopgateReviewXmlModel($this->languageId);
        $limit   = 10;
        $page    = 1;
        $offset  = ($page - 1) * $limit;
        $results = array();

        while ($reviews = $model->getReviewData($limit, $offset)) {
            foreach ($reviews as $review) {
                $review['item_number']      = $review['products_id'];
                $review['update_review_id'] = $review['reviews_id'];
                $review['score']            = $review['reviews_rating'] * 2;
                $review['name']             = $review['customers_name'];
                $review['date']             = $review['date_added'];
                $review['title']            = '';
                $review['text']             = $review['reviews_text'];

                $results[] = $review;
            }

            $page++;
            $offset = ($page - 1) * $limit;
        }

        foreach ($results as $review) {
            $this->addReviewRow($review);
        }

        $this->log("[csv] End export reviews...", ShopgateLogger::LOGTYPE_DEBUG);
    }

    protected function createReviews($limit = null, $offset = null, array $uids = array())
    {
        $this->log("[xml] Begin export reviews", ShopgateLogger::LOGTYPE_DEBUG);

        $model   = new ShopgateReviewXmlModel($this->languageId);
        $reviews = $model->getReviewData($limit, $offset, $uids);

        foreach ($reviews as $review) {
            $actualModel = clone $model;
            $actualModel->setItem($review);
            $this->addReviewModel($actualModel->generateData());
        }

        $this->log("[xml] End export reviews", ShopgateLogger::LOGTYPE_DEBUG);
    }

    protected function createItems($limit = null, $offset = null, array $uids = array())
    {
        $this->log("[xml] Begin export products", ShopgateLogger::LOGTYPE_DEBUG);

        $customerGroupPrices    = array();
        $shopgateDatabaseHelper = new ShopgateDatabaseHelper();
        $customerModel          = new ShopgateCustomerModel($this->config, $this->languageId);
        $itemXmlModel           = new ShopgateItemXmlModel(
            $shopgateDatabaseHelper,
            new ShopgateItemPropertyHelper($shopgateDatabaseHelper, $this->config, $this->languageId),
            new ShopgateCustomizerSetHelper(),
            $this->config,
            $this->languageId,
            $this->currency,
            $this->countryId,
            $this->zoneId,
            $this->exchangeRate
        );

        foreach ($customerModel->getCustomerGroups() as $customerGroup) {
            $customerGroupPrices[$customerGroup['id']]                                             =
                new xtcPrice_ORIGIN($this->currency['code'], $customerGroup['id']);
            $customerGroupPrices[$customerGroup['id']]->cStatus['customers_status_show_price_tax'] = 1;
        }
        $itemXmlModel->setCustomerGroupPrices($customerGroupPrices);

        $productQuery   = $itemXmlModel->generateProductQuery($limit, $offset, $uids);
        $qryResult      = xtc_db_query($productQuery);
        $orderInfoDummy = "";

        while ($item = xtc_db_fetch_array($qryResult)) {
            if (empty($item['products_name']) || $itemXmlModel->isProductDeactivated($item, $orderInfoDummy)) {
                continue;
            }
            $productXmlModel = clone $itemXmlModel;
            $productXmlModel->setItem($item);
            $this->addItemModel($productXmlModel->generateData());
        }

        $this->log("[xml] End export products", ShopgateLogger::LOGTYPE_DEBUG);
    }

    protected function createItemsCsv()
    {
        $this->log("[csv] Begin export products", ShopgateLogger::LOGTYPE_DEBUG);

        $shopgateDatabaseHelper = new ShopgateDatabaseHelper();
        $itemModel              = new ShopgateItemModel(
            $shopgateDatabaseHelper,
            new ShopgateItemPropertyHelper($shopgateDatabaseHelper, $this->config, $this->languageId),
            new ShopgateCustomizerSetHelper(),
            $this->config,
            $this->languageId,
            $this->currency,
            $this->countryId,
            $this->zoneId,
            $this->exchangeRate
        );
        $locationModel          = new ShopgateLocationModel($this->config);
        $priceModel             = new ShopgatePriceModel($this->config, $this->languageId, $this->exchangeRate);
        $this->optionFieldArray = $itemModel->getOptionFieldArray();

        $uids = !empty($_REQUEST['item_numbers']) && is_array($_REQUEST['item_numbers'])
            ? $_REQUEST['item_numbers']
            : array();

        // init seo Boost GGX
        $itemModel->setSeoBoost($this->gmSEOBoost, $this->gmSEOBoostProductsEnabled);
        $gxCustomizerSets            = $itemModel->getGxCustomizerSets();
        $inputFieldsFromGxCustomizer = $itemModel->getInputFieldsFromGxCustomizerSets($gxCustomizerSets);

        $maxOrder = $minOrder = $addToOrderIndex = 0;
        $itemModel->getProductOrderLimits($maxOrder, $minOrder, $addToOrderIndex);

        $query = xtc_db_query($itemModel->generateProductQuery($this->exportLimit, $this->exportOffset, $uids));
        $this->log("execute SQL get products ...", ShopgateLogger::LOGTYPE_DEBUG);

        while ($item = xtc_db_fetch_array($query)) {
            $this->log("start export products_id = " . $item["products_id"] . " ...", ShopgateLogger::LOGTYPE_DEBUG);
            $orderInfos = array();
            $itemArr    = $this->buildDefaultItemRow();

            $tax_rate = $locationModel->getTaxRateToProduct(
                $item["products_tax_class_id"],
                false,
                $this->gambioGXVersion,
                $this->countryId,
                $this->zoneId
            );

            // Get variantions and input fields
            $variations = $itemModel->getVariations($item, $tax_rate, $this->exchangeRate);

            if (empty($variations)) {
                $variations = $itemModel->generatePropertyCombos($item, $tax_rate);
            }

            $inputFields = $itemModel->getInputFields($item, $inputFieldsFromGxCustomizer);

            // Check if both variant types are used by the product
            $deactivateProduct = false;
            if ($itemModel->isProductDeactivated($item, $orderInfos)) {
                $deactivateProduct = true;
                // no variations are exported in this case!
                $variations = array();
            }

            // Get categories
            $categories = $itemModel->getProductPath($item["products_id"]);

            // Get Image Urls
            $images = $itemModel->getProductsImages($item);

            $deepLink = $itemModel->generateDeepLink(
                $item['products_id'],
                $item['products_name'],
                $this->gmSEOBoostProductsEnabled,
                $this->gmSEOBoost
            );

            // Calculate the price $oldPrice and $rice passed by reference
            $productDiscount = $price = $oldPrice = 0;
            $priceModel->getPriceToItem($item, $tax_rate, $price, $oldPrice, $productDiscount);

            $category_numbers = $itemModel->getCategoryNumbers($item);

            // create the description, based on the settings
            $description = $itemModel->getProductDescription(
                $item,
                $this->removeTagsFromString($item["products_description"], array(), array('IFRAME')),
                $this->removeTagsFromString($item["products_short_description"]),
                $this->config->getExportProductsContentManagedFiles(),
                $this->config->getExportDescriptionType()
            );
            // -> Take all products content managed files to link them in the description if enabled in the settings
            $itemArr['item_number']                        = $item["products_id"];
            $itemArr['item_number_public']                 = $item['products_model'];
            $itemArr['manufacturer']                       = $item["manufacturers_name"];
            $itemArr['item_name']                          = $itemModel->generateItemName($item["products_name"]);
            $itemArr['description']                        = $description;
            $itemArr['unit_amount']                        = $this->formatPriceNumber($price);
            $itemArr['currency']                           = $this->currency["code"];
            $itemArr['is_available']                       = $item["products_status"];
            $itemArr['available_text']                     = $itemModel->getAvailableText($item);
            $itemArr['url_deeplink']                       = $deepLink;
            $itemArr['urls_images']                        = $images;
            $itemArr['categories']                         = $categories;
            $itemArr['category_numbers']                   = implode("||", $category_numbers);
            $itemArr['use_stock']                          = $itemModel->useStock();
            $itemArr['stock_quantity']                     = (int)$item['products_quantity'];
            $itemArr['minimum_order_quantity']             = $itemModel->getMinimumOrderQuantity($item);
            $itemArr['weight']                             = $itemModel->calculateWeight($item);
            $itemArr['tags']                               = trim($item["products_keywords"]);
            $itemArr['tax_percent']                        = $tax_rate;
            $itemArr['shipping_costs_per_order']           = 0;
            $itemArr['additional_shipping_costs_per_unit'] = $item['nc_ultra_shipping_costs'];
            $itemArr['ean']                                = preg_replace("/\s+/i", '', $item["products_ean"]);
            $itemArr['last_update']                        = $item["products_last_modified"];
            $itemArr['age_rating']                         = $item["products_fsk18"] == 1
                ? '18'
                : '';
            $itemArr['related_shop_item_numbers']          = $itemModel->getRelatedShopItems($item["products_id"]);
            $itemArr['basic_price']                        = $itemModel->getProductVPE($item, $price);
            $itemArr['is_highlight']                       = $item["products_startpage"];
            $itemArr['highlight_order_index']              = $item["products_startpage_sort"];

            $gmPriceStatusItemFields = $itemModel->generatePriceStatus($item, $oldPrice);

            // products can be exported as deactivated
            if ($deactivateProduct) {
                $itemArr['active_status'] = 'inactive';
            }

            if ($this->config->getReverseItemsSortOrder()) {
                // $addToOrderIndex to make positive sort_order
                $itemArr['sort_order'] = $item["products_sort"] + $addToOrderIndex;
            } else {
                $itemArr['sort_order'] = ($maxOrder - $item["products_sort"]) + $addToOrderIndex;
            }

            if (!empty($orderInfos)) {
                $itemArr['internal_order_info'] = $orderInfos;
            }

            if (!empty($oldPrice) && round($oldPrice, 2) > 0) {
                $itemArr['old_unit_amount'] = $this->formatPriceNumber($oldPrice);
            } else {
                $itemArr['old_unit_amount'] = '';
            }

            if ($itemArr['available_text'] == 'Unbekannt') {
                $itemArr['is_available'] = 0;
            }

            if (!empty($inputFields)) {
                $itemArr['has_input_fields'] = "1";
                $itemArr                     = array_merge($itemArr, $inputFields);
            }

            if (!empty($variations)) {
                if (!empty($variations["has_options"])) {
                    $itemArr['has_options'] = 1;

                    $itemArr = array_merge($itemArr, $variations);

                    if (!empty($gmPriceStatusItemFields)) {
                        $itemArr = $gmPriceStatusItemFields + $itemArr;
                    }

                    // calculate quantity staggering and modify product for this
                    $itemArr = $this->setQuantityStaggering($itemArr, $item['gm_graduated_qty']);

                    if (!empty($itemArr['internal_order_info'])) {
                        $itemArr['internal_order_info'] = $this->jsonEncode($itemArr['internal_order_info']);
                    }

                    $this->addItemRow($itemArr);
                } else {
                    if (isset($variations['has_options'])) {
                        unset($variations['has_options']);
                    }
                    $itemArr['has_children'] = 1;
                    $isFirst                 = true;

                    // Same input fields for child-products
                    if (!empty($inputFields)) {
                        $itemArr['has_input_fields'] = "1";
                        $itemArr                     = array_merge($itemArr, $inputFields);
                    }

                    // $itemArr is changed here to create a child-product, so save the parent to have the initial state of the product (parent)
                    $parentItem   = $itemArr;
                    $basePrice    = round($parentItem["unit_amount"], 2);
                    $baseOldPrice = round($parentItem["old_unit_amount"], 2);
                    foreach ($variations as $key => $variation) {
                        // Always start at a default item setting
                        $itemArr = $parentItem;

                        $price = 0;
                        // Offset amount including tax and exchange rate but without discounts
                        $originalOffsetAmount = 0;
                        // Variation can include an offset amount where the exchange rate and tax is already included
                        if (!empty($variation['offset_amount_with_tax'])) {
                            $originalOffsetAmount = $variation['offset_amount_with_tax'];

                            // Variations also need to be discounted if products discount is set
                            // IMPORTANT NOTICE:
                            // There is a customer group setting where the merchant can set if variations are also discounted by the specific value or not,
                            // but seems to be forgotten here completely so, it is completely ignored for products properties
                            // -> the products detail view shows always a discounted attribute, but the cart does not include the discount for the property-combination
                            // if (!empty($productDiscount) && round($productDiscount, 2) > 0) {
                            //     if ($customerGroupDiscountAttributes) { // Seems to be buggy in gambio so it is ignored here
                            //         $variation["offset_amount_with_tax"] = $this->getDiscountPrice($variation["offset_amount_with_tax"], $productDiscount);
                            //         $variations[$key]["offset_amount_with_tax"] = $variation["offset_amount_with_tax"];
                            //     }
                            // }

                            $price = $variation['offset_amount_with_tax'];
                        } elseif (!empty($variation["offset_amount"])) {
                            if (!empty($this->exchangeRate)) {
                                $variation["offset_amount"]        *= $this->exchangeRate;
                                $variations[$key]["offset_amount"] = $variation["offset_amount"];
                            }
                            $originalOffsetAmount = $variation["offset_amount"] * (1 + ($tax_rate / 100));

                            // Variations also need to be discounted if products discount is set
                            // IMPORTANT NOTICE (different from above!):
                            // There is a customer group setting where the merchant can set if variations are also discounted by the specific value or not,
                            // but this setting seems to be ignored in the frontend, so it's ignored here, too.
                            if (!empty($productDiscount) && round($productDiscount, 2) > 0) {
                                //                                if ($customerGroupDiscountAttributes) { // Seems to be buggy in gambio so it is ignored here
                                $variation["offset_amount"]        =
                                    $priceModel->getDiscountPrice($variation["offset_amount"], $productDiscount);
                                $variations[$key]["offset_amount"] = $variation["offset_amount"];
                                //                                }
                            }

                            $price = $variation["offset_amount"] * (1 + ($tax_rate / 100));
                        }

                        $hash = "";

                        for ($i = 1; $i < 10 && isset($variation["attribute_$i"]); $i++) {
                            $hash                    .= $variation["attribute_$i"];
                            $itemArr["attribute_$i"] =
                                htmlentities($variation["attribute_$i"], ENT_NOQUOTES, $this->config->getEncoding());
                        }

                        $hash = md5($hash);
                        $hash = substr($hash, 0, 5);
                        if (empty($variation)) {
                            $variation = array("order_info" => array());
                        }

                        // Set Order Info from parent product
                        if (!empty($variation['order_info']) && is_array($variation['order_info'])) {
                            $variation["order_info"] = array_merge($orderInfos, $variation['order_info']);
                        } else {
                            $variation["order_info"] = $orderInfos;
                        }

                        $variation["order_info"]["base_item_number"] = $parentItem["item_number"];

                        $imagesTemp = explode('||', $parentItem["urls_images"]);
                        if (isset($variation["images"])) {
                            $itemArr["urls_images"] = implode("||", array_merge($variation["images"], $imagesTemp));
                        } else {
                            $itemArr["urls_images"] = implode("||", $imagesTemp);
                        }

                        $itemArr['internal_order_info'] = $variation["order_info"];

                        $itemArr["item_number"] = $parentItem["item_number"] . ($isFirst
                                ? ""
                                : "_" . $hash);
                        if (!empty($variation["item_number"])) {
                            $itemArr["item_number_public"] = $variation["item_number"];
                        } else {
                            $itemArr['item_number_public'] = $item['products_model'];
                        }

                        $itemArr["unit_amount"] = $this->formatPriceNumber($basePrice + $price);
                        if (!empty($baseOldPrice) && round($baseOldPrice, 2) > 0) {
                            $itemArr["old_unit_amount"] =
                                $this->formatPriceNumber($baseOldPrice + $originalOffsetAmount);
                        } else {
                            $itemArr["old_unit_amount"] = '';
                        }

                        // Check if there is a weight that completely replacec the product weight
                        if (isset($variation["fixed_weight"])) {
                            $itemArr["weight"] = $variation["fixed_weight"] * 1000;
                        } else {
                            // Get offset weight data for attribute otherwise
                            if (isset($variation["offset_weight"])) {
                                $weight            = $variation["offset_weight"] * 1000;
                                $itemArr["weight"] = $parentItem["weight"] + $weight;
                            }
                        }

                        // Stock differs in products_properties_combis
                        if (!empty($variation['order_info']['is_property_attribute'])) {
                            if (isset($variation['use_stock']) && $variation['use_stock'] == '0') {
                                $itemArr["use_stock"] = 0;
                            } else {
                                $itemArr["use_stock"] = $parentItem['use_stock'];
                            }

                            if (!empty($variation['use_parents_stock_quantity'])) {
                                $itemArr["stock_quantity"] = $parentItem["stock_quantity"];
                            } else {
                                $itemArr["stock_quantity"] = $variation["stock_quantity"];
                            }
                        } else {
                            if ($isFirst == false) {
                                $itemArr["use_stock"] =
                                    (STOCK_ALLOW_CHECKOUT == 'true' || ATTRIBUTE_STOCK_CHECK != 'true')
                                        ? 0
                                        : 1;
                            }

                            // Overwrite stock only if its set up in the configuration
                            if (ATTRIBUTE_STOCK_CHECK == 'true' && $isFirst == false) {
                                if (!empty($item["specials_new_products_price"]) && $item["specials_quantity"] > 0) {
                                    $itemArr["stock_quantity"] =
                                        $variation["stock_quantity"] > $item["specials_quantity"]
                                            ? $item["specials_quantity"]
                                            : $variation["stock_quantity"];
                                } else {
                                    $itemArr["stock_quantity"] = $variation["stock_quantity"];
                                }
                            }
                        }

                        // Take shipping status from variation if given
                        if (!empty($variation['shipping_status_name'])) {
                            $itemArr['available_text'] =
                                $itemModel->getAvailableText($item, $variation['shipping_status_name']);
                        } else {
                            $itemArr['available_text'] =
                                $itemModel->getAvailableText($item, $parentItem['available_text']);
                        }

                        $itemArr['properties'] = $itemModel->buildCsvProperties($item);

                        // recalculate basic price
                        $itemArr['basic_price'] = $itemModel->getProductVPE($item, $itemArr['unit_amount'], $variation);

                        // First element is the parent
                        if (!$isFirst) {
                            $itemArr['has_children']       = 0;
                            $itemArr["parent_item_number"] = $parentItem["item_number"];
                        } else {
                            $isFirst = false;
                        }

                        // calculate quantity staggering and modify product for this
                        $itemArr = $this->setQuantityStaggering($itemArr, $item['gm_graduated_qty']);

                        $itemArr['internal_order_info'] = $this->jsonEncode($itemArr['internal_order_info']);

                        if (!empty($gmPriceStatusItemFields)) {
                            $itemArr = $gmPriceStatusItemFields + $itemArr;
                        }

                        $this->addItemRow($itemArr);
                    }
                }
            } else {
                $itemArr['has_children'] = 0;
                $itemArr['properties']   = $itemModel->buildCsvProperties($item);

                // calculate quantity staggering and modify product for this
                $itemArr = $this->setQuantityStaggering($itemArr, $item['gm_graduated_qty']);

                if (!empty($itemArr['internal_order_info'])) {
                    $itemArr['internal_order_info'] = $this->jsonEncode($itemArr['internal_order_info']);
                }

                if (!empty($gmPriceStatusItemFields)) {
                    $itemArr = $gmPriceStatusItemFields + $itemArr;
                }

                $this->addItemRow($itemArr);
            }
        }

        $this->log("[csv] End export products", ShopgateLogger::LOGTYPE_DEBUG);
    }

    protected function buildDefaultItemRow()
    {
        $row = parent::buildDefaultItemRow();

        if (!empty($this->optionFieldArray)) {
            // remove all options fields first (to append them all
            $row = $this->removeFieldsByKeyPrefix($row, 'option_');
            // move the has options field to the end (just before the actual options and options-values)
            unset($row['has_options']);
            $row['has_options'] = '';
            // create an item-array from both part-item-arrays
            $row = $row + $this->optionFieldArray;
        }

        return $row;
    }

    /**
     * Takes an item array and removes all fields that contan a specific prefix in their key (like "option_")
     * By default the second parameter is set to remove all options
     *
     * @param array  $item
     * @param string $keyPrefix
     *
     * @return mixed
     */
    private function removeFieldsByKeyPrefix($item, $keyPrefix = 'option_')
    {
        if (!empty($item) && !empty($keyPrefix)) {
            $fieldsToRemove = array();

            // Find all fields to delete an put them into a list
            foreach ($item as $itemFieldName => $itemFieldValue) {
                if (substr($itemFieldName, 0, strlen($keyPrefix)) == $keyPrefix) {
                    $fieldsToRemove[] = $itemFieldName;
                }
            }

            // Remove all found fields
            foreach ($fieldsToRemove as $fieldKey) {
                unset($item[$fieldKey]);
            }
        }

        return $item;
    }

    /**
     * Takes a product and a stack quantity and modifies the product so it can only be bought in n-packs
     *
     * @param array  $product
     * @param string $stackQuantity
     *
     * @return array
     */
    private function setQuantityStaggering($product, $stackQuantity = '')
    {
        $stackQuantity = (!empty($stackQuantity)
            ? ceil($stackQuantity)
            : 1);
        $stackQuantity = intval($stackQuantity, 10);
        if ($stackQuantity > 1) {
            // Save stack quantity amount
            $product['internal_order_info']['stack_quantity'] = $stackQuantity;

            // Additional text to the name
            $product['item_name'] .= sprintf(
                " (" . SHOPGATE_PLUGIN_ITEM_NAME_ADDITION_STACK_QUANTITY_INFO . ")",
                $stackQuantity
            );

            // Stock quantity must be divided by the stack quantity
            $product['stock_quantity'] = intval(floor($product['stock_quantity'] / floatval($stackQuantity)), 10);

            // Price must be multiplied by the stack quantity
            $product['unit_amount'] *= $stackQuantity;
            if (!empty($product['old_unit_amount'])) {
                $product['old_unit_amount'] *= $stackQuantity;
            }

            // The pack is havier than just one product
            if (!empty($product['weight'])) {
                $product['weight'] *= $stackQuantity;
            }

            // Min order quantity reduced by the times the pack has been upsized
            if ($product['minimum_order_quantity'] > 0) {
                $product['minimum_order_quantity'] = ceil($product['minimum_order_quantity'] / $stackQuantity);
            }

            // Shipping consts per unit is higher, since there is a whole pack of the items to order
            if ($product['additional_shipping_costs_per_unit'] > 0) {
                $product['additional_shipping_costs_per_unit'] *= $stackQuantity;
            }

            // calculate options prices
            for ($i = 1; $i <= 10; $i++) {
                if (!empty($product["option_{$i}_values"])) {
                    $productOptions = explode('||', $product["option_{$i}_values"]);
                    // Split all value-rows to calculate the new price offset
                    foreach ($productOptions as $key => $option) {
                        // price is set after the '=>' mark
                        $optionValues = explode('=>', $option);
                        // get proce offset
                        $offsetPrice = 0;
                        if (!empty($optionValues[1])) {
                            $offsetPrice = $optionValues[1];
                        }
                        // calculate new price offset
                        $offsetPrice     *= $stackQuantity;
                        $optionValues[1] = $offsetPrice;

                        // update value row
                        $productOptions[$key] = implode('=>', $optionValues);
                    }

                    // update options-values
                    $product["option_{$i}_values"] = implode('||', $productOptions);
                }
            }
        }

        return $product;
    }

    protected function createMediaCsv()
    {
        // TODO: Implement createMediaCsv() method.
    }

    private function requireFiles()
    {
        // load helpers
        require_once(dirname(__FILE__) . '/helper/ShopgateCouponHelper.php');
        require_once(dirname(__FILE__) . '/helper/ShopgateCartHelper.php');
        require_once(dirname(__FILE__) . '/helper/ShopgateDatabaseHelper.php');
        require_once(dirname(__FILE__) . '/helper/ShopgatePluginInitHelper.php');
        require_once(dirname(__FILE__) . '/helper/ShopgateItemPropertyHelper.php');
        require_once(dirname(__FILE__) . '/helper/ShopgateCustomizerSetHelper.php');
        require_once(dirname(__FILE__) . '/helper/ShopgateStockHelper.php');

        // load models
        require_once(dirname(__FILE__) . '/model/category/ShopgateCategoryModel.php');
        require_once(dirname(__FILE__) . '/model/category/ShopgateCategoryXmlModel.php');
        require_once(dirname(__FILE__) . '/model/customer/ShopgateCustomerModel.php');
        require_once(dirname(__FILE__) . '/model/customer/ShopgateCustomerOrderModel.php');
        require_once(dirname(__FILE__) . '/model/item/ShopgateItemModel.php');
        require_once(dirname(__FILE__) . '/model/item/property/ShopgateItemPropertyModel.php');
        require_once(dirname(__FILE__) . '/model/item/property/ShopgateItemPropertyValueModel.php');
        require_once(dirname(__FILE__) . '/model/item/ShopgateItemCartModel.php');
        require_once(dirname(__FILE__) . '/model/item/ShopgateItemAttributeModel.php');
        require_once(dirname(__FILE__) . '/model/item/ShopgateItemXmlModel.php');
        require_once(dirname(__FILE__) . '/model/location/ShopgateLocationModel.php');
        require_once(dirname(__FILE__) . '/model/location/ShopgateShippingModel.php');
        require_once(dirname(__FILE__) . '/model/price/ShopgatePriceModel.php');
        require_once(dirname(__FILE__) . '/model/review/ShopgateReviewModel.php');
        require_once(dirname(__FILE__) . '/model/review/ShopgateReviewXmlModel.php');
        require_once(dirname(__FILE__) . '/model/coupon/ShopgateCouponModel.php');

        // load Gambio GX files
        require_once($this->buildPath('inc') . 'xtc_validate_password.inc.php');
        require_once($this->buildPath('inc') . 'xtc_format_price_order.inc.php');
        require_once($this->buildPath('inc') . 'xtc_db_prepare_input.inc.php');
        require_once($this->buildPath('inc') . 'xtc_get_tax_class_id.inc.php');
        require_once($this->buildPath('includes/classes') . 'xtcPrice.php');
        require_once($this->buildPath('gm/classes') . 'GMSEOBoost.php');
        require_once($this->buildPath('gm/inc') . 'gm_get_conf.inc.php');
        require_once($this->buildPath('inc') . 'xtc_get_products_stock.inc.php');
        require_once($this->buildPath('shopgate') . 'gambiogx/ShopgateTools.php');

        // load language class if not already loaded
        if (!class_exists('language')) {
            require_once($this->buildPath('includes/classes') . 'language.php');
        }
    }

    /**
     * Builds the path to a folder.
     *
     * The path will be built using the DIR_FS_CATALOG constant if defined. If not it will be built using this
     * file as starting point. The path will be suffixed with a /.
     *
     * @param string $relativePath the path to the file or folder starting from the shopping cart's root directory
     *
     * @return string
     */
    private function buildPath($relativePath)
    {
        $relativePath = trim($relativePath, '/');

        return defined('DIR_FS_CATALOG')
            ? rtrim(DIR_FS_CATALOG, DS) . '/' . $relativePath . '/'
            : dirname(__FILE__) . '/../' . $relativePath . '/';
    }

    /**
     * Checks if payment methods are installed and returns the first encounter.
     *
     * The first installed payment method in the array of valid methods is returned. If none of the methods are
     * installed the first method in the array is returned regardless if it's installed or not.
     *
     * @param string[] $validMethods The methods to be checked with the most important/fallback first.
     *
     * @return string
     */
    private function determinePaymentMethod(array $validMethods)
    {
        if (empty($validMethods) || empty($validMethods[0])) {
            return '';
        }

        if (!defined('MODULE_PAYMENT_INSTALLED')) {
            return $validMethods[0];
        }

        foreach ($validMethods as $method) {
            if (strpos(MODULE_PAYMENT_INSTALLED, $method . '.php') !== false) {
                return $method;
            }
        }

        return $validMethods[0];
    }
}

/**
 * Class ShopgateXtcMapper
 */
class ShopgateXtcMapper
{
    /**
     * The countries with non-ISO-3166-2 state codes in xt:Commerce 3 are mapped here.
     *
     * @var string[][]
     */
    protected static $stateCodesByCountryCode
        = array(
            'DE' => array(
                "BW" => "BAW",
                "BY" => "BAY",
                "BE" => "BER",
                "BB" => "BRG",
                "HB" => "BRE",
                "HH" => "HAM",
                "HE" => "HES",
                "MV" => "MEC",
                "NI" => "NDS",
                "NW" => "NRW",
                "RP" => "RHE",
                "SL" => "SAR",
                "SN" => "SAS",
                "ST" => "SAC",
                "SH" => "SCN",
                "TH" => "THE",
            ),
            "AT" => array(
                "1" => "BL",
                "2" => "KN",
                "3" => "NO",
                "4" => "OO",
                "5" => "SB",
                "6" => "ST",
                "7" => "TI",
                "8" => "VB",
                "9" => "WI",
            ),
            //"CH" => ist in xt:commerce bereits korrekt
            //"US" => ist in xt:commerce bereits korrekt
        );

    /**
     * Finds the corresponding Shopgate state code for a given xt:Commerce 3 state code (zone_code).
     *
     * @param string $countryCode  The code of the country to which the state belongs
     * @param string $xtcStateCode The code of the state / zone as found in the default "zones" table of xt:Commerce 3
     *
     * @return string The state code as defined at Shopgate Wiki
     *
     * @throws ShopgateLibraryException if one of the given codes is unknown
     */
    public static function getShopgateStateCode($countryCode, $xtcStateCode)
    {
        $countryCode  = strtoupper($countryCode);
        $xtcStateCode = strtoupper($xtcStateCode);

        if (!isset(self::$stateCodesByCountryCode[$countryCode])) {
            return $countryCode . '-' . $xtcStateCode;
        }

        $codes = array_flip(self::$stateCodesByCountryCode[$countryCode]);
        if (!isset($codes[$xtcStateCode])) {
            return $countryCode . '-' . $xtcStateCode;
        }

        $stateCode = $codes[$xtcStateCode];

        return $countryCode . '-' . $stateCode;
    }

    /**
     * Finds the corresponding xt:Commerce 3 state code (zone_code) for a given Shopgate state code
     *
     * @param string $shopgateStateCode The Shopgate state code as defined at Shopgate Wiki
     *
     * @return string The zone code for xt:Commerce 3
     *
     * @throws ShopgateLibraryException if the given code is unknown
     */
    public static function getXtcStateCode($shopgateStateCode)
    {
        $splitCodes = null;
        preg_match('/^([A-Z]{2})\-([A-Z]{2})$/', $shopgateStateCode, $splitCodes);

        if (empty($splitCodes) || empty($splitCodes[1]) || empty($splitCodes[2])) {
            return null;
        }

        if (!isset(self::$stateCodesByCountryCode[$splitCodes[1]])
            || !isset(self::$stateCodesByCountryCode[$splitCodes[1]][$splitCodes[2]])
        ) {
            //throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_UNKNOWN_STATE_CODE, 'Code: '.$shopgateStateCode);
            return $splitCodes[2];
        } else {
            return self::$stateCodesByCountryCode[$splitCodes[1]][$splitCodes[2]];
        }
    }
}
