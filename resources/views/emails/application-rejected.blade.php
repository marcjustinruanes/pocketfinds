<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Application update</title></head>
<body style="font-family:Arial,sans-serif;color:#27272a;line-height:1.6;padding:24px">
<h2 style="color:#bf3c7d">PocketFinds</h2>
<p>Hello {{ $applicantName }},</p>
@if($canResubmit)
  <p>Your {{ $role }} application needs corrections. You have been given permission to edit and resubmit it.</p>
@else
  <p>Your {{ $role }} application has been rejected. This decision does not allow editing or resubmission of this application.</p>
@endif
@if($reason)<p><strong>Reason</strong></p><p style="white-space:pre-wrap">{{ $reason }}</p>@endif
@if($canResubmit)
  @if($hasInvitationLink)
  <p>Use the link below to update your details or documents and resubmit. Then follow your new rider activation email to set your password.</p>
  @else
  <p>Sign in with your existing account to review the rejection reason, update your details or documents, and resubmit. Your application will return to review.</p>
  @endif
  <p><a href="{{ $correctionUrl }}">Open your application</a></p>
  @if($hasInvitationLink)<p>If you have not set a rider password yet, use this emailed link to open the correction screen.</p>@endif
@else
  <p>You cannot sign in to edit this application.</p>
@endif
</body></html>
