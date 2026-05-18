<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\Contracts\AuthProviderDriverInterface;
use App\Auth\Drivers\EmailAuthProviderDriver;
use App\Auth\Drivers\FacebookAuthProviderDriver;
use App\Auth\Drivers\GithubAuthProviderDriver;
use App\Auth\Drivers\GoogleAuthProviderDriver;
use App\Repositories\Auth\AuthProviderRepository;
use App\Repositories\Auth\EmailVerificationCodeRepository;
use App\Repositories\Auth\Interfaces\AuthProviderRepositoryInterface;
use App\Repositories\Auth\Interfaces\EmailVerificationCodeRepositoryInterface;
use App\Repositories\Coin\FavoriteCoinRepository;
use App\Repositories\Coin\FeedKeywordRepository;
use App\Repositories\Coin\Interfaces\FavoriteCoinRepositoryInterface;
use App\Repositories\Coin\Interfaces\FeedKeywordRepositoryInterface;
use App\Repositories\Coin\Interfaces\TagRepositoryInterface;
use App\Repositories\Coin\TagRepository;
use App\Repositories\Stock\FavoriteStockRepository;
use App\Repositories\Stock\Interfaces\FavoriteStockRepositoryInterface;
use App\Repositories\Stock\Interfaces\StockRepositoryInterface;
use App\Repositories\Stock\StockRepository;
use App\Repositories\User\Interfaces\UserRepositoryInterface;
use App\Repositories\User\UserRepository;
use App\Services\Auth\AuthProviderConfigService;
use App\Services\Auth\AuthProviderOAuthService;
use App\Services\Auth\AuthProviderRegistry;
use App\Services\Auth\AuthProviderService;
use App\Services\Auth\EmailVerificationService;
use App\Services\Coin\BinanceCoinApiClient;
use App\Services\Coin\CoinApiClientInterface;
use App\Services\Coin\CoinServiceFactory;
use App\Services\Coin\CoinServiceInterface;
use App\Services\Coin\FavoriteCoinService;
use App\Services\Coin\FavoriteCoinServiceInterface;
use App\Services\Stock\FavoriteStockService;
use App\Services\Stock\FavoriteStockServiceInterface;
use App\Services\Stock\StockService;
use App\Services\Stock\StockServiceInterface;
use App\Services\User\AdminUserService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Class AppServiceProvider
 *
 * Responsible for registering and bootstrapping application services.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register all application service bindings.
     */
    public function register(): void
    {
        $this->app->singleton(EmailAuthProviderDriver::class);
        $this->app->singleton(GoogleAuthProviderDriver::class);
        $this->app->singleton(FacebookAuthProviderDriver::class);
        $this->app->singleton(GithubAuthProviderDriver::class);

        $this->app->tag([
            EmailAuthProviderDriver::class,
            GoogleAuthProviderDriver::class,
            FacebookAuthProviderDriver::class,
            GithubAuthProviderDriver::class,
        ], 'auth.provider.driver');

        $this->app->singleton(AuthProviderRegistry::class, function (Application $app): AuthProviderRegistry {
            /** @var iterable<AuthProviderDriverInterface> $drivers */
            $drivers = $app->tagged('auth.provider.driver');

            return new AuthProviderRegistry($drivers);
        });

        $this->app->bind(AuthProviderRepositoryInterface::class, AuthProviderRepository::class);
        $this->app->bind(EmailVerificationCodeRepositoryInterface::class, EmailVerificationCodeRepository::class);
        $this->app->singleton(AuthProviderConfigService::class);
        $this->app->singleton(AuthProviderOAuthService::class);
        $this->app->singleton(AuthProviderService::class);
        $this->app->singleton(EmailVerificationService::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->singleton(AdminUserService::class);

        // Bind the Coin API client interface to Binance implementation by default
        $this->app->bind(CoinApiClientInterface::class, BinanceCoinApiClient::class);

        // Bind the FavoriteCoin repository and service
        $this->app->bind(FavoriteCoinRepositoryInterface::class, FavoriteCoinRepository::class);
        $this->app->bind(FavoriteCoinServiceInterface::class, FavoriteCoinService::class);

        // Bind the Stock repositories and services
        $this->app->bind(StockRepositoryInterface::class, StockRepository::class);
        $this->app->bind(FavoriteStockRepositoryInterface::class, FavoriteStockRepository::class);
        $this->app->bind(StockServiceInterface::class, StockService::class);
        $this->app->bind(FavoriteStockServiceInterface::class, FavoriteStockService::class);

        // Bind the Tag repository
        $this->app->bind(TagRepositoryInterface::class, TagRepository::class);

        $this->app->bind(FeedKeywordRepositoryInterface::class, FeedKeywordRepository::class);

        // Dynamically bind CoinServiceInterface based on the "source" query parameter
        $this->app->bind(
            CoinServiceInterface::class,
            function () {
                $rawSource = Request::get('source');
                $source = is_string($rawSource) ? $rawSource : 'binance';

                return CoinServiceFactory::make($source);
            }
        );
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (HttpRequest $request): Limit {
            return Limit::perMinute(120)->by($request->ip());
        });

        try {
            if (Schema::hasTable('auth_providers')) {
                $this->app->make(AuthProviderService::class)->ensureDefaultProviders();
            }
        } catch (Throwable) {
            // Skip provider synchronization until the database connection is available.
        }
    }
}
