<?php
/**
 * Copyright 2017, Optimizely Inc and Contributors
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
namespace Optimizely\Notification;

use Monolog\Logger;
use Exception;
use Optimizely\Logger\LoggerInterface;
use Optimizely\Logger\NoOpLogger;

class NotificationCenter
{
    private $_notificationId;

    private $_notifications;

    private $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->_notificationId = 1;
        $this->_notifications = [];
        foreach(array_values(NotificationType::getAll()) as $type){
            $this->_notifications[$type] = [];
        }

        $this->logger = $logger?: new NoOpLogger;
    }

    public function getNotificationId(){
        return $this->_notificationId;
    }

    public function getNotifications(){
        return $this->_notifications;
    }

    /**
     * [addNotificationListener description]
     * @param [type] $notification_type     [description]
     * @param [type] $notification_callback [description]
     */
    public function addNotificationListener($notification_type, $notification_callback)
    {
        if (!NotificationType::isNotificationTypeValid($notification_type)) {
            $this->logger->log(Logger::ERROR, "Invalid notification type.");
            return null;
        }

        if (!is_callable($notification_callback)) {
            $this->logger->log(Logger::ERROR, "Invalid notification callback.");
            return null;
        }

        foreach (array_values($this->_notifications[$notification_type]) as $callback) {
            if ($notification_callback == $callback) {
                $this->logger->log(Logger::DEBUG, "Callback already added for notification type '{$notification_type}'.");
                return -1;
            }
        }

        $this->_notifications[$notification_type][$this->_notificationId] = $notification_callback;
        $this->logger->log(Logger::INFO, "Callback added for notification type '{$notification_type}'.");
        $returnVal = $this->_notificationId++;
        return $returnVal;
    }

    /**
     * [removeNotificationListener description]
     * @param  [type] $notification_id [description]
     * @return [type]                  [description]
     */
    public function removeNotificationListener($notification_id)
    {
        foreach ($this->_notifications as $notification_type => $notifications) {
            foreach (array_keys($notifications) as $id) {
                if ($notification_id == $id) {
                    unset($this->_notifications[$notification_type][$id]);
                    $this->logger->log(Logger::INFO, "Callback with notification ID '{$notification_id}' has been removed.");
                    return true;
                }
            }
        }

        $this->logger->log(Logger::DEBUG, "No Callback found with notification ID '{$notification_id}'.");
        return false;
    }

    /**
     * [clearNotifications description]
     * @param  [type] $notification_type [description]
     * @return [type]                    [description]
     */
    public function clearNotifications($notification_type)
    {
        if (!NotificationType::isNotificationTypeValid($notification_type)) {
            $this->logger->log(Logger::ERROR, "Invalid notification type.");
            return null;
        }

        $this->_notifications[$notification_type] = [];
        $this->logger->log(Logger::INFO, "All callbacks for notification type '{$notification_type}' have been removed.");
    }

    /**
     * [clearAllNotifications description]
     * @return [type] [description]
     */
    public function clearAllNotifications()
    {
        foreach(array_values(NotificationType::getAll()) as $type){
            $this->_notifications[$type] = [];
        }
    }

    /**
     * [fireNotifications description]
     * @param  [type] $notification_type [description]
     * @param  array  $args              [description]
     * @return [type]                    [description]
     */
    public function fireNotifications($notification_type, array $args)
    {
        if (!isset($this->_notifications[$notification_type])) {
            return;
        }

        foreach (array_values($this->_notifications[$notification_type]) as $callback) {
            try {
                call_user_func_array($callback, $args);
            } catch (Exception $e) {
                $this->logger->log(Logger::ERROR, "Problem calling notify callback");
            }
        }
    }
}
