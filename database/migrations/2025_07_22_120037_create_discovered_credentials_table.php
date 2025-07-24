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
        Schema::create('discovered_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');
            $table->string('ip_address');
            $table->string('service'); // ssh, ftp, rdp, smb, etc.
            $table->integer('port');
            $table->string('username');
            $table->string('password');
            $table->enum('access_level', ['guest', 'user', 'admin', 'root'])->default('user');
            $table->text('notes')->nullable();
            $table->boolean('verified')->default(true);
            $table->timestamp('discovered_at');
            $table->timestamps();
            
            $table->index(['session_id']);
            $table->index(['ip_address']);
            $table->index(['service']);
            $table->index(['access_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discovered_credentials');
    }
};
