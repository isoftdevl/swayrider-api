@extends('emails.layouts.main')

@section('content')
<h2>Hello {{ $name }},</h2>
<p>We reviewed your KYC documents, and unfortunately, we could not approve your verification at this time.</p>

<div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">
    <p style="margin: 0; font-weight: bold; color: #b91c1c;">Reason for Rejection:</p>
    <p style="margin: 5px 0 0 0; color: #7f1d1d;">{{ $reason }}</p>
</div>

<p>Please log in to the app, review the guidelines, and resubmit the corrected documents for verification.</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="#" class="button">Resubmit Documents</a>
</div>

<p>If you have any questions, please contact our support team.</p>
@endsection
