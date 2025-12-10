<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check if an index exists on a table
     */
    protected function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        $result = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$database, $table, $indexName]
        );
        return $result[0]->count > 0;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to mock_exams table
        Schema::table('mock_exams', function (Blueprint $table) {
            if (!$this->hasIndex('mock_exams', 'idx_mock_exams_category_id')) {
                $table->index('category_id', 'idx_mock_exams_category_id');
            }
            if (!$this->hasIndex('mock_exams', 'idx_mock_exams_format_id')) {
                $table->index('format_id', 'idx_mock_exams_format_id');
            }
            if (!$this->hasIndex('mock_exams', 'idx_mock_exams_school_id')) {
                $table->index('school_id', 'idx_mock_exams_school_id');
            }
            if (!$this->hasIndex('mock_exams', 'idx_mock_exams_status')) {
                $table->index('status', 'idx_mock_exams_status');
            }
            // Check for unique constraint on slug - only add if no duplicates exist
            if (!$this->hasIndex('mock_exams', 'idx_mock_exams_slug_unique')) {
                $duplicates = DB::select(
                    'SELECT slug, COUNT(*) as count FROM mock_exams WHERE slug IS NOT NULL GROUP BY slug HAVING count > 1'
                );
                if (empty($duplicates)) {
                    $table->unique('slug', 'idx_mock_exams_slug_unique');
                }
            }
        });

        // Add indexes to mock_exam_questions table
        Schema::table('mock_exam_questions', function (Blueprint $table) {
            if (!$this->hasIndex('mock_exam_questions', 'idx_mock_exam_questions_exam_order')) {
                $table->index(['mock_exam_id', 'order'], 'idx_mock_exam_questions_exam_order');
            }
            if (!$this->hasIndex('mock_exam_questions', 'idx_mock_exam_questions_status')) {
                $table->index('status', 'idx_mock_exam_questions_status');
            }
        });

        // Add indexes to mock_exam_options table
        Schema::table('mock_exam_options', function (Blueprint $table) {
            if (!$this->hasIndex('mock_exam_options', 'idx_mock_exam_options_question_order')) {
                $table->index(['mock_exam_question_id', 'order'], 'idx_mock_exam_options_question_order');
            }
            if (!$this->hasIndex('mock_exam_options', 'idx_mock_exam_options_status')) {
                $table->index('status', 'idx_mock_exam_options_status');
            }
        });

        // Add indexes to mock_exam_answers table
        Schema::table('mock_exam_answers', function (Blueprint $table) {
            if (!$this->hasIndex('mock_exam_answers', 'idx_mock_exam_answers_option_id')) {
                $table->index('mock_exam_option_id', 'idx_mock_exam_answers_option_id');
            }
        });

        // Add indexes to mock_exam_purchases table
        Schema::table('mock_exam_purchases', function (Blueprint $table) {
            if (!$this->hasIndex('mock_exam_purchases', 'idx_mock_exam_purchases_user_exam')) {
                $table->index(['user_id', 'mock_exam_id'], 'idx_mock_exam_purchases_user_exam');
            }
            if (!$this->hasIndex('mock_exam_purchases', 'idx_mock_exam_purchases_student_exam')) {
                $table->index(['student_id', 'mock_exam_id'], 'idx_mock_exam_purchases_student_exam');
            }
            if (!$this->hasIndex('mock_exam_purchases', 'idx_mock_exam_purchases_status')) {
                $table->index('status', 'idx_mock_exam_purchases_status');
            }
            if (!$this->hasIndex('mock_exam_purchases', 'idx_mock_exam_purchases_payment_status')) {
                $table->index('payment_status', 'idx_mock_exam_purchases_payment_status');
            }
            if (!$this->hasIndex('mock_exam_purchases', 'idx_mock_exam_purchases_stripe_session')) {
                $table->index('stripe_session_id', 'idx_mock_exam_purchases_stripe_session');
            }
            // Add unique constraint to prevent duplicate purchases
            if (!$this->hasIndex('mock_exam_purchases', 'unique_mock_exam_purchase')) {
                $table->unique(['user_id', 'mock_exam_id', 'student_id'], 'unique_mock_exam_purchase');
            }
        });

        // Add indexes to mock_exam_user_answers table
        Schema::table('mock_exam_user_answers', function (Blueprint $table) {
            if (!$this->hasIndex('mock_exam_user_answers', 'idx_user_answers_purchase_question')) {
                $table->index(['mock_exam_purchase_id', 'mock_exam_question_id'], 'idx_user_answers_purchase_question');
            }
            if (!$this->hasIndex('mock_exam_user_answers', 'idx_user_answers_option_id')) {
                $table->index('mock_exam_option_id', 'idx_user_answers_option_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes from mock_exams table
        Schema::table('mock_exams', function (Blueprint $table) {
            $table->dropIndex('idx_mock_exams_category_id');
            $table->dropIndex('idx_mock_exams_format_id');
            $table->dropIndex('idx_mock_exams_school_id');
            $table->dropIndex('idx_mock_exams_status');
            $table->dropUnique('idx_mock_exams_slug_unique');
        });

        // Remove indexes from mock_exam_questions table
        Schema::table('mock_exam_questions', function (Blueprint $table) {
            $table->dropIndex('idx_mock_exam_questions_exam_order');
            $table->dropIndex('idx_mock_exam_questions_status');
        });

        // Remove indexes from mock_exam_options table
        Schema::table('mock_exam_options', function (Blueprint $table) {
            $table->dropIndex('idx_mock_exam_options_question_order');
            $table->dropIndex('idx_mock_exam_options_status');
        });

        // Remove indexes from mock_exam_answers table
        Schema::table('mock_exam_answers', function (Blueprint $table) {
            $table->dropIndex('idx_mock_exam_answers_option_id');
        });

        // Remove indexes from mock_exam_purchases table
        Schema::table('mock_exam_purchases', function (Blueprint $table) {
            $table->dropIndex('idx_mock_exam_purchases_user_exam');
            $table->dropIndex('idx_mock_exam_purchases_student_exam');
            $table->dropIndex('idx_mock_exam_purchases_status');
            $table->dropIndex('idx_mock_exam_purchases_payment_status');
            $table->dropIndex('idx_mock_exam_purchases_stripe_session');
            $table->dropUnique('unique_mock_exam_purchase');
        });

        // Remove indexes from mock_exam_user_answers table
        Schema::table('mock_exam_user_answers', function (Blueprint $table) {
            $table->dropIndex('idx_user_answers_purchase_question');
            $table->dropIndex('idx_user_answers_option_id');
        });
    }
};

