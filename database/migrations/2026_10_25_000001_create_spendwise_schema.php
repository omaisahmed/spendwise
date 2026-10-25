<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('type', 30)->default('cash');
            $table->string('icon', 50)->default('wallet');
            $table->string('color', 9)->default('#635BFF');
            $table->bigInteger('opening_balance_minor')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_archived', 'sort_order']);
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('display_name', 80)->nullable();
            $table->string('currency', 3)->default('PKR');
            $table->string('locale', 12)->default('en');
            $table->string('timezone', 64)->default('Asia/Karachi');
            $table->unsignedTinyInteger('month_start_day')->default(1);
            $table->string('theme', 12)->default('system');
            $table->bigInteger('monthly_income_minor')->nullable();
            $table->boolean('onboarding_completed')->default(false);
            $table->boolean('onboarding_skipped')->default(false);
            $table->unsignedBigInteger('default_account_id')->nullable();
            $table->unsignedTinyInteger('budget_warning_threshold')->default(80);
            $table->boolean('app_lock_enabled')->default(false);
            $table->string('pin_hash')->nullable();
            $table->boolean('biometric_enabled')->default(false);
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('privacy_mode')->default(false);
            $table->string('date_format', 20)->default('d M Y');
            $table->unsignedTinyInteger('lock_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_due_check_at')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('type', 12);
            $table->string('icon', 50)->default('circle');
            $table->string('color', 9)->default('#635BFF');
            $table->boolean('is_essential')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'name']);
            $table->index(['type', 'archived_at', 'sort_order']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->default('Monthly budget');
            $table->string('month', 7);
            $table->bigInteger('amount_minor');
            $table->unsignedTinyInteger('warning_threshold')->default(80);
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique('month');
        });

        Schema::create('category_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->unsignedTinyInteger('warning_threshold')->nullable();
            $table->timestamps();

            $table->unique(['budget_id', 'category_id']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 12);
            $table->unsignedBigInteger('amount_minor');
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->string('payment_method', 30)->default('cash');
            $table->string('merchant', 160)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_refund')->default(false);
            $table->foreignId('recurring_transaction_id')->nullable();
            $table->uuid('transfer_group')->nullable();
            $table->timestamps();

            $table->index(['type', 'occurred_at']);
            $table->index(['occurred_at', 'category_id']);
            $table->index(['account_id', 'occurred_at']);
            $table->index('merchant');
        });

        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('icon', 50)->default('piggy-bank');
            $table->string('color', 9)->default('#16A878');
            $table->bigInteger('target_minor');
            $table->date('target_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('savings_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_goal_id')->constrained('savings_goals')->cascadeOnDelete();
            $table->bigInteger('amount_minor');
            $table->dateTime('occurred_at');
            $table->string('note', 200)->nullable();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['savings_goal_id', 'occurred_at']);
        });

        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 12)->default('expense');
            $table->unsignedBigInteger('amount_minor');
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('frequency', 12);
            $table->unsignedTinyInteger('anchor_day')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_due_date');
            $table->string('merchant', 160)->nullable();
            $table->text('notes')->nullable();
            $table->string('payment_method', 30)->default('cash');
            $table->boolean('reminder_enabled')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamp('last_processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_due_date']);
        });

        Schema::create('recurring_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_transaction_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            $table->string('status', 20)->default('paid');
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['recurring_transaction_id', 'due_date']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->timestamps();
        });

        Schema::create('tag_transaction', function (Blueprint $table) {
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['transaction_id', 'tag_id']);
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('body');
            $table->string('type', 30)->default('info');
            $table->json('data')->nullable();
            $table->string('dedupe_key', 160)->nullable()->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('tag_transaction');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('recurring_occurrences');
        Schema::dropIfExists('recurring_transactions');
        Schema::dropIfExists('savings_contributions');
        Schema::dropIfExists('savings_goals');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('category_budgets');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('accounts');
    }
};
