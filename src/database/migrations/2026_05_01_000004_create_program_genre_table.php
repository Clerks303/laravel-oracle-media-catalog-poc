<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('program_genre', function (Blueprint $table) {
            $table->unsignedBigInteger('program_id');
            $table->unsignedBigInteger('genre_id');

            $table->primary(['program_id', 'genre_id'], 'pk_program_genre');

            $table->foreign('program_id', 'fk_pg_program')
                ->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('genre_id', 'fk_pg_genre')
                ->references('id')->on('genres')->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_genre');
    }
};
