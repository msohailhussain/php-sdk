<?php
/**
 * Copyright 2016, Optimizely
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

namespace Optimizely\Entity;

class Attribute
{

    /**
     *
     */

    const BOOLEAN = 'boolean';
    const DOUBLE = 'double';
    const INTEGER = 'integer';
    const STRING = 'string';

    /**
     * @var string Attribute ID.
     */
    private $_id;

    /**
     * @var string Attribute key.
     */
    private $_key;


    public function __construct($id = null, $key = null)
    {
        $this->_id = $id;
        $this->_key = $key;
    }

    /**
     * @return boolean Representing whether attributes are valid or not.
     */
    public static function getConstants()
    {
        return array(self::BOOLEAN, self::DOUBLE, self::INTEGER, self::STRING);
    }

    /**
     * @return string Get attribute ID.
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @param $id string ID for attribute.
     */
    public function setId($id)
    {
        $this->_id = $id;
    }

    /**
     * @return string Get attribute key.
     */
    public function getKey()
    {
        return $this->_key;
    }

    /**
     * @param $key string Key for attribute.
     */
    public function setKey($key)
    {
        $this->_key = $key;
    }
}
