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
class ShopgateItemXmlModelTest extends \PHPUnit\Framework\TestCase
{
    /** @var ShopgateItemXmlModel|PHPUnit_Framework_MockObject_MockObject */
    protected $subjectUnderTest;

    /**
     * Initializing main test class as mock
     */
    public function setUp(): void
    {
        $this->subjectUnderTest = $this->getMockBuilder('ShopgateItemXmlModel')
            ->disableOriginalConstructor()
            ->setMethods(
                array(
                    'getInputFieldsToProduct',
                    'generateOptions',
                    'calculateOptionPrice',
                    'calculateTaxRate',
                )
            )->getMock();
    }

    /**
     * @param int   $expectedInputCount
     * @param array $inputs
     *
     * @covers       ShopgateItemXmlModel::setInputFields
     * @covers       ShopgateItemXmlModel::generateInputModelForGxInputField
     * @dataProvider inputFieldCountProvider
     */
    public function testSetInputFieldsCount($expectedInputCount, $inputs)
    {
        $this->subjectUnderTest->method('getInputFieldsToProduct')
            ->will($this->returnValue($inputs));

        $reflection = new ReflectionClass(get_class($this->subjectUnderTest));
        $method     = $reflection->getMethod('setInputFields');
        $method->setAccessible(true);

        $result = $method->invoke($this->subjectUnderTest);
        foreach ($result as $input) {
            $this->assertInstanceOf('Shopgate_Model_Catalog_Input', $input);
        }

        $this->assertCount($expectedInputCount, $result);
    }

    /**
     * @param array $sampleData
     * @param array $inputs
     *
     * @covers       ShopgateItemXmlModel::setInputFields
     * @covers       ShopgateItemXmlModel::generateInputModelForGxInputField
     * @dataProvider inputFieldContentProvider
     */
    public function testSetInputFieldsContent($sampleData, $inputs)
    {
        $this->subjectUnderTest->method('getInputFieldsToProduct')
            ->will($this->returnValue($inputs));

        $reflection = new ReflectionClass(get_class($this->subjectUnderTest));
        $method     = $reflection->getMethod('setInputFields');
        $method->setAccessible(true);

        $result = $method->invoke($this->subjectUnderTest);
        for ($i = 0; $i < count($result); $i++) {
            $originalInput = $sampleData[$i];
            /** @var Shopgate_Model_Catalog_Input $inputModel */
            $inputModel = $result[$i];
            $this->assertEquals($originalInput['input_field_' . ($i + 1) . '_number'], $inputModel->getUid());
            $this->assertEquals(
                $originalInput['input_field_' . ($i + 1) . '_add_amount'],
                $inputModel->getAdditionalPrice()
            );
            $this->assertEquals($originalInput['input_field_' . ($i + 1) . '_label'], $inputModel->getLabel());
        }
    }

    /**
     * @return array
     */
    public function inputFieldContentProvider()
    {
        $firstInput  = $this->generateInputFieldForNumber();
        $secondInput = $this->generateInputFieldForNumber(2);

        return array(
            '1st level single input'   => array(array($firstInput), $firstInput),
            '1st level multiple input' => array(
                array($firstInput, $secondInput),
                array_merge($firstInput, $secondInput),
            ),
            '2nd level single input'   => array(array($firstInput), array($firstInput)),
            '2nd level multiple input' => array(array($firstInput, $secondInput), array($firstInput, $secondInput)),
        );
    }

    /**
     * @return array
     */
    public function inputFieldCountProvider()
    {
        $singleInputs = $this->generateInputFieldForNumber();

        $multipleInputs = array_merge(
            $singleInputs,
            $this->generateInputFieldForNumber(2)
        );

        $secondLevelSingleInputs = array(
            $singleInputs,
        );

        $secondLevelMultipleInputs = array(
            $multipleInputs,
        );

        return array(
            '1st level single input'   => array(1, $singleInputs),
            '1st level multiple input' => array(2, $multipleInputs),
            '2nd level single input'   => array(1, $secondLevelSingleInputs),
            '2nd level multiple input' => array(2, $secondLevelMultipleInputs),
        );
    }

    /**
     * Generates requested sample input field
     *
     * @param int $number
     *
     * @return array
     */
    private function generateInputFieldForNumber($number = 1)
    {
        return array(
            'has_input_fields'                       => 1,
            'input_field_' . $number . '_type'       => 'text',
            'input_field_' . $number . '_number'     => 'gxcust_el_val_id_#' . $number,
            'input_field_' . $number . '_label'      => 'NAME_' . $number,
            'input_field_' . $number . '_infotext'   => 'INFO_' . $number,
            'input_field_' . $number . '_required'   => rand(0, 1),
            'input_field_' . $number . '_add_amount' => rand(0, 10),
        );
    }
}
