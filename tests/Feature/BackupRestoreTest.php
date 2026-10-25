<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\Concerns\InteractsWithSpendWise;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use InteractsWithSpendWise;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSpendWiseWorkspace();
    }

    public function test_export_and_restore_round_trip_all_workspace_data(): void
    {
        $account = $this->defaultAccount();
        $category = $this->category('expense', 'Food & Dining');
        $account->forceFill(['name' => 'Wallet'])->save();

        $transaction = Transaction::query()->create([
            'type' => 'expense',
            'amount_minor' => 15000,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'occurred_at' => now(),
            'payment_method' => 'cash',
            'merchant' => 'Original merchant',
        ]);

        $goal = SavingsGoal::query()->create([
            'name' => 'Holiday',
            'icon' => 'plane',
            'color' => '#4395F7',
            'target_minor' => 100000,
            'status' => 'active',
        ]);

        $backups = app(BackupService::class);
        $file = $backups->export();

        try {
            $this->assertFileExists($file['path']);

            $transaction->delete();
            $goal->delete();
            $account->forceFill(['name' => 'Changed after export'])->save();

            $result = $backups->restore(File::get($file['path']));

            $this->assertSame(1, $result['counts']['transactions']);
            $this->assertDatabaseHas('transactions', ['merchant' => 'Original merchant']);
            $this->assertDatabaseHas('savings_goals', ['name' => 'Holiday']);
            $this->assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'Wallet']);
        } finally {
            File::delete($file['path']);
        }
    }

    public function test_restore_rejects_an_unsupported_backup_format(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This is not a supported SpendWise backup file.');

        app(BackupService::class)->restore(json_encode([
            'format' => 'something-else',
            'version' => 1,
            'data' => [],
        ], JSON_THROW_ON_ERROR));
    }
}
