<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Plain-language explanations per report and analytics run (AI or template).
    public function up()
    {
        Schema::create('analytics_insights', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 20)->index();
            $table->string('report', 30);
            $table->string('source', 10); // gemini | groq | template
            $table->string('model', 60)->nullable();
            $table->json('metrics'); // exactly what the explanation was built from
            $table->json('insights');
            $table->json('actions');
            $table->string('rejected')->nullable(); // why an AI answer was replaced by the template
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('analytics_insights');
    }
};
