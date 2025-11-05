@extends('layout')
@section('content')
  <h2>{{ $m['subject'] ?? '(no subject)' }}</h2>
  <p>From: {{ $m['from']['emailAddress']['address'] ?? '(unknown)' }} • {{ $m['receivedDateTime'] ?? '' }}</p>
  <div class="card">{!! $m['body']['content'] ?? '' !!}</div>
@endsection
