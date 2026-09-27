<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faltas_reglamento_evidencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('falta_reglamento_id');
            $table->string('nombre', 255);
            $table->string('mime_type', 150);
            $table->string('file_extension', 20);
            $table->binary('archivo');
            $table->timestamps();
            $table->unique(['empresa_id', 'id'], 'faltas_reglamento_evidencias_empresa_id_unique');
            $table->index(['empresa_id', 'falta_reglamento_id'], 'faltas_reglamento_evidencias_empresa_falta_index');
            $table->foreign(['empresa_id', 'falta_reglamento_id'], 'faltas_reglamento_evidencias_empresa_falta_foreign')
                ->references(['empresa_id', 'id'])->on('faltas_reglamento')->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE faltas_reglamento_evidencias MODIFY archivo LONGBLOB NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faltas_reglamento_evidencias');
    }
};
