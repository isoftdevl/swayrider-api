@extends('emails.layouts.main')

@section('content')
<h2>Congratulations {{ $name }}!</h2>
<p>We are happy to inform you that your KYC verification was successful.</p>

<p>Your account has been fully activated, and you can now start accepting delivery requests and earning money with SwayRider.</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="#" class="button">Go to Dashboard</a>
</div>

<p>Happy Riding!</p>
@endsection
