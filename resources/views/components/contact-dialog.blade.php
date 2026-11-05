<link rel="stylesheet" href="{{ asset('css/contact-dialog.css') }}?v={{ filemtime(public_path('css/contact-dialog.css')) }}">
<dialog id="pocketfinds-contact" class="pf-contact-dialog" aria-labelledby="pf-contact-title">
  <div class="pf-contact-heading">
    <h2 id="pf-contact-title">Contact PocketFinds</h2>
    <button type="button" data-close-contact aria-label="Close contact form">&times;</button>
  </div>
  <form data-contact-form action="{{ route('support.contact') }}" method="POST">
    @csrf
    <input type="hidden" name="context" value="Website contact form">
    <label for="pf-contact-email">Your email</label>
    <input id="pf-contact-email" type="email" name="email" autocomplete="email" maxlength="255" value="{{ auth()->user()?->email }}" required>
    <label for="pf-contact-message">Message</label>
    <textarea id="pf-contact-message" name="message" rows="5" maxlength="2000" required></textarea>
    <p data-contact-status role="status" aria-live="polite"></p>
    <button class="pf-contact-send" type="submit">Send message</button>
  </form>
</dialog>
<script src="{{ asset('js/contact-dialog.js') }}?v={{ filemtime(public_path('js/contact-dialog.js')) }}" defer></script>
