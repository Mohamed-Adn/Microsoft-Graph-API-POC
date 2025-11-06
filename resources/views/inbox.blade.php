@extends('layout')
@section('content')
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Conversations</h2>
    <div>
      <a href="{{ route('sync.new') }}" style="background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px; margin-right: 10px;">
        📩 Check New Messages
      </a>
      <a href="{{ route('sync') }}" style="background: #007cba; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">
        🔄 Full Sync
      </a>
    </div>
  </div>

  @if(session('success'))
    <div style="background:#f0fff0; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #d4edda;">
      {{ session('success') }}
    </div>
  @endif

  @if(session('info'))
    <div style="background:#e7f3ff; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #b3d7ff;">
      {{ session('info') }}
    </div>
  @endif

  @if(empty($groups))
    <p class="muted">No messages.</p>
  @else
    <ul style="list-style: none; padding: 0;">
    @foreach($groups as $cid => $msgs)
      @php $head = $msgs[0]; @endphp
      <li style="padding: 10px; border-bottom: 1px solid #eee; {{ !$head['is_read'] ? 'background-color: #f0f8ff;' : '' }}">
        <b><a href="{{ route('thread', ['cid'=>$cid]) }}" style="text-decoration: none; color: {{ !$head['is_read'] ? '#007cba' : '#333' }};">{{ $head['subject'] ?? '(no subject)' }}</a></b>
        — {{ $head['from_email'] ?? '(unknown)' }}
        @if(!empty($head['from_name']))
          ({{ $head['from_name'] }})
        @endif
        — <span class="muted">{{ $head['received_at'] ?? '' }}</span>
        — <span class="muted">{{ $head['message_count'] ?? 1 }} msg{{ ($head['message_count'] ?? 1) > 1 ? 's' : '' }}</span>
        @if(!$head['is_read'])
          <span style="color: #007cba; font-weight: bold; font-size: 16px;"> ●</span>
          <span style="color: #007cba; font-weight: bold; font-size: 12px;">[NEW]</span>
        @endif
      </li>
    @endforeach
    </ul>
  @endif
@endsection