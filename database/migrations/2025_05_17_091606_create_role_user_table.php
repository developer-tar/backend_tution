<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->foreign('user_id', 'features_user_role_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreignId('role_id')->index();
            $table->foreign('role_id', 'featuresroles_id_foreign')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
        // $user = User::find(1);
        // $user->roles()->attach(1, ['created_at' => now(),'updated_at' => now()]);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
