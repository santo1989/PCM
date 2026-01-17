<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToHandCashes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hand_cashes', function (Blueprint $table) {
            if (Schema::hasColumn('hand_cashes', 'date')) {
                $table->index('date');
            }
            if (Schema::hasColumn('hand_cashes', 'rules')) {
                $table->index('rules');
            }
            if (Schema::hasColumn('hand_cashes', 'types')) {
                $table->index('types');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hand_cashes', function (Blueprint $table) {
            if (Schema::hasColumn('hand_cashes', 'date')) {
                $table->dropIndex(['date']);
            }
            if (Schema::hasColumn('hand_cashes', 'rules')) {
                $table->dropIndex(['rules']);
            }
            if (Schema::hasColumn('hand_cashes', 'types')) {
                $table->dropIndex(['types']);
            }
        });
    }
}
