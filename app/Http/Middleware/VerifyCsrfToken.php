<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as IlluminateVerifyCsrfToken;
use Illuminate\Http\Request;

class VerifyCsrfToken extends IlluminateVerifyCsrfToken
{
    /**
     * The URLs of origins to be excluded.
     *
     * @var array<int, string>
     */
    protected $allowedOrigins = [];

    /**
     * The URIs that should be excluded.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/health-check/*',
    ];

    /**
     * The globally ignored URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected static $neverVerify = [];

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     *
     * @throws TokenMismatchException
     */
    public function handle($request, Closure $next)
    {
        if (
            $this->isReading($request) ||
            $this->runningUnitTests() ||
            $this->inExceptArray($request) ||
            $this->tokensMatch($request)
        ) {
            return tap($next($request), function ($response) use ($request) {
                if ($this->shouldAddXsrfTokenCookie()) {
                    $this->addCookieToResponse($request, $response);
                }
            });
        }

        throw new TokenMismatchException('CSRF token mismatch on our safe list.');

        return parent::handle($request, $next);
    }

    public function getAllowedOrigins(): array
    {
        return array_unique(
            array_merge(
                $this->allowedOrigins,
                (array) config('cors.allowed_origins', []),
                [
                    //
                ],
            ),
        );
    }

    public function getExcludedPaths(): array
    {
        return array_merge($this->except, static::$neverVerify);
    }

    /**
     * Indicate that the given URIs should be excluded from CSRF verification.
     *
     * @param  array|string  $uris
     * @return void
     */
    public static function except($uris)
    {
        static::$neverVerify = array_values(array_unique(
            array_merge(static::$neverVerify, Arr::wrap($uris))
        ));

        parent::except(static::$neverVerify);
        IlluminateVerifyCsrfToken::except(static::$neverVerify);
    }
}
