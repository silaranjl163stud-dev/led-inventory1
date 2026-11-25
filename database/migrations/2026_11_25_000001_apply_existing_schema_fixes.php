<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addCustomerRole();
        $this->addRepairReviewFields();
        $this->fixActiveChildConstraint();
    }

    public function down(): void
    {
        // These schema fixes are kept on rollback to avoid losing customer data or repair history.
    }

    private function addCustomerRole(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM users LIKE 'role'");
        if ($column && !str_contains($column->Type, "'customer'")) {
            DB::statement("
                ALTER TABLE users
                MODIFY COLUMN role ENUM('admin','manager','warehouse','technician','staff','customer')
                NOT NULL DEFAULT 'customer'
            ");
        }
    }

    private function addRepairReviewFields(): void
    {
        if (!Schema::hasTable('repairs')) {
            return;
        }

        if (!Schema::hasColumn('repairs', 'ticket')) {
            DB::statement("ALTER TABLE repairs ADD COLUMN ticket VARCHAR(150) NOT NULL DEFAULT 'Repair request' AFTER id");
        }
        if (!Schema::hasColumn('repairs', 'reason')) {
            DB::statement('ALTER TABLE repairs ADD COLUMN reason TEXT NULL AFTER ticket');
        }

        $status = DB::selectOne("SHOW COLUMNS FROM repairs LIKE 'status'");
        if ($status && !str_contains($status->Type, "'pending'")) {
            DB::statement("
                ALTER TABLE repairs
                MODIFY COLUMN status ENUM('pending','accepted','declined','open','in_progress','resolved','cancelled')
                NOT NULL DEFAULT 'pending'
            ");
        }

        if (!Schema::hasColumn('repairs', 'reviewed_by')) {
            DB::statement('ALTER TABLE repairs ADD COLUMN reviewed_by BIGINT NULL AFTER created_by');
        }
        if (!Schema::hasColumn('repairs', 'reviewed_at')) {
            DB::statement('ALTER TABLE repairs ADD COLUMN reviewed_at TIMESTAMP NULL AFTER resolved_at');
        }

        $reviewForeignKey = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'repairs'
              AND COLUMN_NAME = 'reviewed_by'
              AND REFERENCED_TABLE_NAME = 'users'
            LIMIT 1
        ");
        if (!$reviewForeignKey) {
            DB::statement("
                ALTER TABLE repairs
                ADD CONSTRAINT fk_repairs_reviewed_by
                FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
            ");
        }

        DB::statement("
            UPDATE repairs
            SET ticket = CONCAT('Legacy repair #', id),
                reason = COALESCE(issue_description, 'Existing repair ticket')
            WHERE ticket = 'Repair request'
        ");
    }

    private function fixActiveChildConstraint(): void
    {
        if (!Schema::hasTable('asset_hierarchy')) {
            return;
        }

        $index = DB::selectOne("SHOW INDEX FROM asset_hierarchy WHERE Key_name = 'uq_active_child'");
        if ($index && $index->Column_name !== 'active_child_id') {
            DB::statement('ALTER TABLE asset_hierarchy DROP INDEX uq_active_child');
            $index = null;
        }

        if (!Schema::hasColumn('asset_hierarchy', 'active_child_id')) {
            DB::statement("
                ALTER TABLE asset_hierarchy
                ADD COLUMN active_child_id BIGINT
                    GENERATED ALWAYS AS (CASE WHEN status = 'installed' THEN child_id ELSE NULL END) STORED
            ");
        }

        if (!$index) {
            DB::statement('ALTER TABLE asset_hierarchy ADD UNIQUE KEY uq_active_child (active_child_id)');
        }
    }
};
