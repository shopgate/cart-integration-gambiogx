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
class ShopgateCartHelperTest extends PHPUnit_Framework_TestCase
{
    /** @var ShopgateCartHelper */
    protected $subjectUnderTest;

    public function setUp()
    {
        $this->subjectUnderTest = new ShopgateCartHelper();
    }

    /**
     * @param array  $expected             - tuple of bools
     * @param string $combinationSettingId - combinationId, e.g. 0, 1, 2, 3
     * @param array  $globals              - global key => value settings
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @covers ::getStockReductionSettings
     * @dataProvider        stockReductionProvider
     */
    public function testGetStockReductionSettings($expected, $combinationSettingId, $globals)
    {
        //Global setup, this will be normally pulled from the table 'configuration'
        foreach ($globals as $globalKey => $value) {
            define($globalKey, $value);
        }

        $return = $this->subjectUnderTest->getStockReductionSettings($combinationSettingId);

        $this->assertEquals($expected, $return);
    }

    /**
     * Expected value is a tuple of whether to update
     * the quantity of ParentStock & PropertyStock
     *
     * @return array
     */
    public function stockReductionProvider()
    {
        return array(
            'id: 0, both globals true'            => array(
                'expected'       => array(false, true),
                'combination id' => '0',
                'globals'        => array(
                    'STOCK_CHECK'           => 'true',
                    'ATTRIBUTE_STOCK_CHECK' => 'true',
                ),
            ),
            'id: 0, main T, attr F'               => array(
                'expected'       => array(true, false),
                'combination id' => '0',
                'globals'        => array(
                    'STOCK_CHECK'           => 'true',
                    'ATTRIBUTE_STOCK_CHECK' => 'false',
                ),
            ),
            'id: 0, main F, attr T'               => array(
                'expected'       => array(false, false),
                'combination id' => '0',
                'globals'        => array(
                    'STOCK_CHECK'           => 'false',
                    'ATTRIBUTE_STOCK_CHECK' => 'true',
                ),
            ),
            'id: 0, both false'                   => array(
                'expected'       => array(false, false),
                'combination id' => '0',
                'globals'        => array(
                    'STOCK_CHECK'           => 'false',
                    'ATTRIBUTE_STOCK_CHECK' => 'false',
                ),
            ),
            'id: 1'                               => array(
                'expected'       => array(true, false),
                'combination id' => '1',
                'globals'        => array(
                    'STOCK_CHECK'           => 'false',
                    'ATTRIBUTE_STOCK_CHECK' => 'false',
                ),
            ),
            'id: 1 showing globals do not matter' => array(
                'expected'       => array(true, false),
                'combination id' => '1',
                'globals'        => array(
                    'STOCK_CHECK'           => 'true',
                    'ATTRIBUTE_STOCK_CHECK' => 'true',
                ),
            ),
            'id: 2'                               => array(
                'expected'       => array(false, true),
                'combination id' => '2',
                'globals'        => array(
                    'STOCK_CHECK'           => 'false',
                    'ATTRIBUTE_STOCK_CHECK' => 'false',
                ),
            ),
            'id: 3'                               => array(
                'expected'       => array(false, false),
                'combination id' => '3',
                'globals'        => array(
                    'STOCK_CHECK'           => 'false',
                    'ATTRIBUTE_STOCK_CHECK' => 'false',
                ),
            ),
        );
    }
}
