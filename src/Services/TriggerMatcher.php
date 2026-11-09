<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

final class TriggerMatcher
{
    public static function matches(mixed $actual, string $operator, string $expected = ''): bool
    {
        $value = $actual === null ? null : (string) $actual;
        return match ($operator) {
            'exists' => $value !== null && $value !== '',
            'not_exists' => $value === null || $value === '',
            'equals' => $value !== null && $value === $expected,
            'not_equals' => $value !== null && $value !== $expected,
            'starts_with' => $value !== null && str_starts_with($value, $expected),
            'not_starts_with' => $value !== null && !str_starts_with($value, $expected),
            default => false,
        };
    }
}
