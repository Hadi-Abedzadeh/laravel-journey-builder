<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use RuntimeException;

/** Read MongoDB Onsite names and idempotently assign one to an existing user. */
final class OnsiteMongoService
{
    private function settings(): array
    {
        return (array) config('journey_builder.onsite.mongo', []);
    }

    private function identifier(string $key): string
    {
        $value = (string)($this->settings()[$key] ?? '');
        if (!preg_match('/^[_A-Za-z][_A-Za-z0-9]{0,79}$/D', $value)) {
            throw new RuntimeException('Invalid MongoDB mapping setting: '.$key);
        }
        return $value;
    }

    public function client(): \MongoDB\Client
    {
        if (!extension_loaded('mongodb') || !class_exists(\MongoDB\Client::class)) {
            throw new RuntimeException('Install PHP ext-mongodb and composer package mongodb/mongodb.');
        }
        $uri = (string)($this->settings()['uri'] ?? '');
        if ($uri === '' || !preg_match('~^mongodb(?:\+srv)?://~i', $uri)) {
            throw new RuntimeException('MongoDB URI is missing or invalid in journey_builder.onsite.mongo.uri.');
        }
        return new \MongoDB\Client($uri, [
            'serverSelectionTimeoutMS' => 2500,
            'connectTimeoutMS' => 2500,
        ]);
    }

    public function database(): \MongoDB\Database
    {
        return $this->client()->selectDatabase($this->identifier('database'));
    }

    /** @return list<string> */
    public function onsiteNames(): array
    {
        $field=$this->identifier('onsite_name_field');
        $result=$this->database()->selectCollection($this->identifier('onsites_collection'))->distinct($field);
        $names=[];
        foreach ($result as $value) {
            if (is_string($value) && trim($value)!=='') $names[]=trim($value);
        }
        $names=array_values(array_unique($names));
        sort($names, SORT_NATURAL);
        return array_slice($names, 0, 2000);
    }

    public function assign(string $userId, string $onsiteName): void
    {
        if (!config('journey_builder.onsite.enabled', false)) {
            throw new RuntimeException('Onsite assignment is disabled in journey_builder configuration.');
        }
        if ($userId==='' || strlen($userId)>100 || trim($onsiteName)==='' || mb_strlen($onsiteName)>180) {
            throw new RuntimeException('Invalid user ID or onsite name.');
        }
        $db=$this->database();
        $onsiteField=$this->identifier('onsite_name_field');
        // Check Mongo source data rather than trusting a browser-provided label.
        $found=$db->selectCollection($this->identifier('onsites_collection'))->findOne(
            [$onsiteField=>$onsiteName], ['projection'=>['_id'=>1]]
        );
        if (!$found) throw new RuntimeException('Selected onsite does not exist in MongoDB.');

        $idField=$this->identifier('user_id_field');
        $assignmentsField=(string)($this->settings()['assignments_field'] ?? '');
        // Supports an explicit nested array such as `onsite_journey.onsites`.
        if (!preg_match('/^[_A-Za-z][_A-Za-z0-9]{0,79}(?:\.[_A-Za-z][_A-Za-z0-9]{0,79}){0,3}$/D', $assignmentsField)) {
            throw new RuntimeException('Invalid MongoDB assignments field mapping.');
        }
        if ($idField===$assignmentsField || in_array($assignmentsField, ['_id', $idField], true)) {
            throw new RuntimeException('Invalid user onsite assignment field mapping.');
        }
        // $addToSet prevents duplicate onsite assignment on worker retry/crash.
        // No other user fields are replaced. Default upsert=false prevents accidental partial user records.
        $result=$db->selectCollection($this->identifier('users_collection'))->updateOne(
            [$idField=>$userId],
            ['$addToSet'=>[$assignmentsField=>$onsiteName]],
            ['upsert'=>(bool)($this->settings()['create_user_if_missing'] ?? false)]
        );
        if ($result->getMatchedCount()===0 && $result->getUpsertedCount()===0) {
            throw new RuntimeException('User does not exist in MongoDB users; enable upsert only after confirming its schema.');
        }
    }
}
