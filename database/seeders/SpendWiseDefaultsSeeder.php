<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpendWiseDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $expenseCategories = [
            ['Food & Dining', 'utensils', '#F05B6B', false],
            ['Groceries', 'shopping-basket', '#16A878', true],
            ['Transport', 'bus', '#4395F7', false],
            ['Fuel', 'fuel', '#F2AE43', true],
            ['Shopping', 'shopping-bag', '#8B7CFF', false],
            ['Bills & Utilities', 'receipt', '#635BFF', true],
            ['Rent & Housing', 'house', '#4E46E5', true],
            ['Health & Medical', 'heart-pulse', '#F05B6B', true],
            ['Education', 'graduation-cap', '#4395F7', true],
            ['Entertainment', 'clapperboard', '#F2AE43', false],
            ['Travel', 'plane', '#8B7CFF', false],
            ['Personal Care', 'sparkles', '#16A878', false],
            ['Family', 'users', '#635BFF', true],
            ['Subscriptions', 'repeat', '#4E46E5', false],
            ['Gifts & Donations', 'gift', '#F05B6B', false],
            ['Other', 'circle-ellipsis', '#788396', false],
        ];

        $incomeCategories = [
            ['Salary', 'banknote', '#16A878'],
            ['Freelance', 'laptop', '#4395F7'],
            ['Business', 'briefcase', '#635BFF'],
            ['Investment Income', 'trending-up', '#8B7CFF'],
            ['Rental Income', 'building', '#F2AE43'],
            ['Gifts', 'gift', '#F05B6B'],
            ['Other Income', 'circle-ellipsis', '#788396'],
        ];

        $order = 0;
        foreach ($expenseCategories as [$name, $icon, $color, $essential]) {
            DB::table('categories')->updateOrInsert(
                ['type' => 'expense', 'name' => $name],
                [
                    'icon' => $icon,
                    'color' => $color,
                    'is_essential' => $essential,
                    'sort_order' => $order++,
                    'archived_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $order = 0;
        foreach ($incomeCategories as [$name, $icon, $color]) {
            DB::table('categories')->updateOrInsert(
                ['type' => 'income', 'name' => $name],
                [
                    'icon' => $icon,
                    'color' => $color,
                    'is_essential' => false,
                    'sort_order' => $order++,
                    'archived_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $cashId = DB::table('accounts')->where('name', 'Cash')->value('id');

        if (! $cashId) {
            $cashId = DB::table('accounts')->insertGetId([
                'name' => 'Cash',
                'type' => 'cash',
                'icon' => 'wallet',
                'color' => '#635BFF',
                'opening_balance_minor' => 0,
                'is_default' => true,
                'is_archived' => false,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('app_settings')->exists()) {
            DB::table('app_settings')->insert([
                'id' => 1,
                'display_name' => null,
                'currency' => 'PKR',
                'locale' => 'en',
                'timezone' => 'Asia/Karachi',
                'month_start_day' => 1,
                'theme' => 'system',
                'monthly_income_minor' => null,
                'onboarding_completed' => false,
                'onboarding_skipped' => false,
                'default_account_id' => $cashId,
                'budget_warning_threshold' => 80,
                'app_lock_enabled' => false,
                'pin_hash' => null,
                'biometric_enabled' => false,
                'notifications_enabled' => true,
                'privacy_mode' => false,
                'date_format' => 'd M Y',
                'lock_attempts' => 0,
                'locked_until' => null,
                'last_due_check_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
