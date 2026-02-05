<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('text_sliders', function (Blueprint $table) {
            $table->id();
            $table->text('text', 2000);
            $table->boolean('active')->default(true);
            $table->unsignedTinyInteger('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('text_sliders');
    }
};
