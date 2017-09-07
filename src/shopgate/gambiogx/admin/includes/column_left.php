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

if (strpos(MODULE_PAYMENT_INSTALLED, 'shopgate.php') !== false) {

    // determine configuration language: $_GET > $_SESSION > global
    $sg_language_get = (!empty($_GET['sg_language'])
        ? '&sg_language=' . $_GET['sg_language']
        : ''
    );

    $displayCssClass = 'fav_drag_item';
    $surroundingHtml = array(
        'start' => '<div class="leftmenu_head" style="background-image:url(images/gm_icons/module.png)">' . BOX_SHOPGATE
            . '</div>' .
            '<div class="leftmenu_collapse leftmenu_collapse_opened"> </div>' .
            '<ul class="leftmenu_box" id="BOX_HEADING_SHOPGATE">',
        'end'   => '</ul>',
    );
    $surroundingTags = array(
        'start' => '<li class="leftmenu_body_item">',
        'end'   => '</li>',
    );
    $hrefIdList      = array(
        'basic'    => 'id="BOX_SHOPGATE_BASIC" ',
        'merchant' => 'id="BOX_SHOPGATE_MERCHANT" ',
    );
    $linkNamePrefix  = '';
    echo($surroundingHtml['start']);

    if (($_SESSION['customers_status']['customers_status_id'] == '0') && ($admin_access['shopgate'] == '1')) {
        echo $surroundingTags['start'] . '<a ' . $hrefIdList['basic'] . 'href="' . xtc_href_link(
                FILENAME_SHOPGATE . "?sg_option=info{$sg_language_get}",
                '',
                'NONSSL'
            )
            . '" class="' . $displayCssClass . '">' . $linkNamePrefix . BOX_SHOPGATE_INFO . '</a>'
            . $surroundingTags['end'];
        echo $surroundingTags['start'] . '<a ' . $hrefIdList['basic']
            . ' target="_blank" href="https://support.shopgate.com/hc/en-us/articles/202798386" class="'
            . $displayCssClass . '">' . $linkNamePrefix . BOX_SHOPGATE_HELP . '</a>' . $surroundingTags['end'];
        echo $surroundingTags['start'] . '<a ' . $hrefIdList['basic'] . 'href="' . xtc_href_link(
                FILENAME_SHOPGATE . "?sg_option=config{$sg_language_get}",
                '',
                'NONSSL'
            )
            . '" class="' . $displayCssClass . '">' . $linkNamePrefix . BOX_SHOPGATE_CONFIG . '</a>'
            . $surroundingTags['end'];
    }

    echo($surroundingHtml['end']);
}
