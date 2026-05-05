<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('channel_id');
            $table->string('title', 200);
            // Oracle: VARCHAR2 max 4000 bytes; use longText() → CLOB for long synopsis
            $table->longText('synopsis')->nullable();
            $table->unsignedSmallInteger('duration_min');
            $table->timestamps();

            $table->foreign('channel_id', 'fk_programs_channel')
                ->references('id')->on('channels')
                ->onDelete('cascade');

            $table->index('channel_id', 'idx_programs_channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
