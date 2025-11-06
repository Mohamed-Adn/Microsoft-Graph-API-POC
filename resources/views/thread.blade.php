@extends('layout')
@section('content')
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
      <a href="{{ route('conversations') }}" class="btn" style="background: #95a5a6;">
        ← Back to Conversations
      </a>
    </div>
    <div>
      <a href="{{ route('sync.new') }}" class="btn btn-success">
        🔄 Refresh Thread
      </a>
    </div>
  </div>
  
  @if(session('success'))
    <div class="card" style="background:#f0fff0; margin-bottom: 10px; padding: 10px; border-color: #27ae60;">
      ✅ {{ session('success') }}
    </div>
  @endif
  
  @if(session('error'))
    <div class="card" style="background:#fff0f0; margin-bottom: 10px; padding: 10px; border-color: #e74c3c;">
      ❌ {{ session('error') }}
    </div>
  @endif

  @foreach($messages as $m)
    <div class="card" style="margin-bottom: 15px;">
      
      <div><b>{{ $m['subject'] ?? '(no subject)' }}</b></div>
      
      <div>From: 
      {{ $m['from_email'] ?? '(unknown)' }}
      @if(!empty($m['from_name']))
        ({{ $m['from_name'] }})
      @endif
      </div>
      
      <div class="muted">
      Received: 
      @php
        $received = $m['received_at'] ?? null;
      @endphp
      {{ $received ? \Carbon\Carbon::parse($received)->format('D d/m/Y h:i A') : '' }}
      </div>
      
      <div style="margin-top:8px; background:#fafafa; padding:8px;">
      {!! $m['body_html'] ?? 'No content' !!}
      </div>
      
      <!-- You replied badge for our reply messages -->
      {{-- @if(!empty($m['from_email']) && $m['from_email'])
      <span style="display: inline-block; background: #27ae60; color: #fff; padding: 5px 10px; border-radius: 12px; font-size: 0.95em; margin-top: 8px;">
        You replied
      </span>
      @endif --}}

      <!-- Reply buttons for all messages -->
      <div style="margin-top:8px;">
      <button onclick="toggleReplyForm('{{ $m['graph_id'] }}')" class="btn">Reply</button>
      <button onclick="toggleReplyAllForm('{{ $m['graph_id'] }}')" class="btn" style="background: #f39c12;">Reply All</button>
      </div>
      
      <!-- Reply Form (Initially Hidden) -->
      <div id="reply-form-{{ $m['graph_id'] }}" style="display: none; margin-top: 15px; background: #f9f9f9; padding: 15px; border-radius: 5px;">
        <form method="post" action="{{ route('reply.post.inline') }}">
          @csrf
          <input type="hidden" name="message_id" value="{{ $m['graph_id'] }}">
          <input type="hidden" name="conversation_id" value="{{ $cid }}">
          <input type="hidden" name="reply_type" value="reply">
          
          <div style="margin-bottom: 10px;">
            <label><strong>Reply:</strong></label>
            <textarea name="comment" rows="4" style="width: 100%; padding: 8px;" placeholder="Type your reply here..." required></textarea>
          </div>
          
          <button type="submit" class="btn">Send Reply</button>
          <button type="button" onclick="toggleReplyForm('{{ $m['graph_id'] }}')" class="btn" style="background: #95a5a6; margin-left: 10px;">Cancel</button>
        </form>
      </div>
      
      <!-- Reply All Form (Initially Hidden) -->
      <div id="replyall-form-{{ $m['graph_id'] }}" style="display: none; margin-top: 15px; background: #f9f9f9; padding: 15px; border-radius: 5px;">
        <form method="post" action="{{ route('reply.post.inline') }}">
          @csrf
          <input type="hidden" name="message_id" value="{{ $m['graph_id'] }}">
          <input type="hidden" name="conversation_id" value="{{ $cid }}">
          <input type="hidden" name="reply_type" value="reply_all">
          
          <div style="margin-bottom: 10px;">
            <label><strong>Reply All:</strong></label>
            <textarea name="comment" rows="4" style="width: 100%; padding: 8px;" placeholder="Type your reply to all recipients..." required></textarea>
          </div>
          
          <button type="submit" class="btn">Send Reply All</button>
          <button type="button" onclick="toggleReplyAllForm('{{ $m['graph_id'] }}')" class="btn" style="background: #95a5a6; margin-left: 10px;">Cancel</button>
        </form>
      </div>
    </div>
  @endforeach

  <script>
    function toggleReplyForm(messageId) {
      const form = document.getElementById('reply-form-' + messageId);
      const replyAllForm = document.getElementById('replyall-form-' + messageId);
      
      // Hide reply all form if open
      if (replyAllForm) replyAllForm.style.display = 'none';
      
      // Toggle reply form
      if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
    
    function toggleReplyAllForm(messageId) {
      const form = document.getElementById('replyall-form-' + messageId);
      const replyForm = document.getElementById('reply-form-' + messageId);
      
      // Hide reply form if open
      if (replyForm) replyForm.style.display = 'none';
      
      // Toggle reply all form
      if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
  </script>
@endsection