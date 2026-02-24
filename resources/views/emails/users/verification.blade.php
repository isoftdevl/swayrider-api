@extends('emails.layouts.main')

@section('content')
<h2>Hello {{ $name }},</h2>
<p>Thank you for registering with Swayider. To complete your registration and verify your email address, please use the One-Time Password (OTP) below:</p>

<div style="text-align: center; margin: 30px 0;">
    <span style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #10b981; background-color: #ecfdf5; padding: 10px 20px; border-radius: 8px;">{{ $code }}</span>
</div>

<p>This code will expire in 15 minutes.</p>

<p>If you did not create an account with SwayRider, please ignore this email.</p>
@endsection
