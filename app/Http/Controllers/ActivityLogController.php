<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::query()->latest('created_at');

        // Optional filters
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('search')) {
            // Escape LIKE wildcards to prevent wildcard injection.
            // Use ESCAPE '\' syntax (supported by MySQL, PostgreSQL, and SQLite).
            $term = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $request->input('search')) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw("user_name LIKE ? ESCAPE '\\'",      [$term])
                  ->orWhereRaw("subject_label LIKE ? ESCAPE '\\'", [$term])
                  ->orWhereRaw("ip_address LIKE ? ESCAPE '\\'",    [$term]);
            });
        }

        $logs = $query->paginate(50)->withQueryString();

        $subjectOptions = cache()->remember('activity_log_subject_types', 300, fn () =>
            ActivityLog::distinct()->whereNotNull('subject_type')
                ->pluck('subject_type')
                ->sort()
                ->values()
        );

        return Inertia::render('ActivityLogs/Index', [
            'logs'           => $logs,
            'filters'        => $request->only('event', 'subject_type', 'user_id', 'search'),
            'eventColors'    => ActivityLog::$eventColors,
            'eventOptions'   => array_keys(ActivityLog::$eventColors),
            'subjectOptions' => $subjectOptions,
        ]);
    }
}
