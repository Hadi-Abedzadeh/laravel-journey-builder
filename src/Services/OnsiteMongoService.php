<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use MongoDB\BSON\ObjectId;
use RuntimeException;

/**
 * Fixed MongoDB schema:
 *  - database: onsite_journey (connection configuration)
 *  - onsite collection: onsites, {_id:ObjectId, onsite_name:string}
 *  - user collection: users, {_id:ObjectId, userId:string, onsite_journey:{"<onsiteId>":bool}}
 *
 * We deliberately never create user documents. Exact string equality between
 * member_data.userId and MongoDB users.userId is required.
 */
final class OnsiteMongoService
{
    private const DATABASE = 'onsite_journey';
    private const ONSITES = 'onsites';
    private const USERS = 'users';
    private const NAME = 'onsite_name';
    private const ASSIGNMENTS = 'onsite_journey';
    private const EXTERNAL_USER_ID = 'userId';

    public function client(): \MongoDB\Client
    {
        if (!extension_loaded('mongodb') || !class_exists(\MongoDB\Client::class)) {
            throw new RuntimeException('Install PHP ext-mongodb and mongodb/mongodb Composer library.');
        }
        $uri = (string) config('journey_builder.onsite.mongo.uri', '');
        if ($uri === '' || !preg_match('~^mongodb(?:\+srv)?://~i', $uri)) {
            throw new RuntimeException('MongoDB URI is missing or invalid.');
        }
        return new \MongoDB\Client($uri, [
            'serverSelectionTimeoutMS' => 2500,
            'connectTimeoutMS' => 2500,
        ]);
    }

    public function database(): \MongoDB\Database
    {
        return $this->client()->selectDatabase(self::DATABASE);
    }

    public static function isValidOnsiteId(string $id): bool
    {
        return (bool) preg_match('/^[a-fA-F0-9]{24}$/D', $id);
    }

    /** @return list<array{id: string, name: string}> */
    public function onsiteOptions(): array
    {
        $cursor = $this->database()->selectCollection(self::ONSITES)->find(
            [self::NAME => ['$type' => 'string']],
            ['projection' => ['_id' => 1, self::NAME => 1], 'limit' => 2100]
        );
        $options = [];
        foreach ($cursor as $doc) {
            $id = (string) ($doc['_id'] ?? '');
            $name = $doc[self::NAME] ?? null;
            if (!self::isValidOnsiteId($id) || !is_string($name) || trim($name) === '') continue;
            $options[] = ['id' => strtolower($id), 'name' => trim($name)];
        }
        usort($options, static fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
        return array_slice($options, 0, 2000);
    }

    /** Legacy helper for existing callers, now only used for display. */
    public function onsiteNames(): array
    {
        return array_values(array_unique(array_column($this->onsiteOptions(), 'name')));
    }

    /** Backward compatibility for old graphs that persisted an onsite_name. */
    public function resolveId(string $idOrName): string
    {
        if (self::isValidOnsiteId($idOrName)) {
            $id = strtolower($idOrName);
            $doc = $this->database()->selectCollection(self::ONSITES)->findOne(
                ['_id' => new ObjectId($id)], ['projection' => ['_id' => 1, self::NAME => 1]]
            );
            if (!$doc || !is_string($doc[self::NAME] ?? null) || trim($doc[self::NAME]) === '') {
                throw new RuntimeException('Selected Onsite does not exist or has no onsite_name.');
            }
            return $id;
        }
        // Migration compatibility only; reject duplicate labels rather than choosing the wrong record.
        $name = trim($idOrName);
        if ($name === '') throw new RuntimeException('Onsite ID is missing.');
        $matches = $this->database()->selectCollection(self::ONSITES)
            ->find([self::NAME => $name], ['projection' => ['_id' => 1], 'limit' => 2]);
        $ids = [];
        foreach ($matches as $doc) $ids[] = (string) $doc['_id'];
        if (count($ids) !== 1) throw new RuntimeException('Onsite name is missing or ambiguous; select its ObjectId in the editor.');
        return strtolower($ids[0]);
    }

    /**
     * SQL member_data.userId is the exact string in MongoDB users.userId.
     * MongoDB users._id remains an unrelated ObjectId and must never be used
     * to infer the user identity, even when userId looks like 24 hex digits.
     */
    private function userIdFromSql(string $userId): ObjectId
    {
        if ($userId === '' || strlen($userId) > 100) {
            throw new RuntimeException('Invalid Journey userId.');
        }

        $cursor = $this->database()->selectCollection(self::USERS)->find(
            [self::EXTERNAL_USER_ID => $userId],
            ['projection' => ['_id' => 1], 'limit' => 2]
        );
        $users = iterator_to_array($cursor, false);
        if (count($users) !== 1 || !(($users[0]['_id'] ?? null) instanceof ObjectId)) {
            throw new RuntimeException('MongoDB users.userId was not found uniquely; Onsite was not assigned.');
        }
        return $users[0]['_id'];
    }

    public function assign(string $userId, string $onsiteId): void
    {
        $id = $this->resolveId($onsiteId);
        $mongoUserId = $this->userIdFromSql($userId);
        $path = self::ASSIGNMENTS . '.' . $id;

        // Atomic idempotency: never reset a false (already viewed) flag to true
        // on a worker retry / transient network error. This update is only for
        // new onsite assignments not previously present on this user.
        $collection = $this->database()->selectCollection(self::USERS);
        $result = $collection->updateOne(
            ['_id' => $mongoUserId, $path => ['$exists' => false]],
            ['$set' => [$path => true]],
            ['upsert' => false]
        );
        if ($result->getMatchedCount() === 0) {
            // Existing true/false is an idempotent replay. Any other result is invalid.
            $doc = $collection->findOne(['_id' => $mongoUserId], ['projection' => [$path => 1]]);
            $flag = ($doc[self::ASSIGNMENTS] ?? null)[$id] ?? null;
            if ($flag !== true && $flag !== false) {
                throw new RuntimeException('Mongo Onsite assignment could not be verified.');
            }
        }
        // If already false, the Onsite frontend owns the final state: never re-arm it on retry.
    }

    /** @return 'pending'|'viewed'|'missing' */
    public function displayStatus(string $userId, string $onsiteId): string
    {
        if (!self::isValidOnsiteId($onsiteId)) {
            throw new RuntimeException('Select a valid MongoDB onsite ObjectId for the waiting node.');
        }
        $user = $this->userIdFromSql($userId);
        $id = strtolower($onsiteId);
        $path = self::ASSIGNMENTS . '.' . $id;
        $doc = $this->database()->selectCollection(self::USERS)->findOne(
            ['_id' => $user], ['projection' => [$path => 1]]
        );
        if (!$doc) throw new RuntimeException('Mongo user disappeared during onsite wait.');
        $data = $doc[self::ASSIGNMENTS] ?? null;
        $value = $data[$id] ?? null;
        // Nested BSONDocument may be ArrayAccess; PHP arrays are also supported.
        if ($value === false) return 'viewed';
        if ($value === true) return 'pending';
        return 'missing';
    }
}
