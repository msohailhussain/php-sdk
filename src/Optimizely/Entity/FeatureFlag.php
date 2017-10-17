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

namespace Optimizely\Entity;

class FeatureFlag{

	/**
	 * variable to hold feature flag ID
	 * @var String
	 */
	private $_id;

	/**
	 * variable to hold feature flag Key
	 * @var String
	 */
	private $_key;

	/**
	 * The ID of the rollout that is attached to this feature flag
	 * @var String
	 */
	private $_rolloutId;

	/**
	 * A list of the IDs of the experiments the feature flag is attached to. If there are multiple expeirments,
	 * they must be in the same mutually exclusive group.
	 * @var [String]
	 */
	private $_experimentIds;

	/**
	 * A list of the feature variables that are part of this feature
	 * @var [FeatureVariable]
	 */
	private $_variables;

	public function __construct($id = null,$key = null, $rolloutId = null, $experimentIds = null, $variables = null){
		$this->_id = $id;
		$this->_key = $key;
		$this->_rolloutId = $rolloutId;
		$this->_experimentIds = $experimentIds;
		$this->_variables = $variables;
	}

	/**
	 * @return String feature flag ID
	 */
	public function getId(){
		return $this->_id;
	}

	/**
	 * @return String feature flag key
	 */
	public function getKey(){
		return $this->_key;
	}

	/**
	 * @return String attached rollout ID
	 */
	public function getRolloutId(){
		return $this->_rolloutId;
	}

	/**
	 * @return [String] attached experiment IDs
	 */
	public function getExperimentIds(){
		return $this->_experimentIds;
	}

	/**
	 * @return [FeatureVariable] feature variables that are part of this feature
	 */
	public function getVariables(){
		return $this->_variables;
	}

}