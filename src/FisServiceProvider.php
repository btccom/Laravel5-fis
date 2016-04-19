<?php

namespace BTCCOM\Fis;

use Illuminate\Support\ServiceProvider;

class FisServiceProvider extends ServiceProvider {
    public function register() {

    }

    public function boot() {
        $this->publishes([
            __DIR__ . '/config/fis.php' => config_path('fis.php'),
            __DIR__ . '/FisReplacer.php' => app_path('/Http/Middleware/FisReplacer.php'),
        ]);
    }
}