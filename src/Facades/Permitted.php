<?php

namespace Williamug\Permitted\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Williamug\Permitted\Permitted
 */
class Permitted extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Williamug\Permitted\Permitted::class;
    }
}
