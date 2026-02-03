<?php

namespace Karnoweb\Hr\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Karnoweb\Hr\Hr
 */
class Hr extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'hr';
    }
}
