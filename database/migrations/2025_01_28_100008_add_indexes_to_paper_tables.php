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
        // Add indexes to papers table
        Schema::table('papers', function (Blueprint $table) {
            $table->index('category_id', 'idx_papers_category_id');
            $table->index('format_id', 'idx_papers_format_id');
            $table->index('school_id', 'idx_papers_school_id');
            $table->index('status', 'idx_papers_status');
            $table->unique('slug', 'idx_papers_slug_unique');
        });

        // Add indexes to paper_questions table
        Schema::table('paper_questions', function (Blueprint $table) {
            $table->index(['paper_id', 'order'], 'idx_paper_questions_paper_order');
            $table->index('status', 'idx_paper_questions_status');
        });

        // Add indexes to paper_options table
        Schema::table('paper_options', function (Blueprint $table) {
            $table->index(['paper_question_id', 'order'], 'idx_paper_options_question_order');
            $table->index('status', 'idx_paper_options_status');
        });

        // Add indexes to paper_answers table
        Schema::table('paper_answers', function (Blueprint $table) {
            $table->index('paper_option_id', 'idx_paper_answers_option_id');
        });

        // Add indexes to paper_purchases table
        Schema::table('paper_purchases', function (Blueprint $table) {
            $table->index(['user_id', 'paper_id'], 'idx_paper_purchases_user_paper');
            $table->index(['student_id', 'paper_id'], 'idx_paper_purchases_student_paper');
            $table->index('status', 'idx_paper_purchases_status');
            $table->index('payment_status', 'idx_paper_purchases_payment_status');
            $table->index('stripe_session_id', 'idx_paper_purchases_stripe_session');
            // Add unique constraint to prevent duplicate purchases
            $table->unique(['user_id', 'paper_id', 'student_id'], 'unique_paper_purchase');
        });

        // Add indexes to paper_user_answers table
        Schema::table('paper_user_answers', function (Blueprint $table) {
            $table->index(['paper_purchase_id', 'paper_question_id'], 'idx_user_answers_purchase_question');
            $table->index('paper_option_id', 'idx_user_answers_option_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes from papers table
        Schema::table('papers', function (Blueprint $table) {
            $table->dropIndex('idx_papers_category_id');
            $table->dropIndex('idx_papers_format_id');
            $table->dropIndex('idx_papers_school_id');
            $table->dropIndex('idx_papers_status');
            $table->dropUnique('idx_papers_slug_unique');
        });

        // Remove indexes from paper_questions table
        Schema::table('paper_questions', function (Blueprint $table) {
            $table->dropIndex('idx_paper_questions_paper_order');
            $table->dropIndex('idx_paper_questions_status');
        });

        // Remove indexes from paper_options table
        Schema::table('paper_options', function (Blueprint $table) {
            $table->dropIndex('idx_paper_options_question_order');
            $table->dropIndex('idx_paper_options_status');
        });

        // Remove indexes from paper_answers table
        Schema::table('paper_answers', function (Blueprint $table) {
            $table->dropIndex('idx_paper_answers_option_id');
        });

        // Remove indexes from paper_purchases table
        Schema::table('paper_purchases', function (Blueprint $table) {
            $table->dropIndex('idx_paper_purchases_user_paper');
            $table->dropIndex('idx_paper_purchases_student_paper');
            $table->dropIndex('idx_paper_purchases_status');
            $table->dropIndex('idx_paper_purchases_payment_status');
            $table->dropIndex('idx_paper_purchases_stripe_session');
            $table->dropUnique('unique_paper_purchase');
        });

        // Remove indexes from paper_user_answers table
        Schema::table('paper_user_answers', function (Blueprint $table) {
            $table->dropIndex('idx_user_answers_purchase_question');
            $table->dropIndex('idx_user_answers_option_id');
        });
    }
};







