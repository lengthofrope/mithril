<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migrate data from bilas and bila_prep_items to meetings and meeting_prep_items.
 *
 * Each bila becomes a meeting with type=one_on_one. The team_member_id on the
 * bila becomes an attendee record in meeting_attendees.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $bilas = DB::table('bilas')->get();

        foreach ($bilas as $bila) {
            $meetingId = DB::table('meetings')->insertGetId([
                'user_id' => $bila->user_id,
                'team_id' => null,
                'title' => 'Bila',
                'type' => 'one_on_one',
                'status' => $bila->is_done ? 'completed' : 'scheduled',
                'scheduled_at' => $bila->scheduled_date,
                'notes' => $bila->notes,
                'is_done' => $bila->is_done,
                'transcription_language' => 'nl',
                'created_at' => $bila->created_at,
                'updated_at' => $bila->updated_at,
            ]);

            DB::table('meeting_attendees')->insert([
                'meeting_id' => $meetingId,
                'team_member_id' => $bila->team_member_id,
                'created_at' => $bila->created_at,
                'updated_at' => $bila->updated_at,
            ]);

            $prepItems = DB::table('bila_prep_items')
                ->where('bila_id', $bila->id)
                ->get();

            foreach ($prepItems as $item) {
                DB::table('meeting_prep_items')->insert([
                    'user_id' => $item->user_id,
                    'meeting_id' => $meetingId,
                    'team_member_id' => $item->team_member_id,
                    'content' => $item->content,
                    'type' => 'agenda_item',
                    'is_discussed' => $item->is_discussed,
                    'sort_order' => $item->sort_order,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }

            DB::table('activities')
                ->where('activityable_type', 'App\\Models\\Bila')
                ->where('activityable_id', $bila->id)
                ->update([
                    'activityable_type' => 'App\\Models\\Meeting',
                    'activityable_id' => $meetingId,
                ]);

            DB::table('calendar_event_links')
                ->where('linkable_type', 'App\\Models\\Bila')
                ->where('linkable_id', $bila->id)
                ->update([
                    'linkable_type' => 'App\\Models\\Meeting',
                    'linkable_id' => $meetingId,
                ]);
        }

        // Orphaned prep items (bila_id = null) are dropped during migration.
        // The new schema requires a meeting_id FK. These floating items were
        // only used as drafts before being linked to a bila.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('meeting_prep_items')->truncate();
        DB::table('meeting_attendees')->truncate();
        DB::table('meetings')->truncate();
    }
};
