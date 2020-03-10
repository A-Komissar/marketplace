<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Validator::extend('recaptcha', 'App\\Validators\\ReCaptcha@validate');
        // URL::forceScheme('https');

        /** DB log
        DB::listen(function ($query) {
            if ($query->time >= 100) {
                $log = ['QUERY' => $query->sql, 'TIME' => $query->time];
                $dbLog = new Logger('DB');
                $dbLog->pushHandler(new StreamHandler(storage_path('logs/DB-'.Carbon::now()->toDateString().'.log')), Logger::INFO);
                $dbLog->info('DBLog', $log);
            }
        }); 
        */
    }
}
