<?php

/*
 * This file is part of lxHive LRS - http://lxhive.org/
 *
 * Copyright (C) 2017 Brightcookie Pty Ltd
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with lxHive. If not, see <http://www.gnu.org/licenses/>.
 *
 * For authorship information, please view the AUTHORS
 * file that was distributed with this source code.
 */

namespace API\Storage\Adapter\Mongo;

use API\Controller;
use API\Storage\Query\StatementResult;
use API\Storage\Query\StatementInterface;
use API\Util;
use Ramsey\Uuid\Uuid;
use API\HttpException as Exception;
use API\Storage\Provider;
use API\Config;

class Statement extends Provider implements StatementInterface
{
    const COLLECTION_NAME = 'statements';
    /**
     * @param  $parameters parameters as per xAPI spec
     *
     * @return StatementResult object
     */
    public function get($parameters)
    {
        $storage = $this->getContainer()['storage'];
        $expression = $storage->createExpression();
        $queryOptions = [];

        $parameters = new Util\Collection($parameters);

        // Single statement
        if ($parameters->has('statementId')) {
            $expression->where('statement.id', $parameters->get('statementId'));
            $expression->where('voided', false);

            $this->validateStatementId($parameters['statementId']);

            $cursor = $storage->find(self::COLLECTION_NAME, $expression);

            $cursor = $this->validateCursorNotEmpty($cursor);
            
            $statementResult = new StatementResult();
            $statementResult->setCursor($cursor);
            $statementResult->setRemainingCount(1);
            $statementResult->setTotalCount(1);
            $statementResult->setHasMore(false);
            $statementResult->setSingleStatementRequest(true);

            return $statementResult;
        }

        if ($parameters->has('voidedStatementId')) {
            $expression->where('statement.id', $parameters->get('voidedStatementId'));
            $expression->where('voided', true);

            $this->validateStatementId($parameters['voidedStatementId']);

            $cursor = $storage->find(self::COLLECTION_NAME, $expression);

            $cursor = $this->validateCursorNotEmpty($cursor);

            $statementResult = new StatementResult();
            $statementResult->setCursor($cursor);
            $statementResult->setRemainingCount(1);
            $statementResult->setTotalCount(1);
            $statementResult->setHasMore(false);
            $statementResult->setSingleStatementRequest(true);

            return $statementResult;
        }

        // New StatementResult for non-single statement queries
        $statementResult = new StatementResult();

        $expression->where('voided', false);

        // Multiple statements
        if ($parameters->has('agent')) {
            $agent = $parameters->get('agent');
            $agent = json_decode($agent, true);

            $uniqueIdentifier = Util\xAPI::extractUniqueIdentifier($agent);

            if ($parameters->has('related_agents') && $parameters->get('related_agents') === 'true') {
                if ($uniqueIdentifier === 'account') {
                    $expression->whereAnd(
                        $expression->expression()->whereOr(
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.actor.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.actor.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.object.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.object.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.authority.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.authority.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.context.team.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.context.team.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.context.instructor.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.context.instructor.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.object.objectType', 'SubStatement'),
                                $expression->expression()->where('statement.object.object.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.object.object.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.actor.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.actor.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.object.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.object.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.authority.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.authority.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.context.team.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.context.team.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.context.instructor.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.context.instructor.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.object.objectType', 'SubStatement'),
                                $expression->expression()->where('references.object.object.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.object.object.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            )
                        )
                    );
                } else {
                    $expression->whereAnd(
                        $expression->expression()->whereOr(
                            $expression->expression()->where('statement.actor.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('statement.object.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('statement.authority.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('statement.context.team.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('statement.context.instructor.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.object.objectType', 'SubStatement'),
                                $expression->expression()->where('statement.object.object.'.$uniqueIdentifier, $agent[$uniqueIdentifier])
                            ),
                            $expression->expression()->where('references.actor.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('references.object.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('references.authority.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('references.context.team.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('references.context.instructor.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.object.objectType', 'SubStatement'),
                                $expression->expression()->where('references.object.object.'.$uniqueIdentifier, $agent[$uniqueIdentifier])
                            )
                        )
                    );
                }
            } else {
                if ($uniqueIdentifier === 'account') {
                    $expression->whereAnd(
                        $expression->expression()->whereOr(
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.actor.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.actor.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('statement.object.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('statement.object.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.actor.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.actor.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            ),
                            $expression->expression()->whereAnd(
                                $expression->expression()->where('references.object.'.$uniqueIdentifier.'.homePage', $agent[$uniqueIdentifier]['homePage']),
                                $expression->expression()->where('references.object.'.$uniqueIdentifier.'.name', $agent[$uniqueIdentifier]['name'])
                            )
                        )
                    );
                } else {
                    $expression->whereAnd(
                        $expression->expression()->whereOr(
                            $expression->expression()->where('statement.actor.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('statement.object.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('references.actor.'.$uniqueIdentifier, $agent[$uniqueIdentifier]),
                            $expression->expression()->where('references.object.'.$uniqueIdentifier, $agent[$uniqueIdentifier])
                        )
                    );
                }
            }
        }

        if ($parameters->has('verb')) {
            $expression->whereAnd(
                $expression->expression()->whereOr(
                    $expression->expression()->where('statement.verb.id', $parameters->get('verb')),
                    $expression->expression()->where('references.verb.id', $parameters->get('verb'))
                )
            );
        }

        if ($parameters->has('activity')) {
            // Handle related
            if ($parameters->has('related_activities') && $parameters->get('related_activities') === 'true') {
                $expression->whereAnd(
                    $expression->expression()->whereOr(
                        $expression->expression()->where('statement.object.id', $parameters->get('activity')),
                        $expression->expression()->where('statement.context.contextActivities.parent.id', $parameters->get('activity')),
                        $expression->expression()->where('statement.context.contextActivities.category.id', $parameters->get('activity')),
                        $expression->expression()->where('statement.context.contextActivities.grouping.id', $parameters->get('activity')),
                        $expression->expression()->where('statement.context.contextActivities.other.id', $parameters->get('activity')),
                        $expression->expression()->where('statement.context.contextActivities.parent.id', $parameters->get('activity')),
                        $expression->expression()->where('statement.context.contextActivities.parent.id', $parameters->get('activity')),
                        $expression->expression()->whereAnd(
                            $expression->expression()->where('statement.object.objectType', 'SubStatement'),
                            $expression->expression()->where('statement.object.object', $parameters->get('activity'))
                        ),
                        $expression->expression()->where('references.object.id', $parameters->get('activity')),
                        $expression->expression()->where('references.context.contextActivities.parent.id', $parameters->get('activity')),
                        $expression->expression()->where('references.context.contextActivities.category.id', $parameters->get('activity')),
                        $expression->expression()->where('references.context.contextActivities.grouping.id', $parameters->get('activity')),
                        $expression->expression()->where('references.context.contextActivities.other.id', $parameters->get('activity')),
                        $expression->expression()->where('references.context.contextActivities.parent.id', $parameters->get('activity')),
                        $expression->expression()->where('references.context.contextActivities.parent.id', $parameters->get('activity')),
                        $expression->expression()->whereAnd(
                            $expression->expression()->where('references.object.objectType', 'SubStatement'),
                            $expression->expression()->where('references.object.object', $parameters->get('activity'))
                        )
                    )
                );
            } else {
                $expression->whereAnd(
                    $expression->expression()->whereOr(
                        $expression->expression()->where('statement.object.id', $parameters->get('activity')),
                        $expression->expression()->where('references.object.id', $parameters->get('activity'))
                    )
                );
            }
        }

        if ($parameters->has('registration')) {
            $expression->whereAnd(
                $expression->expression()->whereOr(
                    $expression->expression()->where('statement.context.registration', $parameters->get('registration')),
                    $expression->expression()->where('references.context.registration', $parameters->get('registration'))
                )
            );
        }

        // Date based filters
        if ($parameters->has('since')) {
            $since = Util\Date::dateStringToMongoDate($parameters->get('since'));
            $expression->whereGreaterOrEqual('mongo_timestamp', $since);
        }

        if ($parameters->has('until')) {
            $until = Util\Date::dateStringToMongoDate($parameters->get('until'));
            $expression->whereLessOrEqual('mongo_timestamp', $until);
        }

        // Count before paginating
        $statementResult->setTotalCount($storage->count(self::COLLECTION_NAME, $expression, $queryOptions));

        // Handle pagination
        if ($parameters->has('since_id')) {
            $id = new \MongoDB\BSON\ObjectID($parameters->get('since_id'));
            $expression->whereGreater('_id', $id);
        }

        if ($parameters->has('until_id')) {
            $id = new \MongoDB\BSON\ObjectID($parameters->get('until_id'));
            $expression->whereLess('_id', $id);
        }

        $statementResult->setRequestedFormat(Config::get(['xAPI', 'default_statement_get_format']));
        if ($parameters->has('format')) {
            $statementResult->setRequestedFormat($parameters->get('format'));
        }

        $statementResult->setSortDescending(true);
        $statementResult->setSortAscending(false);
        $queryOptions['sort'] = ['_id' => -1];
        if ($parameters->has('ascending')) {
            $asc = $parameters->get('ascending');
            if (strtolower($asc) === 'true' || $asc === '1') {
                $queryOptions['sort'] = ['_id' => 1];
                $statementResult->setSortDescending(false);
                $statementResult->setSortAscending(true);
            }
        }

        if ($parameters->has('limit') && $parameters->get('limit') < Config::get(['xAPI', 'statement_get_limit']) && $parameters->get('limit') > 0) {
            $limit = $parameters->get('limit');
        } else {
            $limit = Config::get(['xAPI', 'statement_get_limit']);
        }

        // Remaining includes the current page!
        $statementResult->setRemainingCount($storage->count(self::COLLECTION_NAME, $expression, $queryOptions));

        if ($statementResult->getRemainingCount() > $limit) {
            $statementResult->setHasMore(true);
        } else {
            $statementResult->setHasMore(false);
        }

        $queryOptions['limit'] = (int)$limit;
        
        $cursor = $storage->find(self::COLLECTION_NAME, $expression, $queryOptions);

        $statementResult->setCursor($cursor);

        return $statementResult;
    }

    public function getById($statementId)
    {
        $storage = $this->getContainer()['storage'];
        $expression = $storage->createExpression();
        $expression->where('statement.id', $statementId);
        $requestedStatement = $storage->findOne('statements', $expression);

        if (null === $requestedStatement) {
            throw new \InvalidArgumentException('Requested statement does not exist!', Controller::STATUS_BAD_REQUEST);
        }

        return $requestedStatement;
    }

    public function statementWithIdExists($statementId)
    {
        return false;
    }

    public function insert($statementObject)
    {
        $storage = $this->getContainer()['storage'];
        
        // TODO: This should be in Activity storage manager!
        //$activityCollection = $this->getDocumentManager()->getCollection('activities');

        $attachmentBase = $this->getContainer()['url']->getBaseUrl().Config::get(['filesystem', 'exposed_url']);

        if (isset($statementObject['id'])) {
            $expression = $storage->createExpression();
            $expression->where('statement.id', $statementObject['id']);

            $result = $storage->findOne(self::COLLECTION_NAME, $expression);

            // ID exists, validate if different or conflict
            if ($result) {
                $this->validateStatementMatches($statementObject, $result);
            }
        }

        $statementDocument = new \API\Document\Statement();
        // Uncomment this!
        // Overwrite authority - unless it's a super token and manual authority is set
        //if (!($this->getAccessToken()->isSuperToken() && isset($statementObject['authority'])) || !isset($statementObject['authority'])) {
        //    $statementObject['authority'] = $this->getAccessToken()->generateAuthority();
        //}
        $statementDocument->setStatement($statementObject);
        // Dates
        $currentDate = Util\Date::dateTimeExact();
        $statementDocument->setVoided(false);
        $statementDocument->setStored(Util\Date::dateTimeToISO8601($currentDate));
        $statementDocument->setMongoTimestamp(Util\Date::dateTimeToMongoDate($currentDate));
        $statementDocument->setDefaultTimestamp();
        $statementDocument->fixAttachmentLinks($attachmentBase);
        $statementDocument->convertExtensionKeysToUnicode();
        $statementDocument->setDefaultId();
        $statementDocument->legacyContextActivities();
        if ($statementDocument->isReferencing()) {
            // Copy values of referenced statement chain inside current statement for faster query-ing
            // (space-time tradeoff)
            $referencedStatementId = $statementDocument->getReferencedStatementId();
            $referencedStatement = $this->getById($referencedStatementId);
            $referencedStatement = new \API\Document\Statement($referencedStatement);

            $existingReferences = [];
            if (null !== $referencedStatement->getReferences()) {
                $existingReferences = $referencedStatement->getReferences();
            }
            $existingReferences[] = $referencedStatement->getStatement();
            $statementDocument->setReferences($existingReferences);
        }
        //$statements[] = $statementDocument->toArray();
        if ($statementDocument->isVoiding()) {
            $referencedStatementId = $statementDocument->getReferencedStatementId();
            $referencedStatement = $this->getById($referencedStatementId);
            $referencedStatement = new \API\Document\Statement($referencedStatement);

            $this->validateVoidedStatementNotVoiding($referencedStatement);
            $referencedStatement->setVoided(true);
            $expression = $storage->createExpression();
            $expression->where('statement.id', $referencedStatementId);
        
            $storage->update(self::COLLECTION_NAME, $expression, $referencedStatement);
        }
        /*if ($this->getAccessToken()->hasPermission('define')) {
            $activities = $statementDocument->extractActivities();
            if (count($activities) > 0) {
                $activityCollection->insertMultiple($activities);
            }
        }*/
        // TODO: Save this as a batch
        // Save statement
        $storage->insertOne(self::COLLECTION_NAME, $statementDocument);

        // Add to log
        //$this->getContainer()->requestLog->addRelation('statements', $statementDocument)->save();

        // TODO: Batch insertion of statement upserts!!! - possible with new driver :)
        // self::COLLECTION_NAME->insertMultiple($statements); // Batch operation is much faster ~600%
        // However, because we add every single statement to the access log, we can't use it
        // The only way to still use (fast) batch inserts would be to move the attachment of
        // statements to their respective log entries in a async queue!

        return $statementDocument;
    }

    public function insertOne($statementObject)
    {
        $statementDocument = $this->insert($statementObject);
        $statementResult = new StatementResult();
        $statementResult->setCursor([$statementDocument]);
        $statementResult->setRemainingCount(1);
        $statementResult->setHasMore(false);

        return $statementResult;
    }

    public function insertMultiple($statementObjects)
    {
        $statementDocuments = [];
        foreach ($statementObjects as $statementObject) {
            $statementDocuments[] = $this->insert($statementObject);
        }
        $statementResult = new StatementResult();
        $statementResult->setCursor($statementDocuments);
        $statementResult->setRemainingCount(count($statementDocuments));
        $statementResult->setHasMore(false);

        return $statementResult;
    }

    public function put($parameters, $statementObject)
    {
        $parameters = new Util\Collection($parameters);

        // Check statementId exists
        if (!$parameters->has('statementId')) {
            throw new Exception('The statementId parameter is missing!', Controller::STATUS_BAD_REQUEST);
        }

        $this->validateStatementId($parameters['statementId']);

        // Check statementId
        if (isset($statementObject['id'])) {
            // Check for match
            $this->validateStatementIdMatch($statementObject['id'], $parameters['statementId']);
        } else {
            $body['id'] = $parameters->get('statementId');
        }

        $statementDocument = $this->insert($statementObject);
        $statementResult = new StatementResult();
        $statementResult->setCursor([$statementDocument]);
        $statementResult->setRemainingCount(1);
        $statementResult->setHasMore(false);

        return $statementResult;
    }

    public function delete($parameters)
    {
        throw \InvalidArgumentException('Statements cannot be deleted, only voided!', Controller::STATUS_INTERNAL_SERVER_ERROR);
    }

    /**
     * Gets the Access token to validate for permissions.
     *
     * @return API\Document\Auth\AbstractToken
     */
    private function getAccessToken()
    {
        return $this->getContainer()->auth;
    }

    private function validateStatementMatches($statementOne, $statementTwo)
    {
        // Same - return 200
        if ($statementOne == $statementTwo) {
            // Mismatch - return 409 Conflict
            throw new Exception('An existing statement already exists with the same ID and is different from the one provided.', Controller::STATUS_CONFLICT);
        }
    }

    private function validateVoidedStatementNotVoiding($referencedStatement)
    {
        if ($referencedStatement->isVoiding()) {
            throw new Exception('Voiding statements cannot be voided.', Controller::STATUS_CONFLICT);
        }
    }

    private function validateStatementId($id)
    {
        // Check statementId is acutally valid
        if (!Uuid::isValid($id)) {
            throw new Exception('The provided statement ID is invalid!', Controller::STATUS_BAD_REQUEST);
        }
    }

    private function validateStatementIdMatch($statementIdOne, $statementIdTwo)
    {
        if ($statementIdOne !== $statementIdTwo) {
            throw new Exception('Statement ID query parameter doesn\'t match the given statement property', Controller::STATUS_BAD_REQUEST);
        }
    }

    private function validateCursorNotEmpty($cursor)
    {
        $cursor = $cursor->toArray();
        if (empty($cursor)) {
            throw new Exception('Statement does not exist.', Controller::STATUS_NOT_FOUND);
        }
        return $cursor;
    }
}
