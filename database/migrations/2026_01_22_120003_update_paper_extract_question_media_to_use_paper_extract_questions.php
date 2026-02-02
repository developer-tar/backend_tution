<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('paper_extract_question_media')) {
            // Check current column name
            $hasMockExamColumn = Schema::hasColumn('paper_extract_question_media', 'mock_exam_question_id');
            $hasPaperExtractColumn = Schema::hasColumn('paper_extract_question_media', 'paper_extract_question_id');

            if ($hasMockExamColumn && !$hasPaperExtractColumn) {
                // Try to drop foreign key by column name first (most reliable method)
                try {
                    Schema::table('paper_extract_question_media', function (Blueprint $table) {
                        $table->dropForeign(['mock_exam_question_id']);
                    });
                } catch (\Exception $e) {
                    // If that fails, try to get the actual constraint name and drop it
                    try {
                        $foreignKeys = DB::select("
                            SELECT CONSTRAINT_NAME 
                            FROM information_schema.KEY_COLUMN_USAGE 
                            WHERE TABLE_SCHEMA = DATABASE() 
                            AND TABLE_NAME = 'paper_extract_question_media' 
                            AND COLUMN_NAME = 'mock_exam_question_id'
                            AND REFERENCED_TABLE_NAME IS NOT NULL
                        ");

                        foreach ($foreignKeys as $fk) {
                            $constraintName = $fk->CONSTRAINT_NAME;
                            try {
                                DB::statement("ALTER TABLE `paper_extract_question_media` DROP FOREIGN KEY `{$constraintName}`");
                            } catch (\Exception $e2) {
                                // Foreign key might not exist, continue
                                \Log::warning("Could not drop foreign key: {$constraintName}", ['error' => $e2->getMessage()]);
                            }
                        }
                    } catch (\Exception $e3) {
                        // If all methods fail, log and continue - the column rename might still work
                        \Log::warning("Could not drop foreign key for mock_exam_question_id", ['error' => $e3->getMessage()]);
                    }
                }

                // Rename column
                Schema::table('paper_extract_question_media', function (Blueprint $table) {
                    $table->renameColumn('mock_exam_question_id', 'paper_extract_question_id');
                });

                // Add new foreign key
                Schema::table('paper_extract_question_media', function (Blueprint $table) {
                    $table->foreign('paper_extract_question_id')
                        ->references('id')
                        ->on('paper_extract_questions')
                        ->onDelete('cascade');
                });

                // Update index
                try {
                    Schema::table('paper_extract_question_media', function (Blueprint $table) {
                        $table->dropIndex('paper_extract_question_media_mock_exam_question_id_index');
                    });
                } catch (\Exception $e) {
                    // Index might not exist, continue
                }

                Schema::table('paper_extract_question_media', function (Blueprint $table) {
                    $table->index('paper_extract_question_id');
                });
            }

            // Migrate media data: update references from mock_exam_questions to paper_extract_questions
            // First, get all media items that reference mock_exam_questions with paper_id
            $mediaItems = DB::table('paper_extract_question_media')
                ->whereNull('deleted_at')
                ->get();

            foreach ($mediaItems as $media) {
                $oldQuestionId = $hasMockExamColumn ? $media->mock_exam_question_id : $media->paper_extract_question_id;

                // Find the mock_exam_question
                $mockQuestion = DB::table('mock_exam_questions')
                    ->where('id', $oldQuestionId)
                    ->whereNull('mock_exam_id')
                    ->whereNotNull('paper_id')
                    ->first();

                if ($mockQuestion) {
                    // Find the corresponding paper_extract_question
                    $paper = DB::table('papers')
                        ->where('id', $mockQuestion->paper_id)
                        ->where('is_extract', true)
                        ->first();

                    if ($paper) {
                        $paperExtract = DB::table('paper_extracts')
                            ->where('paper_id', $paper->id)
                            ->first();

                        if ($paperExtract) {
                            $paperExtractQuestion = DB::table('paper_extract_questions')
                                ->where('paper_extract_id', $paperExtract->id)
                                ->where('question_number', $mockQuestion->question_number)
                                ->where('page_number', $mockQuestion->page_number)
                                ->first();

                            if ($paperExtractQuestion) {
                                DB::table('paper_extract_question_media')
                                    ->where('id', $media->id)
                                    ->update(['paper_extract_question_id' => $paperExtractQuestion->id]);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('paper_extract_question_media')) {
            if (Schema::hasColumn('paper_extract_question_media', 'paper_extract_question_id')) {
                // Get foreign key name
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'paper_extract_question_media' 
                    AND COLUMN_NAME = 'paper_extract_question_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");

                // Try to drop foreign key by column name first (most reliable method)
                try {
                    Schema::table('paper_extract_question_media', function (Blueprint $table) {
                        $table->dropForeign(['paper_extract_question_id']);
                    });
                } catch (\Exception $e) {
                    // If that fails, try to get the actual constraint name and drop it
                    foreach ($foreignKeys as $fk) {
                        $constraintName = $fk->CONSTRAINT_NAME;
                        try {
                            DB::statement("ALTER TABLE `paper_extract_question_media` DROP FOREIGN KEY `{$constraintName}`");
                        } catch (\Exception $e2) {
                            // Foreign key might not exist, continue
                            \Log::warning("Could not drop foreign key: {$constraintName}", ['error' => $e2->getMessage()]);
                        }
                    }
                }

                // Rename back
                Schema::table('paper_extract_question_media', function (Blueprint $table) {
                    $table->renameColumn('paper_extract_question_id', 'mock_exam_question_id');
                });

                // Add old foreign key back
                Schema::table('paper_extract_question_media', function (Blueprint $table) {
                    $table->foreign('mock_exam_question_id')
                        ->references('id')
                        ->on('mock_exam_questions')
                        ->onDelete('cascade');
                });
            }
        }
    }
};
