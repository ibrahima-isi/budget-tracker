<?php

namespace App\Observers;

use App\Services\AppCache;
use Illuminate\Database\Eloquent\Model;

class FinanceCacheObserver
{
    public function saved(Model $model): void
    {
        $this->bump($model);

        $originalUserId = $model->getOriginal('user_id');
        if ($originalUserId !== $model->getAttribute('user_id')) {
            AppCache::bumpFinanceVersion((int) $originalUserId);
        }
    }

    public function deleted(Model $model): void
    {
        $this->bump($model);
    }

    private function bump(Model $model): void
    {
        AppCache::bumpFinanceVersion((int) $model->getAttribute('user_id'));
    }
}
