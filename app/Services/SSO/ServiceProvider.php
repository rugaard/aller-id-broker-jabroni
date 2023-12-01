<?php
declare(strict_types=1);

namespace App\Services\SSO;

use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

/**
 * Class ServiceProvider.
 *
 * @package \App\Services\SSO
 */
class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Broker::class, static function () {
            return new Broker(
                id: 'cbae3a4d-f976-4ddc-8a4a-ebe26fc49c82',
                secret: '7kspxFFs1tUauiggXktlSHEAK8SFSzaO',
                token: 'Bp6HiTIyKq61s7jeJES6wEDX0skNMfReSDfMWEKiKLKJDxcrsUexpXyVvcyExJ1eGYUy1naXZWPdfBA7YWriOL2J2eqPVgnKecf9'
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
