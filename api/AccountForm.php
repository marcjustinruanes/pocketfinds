<?php
declare(strict_types=1);

function accountForm(string $path): never {
    $email=(string)($_GET['email']??''); $identity=new Identity(null); $action='confirm-reset';
    if ($path==='account/email') {
        try { $ticket=json_decode(Illuminate\Support\Facades\Crypt::decryptString((string)($_GET['ticket']??'')),true); }
        catch(Throwable) { throw new ApiError('This confirmation link is invalid.',401); }
        ensure(($ticket['expires']??0)>time(),'This link expired. Request another confirmation.',401);
        $email=$ticket['email']; $identity=new Identity(App\Models\User::find($ticket['user_id'])); $action='confirm-email';
    }
    $message=''; $done=false;
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        try {
            (new MobileAuth($identity,''))->handle($action,['email'=>$email,'code'=>$_POST['code']??'','password'=>$_POST['password']??'']);
            $message='Saved. You can return to the PocketFinds app.'; $done=true;
        } catch(ApiError $e) { $message=$e->getMessage(); }
    }
    header('Content-Type: text/html; charset=utf-8');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'");
    header('Referrer-Policy: no-referrer');
    $escape=fn($v)=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
    echo '<!doctype html><meta name="viewport" content="width=device-width"><title>PocketFinds account</title><style>body{font:17px system-ui;max-width:420px;margin:60px auto;padding:24px}input,button{box-sizing:border-box;width:100%;padding:12px;margin:10px 0}button{background:#d9468f;color:white;border:0;border-radius:8px}</style><h1>PocketFinds</h1><p>'.$escape($message?:'Enter the code sent to '.$email).'</p>';
    if (!$done) {
        echo '<form method="post"><label>Email verification code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></label>';
        if ($action==='confirm-reset') echo '<label>New password<input type="password" name="password" minlength="8" maxlength="128" required autocomplete="new-password"></label>';
        echo '<button>Confirm</button></form>';
    }
    exit;
}
