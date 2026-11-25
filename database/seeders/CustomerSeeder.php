<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->updateOrInsert(
            ['username' => 'customer.demo'],
            [
                'password_hash' => Hash::make('customer123'),
                'full_name' => 'Demo Customer',
                'email' => 'customer.demo@example.com',
                'role' => 'customer',
                'is_active' => true,
                'deleted_at' => null,
            ]
        );

        if (Schema::hasTable('repairs') && Schema::hasTable('inventory_items')) {
            $customerId = DB::table('users')->where('username', 'customer.demo')->value('id');
            $hasTicket = DB::table('repairs')->where('created_by', $customerId)->exists();
            $item = DB::table('inventory_items')
                ->where('status', 'available')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if (!$hasTicket && $item) {
                $repairId = DB::table('repairs')->insertGetId([
                    'ticket' => 'Pending',
                    'reason' => 'Demo customer repair request',
                    'item_id' => $item->id,
                    'issue_description' => 'The display has a flickering section and needs inspection.',
                    'status' => 'pending',
                    'created_by' => $customerId,
                ]);
                DB::table('repairs')->where('id', $repairId)->update([
                    'ticket' => 'RPR-' . str_pad((string) $repairId, 8, '0', STR_PAD_LEFT),
                ]);
            }
        }

        $this->command?->info('Demo customer account ready: customer.demo');
    }
}
