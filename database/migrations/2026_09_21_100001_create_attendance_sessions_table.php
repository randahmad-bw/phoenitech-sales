<?php

use App\Models\Attendance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A day can be worked in more than one sitting.
 *
 * Someone finishes at 13:00, and at 20:00 an urgent task arrives and they come
 * back. That second sitting is not the first one running late — it has its own
 * start and its own end, and it is what "extra work" means.
 *
 * Modelling it this way avoids the trap of deriving overtime from
 * `worked - expected`: lingering twenty minutes over a task inside the normal
 * day is not overtime, and should never generate a payable record. Only a
 * deliberate return is. Management asked for exactly this distinction.
 *
 * `attendances` stays the day's summary — first check-in, last check-out and
 * the total — so every existing screen, report and snapshot keeps working. The
 * sessions are the detail underneath.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();

            // 1 is the working day itself; 2 and beyond are returns.
            $table->unsignedTinyInteger('sequence')->default(1);

            /*
             * datetime, not timestamp. MariaDB gives the first NOT NULL
             * TIMESTAMP column in a table an implicit
             * `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`, so
             * every later UPDATE of the row would silently rewrite the
             * check-in to "now" — in the server's timezone, not the app's.
             *
             * DATETIME has no such magic and no timezone conversion, which is
             * what an application-controlled instant wants.
             */
            $table->datetime('check_in_at');
            $table->datetime('check_out_at')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);

            /*
             * Set from `sequence > 1` when the session opens, but stored rather
             * than derived so management can correct a mistake — someone who
             * checks out by accident and straight back in has not worked extra.
             */
            $table->boolean('is_overtime')->default(false);

            /*
             * Extra work is a fact the moment it happens; whether it is *owed*
             * is a judgement, and that is management's to make. Sessions inside
             * the normal day stay null — there is nothing to approve.
             */
            $table->string('overtime_status', 10)->nullable(); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('reviewed_at')->nullable();
            $table->string('review_note')->nullable();

            $table->string('source', 10)->default('self'); // self | manual | system
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['attendance_id', 'sequence']);
            $table->index(['is_overtime', 'overtime_status']);
        });

        // Every existing row becomes its own first session, so nothing that was
        // already recorded loses its times.
        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }

    /**
     * Turn each existing attendance row into session 1 of itself.
     */
    private function backfill(): void
    {
        Attendance::query()
            ->whereNotNull('check_in_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $now = now();

                $sessions = $rows->map(fn ($row): array => [
                    'attendance_id' => $row->id,
                    'sequence' => 1,
                    'check_in_at' => $row->check_in_at,
                    'check_out_at' => $row->check_out_at,
                    'worked_minutes' => (int) $row->worked_minutes,
                    'is_overtime' => false,
                    'overtime_status' => null,
                    'source' => $row->source ?? 'self',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($sessions !== []) {
                    DB::table('attendance_sessions')->insert($sessions);
                }
            });
    }
};
