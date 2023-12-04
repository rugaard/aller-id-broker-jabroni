<?php
declare(strict_types=1);

namespace App\Services\SSO;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

use const SORT_NATURAL;

use function array_key_exists;
use function array_merge;
use function config;
use function http_build_query;
use function implode;
use function ksort;
use function ucfirst;

/**
 * Class Broker.
 *
 * @package \App\Services\SSO
 */
class Broker
{
    /**
     * URL of SSO server.
     *
     * @var string
     */
    protected string $ssoServerUrl = 'https://aller-id.test/sso';

    /**
     * Broker encrypter.
     *
     * @var \Illuminate\Encryption\Encrypter
     */
    public Encrypter $encrypter;

    /**
     * Broker constructor.
     *
     * @param string $id
     * @param string $secret
     * @param string $token
     */
    public function __construct(protected string $id, protected string $secret, protected string $token)
    {
        // Create broker encrypter.
        $this->encrypter = new Encrypter(key: $secret, cipher: config('app.cipher'));
    }

    /**
     * Create a redirect response to check,
     * if user is already authenticated with SSO server.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authRedirect(): RedirectResponse
    {
        // Get current full URL.
        $currentUrl = URL::full();

        // Site domain.
        $domain = $this->domain();

        // Redirect user to SSO authentication sequence.
        return redirect(to: $this->ssoServerUrl . '/auth?' . http_build_query(data: [
            'broker' => $this->id,
            'token' => $this->token,
            'domain' => $domain,
            'origin' => $currentUrl,
            'checksum' => $this->generateChecksum(parameters: [$domain, $currentUrl])
        ]));
    }

    /**
     * Create a login redirect response.
     *
     * @param string $email
     * @param string $password
     * @param array $parameters
     * @return \Illuminate\Http\RedirectResponse
     */
    public function loginRedirect(string $email, string $password, array $parameters = []): RedirectResponse
    {
        // Increase security by encrypting credentials during transport.
        $encryptedCredentials = $this->encrypter->encrypt(value: $email . ':' . $password);

        // Site domain.
        $domain = $this->domain();

        // Collect and merge parameters.
        $parameters = array_merge($parameters, [
            'credentials' => $encryptedCredentials,
            'domain' => $domain
        ]);

        // Redirect user to login attempt.
        return redirect(to: $this->ssoServerUrl . '/auth/login?' . http_build_query(data: array_merge($parameters, [
            'broker' => $this->id,
            'token' => $this->token,
            'domain' => $domain,
            'checksum' => $this->generateChecksum(parameters: $parameters ?? [])
        ])));
    }

    /**
     * Create a logout redirect response.
     *
     * @param array $parameters
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logoutRedirect(array $parameters = []): RedirectResponse
    {
        // Site domain.
        $domain = $this->domain();

        // Collect and merge parameters.
        $parameters = array_merge($parameters, [
            'domain' => $domain,
            'session' => Cookie::get('aller_id')
        ]);

        // Redirect user to logout sequence.
        return redirect(to: $this->ssoServerUrl . '/auth/logout?' . http_build_query(data: array_merge($parameters, [
            'broker' => $this->id,
            'token' => $this->token,
            'domain' => $domain,
            'checksum' => $this->generateChecksum(parameters: $parameters ?? [])
        ])));
    }

    /**
     * Get user info from SSO server.
     *
     * @param string $encryptedSessionToken
     * @return array
     */
    public function userInfo(string $encryptedSessionToken): array
    {
        $response = $this->request(method: 'get', endpoint: 'user', parameters: [
            'referer' => request()->path()
        ], headers: [
            'Authorization' => 'Aller ' . $encryptedSessionToken
        ]);

        return $response['data'] ?? [];
    }

    /**
     * Send request to SSO server.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $parameters
     * @param array $headers
     * @param string|null $as
     * @return array
     */
    protected function request(string $method, string $endpoint, array $parameters = [], array $headers = [], ?string $as = null, ?string $userAgent = null): array
    {
        // Base request.
        $request = Http::acceptJson()->withUrlParameters(parameters: [
            'ssoServerUrl' => $this->ssoServerUrl,
            'endpoint' => $endpoint
        ])->withHeaders(headers: array_merge($headers, [
            'User-Agent' => $userAgent ?? request()->userAgent(),
            'X-Broker-Id' => $this->id,
            'X-Broker-Token' => $this->token,
            'X-Checksum' => $this->generateChecksum(parameters: $parameters),
            'X-Site-Domain' => $this->domain()
        ]));

        // Add parameters to request.
        if (!empty($parameters)) {
            $as !== null && array_key_exists(key: $as, array: ['body', 'form', 'json'])
                ? $request->{'as' . ucfirst(string: $as)}($parameters)
                : $request->withQueryParameters(parameters: $parameters);
        }

        // While in debug mode, disable TLS verification.
        if (config(key: 'app.debug')) {
            $request->withoutVerifying();
        }

        return $request->{$method}('{+ssoServerUrl}/{endpoint}')->throw()->json();
    }

    /**
     * Validate request.
     *
     * @param string $checksum
     * @param string $brokerId
     * @param string $siteToken
     * @param string $siteDomain
     * @param array $parameters
     * @return bool
     */
    public function validateChecksum(string $checksum, array $parameters = []): bool
    {
        try {
            // Decrypt checksum.
            $checksum = $this->encrypter->decrypt(payload: $checksum);

            // Naturally sort parameters by key.
            ksort(array: $parameters, flags: SORT_NATURAL);

            // Validate checksum equals received query parameters.
            return $checksum === sprintf('%s:%s:%s', implode(separator: ':', array: $parameters), $this->token, $this->id);
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * Generate checksum.
     *
     * @param array $parameters
     * @return string
     */
    public function generateChecksum(array $parameters = []): string
    {
        // Naturally sort parameters by key.
        ksort(array: $parameters, flags: SORT_NATURAL);

        // Generate encrypted checksum.
        return $this->encrypter->encrypt(value:
            sprintf('%s:%s:%s', $this->id, $this->token, implode(separator: ':', array: $parameters))
        );
    }

    /**
     * Whether value is decryptable or not.
     *
     * @param string $value
     * @return bool
     */
    public function isDecryptable(string $value): bool
    {
        try {
            $this->encrypter->decrypt(payload: $value);
            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * Get domain of application.
     *
     * @return string
     */
    public function domain(): string
    {
        return preg_replace(pattern: '/^www\./', replacement: '', subject: parse_url(url: URL::full(), component: PHP_URL_HOST));
    }
}
