@extends('layout')
@section('content')
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
      <p class="muted">Select a conversation to view messages</p>
    </div>
    <div>
      <a href="{{ route('sync.new') }}" class="btn btn-success">
        📩 Check New Messages
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

  @if(empty($groups))
    <div class="card" style="text-align: center; padding: 40px;">
      <h3 style="color: #7f8c8d; margin-bottom: 10px;">📭 No Conversations</h3>
      <p class="muted">No message conversations found. Try syncing your messages.</p>
      <a href="{{ route('sync') }}" class="btn" style="margin-top: 15px;">
        🔄 Sync Messages
      </a>
    </div>
  @else
    <div style="display: grid; gap: 15px;">
    @foreach($groups as $cid => $msgs)
      @php $head = $msgs[0]; @endphp
      <div class="card" style="cursor: pointer; transition: all 0.3s ease; {{ !$head['is_read'] ? 'border-left: 4px solid #3498db; background: #f8f9ff;' : '' }}" 
           onclick="window.location.href='{{ route('thread', ['cid'=>$cid]) }}'">
        
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 15px;">
          <div style="flex: 1;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
              <h4 style="margin: 0; color: #2c3e50;">
                {{ $head['subject'] ?? '(no subject)' }}
              </h4>
              @if(!$head['is_read'])
                <span style="background: #3498db; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold;">
                  NEW
                </span>
              @endif
            </div>
            
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 5px;">
              <span style="font-weight: 500; color: #34495e;">
                📧 {{ $head['from_email'] ?? '(unknown)' }}
              </span>
              @if(!empty($head['from_name']))
                <span class="muted">({{ $head['from_name'] }})</span>
              @endif
            </div>
            
            <div style="display: flex; align-items: center; gap: 15px; font-size: 14px;">
              <span class="muted">
                🕒 {{ $head['received_at'] ? \Carbon\Carbon::parse($head['received_at'])->format('M d, Y h:i A') : 'Unknown time' }}
              </span>
              <span class="muted">
                💬 {{ $head['message_count'] ?? 1 }} message{{ ($head['message_count'] ?? 1) > 1 ? 's' : '' }}
              </span>
            </div>
          </div>
          
          <div style="text-align: right;">
            <span style="background: #ecf0f1; color: #7f8c8d; padding: 6px 12px; border-radius: 4px; font-size: 12px;">
              Click to view
            </span>
          </div>
        </div>
      </div>
    @endforeach
    </div>
  @endif

  <style>
    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      border-color: #3498db;
    }
  </style>
@endsection
