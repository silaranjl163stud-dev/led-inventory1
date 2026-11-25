<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $inventoryStatus = DB::selectOne("SHOW COLUMNS FROM inventory_items LIKE 'status'");
        if ($inventoryStatus && !str_contains($inventoryStatus->Type, "'sold'")) {
            DB::statement("
                ALTER TABLE inventory_items
                MODIFY COLUMN status ENUM('available','installed','deployed','defective','under_repair','retired','sold')
                NOT NULL DEFAULT 'available'
            ");
        }

        if (Schema::hasTable('stock_movements')) {
            $movementType = DB::selectOne("SHOW COLUMNS FROM stock_movements LIKE 'movement_type'");
            if ($movementType && !str_contains($movementType->Type, "'sale'")) {
                DB::statement("
                    ALTER TABLE stock_movements
                    MODIFY COLUMN movement_type ENUM('receive','transfer','deploy','return','repair_send','repair_return','retire','sale')
                    NOT NULL
                ");
            }
        }

        if (Schema::hasTable('inventory_logs')) {
            $action = DB::selectOne("SHOW COLUMNS FROM inventory_logs LIKE 'action'");
            if ($action && !str_contains($action->Type, "'sale'")) {
                DB::statement("
                    ALTER TABLE inventory_logs
                    MODIFY COLUMN action ENUM('create','status_change','repair','transfer','deploy','return','retire','sale')
                    NOT NULL
                ");
            }
        }

        DB::statement("
            INSERT INTO status_transitions (from_status, to_status)
            VALUES ('available', 'sold')
            ON DUPLICATE KEY UPDATE from_status = from_status
        ");

        DB::unprepared("
            CREATE TABLE IF NOT EXISTS customer_order_requests (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                request_number VARCHAR(32) NULL UNIQUE,
                customer_id BIGINT NOT NULL,
                status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
                admin_notes TEXT NULL,
                reviewed_by BIGINT NULL,
                reviewed_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_customer_orders_customer (customer_id),
                INDEX idx_customer_orders_status (status),
                INDEX idx_customer_orders_created (created_at)
            ) ENGINE=InnoDB
        ");
        DB::unprepared("
            CREATE TABLE IF NOT EXISTS customer_order_items (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                order_id BIGINT NOT NULL,
                model_id BIGINT NOT NULL,
                quantity INT UNSIGNED NOT NULL,
                FOREIGN KEY (order_id) REFERENCES customer_order_requests(id) ON DELETE CASCADE,
                FOREIGN KEY (model_id) REFERENCES item_models(id) ON DELETE RESTRICT,
                UNIQUE KEY uq_customer_order_model (order_id, model_id),
                INDEX idx_customer_order_items_model (model_id)
            ) ENGINE=InnoDB
        ");
        DB::unprepared("
            CREATE TABLE IF NOT EXISTS customer_order_allocations (
                order_item_id BIGINT NOT NULL,
                inventory_item_id BIGINT NOT NULL,
                PRIMARY KEY (order_item_id, inventory_item_id),
                UNIQUE KEY uq_customer_order_inventory_item (inventory_item_id),
                FOREIGN KEY (order_item_id) REFERENCES customer_order_items(id) ON DELETE CASCADE,
                FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
        ");
    }

    public function down(): void
    {
        // Keep order history and sold-item records on rollback.
    }
};
