<?php

namespace App\Http\Controllers;

use App\Services\SSO\Broker as SSOBroker;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Index action.
     *
     * @param \App\Services\SSO\Broker $broker
     * @return \Illuminate\View\View
     */
    public function index(SSOBroker $broker): View
    {
        $allerIdCookie = Cookie::has(key: 'aller_id') ? $broker->encrypter->decrypt(payload: Cookie::get(key: 'aller_id')) : '(tom)';
        return view(view: 'index', data: compact('allerIdCookie'));
    }

    /**
     * Redirect to SSO server.
     *
     * @param \App\Services\SSO\Broker $broker
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(SSOBroker $broker, Request $request): RedirectResponse
    {
        return $broker->loginRedirect(email: $request->get(key: 'email'), password: $request->get(key: 'password'), parameters: [
            'redirect_url' => urlencode(string: route(name: 'login.validate')),
            'redirect_to' => urlencode(string: route(name: 'index')),
        ]);
    }

    /**
     * Validate login response.
     *
     * @param \App\Services\SSO\Broker $broker
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function validate(SSOBroker $broker, Request $request): RedirectResponse
    {
        if ($request->has(key: 'error') || !$broker->isDecryptable(value: $request->get(key: 'payload'))) {
            return redirect()->route(route: 'index')->with(key: 'error', value: 'Beklager! Log ind mislykkedes.');
        }

        return redirect(to: urldecode(string: $request->get(key: 'redirect_to', default: route(name: 'index'))))->withCookie(
            // Create/update cookie with Aller ID encrypted token.
            Cookie::forever(name: 'aller_id', value: $request->get(key: 'payload'), secure: true)
        );
    }

    /**
     * Log out action.
     *
     * @param \App\Services\SSO\Broker $broker
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(SSOBroker $broker): RedirectResponse
    {
        return $broker->logoutRedirect();
    }
}
