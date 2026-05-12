<?php

declare(strict_types=1);

namespace App\Providers;

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
use App\Services\Coin\BinanceCoinApiClient;
use App\Services\Coin\CoinApiClientInterface;
use App\Services\Coin\CoinServiceFactory;
use App\Services\Coin\FavoriteCoinService;
use App\Services\Coin\FavoriteCoinServiceInterface;
use App\Services\Stock\FavoriteStockService;
use App\Services\Stock\FavoriteStockServiceInterface;
use App\Services\Stock\StockService;
use App\Services\Stock\StockServiceInterface;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;

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
            \App\Services\Coin\CoinServiceInterface::class,
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
        // No boot logic required for now
    }
}
