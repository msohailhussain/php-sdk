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

use Exception;
use Monolog\Logger;

use Optimizely\Notification\NotificationCenter;
use Optimizely\Notification\NotificationType;
use Optimizely\Logger\NoOpLogger;
use Optimizely\Logger\DefaultLogger;

class NotificationCenterTest extends \PHPUnit_Framework_TestCase
{
    private $notificationCenterObj;
    private $loggerMock;

    public function setUp()
    {
        $this->loggerMock = $this->getMockBuilder(NoOpLogger::class)
            ->setMethods(array('log'))
            ->getMock();
        $this->notificationCenterObj = new NotificationCenter($this->loggerMock);
    }

    public function testAddNotificationWithInvalidParams(){
        // should log and return null if invalid notification type given
        $invalid_type = "HelloWorld";

        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::ERROR, "Invalid notification type.");

        $this->assertSame(
            null,
            $this->notificationCenterObj->addNotificationListener($invalid_type, function(){})
        );

         // should log and return null if invalid callable given
         $invalid_callable = "HelloWorld";
         $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::ERROR, "Invalid notification callback.");

        $this->assertSame(
            null,
            $this->notificationCenterObj->addNotificationListener(NotificationType::DECISON, $invalid_callable)
        );
    }

    public function testAddNotificationWithValidTypeAndCallback(){
        $notificationType = NotificationType::DECISON;
        $notificationsLen = sizeof($this->notificationCenterObj->getNotifications()[$notificationType]);

        // should add, log and return notification ID when a plain function is passed as an argument
        $simple_method = function(){};
        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::INFO, "Callback added for notification type '{$notificationType}'.");
        $this->assertSame(
            1,
            $this->notificationCenterObj->addNotificationListener(NotificationType::DECISON, $simple_method)
        );
        $this->assertSame(
            ++$notificationsLen,
            sizeof($this->notificationCenterObj->getNotifications()[$notificationType])
        );

        // should add, log and return notification ID when a anonymous function is passed as an argument
        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::INFO, "Callback added for notification type '{$notificationType}'.");
        $this->assertSame(
            2,
            $this->notificationCenterObj->addNotificationListener(NotificationType::DECISON, function(){})
        );
        $this->assertSame(
            ++$notificationsLen,
            sizeof($this->notificationCenterObj->getNotifications()[$notificationType])
        );

        // should add, log and return notification ID when an object method is passed as an argument
        // TODO on Monday
    }    
}
