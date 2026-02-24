<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    public function dashboard(Request $request)
    {
        $company = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_riders' => $company->riders()->count(),
                'active_riders' => $company->riders()->where('status', 'active')->count(),
                'wallet_balance' => $company->wallet ? $company->wallet->balance : 0,
                'total_earnings' => $company->wallet ? $company->wallet->total_credited : 0,
            ]
        ]);
    }

    public function inviteRider(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        
        $company = $request->user();
        $token = Str::random(32);

        $invitation = CompanyInvitation::create([
            'company_id' => $company->id,
            'email' => $request->email,
            'token' => $token,
            'expires_at' => now()->addDays(7)
        ]);

        // In production: Mail::to($request->email)->send(new CompanyInviteMail($invitation));

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent',
            'token' => $token 
        ]);
    }

    public function listRiders(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->riders()->paginate(20)
        ]);
    }

    public function getRiderChatMessages(Request $request, $id)
    {
        $company = $request->user();
        
        // Ensure the delivery belongs to one of the company's riders
        $delivery = Delivery::where('id', $id)
            ->whereHas('rider', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })->firstOrFail();

        $chat = $delivery->chat;

        if (!$chat) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'success' => true,
            'data' => $chat->messages()->orderBy('created_at', 'asc')->get()
        ]);
    }

    // ============= RIDER-FACING METHODS =============

    public function getRiderCompany(Request $request)
    {
        $rider = $request->user();
        
        if (!$rider->company_id) {
            return response()->json([
                'success' => true,
                'data' => null
            ]);
        }

        $company = Company::find($rider->company_id);

        return response()->json([
            'success' => true,
            'data' => $company
        ]);
    }

    public function getRiderInvitations(Request $request)
    {
        $rider = $request->user();

        $invitations = CompanyInvitation::where('email', $rider->email)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with('company')
            ->get();

        return response()->json([
            'success' => true,
            'invitations' => $invitations
        ]);
    }

    public function acceptInvitation(Request $request, $token)
    {
        $rider = $request->user();

        $invitation = CompanyInvitation::where('token', $token)
            ->where('email', $rider->email)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        // Update rider's company
        $rider->update(['company_id' => $invitation->company_id]);

        // Mark invitation as accepted
        $invitation->update(['status' => 'accepted']);

        return response()->json([
            'success' => true,
            'message' => 'Successfully joined company'
        ]);
    }

    public function rejectInvitation(Request $request, $token)
    {
        $rider = $request->user();

        $invitation = CompanyInvitation::where('token', $token)
            ->where('email', $rider->email)
            ->where('status', 'pending')
            ->firstOrFail();

        $invitation->update(['status' => 'rejected']);

        return response()->json([
            'success' => true,
            'message' => 'Invitation rejected'
        ]);
    }

    public function leaveCompany(Request $request)
    {
        $rider = $request->user();

        if (!$rider->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not part of any company'
            ], 400);
        }

        $rider->update(['company_id' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully left company'
        ]);
    }

    public function getRiderCompanyStats(Request $request)
    {
        $rider = $request->user();

        if (!$rider->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not part of any company'
            ], 400);
        }

        $company = Company::find($rider->company_id);

        return response()->json([
            'success' => true,
            'data' => [
                'total_riders' => $company->riders()->count(),
                'active_riders' => $company->riders()->where('status', 'active')->count(),
                'total_deliveries' => $company->riders()->withCount('deliveries')->get()->sum('deliveries_count'),
            ]
        ]);
    }
}
