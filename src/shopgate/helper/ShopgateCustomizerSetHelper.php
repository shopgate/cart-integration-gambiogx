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
class ShopgateCustomizerSetHelper
{
    /**
     * @param array $gxCustomizerSurfaceElement
     * @param int   $inputFieldNumber
     * @param int   $inputCount
     *
     * @return array
     */
    public function generateInputField($gxCustomizerSurfaceElement, $inputFieldNumber, $inputCount)
    {
        $label      = '';
        $labelField = $inputCount > 1
            ? 'elements_values_name'
            : 'surfaces_name';

        // always precede the surfaces description name if not empty
        if (!empty($gxCustomizerSurfaceElement[$labelField])) {
            $textSpacing = '';
            if (!empty($label)) {
                $textSpacing = ' ';
            }
            // use uppercase for the surface name, since it is used as uppercase in the shop-frontend
            $label =
                strtoupper($gxCustomizerSurfaceElement[$labelField]) . $textSpacing . $label;
        }

        // take at least some name if none could be found, yet
        if (empty($label)) {
            $label = $gxCustomizerSurfaceElement['surfaces_groups_name'];
        }

        $infotext = !empty($gxCustomizerSurfaceElement['elements_values_value'])
            ? $gxCustomizerSurfaceElement['elements_values_value']
            : '';
        // remove line-feeds from the label and infotext elements
        $labelParts = explode("\n", str_replace("\r", '', $label));
        $label      = '';
        foreach ($labelParts as $labelPart) {
            $labelPart = trim($labelPart);
            if (strlen($labelPart) > 0) {
                $label .= " {$labelPart}";
            }
        }
        $infotextParts = explode("\n", str_replace("\r", '', $infotext));
        $infotext      = '';
        foreach ($infotextParts as $infotextPart) {
            $infotextPart = trim($infotextPart);
            if (strlen($infotextPart) > 0) {
                $infotext .= " {$infotextPart}";
            }
        }

        return array(
            "input_field_{$inputFieldNumber}_type"       => 'text',
            "input_field_{$inputFieldNumber}_number"     => 'gxcust_el_val_id_#' .
                $gxCustomizerSurfaceElement['surfaces_groups_id'] . '.' .
                $gxCustomizerSurfaceElement['surfaces_id'] . '.' .
                $gxCustomizerSurfaceElement['elements_values_id'],
            "input_field_{$inputFieldNumber}_label"      => $label,
            "input_field_{$inputFieldNumber}_infotext"   => $infotext,
            "input_field_{$inputFieldNumber}_required"   => 0,
            "input_field_{$inputFieldNumber}_add_amount" => 0,
        );
    }
}
