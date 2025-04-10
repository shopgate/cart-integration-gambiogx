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
class ShopgateItemModel extends Shopgate_Model_Catalog_Product
{
    const ITEM_TYPE_SIMPLE                     = 'simple';
    const ITEM_TYPE_CHILD_ATTRIBUTE            = 'child_attribute';
    const ITEM_TYPE_CHILD_PROPERTY_COMBINATION = 'child_property_combination';

    /**@var array */
    protected $shopCurrency;

    /** @var string */
    protected $ggxVersion;

    /** @var int */
    protected $languageId;

    /** @var ShopgateConfigGambioGx */
    protected $config;

    /** @var ShopgateLogger */
    protected $shopgateLogger;

    /** @var int */
    protected $zoneId;

    /** @var int */
    protected $countryId;

    /** @var int */
    protected $exchangeRate;

    /**
     * Caches all the categories that inherit
     * products from their children
     *
     * @var array
     */
    protected $inheritCategoryCache;

    /** @var null|array */
    private $priceStatus = null;

    /** @var ShopgateDatabaseHelper */
    private $databaseHelper;

    /** @var ShopgateItemPropertyHelper */
    protected $propertyHelper;

    /** @var ShopgateCustomizerSetHelper */
    private $customizerSetHelper;

    /**
     * @param ShopgateDatabaseHelper      $databaseHelper
     * @param ShopgateItemPropertyHelper  $shopgateItemPropertyHelper
     * @param ShopgateCustomizerSetHelper $customizerSetHelper
     * @param ShopgateConfigGambioGx      $config
     * @param int                         $languageId
     * @param array                       $shopCurrency
     * @param int                         $countryId
     * @param int                         $zoneId
     * @param int                         $exchangeRate
     */
    public function __construct(
        ShopgateDatabaseHelper $databaseHelper,
        ShopgateItemPropertyHelper $shopgateItemPropertyHelper,
        ShopgateCustomizerSetHelper $customizerSetHelper,
        $config,
        $languageId,
        $shopCurrency,
        $countryId,
        $zoneId,
        $exchangeRate
    ) {
        $this->languageId          = $languageId;
        $this->shopCurrency        = $shopCurrency;
        $this->config              = $config;
        $this->shopgateLogger      = ShopgateLogger::getInstance();
        $this->ggxVersion          = ShopgateTools::getGambioVersion();
        $this->exchangeRate        = $exchangeRate;
        $this->countryId           = $countryId;
        $this->zoneId              = $zoneId;
        $this->databaseHelper      = $databaseHelper;
        $this->propertyHelper      = $shopgateItemPropertyHelper;
        $this->customizerSetHelper = $customizerSetHelper;

        parent::__construct();
    }

    /**
     * Takes all Gx-Customizer-Sets and returns all kinds of possible input fields indexed by the specific customizer
     * set
     *
     * @param array $gxCustomizerSets [][][]
     *
     * @return array[customizer_set_id][inputfield_number][<inputfield_dataelement>]
     */
    public function getInputFieldsFromGxCustomizerSets($gxCustomizerSets = array())
    {
        $inputFields = array();
        foreach ($gxCustomizerSets as $gxCustomizerGroupId => $gxCustomizerGroup) {
            $inputFields[$gxCustomizerGroupId] = array();

            // start with input field number 0 on every new customizer set
            $inputFieldNumber = 0;
            if (is_array($gxCustomizerGroup)) {
                foreach ($gxCustomizerGroup as $gxCustomizerSurfaceId => $gxCustomizerSurface) {
                    // Sort all elements screen position (take the top-left pixel from labels and bottom right pixel from input fields)
                    // -----------------------------------------------------------------------------------------------------------------
                    // Calculate sort order indices
                    // -> minimum and maximum x and y values need to be found first
                    $minX = null;
                    $maxX = null;
                    $minY = null;
                    $maxY = null;
                    foreach ($gxCustomizerSurface as $gxCustomizerElement) {
                        // skip unsupported elements like file-uploads, images and dropdowns
                        if ($gxCustomizerElement['elements_group_type'] != 'text'
                            && $gxCustomizerElement['elements_group_type'] != 'text_input'
                            && $gxCustomizerElement['elements_group_type'] != 'textarea'
                        ) {
                            continue;
                        }

                        $pX = $gxCustomizerElement['elements_position_x'];
                        $pY = $gxCustomizerElement['elements_position_y'];
                        // get bottom right pixel if it's not a label
                        if ($gxCustomizerElement['elements_group_type'] != 'text') {
                            $pX += $gxCustomizerElement['elements_width'];
                            $pY += $gxCustomizerElement['elements_height'];
                        }

                        // get min/max values for x/y coordinates
                        if ($minX === null || $pX < $minX) {
                            $minX = $pX;
                        }
                        if ($maxX === null || $pX > $maxX) {
                            $maxX = $pX;
                        }
                        if ($minY === null || $pY < $minY) {
                            $minY = $pY;
                        }
                        if ($maxY === null || $pY > $maxY) {
                            $maxY = $pY;
                        }
                    }

                    // calculate lineWidth to be able to convert 2d coordinates to a 1d sort order array
                    $lineWidth = $maxX - $minX;

                    // calculate sort order for each element and add it to the mapper
                    $sortOrderMapper = array();
                    foreach ($gxCustomizerSurface as $elementIndex => $gxCustomizerElement) {
                        // skip unsupported elements like file-uploads, images and dropdowns
                        if ($gxCustomizerElement['elements_group_type'] != 'text'
                            && $gxCustomizerElement['elements_group_type'] != 'text_input'
                            && $gxCustomizerElement['elements_group_type'] != 'textarea'
                        ) {
                            continue;
                        }

                        $pX = $gxCustomizerElement['elements_position_x'];
                        $pY = $gxCustomizerElement['elements_position_y'];

                        // get bottom right pixel if it's not a label
                        if ($gxCustomizerElement['elements_group_type'] != 'text') {
                            $pX += $gxCustomizerElement['elements_width'];
                            $pY += $gxCustomizerElement['elements_height'];
                        }

                        // Re-position offsets
                        $pX -= $minX;
                        $pY -= $minY;

                        // calculate the sort order index and store in the mapper
                        $sortOrderIndex = $pY * $lineWidth
                            + $pX; // Default formula in graphics development to get a 1d index from 2d coordinates
                        if (empty($sortOrderMapper[$sortOrderIndex])) {
                            $sortOrderMapper[$sortOrderIndex] = array();
                        }
                        // save into sub-array since it is possible to have multiple elements positioned on the exact same coordinates
                        // -> in that case first comes first
                        $sortOrderMapper[$sortOrderIndex][] = $elementIndex;
                    }

                    // sort the mapper by sortOrder
                    ksort($sortOrderMapper);

                    // create an array of elements sorted by their local position based on screen coordinates (from top-left to bottom-right linewise)
                    // -> it will also be filtered to only contain supported elements
                    $gxCustomizerSurfaceReordered = array();
                    foreach ($sortOrderMapper as $sortOrderMapElements) {
                        // should only have one element at most
                        foreach ($sortOrderMapElements as $elementIndex) {
                            $gxCustomizerSurfaceReordered[] = $gxCustomizerSurface[$elementIndex];
                        }
                    }

                    // The reordered surface can now be converted to input fields
                    $label      = '';
                    $inputCount = count($gxCustomizerSurfaceReordered);
                    foreach ($gxCustomizerSurfaceReordered as $gxCustomizerSurfaceElement) {
                        // concatenate all labels until the first input field is located
                        if ($gxCustomizerSurfaceElement['elements_group_type'] == 'text') {
                            if (!empty($label)) {
                                $label .= ' ';
                            }
                            // Read priority: Take value if not empty -> take name else
                            $label
                                .= !empty($gxCustomizerSurfaceElement['elements_values_value'])
                                ? $gxCustomizerSurfaceElement['elements_values_value']
                                : $gxCustomizerSurfaceElement['elements_values_name'];
                        } else {
                            $inputFieldNumber++;

                            $newInputFields = $this->customizerSetHelper->generateInputField(
                                $gxCustomizerSurfaceElement,
                                $inputFieldNumber,
                                $inputCount
                            );

                            $inputFields[$gxCustomizerGroupId] = $inputFields[$gxCustomizerGroupId] + $newInputFields;
                        }
                    }
                }
            }

            // Set to "inputs available" if any input fields have been found to export
            if (!empty($inputFields[$gxCustomizerGroupId])) {
                $inputFields[$gxCustomizerGroupId]['has_input_fields'] = '1';
            }
        }

        return $inputFields;
    }

    /**
     * Reads the maximum count of different options a product can have at its max.
     * Then creates of all additional fields for the csv file and returns it
     *
     * @return array
     */
    public function getOptionFieldArray()
    {
        $optionFieldArray = array();

        $qry = "
            SELECT MAX( `resultset`.`options_count` ) AS `highest_options_count`
            FROM (
                SELECT `temp_result`.`products_id`, COUNT( `temp_result`.`products_id` ) AS `options_count`
                FROM (
                    SELECT DISTINCT `products_id`, `options_id`
                    FROM  `" . TABLE_PRODUCTS_ATTRIBUTES . "`
                    ) AS `temp_result`
                GROUP BY `temp_result`.`products_id`
            ) AS `resultset`
            LIMIT 1
            ";

        $query = xtc_db_query($qry);
        if (!empty($query)) {
            $maxCount = xtc_db_fetch_array($query);
            if (!empty($maxCount)) {
                $maxCount = $maxCount['highest_options_count'];
            }

            // create the additional array
            for ($i = 1; $i <= $maxCount; $i++) {
                $optionFieldArray["option_{$i}"]        = '';
                $optionFieldArray["option_{$i}_values"] = '';
            }
        }

        return $optionFieldArray;
    }

    /**
     * Read the whole attribute data to an product from the database
     * and bring them into a structure as needed
     *
     * @param array      $product
     * @param int|double $tax_rate
     * @param bool       $forXml
     *
     * @return array
     */
    public function getVariations($product, $tax_rate, $forXml = false)
    {
        $sg_prod_var_attributes = array();

        $optionsAsTextFieldIds = $this->config->getExportOptionAsInputField();

        // The products most not have both variant types selected at the same time!
        if (!empty($product['attributes_products_id']) && !empty($product['properties_products_id'])) {
            // Don't return any data in this case
            return array();
        }

        $gxAttributeVPESupportEnabled = $this->isAttributeVpeSupported();

        // Get attributes if selected
        if (($this->config->getVariationType() == SHOPGATE_SETTING_VARIATION_TYPE_ATTRIBUTE
                || $this->config->getVariationType() == SHOPGATE_SETTING_VARIATION_TYPE_BOTH)
            && !empty($product['attributes_products_id'])
        ) {
            // get article attributes (options)
            $qry
                = "
                SELECT
                    pa.products_attributes_id,
                    pa.sortorder,
                    po.products_options_id,
                    pov.products_options_values_id,
                    po.products_options_name,
                    pov.products_options_values_name,
                    pa.attributes_model,
                    pa.options_values_price,
                    pa.price_prefix,
                    pa.options_values_weight,
                    pa.attributes_stock,
                    pa.weight_prefix," .
                ($gxAttributeVPESupportEnabled
                    ? ("\n                    vpe.products_vpe_name,")
                    : ("")
                ) . "
                    pov.gm_filename
                FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                INNER JOIN " . TABLE_PRODUCTS_OPTIONS . " po ON (pa.options_id = po.products_options_id AND po.language_id = $this->languageId)
                INNER JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES
                . " pov ON (pa.options_values_id = pov.products_options_values_id AND pov.language_id = $this->languageId)"
                .
                ($gxAttributeVPESupportEnabled
                    ? ("\n                LEFT JOIN " . TABLE_PRODUCTS_VPE
                        . " vpe ON (vpe.products_vpe_id = '" . $product['products_vpe'] . "' AND vpe.language_id = $this->languageId)") // Use VPE ID from $product array
                    : ("")
                ) . "
                WHERE pa.products_id = '" . $product['products_id'] . "'
                    AND (pov.products_options_values_name != 'TEXTFELD' " . (!empty($optionsAsTextFieldIds)
                    ? " AND pa.options_id NOT IN ("
                    . implode(',', $optionsAsTextFieldIds) . "))"
                    : ")")
                . " ORDER BY po.products_options_id, pa.sortorder ASC";

            $query = xtc_db_query($qry);

            //        $options = array_pad(array(), 5, "");
            $options = array();

            $i   = -1;
            $old = null;
            while ($variation = xtc_db_fetch_array($query)) {
                if ($variation["products_options_id"] != $old || is_null($old)) {
                    $i++;
                    $old = $variation["products_options_id"];
                }
                $options[$i][] = $variation;
            }

            if (empty($options)) {
                return array();
            }

            // Find and rename duplicate option-value names
            foreach ($options as $optionIndex => $singleOption) {
                // Check all option-value names for duplicate names
                foreach ($singleOption as $key => $optionVariation) {
                    if (!empty($optionVariation)) {
                        // Compare with following entries
                        $indexNumber = 1;
                        for ($i = $key + 1; $i < count($singleOption); $i++) {
                            if (trim($singleOption[$i]['products_options_values_name']) == trim(
                                    $optionVariation['products_options_values_name']
                                )
                            ) {
                                $indexNumber++;
                                $options[$optionIndex][$i]['products_options_values_name'] .= " $indexNumber";
                            }
                        }
                        // Add index 1 to the actual name if duplicate name-entries found
                        if ($indexNumber > 1) {
                            $options[$optionIndex][$key]['products_options_values_name'] .= " 1";

                            // Refresh the working variable for further operation
                            $singleOption = $options[$optionIndex];
                        }
                    }
                }
            }

            if ($forXml) {
                return $options;
            }

            $countVariations = 1;
            foreach ($options as $option) {
                $countVariations *= count($option);
            }

            if ($countVariations > $this->config->getMaxAttributes()) {
                $this->buildOptions($sg_prod_var_attributes, $options, $tax_rate);
                $sg_prod_var_attributes["has_options"] = 1;
            } else {
                $this->buildAttributes($sg_prod_var_attributes, $options);
                $sg_prod_var_attributes["has_options"] = 0;
            }
        }

        return $sg_prod_var_attributes;
    }

    /**
     * Read the property combinations to a product from the database
     *
     * @param array               $product
     * @param double|float|string $tax_rate
     *
     * @return array
     */
    public function generatePropertyCombos($product, $tax_rate)
    {
        $sg_prod_var_properties_combis = array();
        // get article properties (only for gambio gx 2_0_7 or higher)
        $gxProductsPropertiesSupportEnabled = $this->isPropertySupportEnabled();

        $optionsAsTextFieldIds = $this->config->getExportOptionAsInputField();

        if (($this->config->getVariationType() == SHOPGATE_SETTING_VARIATION_TYPE_PROPERTY
                || $this->config->getVariationType() == SHOPGATE_SETTING_VARIATION_TYPE_BOTH)
            && $gxProductsPropertiesSupportEnabled
            && !empty($product['properties_products_id'])
        ) {
            $qry
                = "
                SELECT
                    `pd`.`properties_id`,
                    `pd`.`properties_name`,
                    `ppc`.`products_properties_combis_id`,
                    `ppc`.`products_id`,
                    `ppc`.`combi_model`,
                    `ppc`.`combi_quantity`,
                    `ppc`.`combi_weight`,
                    `ppc`.`combi_price_type`,
                    `ppc`.`combi_price`,"
                . ($this->databaseHelper->checkColumn(TABLE_PRODUCTS_PROPERTIES_COMBIS, "combi_image")
                    ? "`ppc`.`combi_image`,"
                    : "")
                . ($this->databaseHelper->checkColumn(TABLE_PRODUCTS_PROPERTIES_COMBIS, "combi_ean")
                    ? "`ppc`.`combi_ean`,"
                    : "") .
                "`ppc`.`vpe_value` as `products_vpe_value`,
                    `vpe`.`products_vpe_name`,
                    `pv`.`value_price` AS `display_price`,
                    `pvd`.`values_name`," .
                (ShopgateTools::isGambioVersionLowerThan("2.1")
                    ? "`pv`.`value_price_type` AS `display_price_type`,"
                    : "") .
                "`ss`.`shipping_status_name` 
                    FROM `" . TABLE_PRODUCTS_PROPERTIES_COMBIS . "` AS `ppc`
                    INNER JOIN `" . TABLE_PRODUCTS_PROPERTIES_COMBIS_VALUES . "` AS `ppcv` ON(`ppc`.`products_properties_combis_id` = `ppcv`.`products_properties_combis_id`)
                    INNER JOIN `" . TABLE_PROPERTIES_VALUES . "` AS `pv` ON(`ppcv`.`properties_values_id` = `pv`.`properties_values_id`)
                    INNER JOIN `" . TABLE_PROPERTIES_VALUES_DESCRIPTION . "` AS `pvd` ON(`ppcv`.`properties_values_id` = `pvd`.`properties_values_id` AND `pvd`.`language_id` = '$this->languageId')
                    INNER JOIN `" . TABLE_PROPERTIES . "` AS `p` ON(`pv`.`properties_id` = `p`.`properties_id`)
                    INNER JOIN `" . TABLE_PROPERTIES_DESCRIPTION . "` AS `pd` ON(`pv`.`properties_id` = `pd`.`properties_id` AND `pd`.`language_id` = '$this->languageId')
                    LEFT JOIN `" . TABLE_SHIPPING_STATUS . "` AS `ss` ON(`ss`.`shipping_status_id` = `ppc`.`combi_shipping_status_id` AND `ss`.`language_id`='$this->languageId')
                    LEFT JOIN `" . TABLE_PRODUCTS_VPE . "` AS `vpe` ON (`vpe`.`products_vpe_id` = `ppc`.`products_vpe_id` AND `vpe`.`language_id` = '$this->languageId')
                    LEFT JOIN `" . TABLE_PRODUCTS_ATTRIBUTES . "` AS `pa` ON (`pa`.`products_id` = `ppc`.`products_id`)
                WHERE
                    `ppc`.`products_id` = '" . $product['products_id'] . "'"
                . ((!empty($optionsAsTextFieldIds))
                    ? " AND pa.options_id NOT IN (" . implode(
                        ',',
                        $optionsAsTextFieldIds
                    ) . ") "
                    : "")
                . "ORDER BY `ppc`.`products_properties_combis_id`, `pd`.`properties_id`
            ";

            $qResult = xtc_db_query($qry);

            $propertyList = array();
            while ($propertyCombi = xtc_db_fetch_array($qResult)) {
                // Group by products combination id
                if (empty($propertyList[$propertyCombi['products_properties_combis_id']])) {
                    $propertyList[$propertyCombi['products_properties_combis_id']] = array();
                }
                $propertyList[$propertyCombi['products_properties_combis_id']][] = $propertyCombi;
            }

            if (!empty($propertyList)) {
                // Export as attributes only
                $this->buildPropertiesAttributes($sg_prod_var_properties_combis, $product, $propertyList, $tax_rate);
                $sg_prod_var_properties_combis["has_options"] = 0;
            }
        }

        return $sg_prod_var_properties_combis;
    }

    /**
     * Read the whole input field data to an product from the database
     * and brig them into the structure as needed
     *
     * @param array $item
     * @param array $inputFieldsFromGxCustomizer
     *
     * @param bool  $asArray
     *
     * @return mixed
     */
    public function getInputFields($item, $inputFieldsFromGxCustomizer, $asArray = false)
    {
        $this->shopgateLogger->log("execute _getInputFields() ...", ShopgateLogger::LOGTYPE_DEBUG);
        // add imput fields if the product uses the customizer
        if (!empty($item['gm_gprint_surfaces_groups_id'])
            && !empty($inputFieldsFromGxCustomizer[$item['gm_gprint_surfaces_groups_id']])
        ) {
            return $inputFieldsFromGxCustomizer[$item['gm_gprint_surfaces_groups_id']];
        }
        $optionsAsTextFieldIds = $this->config->getExportOptionAsInputField();
        $productId             = $item["products_id"];

        $qry = "
            SELECT
                pa.products_attributes_id,
                po.products_options_id,
                pov.products_options_values_id,
                po.products_options_name,
                pov.products_options_values_name,
                pa.attributes_model,
                pa.options_values_price,
                pa.price_prefix,
                pa.options_values_weight,
                pa.attributes_stock,
                pa.weight_prefix
            FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa
            INNER JOIN " . TABLE_PRODUCTS_OPTIONS . " po ON pa.options_id = po.products_options_id
            INNER JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov ON (pa.options_values_id = pov.products_options_values_id AND pov.language_id = $this->languageId)
            WHERE pa.products_id = '$productId'
                AND (pov.products_options_values_name = 'TEXTFELD' "
            . ((!empty($optionsAsTextFieldIds))
                ? " OR pa.options_id IN (" . implode(
                    ',',
                    $optionsAsTextFieldIds
                ) . "))"
                : ")")
            . "ORDER BY po.products_options_id, pa.sortorder
        ";

        $query          = xtc_db_query($qry);
        $old            = -1;
        $i              = 0;
        $inputFieldsAll = array();

        while ($inputFields = xtc_db_fetch_array($query)) {
            if ($inputFields["products_options_id"] != $old) {
                $i++;
                $old = $inputFields["products_options_id"];
            }
            $inputFieldsAll[$i][] = $inputFields;
        }

        if (empty($inputFieldsAll)) {
            return $inputFieldsAll;
        }

        if ($asArray) {
            return $inputFieldsAll;
        }

        $sg_product_var = $this->buildInputFields($inputFieldsAll);

        return $sg_product_var;
    }

    /**
     * Generates the image local path and the url where to one product.
     *
     * @param array $product
     *
     * @param bool  $asArray
     *
     * @return array|string
     */
    public function getProductsImages($product, $asArray = false)
    {
        $this->shopgateLogger->log("execute _getProductImages() ...", ShopgateLogger::LOGTYPE_DEBUG);

        $qry = "
            SELECT *
            FROM " . TABLE_PRODUCTS_IMAGES . "
            WHERE products_id = '{$product['products_id']}'
            ORDER BY image_nr
        ";

        $images = array();

        if (!empty($product['products_image'])) {
            if (file_exists(DIR_FS_CATALOG . DIR_WS_ORIGINAL_IMAGES . $product['products_image'])) {
                $images[] = $this->getFilePath(urlencode($product['products_image']), DIR_WS_ORIGINAL_IMAGES);
            } elseif (file_exists(DIR_FS_CATALOG . DIR_WS_POPUP_IMAGES . $product['products_image'])) {
                $images[] = $this->getFilePath(urlencode($product['products_image']), DIR_WS_POPUP_IMAGES);
            } elseif (file_exists(DIR_FS_CATALOG . DIR_WS_INFO_IMAGES . $product['products_image'])) {
                $images[] = $this->getFilePath(urlencode($product['products_image']), DIR_WS_INFO_IMAGES);
            }
        }

        $query = xtc_db_query($qry);
        while ($image = xtc_db_fetch_array($query)) {
            if (file_exists(DIR_FS_CATALOG . DIR_WS_ORIGINAL_IMAGES . $image['image_name'])) {
                $images[] = $this->getFilePath(urlencode($image['image_name']), DIR_WS_ORIGINAL_IMAGES);
            } elseif (file_exists(DIR_FS_CATALOG . DIR_WS_POPUP_IMAGES . $image['image_name'])) {
                $images[] = $this->getFilePath(urlencode($image['image_name']), DIR_WS_POPUP_IMAGES);
            } elseif (file_exists(DIR_FS_CATALOG . DIR_WS_INFO_IMAGES . $image['image_name'])) {
                $images[] = $this->getFilePath(urlencode($image['image_name']), DIR_WS_INFO_IMAGES);
            }
        }

        if (!$asArray) {
            $images = implode("||", $images);
        }

        return $images;
    }

    /**
     * Returns an address starting with base directory
     * as defined by the DIR_WS_CATALOG constant
     *
     * @param string $fileName     - e.g. file.jpeg
     * @param string $subDirectory - e.g. 'product_images/original_images/'
     *
     * @return string - returns full address
     */
    public function getFilePath($fileName, $subDirectory = '')
    {
        if (empty($fileName)) {
            return '';
        }

        $httpServer = HTTP_SERVER;
        if (empty($httpServer)) {
            $httpServer = 'http://' . $_SERVER['HTTP_HOST'];
        }

        return $httpServer . DIR_WS_CATALOG . $subDirectory . ltrim($fileName, '/');
    }

    /**
     * Generate the deep link to a product using the shop systems functions
     *
     * @param int               $productsId
     * @param string            $productsName
     * @param bool              $gmSEOBoostProductsEnabled
     * @param GMSEOBoost_ORIGIN $gmSEOBoost
     *
     * @return string
     */
    public function generateDeepLink($productsId, $productsName, $gmSEOBoostProductsEnabled, $gmSEOBoost)
    {
        if ($gmSEOBoostProductsEnabled) {
            $deepLink = $gmSEOBoost->get_boosted_product_url($productsId, '', $this->languageId);
        } else {
            $deepLink = "product_info.php?" . xtc_product_link($productsId, $productsName);
        }
        $deepLink = $this->getFilePath($deepLink);

        return $deepLink;
    }

    /**
     * Check if a product is deactivated. If so add the information to the order infos
     *
     * @param array $item
     * @param array $orderInfo
     *
     * @return bool
     */
    public function isProductDeactivated($item, &$orderInfo)
    {
        if (!empty($item['attributes_products_id']) && !empty($item['properties_products_id'])) {
            // set a warning message and deactivate the product
            $orderInfo['plugin_warning_information'] = "The product [products_id={$item['products_id']}] will be 
            exported as deactivated, because it uses products attributes and products properties at the same time!";

            return true;
        }

        return false;
    }

    /**
     * Load all Categories of the product and build its category-path
     *
     * The categories are seperated by a =>. The Paths are seperated b< a double-pipe ||
     *
     * Example: kategorie_1=>kategorie_2||other_1=>other_2
     *
     * @param int $productId
     *
     * @return string
     */
    public function getProductPath($productId)
    {
        $this->shopgateLogger->log("execute _getProductPath() ...", ShopgateLogger::LOGTYPE_DEBUG);

        $catsQry   = "
            SELECT DISTINCT ptc.categories_id
            FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " ptc
            INNER JOIN " . TABLE_CATEGORIES . " c ON ptc.categories_id = c.categories_id
            WHERE ptc.products_id = '$productId'
              AND c.categories_status = 1
            ORDER BY products_sorting
        ";
        $catsQuery = xtc_db_query($catsQry);

        $categories = "";
        while ($category = xtc_db_fetch_array($catsQuery)) {
            $cats = xtc_get_category_path($category["categories_id"]);
            $cats = preg_replace("/\_/", ",", $cats);

            $q = "
                SELECT DISTINCT cd.categories_name
                FROM " . TABLE_CATEGORIES_DESCRIPTION . " cd
                WHERE cd.categories_id IN (" . $cats . ")
                    AND cd.language_id = " . $this->languageId . "
                ORDER BY find_in_set(cd.categories_id, '$cats')
            ";

            $q = xtc_db_query($q);

            $cats = "";
            while ($cd = xtc_db_fetch_array($q)) {
                if (!empty($cats)) {
                    $cats .= "=>";
                }
                $cats .= $cd["categories_name"];
            }
            if (!empty($categories)) {
                $categories .= "||";
            }
            $categories .= $cats;
        }

        return $categories;
    }

    /**
     * Returns the position of a product in a category, according to the the category's products_sorting
     *
     * @param int   $itemNumber
     * @param array $categoryData
     *
     * @return int
     */
    public function getProductPositionInCategory($itemNumber, $categoryData)
    {
        $orderBy1 = (empty($categoryData['products_sorting']))
            ? 'p.products_price'
            : $categoryData['products_sorting'];
        $orderBy2 = ($categoryData['products_sorting2'] == 'ASC')
            ? 'ASC'
            : 'DESC';
        $orderBy  = ' ORDER BY ' . $orderBy1 . ' ' . $orderBy2 . ', p.products_sort ASC, p.products_id ASC';

        $indexTable = ShopgateTools::getCategoryIndexTable();
        if (!$indexTable) {
            $productsQuery = xtc_db_query(
                "SELECT
            pc.products_id
            FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " pc,
            " . TABLE_PRODUCTS . " p,
            " . TABLE_PRODUCTS_DESCRIPTION . " pd
            WHERE categories_id='" . $categoryData['categories_id'] . "'
            AND p.products_id=pc.products_id
            AND p.products_id = pd.products_id
            AND pd.language_id = '" . $this->languageId . "'
            AND p.products_status=1
            " . $orderBy
            );
        } else {
            $productsQuery = xtc_db_query(
                "SELECT
            p.products_id
            FROM " . TABLE_PRODUCTS . " AS p
                LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " AS pd ON (pd.products_id = p.products_id)
                LEFT JOIN " . $indexTable . " AS ci ON (ci.products_id = p.products_id) 
            WHERE p.products_status=1
                AND pd.language_id = '" . $this->languageId . "'
                AND ci.categories_index LIKE '%-" . $categoryData['categories_id'] . "-%'
            " . $orderBy
            );
        }

        $i = 0;
        while (($product = xtc_db_fetch_array($productsQuery, true)) && ($product['products_id'] != $itemNumber)) {
            $i++;
        }

        return $i;
    }

    /**
     * Get all category uids to a product
     *
     * @param array $item
     * @param bool  $asArray
     *
     * @return array
     *
     * @throws ShopgateLibraryException
     */
    public function getCategoryNumbers($item, $asArray = false)
    {
        $categoryNumbers = $this->getProductCategories($item['products_id']);
        $inheritCats     = $this->getInheritingCategories($item['products_id']);
        $categories      = $categoryNumbers + $inheritCats;
        $numbers         = array();
        foreach ($categories as &$category) {
            $category['sortOrder'] = $this->getProductPositionInCategory($item['products_id'], $category);
            $numbers[]             = $category['categories_id'] . "=>" . $category['sortOrder'];
        }

        if ($asArray) {
            return $categories;
        }

        $maxCatDepth        = $this->config->getMaximumCategoryExportDepth();
        $categoryReducedMap = array();

        if (!empty($maxCatDepth)) {
            $categoryReducedMap = $this->getCategoryReducedMap($maxCatDepth);
        }

        // check if there is a category replacement map to reduce categories depth
        if (!empty($categoryReducedMap)) {
            foreach ($numbers as &$categoryNumber) {
                // can possibly contain a split symbol "=>"
                if (strpos($categoryNumber, '=>') !== false) {
                    $catNumberParts    = explode('=>', $categoryNumber);
                    $catNumberParts[0] = $categoryReducedMap[$catNumberParts[0]];
                    $categoryNumber    = implode('=>', $catNumberParts);
                } else {
                    $categoryNumber = $categoryReducedMap[$categoryNumber];
                }
            }
        }

        return $numbers;
    }

    /**
     * Read the category data to an product from the database
     *
     * @param int $itemNumber
     *
     * @return array
     */
    private function getProductCategories($itemNumber)
    {
        $this->shopgateLogger->log("execute _getProductCategoryNumbers() ...", ShopgateLogger::LOGTYPE_DEBUG);
        $category_numbers = array();

        $catsQry   = "
            SELECT DISTINCT
                ptc.categories_id,
                c.products_sorting,
                c.products_sorting2
            FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " ptc
            INNER JOIN " . TABLE_CATEGORIES . " c ON (ptc.categories_id = c.categories_id)
            WHERE ptc.products_id = '{$itemNumber}'
                AND c.categories_status = 1
            ";
        $catsQuery = xtc_db_query($catsQry);

        while ($category = xtc_db_fetch_array($catsQuery)) {
            if (empty($category["categories_id"])) {
                continue;
            }
            $category_numbers[$category["categories_id"]] = $category;
        }

        return $category_numbers;
    }

    /**
     * Grabs all categories that allow inheritance and
     * cross references them with current item's index
     *
     * @param int $itemNumber
     *
     * @return array
     */
    public function getInheritingCategories($itemNumber)
    {
        $index      = $this->getCategoryIndexForItem($itemNumber);
        $categories = $this->getAllInheritingCategories();
        $list       = array_intersect_key($categories, $index);

        return $list;
    }

    /**
     * Retrieves the category hierarchy for the current product
     * Raw table 'categories_index' format -0--2--5--7--9-
     * which does not necessarily mean the product is in all
     * of these categories.
     *
     * @param int $itemNumber - product ID to look up the index of
     *
     * @return array - e.g. array(0=>0, 2=>1, 5=>2, 7=>3, 9=>4)
     */
    private function getCategoryIndexForItem($itemNumber)
    {
        $index = array();
        $table = ShopgateTools::getCategoryIndexTable();
        if (!$table) {
            return $index;
        }

        $catsQry   = "
            SELECT categories_index
            FROM {$table} 
            WHERE products_id = '{$itemNumber}';";
        $catsQuery = xtc_db_query($catsQry);
        $return    = xtc_db_fetch_array($catsQuery);

        if (!isset($return['categories_index'])) {
            return $index;
        }
        $index = trim($return['categories_index'], '-');
        $index = explode('--', $index);
        $index = array_flip($index);

        return $index;
    }

    /**
     * Retrieves all categories that inherit products
     * from their sub-categories
     *
     * @return array - array(cat_id => array(category_data_row))
     */
    private function getAllInheritingCategories()
    {
        if (!is_null($this->inheritCategoryCache)) {
            return $this->inheritCategoryCache;
        }

        $catsQry   = "
            SELECT categories_id,
                products_sorting,
                products_sorting2
            FROM " . TABLE_CATEGORIES . "
            WHERE show_sub_products = 1;";
        $catsQuery = xtc_db_query($catsQry);

        while ($row = xtc_db_fetch_array($catsQuery)) {
            if (isset($row['categories_id'])) {
                $this->inheritCategoryCache[$row['categories_id']] = $row;
            }
        }

        if (empty($this->inheritCategoryCache)) {
            $this->inheritCategoryCache = array();
        }

        return $this->inheritCategoryCache;
    }

    /**
     * Generates the description to a product depending on the shop system settings
     *
     * @param array  $item
     * @param string $desc
     * @param string $shortDesc
     * @param bool   $exportProductsManagedFiles
     * @param bool   $exportDescriptionType
     *
     * @return mixed
     */
    public function getProductDescription($item, $desc, $shortDesc, $exportProductsManagedFiles, $exportDescriptionType)
    {
        $description = "";
        if ($exportProductsManagedFiles) {
            $this->shopgateLogger->log(
                "loading products content file links (enabled by config) ...",
                ShopgateLogger::LOGTYPE_DEBUG
            );
            $description       = '';
            $prefixDescription = ''; // TODO: FILL WITH DATA IF THERE IS ANY
            $sql               = "SELECT " .
                //                         "`content_id`, " .
                //                         "`products_id`, " .
                //                         "`group_ids, ".
                "`content_name`, " .
                "`content_file`, " .
                "`content_link`, " .
                //                         "`languages_id`, " .
                //                         "`content_read`, " .
                "`file_comment` " .
                "FROM " .
                "`" . TABLE_PRODUCTS_CONTENT . "` " .
                "WHERE " .
                "`products_id`='{$item['products_id']}' " .
                "AND " .
                "`languages_id`='{$this->languageId}'";
            $queryResult       = xtc_db_query($sql);
            while ($row = xtc_db_fetch_array($queryResult)) {
                $elementString = '';
                if (!empty($row['content_file'])) {
                    $contentFileParts = explode('.', $row['content_file']);
                    $fileExt          = $contentFileParts[count($contentFileParts) - 1];
                } else {
                    $fileExt = 'link';
                }

                // new path structure for GambioGX versions starting from 3.1.1.0
                if (!ShopgateTools::isGambioVersionLowerThan('3.1.1.0')) {
                    $iconImageUrl = rtrim(HTTP_SERVER . DIR_WS_CATALOG, '/')
                        . "/admin/html/assets/images/legacy/icons/icon_{$fileExt}.gif";
                } else {
                    $iconImageUrl =
                        rtrim(HTTP_SERVER . DIR_WS_CATALOG, '/') . "/admin/images/icons/icon_{$fileExt}.gif";
                }

                $documentLink = rtrim(HTTP_SERVER . DIR_WS_CATALOG, '/') .
                    (!empty($row['content_link'])
                        ? "/{$row['content_link']}?shopgate_redirect=1"
                        : "/media/products/{$row['content_file']}"
                    );

                $textStyle =
                    "vertical-align:top !important;padding-right:20px !important;font-size:11px 
                    !important;font-weight:700 !important;color:#999 !important;";
                $linkStyle
                           =
                    "vertical-align:top !important;color:#666 !important;font-size:12px 
                    !important;font-style:normal !important;font-weight:400 !important;
                    text-align:left;text-decoration:none !important;margin-bottom:1px;";

                $elementString     .= "<tr>";
                $elementString
                                   .= "<td width=\"1%\">" .
                    "<img src=\"{$iconImageUrl}\" alt=\"\" />&nbsp;" .
                    "</td>" .
                    "<td width=\"99%\">" .
                    "<span style=\"$linkStyle\">" .
                    "<a href=\"$documentLink\" target=\"_blank\" style=\"{$linkStyle}\">" .
                    "&nbsp;{$row['content_name']}" .
                    "</a>:&nbsp;" .
                    "</span>" .
                    "<span style=\"$textStyle\">{$row['file_comment']}</span>" .
                    "</td>";
                $elementString     .= "</td></tr><tr><td colspan=\"2\">" .
                    "<hr style=\"height:0px;padding:0;margin:0 0 4px 0;\"/></td></tr>";
                $prefixDescription .= $elementString;
            }

            if (!empty($prefixDescription)) {
                $prefixDescription = "<h4 style=\"color:#999;font-size:12px;margin-bottom:0;\">"
                    . SHOPGATE_PLUGIN_DESCRIPTION_DOCUMENTS_TEXT . "</h4>"
                    . "<table style=\"margin:0 !important;\">{$prefixDescription}</table>";
                $description       = $prefixDescription . '' . $description;
            }
        }
        switch ($exportDescriptionType) {
            case SHOPGATE_SETTING_EXPORT_DESCRIPTION:
                $description .= $desc;
                break;
            case SHOPGATE_SETTING_EXPORT_SHORTDESCRIPTION:
                $description .= $shortDesc;
                break;
            case SHOPGATE_SETTING_EXPORT_DESCRIPTION_SHORTDESCRIPTION:
                if (!empty($desc)) {
                    $description .= $desc . "<br/><br/>" . $shortDesc;
                } else {
                    $description .= $shortDesc;
                }
                break;
            case SHOPGATE_SETTING_EXPORT_SHORTDESCRIPTION_DESCRIPTION:
                if (!empty($shortDesc)) {
                    $description .= $shortDesc . "<br/><br/>" . $desc;
                } else {
                    $description .= $desc;
                }
                break;
        }

        return preg_replace("/\n|\r/", "", $this->parseDescription($description));
    }

    /**
     * Function to Parse Options like [TAB:xxxx] in the Description
     *
     * @param string $description
     *
     * @return mixed
     */
    private function parseDescription($description)
    {
        $tabs = array();
        preg_match_all("/\[TAB:[\w\s\d\&\;]*\]/", $description, $tabs);

        foreach ($tabs[0] as $replace) {
            $replacement = preg_replace("/(\[TAB:)|\]/", "", $replace);
            $replacement = "<h1>" . $replacement . "</h1>";

            $description = preg_replace("/" . preg_quote($replace) . "/", $replacement, $description);
        }

        return $description;
    }

    /**
     * Build the Product variations as options
     *
     * @param &array $sg_prod_var
     * @param array $variations
     * @param float $tax_rate
     */
    private function buildOptions(&$sg_prod_var, $variations, $tax_rate)
    {
        $tmp = array();
        $i   = 0;
        foreach ($variations as $_variation) {
            $i++;
            $tmp["option_$i"] =
                $_variation[0]["products_options_id"] . '=' . strip_tags($_variation[0]["products_options_name"]);

            $options = array();
            foreach ($_variation as $option) {
                // Currency and tax must be included here because the data is directly used for the item
                $optionOffsetPrice =
                    $option["options_values_price"] * $this->exchangeRate * (1 + ($tax_rate / 100)); // Include Tax
                $optionOffsetPrice = round($optionOffsetPrice * 100, 0); // get euro-cent

                $field = strip_tags($option["products_options_values_id"]) . "=" . strip_tags(
                        $option["products_options_values_name"]
                    );
                $field .= ($option["options_values_price"] != 0)
                    ? "=>" . $option["price_prefix"] . $optionOffsetPrice
                    : "";

                $options[] = $field;
            }
            $tmp["option_" . $i . "_values"] = implode("||", $options);
        }

        $sg_prod_var = $tmp;
    }

    /**
     * Build the product variations recursively
     *
     * @param array $sg_prod_var
     * @param array $variations
     * @param int   $index
     * @param array $baseVar
     */
    private function buildAttributes(&$sg_prod_var, $variations, $index = 0, $baseVar = array())
    {
        if ($index == 0) {
            // Index 0 sind die Überschriften. Diese müssen als erstes hinzugefügt werden
            for ($i = 0; $i < count($variations); $i++) {
                $sg_prod_var[0]['attribute_' . ($i + 1)] = $variations[$i][0]['products_options_name'];
            }
        }

        foreach ($variations[$index] as $variation) {
            $tmpNewVariation = array();

            // copy all prvious attributes (inclusive the order info)
            if (!empty($baseVar)) {
                for ($i = 1; $i <= 10; $i++) {
                    $keyName = 'attribute_' . $i;
                    if (array_key_exists($keyName, $baseVar)) {
                        $tmpNewVariation[$keyName]               = $baseVar[$keyName];
                        $tmpNewVariation['order_info'][$keyName] = $baseVar['order_info'][$keyName];
                    } else {
                        break;
                    }
                }
            }

            if (count($variations) == 1) {
                // only if 1 dimension
                $tmpNewVariation['item_number'] = $variation['attributes_model'];
            }

            if (!empty($baseVar['images'])) {
                $tmpNewVariation['images'] = $baseVar['images'];
            } else {
                $tmpNewVariation['images'] = array();
            }
            if (!empty($variation['gm_filename'])) {
                $tmpNewVariation['images'][] =
                    HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_IMAGES . '/product_images/attribute_images/'
                    . $variation['gm_filename'];
            }

            $tmpNewVariation['products_vpe_value'] = '';
            $tmpNewVariation['products_vpe_name']  = '';
            if (!empty($variation['products_vpe_value']) && !empty($variation['products_vpe_name'])) {
                $tmpNewVariation['products_vpe_value'] = $variation['products_vpe_value'];
                $tmpNewVariation['products_vpe_name']  = $variation['products_vpe_name'];
            }

            $tmpNewVariation['attribute_' . ($index + 1)]               = $variation['products_options_values_name'];
            $tmpNewVariation['order_info']['attribute_' . ($index + 1)] = array(
                $variation['products_attributes_id'] => array(
                    'options_id'        => $variation['products_options_id'],
                    'options_values_id' => $variation['products_options_values_id'],
                ),
            );

            $tmpNewVariation['stock_quantity'] = $variation['attributes_stock'];
            if (isset($baseVar['stock_quantity']) && $baseVar['stock_quantity'] < $variation['attributes_stock']) {
                $tmpNewVariation['stock_quantity'] = $baseVar['stock_quantity'];
            }

            // Kalkuliere den Preisunterschied (Steuern und Währung werden noch nicht hier berücksichtigt)
            $price = $variation['options_values_price'];
            if ($variation['price_prefix'] == '-') {
                $price = -1 * $price;
            }
            if (empty($baseVar['offset_amount'])) {
                $baseVar['offset_amount'] = 0;
            }
            $tmpNewVariation['offset_amount'] = $baseVar['offset_amount'] + $price;

            // Kalkuliere den Gewichtsunterschied
            $weight = (float)$variation['options_values_weight'];
            if ($variation['weight_prefix'] == '-') {
                $weight = -1 * $weight;
            }
            if (empty($baseVar['offset_weight'])) {
                $baseVar['offset_weight'] = 0;
            }
            $tmpNewVariation['offset_weight'] = $baseVar['offset_weight'] + (double)$weight;

            if ($index < (count($variations) - 1)) {
                // Fahre mit nächstem Attribute fort (mit aktuellem Zwischenattribut als Basis für die Gewicht, Stock und Preisberechnung)
                // Das aktuelle Zwischenattribut enthält das Gesamtgewicht, den Gesamtpreis und den max-Stock, der für weitere Berechnungen notwendig ist
                $this->buildAttributes($sg_prod_var, $variations, $index + 1, $tmpNewVariation);
            } else {
                // Wenn kein Attribut mehr existiert, dieses auf den Stack legen
                $sg_prod_var[] = $tmpNewVariation;
            }
        }
    }

    /**
     * Convert the input field data from the shop system into the Shopgate-specific structure
     *
     * @param array $inputFieldsAll
     *
     * @return array
     */
    private function buildInputFields($inputFieldsAll)
    {
        $sg_product_var = array();
        $i              = 0;
        foreach ($inputFieldsAll as $inputField) {
            $i++;
            //            $sg_product_var["has_input_fields"] = 1;
            $sg_product_var["input_field_" . $i . "_number"]     = $inputField[0]["products_options_id"];
            $sg_product_var["input_field_" . $i . "_type"]       = 'text';
            $sg_product_var["input_field_" . $i . "_label"]      = strip_tags($inputField[0]["products_options_name"]);
            $sg_product_var["input_field_" . $i . "_add_amount"] = ($inputField["options_values_price"] != 0)
                ? "=>" . $inputField["price_prefix"] . round($inputField["options_values_price"], 2)
                : "";
            // keine Angabe möglich
            $sg_product_var["input_field_" . $i . "_infotext"] = '';
            $sg_product_var["input_field_" . $i . "_required"] = 0;
        }

        return $sg_product_var;
    }

    /**
     * Build the product variations using the products properties
     *
     * @param array      &$sg_prod_var
     * @param array      $product      contains settings about how the properties are set up for the given product
     * @param array      $propertyList two-dimensional, grouped by products_properties_combi_id
     * @param int|double $tax_rate
     *
     * @return bool
     */
    private function buildPropertiesAttributes(&$sg_prod_var, $product, $propertyList, $tax_rate)
    {
        $sg_prod_var = array(0 => array());

        if (empty($product) || empty($propertyList)) {
            return false;
        }

        // Move through all combination plus an additional first row as title
        $propertyIndexToKeyMap = array_keys($propertyList);
        for ($i = 0; $i <= count($propertyList); $i++) {
            // Get actual property combi (make it start at index 1)
            if ($i > 0) {
                $properyCombiRow = $propertyList[$propertyIndexToKeyMap[$i - 1]];
            } else {
                // Take first combination row as data dummy, since the needed data is set to all combi lines
                $properyCombiRow = $propertyList[$propertyIndexToKeyMap[$i]];
            }

            // avoid the need to load the property combis later again
            $sg_prod_var[$i]['raw_variation_data'] = current($properyCombiRow);

            // Go through all available columns
            foreach ($properyCombiRow as $columnIndex => $combiColumn) {
                $attributeColumnIndex = $columnIndex + 1;

                // Create title line of not done yet
                if ($i == 0) {
                    $sg_prod_var[0]["attribute_$attributeColumnIndex"] = $combiColumn['properties_name'];
                } else {
                    $sg_prod_var[$i]["attribute_$attributeColumnIndex"] = $combiColumn['values_name'];

                    // Include price to name if activated for the product
                    if ($product['properties_show_price'] == 'true') { // "true" or "false"/empty as string
                        // Get price offset display prefix
                        /*if (isset($combiColumn['display_price_type'])) {
                            switch ($combiColumn['display_price_type']) {
                                case 'minus':
                                    $priceDisplayPrefix = '-';
                                    break;
                                case 'plus':
                                case 'fix':
                                default:
                                    $priceDisplayPrefix = '+';
                            }
                        }*/
                        // price offset display prefix type seems not to be used yet!
                        $priceDisplayPrefix = '+'; // reset to 1 to have no effect on this, yet

                        // Get price offset
                        $priceDisplayOffset = $combiColumn['display_price'];
                        if (empty($priceDisplayOffset)) {
                            $priceDisplayOffset = 0;
                        }

                        // Exchange rate must be used, tax rate is is ignored for the display
                        $price              =
                            round($priceDisplayOffset * $this->exchangeRate, $this->shopCurrency["decimal_places"]);
                        $priceDisplayOffset = number_format(
                            $price,
                            $this->shopCurrency["decimal_places"],
                            $this->shopCurrency["decimal_point"],
                            $this->shopCurrency["thousands_point"]
                        );

                        // Price is displayed exactly as set in the database
                        $sg_prod_var[$i]["attribute_$attributeColumnIndex"] .= " ($priceDisplayPrefix {$this->shopCurrency['symbol_left']}{$priceDisplayOffset} {$this->shopCurrency['symbol_right']})";
                    }
                }
            }

            // Check for row (first row is the title)
            if ($i == 0) {
                continue;
            }

            // All needed data is present in every each column, so just take the first
            $propertyCombinationData = $properyCombiRow[0];

            // Mark as "property attribute" to let the addOrder process know that it should search for properties instead of product options
            // Deprecated: Do not use the "order_info" field during item export anymore!
            $sg_prod_var[$i]['order_info']['is_property_attribute']         = '1';
            $sg_prod_var[$i]['order_info']['products_properties_combis_id'] =
                $propertyCombinationData['products_properties_combis_id'];

            // Save metadata about the current variation
            $sg_prod_var[$i]['variation_type'] = self::ITEM_TYPE_CHILD_PROPERTY_COMBINATION;

            // Use the model as item_number (public)
            $sg_prod_var[$i]['item_number'] = $propertyCombinationData['combi_model'];

            // Get price offset prefix
            /*switch ($propertyCombinationData['combi_price_type']) {
                case 'minus':
                    $pricePrefix = -1;
                    break;
                case 'plus':
                case 'fix':
                default:
                    $pricePrefix = 1;
            }*/
            // price prefix type seems not to be used yet!
            $pricePrefix = 1; // reset to 1 to have no effect on this, yet

            // Get price offset
            $priceOffset = $propertyCombinationData['combi_price'];
            if (empty($priceOffset)) {
                $priceOffset = 0;
            }

            // Include prefix to price offset
            $priceOffset *= $pricePrefix;

            // IMPORTANT NOTICE: EXCHANGE RATE IS NOT USED FOR PRODUCT PROPERTY COMBINATIONS; SEEMS TO BE A BUG!
            // Calculate to the actual currency
            //$priceOffset *= $this->exchangeRate;

            // SECOND IMPORTANT NOTICE: BY DEFAULT THE TAX RATE IS NEEDED HERE TO REMOVE TAXES IF SET FOR THE USER GROUP; THIS IS ALSO NOT WORKING (BUG)
            // SINCE THE SETTING FROM THE CUSTOMER GROUP IS IGNORED IN GAMBIO CHECKOUT
            // if($priceExcludingTaxSettingIsSetInCustomerGroup) {
            //     $priceOffset /= 1+$this->taxRate/100;
            // }

            // $priceOffset does not include tax on GambioGX Versions 2_0_13.3 or higher, but does on lower versions, so a consistent value needs to be used
            if ($this->ggxVersion['main_version'] > 2
                || $this->ggxVersion['main_version'] == 2
                && ($this->ggxVersion['sub_version'] > 0 or $this->ggxVersion['revision'] >= 13)
            ) {
                // Add up taxes since the value is later expected to have taxes included
                $priceOffset *= (1 + $tax_rate / 100);
            }

            // Use the calculated price offset
            $sg_prod_var[$i]['offset_amount']          = 0;
            $sg_prod_var[$i]['offset_amount_with_tax'] = $priceOffset;

            // Calculate offset weight
            switch ($product['use_properties_combis_weight']) {
                case '1': // replace weight
                    $sg_prod_var[$i]['offset_weight'] = '0';
                    $sg_prod_var[$i]['fixed_weight']  = $propertyCombinationData['combi_weight'];
                    break;
                case '0': // add/subtract weight
                default: // same as '0'
                    $sg_prod_var[$i]['offset_weight'] = $propertyCombinationData['combi_weight'];
                    break;
            }

            // Get image file if there is one
            if (!empty($propertyCombinationData['combi_image'])) {
                // Save as array even if it's only one image, so it can be merged to an existing image array
                $sg_prod_var[$i]['images'] = array(
                    HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_IMAGES . '/product_images/properties_combis_images/'
                    . $propertyCombinationData['combi_image'],
                );
            }

            // Calculate quantity
            switch ($product['use_properties_combis_quantity']) {
                case '1': // use parents quantity
                    $sg_prod_var[$i]['use_stock']                  = 1;
                    $sg_prod_var[$i]['use_parents_stock_quantity'] = 1;
                    break;
                case '3': // no check
                    $sg_prod_var[$i]['use_stock']                  = 0;
                    $sg_prod_var[$i]['use_parents_stock_quantity'] = 0;
                    break;
                case '0': // default value (use combination quantity)
                case '2': // use combination quantity
                default: // do same as '0'
                    $sg_prod_var[$i]['use_stock']                  = 1;
                    $sg_prod_var[$i]['use_parents_stock_quantity'] = 0;
                    break;
            }
            if (!empty($propertyCombinationData['combi_quantity'])) {
                $sg_prod_var[$i]['stock_quantity'] = $propertyCombinationData['combi_quantity'];
            } else {
                $sg_prod_var[$i]['stock_quantity'] = 0;
            }

            // Get shipping time (if set for product and a shipping time given for the combination)
            if (!empty($propertyCombinationData['shipping_status_name'])
                && !empty($product['use_properties_combis_shipping_time'])
            ) {
                $sg_prod_var[$i]['shipping_status_name'] = $propertyCombinationData['shipping_status_name'];
            }

            // Get VPE value and name
            if (!empty($propertyCombinationData['products_vpe_value'])
                && !empty($propertyCombinationData['products_vpe_name'])
            ) {
                $sg_prod_var[$i]['products_vpe_value'] = $propertyCombinationData['products_vpe_value'];
                $sg_prod_var[$i]['products_vpe_name']  = $propertyCombinationData['products_vpe_name'];
            }

            if (!empty($propertyCombinationData['shipping_status_name'])) {
                $sg_prod_var[$i]['shipping_status_name'] = $propertyCombinationData['shipping_status_name'];
            }

            if (!empty($propertyCombinationData['combi_ean'])) {
                $sg_prod_var[$i]['combi_ean'] = $propertyCombinationData['combi_ean'];
            }

            if (!empty($propertyCombinationData['combi_price_type'])) {
                $sg_prod_var[$i]['combi_price_type'] = $propertyCombinationData['combi_price_type'];
            }
        }

        return true;
    }

    /**
     * Read the maximum products uid from the database
     *
     * @return int
     */
    public function getMaxProductId()
    {
        $this->shopgateLogger->log("execute SQL get max_id ...", ShopgateLogger::LOGTYPE_DEBUG);

        $result = xtc_db_query("SELECT MAX(products_id) max_id FROM `" . TABLE_PRODUCTS . "`");
        $maxId  = xtc_db_fetch_array($result);

        return $maxId["max_id"];
    }

    /**
     * Read the minimum and maximum sort order values from the database
     *
     * @param int $maxOrder
     * @param int $minOrder
     * @param int $addToOrderIndex
     */
    public function getProductOrderLimits(&$maxOrder, &$minOrder, &$addToOrderIndex)
    {
        $this->shopgateLogger->log("execute SQL min_order, max_order ...", ShopgateLogger::LOGTYPE_DEBUG);

        // order_index for the products
        $result          = xtc_db_query(
            "SELECT MIN(products_sort) AS 'min_order', MAX(products_sort) AS 'max_order' FROM `" . TABLE_PRODUCTS . "`"
        );
        $orderIndices    = xtc_db_fetch_array($result);
        $maxOrder        = $orderIndices["max_order"] + 1;
        $minOrder        = $orderIndices["min_order"];
        $addToOrderIndex = 0;

        if ($minOrder < 0) {
            // make the sort_order positive
            $addToOrderIndex += abs($minOrder);
        }
    }

    /**
     * @param int   $limit
     * @param int   $offset
     * @param array $uids
     *
     * @return string
     */
    public function generateProductQuery($limit, $offset, $uids)
    {
        $this->shopgateLogger->log("generate SQL get products ...", ShopgateLogger::LOGTYPE_DEBUG);
        $gxCustomizerSets                   = $this->getGxCustomizerSets();
        $gxProductsPropertiesSupportEnabled = $this->isPropertySupportEnabled();
        // Group check for products
        // (also do not export any products thate are stored in blacklisted categories for the actual customer group)
        $categoriesBlacklist = $this->getCategoriesBlacklist(DEFAULT_CUSTOMERS_STATUS_ID);
        $qry
                             = "
            SELECT DISTINCT
                p.products_id,
                p.products_model,
                p.products_ean,
                p.products_quantity,
                p.products_image,
                p.products_price,
                DATE_FORMAT(p.products_last_modified, '%Y-%m-%d') as products_last_modified,
                p.products_weight,
                p.products_status,
                sp.specials_new_products_price,
                sp.specials_quantity,
                pdsc.products_keywords,
                pdsc.products_name,
                pdsc.products_description,
                pdsc.products_short_description,
                shst.shipping_status_name,
                mf.manufacturers_name,
                p.products_tax_class_id,
                p.products_fsk18,
                p.products_vpe_status,
                p.products_vpe_value,
                vpe.products_vpe_name,
                p.gm_price_status,
                p.products_sort,
                p.products_startpage,
                p.products_startpage_sort,
                p.products_discount_allowed,
                p.products_date_available,
                p.gm_show_weight,
                p.gm_show_qty_info,
                p.gm_min_order,
                p.gm_graduated_qty,
                p.products_discount_allowed,
                p.nc_ultra_shipping_costs,
                pa.products_id AS attributes_products_id" .
            ($gxProductsPropertiesSupportEnabled
                ? (",
                    ppc.products_id AS properties_products_id,
                    p.properties_dropdown_mode,
                    p.properties_show_price,
                    p.use_properties_combis_weight,
                    p.use_properties_combis_quantity,
                    p.use_properties_combis_shipping_time")
                : "") .
            (!empty($gxCustomizerSets)
                ? (",
                sgtp.gm_gprint_surfaces_groups_id")
                : "") .
            "
            FROM " . TABLE_PRODUCTS . " p
            LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION
            . " pdsc ON (p.products_id = pdsc.products_id AND pdsc.language_id = '" . $this->languageId . "')
            LEFT JOIN " . TABLE_SHIPPING_STATUS
            . " shst ON (p.products_shippingtime = shst.shipping_status_id AND shst.language_id = '" . $this->languageId
            . "')
            LEFT JOIN " . TABLE_MANUFACTURERS . " mf ON (mf.manufacturers_id = p.manufacturers_id)
            LEFT JOIN " . TABLE_SPECIALS . " sp ON (sp.products_id = p.products_id AND sp.status = 1 AND (sp.expires_date > now() OR sp.expires_date <= '1000-01-01 00:00:00' OR sp.expires_date IS NULL))
            LEFT JOIN " . TABLE_PRODUCTS_VPE . " vpe ON (vpe.products_vpe_id = p.products_vpe AND vpe.language_id = pdsc.language_id)

            LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa ON (pa.products_id=p.products_id)
            " .
            // Get the connected gx customizer set if available
            ((!empty($gxCustomizerSets))
                ? ("LEFT JOIN gm_gprint_surfaces_groups_to_products sgtp ON (sgtp.products_id=p.products_id)")
                : ("")
            ) .
            // Get products property combinations if available
            (($gxProductsPropertiesSupportEnabled)
                ? ("LEFT JOIN " . TABLE_PRODUCTS_PROPERTIES_COMBIS . " ppc ON (ppc.products_id=p.products_id)")
                : ("")
            ) .
            // Exclude products in blacklisted categories if set up to do so
            ((GROUP_CHECK == 'true' && !empty($categoriesBlacklist))
                ? ("LEFT JOIN " . TABLE_PRODUCTS_TO_CATEGORIES
                    . " ptc ON (ptc.products_id=p.products_id AND ptc.categories_id NOT IN (" . (implode(
                        ", ",
                        $categoriesBlacklist
                    )) . "))")
                : ("")
            ) .
            "WHERE p.products_status = 1
            " .
            // Exclude products for the actual price group if set up to do so
            ((GROUP_CHECK == 'true')
                ? ("AND p.group_permission_" . DEFAULT_CUSTOMERS_STATUS_ID . " = 1")
                : ("")
            );

        // Filter by products-attributes or products-properties-combis
        if ($this->config->getVariationType() == SHOPGATE_SETTING_VARIATION_TYPE_PROPERTY
            && $gxProductsPropertiesSupportEnabled
        ) {
            $qry
                .= "    AND pa.products_attributes_id IS NULL
            ";
        } elseif ($this->config->getVariationType() == SHOPGATE_SETTING_VARIATION_TYPE_ATTRIBUTE
            && $gxProductsPropertiesSupportEnabled
        ) {
            $qry
                .= "    AND ppc.products_properties_combis_id IS NULL
            ";
        } elseif ($gxProductsPropertiesSupportEnabled) {
            // export both variation types (products that used both variation types at the same time will be
            // deactivated and no child products are appended, because the functionality would become too complex)
            // -> the export would have to do a cross-product on every attribute-value-pair and
            // products-properties-combi and set a special dataset into the internal_order_info to recognize
            // the right combination of these selections
        }

        // the product dataset will have two extra fields: properties_products_id and attributes_products_id,
        // the id's are for each product as information about if the variant type is used by the product,
        // null/empty otherwise
        // sp.specials_quantity > 0 deleted, to handle the check in the loop
        // Code for enabling to download specific products (for debugging purposes only, at this time)
        if (!empty($uids) && is_array($uids)) {
            $qry .= " AND p.products_id IN ('" . implode("', '", $uids) . "') ";
        }

        // Fix for "Ahorn24" shop. 10 products were not found without sorting.
        $qry .= ' ORDER BY p.products_id ASC ';

        if (!empty($limit)) {
            $qry .= " LIMIT {$offset}, {$limit}";
        }

        return $qry;
    }

    /**
     * Check if properties are available in the current Gambio version
     *
     * @return bool
     */
    public function isPropertySupportEnabled()
    {
        // get article properties (only for gambio gx 2_0_7 or higher)
        if ($this->ggxVersion['main_version'] > 2
            || $this->ggxVersion['main_version'] == 2
            && ($this->ggxVersion['sub_version'] > 0
                || $this->ggxVersion['revision'] >= 7)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Checks for attribute VPE support.
     * According to a blog post on the Gambio blog, the attribute-vpe
     * and -ean is supported since GambioGX 1.0.14
     *
     * @return bool
     */
    public function isAttributeVpeSupported()
    {
        if ($this->ggxVersion['main_version'] > 1
            || $this->ggxVersion['main_version'] == 1
            && ($this->ggxVersion['sub_version'] > 0 or $this->ggxVersion['revision'] >= 14)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Reads all the available Gx-Customizer sets from the database
     *
     * @return array[][][]
     */
    public function getGxCustomizerSets()
    {
        $gxCustomizerSets = array();
        // Not available in all versions
        if (!$this->databaseHelper->tableExists('gm_gprint_surfaces_groups')) {
            return array();
        }

        $sql
                   = "
            SELECT
                `sg`.`name` AS `surfaces_groups_name`,
                `sg`.`gm_gprint_surfaces_groups_id` AS `surfaces_groups_id`,
                `s`.`gm_gprint_surfaces_id` AS `surfaces_id`,
                `s`.`width` AS `surfaces_width`,
                `s`.`height` AS `surfaces_height`,
                `sd`.`name` AS `surfaces_name`,
                `e`.`gm_gprint_elements_id` AS `elements_id`,
                `e`.`gm_gprint_elements_groups_id` AS `elements_groups_id`,
                `e`.`position_x` AS `elements_position_x`,
                `e`.`position_y` AS `elements_position_y`,
                `e`.`height` AS `elements_position_height`,
                `e`.`width` AS `elements_position_width`,
                `e`.`z_index` AS `elements_position_z_index`,
                `e`.`show_name` AS `elements_show_name`,
                `eg`.`group_type` AS `elements_group_type`,
                `eg`.`group_name` AS `elements_group_name`,
                `ev`.`gm_gprint_elements_values_id` AS `elements_values_id`,
                `ev`.`name` AS `elements_values_name`,
                `ev`.`elements_value` AS `elements_values_value`
            FROM `gm_gprint_surfaces_groups` AS `sg`
                INNER JOIN `gm_gprint_surfaces` AS `s` ON (`s`.`gm_gprint_surfaces_groups_id` = `sg`.`gm_gprint_surfaces_groups_id`)
                    INNER JOIN `gm_gprint_surfaces_description` AS `sd` ON (`sd`.`gm_gprint_surfaces_id` = `s`.`gm_gprint_surfaces_id`
                        AND `sd`.`languages_id` = '{$this->languageId}')
                    INNER JOIN `gm_gprint_elements` AS `e` ON (`e`.`gm_gprint_surfaces_id` = `s`.`gm_gprint_surfaces_id`)
                        INNER JOIN `gm_gprint_elements_groups` AS `eg` ON (`eg`.`gm_gprint_elements_groups_id` = `e`.`gm_gprint_elements_groups_id`)
                        INNER JOIN `gm_gprint_elements_values` AS `ev` ON (`ev`.`gm_gprint_elements_groups_id` = `e`.`gm_gprint_elements_groups_id`
                            AND `ev`.`languages_id` = '{$this->languageId}')
            WHERE 1";
        $sqlResult = xtc_db_query($sql);
        while ($row = xtc_db_fetch_array($sqlResult)) {
            // create a 3 dimensional structure:
            // n surface-groups (sets) possible
            if (empty($gxCustomizerSets[$row['surfaces_groups_id']])) {
                $gxCustomizerSets[$row['surfaces_groups_id']] = array();
            }
            // n surfaces per surface-group (set)
            if (empty($gxCustomizerSets[$row['surfaces_groups_id']][$row['surfaces_id']])) {
                $gxCustomizerSets[$row['surfaces_groups_id']][$row['surfaces_id']] = array();
            }
            // n elements per surface
            $gxCustomizerSets[$row['surfaces_groups_id']][$row['surfaces_id']][] = $row;
        }

        return $gxCustomizerSets;
    }

    /**
     * Checks all categories against the given customer group to find any
     * categories that should not be exported for the set um  customer group
     *
     * @param integer $customerGroupId
     *
     * @return array
     */
    public function getCategoriesBlacklist($customerGroupId)
    {
        $categoriesBlacklist = array();
        if (GROUP_CHECK == "true") {
            // Start at the root category and finish at the leaves
            $categoriesBlacklist = $this->getBlacklistedSubCategoriyIds(0, $customerGroupId);
        }

        return $categoriesBlacklist;
    }

    /**
     * Returns all sub categories including the given parent as a list that is a mapping from
     * one category to a higher category if a given depth is exceeded
     *
     * @param int $maxDepth
     * @param int $parentId
     * @param int $copyId
     * @param int $depth
     *
     * @throws ShopgateLibraryException
     * @return array
     */
    public function getCategoryReducedMap($maxDepth = null, $parentId = null, $copyId = null, $depth = null)
    {
        $this->shopgateLogger->log("execute _getCategoryReducementMap() ...", ShopgateLogger::LOGTYPE_DEBUG);

        $circularDepthStop = 50;
        if (empty($depth)) {
            $depth = 1;
        } elseif ($depth > $circularDepthStop) {
            // disallow circular category connections (detect by a maximum depth)
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR,
                'error on loading sub-categories: Categories-Depth exceedes a value of ' . $circularDepthStop
                . '. Check if there is a circular connection (referenced categories ids: ' . $parentId . ')', true
            );
        }

        $qry
            = "SELECT `categories_id` FROM `" . TABLE_CATEGORIES . "` WHERE" .
            // select by parent id, if set
            (!empty($parentId)
                ? " (`parent_id` = '{$parentId}')"
                : " (`parent_id` IS NULL OR `parent_id` = 0 OR `parent_id` = '')"
            );

        $qryResult = xtc_db_query($qry);
        if (!$qryResult) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR, 'error on selecting categories', true
            );
        }

        // add all sub categories to a simple one-dimensional array
        $categoryMap = array();
        while ($row = xtc_db_fetch_array($qryResult)) {
            // copy only if a maximum depth is set, yet
            if (!empty($maxDepth)) {
                if ($depth == $maxDepth) {
                    $copyId = $row['categories_id'];
                }
            }
            // Check if a mapping to a higher category needs to be applied
            if (!empty($copyId) && !empty($row['categories_id'])) {
                $categoryMap[$row['categories_id']] = $copyId;
            } else {
                // no mapping to other categories, map to itself!
                $categoryMap[$row['categories_id']] = $row['categories_id'];
            }

            $subCategories = $this->getCategoryReducedMap($maxDepth, $row['categories_id'], $copyId, $depth + 1);
            if (!empty($subCategories)) {
                $categoryMap = $categoryMap + $subCategories;
            }
        }

        return $categoryMap;
    }

    /**
     * Read the customer group uids from the database by the language uid
     */
    public function getCustomerGroupUIds()
    {
        $query    =
            "SELECT customers_status_id FROM `" . TABLE_CUSTOMERS_STATUS . "` WHERE language_id={$this->languageId}";
        $dbObject = xtc_db_query($query);
        $idList   = array();
        while ($row = xtc_db_fetch_array($dbObject)) {
            if (!empty($row['customers_status_id'])) {
                $idList[] = $row['customers_status_id'];
            }
        }

        return $idList;
    }

    /**
     * Use the shop systems xtcPrice_ORIGIN class to calculate the tier prices
     * by the customer group ids and the products uid
     *
     * @param string $currencyCode
     * @param int    $productsId
     * @param int    $minQuantity
     *
     * @return array
     */
    protected function calculateTierPriceData($currencyCode, $productsId, $minQuantity)
    {
        $tierPrices          = array();
        $customerGroupIdList = $this->getCustomerGroupUIds();

        if (empty($customerGroupIdList)) {
            return $tierPrices;
        }
        foreach ($customerGroupIdList as $id) {
            $xtPrice            = new xtcPrice_ORIGIN($currencyCode, $id);
            $graduatedPriceData = $xtPrice->xtcGetGraduatedPrice($productsId, $minQuantity);
            $tierPrices[$id]    = $graduatedPriceData;
        }

        return $tierPrices;
    }

    /**
     * Removes tags from strings
     *
     * @param string $itemName
     *
     * @return string
     */
    public function generateItemName($itemName)
    {
        return trim(preg_replace('/<[^>]+>/', ' ', $itemName));
    }

    /**
     * Returns all sub categories including the given parent as a list
     *
     * @param int    $parentId
     * @param string $customerGroupId
     * @param array  $excludeCategoryIdPath
     *
     * @return array
     * @throws \ShopgateLibraryException
     */
    private function getBlacklistedSubCategoriyIds($parentId, $customerGroupId = '', $excludeCategoryIdPath = array())
    {
        $subCategoryBlacklistIds = array($parentId);

        $qry = "SELECT `categories_id` FROM `" . TABLE_CATEGORIES . "` WHERE" .
            // use filter by customer group if given (get only the categories that are to be blacklisted)
            (!empty($customerGroupId)
                ? " (group_permission_{$customerGroupId} = 0) AND"
                : ''
            ) .
            // select by parent id
            (!empty($parentId)
                ? " (`parent_id` = '{$parentId}')"
                : " (`parent_id` IS NULL OR `parent_id` = 0 OR `parent_id` = '')"
            ) .
            // exclude all ids that have been checked already (to prevent circular traversing)
            ((!empty($excludeCategoryIds) && is_array($excludeCategoryIds))
                ? (" AND (`categories_id` IN (" . implode(', ', $excludeCategoryIds) . "))")
                : ''
            );

        $qryResult = xtc_db_query($qry);
        if (!$qryResult) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR, 'error on selecting categories', true
            );
        }
        // add all sub categories
        while ($row = xtc_db_fetch_array($qryResult)) {
            $subCategoryBlacklistIds = array_merge(
                $subCategoryBlacklistIds,
                $this->getBlacklistedSubCategoriyIds(
                    $row['categories_id'],
                    $customerGroupId,
                    array_merge($excludeCategoryIdPath, $subCategoryBlacklistIds)
                )
            );
        }

        return $subCategoryBlacklistIds;
    }

    /**
     * Instantiates an GMSEOBoost object and checks if seo boost is enbabled
     *
     * @param string $gmSEOBoost
     * @param bool   $gmSEOBoostProductsEnabled
     */
    public function setSeoBoost(&$gmSEOBoost, &$gmSEOBoostProductsEnabled)
    {
        if (class_exists("GMSEOBoost_ORIGIN")) {
            /** @var GMSEOBoost_ORIGIN $gmSEOBoost */
            $gmSEOBoost = new GMSEOBoost_ORIGIN();
        } else {
            /** @var GMSEOBoost $gmSEOBoost */
            $gmSEOBoost = new GMSEOBoost();
        }
        $gmSEOBoostProductsEnabled = false;
        if (file_exists(DIR_FS_CATALOG . '.htaccess') && function_exists('gm_get_conf')) {
            $gmSEOBoostProductsEnabled = gm_get_conf('GM_SEO_BOOST_PRODUCTS')
                ? 1
                : 0;
        }
    }

    /**
     * Calculates the weight to a product as needed for export
     *
     * @param array $product
     *
     * @return int
     */
    public function calculateWeight($product)
    {
        $weight = 0;
        if (!empty($product["products_weight"])) {
            $weight = $product["products_weight"];
        }

        return $weight;
    }

    /**
     * Calculates the VPE price (basic_price in csv)
     *
     * @param array  $product
     * @param string $price
     * @param array  $variation
     *
     * @return string
     */
    public function getProductVPE($product, $price, $variation = array())
    {
        $vpe         = "";
        $vpeValue    = '';
        $vpeName     = '';
        $priceHelper = $this->getHelper(ShopgateObject::HELPER_PRICING);

        if (!empty($variation["products_vpe_value"])) {
            $vpeValue = $variation["products_vpe_value"];
        } elseif (!empty($product["products_vpe_value"])) {
            $vpeValue = $product["products_vpe_value"];
        }

        if (!empty($variation["products_vpe_name"])) {
            $vpeName = $variation["products_vpe_name"];
        } elseif (!empty($product["products_vpe_name"])) {
            $vpeName = $product["products_vpe_name"];
        }

        if (empty($variation['order_info']['is_property_attribute'])) {
            foreach ($variation as $variationOption) {
                if (!is_array($variationOption)) {
                    continue;
                }

                if (!empty($variationOption["products_vpe_value"])) {
                    $vpeValue = $variationOption["products_vpe_value"];
                    $vpeName  = $variationOption["products_vpe_name"];
                }
            }
        }

        if (
            !empty($vpeValue)
            && !empty($vpeName)
            && $vpeValue != 0.0000
            && !empty($product["products_vpe_status"])
            && $product["products_vpe_status"] == 1
        ) {
            $factor = 1;
            switch (strtolower($vpeName)) {
                case "ml":
                case "mg":
                    // don't know why this logic was create
                    // it's used and there was no failure with it in the past
                    $factor = $vpeValue < 250
                        ? 100
                        : 1000;
                    break;
            }

            $_price = ($price / $vpeValue) * $factor;
            $vpe    = $this->shopCurrency["symbol_left"];

            $vpe .= $priceHelper->formatPriceNumber(
                $_price,
                $this->shopCurrency["decimal_places"],
                $this->shopCurrency["decimal_point"],
                $this->shopCurrency["thousands_point"]
            );

            $vpe .= " " . trim($this->shopCurrency["symbol_right"]);
            $vpe .= ' pro ' . (($factor == 1)
                    ? ''
                    : $factor . ' ');
            $vpe .= $vpeName;
        }

        return $vpe;
    }

    /**
     * Uses shop constants to check if the stock needs to be regarded
     *
     * @return int
     */
    public function useStock()
    {
        return (STOCK_ALLOW_CHECKOUT == 'true' || STOCK_CHECK != 'true')
            ? 0
            : 1;
    }

    /**
     * Check if a minimum order quantity is set to a product
     *
     * @param array $item
     *
     * @return float|string
     */
    public function getMinimumOrderQuantity($item)
    {
        return (empty($item['gm_min_order']) || ceil($item['gm_min_order']) <= 0)
            ? ''
            : ceil($item['gm_min_order']);
    }

    /**
     * Generates an available text based on the date available field
     *
     * @param array  $item
     *
     * @param string $defaultStatusName
     *
     * @return string
     */
    public function getAvailableText($item = array(), $defaultStatusName = '')
    {
        if (empty($item) || empty($item['shipping_status_name']) && empty($defaultStatusName)) {
            return '';
        }

        if (!empty($defaultStatusName)) {
            $availableText = (string)$defaultStatusName;
        } else {
            $availableText = (string)$item['shipping_status_name'];
        }

        // Check if the product is available in the future
        if (!empty($item['products_date_available'])) {
            // Check if the date is in the future
            $availableOnTimestamp = strtotime(
                substr($item['products_date_available'], 0, 10) . ' 00:00:00'
            ); // Take the date beginning at 00:00:00 o' clock
            // Set the "available on" text only if it is at least one day in the future
            if ($availableOnTimestamp - time() > 60 * 60 * 24) { // 60sec * 60min * 24h == count seconds in 1 day
                switch (strtolower($this->config->getLanguage())) {
                    case 'de':
                        $dateAvailableFormatted = date('d.m.Y', $availableOnTimestamp);
                        break;
                    case 'en':
                    default:
                        $dateAvailableFormatted = date('m/d/Y', $availableOnTimestamp);
                        break;
                }
                $availableText = str_replace(
                    '#DATE#',
                    $dateAvailableFormatted,
                    SHOPGATE_PLUGIN_FIELD_AVAILABLE_TEXT_AVAILABLE_ON_DATE
                );
                //$availableText .= ' - '.str_replace('#SHIPPINGTIME#', (string) $item['shipping_status_name'], SHOPGATE_PLUGIN_FIELD_AVAILABLE_TEXT_SHIPPING_DELAY);
            }
        }

        // return a default string as fallback
        return $availableText;
    }

    /**
     * Generate price status
     *
     * @param array $item
     * @param float $oldPrice
     *
     * @return array
     */
    public function generatePriceStatus($item, &$oldPrice)
    {
        if (!empty($this->priceStatus) && is_array($this->priceStatus)) {
            return $this->priceStatus;
        }

        $gmPriceStatusItemFields = array();
        switch ($item['gm_price_status']) {
            case 2: // "not available for purchase":
                $gmPriceStatusItemFields['use_stock']      = '1';
                $gmPriceStatusItemFields['stock_quantity'] = '0';
                $gmPriceStatusItemFields['active_status']  = 'active';
                $gmPriceStatusItemFields['available_text'] = SHOPGATE_PLUGIN_PRICE_STATUS_TEXT_NOT_AVAILABLE;
                $gmPriceStatusItemFields['basic_price']    = '0';
                break;
            case 1: // "price on request"
                if ($this->config->getExportPriceOnRequest() == SHOPGATE_SETTING_EXPORT_PRICE_ON_REQUEST_WITHOUT_PRICE
                ) {
                    $oldPrice                                   = '';
                    $gmPriceStatusItemFields['unit_amount']     = '0';
                    $gmPriceStatusItemFields['basic_price']     = '0';
                    $gmPriceStatusItemFields['available_text']  = SHOPGATE_PLUGIN_PRICE_STATUS_TEXT_NOT_AVAILABLE;
                    $gmPriceStatusItemFields['old_unit_amount'] = '';
                    $gmPriceStatusItemFields['use_stock']       = '1';
                    $gmPriceStatusItemFields['stock_quantity']  = '0';
                    $gmPriceStatusItemFields['active_status']   = 'active';
                } else {
                    // Use Product as is and export with price
                }
                break;
        }

        return $this->priceStatus = $gmPriceStatusItemFields;
    }

    /**
     * @param array $product
     *
     * @return string
     */
    public function buildCsvProperties(array $product)
    {
        return $this->propertyHelper->buildCsvProperties($product);
    }

    /**
     * Read all identifiers to an product from the database by the products uid
     *
     * @param int $uid
     *
     * @return array
     */
    public function getProductCodes($uid)
    {
        $codeData = array();

        if ($this->databaseHelper->checkTable("products_item_codes")) {
            $query = "SELECT code_isbn AS `isbn`, code_upc AS `upc` FROM " .
                TABLE_ITEM_CODES . " WHERE products_id={$uid}";

            $result = xtc_db_query($query);

            while ($row = xtc_db_fetch_array($result)) {
                $codeData[] = $row;
            }
        }

        return $codeData;
    }

    /**
     * Read the cross selling product uids from the database
     *
     * @param int  $products_id
     * @param bool $asArray
     *
     * @return string
     */
    public function getRelatedShopItems($products_id, $asArray = false)
    {
        $qry = "
            SELECT px.xsell_id
            FROM " . TABLE_PRODUCTS_XSELL . " px
            INNER JOIN " . TABLE_PRODUCTS . " p ON (px.products_id = p.products_id)
            WHERE p.products_id = '$products_id'
                AND (p.products_date_available < NOW() OR p.products_date_available IS NULL)
            ORDER BY px.sort_order
        ";

        $xSellIds = array();

        $query = xtc_db_query($qry);
        for ($i = 0; $i < xtc_db_num_rows($query); $i++) {
            $array      = xtc_db_fetch_array($query);
            $xSellIds[] = $array["xsell_id"];
        }

        return !$asArray
            ? implode("||", $xSellIds)
            : $xSellIds;
    }

    /**
     * Calculate the amount of all option combinations
     *
     * @param array $options
     *
     * @return int
     */
    protected function calculateVariationAmountByOptions($options)
    {
        $countVariations = 1;

        if (isset($options['has_options'])) {
            if ((int)$options['has_options'] == 0) {
                return $this->config->getMaxAttributes() - 2;
            } else {
                return $this->config->getMaxAttributes() + 2;
            }
        }

        foreach ($options as $option) {
            $countVariations *= count($option);
        }

        return $countVariations;
    }
}
