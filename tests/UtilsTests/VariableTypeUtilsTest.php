<?php
/**
 * Copyright 2017, Optimizely
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Optimizely\Tests;

use Monolog\Logger;
use Optimizely\ErrorHandler\NoOpErrorHandler;
use Optimizely\Logger\NoOpLogger;
use Optimizely\Utils\VariableTypeUtils;

class VariableTypeUtilsTest extends \PHPUnit_Framework_TestCase
{
    protected $loggerMock;
    protected $variableUtilObj;

    protected function setUp()
    {
        // Mock Logger
        $this->loggerMock = $this->getMockBuilder(NoOpLogger::class)
        ->setMethods(array('log'))
        ->getMock();

        $this->variableUtilObj = new VariableTypeUtils();
    }

    public function testValueCastingToBoolean(){
        $this->assertSame($this->variableUtilObj->castStringToType('true', 'boolean'), true);
        $this->assertSame($this->variableUtilObj->castStringToType('True', 'boolean'), true);
        $this->assertSame($this->variableUtilObj->castStringToType('false', 'boolean'), false);
        $this->assertSame($this->variableUtilObj->castStringToType('somestring', 'boolean'), false);
    }

    public function testValueCastingToInteger(){
       $this->assertSame($this->variableUtilObj->castStringToType('1000', 'integer'), 1000);
       $this->assertSame($this->variableUtilObj->castStringToType('123', 'integer'), 123);

       // should return nil and log a message if value can not be casted to an integer
       $value = 'any-non-numeric-string';
       $type = 'integer';     
       $this->loggerMock->expects($this->exactly(1))
            ->method('log')
            ->with(Logger::ERROR, 
            "Unable to cast variable value '{$value}' to type '{$type}'.");

       $this->assertSame($this->variableUtilObj->castStringToType($value, $type, $this->loggerMock), null);
   }

   public function testValueCastingToDouble(){
       $this->assertSame($this->variableUtilObj->castStringToType('1000', 'double'), 1000.0);
       $this->assertSame($this->variableUtilObj->castStringToType('3.0', 'double'), 3.0);
       $this->assertSame($this->variableUtilObj->castStringToType('13.37', 'double'), 13.37);

       // should return nil and log a message if value can not be casted to a double
       $value = 'any-non-numeric-string';
       $type = 'double';     
       $this->loggerMock->expects($this->exactly(1))
            ->method('log')
            ->with(Logger::ERROR, 
            "Unable to cast variable value '{$value}' to type '{$type}'.");

       $this->assertSame($this->variableUtilObj->castStringToType($value, $type, $this->loggerMock), null);
   }

   public function testValueCastingToString(){
       $this->assertSame($this->variableUtilObj->castStringToType('13.37', 'string'), '13.37');
       $this->assertSame($this->variableUtilObj->castStringToType('a string', 'string'), 'a string');
       $this->assertSame($this->variableUtilObj->castStringToType('3', 'string'), '3');
       $this->assertSame($this->variableUtilObj->castStringToType('false', 'string'), 'false');
   }
}
