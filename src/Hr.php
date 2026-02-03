<?php

namespace Karnoweb\Hr;

use Illuminate\Support\ServiceProvider;

class Hr
{
    public function config(string $key, mixed $default = null): mixed
    {
        return config('hr.' . $key, $default);
    }
}
