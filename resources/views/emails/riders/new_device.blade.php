@extends('emails.layouts.main')

@section('content')
<h2 style="color: #ef4444;">New Login Detected</h2>
<p>Hello {{ $data['name'] }},</p>
<p>We noticed a new sign-in to your SwayRider account from a new device.</p>

<div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <p style="margin: 5px 0;"><strong>Device:</strong> {{ $data['device_name'] }}</p>
    <p style="margin: 5px 0;"><strong>IP Address:</strong> {{ $data['ip_address'] }}</p>
    <p style="margin: 5px 0;"><strong>Time:</strong> {{ $data['time'] }}</p>
    @if(isset($data['location']))
    <p style="margin: 5px 0;"><strong>Location:</strong> {{ $data['location'] }}</p>
    @endif
    @if(isset($data['latitude']) && isset($data['longitude']))
    <p style="margin: 5px 0;"><strong>Coordinates:</strong> {{ $data['latitude'] }}, {{ $data['longitude'] }}</p>
    <p style="margin: 5px 0;"><a href="https://www.google.com/maps?q={{ $data['latitude'] }},{{ $data['longitude'] }}">View on Map</a></p>
    @endif
</div>

<p>If this was you, you can safely ignore this email.</p>

<p><strong>If you did not authorize this login, please change your password immediately and contact support.</strong></p>

<a href="mailto:support@swayrider.com" class="button" style="background-color: #ef4444;">Contact Support</a>
@endsection
