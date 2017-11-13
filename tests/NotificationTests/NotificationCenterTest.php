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

use Optimizely\Event\Builder\EventBuilder;
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

        // ensure that notifications length is zero
        $this->notificationCenterObj->clearAllNotifications();
        $this->assertSame(
            0,
            sizeof($this->notificationCenterObj->getNotifications()[$notificationType])
        );

        //  ===== should add, log and return notification ID when a plain function is passed as an argument =====
        $simple_method = function(){};
        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::INFO, "Callback added for notification type '{$notificationType}'.");
        $this->assertSame(
            1,
            $this->notificationCenterObj->addNotificationListener($notificationType, $simple_method)
        );
        // verify that notifications length has incremented by 1
        $this->assertSame(
            1,
            sizeof($this->notificationCenterObj->getNotifications()[$notificationType])
        );

        // ===== should add, log and return notification ID when an anonymous function is passed as an argument =====
        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::INFO, "Callback added for notification type '{$notificationType}'.");
        $this->assertSame(
            2,
            $this->notificationCenterObj->addNotificationListener($notificationType, function(){})
        );
         // verify that notifications length has incremented by 1
        $this->assertSame(
            2,
            sizeof($this->notificationCenterObj->getNotifications()[$notificationType])
        );

        // ===== should add, log and return notification ID when an object method is passed as an argument ===== 
        $eBuilder = new EventBuilder;
        $callbackInput = array($eBuilder, 'createImpressionEvent');

        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::INFO, "Callback added for notification type '{$notificationType}'.");
        $this->assertSame(
            3,
            $this->notificationCenterObj->addNotificationListener($notificationType, $callbackInput)
        );
         // verify that notifications length has incremented by 1
        $this->assertSame(
            3,
            sizeof($this->notificationCenterObj->getNotifications()[$notificationType])
        );
    } 

    public function testAddNotificationForMultipleNotificationTypes(){
        // ensure that notifications length is zero for each notification type
        $this->notificationCenterObj->clearAllNotifications();
        $this->assertSame(
            0,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::DECISON])
        );
        $this->assertSame(
            0,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::TRACK])
        );
        $this->assertSame(
            0,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::FEATURE_ACCESSED])
        );

        // ===== should add, log and return notification ID when a valid callback is added for each notification type =====
        
         $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::INFO, sprintf("Callback added for notification type '%s'.", NotificationType::DECISON));
        $this->assertSame(
            1,
            $this->notificationCenterObj->addNotificationListener(NotificationType::DECISON, function(){})
        );
         // verify that notifications length for NotificationType::DECISON has incremented by 1
        $this->assertSame(
            1,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::DECISON])
        );

        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::INFO, sprintf("Callback added for notification type '%s'.", NotificationType::TRACK));
        $this->assertSame(
            2,
            $this->notificationCenterObj->addNotificationListener(NotificationType::TRACK, function(){})
        );
         // verify that notifications length for NotificationType::TRACK has incremented by 1
        $this->assertSame(
            1,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::TRACK])
        );

        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::INFO, sprintf("Callback added for notification type '%s'.", NotificationType::FEATURE_ACCESSED));
        $this->assertSame(
            3,
            $this->notificationCenterObj->addNotificationListener(NotificationType::FEATURE_ACCESSED, function(){})
        );
         // verify that notifications length for NotificationType::FEATURE_ACCESSED has incremented by 1
        $this->assertSame(
            1,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::FEATURE_ACCESSED])
        );
    }

    public function testAddNotificationForMultipleCallbacksForANotificationType(){
        // ensure that notifications length is zero for notification type
        $this->notificationCenterObj->clearAllNotifications();
        $this->assertSame(
            0,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::DECISON])
        );

        // ===== should add, log and return notification ID when multiple valid callbacks 
        // are added for a single notification type =====  
         $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::INFO, sprintf("Callback added for notification type '%s'.", NotificationType::DECISON));
        $this->assertSame(
            1,
            $this->notificationCenterObj->addNotificationListener(NotificationType::DECISON, function(){})
        );
         // verify that notifications length for NotificationType::DECISON has incremented by 1
        $this->assertSame(
            1,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::DECISON])
        );

        $this->assertSame(
            2,
            $this->notificationCenterObj->addNotificationListener(NotificationType::DECISON, function(){
                echo "HelloWorld";
            })
        );
         // verify that notifications length for NotificationType::DECISON has incremented by 1
        $this->assertSame(
            2,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::DECISON])
        );

        $this->assertSame(
            3,
            $this->notificationCenterObj->addNotificationListener(NotificationType::DECISON, function(){
                $a = 1;
            })
        );
         // verify that notifications length for NotificationType::DECISON has incremented by 1
        $this->assertSame(
            3,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::DECISON])
        );
    }

    public function testAddNotificationThatAlreadyAddedCallbackIsNotReAdded(){
        // Note: anonymous methods sent with the same body will be re-added. 
        // Only variable and object methods can be checked for duplication
        
        $functionToSend = function(){};
        // ensure that notifications length is zero
        $this->notificationCenterObj->clearAllNotifications();
        $this->assertSame(
            0,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::DECISON])
        );

        ///////////////////////////////////////////////////////////////////////////
        // ===== verify that a variable method with same body isn't re-added ===== //
        ///////////////////////////////////////////////////////////////////////////
        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::INFO, sprintf("Callback added for notification type '%s'.", NotificationType::DECISON));
        // verify that notification ID 1 is returned
        $this->assertSame(
            1,
            $this->notificationCenterObj->addNotificationListener(NotificationType::DECISON, $functionToSend)
        );

        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::DEBUG, sprintf("Callback already added for notification type '%s'.", NotificationType::DECISON));

        // verify that -1 is returned when adding the same callback
        $this->assertSame(
            -1,
            $this->notificationCenterObj->addNotificationListener(NotificationType::DECISON, $functionToSend)
        );

        // verify that same method is added for a different notification type
        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::INFO, sprintf("Callback added for notification type '%s'.", NotificationType::TRACK));
        $this->assertSame(
            2,
            $this->notificationCenterObj->addNotificationListener(NotificationType::TRACK, $functionToSend)
        );
        
        /////////////////////////////////////////////////////////////////////////
        // ===== verify that an object method with same body isn't re-added ===== //
        /////////////////////////////////////////////////////////////////////////
        $eBuilder = new EventBuilder;
        $callbackInput = array($eBuilder, 'createImpressionEvent');

        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::INFO, sprintf("Callback added for notification type '%s'.", NotificationType::DECISON));
        $this->assertSame(
            3,
            $this->notificationCenterObj->addNotificationListener(NotificationType::DECISON, $callbackInput)
        );

        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::DEBUG, sprintf("Callback already added for notification type '%s'.", NotificationType::DECISON));
        // verify that -1 is returned when adding the same callback
        $this->assertSame(
            -1,
            $this->notificationCenterObj->addNotificationListener(NotificationType::DECISON, $callbackInput)
        );
        // verify that same method is added for a different notification type
        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::INFO, sprintf("Callback added for notification type '%s'.", NotificationType::TRACK));
        $this->assertSame(
            4,
            $this->notificationCenterObj->addNotificationListener(NotificationType::TRACK, $callbackInput)
        );
    }


    public function testRemoveNotification(){
        // ensure that notifications length is zero for each notification type
        $this->notificationCenterObj->clearAllNotifications();
        $this->assertSame(
            0,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::DECISON])
        );
        $this->assertSame(
            0,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::TRACK])
        );

        // add a callback for multiple notification types
        $this->assertSame(
            1,
            $this->notificationCenterObj->addNotificationListener(NotificationType::DECISON, function(){})
        );
         $this->assertSame(
            2,
            $this->notificationCenterObj->addNotificationListener(NotificationType::TRACK, function(){})
        );
        // add another callback for NotificationType::DECISON
        $this->assertSame(
            3,
            $this->notificationCenterObj->addNotificationListener(NotificationType::DECISON, function(){
            //doSomething
            })
        );

        // Verify that notifications length for NotificationType::DECISON is 2
        $this->assertSame(
            2,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::DECISON])
        );

        // Verify that notifications length for NotificationType::TRACK is 1
         $this->assertSame(
            1,
            sizeof($this->notificationCenterObj->getNotifications()[NotificationType::TRACK])
        );


        ///////////////////////////////////////////////////////////////////////////////
        // === Verify that no callback is removed for an invalid notification ID === //
        ///////////////////////////////////////////////////////////////////////////////
        $invalid_id = 4;
        $this->loggerMock->expects($this->at(0))
            ->method('log')
            ->with(Logger::DEBUG, sprintf("No Callback found with notification ID '%s'.",$invalid_id));
        $this->assertSame(
            false,
            $this->notificationCenterObj->removeNotificationListener($invalid_id)
        );

    }

}

