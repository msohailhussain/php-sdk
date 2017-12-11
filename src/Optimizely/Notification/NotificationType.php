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

use Optimizely\Entity\Experiment;
use Optimizely\Entity\Variation;
use Optimizely\Event\LogEvent;
use Optimizely\Utils\Validator;

class NotificationType
{
    // ACTIVATE:experiment, user_id, attributes, variation, event
    const ACTIVATE = "ACTIVATE";
    // TRACK:event_key, user_id, attributes, event_tags, event
    const TRACK = "TRACK";

    public static function isNotificationTypeValid($notification_type)
    {
        $oClass = new \ReflectionClass(__CLASS__);
        $notificationTypeList = array_values($oClass->getConstants());

        return in_array($notification_type, $notificationTypeList);
    }

    public static function getAll()
    {
        $oClass = new \ReflectionClass(__CLASS__);
        return $oClass->getConstants();
    }

    /**
     * Validates params for notification type ACTIVATE
     * @param  array  $args Params to execute callback for notification type ACTIVATE
     * @return boolean  
     */
    public static function validateACTIVATE(array $args = [])
    {
        // validate arguments length
        if(sizeof($args)!=5){
            return false;
        }

        // validate experiment
        if(!($args[0] instanceof Experiment)){
            return false;
        }

        // validate user ID
        if(gettype($args[1]) != 'string'){
            return false;
        }

        // validate attributes
        if(!is_null($args[2])){
            if(!Validator::areAttributesValid($args[2])){
                return false;
            }
        }

        // validate variation
        if(!($args[3] instanceof Variation)){
            return false;
        }

        // validate event
        if(!($args[4] instanceof LogEvent)){
            return false;
        }

        return true;
    }

     /**
     * Validates params for notification type TRACK
     * @param  array  $args Params to execute callback for notification type TRACK
     * @return boolean  
     */
     public static function validateTRACK(array $args = [])
    {
        // validate arguments length
        if(sizeof($args)!=5){
            return false;
        }

        // validate event key
        if(gettype($args[0]) != 'string'){
            return false;
        }

        //validate user ID
        if(gettype($args[1]) != 'string'){
            return false;
        }

        // validate attributes
        if(!Validator::areAttributesValid($args[2])){
            return false;
        }

        // validate event tags
        if(!Validator::areEventTagsValid($args[3])){
            return false;
        }

        // validate event
        if(!($args[4] instanceof LogEvent)){
            return false;
        }

        return true;
    }
}
