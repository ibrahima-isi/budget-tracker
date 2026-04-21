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
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('user_name',     'like', "%{$term}%")
                  ->orWhere('subject_label', 'like', "%{$term}%")
                  ->orWhere('ip_address',    'like', "%{$term}%");
            });
        }

        $logs = $query->paginate(50)->withQueryString();

        return Inertia::render('ActivityLogs/Index', [
            'logs'         => $logs,
            'filters'      => $request->only('event', 'subject_type', 'user_id', 'search'),
            'eventColors'  => ActivityLog::$eventColors,
            'eventOptions' => array_keys(ActivityLog::$eventColors),
            'subjectOptions' => ActivityLog::distinct()->whereNotNull('subject_type')
                ->pluck('subject_type')
                ->sort()
                ->values(),
        ]);
    }
}
