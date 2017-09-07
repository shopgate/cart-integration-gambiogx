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
class ShopgateCustomerModel extends ShopgateObject
{
    const TABLE_CUSTOMERS_SHOPGATE_CUSTOMER = 'customers_shopgate_customer';

    /**
     * @var ShopgateConfigGambioGx $config
     */
    private $config;

    /**
     * @var int $languageId
     */
    private $languageId;

    /**
     * @param ShopgateConfigGambioGx $config
     * @param int                    $languageId
     */
    public function __construct(ShopgateConfigGambioGx $config, $languageId)
    {
        $this->languageId = $languageId;
        $this->config     = $config;
    }

    /**
     * return an array with all customer groups
     *
     * @return array
     */
    public function getCustomerGroups()
    {
        $customerGroups = array();

        $query
            = "SELECT 
                        cs.customers_status_name AS name,
                        cs.customers_status_id AS id,
                        0 AS 'is_default'
                    FROM " . TABLE_CUSTOMERS_STATUS . " AS cs 
                    WHERE cs.language_id = {$this->languageId}";

        $result = xtc_db_query($query);

        while ($customerGroup = xtc_db_fetch_array($result)) {
            foreach ($customerGroup as &$cgrp) {
                $this->stringToUtf8($cgrp, $this->config->getEncoding());
            }
            if ((int)$customerGroup['id'] == DEFAULT_CUSTOMERS_STATUS_ID) {
                $customerGroup['is_default'] = 1;
            }
            $customerGroup['customer_tax_class_key'] = 'default';

            $customerGroups[] = $customerGroup;
        }

        return $customerGroups;
    }

    /**
     * @param string $internalCustomerId
     *
     * @return bool|int
     */
    public function getCustomerToken($internalCustomerId)
    {
        $query
            = "SELECT customer_token
                    FROM " . ShopgateCustomerModel::TABLE_CUSTOMERS_SHOPGATE_CUSTOMER . "
                    WHERE customer_id = {$internalCustomerId}";

        $result = xtc_db_fetch_array(xtc_db_query($query));

        if (is_array($result)) {
            return $result['customer_token'];
        }

        return false;
    }

    /**
     * @param string $internalCustomerId
     *
     * @return bool|int
     */
    public function hasCustomerToken($internalCustomerId)
    {
        return (bool)$this->getCustomerToken($internalCustomerId);
    }

    /**
     * @param int    $internalCustomerId
     * @param string $eMailAddress
     *
     * @return string
     */
    public function insertToken($internalCustomerId, $eMailAddress)
    {
        $token = md5($internalCustomerId . $eMailAddress . microtime());

        xtc_db_query(
            "INSERT INTO `" . ShopgateCustomerModel::TABLE_CUSTOMERS_SHOPGATE_CUSTOMER . "` " .
            "(`customer_id`, `customer_token`) VALUES " .
            "(" . xtc_db_input($internalCustomerId) . ", '" . xtc_db_input($token) . "')"
        );

        return $token;
    }

    /**
     * @param string $token
     *
     * @return int|bool
     */
    public function getCustomerIdByToken($token)
    {
        $query
            = "SELECT customer_id
                    FROM `" . ShopgateCustomerModel::TABLE_CUSTOMERS_SHOPGATE_CUSTOMER . "`
                    WHERE customer_token = '" . $token . "'";

        $result = xtc_db_fetch_array(xtc_db_query($query));

        return isset($result['customer_id'])
            ? $result['customer_id']
            : false;
    }

    /**
     * read the customer group data from the database by the customer's uid
     *
     * @param int $uid
     *
     * @return array|bool|mixed
     */
    public function getGroupToCustomer($uid)
    {
        $query =
            "SELECT cs.customers_status_name AS `name`, cs.customers_status_id AS `id` " .
            "FROM `" . TABLE_CUSTOMERS . "` AS c " .
            "JOIN `" . TABLE_CUSTOMERS_STATUS . "` AS cs ON cs.customers_status_id = c.customers_status " .
            "WHERE c.customers_id={$uid} AND cs.language_id={$this->languageId}";

        return xtc_db_fetch_array(xtc_db_query($query));
    }

    /**
     * read the customer group data from the database it's uid
     *
     * @param int $uid
     *
     * @return array|bool|mixed
     */
    public function getCustomerGroup($uid)
    {
        $query =
            "SELECT *" .
            "FROM `" . TABLE_CUSTOMERS_STATUS . "` AS cs " .
            "WHERE cs.customers_status_id = {$uid} AND cs.language_id = {$this->languageId}";

        return xtc_db_fetch_array(xtc_db_query($query));
    }

    public function customerHasGroup($groupUid, $email)
    {
        $customerResult = $this->getCustomerByEmail($email);
        if (!empty($customerResult['customers_status_id']) && $customerResult['customers_status_id'] == $groupUid) {
            return true;
        }

        return false;
    }

    /**
     * read customer data from the database by the email address
     *
     * @param string $user
     *
     * @return resource
     */
    public function getCustomerByEmail($user)
    {
        // find customer
        $qry = "SELECT"

            // basic user information
            . " customer.customers_id,"
            . " customer.customers_cid,"
            . " status.customers_status_name,"
            . " status.customers_status_id,"
            . " customer.customers_gender,"
            . " customer.customers_firstname,"
            . " customer.customers_lastname,"
            . " date_format(customer.customers_dob,'%Y-%m-%d') as customers_birthday,"
            . " customer.customers_telephone,"
            . " customer.customers_email_address,"

            // additional information for password verification, default address etc.
            . " customer.customers_password,"
            . " customer.customers_default_address_id"

            . " FROM " . TABLE_CUSTOMERS . " AS customer"

            . " INNER JOIN " . TABLE_CUSTOMERS_STATUS . " AS status"

            . " ON customer.customers_status = status.customers_status_id"
            . " AND status.language_id = " . $this->languageId

            . " WHERE customers_email_address = '" . xtc_db_input($user) . "';";

        // user exists?
        return xtc_db_query($qry);
    }

    /**
     * read customers address data from the database by the customers uid
     *
     * @param int $uid
     *
     * @return resource
     */
    public function getCustomerAdressData($uid)
    {
        $fields = array(
            'address.address_book_id',
            'address.entry_gender',
            'address.entry_firstname',
            'address.entry_lastname',
            'address.entry_company',
            'address.entry_street_address',
            'address.entry_postcode',
            'address.entry_city',
            'country.countries_iso_code_2',
            'zone.zone_code',
        );

        if (!ShopgateTools::isGambioVersionLowerThan('3.1.1.0')) {
            $fields[] = 'address.entry_house_number';
            $fields[] = 'address.entry_additional_info';
        }

        // fetch customers' addresses
        $qry = "SELECT "

            . implode(",\n", $fields)

            . " FROM " . TABLE_ADDRESS_BOOK . " AS address"

            . " LEFT JOIN " . TABLE_COUNTRIES . " AS country"
            . " ON country.countries_id = address.entry_country_id"

            . " LEFT JOIN " . TABLE_ZONES . " AS zone"
            . " ON address.entry_zone_id = zone.zone_id"
            . " AND country.countries_id = zone.zone_country_id"

            . " WHERE address.customers_id = " . xtc_db_input($uid) . ";";

        return xtc_db_query($qry);
    }

    /**
     * returns a customer from the database by customer_uid
     *
     * @param int $customerId
     *
     * @return array|string
     */
    public function getCustomerById($customerId)
    {
        if (empty($customerId)) {
            return "";
        }
        $query = "SELECT * FROM `" . TABLE_CUSTOMERS . "` WHERE customers_id={$customerId}";

        return xtc_db_fetch_array(xtc_db_query($query));
    }
}
