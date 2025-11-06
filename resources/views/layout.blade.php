<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Mail Demo</title>
  <style>
    body { 
      font-family: system-ui, Arial, sans-serif; 
      margin: 0; 
      padding: 0;
      background-color: #f5f5f5;
    }
    .main-container {
      display: flex;
      min-height: 100vh;
    }
    .sidebar {
      width: 250px;
      background: #2c3e50;
      color: white;
      padding: 20px 0;
      position: fixed;
      height: 100vh;
      overflow-y: auto;
    }
    .sidebar h2 {
      margin: 0 20px 20px 20px;
      font-size: 18px;
      font-weight: bold;
      border-bottom: 1px solid #34495e;
      padding-bottom: 10px;
    }
    .sidebar-menu {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .sidebar-menu li {
      margin: 0;
    }
    .sidebar-menu a {
      display: block;
      padding: 12px 20px;
      color: #ecf0f1;
      text-decoration: none;
      transition: all 0.3s ease;
      border-left: 3px solid transparent;
    }
    .sidebar-menu a:hover {
      background-color: #34495e;
      border-left-color: #3498db;
    }
    .sidebar-menu a.active {
      background-color: #3498db;
      border-left-color: #2980b9;
      font-weight: bold;
    }
    .sidebar-menu .badge {
      background: #e74c3c;
      color: white;
      border-radius: 10px;
      padding: 2px 6px;
      font-size: 11px;
      font-weight: bold;
      margin-left: 8px;
      display: inline-block;
      min-width: 16px;
      text-align: center;
    }
    .sidebar-menu .icon {
      margin-right: 10px;
      width: 16px;
      display: inline-block;
    }
    .content-area {
      margin-left: 250px;
      flex: 1;
      padding: 0;
      background: white;
      min-height: 100vh;
    }
    .top-nav {
      background: white;
      padding: 15px 25px;
      border-bottom: 1px solid #ddd;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .top-nav h1 {
      margin: 0;
      font-size: 24px;
      color: #2c3e50;
    }
    .top-nav .user-info {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .top-nav .user-info a,
    .top-nav .user-info button {
      padding: 8px 16px;
      text-decoration: none;
      background: #3498db;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      transition: background 0.3s ease;
    }
    .top-nav .user-info a:hover,
    .top-nav .user-info button:hover {
      background: #2980b9;
    }
    .main-content {
      padding: 25px;
      max-width: 1200px;
    }
    .card { 
      border: 1px solid #ddd; 
      border-radius: 6px; 
      padding: 15px; 
      margin: 10px 0; 
      background: #fff; 
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .muted { 
      color: #666; 
      font-size: 0.95em; 
    }
    .btn {
      display: inline-block;
      padding: 8px 16px;
      background: #3498db;
      color: white;
      text-decoration: none;
      border-radius: 4px;
      border: none;
      cursor: pointer;
      transition: background 0.3s ease;
    }
    .btn:hover {
      background: #2980b9;
    }
    .btn-success {
      background: #27ae60;
    }
    .btn-success:hover {
      background: #219a52;
    }
    @media (max-width: 768px) {
      .sidebar {
        width: 100%;
        height: auto;
        position: relative;
      }
      .content-area {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>
  <div class="main-container">
    @if(session()->has('access_token'))
      <!-- Sidebar -->
      <div class="sidebar">
        <h2>📧 Mail System</h2>
        <ul class="sidebar-menu">
          <li>
            <a href="{{ route('inbox') }}" class="{{ request()->routeIs('inbox') ? 'active' : '' }}">
              <span class="icon">📥</span>
              Inbox
              @if(isset($unreadCount) && $unreadCount > 0)
                <span class="badge">{{ $unreadCount }}</span>
              @endif
            </a>
          </li>
          <li>
            <a href="{{ route('conversations') }}" class="{{ request()->routeIs('conversations') || request()->routeIs('thread') ? 'active' : '' }}">
              <span class="icon">💬</span>
              Conversations
            </a>
          </li>
          <li>
            <a href="{{ route('send.form') }}" class="{{ request()->routeIs('send.form') ? 'active' : '' }}">
              <span class="icon">✉️</span>
              Compose
            </a>
          </li>
          <li style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #34495e;">
            <a href="{{ route('sync') }}">
              <span class="icon">🔄</span>
              Sync Messages
            </a>
          </li>
        </ul>
      </div>

      <!-- Content Area -->
      <div class="content-area">
        <!-- Top Navigation -->
        <div class="top-nav">
          <h1>
            @if(request()->routeIs('inbox'))
              Inbox
            @elseif(request()->routeIs('conversations') || request()->routeIs('thread'))
              Conversations
            @elseif(request()->routeIs('send.form'))
              Compose Message
            @else
              Mail System
            @endif
          </h1>
          <div class="user-info">
            <form method="post" action="{{ route('logout') }}" style="display:inline;">
              @csrf 
              <button type="submit">Logout</button>
            </form>
          </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
          @yield('content')
        </div>
      </div>
    @else
      <!-- Login Page (Full Width) -->
      <div style="width: 100%; display: flex; align-items: center; justify-content: center; min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div style="background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); text-align: center; max-width: 400px; width: 100%; margin: 20px;">
          <h1 style="margin-bottom: 30px; color: #2c3e50;">M365 Outlook </h1>
          <p style="color: #7f8c8d; margin-bottom: 30px;">Connect with your Microsoft account to access your emails</p>
          <a href="{{ route('login') }}" style="display: inline-block; padding: 12px 30px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; transition: all 0.3s ease;">
            🔐 Sign in with Microsoft
          </a>
        </div>
      </div>
    @endif
  </div>
</body>
</html>
