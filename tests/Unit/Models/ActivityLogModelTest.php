<?php

namespace Tests\Unit\Models;

use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_properties_are_cast_to_array(): void
    {
        $log = ActivityLog::create([
            'event'      => 'created',
            'properties' => ['old' => ['nom' => 'A'], 'new' => ['nom' => 'B']],
        ]);

        $fresh = $log->fresh();
        $this->assertIsArray($fresh->properties);
        $this->assertArrayHasKey('old', $fresh->properties);
    }

    public function test_created_at_is_cast_to_datetime(): void
    {
        $log = ActivityLog::create(['event' => 'login']);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $log->fresh()->created_at);
    }

    public function test_event_colors_contains_all_standard_events(): void
    {
        $expected = ['created', 'updated', 'deleted', 'login', 'logout', 'registered', 'password_reset'];

        foreach ($expected as $event) {
            $this->assertArrayHasKey($event, ActivityLog::$eventColors);
        }
    }

    public function test_event_colors_map_to_valid_tailwind_color_names(): void
    {
        $valid = ['green', 'blue', 'red', 'indigo', 'gray', 'teal', 'yellow'];

        foreach (ActivityLog::$eventColors as $event => $color) {
            $this->assertContains($color, $valid, "Event '{$event}' has unknown color '{$color}'");
        }
    }

    public function test_fillable_does_not_include_created_at(): void
    {
        $fillable = (new ActivityLog())->getFillable();

        $this->assertNotContains('created_at', $fillable);
    }

    public function test_can_create_log_with_null_properties(): void
    {
        $log = ActivityLog::create(['event' => 'logout', 'properties' => null]);

        $this->assertNull($log->fresh()->properties);
    }
}
