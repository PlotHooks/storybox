// database/migrations/xxxx_xx_xx_xxxxxx_create_room_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('room_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
            $table->index(['user_id', 'room_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('room_users');
    }
};
