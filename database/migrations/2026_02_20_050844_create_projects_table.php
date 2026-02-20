<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('projects', function (Blueprint $table) {
        $table->id();
        $table->foreignId('entity_id')->constrained()->onDelete('cascade');

        $table->string('project_number')->unique();
        $table->string('project_name');
        $table->string('client_name')->nullable();
        $table->string('consultant')->nullable();
        $table->string('project_manager')->nullable();
        $table->string('document_controller')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
