<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function getUserTickets(Request $request)
    {
        $user = $request->user();
        $tickets = SupportTicket::where('user_type', get_class($user))
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    }

    public function createTicket(Request $request)
    {
        $request->validate([
            'subject' => 'required|string',
            'description' => 'required_without:message|string',
            'message' => 'required_without:description|string',
            'category' => 'required|string',
            'priority' => 'nullable|string',
            'related_delivery_id' => 'nullable|exists:deliveries,id'
        ]);

        $user = $request->user();

        // Normalize category to match DB enum
        $categoryMap = [
            'payment_issue' => 'payment',
            'account_issue' => 'account',
            'technical_issue' => 'technical',
        ];
        $category = $categoryMap[$request->category] ?? $request->category;

        $ticket = SupportTicket::create([
            'ticket_number' => 'TKT-' . strtoupper(bin2hex(random_bytes(3))),
            'user_type' => get_class($user),
            'user_id' => $user->id,
            'subject' => $request->subject,
            'description' => $request->description ?? $request->message,
            'category' => $category,
            'priority' => $request->priority ?? 'medium',
            'delivery_id' => $request->related_delivery_id,
            'status' => 'open'
        ]);

        return response()->json(['success' => true, 'data' => $ticket]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $ticket = SupportTicket::where('user_type', get_class($user))
            ->where('user_id', $user->id)
            ->with('messages')
            ->findOrFail($id);

        return response()->json($ticket);
    }

    public function sendMessage(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string',
            'attachments' => 'nullable|array'
        ]);

        $user = $request->user();
        $ticket = SupportTicket::where('user_type', get_class($user))
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $message = $ticket->messages()->create([
            'sender_type' => get_class($user),
            'sender_id' => $user->id,
            'message' => $request->message,
            'attachments' => $request->attachments
        ]);

        $ticket->touch(); // Update updated_at of ticket

        return response()->json(['success' => true, 'data' => $message]);
    }

    public function closeTicket(Request $request, $id)
    {
        $user = $request->user();
        $ticket = SupportTicket::where('user_type', get_class($user))
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

    public function createDispute(Request $request)
    {
        $request->validate([
            'delivery_id' => 'required|exists:deliveries,id',
            'reason' => 'required|string',
            'description' => 'required|string'
        ]);

        $user = $request->user();
        
        $dispute = Dispute::create([
            'delivery_id' => $request->delivery_id,
            'raised_by_type' => get_class($user),
            'raised_by_id' => $user->id,
            'category' => $request->reason,
            'description' => $request->description,
            'status' => 'open'
        ]);

        return response()->json(['success' => true, 'data' => $dispute]);
    }
}
