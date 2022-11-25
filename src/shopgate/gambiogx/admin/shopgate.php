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

ob_start();
require_once 'includes/application_top.php';
ob_end_clean();
define("SHOPGATE_LINK_WIKI", "http://support.shopgate.com/hc/en-us/articles/202798386");
(defined('_VALID_XTC') || defined('_GM_VALID_CALL')) or die('Direct Access to this location is not allowed.');

if (file_exists(DIR_FS_CATALOG . '/shopgate/vendor/autoload.php')) {
    include_once(DIR_FS_CATALOG . '/shopgate/vendor/autoload.php');
}

require(DIR_FS_CATALOG . '/shopgate/gambiogx/shopgate_config.php');
include_once(DIR_FS_CATALOG . '/shopgate/gambiogx/ShopgateTools.php');

$encodings = array('UTF-8', 'ISO-8859-1', 'ISO-8859-15');
$error     = array();

// determine configuration language: $_GET > $_SESSION > global (null)
$sg_language = (!empty($_GET['sg_language'])
    ? $_GET['sg_language']
    : null
);

// remove '?' characters from the language id. This can happen if a wrong formatted link is used
if (!empty($sg_language)) {
    $sg_language = trim($sg_language, '?');
    if (strpos($sg_language, '?') !== false) {
        $sg_language = explode('?', $sg_language);
        $sg_language = $sg_language[0];
    }
}

// Get Gambio GX version
$gambioGXVersion          = array(
    'main_version' => '1',
    'sub_version'  => '0',
    'revision'     => '0',
);
$gxVersionFileDestination = '/' . trim(DIR_FS_CATALOG, '/') . '/release_info.php';
if (file_exists($gxVersionFileDestination)) {
    require_once $gxVersionFileDestination;
    if (preg_match('/(?P<main_version>[1-9]+).(?P<sub_version>[0-9]+).(?P<revision>[0-9]+)/', $gx_version, $matches)) {
        $gambioGXVersion = array(
            'main_version' => $matches['main_version'],
            'sub_version'  => $matches['sub_version'],
            'revision'     => $matches['revision'],
        );
    }
}

// article properties are only available for gambio gx 2_0_7 or higher
$gxProductsPropertiesSupportEnabled = false;
if ($gambioGXVersion['main_version'] > 2
    || $gambioGXVersion['main_version'] == 2
    && ($gambioGXVersion['sub_version'] > 0
        || $gambioGXVersion['revision'] >= 7)
) {
    $gxProductsPropertiesSupportEnabled = true;
}

// determine redirect_languages for global configuration
if (($sg_language === null) && !isset($_POST['_shopgate_config']['redirect_languages'])) {
    $_POST['_shopgate_config']['redirect_languages'] = array();
}

// load configuration
if (isset($_GET['action']) && ($_GET["action"] === "save")) {
    try {
        //MODULE_PAYMENT_SHOPGATE_STATUS
        if (isset($_POST['_shopgate_config']['module_active'])) {
            $modulePaymentShopgateStatus = $_POST['_shopgate_config']['module_active'];
            unset($_POST['_shopgate_config']['module_active']);

            if ($modulePaymentShopgateStatus != MODULE_PAYMENT_SHOPGATE_STATUS) {
                if (!(TABLE_CONFIGURATION === 'gx_configurations')) {
                    $qry = 'UPDATE ' . TABLE_CONFIGURATION . ' SET configuration_value = "'
                        . xtc_db_prepare_input($modulePaymentShopgateStatus)
                        . '" WHERE configuration_key = "MODULE_PAYMENT_SHOPGATE_STATUS"';
                } else {
                    $qry = 'UPDATE ' . TABLE_CONFIGURATION . ' SET `value` = "'
                        . xtc_db_prepare_input($modulePaymentShopgateStatus)
                        . '" WHERE `key` = "configuration/MODULE_PAYMENT_SHOPGATE_STATUS"';
                }
                xtc_db_query($qry);
            }
        }

        $shopgateConfig = new ShopgateConfigGambioGx();
        // check if some settings are selected, keep default if not
        $sgEmptySettings = array(
            'language',
            'currency',
            'country',
            'tax_zone_id',
            'order_status_open',
            'order_status_shipping_blocked',
            'order_status_shipped',
            'order_status_canceled',
        );
        foreach ($sgEmptySettings as $sgEmptySetting) {
            if ($_POST['_shopgate_config'][$sgEmptySetting] == '-') {
                $_POST['_shopgate_config'][$sgEmptySetting] =
                    $shopgateConfig->{'get' . $shopgateConfig->camelize($sgEmptySetting, true)}();
            }
        }

        $shopgateConfig->loadArray($_POST['_shopgate_config']);
        if (($sg_language !== null) && !empty($_POST['sg_global_switch'])) {
            $shopgateConfig->useGlobalFor($sg_language);
        } else {
            $shopgateConfig->saveFileForLanguage(array_keys($_POST['_shopgate_config']), $sg_language);
        }

        xtc_redirect(
            FILENAME_SHOPGATE . '?sg_option=' . $_GET['sg_option'] . (($sg_language === null)
                ? ''
                : '&sg_language=' . $sg_language)
        );
    } catch (ShopgateLibraryException $e) {
        $shopgate_message = SHOPGATE_CONFIG_ERROR_SAVING;
        switch ($e->getCode()) {
            case ShopgateLibraryException::CONFIG_READ_WRITE_ERROR:
                $shopgate_message .= SHOPGATE_CONFIG_ERROR_READ_WRITE;
                break;
            case ShopgateLibraryException::CONFIG_INVALID_VALUE:
                $shopgate_message .= SHOPGATE_CONFIG_ERROR_INVALID_VALUE . $e->getAdditionalInformation();
                foreach (explode(',', $e->getAdditionalInformation()) as $errorField) {
                    $error[$errorField] = true;
                }
                break;
        }
        $shopgateConfig = $_POST['_shopgate_config']; // keep submitted form data
    }
} else {
    try {
        $shopgate_message = '';
        $shopgateConfig   = new ShopgateConfigGambioGx();

        if ($sg_language !== null) {
            $sgUseGlobalConfig = $shopgateConfig->checkUseGlobalFor($sg_language);
        }

        $shopgateConfig->loadByLanguage($sg_language);

        if ($shopgateConfig->checkDuplicates()) {
            $shopgate_message .= SHOPGATE_CONFIG_ERROR_DUPLICATE_SHOP_NUMBERS;
        }

        if ($shopgateConfig->checkMultipleConfigs()) {
            $shopgate_info = SHOPGATE_CONFIG_INFO_MULTIPLE_CONFIGURATIONS;
        }

        $shopgateConfig = $shopgateConfig->toArray();
    } catch (ShopgateLibraryException $e) {
        $shopgate_message .= SHOPGATE_CONFIG_ERROR_LOADING . SHOPGATE_CONFIG_ERROR_READ_WRITE;
        $shopgateConfig   = $shopgateConfig->toArray();
    }
}

// load all languages
$qry = xtc_db_query("SELECT LOWER(code) AS code, name, directory FROM `" . TABLE_LANGUAGES . "` ORDER BY code");

$sgLanguages = array();
while ($row = xtc_db_fetch_array($qry)) {
    $sgLanguages[$row['code']] = $row;
}

$gambioVersion        = ShopgateTools::getGambioVersion();
$gambioCompareVersion = $gambioVersion['main_version'] . '.' . $gambioVersion['sub_version'];

// gather information about the system configuration for the plugin configuration
if ($_GET["sg_option"] === "config") {
    // get order states
    $qry = xtc_db_query(
        "
        SELECT
            orders_status_id,
            " . (($sg_language === null)
            ? "CONCAT(orders_status_name, ' (', code, ')') AS orders_status_name"
            : 'orders_status_name'
        ) . ",
            code
        FROM orders_status os
        INNER JOIN languages l ON l.languages_id = os.language_id
        " . (($sg_language === null)
            ? ''
            : "WHERE LOWER(l.code) = '{$sg_language}'") . "
        ORDER BY os.orders_status_id"
    );

    $sgOrderStates = array();
    while ($row = xtc_db_fetch_array($qry)) {
        $sgOrderStates[$row['orders_status_id']] = $row;
    }

    $sgExportDescriptionTypes = array(
        SHOPGATE_SETTING_EXPORT_DESCRIPTION                  => SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_DESC_ONLY,
        SHOPGATE_SETTING_EXPORT_SHORTDESCRIPTION             => SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_SHORTDESC_ONLY,
        SHOPGATE_SETTING_EXPORT_DESCRIPTION_SHORTDESCRIPTION => SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_DESC_SHORTDESC,
        SHOPGATE_SETTING_EXPORT_SHORTDESCRIPTION_DESCRIPTION => SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_SHORTDESC_DESC,
    );

    // get tax zones
    $qry = xtc_db_query(
        "
        SELECT
            geo_zone_id,
            geo_zone_name,
            geo_zone_description
        FROM `" . TABLE_GEO_ZONES . "`
        ORDER BY geo_zone_id
    "
    );

    $sgTaxZones = array();
    while ($row = xtc_db_fetch_array($qry)) {
        $sgTaxZones[$row['geo_zone_id']] = $row;
    }

    // get currencies
    $qry = xtc_db_query(
        "
        SELECT
            *
        FROM `" . TABLE_CURRENCIES . "`
        ORDER BY title
    "
    );

    $sgCurrencies = array();
    while ($row = xtc_db_fetch_array($qry)) {
        $sgCurrencies[$row["code"]] = $row["title"];
    }

    // get countries
    $qry = xtc_db_query(
        "
        SELECT
            UPPER(countries_iso_code_2) AS countries_iso_code_2,
            countries_name
        FROM `" . TABLE_COUNTRIES . "`
        WHERE status = 1
        ORDER BY countries_name
    "
    );

    $sgCountries = array();
    while ($row = xtc_db_fetch_array($qry)) {
        $sgCountries[$row['countries_iso_code_2']] = $row;
    }

    $qry              = xtc_db_query(
        "
        SELECT DISTINCT fd.feature_name
        FROM `feature_description` fd
        INNER JOIN languages l ON l.languages_id = fd.language_id
        " . (($sg_language === null)
            ? ''
            : "WHERE LOWER(l.code) = '{$sg_language}'") . "
    "
    );
    $sgProductFilters = array();
    while ($row = xtc_db_fetch_array($qry)) {
        $sgProductFilters[] = $row;
    }

    // get directory name by language of the backend interface
    if (!empty($_SESSION['language'])) {
        $languageDirectory = strtolower(trim($_SESSION['language']));
    }
    // fallback to language in config
    if (empty($languageDirectory)) {
        $languageDirectory = $sgLanguages[$shopgateConfig['language']]['directory'];
    }

    // create a list of all installed shipping modules
    $sgInstalledShippingModules = array('' => SHOPGATE_CONFIG_EXTENDED_SHIPPING_NO_SELECTION);
    $installedShippingModules   = explode(';', MODULE_SHIPPING_INSTALLED);

    if (version_compare($gambioCompareVersion, '2.1', '>=')) {
        $languageTextManager = MainFactory::create_object('LanguageTextManager', array(), true);
    }
    foreach ($installedShippingModules as $shippingModule) {
        if (!empty($shippingModule)) {
            if (version_compare($gambioCompareVersion, '2.1', '>=')) {
                // new language system in Gambio since version 2.1
                $languageTextManager->init_from_lang_file(
                    'lang/' . $languageDirectory . '/modules/shipping/' . $shippingModule
                );
            } else {
                if (is_file(DIR_FS_LANGUAGES . $languageDirectory . '/modules/shipping/' . $shippingModule)) {
                    require(DIR_FS_LANGUAGES . $languageDirectory . '/modules/shipping/' . $shippingModule);
                }
            }

            $shippingModule         = substr($shippingModule, 0, strpos($shippingModule, '.'));
            $shippingModuleTitleKey = 'MODULE_SHIPPING_' . strtoupper($shippingModule) . '_TEXT_TITLE';
            if (defined($shippingModuleTitleKey)) {
                $sgInstalledShippingModules[$shippingModule] = constant($shippingModuleTitleKey);
            }
        }
    }

    if ($gxProductsPropertiesSupportEnabled) {
        $sgVariationTypes = array(
            SHOPGATE_SETTING_VARIATION_TYPE_BOTH      => SHOPGATE_CONFIG_VARIATION_TYPE_PRODUCTS_BOTH,
            SHOPGATE_SETTING_VARIATION_TYPE_ATTRIBUTE => SHOPGATE_CONFIG_VARIATION_TYPE_PRODUCTS_ATTRIBUTES,
            SHOPGATE_SETTING_VARIATION_TYPE_PROPERTY  => SHOPGATE_CONFIG_VARIATION_TYPE_PRODUCTS_PROPERTIES,
        );
    }
}

$version = 1;
if (version_compare($gambioCompareVersion, '2.0', '>=')) {
    $version = 2;
}
$shopgateWikiLink = 'https://support.shopgate.com/hc/en-us/articles/202798386#4';
?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $_SESSION['language_charset']; ?>">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo TITLE; ?></title>
    <?php if (version_compare($gambioCompareVersion, '3.1', '>=')): ?>
    <link rel="stylesheet" type="text/css" href="html/assets/styles/legacy/stylesheet.css">
        <script type="text/javascript" src="html/assets/javascript/legacy/gm/general.js"></script>
    <?php else: ?>
    <link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
        <script type="text/javascript" src="includes/general.js"></script>
    <?php endif; ?>
    <script type="text/javascript">
        <!--
        function sgDisplayLanguageSelection(sg_button) {
            document.getElementById('shopgate_language_selection').setAttribute('style', 'display: block;');
            sg_button.setAttribute('style', 'display: none;');
        }

        function sgLoadLanguage(sg_option) {
            var sg_language = document.getElementById("sg_language").options[document.getElementById("sg_language").selectedIndex].value;
            window.location = '<?php echo FILENAME_SHOPGATE ?>?sg_option=' + sg_option + ((sg_language.length > 0) ? '&sg_language=' + sg_language : '');
        }

        function sgToggleSettings(sg_checkbox) {
            document.getElementById("sg_settings").setAttribute('style', (sg_checkbox.checked ? 'display: none;' : 'display: table;'));
        }

        // -->
    </script>
    <style type="text/css">
        .shopgate_iframe {
            width: 1000px;
            min-height: 600px;
            height: 100%;
            border: 0;
        }

        table.shopgate_setting {
            border-bottom: 1px dotted #5a5a5a;
            width: 100%;
        }

        table.shopgate_setting tr {
            vertical-align: top;
        }

        td.shopgate_setting {
            width: 1050px;
        }

        td.shopgate_setting label {
            font-weight: bold;
        }

        td.shopgate_setting .shopgate_input label {
            font-weight: normal;
        }

        tr:nth-child(even) > td.shopgate_setting {
            background: #d6e6f3;
        }

        tr:nth-child(odd) > td.shopgate_setting {
            background: #f7f7f7;
        }

        td.shopgate_input div {
            margin-bottom: 10px;
            padding: 2px;
        }

        td.shopgate_input.error div input, td.shopgate_input.error div select {
            border-color: red;
        }

        div.shopgate_language_selection {
            font-size: 11pt;
            background: #f9f0f1;
            padding: 12px;
            margin-top: 8px;
            margin-bottom: 8px;
            border: 1px dashed #aaaaaa;
            width: 1023px;
        }

        div.shopgate_red_message {
            background: #ffd6d9;
            width;
            100%;
            padding: 10px;
        }

        div.shopgate_blue_message {
            background: #d6e9ff;
            width;
            100%;
            padding: 10px;
        }

        div.shopgate_language_selection div {
            font-size: 8pt;
            margin-bottom: 8px;
        }

        div.sg_submit {
            margin-top: 16px;
        }

        div.sg_submit input {
            padding: 2px;
        }

        #shopgate_image_wiki {
            text-align: center;
            background-color: white;
            padding-top: 50px;
            padding-bottom: 50px;
        }

        #shopgate_image_wiki img {
            width: 500px;
        }

        #shopgate_image_settings {
            background-color: white;
            padding: 20px;
        }

        #shopgate_image_settings img {
            width: 200px;
        }

        textarea {
            height: 60px;
        }
    </style>
</head>
<?php $tableClass = 'dataTableContent_gm'; ?>
<body marginwidth="0" marginheight="0" topmargin="0" bottommargin="0" leftmargin="0" rightmargin="0" bgcolor="#FFFFFF"
      onload="SetFocus();">

<!-- header //-->
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //-->

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
    <tr>
        <td class="columnLeft2" width="<?php echo BOX_WIDTH; ?>" valign="top">
            <table border="0" width="<?php echo BOX_WIDTH; ?>" cellspacing="1"
                   cellpadding="1" class="columnLeft">
                <!-- left_navigation //-->
                <?php require(DIR_WS_INCLUDES . 'column_left.php'); ?>
                <!-- left_navigation_eof //-->
            </table>
        </td>
        <!-- body_text //-->
        <td class="boxCenter" width="100%" valign="top" style="height: 100%;">
            <table border="0" width="100%" cellspacing="0" cellpadding="2" style="height:100%;">
                <tr>
                    <td>
                        <div class="pageHeading" style="background-image: url(images/gm_icons/module.png)">
                            <?php echo SHOPGATE_CONFIG_TITLE; ?>
                        </div>
                    </td>
                </tr>
                <tr style="height: 100%;">
                    <td class="main" style="height: 100%; vertical-align: top;">
                        <?php if (!empty($shopgate_message)): ?>
                            <div class="shopgate_red_message">
                                <strong style="color: red;"><?php echo SHOPGATE_CONFIG_ERROR; ?></strong>
                                <?php echo htmlentities($shopgate_message, ENT_COMPAT, "UTF-8") ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($_GET["sg_option"] === "help"): ?>
                            <div id="shopgate_image_wiki">
                                <a target="_blank" href="<?php echo SHOPGATE_LINK_WIKI; ?>">
                                    <img src="../shopgate/gambiogx/admin/includes/img/shopgate_manual_logo.jpg"
                                         alt="Shopgate Wiki"/>
                                </a>
                            </div>
                        <?php elseif ($_GET["sg_option"] === "info"): ?>
                            <iframe src="<?php echo SHOPGATE_LINK_HOME; ?>" class="shopgate_iframe"></iframe>
                            <?php
                        elseif ($_GET["sg_option"] === "config"): ?>
                            <?php echo xtc_draw_form(
                                'shopgate',
                                FILENAME_SHOPGATE,
                                'sg_option=config&action=save' . (($sg_language === null)
                                    ? ''
                                    : '&sg_language=' . $sg_language)
                            ); ?>
                            <?php if (count($sgLanguages) > 1): ?>
                                <?php if ($sg_language === null): ?>
                                    <?php if (!empty($shopgate_info)): ?>
                                        <div class="shopgate_blue_message"><strong
                                                    style="color: blue;">Info:</strong> <?php echo $shopgate_info; ?>
                                        </div>
                                        <br/>
                                    <?php endif; ?>
                                    <button onclick="sgDisplayLanguageSelection(this); return false;"
                                            id="sg_multiple_shops_button"><?php echo SHOPGATE_CONFIG_MULTIPLE_SHOPS_BUTTON ?></button>
                                <?php endif ?>
                                <div class="shopgate_language_selection" id="shopgate_language_selection"
                                     style="display: <?php echo ($sg_language !== null)
                                         ? 'block'
                                         : 'none' ?>">
                                    <div><?php echo SHOPGATE_CONFIG_LANGUAGE_SELECTION; ?></div>
                                    <select onchange="sgLoadLanguage('<?php echo $_GET["sg_option"] ?>')"
                                            id="sg_language">
                                        <option value=""><?php echo SHOPGATE_CONFIG_GLOBAL_CONFIGURATION; ?></option>
                                        <?php foreach ($sgLanguages as $sgLanguage): ?>
                                            <option value="<?php echo $sgLanguage['code']; ?>"<?php if ($sgLanguage['code'] == $sg_language) {
                                             echo ' selected="selected"';
                                         } ?>>
                                                - <?php echo $sgLanguage['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if ($sg_language !== null): ?>
                                    <input type="hidden" name="sg_global_switch" value="0"/>
                                    <input type="checkbox" name="sg_global_switch" value="1"
                                           onclick="sgToggleSettings(this)"
                                           id="sg_global_switch" <?php if (!empty($sgUseGlobalConfig)) {
                                             echo 'checked="checked"';
                                         } ?> />
                                    <label for="sg_global_switch"><?php echo SHOPGATE_CONFIG_USE_GLOBAL_CONFIG; ?></label>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div id="shopgate_image_settings">
                                <a target="_blank" href="<?php echo SHOPGATE_LINK_WIKI; ?>">
                                    <img src="../shopgate/gambiogx/admin/includes/img/shopgate_manual_logo.jpg"
                                         alt="Shopgate Wiki"/>
                                </a>
                            </div>

                            <table id="sg_settings" <?php if (!empty($sgUseGlobalConfig)) {
                                             echo 'style="display: none;';
                                         } ?>>
                                <tr>
                                    <td colspan="2">&nbsp;</td>
                                </tr>
                                <tr>
                                    <th colspan="2"
                                        style="text-align: left;"><?php echo SHOPGATE_CONFIG_CONNECTION_SETTINGS; ?></th>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_CUSTOMER_NUMBER; ?></label></td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input<?php echo empty($error['customer_number'])
                                                    ? ''
                                                    : ' error' ?>">
                                                    <div><input type="text" name="_shopgate_config[customer_number]"
                                                                value="<?php echo $shopgateConfig["customer_number"] ?>"/>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_CUSTOMER_NUMBER_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_SHOP_NUMBER; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input<?php echo empty($error['shop_number'])
                                                    ? ''
                                                    : ' error' ?>">
                                                    <div><input type="text" name="_shopgate_config[shop_number]"
                                                                value="<?php echo $shopgateConfig["shop_number"] ?>"/>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_SHOP_NUMBER_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_APIKEY; ?></label></td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input<?php echo empty($error['apikey'])
                                                    ? ''
                                                    : ' error' ?>">
                                                    <div><input type="text" name="_shopgate_config[apikey]"
                                                                value="<?php echo $shopgateConfig["apikey"] ?>"/></div>
                                                    <?php echo SHOPGATE_CONFIG_APIKEY_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">&nbsp;<br/><br/></td>
                                </tr>
                                <tr>
                                    <th colspan="2"
                                        style="text-align: left;"><?php echo SHOPGATE_CONFIG_MOBILE_REDIRECT_SETTINGS; ?></th>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_MODULE_ACTIVE; ?></label></td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <input
                                                                type="radio" <?php echo MODULE_PAYMENT_SHOPGATE_STATUS == 'True'
                                                            ? 'checked=""'
                                                            : '' ?>
                                                                value="True" name="_shopgate_config[module_active]"
                                                                id="sg_module_active_on">
                                                        <label for="sg_module_active_on"><?php echo SHOPGATE_CONFIG_MODULE_ACTIVE_ON; ?></label><br>
                                                        <input
                                                                type="radio" <?php echo MODULE_PAYMENT_SHOPGATE_STATUS == 'False'
                                                            ? 'checked=""'
                                                            : '' ?>
                                                                value="False" name="_shopgate_config[module_active]"
                                                                id="sg_module_active_off">
                                                        <label for="sg_module_active_off"><?php echo SHOPGATE_CONFIG_MODULE_ACTIVE_OFF; ?></label>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_ALIAS; ?></label></td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input<?php echo empty($error['alias'])
                                                    ? ''
                                                    : ' error' ?>">
                                                    <div><input type="text" name="_shopgate_config[alias]"
                                                                value="<?php echo $shopgateConfig["alias"] ?>"/></div>
                                                    <?php echo SHOPGATE_CONFIG_ALIAS_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_CNAME; ?></label></td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input<?php echo empty($error['cname'])
                                                    ? ''
                                                    : ' error' ?>">
                                                    <div><input type="text" name="_shopgate_config[cname]"
                                                                value="<?php echo $shopgateConfig["cname"] ?>"/></div>
                                                    <?php echo SHOPGATE_CONFIG_CNAME_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <?php if ($sg_language === null): ?>
                                    <tr>
                                        <td class="shopgate_setting" align="right">
                                            <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                                <tr>
                                                    <td width="300" class="<?php echo $tableClass; ?>">
                                                        <label><?php echo SHOPGATE_CONFIG_REDIRECT_LANGUAGES; ?></label>
                                                    </td>
                                                    <td class="<?php echo $tableClass; ?> shopgate_input<?php echo empty($error['redirect_languages'])
                                                        ? ''
                                                        : ' error' ?>">
                                                        <div>
                                                            <select multiple="multiple"
                                                                    name="_shopgate_config[redirect_languages][]">
                                                                <?php foreach ($sgLanguages as $sgLanguageCode => $sgLanguage): ?>
                                                                    <?php $sgSelected = (in_array(
                                                                        $sgLanguageCode,
                                                                        $shopgateConfig['redirect_languages']
                                                                    ))
                                                                        ? 'selected="selected"'
                                                                        : ''; ?>
                                                                    <option
                                                                            value="<?php echo $sgLanguageCode; ?>" <?php echo $sgSelected; ?>><?php echo $sgLanguage['name'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <?php echo SHOPGATE_CONFIG_REDIRECT_LANGUAGES_DESCRIPTION; ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="2">&nbsp;<br/><br/></td>
                                </tr>
                                <tr>
                                    <th colspan="2"
                                        style="text-align: left;"><?php echo SHOPGATE_CONFIG_EXPORT_SETTINGS; ?></th>
                                </tr>
                                <?php if ($sg_language === null): ?>
                                    <tr>
                                        <td class="shopgate_setting" align="right">
                                            <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                                <tr>
                                                    <td width="300" class="<?php echo $tableClass; ?>">
                                                        <label><?php echo SHOPGATE_CONFIG_LANGUAGE; ?></label></td>
                                                    <td class="<?php echo $tableClass; ?> shopgate_input<?php echo empty($error['language'])
                                                        ? ''
                                                        : ' error' ?>">
                                                        <div>
                                                            <select name="_shopgate_config[language]">
                                                                <?php if (!in_array(
                                                                    $shopgateConfig['language'],
                                                                    array_keys($sgLanguages)
                                                                )): ?>
                                                                    <option value="-"></option>
                                                                <?php endif; ?>
                                                                <?php foreach ($sgLanguages as $sgLanguageCode => $sgLanguage): ?>
                                                                    <?php $sgSelected = ($sgLanguageCode == $shopgateConfig['language'])
                                                                        ? 'selected="selected"'
                                                                        : ''; ?>
                                                                    <option
                                                                            value="<?php echo $sgLanguageCode; ?>" <?php echo $sgSelected; ?>><?php echo $sgLanguage['name']; ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <?php echo SHOPGATE_CONFIG_LANGUAGE_DESCRIPTION; ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_EXTENDED_CURRENCY; ?></label></td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <select name="_shopgate_config[currency]">
                                                            <?php if (!in_array(
                                                                $shopgateConfig['currency'],
                                                                array_keys($sgCurrencies)
                                                            )): ?>
                                                                <option value="-"></option>
                                                            <?php endif; ?>
                                                            <?php foreach ($sgCurrencies as $sgCurrencyCode => $sgCurrency): ?>
                                                                <option value="<?php echo $sgCurrencyCode ?>"
                                                                    <?php echo $shopgateConfig["currency"] == $sgCurrencyCode
                                                                        ? 'selected=""'
                                                                        : '' ?>>
                                                                    <?php echo $sgCurrency ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_CURRENCY_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_EXTENDED_COUNTRY; ?></label></td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <select name="_shopgate_config[country]">
                                                            <?php if (!in_array(
                                                                $shopgateConfig['country'],
                                                                array_keys($sgCountries)
                                                            )): ?>
                                                                <option value="-"></option>
                                                            <?php endif; ?>
                                                            <?php foreach ($sgCountries as $sgCountry): ?>
                                                                <option
                                                                        value="<?php echo $sgCountry["countries_iso_code_2"] ?>" <?php echo $shopgateConfig["country"] == $sgCountry["countries_iso_code_2"]
                                                                    ? 'selected="selected"'
                                                                    : '' ?>>
                                                                    <?php echo $sgCountry["countries_name"] ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_COUNTRY_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_EXTENDED_TAX_ZONE; ?></label></td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <select name="_shopgate_config[tax_zone_id]">
                                                            <?php if (!in_array(
                                                                $shopgateConfig['tax_zone_id'],
                                                                array_keys($sgTaxZones)
                                                            )): ?>
                                                                <option value="-"></option>
                                                            <?php endif; ?>
                                                            <?php foreach ($sgTaxZones as $sgTaxZone): ?>
                                                                <option
                                                                        value="<?php echo $sgTaxZone["geo_zone_id"] ?>" <?php echo $shopgateConfig["tax_zone_id"] == $sgTaxZone["geo_zone_id"]
                                                                    ? 'selected=""'
                                                                    : '' ?>>
                                                                    <?php echo $sgTaxZone["geo_zone_name"] ?>
                                                                    (<?php echo $sgTaxZone["geo_zone_id"] ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_TAX_ZONE_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_EXTENDED_REVERSE_CATEGORIES_SORT_ORDER; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <input
                                                                type="radio" <?php echo $shopgateConfig["reverse_categories_sort_order"]
                                                            ? 'checked=""'
                                                            : '' ?>
                                                                value="1"
                                                                name="_shopgate_config[reverse_categories_sort_order]"
                                                                id="sg_reverse_categories_sort_order_on">
                                                        <label for="sg_reverse_categories_sort_order_on"><?php echo SHOPGATE_CONFIG_EXTENDED_REVERSE_CATEGORIES_SORT_ORDER_ON; ?></label><br>
                                                        <input
                                                                type="radio" <?php echo !$shopgateConfig["reverse_categories_sort_order"]
                                                            ? 'checked=""'
                                                            : '' ?>
                                                                value="0"
                                                                name="_shopgate_config[reverse_categories_sort_order]"
                                                                id="sg_reverse_categories_sort_order_off">
                                                        <label for="sg_reverse_categories_sort_order_off"><?php echo SHOPGATE_CONFIG_EXTENDED_REVERSE_CATEGORIES_SORT_ORDER_OFF; ?></label>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_REVERSE_CATEGORIES_SORT_ORDER_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_EXTENDED_REVERSE_ITEMS_SORT_ORDER; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <input
                                                                type="radio" <?php echo $shopgateConfig["reverse_items_sort_order"]
                                                            ? 'checked=""'
                                                            : '' ?>
                                                                value="1"
                                                                name="_shopgate_config[reverse_items_sort_order]"
                                                                id="sg_reverse_items_sort_order_on">
                                                        <label for="sg_reverse_items_sort_order_on"><?php echo SHOPGATE_CONFIG_EXTENDED_REVERSE_ITEMS_SORT_ORDER_ON; ?></label><br>
                                                        <input
                                                                type="radio" <?php echo !$shopgateConfig["reverse_items_sort_order"]
                                                            ? 'checked=""'
                                                            : '' ?>
                                                                value="0"
                                                                name="_shopgate_config[reverse_items_sort_order]"
                                                                id="sg_reverse_items_sort_order_off">
                                                        <label for="sg_reverse_items_sort_order_off"><?php echo SHOPGATE_CONFIG_EXTENDED_REVERSE_ITEMS_SORT_ORDER_OFF; ?></label>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_REVERSE_ITEMS_SORT_ORDER_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <select name="_shopgate_config[export_description_type]">
                                                            <?php foreach ($sgExportDescriptionTypes as $sgDescriptionType => $sgDescriptionTypeName): ?>
                                                                <option value="<?php echo $sgDescriptionType; ?>"
                                                                    <?php echo $shopgateConfig["export_description_type"] == $sgDescriptionType
                                                                        ? 'selected=""'
                                                                        : '' ?>>
                                                                    <?php echo $sgDescriptionTypeName; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_PRODUCTSDESCRIPTION_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <?php if ($gxProductsPropertiesSupportEnabled): ?>
                                    <tr>
                                        <td class="shopgate_setting" align="right">
                                            <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                                <tr>
                                                    <td width="300" class="<?php echo $tableClass; ?>">
                                                        <label><?php echo SHOPGATE_CONFIG_EXTENDED_VARIATION_TYPE; ?></label>
                                                    </td>
                                                    <td class="<?php echo $tableClass; ?> shopgate_input">
                                                        <div>
                                                            <select name="_shopgate_config[variation_type]">
                                                                <?php foreach ($sgVariationTypes as $sgVariationType => $sgVariationTypeName): ?>
                                                                    <option value="<?php echo $sgVariationType ?>"
                                                                        <?php echo $shopgateConfig["variation_type"] == $sgVariationType
                                                                            ? 'selected=""'
                                                                            : '' ?>>
                                                                        <?php echo $sgVariationTypeName ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <?php echo SHOPGATE_CONFIG_EXTENDED_VARIATION_TYPE_DESCRIPTION; ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_EXTENDED_EXPORT_PRICE_ON_REQUEST_PRODUCTS; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <input
                                                                type="radio" <?php echo $shopgateConfig["export_price_on_request"]
                                                            ? 'checked=""'
                                                            : '' ?>
                                                                value="1"
                                                                name="_shopgate_config[export_price_on_request]"
                                                                id="sg_export_price_on_request_products_with_price">
                                                        <label for="sg_export_price_on_request_products_with_price"><?php echo SHOPGATE_CONFIG_EXTENDED_EXPORT_PRICE_ON_REQUEST_PRODUCTS_WITH_PRICE; ?></label><br>
                                                        <input
                                                                type="radio" <?php echo !$shopgateConfig["export_price_on_request"]
                                                            ? 'checked=""'
                                                            : '' ?>
                                                                value="0"
                                                                name="_shopgate_config[export_price_on_request]"
                                                                id="sg_export_price_on_request_products_without_price">
                                                        <label for="sg_export_price_on_request_products_without_price"><?php echo SHOPGATE_CONFIG_EXTENDED_EXPORT_PRICE_ON_REQUEST_PRODUCTS_WITHOUT_PRICE; ?></label>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_EXPORT_PRICE_ON_REQUEST_PRODUCTS_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label><?php echo SHOPGATE_CONFIG_EXTENDED_EXPORT_PRODUCTS_CONTENT_MANAGED_FILES; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <input
                                                                type="radio" <?php echo $shopgateConfig["export_products_content_managed_files"]
                                                            ? 'checked=""'
                                                            : '' ?>
                                                                value="1"
                                                                name="_shopgate_config[export_products_content_managed_files]"
                                                                id="sg_export_products_content_managed_files_yes">
                                                        <label for="sg_export_products_content_managed_files_yes"><?php echo SHOPGATE_CONFIG_EXTENDED_EXPORT_PRODUCTS_CONTENT_MANAGED_ON; ?></label><br>
                                                        <input
                                                                type="radio" <?php echo !$shopgateConfig["export_products_content_managed_files"]
                                                            ? 'checked=""'
                                                            : '' ?>
                                                                value="0"
                                                                name="_shopgate_config[export_products_content_managed_files]"
                                                                id="sg_export_products_content_managed_files_no">
                                                        <label for="sg_export_products_content_managed_files_no"><?php echo SHOPGATE_CONFIG_EXTENDED_EXPORT_PRODUCTS_CONTENT_MANAGED_OFF; ?></label>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_EXPORT_PRODUCTS_CONTENT_MANAGED_FILES_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label for="sg_max_attributes"><?php echo SHOPGATE_PLUGIN_MAX_ATTRIBUTE_VALUE_HEADLINE; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <input type="text"
                                                               value="<?php echo $shopgateConfig["max_attributes"] ?>"
                                                               name="_shopgate_config[max_attributes]"
                                                               id="sg_max_attributes">
                                                        <br>
                                                    </div>
                                                    <?php echo SHOPGATE_PLUGIN_MAX_ATTRIBUTE_VALUE_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table width="100%" cellspacing="0" cellpadding="4" border="0"
                                               class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label for="sg_export_option_as_input_field"><?php echo SHOPGATE_CONFIG_EXPORT_OPTIONS_AS_INPUT_FIELD; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                            <textarea
                                    name="_shopgate_config[export_option_as_input_field]"
                                    id="sg_export_option_as_input_field"
                            ><?php echo implode(',', $shopgateConfig["export_option_as_input_field"]) ?></textarea>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXPORT_OPTIONS_AS_INPUT_FIELD_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label for="sg_export_filters_as_properties"><?php echo SHOPGATE_CONFIG_EXPORT_FILTERS_AS_PROPERTIES; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <?php $inputSize = (count($sgProductFilters) <= 10)
                                                            ? count($sgProductFilters)
                                                            : 10 ?>
                                                        <select multiple="multiple"
                                                                name="_shopgate_config[export_filters_as_properties][]"
                                                                id="sg_export_filters_as_properties"
                                                                size="<?php echo $inputSize ?>">
                                                            <?php foreach ($sgProductFilters as $filter): ?>
                                                                <?php $selected = (in_array(
                                                                    $filter['feature_name'],
                                                                    $shopgateConfig["export_filters_as_properties"]
                                                                ))
                                                                    ? 'selected="selected"'
                                                                    : '' ?>
                                                                <option value="<?php echo $filter['feature_name']; ?>" <?php echo $selected; ?>>
                                                                    <?php echo $filter['feature_name'] ?>
                                                                </option>
                                                            <?php endforeach ?>
                                                        </select>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXPORT_FILTERS_AS_PROPERTIES_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="2">&nbsp;<br/><br/></td>
                                </tr>
                                <tr>
                                    <th colspan="2"
                                        style="text-align: left;"><?php echo SHOPGATE_CONFIG_ORDER_IMPORT_SETTINGS; ?></th>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label for="sg_shipping"><?php echo SHOPGATE_CONFIG_EXTENDED_SHIPPING; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <select name="_shopgate_config[shipping]" id="sg_shipping">
                                                            <?php foreach ($sgInstalledShippingModules as $sgShippingModuleId => $sgShippingModuleName): ?>
                                                                <option value="<?php echo $sgShippingModuleId ?>"
                                                                    <?php echo $shopgateConfig["shipping"] == $sgShippingModuleId
                                                                        ? 'selected=""'
                                                                        : '' ?>>
                                                                    <?php echo $sgShippingModuleName ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_SHIPPING_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label for="sg_order_status_open"><?php echo SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SHIPPING_APPROVED; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <select name="_shopgate_config[order_status_open]"
                                                                id="sg_order_status_open">
                                                            <?php if (!in_array(
                                                                $shopgateConfig['order_status_open'],
                                                                array_keys($sgOrderStates)
                                                            )): ?>
                                                                <option value="-"></option>
                                                            <?php endif; ?>
                                                            <?php foreach ($sgOrderStates as $sgOrderState): ?>
                                                                <?php $selected = (
                                                                    ($shopgateConfig['order_status_open'] == $sgOrderState['orders_status_id']) &&
                                                                    ($shopgateConfig['language'] == $sgOrderState['code']))
                                                                    ? 'selected="selected"'
                                                                    : ($shopgateConfig['order_status_open'] == $sgOrderState['orders_status_id'])
                                                                        ? 'selected="selected"'
                                                                        : '';
                                                                ?>
                                                                <option
                                                                        value="<?php echo $sgOrderState["orders_status_id"] ?>" <?php echo $selected; ?>>
                                                                    <?php echo $sgOrderState["orders_status_name"] ?>
                                                                    (<?php echo $sgOrderState["orders_status_id"] ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SHIPPING_APPROVED_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label for="sg_order_status_shipping_blocked"><?php echo SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SHIPPING_BLOCKED; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <select name="_shopgate_config[order_status_shipping_blocked]"
                                                                id="sg_order_status_shipping_blocked">
                                                            <?php if (!in_array(
                                                                $shopgateConfig['order_status_shipping_blocked'],
                                                                array_keys($sgOrderStates)
                                                            )): ?>
                                                                <option value="-"></option>
                                                            <?php endif; ?>
                                                            <?php foreach ($sgOrderStates as $sgOrderState): ?>
                                                                <?php $selected = (
                                                                    ($shopgateConfig['order_status_shipping_blocked'] == $sgOrderState['orders_status_id']) &&
                                                                    ($shopgateConfig['language'] == $sgOrderState['code']))
                                                                    ? 'selected="selected"'
                                                                    : ($shopgateConfig['order_status_shipping_blocked'] == $sgOrderState['orders_status_id'])
                                                                        ? 'selected="selected"'
                                                                        : '';
                                                                ?>
                                                                <option
                                                                        value="<?php echo $sgOrderState["orders_status_id"] ?>" <?php echo $selected; ?>>
                                                                    <?php echo $sgOrderState["orders_status_name"] ?>
                                                                    (<?php echo $sgOrderState["orders_status_id"] ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SHIPPING_BLOCKED_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label for="sg_order_status_shipped"><?php echo SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SENT; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <select name="_shopgate_config[order_status_shipped]"
                                                                id="sg_order_status_shipped">
                                                            <?php if (!in_array(
                                                                $shopgateConfig['order_status_shipped'],
                                                                array_keys($sgOrderStates)
                                                            )): ?>
                                                                <option value="-"></option>
                                                            <?php endif; ?>
                                                            <?php foreach ($sgOrderStates as $sgOrderState): ?>
                                                                <?php $selected = (
                                                                    ($shopgateConfig['order_status_shipped'] == $sgOrderState['orders_status_id']) &&
                                                                    ($shopgateConfig['language'] == $sgOrderState['code']))
                                                                    ? 'selected="selected"'
                                                                    : ($shopgateConfig['order_status_shipped'] == $sgOrderState['orders_status_id'])
                                                                        ? 'selected="selected"'
                                                                        : '';
                                                                ?>
                                                                <option
                                                                        value="<?php echo $sgOrderState["orders_status_id"] ?>" <?php echo $selected; ?>>
                                                                    <?php echo $sgOrderState["orders_status_name"] ?>
                                                                    (<?php echo $sgOrderState["orders_status_id"] ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SENT_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label for="sg_order_status_canceled"><?php echo SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_CANCELED; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <select name="_shopgate_config[order_status_canceled]"
                                                                id="sg_order_status_canceled">
                                                            <?php if (!in_array(
                                                                $shopgateConfig['order_status_canceled'],
                                                                array_keys($sgOrderStates)
                                                            )): ?>
                                                                <option value="-"></option>
                                                            <?php endif; ?>
                                                            <option
                                                                    value="-1"><?php echo SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_CANCELED_NOT_SET; ?></option>
                                                            <?php foreach ($sgOrderStates as $sgOrderState): ?>
                                                                <?php $selected = (
                                                                    ($shopgateConfig['order_status_canceled'] == $sgOrderState['orders_status_id']) &&
                                                                    ($shopgateConfig['language'] == $sgOrderState['code']))
                                                                    ? 'selected="selected"'
                                                                    : ($shopgateConfig['order_status_canceled'] == $sgOrderState['orders_status_id'])
                                                                        ? 'selected="selected"'
                                                                        : '';
                                                                ?>
                                                                <option
                                                                        value="<?php echo $sgOrderState["orders_status_id"] ?>" <?php echo $selected; ?>>
                                                                    <?php echo $sgOrderState["orders_status_name"] ?>
                                                                    (<?php echo $sgOrderState["orders_status_id"] ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_CANCELED_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table width="100%" cellspacing="0" cellpadding="4" border="0"
                                               class="shopgate_setting">
                                            <tr>
                                                <td width="300"
                                                    class="dataTableContent <?php echo $tableClass; ?>">
                                                    <label for="sg_payment_name_mapping"><?php echo SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SEND_CONFIRMATION_MAIL; ?></label>
                                                </td>
                                                <td class="dataTableContent <?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <input
                                                                type="radio" <?php echo $shopgateConfig["send_order_confirmation_mail"]
                                                            ? 'checked=""'
                                                            : '' ?> value="1"
                                                                name="_shopgate_config[send_order_confirmation_mail]">
                                                        <?php echo SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SEND_CONFIRMATION_MAIL_ON; ?>
                                                        <br>
                                                        <input
                                                                type="radio" <?php echo !$shopgateConfig["send_order_confirmation_mail"]
                                                            ? 'checked=""'
                                                            : '' ?> value="0"
                                                                name="_shopgate_config[send_order_confirmation_mail]">
                                                        <?php echo SHOPGATE_CONFIG_EXTENDED_STATUS_ORDER_SEND_CONFIRMATION_MAIL_OFF; ?>
                                                        <br>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table width="100%" cellspacing="0" cellpadding="4" border="0"
                                               class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label for="sg_payment_name_mapping"><?php echo SHOPGATE_CONFIG_PAYMENT_NAME_MAPPING; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <textarea name="_shopgate_config[payment_name_mapping]"
                                                                  id="sg_payment_name_mapping"><?php echo $shopgateConfig["payment_name_mapping"] ?></textarea>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_PAYMENT_NAME_MAPPING_DESCRIPTION; ?>
                                                    <a target="_blank"
                                                       href="<?php echo SHOPGATE_CONFIG_PAYMENT_NAME_MAPPING_LINK; ?>">
                                                        <?php echo SHOPGATE_CONFIG_PAYMENT_NAME_MAPPING_LINK_DESCRIPTION; ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">&nbsp;<br/><br/></td>
                                </tr>
                                <tr>
                                    <th colspan="2"
                                        style="text-align: left;"><?php echo SHOPGATE_CONFIG_SYSTEM_SETTINGS; ?></th>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label for="sg_encoding"><?php echo SHOPGATE_CONFIG_EXTENDED_ENCODING; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input">
                                                    <div>
                                                        <select name="_shopgate_config[encoding]" id="sg_encoding">
                                                            <?php foreach ($encodings as $encoding): ?>
                                                                <option <?php if ($shopgateConfig['encoding'] == $encoding) {
                                                                echo 'selected="selected"';
                                                            } ?>>
                                                                    <?php echo $encoding; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_EXTENDED_ENCODING_DESCRIPTION; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="shopgate_setting" align="right">
                                        <table cellspacing="0" cellpadding="4" border="0" class="shopgate_setting">
                                            <tr>
                                                <td width="300" class="<?php echo $tableClass; ?>">
                                                    <label for="sg_server"><?php echo SHOPGATE_CONFIG_SERVER_TYPE; ?></label>
                                                </td>
                                                <td class="<?php echo $tableClass; ?> shopgate_input<?php echo empty($error['api_url'])
                                                    ? ''
                                                    : ' error' ?>">
                                                    <div>
                                                        <select name="_shopgate_config[server]" id="sg_server">
                                                            <option
                                                                    value="live" <?php echo $shopgateConfig["server"] == 'live'
                                                                ? 'selected=""'
                                                                : '' ?>>
                                                                <?php echo SHOPGATE_CONFIG_SERVER_TYPE; ?>
                                                            </option>
                                                            <option value="pg" <?php echo $shopgateConfig["server"] == 'pg'
                                                                ? 'selected=""'
                                                                : '' ?>>
                                                                <?php echo SHOPGATE_CONFIG_SERVER_TYPE_PG; ?>
                                                            </option>
                                                            <option
                                                                    value="custom" <?php echo $shopgateConfig["server"] == 'custom'
                                                                ? 'selected=""'
                                                                : '' ?>>
                                                                <?php echo SHOPGATE_CONFIG_SERVER_TYPE_CUSTOM; ?>
                                                            </option>
                                                        </select>
                                                        <br/>
                                                        <input type="text" name="_shopgate_config[api_url]"
                                                               value="<?php echo $shopgateConfig["api_url"] ?>"/> <?php echo SHOPGATE_CONFIG_SERVER_TYPE_CUSTOM_URL; ?>
                                                    </div>
                                                    <?php echo SHOPGATE_CONFIG_SERVER_TYPE_CUSTOM_URL; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <div class="sg_submit"><input type="submit" value="<?php echo SHOPGATE_CONFIG_SAVE; ?>"
                                                          onclick="this.blur();"
                                                          class="button"></div>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </td>
        <!-- body_text_eof //-->
    </tr>
</table>
<!-- body_eof //-->

<!-- footer //-->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //-->
<br/>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
