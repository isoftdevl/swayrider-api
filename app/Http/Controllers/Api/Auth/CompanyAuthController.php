<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CompanyAuthController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|email|unique:companies',
            'phone' => 'required|unique:companies',
            'password' => 'required|confirmed|min:8',
            'address' => 'required',
            'city' => 'required',
            'state' => 'required'
        ]);

        if ($validator->fails()) return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

        $company = Company::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'status' => 'pending', 
        ]);

        $this->walletService->getWallet('App\Models\Company', $company->id);

        $token = $company->createToken('company_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Company registered. Please submit verification documents.',
            'data' => [
                'company' => $company,
                'token' => $token
            ]
        ], 201);
    }
    
    public function login(Request $request)
    {
        // Similar login logic...
        $company = Company::where('email', $request->email)->first(); // Login with email only for Business?

        if (!$company || !Hash::check($request->password, $company->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        $token = $company->createToken('company_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'company' => $company,
                'token' => $token
            ]
        ]);
    }
}
