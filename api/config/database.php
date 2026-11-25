<?php

use Illuminate\Support\Facades\DB;

class Database
{
    public function getConnection(): PDO
    {
        return DB::connection()->getPdo();
    }
}
