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

### Plugin ###
define('SHOPGATE_CONFIG_EXTENDED_ENCODING', 'Shop system encoding');

define(
    'SHOPGATE_CONFIG_EXTENDED_ENCODING_DESCRIPTION',
    'Choose the encoding of your shop system. This is usually "ISO-8859-15" for versions older than GambioGX 2.1 and "UTF-8" for versions starting from GambioGX 2.1.'
);
define('SHOPGATE_CONFIG_WIKI_LINK', 'http://wiki.shopgate.com/Gambio_GX2/de');

define('SHOPGATE_PLUGIN_DESCRIPTION_DOCUMENTS_TEXT', 'Documents:');
define('SHOPGATE_PLUGIN_ITEM_NAME_ADDITION_STACK_QUANTITY_INFO', 'Pack of %d');
define('SHOPGATE_PLUGIN_FIELD_AVAILABLE_TEXT_AVAILABLE_ON_DATE', 'Available on #DATE#');
define('SHOPGATE_CONFIG_VARIATION_TYPE_PRODUCTS_BOTH', 'Both variation types');
define(
    'SHOPGATE_CONFIG_VARIATION_TYPE_PRODUCTS_ATTRIBUTES',
    defined('BOX_PRODUCTS_ATTRIBUTES')
        ? BOX_PRODUCTS_ATTRIBUTES
        : 'Product Options'
);
define(
    'SHOPGATE_CONFIG_VARIATION_TYPE_PRODUCTS_PROPERTIES',
    defined('BOX_PROPERTIES')
        ? BOX_PROPERTIES
        : 'Product Properties'
);
define('SHOPGATE_CONFIG_EXTENDED_VARIATION_TYPE', 'Variation type');
define(
    'SHOPGATE_CONFIG_EXTENDED_VARIATION_TYPE_DESCRIPTION',
    'Choose the type of variation that should be used for products export.<br/><b>Important:</b> Each product must only have one variant type active at the same time, or it will not be exported!'
);
define('SHOPGATE_CONFIG_MODULE_ACTIVE', 'Shopgate module activated');
define('SHOPGATE_CONFIG_MODULE_ACTIVE_OFF', 'No');
define('SHOPGATE_CONFIG_MODULE_ACTIVE_ON', 'Yes');

### Menu ###
define('BOX_SHOPGATE', 'Shopgate');
define('BOX_SHOPGATE_INFO', 'What is Shopgate');
define('BOX_SHOPGATE_HELP', 'Installation aid');
define('BOX_SHOPGATE_REGISTER', 'Registration');
define('BOX_SHOPGATE_CONFIG', 'Settings');
define('BOX_SHOPGATE_MERCHANT', 'Shopgate login');

### Links ###
define('SHOPGATE_LINK_HOME', 'https://www.shopgate.com');
define('SHOPGATE_LINK_REGISTER', 'http://www.shopgate.com/welcome/shop_register');
define('SHOPGATE_LINK_LOGIN', 'https://www.shopgate.com/users/login/0/2');

### Configuration ###
define('SHOPGATE_CONFIG_TITLE', 'SHOPGATE');
define('SHOPGATE_CONFIG_ERROR', 'ERROR:');
define('SHOPGATE_CONFIG_ERROR_SAVING', 'Error saving configuration. ');
define('SHOPGATE_CONFIG_ERROR_LOADING', 'Error loading configuration. ');
define(
    'SHOPGATE_CONFIG_ERROR_READ_WRITE',
    'Please check the permissions (777) for the folder &quot;/shopgate_library/config&quot; of the Shopgate plugin.'
);
define('SHOPGATE_CONFIG_ERROR_INVALID_VALUE', 'Please check your input in the following fields: ');
define(
    'SHOPGATE_CONFIG_ERROR_DUPLICATE_SHOP_NUMBERS',
    'There are multiple configurations with the same shop number. This can cause major unforeseen issues!'
);
define('SHOPGATE_CONFIG_INFO_MULTIPLE_CONFIGURATIONS', 'Configurations for multiple market places are active.');
define('SHOPGATE_CONFIG_SAVE', 'Save');
define('SHOPGATE_CONFIG_GLOBAL_CONFIGURATION', 'Global configuration');
define('SHOPGATE_CONFIG_USE_GLOBAL_CONFIG', 'Use the global configuration for this language.');
define('SHOPGATE_CONFIG_MULTIPLE_SHOPS_BUTTON', 'Setup multiple Shopgate marketplaces');
define(
    'SHOPGATE_CONFIG_LANGUAGE_SELECTION',
    'At Shopgate you need a shop for each marketplace restricted to one language and currency. Here you can map the configured languages to your Shopgate shops on different '
    .
    'marketplaces. Choose a language and enter the credentials of your Shopgate shop at the corresponding marketplace. If you do not have a Shopgate shop for a certain language '
    .
    'the global configuration will be used for this one.'
);

### Connection Settings ###
define('SHOPGATE_CONFIG_CONNECTION_SETTINGS', 'Connection Settings');

define('SHOPGATE_CONFIG_CUSTOMER_NUMBER', 'Customer number');
define(
    'SHOPGATE_CONFIG_CUSTOMER_NUMBER_DESCRIPTION',
    'You can find your customer number at the &quot;Integration&quot; section of your shop.'
);

define('SHOPGATE_CONFIG_SHOP_NUMBER', 'Shop number');
define(
    'SHOPGATE_CONFIG_SHOP_NUMBER_DESCRIPTION',
    'You can find the shop number at the &quot;Integration&quot; section of your shop.'
);

define('SHOPGATE_CONFIG_APIKEY', 'API key');
define(
    'SHOPGATE_CONFIG_APIKEY_DESCRIPTION',
    'You can find the API key at the &quot;Integration&quot; section of your shop.'
);

### Mobile Redirect ###
define('SHOPGATE_CONFIG_MOBILE_REDIRECT_SETTINGS', 'Mobile Redirect');

define('SHOPGATE_CONFIG_ALIAS', 'Shop alias');
define(
    'SHOPGATE_CONFIG_ALIAS_DESCRIPTION',
    'You can find the alias at the &quot;Integration&quot; section of your shop.'
);

define('SHOPGATE_CONFIG_CNAME', 'Custom URL to mobile webpage (CNAME) incl. http://');
define(
    'SHOPGATE_CONFIG_CNAME_DESCRIPTION',
    'Enter a custom URL (defined by CNAME) for your mobile website. You can find the URL at the &quot;Integration&quot; section of your shop '
    .
    'after you activated this option in the &quot;Settings&quot; &equals;&gt; &quot;Mobile website / webapp&quot; section.'
);

define('SHOPGATE_CONFIG_REDIRECT_LANGUAGES', 'Redirected languages');
define(
    'SHOPGATE_CONFIG_REDIRECT_LANGUAGES_DESCRIPTION',
    'Choose the languages that should be redirected to this Shopgate shop. At least one language must be selected. Hold CTRL to select multiple entries.'
);

### Export ###
define('SHOPGATE_CONFIG_EXPORT_SETTINGS', 'Exporting Categories and Products');

define('SHOPGATE_CONFIG_LANGUAGE', 'Language');
define(
    'SHOPGATE_CONFIG_LANGUAGE_DESCRIPTION',
    'Choose the language in which categories and products should be exported.'
);

define('SHOPGATE_CONFIG_EXTENDED_CURRENCY', 'Currency');
define('SHOPGATE_CONFIG_EXTENDED_CURRENCY_DESCRIPTION', 'Choose the currency for products export.');

define('SHOPGATE_CONFIG_EXTENDED_COUNTRY', 'Country');
define('SHOPGATE_CONFIG_EXTENDED_COUNTRY_DESCRIPTION', 'Choose the country for which your products should be exported');

define('SHOPGATE_CONFIG_EXTENDED_TAX_ZONE', 'Tax zone for Shopgate');
define('SHOPGATE_CONFIG_EXTENDED_TAX_ZONE_DESCRIPTION', 'Choose the valid tax zone for Shopgate.');

define('SHOPGATE_CONFIG_EXTENDED_REVERSE_CATEGORIES_SORT_ORDER', 'Reverse category sort order');
define('SHOPGATE_CONFIG_EXTENDED_REVERSE_CATEGORIES_SORT_ORDER_ON', 'Yes');
define('SHOPGATE_CONFIG_EXTENDED_REVERSE_CATEGORIES_SORT_ORDER_OFF', 'No');
define(
    'SHOPGATE_CONFIG_EXTENDED_REVERSE_CATEGORIES_SORT_ORDER_DESCRIPTION',
    'Choose "Yes" if the sort order of the categories in your mobile shop appears upside down.'
);

define('SHOPGATE_CONFIG_EXTENDED_REVERSE_ITEMS_SORT_ORDER', 'Reverse products sort order');
define('SHOPGATE_CONFIG_EXTENDED_REVERSE_ITEMS_SORT_ORDER_ON', 'Yes');
define('SHOPGATE_CONFIG_EXTENDED_REVERSE_ITEMS_SORT_ORDER_OFF', 'No');
define(
    'SHOPGATE_CONFIG_EXTENDED_REVERSE_ITEMS_SORT_ORDER_DESCRIPTION',
    'Choose "Yes" if the sort order of the products in your mobile shop appears upside down.'
);

define('SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION', 'Products description');
define('SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_DESC_ONLY', 'Description only');
define('SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_SHORTDESC_ONLY', 'Short description only');
define('SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_DESC_SHORTDESC', 'Description and short description');
define('SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_SHORTDESC_DESC', 'Short description and description');
define(
    'SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_DESCRIPTION',
    'Please select the method to be used to build descriptions for the mobile shop.'
);

define('SHOPGATE_PLUGIN_PRICE_STATUS_TEXT_NOT_AVAILABLE', 'not available for purchase');
define('SHOPGATE_PLUGIN_PRICE_ON_REQUEST_BASIC_PRICE_TEXT', 'price on request');
define('SHOPGATE_CONFIG_EXTENDED_EXPORT_PRICE_ON_REQUEST_PRODUCTS', 'Export "price on request" products');
define('SHOPGATE_CONFIG_EXTENDED_EXPORT_PRICE_ON_REQUEST_PRODUCTS_WITH_PRICE', 'With price');
define('SHOPGATE_CONFIG_EXTENDED_EXPORT_PRICE_ON_REQUEST_PRODUCTS_WITHOUT_PRICE', 'Without price');
define(
    'SHOPGATE_CONFIG_EXTENDED_EXPORT_PRICE_ON_REQUEST_PRODUCTS_DESCRIPTION',
    'Choose "with price" to show the products prices even if their price status is set to "price on request".<br/>The setting "without price" will export a price amount of zero including the information, that the price needs to be requested.'
);
define('SHOPGATE_CONFIG_EXTENDED_EXPORT_PRODUCTS_CONTENT_MANAGED_FILES', 'Include product content manager');
define('SHOPGATE_CONFIG_EXTENDED_EXPORT_PRODUCTS_CONTENT_MANAGED_ON', 'Yes');
define('SHOPGATE_CONFIG_EXTENDED_EXPORT_PRODUCTS_CONTENT_MANAGED_OFF', 'No');
define(
    'SHOPGATE_CONFIG_EXTENDED_EXPORT_PRODUCTS_CONTENT_MANAGED_FILES_DESCRIPTION',
    'Choose "Yes", if you want links or files, added by the products content manager, to be included to the products description on exporting products.'
);

define('SHOPGATE_PLUGIN_MAX_ATTRIBUTE_VALUE_HEADLINE', 'Attribute option amount');
define(
    'SHOPGATE_PLUGIN_MAX_ATTRIBUTE_VALUE_DESCRIPTION',
    'Choose the maximum amount of Atrributes. When the given attribute limit is reached, the system uses an more efficient data structure to export the products.(default value: 50)'
);

define('SHOPGATE_CONFIG_EXPORT_OPTIONS_AS_INPUT_FIELD', 'Export product options as text field');
define(
    'SHOPGATE_CONFIG_EXPORT_OPTIONS_AS_INPUT_FIELD_DESCRIPTION',
    'Add the option id (can be found in "Product Options") which needs to be exported as text field to the text area.(example: 1,2,3).'
);

define('SHOPGATE_CONFIG_EXPORT_FILTERS_AS_PROPERTIES', 'Export article filters as properties');
define(
    'SHOPGATE_CONFIG_EXPORT_FILTERS_AS_PROPERTIES_DESCRIPTION',
    'The selected article filters will be exported as properties.'
);

### Orders Import ###
define('SHOPGATE_CONFIG_ORDER_IMPORT_SETTINGS', 'Importing Orders');

define('SHOPGATE_CONFIG_EXTENDED_SHIPPING', 'Shipping method');
define(
    'SHOPGATE_CONFIG_EXTENDED_SHIPPING_DESCRIPTION',
    'Choose the shipping method for the import of the orders. This will be used to calculate the tax for the shipping costs.'
);
define('SHOPGATE_CONFIG_EXTENDED_SHIPPING_NO_SELECTION', '-- no selection --');

define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SHIPPING_APPROVED', 'Shipping not blocked');
define(
    'SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SHIPPING_APPROVED_DESCRIPTION',
    'Choose the status for orders that are not blocked for shipping by Shopgate.'
);

define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SHIPPING_BLOCKED', 'Shipping blocked');
define(
    'SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SHIPPING_BLOCKED_DESCRIPTION',
    'Choose the status for orders that are blocked for shipping by Shopgate.'
);

define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SENT', 'Shipped');
define(
    'SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SENT_DESCRIPTION',
    'Choose the status you apply to orders that have been shipped.'
);

define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_CANCELED', 'Cancelled');
define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_CANCELED_NOT_SET', '- Status not set -');
define(
    'SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_CANCELED_DESCRIPTION',
    'Choose the status for orders that have been cancelled.'
);

define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SEND_CONFIRMATION_MAIL_ON', 'Yes');
define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SEND_CONFIRMATION_MAIL_OFF', 'No');
define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SEND_CONFIRMATION_MAIL', 'Send order confirmation mail');

define('SHOPGATE_CONFIG_PAYMENT_NAME_MAPPING', 'Display names for payment methods');
define(
    'SHOPGATE_CONFIG_PAYMENT_NAME_MAPPING_DESCRIPTION',
    "Individual names for payment methods, which are used on order import. Defined by '=' and separated by ';'.<br/>(Example: PREPAY=Prepay;SHOPGATE=Handled by Shopgate)<br/>"
);
define(
    'SHOPGATE_CONFIG_PAYMENT_NAME_MAPPING_LINK',
    'https://support.shopgate.com/hc/en-us/articles/202798386-Connecting-to-Gambio#4.3'
);
define('SHOPGATE_CONFIG_PAYMENT_NAME_MAPPING_LINK_DESCRIPTION', "Link to the support page");

### System Settings ###
define('SHOPGATE_CONFIG_SYSTEM_SETTINGS', 'System Settings');

define('SHOPGATE_CONFIG_SERVER_TYPE', 'Shopgate server');
define('SHOPGATE_CONFIG_SERVER_TYPE_LIVE', 'Live');
define('SHOPGATE_CONFIG_SERVER_TYPE_PG', 'Playground');
define('SHOPGATE_CONFIG_SERVER_TYPE_CUSTOM', 'Custom');
define('SHOPGATE_CONFIG_SERVER_TYPE_CUSTOM_URL', 'Custom Shopgate server url');
define('SHOPGATE_CONFIG_SERVER_TYPE_DESCRIPTION', 'Choose the Shopgate server to connect to.');

define('SHOPGATE_ORDER_ORDER', 'Order');
define('SHOPGATE_ORDER_INVOICE_ADDRESS', 'Invoice address');
define('SHOPGATE_ORDER_SHIPPING_ADDRESS', 'Shipping address');
