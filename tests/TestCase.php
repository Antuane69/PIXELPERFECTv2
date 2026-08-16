<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  array<string, bool|float|int|string>  $expectedQuery
     */
    protected function urlContainsQuery(mixed $url, array $expectedQuery): bool
    {
        if (! is_string($url)) {
            return false;
        }

        $queryString = parse_url($url, PHP_URL_QUERY);

        if (! is_string($queryString)) {
            return false;
        }

        parse_str($queryString, $actualQuery);

        foreach ($expectedQuery as $key => $value) {
            if (! array_key_exists($key, $actualQuery) || (string) $actualQuery[$key] !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
