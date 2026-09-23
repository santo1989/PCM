<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'expense_calculations' => [
            'ec_date_id_idx' => ['date', 'id'],
            'ec_name_idx' => ['name'],
            'ec_cat_date_idx' => ['category_id', 'date'],
            'ec_updated_idx' => ['updated_at'],
        ],
        'hand_cashes' => [
            'hc_date_id_idx' => ['date', 'id'],
            'hc_name_idx' => ['name'],
            'hc_rules_types_date_idx' => ['rules', 'types', 'date'],
            'hc_updated_idx' => ['updated_at'],
        ],
        'categories' => [
            'cat_name_idx' => ['name'],
            'cat_types_idx' => ['types'],
        ],
        'users' => [
            'users_name_idx' => ['name'],
            'users_role_idx' => ['role_id'],
        ],
    ];

    public function up()
    {
        foreach ($this->indexes as $table => $defs) {
            foreach ($defs as $name => $cols) {
                if (!Schema::hasTable($table) || count(array_filter($cols, fn ($c) => Schema::hasColumn($table, $c))) !== count($cols)) {
                    continue;
                }
                try {
                    Schema::table($table, fn (Blueprint $t) => $t->index($cols, $name));
                } catch (\Throwable $e) {
                    // index already exists / unsupported column type — skip
                }
            }
        }
    }

    public function down()
    {
        foreach ($this->indexes as $table => $defs) {
            foreach (array_keys($defs) as $name) {
                try {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                } catch (\Throwable $e) {
                }
            }
        }
    }
};
