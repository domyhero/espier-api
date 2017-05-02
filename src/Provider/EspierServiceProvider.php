<?php

namespace Dingo\Api\Provider;

use ReflectionClass;
use Dingo\Api\Http\Middleware\Auth;
use Dingo\Api\Http\Middleware\Request;
use Dingo\Api\Http\Middleware\RateLimit;
use FastRoute\Dispatcher\GroupCountBased;
use Dingo\Api\Http\Middleware\PrepareController;
use FastRoute\RouteParser\Std as StdRouteParser;
use Illuminate\Http\Request as IlluminateRequest;
use Dingo\Api\Routing\Adapter\Espier as EspierAdapter;
use FastRoute\DataGenerator\GroupCountBased as GcbDataGenerator;

class EspierServiceProvider extends DingoServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        $this->app->configure('api');

        $reflection = new ReflectionClass($this->app);

        /** 
         * $this->app[Request::class]->mergeMiddlewares(
         *     $this->gatherAppMiddleware($reflection)
         * );
         * 
         * $this->addRequestMiddlewareToBeginning($reflection);
         */

        // Because Lumen sets the route resolver at a very weird point we're going to
        // have to use reflection whenever the request instance is rebound to
        // set the route resolver to get the current route.
        $this->app->rebinding(IlluminateRequest::class, function ($app, $request) {
            $request->setRouteResolver(function () use ($app) {
                $reflection = new ReflectionClass($app);

                $property = $reflection->getProperty('currentRoute');
                $property->setAccessible(true);

                return $property->getValue($app);
            });
        });

        $this->app->routeMiddleware([
            'api.auth' => Auth::class,
            'api.throttle' => RateLimit::class,
            'api.controllers' => PrepareController::class,
        ]);
    }

    /**
     * Setup the configuration.
     *
     * @return void
     */
    protected function setupConfig()
    {
        $this->app->configure('api');

        parent::setupConfig();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        parent::register();

        $this->app->singleton('api.router.adapter', function ($app) {
            return new EspierAdapter($app, new StdRouteParser, new GcbDataGenerator, GroupCountBased::class);
        });
    }

}
