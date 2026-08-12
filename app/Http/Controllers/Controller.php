<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

abstract class Controller
{
    /**
     * @param  list<string>  $allowedQueryParameters
     * @param  array<string, scalar>  $fallbackParameters
     */
    protected function redirectToResourceIndex(
        Request $request,
        string $routeName,
        array $allowedQueryParameters,
        array $fallbackParameters = [],
    ): RedirectResponse {
        $referer = $request->headers->get('referer');
        $indexPath = parse_url(route($routeName), PHP_URL_PATH);
        $refererPath = is_string($referer) ? parse_url($referer, PHP_URL_PATH) : null;

        if (! is_string($indexPath) || $refererPath !== $indexPath) {
            return to_route($routeName, $fallbackParameters);
        }

        $queryString = parse_url($referer, PHP_URL_QUERY);

        if (! is_string($queryString)) {
            return to_route($routeName, $fallbackParameters);
        }

        parse_str($queryString, $queryParameters);

        $safeQueryParameters = Arr::where(
            Arr::only($queryParameters, $allowedQueryParameters),
            static fn (mixed $value): bool => is_scalar($value),
        );

        return to_route($routeName, $safeQueryParameters);
    }
}
