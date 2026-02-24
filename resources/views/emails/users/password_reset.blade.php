@extends('emails.layouts.main')
   
@section('content')
    <h1>Hello {{ $name }},</h1>
    <p>Your password reset code is: <strong>{{ $code }}</strong></p>
    <p>This code will expire in 15 minutes.</p>
    <p>If you did not request a password reset, please ignore this email.</p>
    <p>Thank you for choosing Swayider!</p>
@endsection
 