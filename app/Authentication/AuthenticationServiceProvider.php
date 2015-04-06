<?php namespace LaravelAcl\Authentication;

use App;
use LaravelAcl\Authentication\Classes\Captcha\GregWarCaptchaValidator;
use LaravelAcl\Authentication\Classes\CustomProfile\Repository\CustomProfileRepository;
use LaravelAcl\Authentication\Commands\InstallCommand;
use LaravelAcl\Authentication\Commands\PrepareCommand;
use TestRunner;
use Config;
use LaravelAcl\Authentication\Middleware\Config as ConfigMiddleware;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use LaravelAcl\Authentication\Classes\SentryAuthenticator;
use LaravelAcl\Authentication\Helpers\SentryAuthenticationHelper;
use LaravelAcl\Authentication\Repository\EloquentPermissionRepository;
use LaravelAcl\Authentication\Repository\EloquentUserProfileRepository;
use LaravelAcl\Authentication\Repository\SentryGroupRepository;
use LaravelAcl\Authentication\Repository\SentryUserRepository;
use LaravelAcl\Authentication\Services\UserRegisterService;

class AuthenticationServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @override
     * @return void
     */
    public function register()
    {
        $this->loadOtherProviders();
        $this->registerAliases();
    }

    /**
     * @override
     */
    public function boot()
    {
        $this->bindClasses();

        // setup views path
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'laravel-authentication-acl');
        // include filters
        require __DIR__ . "/filters.php";
        // include view composers
        require __DIR__ . "/composers.php";
        // include event subscribers
        require __DIR__ . "/subscribers.php";
        // include custom validators
        require __DIR__ . "/validators.php";

        require __DIR__.'/../Http/routes.php';

        $this->registerCommands();

        $this->publishViews();
        $this->publishConfig();
        //        $this->setupAcceptanceTestingParams();
    }


    protected function bindClasses()
    {
        $this->app->bind('authenticator', function ()
        {
            return new SentryAuthenticator;
        });

        $this->app->bind('LaravelAcl\Authentication\Interfaces\AuthenticateInterface', function ()
        {
            return $this->app['authenticator'];
        });

        $this->app->bind('authentication_helper', function ()
        {
            return new SentryAuthenticationHelper;
        });

        $this->app->bind('user_repository', function ($app, $config = null)
        {
            return new SentryUserRepository($config);
        });

        $this->app->bind('group_repository', function ()
        {
            return new SentryGroupRepository;
        });

        $this->app->bind('permission_repository', function ()
        {
            return new EloquentPermissionRepository;
        });

        $this->app->bind('profile_repository', function ()
        {
            return new EloquentUserProfileRepository;
        });

        $this->app->bind('register_service', function ()
        {
            return new UserRegisterService;
        });

        $this->app->bind('custom_profile_repository', function ($app, $profile_id = null)
        {
            return new CustomProfileRepository($profile_id);
        });

        $this->app->bind('captcha_validator', function ($app)
        {
            return new GregWarCaptchaValidator();
        });
    }

    protected function loadOtherProviders()
    {
        $this->app->register('LaravelAcl\Library\LibraryServiceProvider');
        $this->app->register('Cartalyst\Sentry\SentryServiceProvider');
        $this->app->register('Intervention\Image\ImageServiceProvider');
        $this->registerIlluminateForm();
    }

    protected function registerAliases()
    {
        AliasLoader::getInstance()->alias("Sentry", 'Cartalyst\Sentry\Facades\Laravel\Sentry');
        AliasLoader::getInstance()->alias("Image", 'Intervention\Image\Facades\Image');
        $this->registerIlluminateFormAlias();

    }

    protected function setupConnection()
    {
        $connection = Config::get('acl_database.default');

        if($connection !== 'default')
        {
            $authenticator_conn = Config::get('acl_database.connections.' . $connection);
        } else
        {
            $connection = Config::get('database.default');
            $authenticator_conn = Config::get('database.connections.' . $connection);
        }

        Config::set('database.connections.authentication', $authenticator_conn);

        $this->setupPresenceVerifierConnection();
    }

    protected function setupPresenceVerifierConnection()
    {
        $this->app['validation.presence']->setConnection('authentication');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     * @override
     */
    public function provides()
    {
        return array();
    }

    private function registerInstallCommand()
    {
        $this->app['authentication.install'] = $this->app->share(function ($app)
        {
            return new InstallCommand;
        });

        $this->commands('authentication.install');
    }

    private function registerPrepareCommand()
    {
        $this->app['authentication.prepare'] = $this->app->share(function ($app)
        {
            return new PrepareCommand;
        });

        $this->commands('authentication.prepare');
    }

    private function registerCommands()
    {
        $this->registerInstallCommand();
        $this->registerPrepareCommand();
    }

    protected function setupAcceptanceTestingParams()
    {
        if(App::environment() == 'testing-acceptance')
        {
            $this->useMiddlewareCustomConfig();
        }
    }

    protected function useMiddlewareCustomConfig()
    {
        App::instance('config', new ConfigMiddleware());

        Config::swap(new ConfigMiddleware());
    }

    protected function registerIlluminateForm()
    {
        $this->app->register('Illuminate\Html\HtmlServiceProvider');
    }

    protected function registerIlluminateFormAlias()
    {
        AliasLoader::getInstance()->alias('Form', 'Illuminate\Html\FormFacade');
        AliasLoader::getInstance()->alias('HTML', 'Illuminate\Html\HtmlFacade');
    }

    protected function publishViews()
    {
        $this->publishes([
                                 __DIR__.'/../../resources/views' => public_path('packages/jacopo/laravel-authentication-acl'),
                         ]);
    }

    protected function publishConfig()
    {
        $this->publishes([
                                 __DIR__.'/../../config/acl_base.php' => config_path('acl_base.php'),
                                 __DIR__.'/../../config/acl_menu.php' => config_path('acl_menu.php'),
                                 __DIR__.'/../../config/acl_menu.php' => config_path('acl_menu.php'),
                                 __DIR__.'/../../config/acl_permissions.php' => config_path('acl_permissions.php'),
                         ]);
    }
}