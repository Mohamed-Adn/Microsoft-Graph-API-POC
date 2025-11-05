<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Mail Demo</title>
  <style>
    body { font-family: system-ui, Arial, sans-serif; max-width: 900px; margin: 20px auto; }
    .card { border:1px solid #ddd; border-radius:6px; padding:12px; margin:10px 0; background:#fff; }
    .muted { color:#666; font-size: 0.95em; }
    nav a{ margin-right:10px; }
  </style>
</head>
<body>
  <nav>
    <a href="{{ route('home') }}">Home</a>
    @if(session()->has('access_token'))
      <a href="{{ route('inbox') }}">Inbox</a>
      <a href="{{ route('send.form') }}">Send</a>
      <form method="post" action="{{ route('logout') }}" style="display:inline;">
        @csrf <button type="submit">Logout</button>
      </form>
    @else
      <a href="{{ route('login') }}">Sign in with Microsoft</a>
    @endif
  </nav>
  <hr>
  @yield('content')
</body>
</html>
