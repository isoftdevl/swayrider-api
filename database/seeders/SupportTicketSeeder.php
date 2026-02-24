<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Rider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupportTicketSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $riders = Rider::all();

        // Define valid categories (adjust these to match your migration ENUM values)
        $categories = ['general', 'delivery_issue', 'payment_issue', 'technical', 'account'];
        $statuses = ['open', 'in_progress', 'resolved', 'closed'];
        $priorities = ['low', 'medium', 'high', 'urgent'];

        // Create random tickets for users
        if ($users->isNotEmpty()) {
            for ($i = 0; $i < 10; $i++) {
                $user = $users->random();
                SupportTicket::create([
                    'user_type' => User::class,
                    'user_id' => $user->id,
                    'ticket_number' => 'TKT-' . strtoupper(Str::random(8)),
                    'subject' => 'Issue with delivery',
                    'description' => 'I have an issue with my recent delivery...',
                    'status' => collect($statuses)->random(),
                    'priority' => collect($priorities)->random(),
                    'category' => collect($categories)->random(),
                ]);
            }
        }

        // Create random tickets for riders
        if ($riders->isNotEmpty()) {
            for ($i = 0; $i < 10; $i++) {
                $rider = $riders->random();
                SupportTicket::create([
                    'user_type' => Rider::class,
                    'user_id' => $rider->id,
                    'ticket_number' => 'TKT-' . strtoupper(Str::random(8)),
                    'subject' => 'Issue with my payout',
                    'description' => 'I have not received my payout for last week...',
                    'status' => collect($statuses)->random(),
                    'priority' => collect($priorities)->random(),
                    'category' => collect($categories)->random(),
                ]);
            }
        }
    }
}