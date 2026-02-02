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
        // Check if paper_extracts table exists
        if (!Schema::hasTable('paper_extracts')) {
            // Table doesn't exist, skip migration
            return;
        }

        // Migrate data from papers table (where is_extract = true) to paper_extracts
        $extractedPapers = DB::table('papers')
            ->where('is_extract', true)
            ->whereNull('deleted_at')
            ->get();

        foreach ($extractedPapers as $paper) {
            // Only insert columns that exist in paper_extracts table
            $paperExtractData = [
                'paper_id' => $paper->id,
                'title' => property_exists($paper, 'name') ? $paper->name : null,
                'description' => property_exists($paper, 'description') ? $paper->description : null,
                'source_file_path' => property_exists($paper, 'source_file_path') ? $paper->source_file_path : null,
                'extracted_file_path' => property_exists($paper, 'extracted_file_path') ? $paper->extracted_file_path : null,
                'extraction_status' => property_exists($paper, 'extraction_status') ? ($paper->extraction_status ?? 'pending') : 'pending',
                'extraction_error' => property_exists($paper, 'extraction_error') ? $paper->extraction_error : null,
                'metadata' => property_exists($paper, 'metadata') ? $paper->metadata : null,
                'total_questions' => property_exists($paper, 'total_questions') ? ($paper->total_questions ?? 0) : 0,
                'total_pages' => property_exists($paper, 'total_pages') ? $paper->total_pages : null,
                'subject' => property_exists($paper, 'subject') ? $paper->subject : null,
                'exam_board' => property_exists($paper, 'exam_board') ? $paper->exam_board : null,
                'year' => property_exists($paper, 'year') ? $paper->year : null,
                'level' => property_exists($paper, 'level') ? $paper->level : null,
                'status' => property_exists($paper, 'status') ? $paper->status : config('constants.statuses.APPROVED'),
                'created_by' => property_exists($paper, 'created_by') ? $paper->created_by : null,
                'created_at' => property_exists($paper, 'created_at') ? $paper->created_at : now(),
                'updated_at' => property_exists($paper, 'updated_at') ? $paper->updated_at : now(),
            ];

            $paperExtractId = DB::table('paper_extracts')->insertGetId($paperExtractData);

            // Migrate questions from mock_exam_questions (where paper_id is set and mock_exam_id is null)
            // Only if paper_extract_questions table exists
            if (Schema::hasTable('paper_extract_questions')) {
                $questions = DB::table('mock_exam_questions')
                    ->where('paper_id', $paper->id)
                    ->whereNull('mock_exam_id')
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($questions as $question) {
                    $questionData = [
                        'paper_extract_id' => $paperExtractId,
                        'question_number' => property_exists($question, 'question_number') ? $question->question_number : 0,
                        'question_text' => property_exists($question, 'question_text') ? $question->question_text : '',
                        'question_text_clean' => property_exists($question, 'question_text_clean') ? $question->question_text_clean : null,
                        'question_type' => property_exists($question, 'question_type') ? $question->question_type : null,
                        'marks' => property_exists($question, 'marks') ? ($question->marks ?? 1) : 1,
                        'page_number' => property_exists($question, 'page_number') ? $question->page_number : null,
                        'options' => property_exists($question, 'options') ? $question->options : null,
                        'correct_answer' => property_exists($question, 'correct_answer') ? $question->correct_answer : null,
                        'answer_explanation' => property_exists($question, 'answer_explanation') ? $question->answer_explanation : null,
                        'topic' => property_exists($question, 'topic') ? $question->topic : null,
                        'subtopic' => property_exists($question, 'subtopic') ? $question->subtopic : null,
                        'keywords' => property_exists($question, 'keywords') ? $question->keywords : null,
                        'metadata' => property_exists($question, 'metadata') ? $question->metadata : null,
                        'order' => property_exists($question, 'order') ? ($question->order ?? 0) : 0,
                        'status' => property_exists($question, 'status') ? ($question->status ?? config('constants.statuses.APPROVED')) : config('constants.statuses.APPROVED'),
                        'created_at' => property_exists($question, 'created_at') ? $question->created_at : now(),
                        'updated_at' => property_exists($question, 'updated_at') ? $question->updated_at : now(),
                    ];

                    // Only add section if it exists in the table (added in later migration)
                    if (Schema::hasColumn('paper_extract_questions', 'section')) {
                        $questionData['section'] = property_exists($question, 'section') ? $question->section : null;
                    }

                    DB::table('paper_extract_questions')->insert($questionData);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clear the migrated data (optional - be careful with this)
        DB::table('paper_extract_questions')->truncate();
        DB::table('paper_extracts')->truncate();
    }
};
