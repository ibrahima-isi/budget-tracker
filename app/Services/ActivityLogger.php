<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Fields that should never appear in logged properties,
     * regardless of model hidden settings.
     */
    private const ALWAYS_REDACT = ['password', 'remember_token', 'two_factor_secret'];

    /**
     * Record a single activity to the DB and to the rotating activity log file.
     *
     * @param  string  $event  created|updated|deleted|login|logout|registered|password_reset
     * @param  Model|null  $subject  The Eloquent model that was acted upon
     * @param  array  $properties  Extra context, e.g. ['old'=>[…], 'new'=>[…]]
     */
    public static function log(
        string $event,
        ?Model $subject = null,
        array $properties = [],
    ): void {
        $user = Auth::user();
        $request = Request::instance();
        $actor = $user ? 'user#'.$user->getAuthIdentifier() : null;

        $entry = [
            'user_id' => $user?->id,
            'user_name' => $actor,
            'event' => $event,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject ? static::labelFor($subject) : null,
            'properties' => empty($properties) ? null : $properties,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ];

        // ── 1. Persist to database ─────────────────────────────────────────────
        ActivityLog::create($entry);

        // ── 2. Mirror to the daily rotating activity log file ─────────────────
        Log::channel('activity')->info($event, array_filter([
            'user' => $actor ?? 'guest',
            'subject' => $entry['subject_type'] ? "{$entry['subject_type']}#{$entry['subject_id']}" : null,
            'label' => $entry['subject_label'],
            'ip' => $entry['ip_address'],
            'props' => $entry['properties'],
        ]));
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Extract a human-readable label from a model instance.
     * Tries common naming attributes in order.
     */
    public static function labelFor(Model $model): string
    {
        if ($model instanceof User) {
            return $model->getKey() ? 'user#'.$model->getKey() : 'user';
        }

        foreach (['label', 'source', 'name', 'title', 'code'] as $attr) {
            if (! empty($model->{$attr})) {
                return (string) $model->{$attr};
            }
        }

        return '#'.$model->getKey();
    }

    /**
     * Return a model's attributes stripped of sensitive / redundant fields.
     */
    public static function sanitize(Model $model, array $attributes): array
    {
        $redact = array_merge(static::ALWAYS_REDACT, $model->getHidden());

        if ($model instanceof User) {
            $redact = array_merge($redact, ['name', 'email', 'email_hash']);
        }

        return array_diff_key($attributes, array_flip($redact));
    }

    /**
     * Snapshot all non-sensitive attributes of a model.
     */
    public static function snapshot(Model $model): array
    {
        return static::sanitize($model, $model->getAttributes());
    }

    /**
     * Return only the changed attributes (before → after) for an update event.
     */
    public static function diff(Model $model): array
    {
        $dirty = $model->getDirty();
        if (empty($dirty)) {
            return [];
        }

        $old = static::sanitize($model, array_intersect_key($model->getOriginal(), $dirty));
        $new = static::sanitize($model, $dirty);

        return compact('old', 'new');
    }
}
