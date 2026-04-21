<?php

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic observer that logs created / updated / deleted events for any model.
 * Register it in AppServiceProvider via Model::observe(ModelActivityObserver::class).
 */
class ModelActivityObserver
{
    public function created(Model $model): void
    {
        ActivityLogger::log('created', $model, [
            'new' => ActivityLogger::snapshot($model),
        ]);
    }

    public function updated(Model $model): void
    {
        $diff = ActivityLogger::diff($model);
        if (empty($diff)) {
            return; // nothing actually changed (e.g. touch())
        }

        ActivityLogger::log('updated', $model, $diff);
    }

    public function deleted(Model $model): void
    {
        ActivityLogger::log('deleted', $model, [
            'old' => ActivityLogger::snapshot($model),
        ]);
    }
}
