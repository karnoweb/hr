<?php

namespace Karnoweb\Hr\Support;

use Illuminate\Support\Facades\DB;

/**
 * Dispatches accounting boundary events after DB commit in production.
 */
final class AccountingEventDispatcher
{
    public static function dispatch(object $event): void
    {
        if (config('hr.accounting.dispatch_after_commit', true)) {
            DB::afterCommit(static fn () => event($event));

            return;
        }

        event($event);
    }
}
