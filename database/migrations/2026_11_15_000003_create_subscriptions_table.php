<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('plan_id')
                ->constrained('plans')
                ->restrictOnDelete();

            // Values come from App\Enums\SubscriptionStatus.
            $table->string('status', 20);

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->timestamp('grace_ends_at')->nullable();

            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            // Commercial snapshot for the current period.
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->char('currency', 3);
            $table->string('billing_interval', 10);
            $table->unsignedSmallInteger('billing_interval_count')->default(1);

            // Plan change scheduled for the next renewal.
            $table->foreignId('pending_plan_id')
                ->nullable()
                ->constrained('plans')
                ->nullOnDelete();

            $table->string('provider', 30)->default('mock');
            $table->string('provider_subscription_id', 191)->nullable();

            $table->timestamp('last_renewed_at')->nullable();

            $table->timestamps();

            // Required for composite tenant foreign keys on child tables.
            $table->unique(['id', 'organization_id']);

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'current_period_end']);
            $table->index('current_period_end');
            $table->index('plan_id');

            // Multiple nulls are allowed on both SQLite and MySQL.
            $table->unique(['provider', 'provider_subscription_id']);
        });

        $this->createNonTerminalGuard();
    }

    public function down(): void
    {
        $this->dropNonTerminalGuard();

        Schema::dropIfExists('subscriptions');
    }

    /**
     * Only one non-terminal subscription may exist per organization. The
     * application locks the organization row, but this index is the database
     * level defense. Partial indexes are used where the driver supports them;
     * MySQL/MariaDB use a stored generated column instead.
     */
    private function createNonTerminalGuard(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX subscriptions_one_non_terminal_per_organization
                 ON subscriptions (organization_id)
                 WHERE status <> 'expired'"
            );

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE subscriptions
                 ADD COLUMN non_terminal_organization_id BIGINT UNSIGNED
                 GENERATED ALWAYS AS (
                     CASE WHEN status <> 'expired' THEN organization_id ELSE NULL END
                 ) STORED"
            );

            DB::statement(
                'CREATE UNIQUE INDEX subscriptions_one_non_terminal_per_organization
                 ON subscriptions (non_terminal_organization_id)'
            );
        }
    }

    private function dropNonTerminalGuard(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS subscriptions_one_non_terminal_per_organization');

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('DROP INDEX subscriptions_one_non_terminal_per_organization ON subscriptions');
            DB::statement('ALTER TABLE subscriptions DROP COLUMN non_terminal_organization_id');
        }
    }
};
