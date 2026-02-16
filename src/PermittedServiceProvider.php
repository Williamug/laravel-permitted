<?php

namespace Williamug\Permitted;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Williamug\Permitted\Commands\PermittedCommand;

class PermittedServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-permitted')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_permitted_table')
            ->hasCommand(PermittedCommand::class);
    }
}
