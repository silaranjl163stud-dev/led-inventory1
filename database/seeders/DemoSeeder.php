<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Support\SqlScript;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('inventory_items')) {
            $this->command?->error('Import schema.sql before running seeders.');
            return;
        }

        $sqlFile = base_path('seed.sql');
        if (!is_file($sqlFile)) {
            $this->command?->error('seed.sql was not found.');
            return;
        }

        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            $this->command?->error('seed.sql could not be read.');
            return;
        }

        $tables = [
            'customer_order_allocations',
            'customer_order_items',
            'customer_order_requests',
            'inventory_logs',
            'stock_movements',
            'qr_scan_logs',
            'repair_actions',
            'repairs',
            'deployment_items',
            'deployments',
            'maintenance_schedules',
            'asset_hierarchy',
            'warranties',
            'spec_modules',
            'spec_power_supplies',
            'spec_cabinets',
            'spec_video_processors',
            'inventory_items',
            'purchase_orders',
            'suppliers',
            'cabinet_templates',
            'model_compatibility',
            'item_models',
            'manufacturers',
            'custom_fields',
            'locations',
            'users',
        ];

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            DB::transaction(function () use ($sql): void {
                foreach ($this->sqlStatements($sql) as $statement) {
                    if (preg_match('/^\s*(START TRANSACTION|COMMIT)\b/i', $statement)) {
                        continue;
                    }

                    DB::unprepared($statement);
                }
            });

            DB::table('users')->updateOrInsert(
                ['username' => 'admin'],
                [
                    'password_hash' => Hash::make('admin123'),
                    'full_name' => 'System Admin',
                    'email' => 'admin@example.com',
                    'role' => 'admin',
                    'is_active' => true,
                    'deleted_at' => null,
                ]
            );
            $this->call(CustomerSeeder::class);
        } catch (Throwable $exception) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            throw $exception;
        }

        $this->command?->info('Full demo dataset loaded across the entire system.');
    }

    private function sqlStatements(string $sql): array
    {
        $sql = preg_replace('/^USE\s+[^;]+;/mi', '', $sql) ?? $sql;

        return SqlScript::statements($sql);
    }
}
