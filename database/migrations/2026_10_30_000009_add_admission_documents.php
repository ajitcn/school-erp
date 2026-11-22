<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            if (! Schema::hasColumn('admissions', 'documents')) {
                $table->json('documents')->nullable();
            }
            if (! Schema::hasColumn('admissions', 'review_notes')) {
                $table->text('review_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            if (Schema::hasColumn('admissions', 'review_notes')) {
                $table->dropColumn('review_notes');
            }
            if (Schema::hasColumn('admissions', 'documents')) {
                $table->dropColumn('documents');
            }
        });
    }
};
