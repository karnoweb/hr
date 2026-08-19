<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class BaseModel extends Model
{
    public function getTable(): string
    {
        $prefix = (string) config('hr.tables.prefix', 'hr_');
        $table = $this->table ?? str_replace('\\', '', Str::snake(Str::plural(class_basename($this))));

        // Eloquent's newInstance() copies getTable() onto $this->table. Guard against
        // double-prefixing when that already-prefixed value is passed back in.
        if ($prefix !== '' && str_starts_with($table, $prefix)) {
            return $table;
        }

        return $prefix.$table;
    }
}
