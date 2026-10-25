<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $fillable = ['title', 'body', 'type', 'data', 'dedupe_key', 'read_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public static function record(string $title, string $body, string $type = 'info', array $data = [], ?string $dedupeKey = null): self
    {
        $values = ['title' => $title, 'body' => $body, 'type' => $type, 'data' => $data];

        if ($dedupeKey === null) {
            return static::query()->create($values);
        }

        return static::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            $values
        );
    }
}
