@extends('layout')
@section('content')
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
      <p class="muted">Recent messages in your mailbox</p>
    </div>
    <div style="display: flex; gap: 10px;">
      <a href="{{ route('sync.new') }}" class="btn btn-success">
        📩 Check New Messages
      </a>
      <a href="{{ route('sync') }}" class="btn">
        🔄 Full Sync
      </a>
    </div>
  </div>

  @if(session('success'))
    <div class="card" style="background:#f0fff0; margin-bottom: 10px; padding: 10px; border-color: #27ae60;">
      ✅ {{ session('success') }}
    </div>
  @endif

  @if(session('info'))
    <div class="card" style="background:#e3f2fd; margin-bottom: 10px; padding: 10px; border-color: #3498db;">
      ℹ️ {{ session('info') }}
    </div>
  @endif

  @php
    // Get the current user's email for identifying their messages
    $userEmail = session('user_email') ?? 'your-email@domain.com';
    
    if (session()->has('access_token')) {
        $token = session('access_token');
        $tokenParts = explode(".", $token);
        if (count($tokenParts) >= 2) {
            $payload = json_decode(base64_decode(strtr($tokenParts[1], '-_', '+/')), true);
            $userEmail = $payload['preferred_username'] ?? $payload['email'] ?? $userEmail;
        }
    }
  @endphp

  @if(empty($groups))
    <div class="card" style="text-align: center; padding: 40px;">
      <h3 style="color: #7f8c8d; margin-bottom: 10px;">📭 No Messages</h3>
      <p class="muted">No messages found in your inbox. Try syncing your messages.</p>
      <a href="{{ route('sync') }}" class="btn" style="margin-top: 15px;">
        🔄 Sync Messages
      </a>
    </div>
  @else
    <div style="background: white; border-radius: 8px; overflow: hidden; border: 1px solid #ddd;">
      <div style="background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd; font-weight: bold; color: #2c3e50;">
        📧 Conversations ({{ count($groups) }} total)
      </div>
      
      @foreach($groups as $cid => $msgs)
        @php 
          $head = $msgs[0];
          $isFromUser = ($head['from_email'] ?? '') === $userEmail;
          $latestActivity = \Carbon\Carbon::parse($head['latest_received_at'] ?? $head['received_at']);
        @endphp
        <div style="padding: 15px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 15px; {{ !$head['is_read'] ? 'background: #f8f9ff;' : '' }} cursor: pointer; transition: all 0.2s ease;"
             onclick="window.location.href='{{ route('thread', ['cid'=>$cid]) }}'">
          
          <div style="width: 40px; height: 40px; background: {{ !$head['is_read'] ? '#3498db' : '#95a5a6' }}; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px;">
            @if($isFromUser)
              {{ strtoupper(substr('You', 0, 1)) }}
            @else
              {{ strtoupper(substr($head['from_name'] ?? $head['from_email'] ?? 'U', 0, 1)) }}
            @endif
          </div>
          
          <div style="flex: 1; min-width: 0;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
              <span style="font-weight: {{ !$head['is_read'] ? 'bold' : 'normal' }}; color: #2c3e50; font-size: 16px;">
                {{ $head['subject'] ?? '(no subject)' }}
              </span>
              @if(!$head['is_read'])
                <span style="background: #3498db; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; font-weight: bold;">
                  NEW
                </span>
              @endif
              @if(($head['message_count'] ?? 1) > 1)
                <span style="background: #f39c12; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; font-weight: bold;">
                  {{ $head['message_count'] }} MSGS
                </span>
              @endif
            </div>
            
            <div style="color: #7f8c8d; font-size: 14px;">
              @if($isFromUser)
                <span style="color: #007cba; font-weight: bold;">You started this conversation</span>
              @else
                From: {{ $head['from_email'] ?? '(unknown)' }}
                @if(!empty($head['from_name']))
                  ({{ $head['from_name'] }})
                @endif
              @endif
            </div>
            
            <div style="color: #95a5a6; font-size: 12px; margin-top: 2px;">
              Started: {{ $head['received_at'] ? \Carbon\Carbon::parse($head['received_at'])->format('M d, Y') : '' }}
              @if(isset($head['latest_received_at']) && $head['latest_received_at'] !== $head['received_at'])
                • Last activity: {{ $latestActivity->diffForHumans() }}
              @endif
            </div>
          </div>
          
          <div style="text-align: right; color: #7f8c8d; font-size: 12px;">
            <div>{{ $latestActivity->format('M d') }}</div>
            <div style="margin-top: 2px;">{{ $head['message_count'] ?? 1 }} msg{{ ($head['message_count'] ?? 1) > 1 ? 's' : '' }}</div>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  <style>
    [onclick]:hover {
      background-color: #f5f5f5 !important;
    }
  </style>
@endsection