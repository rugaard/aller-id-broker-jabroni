<?php
declare(strict_types=1);

namespace App\Services\SSO;

use App\Services\SSO\Broker as SSOBroker;
use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
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
        if ($request->has('aller-id')) {
            // Get payload from request.
            $payload = $request->get('aller-id');

            // Redirect with "aller_id" cookie.
            return redirect($request->fullUrlWithoutQuery(['aller-id', 'checksum', 'code', 'error']))->withCookie(
                !$this->broker->isDecryptable($payload) || !$this->broker->validateChecksum($request->get('checksum', $request->except(['aller-id', 'checksum', 'code', 'error']))) || $payload === 'false'
                    ? Cookie::make(name: 'aller_id', value: '', minutes: 5, secure: true)
                    : Cookie::forever(name: 'aller_id', value: $payload, secure: true)
            );
        }

        // When no Aller ID cookie is available,
        // redirect user to SSO service fo potential
        // automatic authentication by shared session.
        if (!Cookie::has('aller_id')) {
            return $this->broker->authRedirect();
        }

        // When Aller ID cookie is available,
        // then fetch user info from SSO service.
        if (Cookie::get('aller_id') !== '') {
            try {
                // Fetch user information.
                $userInfo = $this->broker->userInfo(Cookie::get('aller_id'));

                // Create a generic user with info from Aller ID,
                // and authenticated within our application.
                Auth::setUser(new GenericUser((array) $userInfo));
            } catch (Throwable $e) {
                dd($e->getMessage());
                // Log out authenticated user.
                Auth::logout();

                // Reload current URL without "aller_id" cookie,
                // since it's containing user session is expired.
                // return redirect()->refresh()->withCookie(
                //     Cookie::forget('aller_id')
                // );
            }
        }

        return $next($request);
    }
}
