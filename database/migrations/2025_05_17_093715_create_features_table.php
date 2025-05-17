<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();

            // created_id → users.id
            $table->foreignId('created_id')->index();
            $table->foreign('created_id', 'features_created_id_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // course_id → courses.id
            $table->foreignId('course_id')->index();
            $table->foreign('course_id', 'features_course_id_foreign')
                  ->references('id')
                  ->on('courses')
                  ->onDelete('cascade');

            $table->text('name');
            $table->tinyInteger('status')
                  ->default(config('constants.statuses.APPROVED'))
                  ->nullable()
                  ->comment('1 = Pending, 2 = Approved, 3 = Rejected');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
