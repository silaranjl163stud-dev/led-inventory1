<?php

use App\Support\SqlScript;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $schemaFile = base_path('schema.sql');
        if (!is_file($schemaFile)) {
            throw new RuntimeException('schema.sql is required to create the inventory schema.');
        }

        $sql = file_get_contents($schemaFile);
        if ($sql === false) {
            throw new RuntimeException('schema.sql could not be read.');
        }

        $sql = preg_replace('/^\s*CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS[^;]*;/mi', '', $sql) ?? $sql;
        $sql = preg_replace('/^\s*USE\s+[^;]+;/mi', '', $sql) ?? $sql;

        foreach (SqlScript::statements($sql) as $statement) {
            DB::unprepared($statement);
        }
    }

    public function down(): void
    {
        // The inventory schema may contain existing user data; rollback never drops it.
    }
};
