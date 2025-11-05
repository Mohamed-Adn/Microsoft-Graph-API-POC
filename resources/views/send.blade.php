@extends('layout')
@section('content')
  @if(session('ok')) <div class="card" style="background:#f0fff0">{{ session('ok') }}</div> @endif
  <h2>Send Mail</h2>
  <form method="post" action="{{ route('send.post') }}">
    @csrf
    <label>To: <input type="email" name="to" required></label><br><br>
    <label>Subject: <input type="text" name="subject" required></label><br><br>
    <label>Body (HTML):<br>
      <textarea name="body" rows="8" cols="80" required><b>Hello</b> from Laravel!</textarea>
    </label><br><br>
    <button type="submit">Send</button>
  </form>
@endsection
