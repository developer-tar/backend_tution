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
        Schema::table('paper_extract_user_answers', function (Blueprint $table) {
            $table->text('answer_text')->nullable()->after('selected_option_key')->comment('For short_answer/essay type questions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paper_extract_user_answers', function (Blueprint $table) {
            $table->dropColumn('answer_text');
        });
    }
};
