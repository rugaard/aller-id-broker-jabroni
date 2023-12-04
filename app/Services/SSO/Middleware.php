<?php
declare(strict_types=1);

namespace App\Services\SSO;

use App\Services\SSO\Broker as SSOBroker;
use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
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
            $redirect = redirect(to: $request->fullUrlWithoutQuery(keys: ['aller-id', 'checksum', 'code', 'error']));

            return !$this->broker->isDecryptable(value: $payload) || !$this->broker->validateChecksum(checksum: $request->get(key: 'checksum'), parameters: $request->except(keys: ['aller-id', 'checksum', 'code', 'error'])) || $payload === 'false'
                ? $redirect->with(key: 'aller_id', value: false)
                : $redirect->withCookie(cookie: Cookie::forever(name: 'aller_id', value: $payload, secure: true));
        }

        // When no Aller ID cookie is available,
        // redirect user to SSO service fo potential
        // automatic authentication by shared session.
        if (!Cookie::has(key: 'aller_id') && !Session::has('aller_id')) {
            return $this->broker->authRedirect();
        }

        // When Aller ID cookie is available,
        // then fetch user info from SSO service.
        if (Cookie::get(key: 'aller_id') !== null) {
            try {
                // Fetch user information.
                $userInfo = $this->broker->userInfo(encryptedSessionToken: Cookie::get(key: 'aller_id'));

                // Create a generic user with info from Aller ID,
                // and authenticated within our application.
                Auth::setUser(user: new GenericUser(attributes: (array) $userInfo));
            } catch (Throwable) {
                // Log out authenticated user.
                Auth::logout();

                // Reload current URL without "aller_id" cookie,
                // since it's containing user session is expired.
                return redirect(to: $request->fullUrlWithoutQuery(keys: ['aller-id', 'checksum', 'code', 'error']))
                    ->with(key: 'aller_id', value: false)
                    ->withCookie(cookie: Cookie::forget(name: 'aller_id'));
            }
        }

        return $next($request);
    }
}
