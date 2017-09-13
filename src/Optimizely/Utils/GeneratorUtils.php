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

namespace Optimizely\Utils;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;


class GeneratorUtils
{
	/**
     * Generate random uuid
     * @return string - version 4 uuid
     */
	public static function getRandomUuid()
	{
		try {
            // Generate a version 4 (random) UUID object
			$uuid4 = Uuid::uuid4();
                //echo $uuid4->toString() . "\n"; // i.e. 25769c6c-d34d-4bfe-ba98-e0ee856f3e7a
		}  catch (UnsatisfiedDependencyException $e) {
            // Some dependency was not met. Either the method cannot be called on a
            // 32-bit system, or it can, but it relies on Moontoast\Math to be present.
			echo 'Caught exception: ' . $e->getMessage() . "\n";
		}

		return $uuid4->toString();
	}

}