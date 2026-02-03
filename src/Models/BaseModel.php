<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    public function getTable(): string
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        $table = $this->table ?? str_replace('\\', '', \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural(class_basename($this))));

        return $prefix . $table;
    }
}
