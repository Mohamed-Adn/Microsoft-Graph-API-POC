@extends('layout')
@section('content')
  <h2>Conversations</h2>
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
        — <span class="muted">{{ count($msgs) }} msg{{ count($msgs)>1?'s':'' }}</span>
        @if(!$head['is_read'])
          <span style="color: #007cba; font-weight: bold; font-size: 16px;"> ●</span>
          <span style="color: #007cba; font-weight: bold; font-size: 12px;">[NEW]</span>
        @endif
      </li>
    @endforeach
    </ul>
  @endif
  
  <div style="margin-top: 20px;">
    <a href="{{ route('sync') }}" style="background: #007cba; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Sync Mailbox</a>
  </div>
@endsection