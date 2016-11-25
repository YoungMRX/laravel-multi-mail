<?php

namespace KVZ\Laravel\SwitchableMail;

use Illuminate\Mail\TransportManager;

class MailServiceProvider extends \Illuminate\Mail\MailServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/switchable-mail.php' => config_path('switchable-mail.php'),
        ], 'switchable-mail');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/switchable-mail.php', 'switchable-mail');

        $this->registerSwiftTransport();
        $this->registerSwiftMailerManager();
        $this->registerSwiftMailer();
        $this->registerMailer();
    }

    public function registerMailer()
    {
        $this->app->singleton('mailer', function ($app) {
            // Once we have create the mailer instance, we will set a container instance
            // on the mailer. This allows us to resolve mailer classes via containers
            // for maximum testability on said classes instead of passing Closures.
            $mailer = new Mailer(
                $app['view'], $app['swift.mailer'], $app['events']
            );

            $this->setMailerDependencies($mailer, $app);

            // If a "from" address is set, we will set it on the mailer so that all mail
            // messages sent by the applications will utilize the same "from" address
            // on each one, which makes the developer's life a lot more convenient.
            $from = $app['config']['mail.from'];

            if (is_array($from) && isset($from['address'])) {
                $mailer->alwaysFrom($from['address'], $from['name']);
            }

            $to = $app['config']['mail.to'];

            if (is_array($to) && isset($to['address'])) {
                $mailer->alwaysTo($to['address'], $to['name']);
            }

            return $mailer;
        });
    }

    /**
     * Set a few dependencies on the mailer instance.
     *
     * @param  \Illuminate\Mail\Mailer  $mailer
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function setMailerDependencies($mailer, $app)
    {
        $mailer->setContainer($app);

        if ($app->bound('queue')) {
            $mailer->setQueue($app['queue']);
        }
    }

    public function registerSwiftMailerManager()
    {
        $this->app['swift.mailerManager'] = $this->app->share(function ($app) {
            return new SwiftMailerManager($app);
        });
    }

    /**
     * Register the Swift Mailer instance.
     *
     * @return void
     */
    public function registerSwiftMailer()
    {
        $this->app['swift.mailer'] = $this->app->share(function ($app) {
            return $app['swift.mailerManager']->getDefaultSwiftMailer();
        });
    }

    /**
     * Register the Swift Transport instance.
     *
     * @return void
     */
    protected function registerSwiftTransport()
    {
        $this->app['swift.transport'] = $this->app->share(function ($app) {
            return new TransportManager($app);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['mailer', 'swift.mailerManager', 'swift.transport'];
    }
}