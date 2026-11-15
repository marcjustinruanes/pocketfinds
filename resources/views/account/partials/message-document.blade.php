<a href="{{ $documentUrl }}" target="_blank" rel="noopener" class="chat-doc-bubble" title="{{ $documentName ?: 'Document attachment' }}">
  <span class="chat-doc-badge" aria-hidden="true">{{ strtolower(pathinfo($documentName ?? '', PATHINFO_EXTENSION))==='pdf' ? 'PDF' : 'FILE' }}</span>
  <span class="chat-doc-name">{{ $documentName ?: 'Document attachment' }}</span>
  <svg class="chat-doc-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7M21 3l-9 9M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/></svg>
</a>
