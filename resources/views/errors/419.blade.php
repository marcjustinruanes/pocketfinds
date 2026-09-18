<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Session Expired — PocketFinds</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, sans-serif;
    background: #fff9fc;
    color: #18181b;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 16px;
    -webkit-font-smoothing: antialiased;
  }
  .card {
    background: #fff;
    border: 1px solid #e4e4e7;
    border-radius: 20px;
    padding: 48px 40px;
    max-width: 440px;
    width: 100%;
    text-align: center;
    box-shadow: 0 4px 24px rgba(0,0,0,.06);
  }
  .icon {
    width: 64px; height: 64px;
    border-radius: 50%;
    background: #fff0f8;
    display: grid; place-items: center;
    margin: 0 auto 24px;
    color: #d9468f;
  }
  h1 {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -.03em;
    margin-bottom: 10px;
  }
  p {
    font-size: 14px;
    line-height: 1.65;
    color: #71717a;
    margin-bottom: 28px;
  }
  p strong { color: #18181b; font-weight: 700; }
  .btn-back {
    display: inline-flex; align-items: center; gap: 8px;
    height: 44px; padding: 0 24px; border-radius: 11px;
    background: #d9468f; color: #fff;
    font-size: 14px; font-weight: 700; text-decoration: none;
    border: 0; cursor: pointer;
    transition: background .15s;
  }
  .btn-back:hover { background: #be3a7d; }
  .hint {
    margin-top: 20px;
    font-size: 12px;
    color: #a1a1aa;
    line-height: 1.6;
  }
</style>
</head>
<body>
<div class="card">
  <div class="icon">
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="1.8"
         stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/>
      <polyline points="12 6 12 12 16 14"/>
    </svg>
  </div>
  <h1>Session Expired</h1>
  <p>
    Your session timed out while the page was open.
    <strong>Don't worry — go back and try submitting again.</strong>
    Your details should still be in the form.
  </p>
  <a class="btn-back" href="javascript:history.back()">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2.5"
         stroke-linecap="round" stroke-linejoin="round">
      <path d="M19 12H5M12 5l-7 7 7 7"/>
    </svg>
    Go Back & Try Again
  </a>
  <p class="hint">
    If this keeps happening, try refreshing the page first before filling the form,
    or make sure you're still logged in.
  </p>
</div>
</body>
</html>
