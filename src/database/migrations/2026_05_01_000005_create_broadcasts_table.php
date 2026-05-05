<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('program_id');
            $table->unsignedBigInteger('channel_id');
            // Oracle DATE includes time; timestamp() maps to TIMESTAMP(6)
            $table->timestamp('scheduled_at');
            $table->timestamp('replay_until')->nullable();
            $table->timestamps();

            $table->foreign('program_id', 'fk_bc_program')
                ->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('channel_id', 'fk_bc_channel')
                ->references('id')->on('channels')->onDelete('cascade');

            $table->index(['channel_id', 'scheduled_at'], 'idx_bc_chan_sched');
            $table->index('scheduled_at', 'idx_bc_sched');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
