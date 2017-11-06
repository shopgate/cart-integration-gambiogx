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
define('SHOPGATE_CONFIG_EXTENDED_ENCODING', 'Encoding des Shopsystems');

define(
    'SHOPGATE_CONFIG_EXTENDED_ENCODING_DESCRIPTION',
    'W&auml;hlen Sie das Encoding Ihres Shopsystems. &Uuml;blicherweise ist f&uuml;r GambioGX "ISO-8859-15" und ab GambioGX 2.1 "UTF-8" zu w&auml;hlen.'
);
define('SHOPGATE_CONFIG_WIKI_LINK', 'http://wiki.shopgate.com/Gambio_GX2/de');

define('SHOPGATE_PLUGIN_DESCRIPTION_DOCUMENTS_TEXT', 'Dokumente:');
define('SHOPGATE_PLUGIN_ITEM_NAME_ADDITION_STACK_QUANTITY_INFO', '%der Packung');
define('SHOPGATE_PLUGIN_FIELD_AVAILABLE_TEXT_AVAILABLE_ON_DATE', 'Verf&uuml;gbar ab dem #DATE#');
define('SHOPGATE_CONFIG_VARIATION_TYPE_PRODUCTS_BOTH', 'Beide Variantentypen');
define(
    'SHOPGATE_CONFIG_VARIATION_TYPE_PRODUCTS_ATTRIBUTES',
    defined('BOX_PRODUCTS_ATTRIBUTES')
        ? BOX_PRODUCTS_ATTRIBUTES
        : 'Artikelattribute'
);
define(
    'SHOPGATE_CONFIG_VARIATION_TYPE_PRODUCTS_PROPERTIES',
    defined('BOX_PROPERTIES')
        ? BOX_PROPERTIES
        : 'Artikeleigenschaften'
);
define('SHOPGATE_CONFIG_EXTENDED_VARIATION_TYPE', 'Variantenart');
define(
    'SHOPGATE_CONFIG_EXTENDED_VARIATION_TYPE_DESCRIPTION',
    'W&auml;hlen Sie die Variantenart f&uuml;r den Produktexport.<br/><b>Wichtig:</b> Ein Produkt darf nur eine Variante zur gleichen Zeit nutzen, sonst wird es nicht exportiert!'
);
define('SHOPGATE_CONFIG_MODULE_ACTIVE', 'Shopgate-Modul aktiviert');
define('SHOPGATE_CONFIG_MODULE_ACTIVE_OFF', 'Nein');
define('SHOPGATE_CONFIG_MODULE_ACTIVE_ON', 'Ja');

### Menu ###
define('BOX_SHOPGATE', 'Shopgate');
define('BOX_SHOPGATE_INFO', 'Was ist Shopgate');
define('BOX_SHOPGATE_HELP', 'Installationshilfe');
define('BOX_SHOPGATE_REGISTER', 'Registrierung');
define('BOX_SHOPGATE_CONFIG', 'Einstellungen');
define('BOX_SHOPGATE_MERCHANT', 'Shopgate-Login');

### Links ###
define('SHOPGATE_LINK_HOME', 'https://www.shopgate.com');
define('SHOPGATE_LINK_REGISTER', 'https://www.shopgate.com/welcome/shop_register');
define('SHOPGATE_LINK_LOGIN', 'https://www.shopgate.com/users/login/0/2');

### Konfiguration ###
define('SHOPGATE_CONFIG_TITLE', 'SHOPGATE');
define('SHOPGATE_CONFIG_ERROR', 'FEHLER:');
define('SHOPGATE_CONFIG_ERROR_SAVING', 'Fehler beim Speichern der Konfiguration. ');
define('SHOPGATE_CONFIG_ERROR_LOADING', 'Fehler beim Laden der Konfiguration. ');
define(
    'SHOPGATE_CONFIG_ERROR_READ_WRITE',
    'Bitte überprüfen Sie die Schreibrechte (777) für den Ordner "/includes/shopgate/" des Shopgate-Plugins.'
);
define('SHOPGATE_CONFIG_ERROR_INVALID_VALUE', 'Bitte überprüfen Sie ihre Eingaben in den folgenden Feldern: ');
define(
    'SHOPGATE_CONFIG_ERROR_DUPLICATE_SHOP_NUMBERS',
    'Es existieren mehrere Konfigurationen mit der gleichen Shop-Nummer. Dies kann zu erheblichen Problemen führen!'
);
define(
    'SHOPGATE_CONFIG_INFO_MULTIPLE_CONFIGURATIONS',
    'Es existieren Konfigurationen f&uuml;r mehrere Marktpl&auml;tze.'
);
define('SHOPGATE_CONFIG_SAVE', 'Speichern');
define('SHOPGATE_CONFIG_GLOBAL_CONFIGURATION', 'Globale Konfiguration');
define('SHOPGATE_CONFIG_USE_GLOBAL_CONFIG', 'F&uuml;r diese Sprache die globale Konfiguration nutzen.');
define('SHOPGATE_CONFIG_MULTIPLE_SHOPS_BUTTON', 'Mehrere Shopgate-Marktpl&auml;tze einrichten');
define(
    'SHOPGATE_CONFIG_LANGUAGE_SELECTION',
    'Bei Shopgate ben&ouml;tigen Sie pro Marktplatz einen Shop, der auf eine Sprache und eine W&auml;hrung festgelegt ist. Hier haben Sie die M&ouml;glichkeit, Ihre konfigurierten '
    .
    'Sprachen mit Ihren Shopgate-Shops auf unterschiedlichen Marktpl&auml;tzen zu verbinden. W&auml;hlen Sie eine Sprache und tragen Sie die Zugangsdaten zu Ihrem Shopgate-Shop auf '
    .
    'dem entsprechenden Marktplatz ein. Wenn Sie f&uuml;r eine Sprache keinen eigenen Shop bei Shopgate haben, wird daf&uuml;r die "Globale Konfiguration" genutzt.'
);

### Verbindungseinstellungen ###
define('SHOPGATE_CONFIG_CONNECTION_SETTINGS', 'Verbindungseinstellungen');

define('SHOPGATE_CONFIG_CUSTOMER_NUMBER', 'Kundennummer');
define(
    'SHOPGATE_CONFIG_CUSTOMER_NUMBER_DESCRIPTION',
    'Tragen Sie hier Ihre Kundennummer ein. Sie finden diese im Tab &quot;Integration&quot; Ihres Shops.'
);

define('SHOPGATE_CONFIG_SHOP_NUMBER', 'Shopnummer');
define(
    'SHOPGATE_CONFIG_SHOP_NUMBER_DESCRIPTION',
    'Tragen Sie hier die Shopnummer Ihres Shops ein. Sie finden diese im Tab &quot;Integration&quot; Ihres Shops.'
);

define('SHOPGATE_CONFIG_APIKEY', 'API-Key');
define(
    'SHOPGATE_CONFIG_APIKEY_DESCRIPTION',
    'Tragen Sie hier den API-Key Ihres Shops ein. Sie finden diese im Tab &quot;Integration&quot; Ihres Shops.'
);

### Mobile Weiterleitung ###
define('SHOPGATE_CONFIG_MOBILE_REDIRECT_SETTINGS', 'Mobile Weiterleitung');

define('SHOPGATE_CONFIG_ALIAS', 'Shop-Alias');
define(
    'SHOPGATE_CONFIG_ALIAS_DESCRIPTION',
    'Tragen Sie hier den Alias Ihres Shops ein. Sie finden diese im Tab &quot;Integration&quot; Ihres Shops.'
);

define('SHOPGATE_CONFIG_CNAME', 'Eigene URL zur mobilen Webseite (mit http://)');
define(
    'SHOPGATE_CONFIG_CNAME_DESCRIPTION',
    'Tragen Sie hier eine eigene (per CNAME definierte) URL zur mobilen Webseite Ihres Shops ein. Sie finden die URL im Tab &quot;Integration&quot; Ihres Shops, '
    .
    'nachdem Sie diese Option unter &quot;Einstellungen&quot; &equals;&gt; &quot;Mobile Webseite / Webapp&quot; aktiviert haben.'
);

define('SHOPGATE_CONFIG_REDIRECT_LANGUAGES', 'Weitergeleitete Sprachen');
define(
    'SHOPGATE_CONFIG_REDIRECT_LANGUAGES_DESCRIPTION',
    'W&auml;hlen Sie die Sprachen aus, die auf diesen Shopgate-Shop weitergeleitet werden sollen. Es muss mindestens ' .
    'eine Sprache ausgew&auml;hlt werden. Halten Sie STRG gedr&uuml;ckt, um mehrere Eintr&auml;ge zu w&auml;hlen.'
);

### Export ###
define('SHOPGATE_CONFIG_EXPORT_SETTINGS', 'Kategorie- und Produktexport');

define('SHOPGATE_CONFIG_LANGUAGE', 'Sprache');
define(
    'SHOPGATE_CONFIG_LANGUAGE_DESCRIPTION',
    'W&auml;hlen Sie die Sprache, in der Kategorien und Produkte exportiert werden sollen.'
);

define('SHOPGATE_CONFIG_EXTENDED_CURRENCY', 'W&auml;hrung');
define('SHOPGATE_CONFIG_EXTENDED_CURRENCY_DESCRIPTION', 'W&auml;hlen Sie die W&auml;hrung f&uuml;r den Produktexport.');

define('SHOPGATE_CONFIG_EXTENDED_COUNTRY', 'Land');
define(
    'SHOPGATE_CONFIG_EXTENDED_COUNTRY_DESCRIPTION',
    'W&auml;hlen Sie das Land, f&uuml;r das Ihre Produkte und Kategorien exportiert werden sollen.'
);

define('SHOPGATE_CONFIG_EXTENDED_TAX_ZONE', 'Steuerzone f&uuml;r Shopgate');
define(
    'SHOPGATE_CONFIG_EXTENDED_TAX_ZONE_DESCRIPTION',
    'Geben Sie die Steuerzone an, die f&uuml;r Shopgate g&uuml;ltig sein soll.'
);

define('SHOPGATE_CONFIG_EXTENDED_REVERSE_CATEGORIES_SORT_ORDER', 'Kategorie-Reihenfolge umkehren');
define('SHOPGATE_CONFIG_EXTENDED_REVERSE_CATEGORIES_SORT_ORDER_ON', 'Ja');
define('SHOPGATE_CONFIG_EXTENDED_REVERSE_CATEGORIES_SORT_ORDER_OFF', 'Nein');
define(
    'SHOPGATE_CONFIG_EXTENDED_REVERSE_CATEGORIES_SORT_ORDER_DESCRIPTION',
    'W&auml;hlen Sie hier "Ja" aus, wenn die Sortierung Ihrer Kategorien in Ihrem mobilen Shop genau falsch herum ist.'
);

define('SHOPGATE_CONFIG_EXTENDED_REVERSE_ITEMS_SORT_ORDER', 'Produkt-Reihenfolge umkehren');
define('SHOPGATE_CONFIG_EXTENDED_REVERSE_ITEMS_SORT_ORDER_ON', 'Ja');
define('SHOPGATE_CONFIG_EXTENDED_REVERSE_ITEMS_SORT_ORDER_OFF', 'Nein');
define(
    'SHOPGATE_CONFIG_EXTENDED_REVERSE_ITEMS_SORT_ORDER_DESCRIPTION',
    'W&auml;hlen Sie hier "Ja" aus, wenn die Sortierung Ihrer Produkte in Ihrem mobilen Shop genau falsch herum ist.'
);

define('SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION', 'Produktbeschreibung');
define('SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_DESC_ONLY', 'Nur Beschreibung');
define('SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_SHORTDESC_ONLY', 'Nur Kurzbeschreibung');
define('SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_DESC_SHORTDESC', 'Beschreibung + Kurzbeschreibung');
define('SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_SHORTDESC_DESC', 'Kurzbeschreibung + Beschreibung');
define(
    'SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_DESCRIPTION',
    'W&auml;hlen Sie hier aus, wie die Produktbeschreibung im mobilen Shop zusammengesetzt sein soll.'
);

define('SHOPGATE_PLUGIN_PRICE_STATUS_TEXT_NOT_AVAILABLE', 'nicht k&auml;uflich');
define('SHOPGATE_PLUGIN_PRICE_ON_REQUEST_BASIC_PRICE_TEXT', 'Preis auf Anfrage');
define('SHOPGATE_CONFIG_EXTENDED_EXPORT_PRICE_ON_REQUEST_PRODUCTS', 'Export von "Preis auf Anfrage" Produkten');
define('SHOPGATE_CONFIG_EXTENDED_EXPORT_PRICE_ON_REQUEST_PRODUCTS_WITH_PRICE', 'Mit Preis');
define('SHOPGATE_CONFIG_EXTENDED_EXPORT_PRICE_ON_REQUEST_PRODUCTS_WITHOUT_PRICE', 'Ohne Preis');
define(
    'SHOPGATE_CONFIG_EXTENDED_EXPORT_PRICE_ON_REQUEST_PRODUCTS_DESCRIPTION',
    'W&auml;hlen Sie "Mit Preis", wenn Produkte mit dem Status "Preis auf Anfrage" samt dem Produktpreis zu Shopgate exportiert werden sollen.<br/>Andernfalls wird ein Preisbetrag von 0 in der gegebenen W&auml;hrung exportiert, samt einer Information, dass der Preis nur auf Anfrage erh&auml;ltlich ist.'
);
define('SHOPGATE_CONFIG_EXTENDED_EXPORT_PRODUCTS_CONTENT_MANAGED_FILES', 'Produkt Content Manager beachten');
define('SHOPGATE_CONFIG_EXTENDED_EXPORT_PRODUCTS_CONTENT_MANAGED_ON', 'Ja');
define('SHOPGATE_CONFIG_EXTENDED_EXPORT_PRODUCTS_CONTENT_MANAGED_OFF', 'Nein');
define(
    'SHOPGATE_CONFIG_EXTENDED_EXPORT_PRODUCTS_CONTENT_MANAGED_FILES_DESCRIPTION',
    'W&auml;hlen Sie "Ja", wenn Sie m&ouml;chten, dass auch zum Produkt zugeh&ouml;rige Dateien und Links zu der Produktbeschreibung hinzugef&uuml;gt werden.'
);

define('SHOPGATE_PLUGIN_MAX_ATTRIBUTE_VALUE_HEADLINE', 'Anzahl der Attributoptionen');
define(
    'SHOPGATE_PLUGIN_MAX_ATTRIBUTE_VALUE_DESCRIPTION',
    'W&auml;hlen Sie hier die maximale Anzahl der Attribute aus. Sollte die angegebene Anzahl an Attributen &uuml;berschritten werden, so wird eine effizientere Datenstruktur zum Export der Produktdaten verwendet.(Standardwert: 50)'
);

define('SHOPGATE_CONFIG_EXPORT_OPTIONS_AS_INPUT_FIELD', 'Export von Produktoptionen als Eingabefeld');
define(
    'SHOPGATE_CONFIG_EXPORT_OPTIONS_AS_INPUT_FIELD_DESCRIPTION',
    'F&uuml;gen Sie die ID der Optionen, welche als Textfeld exportiert werden sollen, der Textfl&auml;che hinzu. Sie finden diese unter "Artikelmerkmale". (Beispiel: 1,2,3)'
);

define('SHOPGATE_CONFIG_EXPORT_FILTERS_AS_PROPERTIES', 'Export von Artikel-Filtern als Eigenschaften');
define(
    'SHOPGATE_CONFIG_EXPORT_FILTERS_AS_PROPERTIES_DESCRIPTION',
    'Die ausgew&auml;hlten Artikel-Filter werden als Eigenschaften exportiert.'
);

### Bestellungsimport ###
define('SHOPGATE_CONFIG_ORDER_IMPORT_SETTINGS', 'Bestellungsimport');

define('SHOPGATE_CONFIG_EXTENDED_SHIPPING', 'Versandart');
define(
    'SHOPGATE_CONFIG_EXTENDED_SHIPPING_DESCRIPTION',
    'W&auml;hlen Sie die Versandart f&uuml;r den Bestellungsimport. Diese wird f&uuml;r die Ausweisung der Steuern der Versandkosten genutzt, sofern eine Steuerklasse f&uuml;r die Versandart ausgew&auml;hlt ist.'
);
define('SHOPGATE_CONFIG_EXTENDED_SHIPPING_NO_SELECTION', '-- keine Auswahl --');

define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SHIPPING_APPROVED', 'Versand nicht blockiert');
define(
    'SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SHIPPING_APPROVED_DESCRIPTION',
    'W&auml;hlen Sie den Status f&uuml;r Bestellungen, deren Versand bei Shopgate nicht blockiert ist.'
);

define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SHIPPING_BLOCKED', 'Versand blockiert');
define(
    'SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SHIPPING_BLOCKED_DESCRIPTION',
    'W&auml;hlen Sie den Status f&uuml;r Bestellungen, deren Versand bei Shopgate blockiert ist.'
);

define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SENT', 'Versendet');
define(
    'SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SENT_DESCRIPTION',
    'W&auml;hlen Sie den Status, mit dem Sie Bestellungen als &quot;versendet&quot; markieren.'
);

define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SEND_CONFIRMATION_MAIL_ON', 'Ja');
define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SEND_CONFIRMATION_MAIL_OFF', 'Nein');
define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SEND_CONFIRMATION_MAIL', 'Sende Bestellbest&auml;tigungs E-Mail');

define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_CANCELED', 'Storniert');
define('SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_CANCELED_NOT_SET', '- Status nicht ausgew&auml;hlt -');
define(
    'SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_CANCELED_DESCRIPTION',
    'W&auml;hlen Sie den Status f&uuml;r stornierte Bestellungen.'
);

define('SHOPGATE_CONFIG_PAYMENT_NAME_MAPPING', 'Anzeigenamen f&uuml;r Zahlungsweisen');
define(
    'SHOPGATE_CONFIG_PAYMENT_NAME_MAPPING_DESCRIPTION',
    "Individuelle Namen f&uuml;r Zahlungsweisen, die beim Bestellungsimport verwendet werden. Definiert durch '=' und getrennt durch ';'.<br/>(Beispiel: PREPAY=Vorkasse;SHOPGATE=Abwicklung durch Shopgate)<br/>"
);
define(
    'SHOPGATE_CONFIG_PAYMENT_NAME_MAPPING_LINK',
    'https://support.shopgate.com/hc/de/articles/202798386-Anbindung-an-Gambio#4.3'
);
define('SHOPGATE_CONFIG_PAYMENT_NAME_MAPPING_LINK_DESCRIPTION', "Link zur Anleitung");

### Systemeinstellungen ###
define('SHOPGATE_CONFIG_SYSTEM_SETTINGS', 'Systemeinstellungen');

define('SHOPGATE_CONFIG_SERVER_TYPE', 'Shopgate Server');
define('SHOPGATE_CONFIG_SERVER_TYPE_LIVE', 'Live');
define('SHOPGATE_CONFIG_SERVER_TYPE_PG', 'Playground');
define('SHOPGATE_CONFIG_SERVER_TYPE_CUSTOM', 'Custom');
define('SHOPGATE_CONFIG_SERVER_TYPE_CUSTOM_URL', 'Benutzerdefinierte URL zum Shopgate-Server');
define('SHOPGATE_CONFIG_SERVER_TYPE_DESCRIPTION', 'W&auml;hlen Sie hier die Server-Verbindung zu Shopgate aus.');

define('SHOPGATE_ORDER_ORDER', 'Bestellung');
define('SHOPGATE_ORDER_INVOICE_ADDRESS', 'Rechnungsadresse');
define('SHOPGATE_ORDER_SHIPPING_ADDRESS', 'Versandadresse');
