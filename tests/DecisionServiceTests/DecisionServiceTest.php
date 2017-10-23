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
use Optimizely\Bucketer;
use Optimizely\DecisionService\DecisionService;
use Optimizely\Entity\Experiment;
use Optimizely\Entity\Variation;
use Optimizely\ErrorHandler\NoOpErrorHandler;
use Optimizely\Logger\NoOpLogger;
use Optimizely\Logger\DefaultLogger;
use Optimizely\Optimizely;
use Optimizely\ProjectConfig;
use Optimizely\UserProfile\UserProfileServiceInterface;
use Optimizely\Utils\Validator;


class DecisionServiceTest extends \PHPUnit_Framework_TestCase
{
    private $bucketerMock;
    private $config;
    private $decisionService;
    private $decisionServiceMock;
    private $loggerMock;
    private $testUserId;
    private $userProvideServiceMock;

    public function setUp()
    {
        $this->testUserId = 'testUserId';
        $this->testUserIdWhitelisted = 'user1';
        $this->experimentKey = 'test_experiment';
        $this->testBucketingIdControl = 'testBucketingIdControl!';  // generates bucketing number 3741
        $this->testBucketingIdVariation = '123456789'; // generates bucketing number 4567
        $this->variationKeyControl = 'control';
        $this->variationKeyVariation = 'variation';
        $this->testUserAttributes = [
            'device_type' => 'iPhone',
            'company' => 'Optimizely',
            'location' => 'San Francisco'
        ];

        // Mock Logger
        $this->loggerMock = $this->getMockBuilder(NoOpLogger::class)
            ->setMethods(array('log'))
            ->getMock();

        $this->config = new ProjectConfig(DATAFILE, $this->loggerMock, new NoOpErrorHandler());

        // Mock bucketer
        $this->bucketerMock = $this->getMockBuilder(Bucketer::class)
            ->setConstructorArgs(array($this->loggerMock))
            ->setMethods(array('bucket'))
            ->getMock();

        // Mock user profile service implementation
        $this->userProvideServiceMock = $this->getMockBuilder(UserProfileServiceInterface::class)
            ->getMock();

       $this->decisionService = new DecisionService($this->loggerMock, $this->config); 

       $this->decisionServiceMock = $this->getMockBuilder(DecisionService::class)
            ->setConstructorArgs(array($this->loggerMock, $this->config))
            ->setMethods(array('getVariation'))
            ->getMock();
    }

    public function testGetVariationReturnsNullWhenExperimentIsNotRunning()
    {
        $this->bucketerMock->expects($this->never())
            ->method('bucket');

        $pausedExperiment = $this->config->getExperimentFromKey('paused_experiment');

        
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variation = $this->decisionService->getVariation($pausedExperiment, $this->testUserId);

        $this->assertNull($variation);
    }

    public function testGetVariationBucketsUserWhenExperimentIsRunning()
    {
        $expectedVariation = new Variation('7722370027', 'control');
        $this->bucketerMock->expects($this->once())
            ->method('bucket')
            ->willReturn($expectedVariation);

        $runningExperiment = $this->config->getExperimentFromKey('test_experiment');

        
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variation = $this->decisionService->getVariation($runningExperiment, $this->testUserId, $this->testUserAttributes);

        $this->assertEquals(
            $expectedVariation,
            $variation
        );
    }

    public function testGetVariationReturnsWhitelistedVariation()
    {
        $expectedVariation = new Variation('7722370027', 'control');
        $runningExperiment = $this->config->getExperimentFromKey('test_experiment');

        $callIndex = 0;
        $this->bucketerMock->expects($this->never())
            ->method('bucket');
         $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::DEBUG, 'User "user1" is not in the forced variation map.');              
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::INFO, 'User "user1" is forced in variation "control" of experiment "test_experiment".');

        
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variation = $this->decisionService->getVariation($runningExperiment, 'user1');

        $this->assertEquals(
            $expectedVariation,
            $variation
        );
    }

    public function testGetVariationReturnsWhitelistedVariationForGroupedExperiment()
    {
        $expectedVariation = new Variation('7722260071', 'group_exp_1_var_1',[
                [
                  "id" => "155563",
                  "value" => "groupie_1_v1"
                ]
          ]);
        $runningExperiment = $this->config->getExperimentFromKey('group_experiment_1');

        $callIndex = 0;
        $this->bucketerMock->expects($this->never())
            ->method('bucket');
         $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::DEBUG, 'User "user1" is not in the forced variation map.');                   
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::INFO, 'User "user1" is forced in variation "group_exp_1_var_1" of experiment "group_experiment_1".');

        
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variation = $this->decisionService->getVariation($runningExperiment, 'user1');

        $this->assertEquals(
            $expectedVariation,
            $variation
        );
    }

    public function testGetVariationBucketsWhenForcedVariationsIsEmpty()
    {
        $expectedVariation = new Variation('7722370027', 'control');
        $this->bucketerMock->expects($this->once())
            ->method('bucket')
            ->willReturn($expectedVariation);

        $runningExperiment = $this->config->getExperimentFromKey('test_experiment');

        // empty out the forcedVariations property
        $experiment = new \ReflectionProperty(Experiment::class, '_forcedVariations');
        $experiment->setAccessible(true);
        $experiment->setValue($runningExperiment, array());

        
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variation = $this->decisionService->getVariation($runningExperiment, 'user1', $this->testUserAttributes);

        $this->assertEquals(
            $expectedVariation,
            $variation
        );
    }

    public function testGetVariationBucketsWhenWhitelistedVariationIsInvalid()
    {
        $expectedVariation = new Variation('7722370027', 'control');
        $this->bucketerMock->expects($this->once())
            ->method('bucket')
            ->willReturn($expectedVariation);

        $runningExperiment = $this->config->getExperimentFromKey('test_experiment');

        // modify the forcedVariation to point to invalid variation
        $experiment = new \ReflectionProperty(Experiment::class, '_forcedVariations');
        $experiment->setAccessible(true);
        $experiment->setValue($runningExperiment, [
            'user_1' => 'invalid'
        ]);

        
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variation = $this->decisionService->getVariation($runningExperiment, 'user1', $this->testUserAttributes);

        $this->assertEquals(
            $expectedVariation,
            $variation
        );
    }

    public function testGetVariationBucketsUserWhenUserIsNotWhitelisted()
    {
        $expectedVariation = new Variation('7722370027', 'control');
        $this->bucketerMock->expects($this->once())
            ->method('bucket')
            ->willReturn($expectedVariation);

        $runningExperiment = $this->config->getExperimentFromKey('test_experiment');

        
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variation = $this->decisionService->getVariation($runningExperiment, 'not_whitelisted_user', $this->testUserAttributes);

        $this->assertEquals(
            $expectedVariation,
            $variation
        );
    }

    public function testGetVariationReturnsNullIfUserDoesNotMeetAudienceConditions()
    {
        $this->bucketerMock->expects($this->never())
            ->method('bucket');

        $runningExperiment = $this->config->getExperimentFromKey('test_experiment');

        
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variation = $this->decisionService->getVariation($runningExperiment, $this->testUserId); // no matching attributes

        $this->assertNull($variation);
    }

    public function testGetVariationReturnsStoredVariationIfAvailable()
    {
        $userId = 'not_whitelisted_user';
        $runningExperiment = $this->config->getExperimentFromKey('test_experiment');
        $expectedVariation = new Variation('7722370027', 'control');

        $callIndex = 0;
        $this->bucketerMock->expects($this->never())
            ->method('bucket');
         $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::DEBUG, 'User "not_whitelisted_user" is not in the forced variation map.');  
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::INFO, 'Returning previously activated variation "control" of experiment "test_experiment" for user "not_whitelisted_user" from user profile.');

        $storedUserProfile = array(
            'user_id' => $userId,
            'experiment_bucket_map' => array(
                '7716830082' => array(
                    'variation_id' => '7722370027'
                )
            )
        );
        $this->userProvideServiceMock->expects($this->once())
            ->method('lookup')
            ->willReturn($storedUserProfile);

        $this->decisionService = new DecisionService($this->loggerMock, $this->config, $this->userProvideServiceMock);
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variation = $this->decisionService->getVariation($runningExperiment, $userId);
        $this->assertEquals($expectedVariation, $variation);
    }

    public function testGetVariationBucketsIfNoStoredVariation()
    {
        $userId = $this->testUserId;
        $runningExperiment = $this->config->getExperimentFromKey('test_experiment');
        $expectedVariation = new Variation('7722370027', 'control');

        $callIndex = 0;
        $this->bucketerMock->expects($this->once())
            ->method('bucket')
            ->willReturn($expectedVariation);
         $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::DEBUG, sprintf('User "%s" is not in the forced variation map.', $userId));  
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::INFO, 'No previously activated variation of experiment "test_experiment" for user "testUserId" found in user profile.');
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::INFO, 'Saved variation "control" of experiment "test_experiment" for user "testUserId".');

        $storedUserProfile = array(
            'user_id' => $userId,
            'experiment_bucket_map' => array()
        );
        $this->userProvideServiceMock->expects($this->once())
            ->method('lookup')
            ->willReturn($storedUserProfile);

        $this->userProvideServiceMock->expects($this->once())
            ->method('save')
            ->with(array(
                'user_id' => $userId,
                'experiment_bucket_map' => array(
                    '7716830082' => array(
                        'variation_id' => '7722370027'
                    )
                )
            ));

        $this->decisionService = new DecisionService($this->loggerMock, $this->config, $this->userProvideServiceMock);
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variation = $this->decisionService->getVariation($runningExperiment, $userId, $this->testUserAttributes);
        $this->assertEquals($expectedVariation, $variation);
    }

    public function testGetVariationBucketsIfStoredVariationIsInvalid()
    {
        $userId = $this->testUserId;
        $runningExperiment = $this->config->getExperimentFromKey('test_experiment');
        $expectedVariation = new Variation('7722370027', 'control');

        $callIndex = 0;
        $this->bucketerMock->expects($this->once())
            ->method('bucket')
            ->willReturn($expectedVariation);
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::DEBUG, sprintf('User "%s" is not in the forced variation map.', $userId));  
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::INFO, 'User "testUserId" was previously bucketed into variation with ID "invalid" for experiment "test_experiment", but no matching variation was found for that user. We will re-bucket the user.');
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::INFO, 'Saved variation "control" of experiment "test_experiment" for user "testUserId".');

        $storedUserProfile = array(
            'user_id' => $userId,
            'experiment_bucket_map' => array(
                '7716830082' => array(
                    'variation_id' => 'invalid'
                )
            )
        );
        $this->userProvideServiceMock->expects($this->once())
            ->method('lookup')
            ->willReturn($storedUserProfile);

        $this->userProvideServiceMock->expects($this->once())
            ->method('save')
            ->with(array(
                'user_id' => $userId,
                'experiment_bucket_map' => array(
                    '7716830082' => array(
                        'variation_id' => '7722370027'
                    )
                )
            ));

        $this->decisionService = new DecisionService($this->loggerMock, $this->config, $this->userProvideServiceMock);
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variation = $this->decisionService->getVariation($runningExperiment, $userId, $this->testUserAttributes);
        $this->assertEquals($expectedVariation, $variation);
    }

    public function testGetVariationBucketsIfUserProfileServiceLookupThrows()
    {
        $userId = $this->testUserId;
        $runningExperiment = $this->config->getExperimentFromKey('test_experiment');
        $expectedVariation = new Variation('7722370027', 'control');

        $callIndex = 0;
        $this->bucketerMock->expects($this->once())
            ->method('bucket')
            ->willReturn($expectedVariation);
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::DEBUG, sprintf('User "%s" is not in the forced variation map.', $userId)); 
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::ERROR, 'The User Profile Service lookup method failed: I am error.');
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::INFO, 'Saved variation "control" of experiment "test_experiment" for user "testUserId".');

        $storedUserProfile = array(
            'user_id' => $userId,
            'experiment_bucket_map' => array(
                '7716830082' => array(
                    'variation_id' => 'invalid'
                )
            )
        );
        $this->userProvideServiceMock
            ->method('lookup')
            ->will($this->throwException(new Exception('I am error')));

        $this->userProvideServiceMock->expects($this->once())
            ->method('save')
            ->with(array(
                'user_id' => $userId,
                'experiment_bucket_map' => array(
                    '7716830082' => array(
                        'variation_id' => '7722370027'
                    )
                )
            ));

        $this->decisionService = new DecisionService($this->loggerMock, $this->config, $this->userProvideServiceMock);
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variation = $this->decisionService->getVariation($runningExperiment, $userId, $this->testUserAttributes);
        $this->assertEquals($expectedVariation, $variation);
    }

    public function testGetVariationBucketsIfUserProfileServiceSaveThrows()
    {
        $userId = $this->testUserId;
        $runningExperiment = $this->config->getExperimentFromKey('test_experiment');
        $expectedVariation = new Variation('7722370027', 'control');

        $callIndex = 0;
        $this->bucketerMock->expects($this->once())
            ->method('bucket')
            ->willReturn($expectedVariation);
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::DEBUG, sprintf('User "%s" is not in the forced variation map.', $userId)); 
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::INFO, 'No user profile found for user with ID "testUserId".');
        $this->loggerMock->expects($this->at($callIndex++))
            ->method('log')
            ->with(Logger::WARNING, 'Failed to save variation "control" of experiment "test_experiment" for user "testUserId".');

        $storedUserProfile = array(
            'user_id' => $userId,
            'experiment_bucket_map' => array(
                '7716830082' => array(
                    'variation_id' => 'invalid'
                )
            )
        );
        $this->userProvideServiceMock->expects($this->once())
            ->method('lookup')
            ->willReturn(null);

        $this->userProvideServiceMock
            ->method('save')
            ->with($this->throwException(new Exception()));

        $this->decisionService = new DecisionService($this->loggerMock, $this->config, $this->userProvideServiceMock);
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variation = $this->decisionService->getVariation($runningExperiment, $userId, $this->testUserAttributes);
        $this->assertEquals($expectedVariation, $variation);
    }

    public function testGetVariationUserWithSetForcedVariation()
    {
        $experimentKey = 'test_experiment';
        $pausedExperimentKey = 'paused_experiment';
        $userId = 'test_user';
        $bucketedVariationKey = 'control';

        $optlyObject = new Optimizely(DATAFILE, new ValidEventDispatcher(), $this->loggerMock);

        $userAttributes = [
            'device_type' => 'iPhone',
            'location' => 'San Francisco'
        ];

        $optlyObject->activate($experimentKey, $userId, $userAttributes);

        // confirm normal bucketing occurs before setting the forced variation
        $forcedVariationKey = $optlyObject->getVariation($experimentKey, $userId, $userAttributes);
        $this->assertEquals($bucketedVariationKey, $forcedVariationKey);

        // test valid experiment
        $this->assertTrue($optlyObject->setForcedVariation($experimentKey, $userId, $forcedVariationKey), sprintf('Set variation to "%s" failed.', $forcedVariationKey));
        $forcedVariationKey = $optlyObject->getVariation($experimentKey, $userId, $userAttributes);
        $this->assertEquals($forcedVariationKey, $forcedVariationKey);

        // clear forced variation and confirm that normal bucketing occurs
        $this->assertTrue($optlyObject->setForcedVariation($experimentKey, $userId, null), sprintf('Set variation to "%s" failed.', $forcedVariationKey));
        $forcedVariationKey = $optlyObject->getVariation($experimentKey, $userId, $userAttributes);
        $this->assertEquals($bucketedVariationKey, $forcedVariationKey);

        // check that a paused experiment returns null
        $this->assertTrue($optlyObject->setForcedVariation($pausedExperimentKey, $userId, 'variation'), sprintf('Set variation to "%s" failed.', $forcedVariationKey));
        $forcedVariationKey = $optlyObject->getVariation($pausedExperimentKey, $userId, $userAttributes);
        $this->assertNull($forcedVariationKey);
    }

    public function testGetVariationWithBucketingId()
    {
        $pausedExperimentKey = 'paused_experiment';
        $userId = 'test_user';

        $userAttributesWithBucketingId = [
            'device_type' => 'iPhone',
            'company' => 'Optimizely',
            'location' => 'San Francisco',
            RESERVED_ATTRIBUTE_KEY_BUCKETING_ID => $this->testBucketingIdVariation
        ];

        $invalidUserAttributesWithBucketingId = [
            'company' => 'Optimizely',
            RESERVED_ATTRIBUTE_KEY_BUCKETING_ID => $this->testBucketingIdControl
        ];

        $optlyObject = new Optimizely(DATAFILE, new ValidEventDispatcher(), $this->loggerMock);

        // confirm normal bucketing occurs before setting the bucketing ID
        $variationKey = $optlyObject->getVariation($this->experimentKey, $userId, $this->testUserAttributes);
        $this->assertEquals($this->variationKeyControl, $variationKey);

        // confirm valid bucketing with bucketing ID set in attributes
        $variationKey = $optlyObject->getVariation($this->experimentKey, $userId, $userAttributesWithBucketingId);
        $this->assertEquals($this->variationKeyVariation, $variationKey);

        // check invalid audience with bucketing ID
        $variationKey = $optlyObject->getVariation($this->experimentKey, $userId, $invalidUserAttributesWithBucketingId);
        $this->assertEquals(null, $variationKey);

        // check null audience with bucketing Id
        $variationKey = $optlyObject->getVariation($this->experimentKey, $userId, null);
        $this->assertEquals(null, $variationKey);

        // test that an experiment that's not running returns a null variation
        $variationKey = $optlyObject->getVariation($pausedExperimentKey, $userId, $userAttributesWithBucketingId);
        $this->assertEquals(null, $variationKey);

        // check forced variation
        $this->assertTrue($optlyObject->setForcedVariation($this->experimentKey, $userId, $this->variationKeyControl), sprintf('Set variation to "%s" failed.', $this->variationKeyControl));
        $variationKey = $optlyObject->getVariation($this->experimentKey, $userId, $userAttributesWithBucketingId);
        $this->assertEquals( $this->variationKeyControl, $variationKey);

        // check whitelisted variation
        $variationKey = $optlyObject->getVariation($this->experimentKey, $this->testUserIdWhitelisted, $userAttributesWithBucketingId);
        $this->assertEquals( $this->variationKeyControl, $variationKey);

        // check user profile
        $storedUserProfile = array(
            'user_id' => $userId,
            'experiment_bucket_map' => array(
                '7716830082' => array(
                    'variation_id' => '7722370027'
                )
            )
        );
        $this->userProvideServiceMock
            ->method('lookup')
            ->willReturn($storedUserProfile);

        $this->decisionService = new DecisionService($this->loggerMock, $this->config, $this->userProvideServiceMock);
        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $variationKey = $optlyObject->getVariation($this->experimentKey, $userId, $userAttributesWithBucketingId);
        $this->assertEquals($this->variationKeyControl, $variationKey, sprintf('Variation "%s" does not match expected user profile variation "%s".', $variationKey, $this->variationKeyControl));
    }
    
     //should return nil and log a message when the feature flag's experiment ids array is empty
    public function testGetVariationForFeatureExperimentGivenNullExperimentIds(){

        $feature_flag = $this->config->getFeatureFlagFromKey('empty_feature');
        $this->loggerMock->expects($this->at(0))
        ->method('log')
        ->with(Logger::DEBUG, "The feature flag 'empty_feature' is not used in any experiments.");

        $this->assertSame(
            $this->decisionService->getVariationForFeatureExperiment($feature_flag,'user1',[]),
            null
        );
    }

     //should return nil and log when the experiment is not in the datafile 
    public function testGetVariationForFeatureExperimentGivenExperimentNotInDataFile(){

        $boolean_feature = $this->config->getFeatureFlagFromKey('boolean_feature');
        $feature_flag = clone $boolean_feature;
        $feature_flag->setExperimentIds(["29039203"]);

        $this->loggerMock->expects($this->at(0))
        ->method('log')
        ->with(Logger::ERROR, 'Experiment ID "29039203" is not in datafile.');

        $this->loggerMock->expects($this->at(1))
        ->method('log')
        ->with(Logger::INFO, 
            "The user 'user1' is not bucketed into any of the experiments on the feature 'boolean_feature'.");

        $this->assertSame(
            $this->decisionService->getVariationForFeatureExperiment($feature_flag,'user1',[]),
            null
        );
    }

    //should return nil and log when the user is not bucketed into the feature flag's experiments
    public function testGetVariationForFeatureExperimentGivenNonMutexGroupAndUserNotBucketed(){
        $multivariate_experiment = $this->config->getExperimentFromKey('test_experiment_multivariate');
        $map = [ [$multivariate_experiment, 'user1', [], null] ];

        //make sure the user is not bucketed into the feature experiment
        $this->decisionServiceMock->expects($this->at(0))
        ->method('getVariation')
        ->will($this->returnValueMap($map));

        $this->loggerMock->expects($this->at(0))
        ->method('log')
        ->with(Logger::INFO, 
            "The user 'user1' is not bucketed into any of the experiments on the feature 'multi_variate_feature'.");
        $feature_flag = $this->config->getFeatureFlagFromKey('multi_variate_feature');
        $this->assertSame(
            $this->decisionServiceMock->getVariationForFeatureExperiment($feature_flag, 'user1', []),
            null);
    }

    //  should return the variation when the user is bucketed into a variation for the experiment on the feature flag
    public function testGetVariationForFeatureExperimentGivenNonMutexGroupAndUserIsBucketed(){
        // return the first variation of the `test_experiment_multivariate` experiment, which is attached to the `multi_variate_feature`
        $variation = $this->config->getVariationFromId('test_experiment_multivariate','122231');
        $this->decisionServiceMock->expects($this->at(0))
        ->method('getVariation')
        ->will($this->returnValue($variation));
        
        $feature_flag = $this->config->getFeatureFlagFromKey('multi_variate_feature');
        $expected_decision = [
            'experiment' => $this->config->getExperimentFromKey('test_experiment_multivariate'),
            'variation' => $this->config->getVariationFromId('test_experiment_multivariate','122231')
        ];

        $this->loggerMock->expects($this->at(0))
        ->method('log')
        ->with(Logger::INFO, 
            "The user 'user1' is bucketed into experiment 'test_experiment_multivariate' of feature 'multi_variate_feature'.");

        $this->assertEquals(
            $this->decisionServiceMock->getVariationForFeatureExperiment($feature_flag, 'user1', []),
            $expected_decision
        );

    }

    // should return the variation the user is bucketed into when the user is bucketed into one of the experiments
    public function testGetVariationForFeatureExperimentGivenMutexGroupAndUserIsBucketed(){
        $mutex_exp = $this->config->getExperimentFromKey('group_experiment_1');
        $variation = $mutex_exp->getVariations()[0];
        $this->decisionServiceMock->expects($this->at(0))
        ->method('getVariation')
        ->will($this->returnValue($variation));

        
        $mutex_exp = $this->config->getExperimentFromKey('group_experiment_1');
        $variation = $mutex_exp->getVariations()[0];
        $expected_decision = [
            'experiment' => $mutex_exp,
            'variation' => $variation
        ];
        $feature_flag = $this->config->getFeatureFlagFromKey('boolean_feature');
        $this->loggerMock->expects($this->at(0))
        ->method('log')
        ->with(Logger::INFO, 
            "The user 'user_1' is bucketed into experiment 'group_experiment_1' of feature 'boolean_feature'.");
        $this->assertEquals(
            $this->decisionServiceMock->getVariationForFeatureExperiment($feature_flag, 'user_1', []),
            $expected_decision
        );

    }

     // should return nil and log a message when the user is not bucketed into any of the mutex experiments      
    public function testGetVariationForFeatureExperimentGivenMutexGroupAndUserNotBucketed(){
        $mutex_exp = $this->config->getExperimentFromKey('group_experiment_1');
        $variation = $mutex_exp->getVariations()[0];
        $this->decisionServiceMock->expects($this->at(0))
        ->method('getVariation')
        ->will($this->returnValue(null));


        $mutex_exp = $this->config->getExperimentFromKey('group_experiment_1');
        $feature_flag = $this->config->getFeatureFlagFromKey('boolean_feature');
        $this->loggerMock->expects($this->at(0))
        ->method('log')
        ->with(Logger::INFO, 
            "The user 'user_1' is not bucketed into any of the experiments on the feature 'boolean_feature'.");
        $this->assertEquals(
            $this->decisionServiceMock->getVariationForFeatureExperiment($feature_flag, 'user_1', []),
            null
        );
    }

    //should return the bucketed experiment and variation
    public function testGetVariationForFeatureWhenTheUserIsBucketedIntoFeatureExperiment(){

       $this->decisionServiceMock = $this->getMockBuilder(DecisionService::class)
       ->setConstructorArgs(array($this->loggerMock, $this->config))
       ->setMethods(array('getVariationForFeatureExperiment'))
       ->getMock();

       $feature_flag = $this->config->getFeatureFlagFromKey('string_single_variable_feature');
       $expected_experiment_id = $feature_flag->getExperimentIds()[0];
       $expected_experiment = $this->config->getExperimentFromId($expected_experiment_id);
       $expected_variation = $expected_experiment->getVariations()[0];
       $expected_decision = [
        'experiment' => $expected_experiment,
        'variation' => $expected_variation
        ];

        $this->decisionServiceMock->expects($this->at(0))
        ->method('getVariationForFeatureExperiment')
        ->will($this->returnValue($expected_decision));

        $this->assertEquals(
            $this->decisionServiceMock->getVariationForFeature($feature_flag, 'user_1', []),
            $expected_decision
        );
    }

    // should return the bucketed variation and null experiment
    public function testGetVariationForFeatureWhenTheUserIsNotBucketedIntoFeatureExperimentAndBucketedToFeatureRollout(){

        $this->decisionServiceMock = $this->getMockBuilder(DecisionService::class)
        ->setConstructorArgs(array($this->loggerMock, $this->config))
        ->setMethods(array('getVariationForFeatureExperiment','getVariationForFeatureRollout'))
        ->getMock();

        $feature_flag = $this->config->getFeatureFlagFromKey('string_single_variable_feature');
        $rollout_id = $feature_flag->getRolloutId();
        $rollout = $this->config->getRolloutFromId($rollout_id);
        $experiment = $rollout->getExperiments()[0];
        $expected_variation = $experiment->getVariations()[0];
        $expected_decision = [
            'experiment' => null,
            'variation' => $expected_variation
        ];

        $this->decisionServiceMock
        ->method('getVariationForFeatureExperiment')
        ->will($this->returnValue(null));

        $this->decisionServiceMock
        ->method('getVariationForFeatureRollout')
        ->will($this->returnValue($expected_variation));

        $this->loggerMock->expects($this->at(0))
        ->method('log')
        ->with(Logger::INFO, 
            "User 'user_1' is in the rollout for feature flag 'string_single_variable_feature'.");

        $this->assertEquals(
            $this->decisionServiceMock->getVariationForFeature($feature_flag, 'user_1', []),
            $expected_decision
        );
    }

    public function testGetVariationForFeatureWhenTheUserIsNeitherBucketedIntoFeatureExperimentNorToFeatureRollout(){
        $this->decisionServiceMock = $this->getMockBuilder(DecisionService::class)
        ->setConstructorArgs(array($this->loggerMock, $this->config))
        ->setMethods(array('getVariationForFeatureExperiment','getVariationForFeatureRollout'))
        ->getMock();

        $feature_flag = $this->config->getFeatureFlagFromKey('string_single_variable_feature');

         $this->decisionServiceMock
        ->method('getVariationForFeatureExperiment')
        ->will($this->returnValue(null));

        $this->decisionServiceMock
        ->method('getVariationForFeatureRollout')
        ->will($this->returnValue(null));
        
        $this->loggerMock->expects($this->at(0))
        ->method('log')
        ->with(Logger::INFO, 
            "User 'user_1' is not in the rollout for feature flag 'string_single_variable_feature'.");

        $this->assertEquals(
            $this->decisionServiceMock->getVariationForFeature($feature_flag, 'user_1', []),
            null
        );
    }

    public function testGetVariationForFeatureRolloutWhenNoRolloutIsAssociatedToFeatureFlag(){
         $feature_flag = $this->config->getFeatureFlagFromKey('boolean_feature');

         $this->loggerMock->expects($this->at(0))
        ->method('log')
        ->with(Logger::DEBUG, 
            "Feature flag 'boolean_feature' is not part of a rollout.");

        $this->assertEquals(
            $this->decisionServiceMock->getVariationForFeatureRollout($feature_flag, 'user_1', []),
            null
        );
    }


    public function testGetVariationForFeatureRolloutWhenRolloutIsNotInDataFile(){
         $feature_flag = $this->config->getFeatureFlagFromKey('boolean_feature');
         $feature_flag = clone $feature_flag;
         $feature_flag->setRolloutId('invalid_rollout_id');

         $this->loggerMock->expects($this->at(0))
        ->method('log')
        ->with(Logger::ERROR, 
            'Rollout with ID "invalid_rollout_id" is not in the datafile.');

        $this->assertEquals(
            $this->decisionServiceMock->getVariationForFeatureRollout($feature_flag, 'user_1', []),
            null
        );
    }

    public function testGetVariationForFeatureRolloutWhenRolloutDoesNotHaveExperiment(){
        // Mock Project Config
        $configMock = $this->getMockBuilder(ProjectConfig::class)
            ->setConstructorArgs(array(DATAFILE, $this->loggerMock, new NoOpErrorHandler()))
            ->setMethods(array('getRolloutFromId'))
            ->getMock(); 

        $this->decisionService = new DecisionService($this->loggerMock, $configMock);
  
        $feature_flag = $this->config->getFeatureFlagFromKey('boolean_single_variable_feature');
        $rollout_id = $feature_flag->getRolloutId();
        $rollout = $this->config->getRolloutFromId($rollout_id);
        $experiment_less_rollout = clone $rollout;
        $experiment_less_rollout->setExperiments([]);

        $configMock
        ->method('getRolloutFromId')
        ->will($this->returnValue($experiment_less_rollout));

        $this->assertEquals(
            $this->decisionService->getVariationForFeatureRollout($feature_flag, 'user_1', []),
            null
        );
    }


    public function testGetVariationForFeatureRolloutWhenUserIsBucketedInTheTargetingRule(){

        $this->decisionService = new DecisionService(new DefaultLogger(Logger::DEBUG), $this->config);

        $feature_flag = $this->config->getFeatureFlagFromKey('boolean_single_variable_feature');
        $rollout_id = $feature_flag->getRolloutId();
        $rollout = $this->config->getRolloutFromId($rollout_id);
        $experiment = $rollout->getExperiments()[0];
        $expected_variation = $experiment->getVariations()[0];   

        $bucketer = new \ReflectionProperty(DecisionService::class, '_bucketer');
        $bucketer->setAccessible(true);
        $bucketer->setValue($this->decisionService, $this->bucketerMock);

        $map = [ [$experiment, 'user_1', $expected_variation] ];
         $this->bucketerMock
        ->method('bucket')
        ->will($this->returnValueMap($map));

        $this->loggerMock->expects($this->at(0))
        ->method('log')
        ->with(Logger::DEBUG, 
            "User 'user_1' meets conditions for targeting rule '1'.");

        $this->assertEquals(
            $this->decisionService->getVariationForFeatureRollout($feature_flag, 'user_1', ["browser_type" => "chrome"]),
            $expected_variation
        );
    }

}



// describe 'when the user qualifies for targeting rule' do
//       describe 'and the user is bucketed into the targeting rule' do
//         it 'should return the variation the user is bucketed into' do
//           feature_flag = config.feature_flag_key_map['boolean_single_variable_feature']
//           rollout_experiment = config.rollout_id_map[feature_flag['rolloutId']]['experiments'][0]
//           expected_variation = rollout_experiment['variations'][0]

//           allow(Optimizely::Audience).to receive(:user_in_experiment?).and_return(true)
//           allow(decision_service.bucketer).to receive(:bucket)
//                                         .with(rollout_experiment, user_id)
//                                         .and_return(expected_variation)
//           expect(decision_service.get_variation_for_feature_rollout(feature_flag, user_id, user_attributes)).to eq(expected_variation)

//           expect(spy_logger).to have_received(:log).once
//                           .with(Logger::DEBUG, "User 'user_1' meets conditions for targeting rule '1'.")
//         end
//       end