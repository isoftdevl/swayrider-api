@extends('emails.layouts.main')

@section('content')
<h2>Hello {{ $name }},</h2>
<p>Your KYC (Know Your Customer) documents have been submitted successfully!</p>

<p>Our team is currently reviewing your documents. This process typically takes 24 to 48 hours. You will receive another email once the review is complete.</p>

<p>In the meantime, you can check your verification status in the app under the KYC section.</p>

<p>Thank you for choosing SwayRider!</p>
@endsection
