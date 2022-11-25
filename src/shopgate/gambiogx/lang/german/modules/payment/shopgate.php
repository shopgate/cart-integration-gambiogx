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

$link = '<a href="' . xtc_href_link('shopgate.php?sg_option=config', '', 'NONSSL') . '">Shopgate Konfiguration</a>';

define('MODULE_PAYMENT_SHOPGATE_TEXT_TITLE', 'Shopgate');
define('MODULE_PAYMENT_SHOPGATE_TEXT_DESCRIPTION', 'Shopgate - Mobile Shopping.<br>' . $link . '<br>');
define('MODULE_PAYMENT_SHOPGATE_TEXT_INFO', 'Bestellungen sind bereits bei Shopgate bezahlt.');

define('MODULE_PAYMENT_SHOPGATE_ORDER_LINE_TEXT_SHIPPING', 'Versand');
define('MODULE_PAYMENT_SHOPGATE_ORDER_LINE_TEXT_SUBTOTAL', 'Zwischensumme');
define('MODULE_PAYMENT_SHOPGATE_ORDER_LINE_TEXT_PAYMENTFEE', 'Zahlungsartkosten');
define('MODULE_PAYMENT_SHOPGATE_ORDER_LINE_TEXT_TOTAL', 'Summe');
define('MODULE_PAYMENT_SHOPGATE_ORDER_LINE_TEXT_TOTAL_WITHOUT_TAX', 'Summe (netto)');

define('MODULE_PAYMENT_SHOPGATE_TEXT_EMAIL_FOOTER', '');
define('MODULE_PAYMENT_SHOPGATE_STATUS_TITLE', 'Shopgate-Zahlungsmodul aktiviert:');

define('MODULE_PAYMENT_SHOPGATE_STATUS_DESC', '');
define('MODULE_PAYMENT_SHOPGATE_ALLOWED_TITLE', '');
define('MODULE_PAYMENT_SHOPGATE_ALLOWED_DESC', '');
define('MODULE_PAYMENT_SHOPGATE_PAYTO_TITLE', '');
define('MODULE_PAYMENT_SHOPGATE_PAYTO_DESC', '');
define('MODULE_PAYMENT_SHOPGATE_SORT_ORDER_TITLE', '');
define('MODULE_PAYMENT_SHOPGATE_SORT_ORDER_DESC', '');
define('MODULE_PAYMENT_SHOPGATE_ZONE_TITLE', '');
define('MODULE_PAYMENT_SHOPGATE_ZONE_DESC', '');
define('MODULE_PAYMENT_SHOPGATE_ORDER_STATUS_ID_TITLE', 'Status');
define(
    'MODULE_PAYMENT_SHOPGATE_ORDER_STATUS_ID_DESC',
    'Bestellungen, die mit diesem Modul importiert werden, auf diesen Status setzen:'
);
define('MODULE_PAYMENT_SHOPGATE_ERROR_READING_LANGUAGES', 'Fehler beim Konfigurieren der Spracheinstellungen.');
define('MODULE_PAYMENT_SHOPGATE_ERROR_LOADING_CONFIG', 'Fehler beim Laden der Konfiguration.');
define(
    'MODULE_PAYMENT_SHOPGATE_ERROR_SAVING_CONFIG',
    'Fehler beim Speichern der Konfiguration. ' .
    'Bitte &uuml;berpr&uuml;fen Sie die Schreibrechte (777) f&uuml;r ' .
    'den Ordner &quot;/shopgate_library/config/&quot; des Shopgate-Plugins.'
);
define('MODULE_PAYMENT_SHOPGATE_TITLE_BLANKET ', 'Pauschal');

define("SHOPGATE_COUPON_ERROR_NEED_ACCOUNT", "Um diesen Gutschein verwenden zu können, müssen Sie angemeldet sein.");
define("SHOPGATE_COUPON_ERROR_RESTRICTED_PRODUCTS", "Dieser Gutschein ist auf bestimmte Produkte beschränkt");
define("SHOPGATE_COUPON_ERROR_RESTRICTED_CATEGORIES", "Dieser Gutschein ist auf bestimmte Kategorien beschränkt");
define(
    "SHOPGATE_COUPON_ERROR_MINIMUM_ORDER_AMOUNT_NOT_REACHED",
    "Der Mindestbestellwert, um diesen Gutschein nutzen zu können, wurde nicht erreicht"
);
