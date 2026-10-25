<?php

namespace App\Services;

use App\Support\AppSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class BackupService
{
    public const FORMAT = 'spendwise-backup';

    public const VERSION = 1;

    private const TABLES = [
        'accounts',
        'categories',
        'app_settings',
        'budgets',
        'category_budgets',
        'transactions',
        'tags',
        'tag_transaction',
        'savings_goals',
        'savings_contributions',
        'recurring_transactions',
        'recurring_occurrences',
        'app_notifications',
    ];

    private const DELETE_ORDER = [
        'app_notifications',
        'tag_transaction',
        'tags',
        'recurring_occurrences',
        'recurring_transactions',
        'savings_contributions',
        'savings_goals',
        'transactions',
        'category_budgets',
        'budgets',
        'categories',
        'app_settings',
        'accounts',
    ];

    public function export(): array
    {
        $data = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'app_version' => config('nativephp.version', '1.0.0'),
            'counts' => [],
            'data' => [],
        ];

        foreach (self::TABLES as $table) {
            $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            $data['data'][$table] = $rows;
            $data['counts'][$table] = count($rows);
        }

        try {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The backup could not be encoded.', previous: $exception);
        }

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.'spendwise-backup-'.now()->format('Ymd-His').'.json';

        File::put($path, $json);

        return [
            'path' => $path,
            'filename' => basename($path),
            'mime' => 'application/json',
            'counts' => $data['counts'],
        ];
    }

    public function restore(string $json): array
    {
        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('This file is not valid JSON.', previous: $exception);
        }

        if (! is_array($payload)
            || ($payload['format'] ?? null) !== self::FORMAT
            || (int) ($payload['version'] ?? 0) !== self::VERSION
            || ! is_array($payload['data'] ?? null)) {
            throw new RuntimeException('This is not a supported SpendWise backup file.');
        }

        $tables = [];

        foreach (self::TABLES as $table) {
            $rows = $payload['data'][$table] ?? [];

            if (! is_array($rows)) {
                throw new RuntimeException('The backup contains invalid data for '.$table.'.');
            }

            if (count($rows) > 100000) {
                throw new RuntimeException('The backup is too large to restore.');
            }

            $columns = Schema::getColumnListing($table);
            $tables[$table] = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new RuntimeException('The backup contains invalid rows.');
                }

                $filtered = array_intersect_key($row, array_flip($columns));
                if ($filtered !== []) {
                    $tables[$table][] = $filtered;
                }
            }
        }

        if (count($tables['accounts']) === 0 || count($tables['app_settings']) === 0) {
            throw new RuntimeException('The backup is missing required account or settings data.');
        }

        DB::transaction(function () use ($tables) {
            foreach (self::DELETE_ORDER as $table) {
                DB::table($table)->delete();
            }

            foreach (self::TABLES as $table) {
                foreach (array_chunk($tables[$table], 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        });

        AppSettings::forget();

        return [
            'counts' => array_map('count', $tables),
        ];
    }

    public function filename(): string
    {
        return 'spendwise-backup-'.Str::slug(now()->format('Ymd His')).'.json';
    }
}
