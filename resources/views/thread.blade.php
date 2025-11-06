@extends('layout')
@section('content')
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Thread</h2>
    <div>
      <a href="{{ route('thread', ['cid' => $cid]) }}" style="background: #28a745; color: white; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 12px;">
        🔄 Refresh Thread
      </a>
      <a href="{{ route('inbox') }}" style="background: #6c757d; color: white; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 12px; margin-left: 8px;">
        ← Back to Inbox
      </a>
    </div>
  </div>
  
  @if(session('success'))
    <div class="card" style="background:#f0fff0; margin-bottom: 10px; padding: 10px;">{{ session('success') }}</div>
  @endif
  
  @if(session('error'))
    <div class="card" style="background:#fff0f0; margin-bottom: 10px; padding: 10px;">{{ session('error') }}</div>
  @endif

  @foreach($messages as $m)
    <div class="card" style="margin-bottom: 15px;">
      <div><b>{{ $m['subject'] ?? $m['subject'] ?? '(no subject)' }}</b></div>
      <div>From: 
        @if(isset($m['from_email']))
          {{ $m['from_email'] }}
          @if(!empty($m['from_name']))
            ({{ $m['from_name'] }})
          @endif
        @else
          {{ $m['from']['emailAddress']['address'] ?? '(unknown)' }}
        @endif
      </div>
      
      @if(!empty($m['toRecipients']))
        <div>To: {{ collect($m['toRecipients'])->map(fn($r)=>$r['emailAddress']['address']??'')->implode(', ') }}</div>
      @endif
      @if(!empty($m['ccRecipients']))
        <div>CC: {{ collect($m['ccRecipients'])->map(fn($r)=>$r['emailAddress']['address']??'')->implode(', ') }}</div>
      @endif
      
      <div class="muted">
        Sent: 
        @php
          $received = $m['received_at'] ?? $m['receivedDateTime'] ?? null;
        @endphp
        {{ $received ? \Carbon\Carbon::parse($received)->format('D d/m/Y h:i A') : '' }}
      </div>
      
      <div style="margin-top:8px; background:#fafafa; padding:8px;">
        {!! $m['body_html'] ?? $m['body']['content'] ?? 'No content' !!}
      </div>
      
      <div style="margin-top:8px;">
        <button onclick="toggleReplyForm('{{ $m['graph_id'] ?? $m['id'] }}')" class="reply-btn">Reply</button>
        <button onclick="toggleReplyAllForm('{{ $m['graph_id'] ?? $m['id'] }}')" class="reply-btn">Reply All</button>
      </div>
      
      <!-- Reply Form (Initially Hidden) -->
      <div id="reply-form-{{ $m['graph_id'] ?? $m['id'] }}" style="display: none; margin-top: 15px; background: #f9f9f9; padding: 15px; border-radius: 5px;">
        <form method="post" action="{{ route('reply.post.inline') }}">
          @csrf
          <input type="hidden" name="message_id" value="{{ $m['graph_id'] ?? $m['id'] }}">
          <input type="hidden" name="conversation_id" value="{{ $cid }}">
          <input type="hidden" name="reply_type" value="reply">
          
          <div style="margin-bottom: 10px;">
            <label><strong>Reply:</strong></label>
            <textarea name="comment" rows="4" style="width: 100%; padding: 8px;" placeholder="Type your reply here..." required></textarea>
          </div>
          
          <button type="submit" style="background: #007cba; color: white; padding: 8px 16px; border: none; border-radius: 3px;">Send Reply</button>
          <button type="button" onclick="toggleReplyForm('{{ $m['graph_id'] ?? $m['id'] }}')" style="background: #ccc; color: black; padding: 8px 16px; border: none; border-radius: 3px; margin-left: 10px;">Cancel</button>
        </form>
      </div>
      
      <!-- Reply All Form (Initially Hidden) -->
      <div id="replyall-form-{{ $m['graph_id'] ?? $m['id'] }}" style="display: none; margin-top: 15px; background: #f9f9f9; padding: 15px; border-radius: 5px;">
        <form method="post" action="{{ route('reply.post.inline') }}">
          @csrf
          <input type="hidden" name="message_id" value="{{ $m['graph_id'] ?? $m['id'] }}">
          <input type="hidden" name="conversation_id" value="{{ $cid }}">
          <input type="hidden" name="reply_type" value="reply_all">
          
          <div style="margin-bottom: 10px;">
            <label><strong>Reply All:</strong></label>
            <textarea name="comment" rows="4" style="width: 100%; padding: 8px;" placeholder="Type your reply to all recipients..." required></textarea>
          </div>
          
          <button type="submit" style="background: #007cba; color: white; padding: 8px 16px; border: none; border-radius: 3px;">Send Reply All</button>
          <button type="button" onclick="toggleReplyAllForm('{{ $m['graph_id'] ?? $m['id'] }}')" style="background: #ccc; color: black; padding: 8px 16px; border: none; border-radius: 3px; margin-left: 10px; cursor: pointer;">Cancel</button>
        </form>
      </div>
    </div>
  @endforeach

  {{-- <div class="card" style="margin-top: 20px; background: #f8f9fa;">
    <h4>Add New Reply</h4>
    <form method="post" action="{{ route('reply.post.inline') }}">
      @csrf
      @if(!empty($messages))
        @php $firstOriginal = collect($messages)->firstWhere('type', 'original'); @endphp
        <input type="hidden" name="message_id" value="{{ $firstOriginal['graph_id'] ?? '' }}">
      @endif
      <input type="hidden" name="conversation_id" value="{{ $cid }}">
      <input type="hidden" name="reply_type" value="reply">
      
      <div style="margin-bottom: 10px;">
        <textarea name="comment" rows="4" style="width: 100%; padding: 8px;" placeholder="Type your reply to this conversation..." required></textarea>
      </div>
      
      <button type="submit" style="background: #007cba; color: white; padding: 8px 16px; border: none; border-radius: 3px;">Send Reply</button>
    </form>
  </div> --}}

  <script>
    function toggleReplyForm(messageId) {
      const form = document.getElementById('reply-form-' + messageId);
      const replyAllForm = document.getElementById('replyall-form-' + messageId);
      
      // Hide reply all form if open
      replyAllForm.style.display = 'none';
      
      // Toggle reply form
      form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
    
    function toggleReplyAllForm(messageId) {
      const form = document.getElementById('replyall-form-' + messageId);
      const replyForm = document.getElementById('reply-form-' + messageId);
      
      // Hide reply form if open
      replyForm.style.display = 'none';
      
      // Toggle reply all form
      form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
  </script>

  <style>
    .reply-btn {
      background: #007cba;
      color: white;
      padding: 6px 12px;
      border: none;
      border-radius: 3px;
      cursor: pointer;
      margin-right: 8px;
    }
    
    .reply-btn:hover {
      background: #005a87;
    }
    
    .card {
      border: 1px solid #ddd;
      padding: 15px;
      border-radius: 5px;
    }
  </style>
@endsection