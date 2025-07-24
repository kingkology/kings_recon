<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ip_scans', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->index();
            $table->string('ip_address');
            $table->boolean('is_online')->default(false);
            $table->integer('ping_time')->nullable();
            $table->json('open_ports')->nullable();
            $table->json('vulnerable_ports')->nullable();
            $table->text('scan_details')->nullable();
            $table->enum('status', ['pending', 'scanning', 'completed', 'failed'])->default('pending');
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
            
            $table->index(['batch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_scans');
    }
};
