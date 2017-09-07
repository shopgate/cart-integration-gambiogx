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

date_default_timezone_set("Europe/Berlin");

// Change to a base directory to include all files from
$dir = realpath(dirname(__FILE__) . "/../");

// @chdir hack for warning: "open_basedir restriction in effect"
if (@chdir($dir) === false) {
    chdir($dir . '/');
}

// fix for bot-trap. Sometimes they block requests by mistake.
define("PRES_CLIENT_IP", @$_SERVER["SERVER_ADDR"]);

ini_set('session.use_trans_sid', false);
define("GZIP_COMPRESSION", "false");
error_reporting(E_ALL ^ E_NOTICE);
$_POST = array();

/** application_top.php must be included in this file because of errors on other gambio extensions */
include_once('includes/application_top.php');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    include_once __DIR__ . '/vendor/autoload.php';
}

include_once dirname(__FILE__) . '/plugin.php';
$ShopgateFramework = new ShopgatePluginGambioGX();
$ShopgateFramework->handleRequest($_REQUEST);
