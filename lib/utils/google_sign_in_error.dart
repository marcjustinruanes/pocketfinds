/// Recovery messages without exposing native account or token details.
String googleSignInErrorMessage(String code) => switch (code) {
  'clientConfigurationError' || 'providerConfigurationError' => 'Google sign-in is not configured for this app installation. Please contact support or verify your email with a code.',
  'interrupted' || 'uiUnavailable' =>
    'Google sign-in was interrupted. Return to the app and try again.',
  _ => 'Google could not sign you in. Check your internet connection and sign in to your Google account in device Settings, then try again. You can also verify your email with a code.',
};
