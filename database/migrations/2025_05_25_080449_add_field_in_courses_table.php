<?php

use App\Models\Course;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add the slug column first
        Schema::table('courses', function (Blueprint $table) {
            $table->string('slug')->after('name')->nullable();
        });

        // Populate slug for existing rows
        $courses = Course::select('id', 'name')->whereNull('slug')->get();

        if ($courses->isNotEmpty()) {
            foreach ($courses as $course) {
                $slug = Str::slug($course->name);

                // Ensure slug is unique
                $originalSlug = $slug;
                $counter = 1;
                while (Course::where('slug', $slug)->exists()) {
                    $slug = $originalSlug . '-' . $counter++;
                }

                $course->update(['slug' => $slug]);
            }
        }

        // Finally, make the column non-nullable & unique (optional)
        Schema::table('courses', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
