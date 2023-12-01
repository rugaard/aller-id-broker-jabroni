<?php

namespace App\Http\Controllers;

use App\Services\SSO\Broker as SSOBroker;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cookie;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index(SSOBroker $broker)
    {
        $allerIdCookie = !Cookie::has('aller_id') ? 'N/A' : (Cookie::get('aller_id') !== '' ? $broker->encrypter->decrypt(Cookie::get('aller_id')) : '(tom)');
        return view('index', compact('allerIdCookie'));
    }

    public function login(SSOBroker $broker, Request $request): RedirectResponse
    {
        return $broker->loginRedirect($request->get('email'), $request->get('password'), [
            'flot' => 'fyr',
            'redirect_url' => urlencode(route('login.validate')),
            'redirect_to' => urlencode(route('index')),
            'dinmor' => 'er-flot',
            'hest' => 'lort'
        ]);
    }

    public function validate(SSOBroker $broker, Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            throw new Exception(sprintf('%d | Error: %s', $request->get('code'), $request->get('error')));
        }

        if (!$broker->isDecryptable($request->get('payload'))) {
            return redirect()->route('index')->with('error', 'Could not authenticate user');
        }

        return redirect(urldecode($request->get('redirect_to', route('index'))))->withCookie(
            // Create/update cookie with Aller ID encrypted token.
            Cookie::forever(name: 'aller_id', value: $request->get('payload'), secure: true)
        );
    }

    public function logout(SSOBroker $broker): RedirectResponse
    {
        return $broker->logoutRedirect();
    }
}
