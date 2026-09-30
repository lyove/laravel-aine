<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

class VerifyCsrfToken extends PreventRequestForgery
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'forms/*',
    ];

    /**
     * Determine if the session and input CSRF tokens match.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function tokensMatch($request)
    {
        if ($request->bearerToken()) {
            return true;
        }

        return parent::tokensMatch($request);
    }
}
