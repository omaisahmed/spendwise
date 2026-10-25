<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_without_a_dedupe_key_are_always_created(): void
    {
        AppNotification::record('First', 'First body');
        AppNotification::record('Second', 'Second body');

        $this->assertDatabaseCount('app_notifications', 2);
    }

    public function test_notifications_with_the_same_dedupe_key_are_created_once(): void
    {
        $first = AppNotification::record('Reminder', 'Body', 'warning', [], 'due-2026-01-01');
        $second = AppNotification::record('Reminder again', 'Other body', 'warning', [], 'due-2026-01-01');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Reminder', $second->title);
        $this->assertDatabaseCount('app_notifications', 1);
    }
}
