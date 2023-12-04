<?php
declare(strict_types=1);

namespace App\Services\SSO;

use App\Services\SSO\Broker as SSOBroker;
use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Class Middleware.
 *
 * @package App\Services\SSO
 */
class Middleware
{
    /**
     * Middleware constructor.
     *
     * @param \App\Services\SSO\Broker $broker
     */
    public function __construct(protected SSOBroker $broker)
    {
        //
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param \App\Services\SSO\Broker $sso
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has(key: 'aller-id')) {
            // Get payload from request.
            $payload = $request->get(key: 'aller-id');

            // Redirect with "aller_id" cookie.
            return redirect(to: $request->fullUrlWithoutQuery(keys: ['aller-id', 'checksum', 'code', 'error']))->withCookie(
                cookie: !$this->broker->isDecryptable(value: $payload) || !$this->broker->validateChecksum(checksum: $request->get(key: 'checksum'), parameters: $request->except(keys: ['aller-id', 'checksum', 'code', 'error'])) || $payload === 'false'
                    ? Cookie::make(name: 'aller_id', value: '', minutes: 1, secure: true)
                    : Cookie::forever(name: 'aller_id', value: $payload, secure: true)
            );
        }

        // When no Aller ID cookie is available,
        // redirect user to SSO service fo potential
        // automatic authentication by shared session.
        if (!Cookie::has(key: 'aller_id')) {
            return $this->broker->authRedirect();
        }

        // When Aller ID cookie is available,
        // then fetch user info from SSO service.
        if (Cookie::get(key: 'aller_id') !== '') {
            try {
                // Fetch user information.
                $userInfo = $this->broker->userInfo(encryptedSessionToken: Cookie::get(key: 'aller_id'));

                // Create a generic user with info from Aller ID,
                // and authenticated within our application.
                Auth::setUser(user: new GenericUser(attributes: (array) $userInfo));
            } catch (Throwable $e) {
                // Log out authenticated user.
                Auth::logout();

                // Reload current URL without "aller_id" cookie,
                // since it's containing user session is expired.
                return redirect(to: $request->fullUrlWithoutQuery(keys: ['aller-id', 'checksum', 'code', 'error']))->withCookie(
                    cookie: Cookie::make(name: 'aller_id', value: '', minutes: 1, secure: true)
                );
            }
        }

        return $next($request);
    }
}
