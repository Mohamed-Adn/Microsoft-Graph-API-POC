@extends('layout')
@section('content')
  @if(session('ok')) <div class="card" style="background:#f0fff0">{{ session('ok') }}</div> @endif
  <h2>Reply</h2>
  <form method="post" action="{{ route('reply.post') }}">
    @csrf
    <input type="hidden" name="id" value="{{ $id }}">
    <label>Comment:<br>
      <textarea name="comment" rows="6" cols="80" required>Thanks for your email!</textarea>
    </label><br><br>
    <button type="submit">Reply</button>
  </form>
@endsection
