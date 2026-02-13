<?php
require_once __DIR__ . '/includes/initialize.php';
if (is_logged_in()) redirect('/adminconsole/index.php');
$err='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $login_id=trim($_POST['login_id']??''); $pw=strval($_POST['password']??''); $tok=$_POST['csrf']??'';
  if(!verify_csrf($tok)){ $err='Invalid request. Please refresh and try again.'; }
  elseif($login_id===''||$pw===''){ $err='Please enter login and password.'; }
  else{
    $col=(strpos($login_id,'@')!==false)?'email':'mobile_no';
    $sql="SELECT id,name,email,mobile_no,password_hash,role_id,status FROM jos_admin_users WHERE status=1 AND $col=? LIMIT 1";
    if($st=$con->prepare($sql)){
      $st->bind_param('s',$login_id); $st->execute(); $u=$st->get_result()->fetch_assoc(); $st->close();
      if($u && password_verify($pw,$u['password_hash'])){
        session_regenerate_id(true); $_SESSION['admin_user']=$u; redirect($_GET['next']??'/adminconsole/index.php');
      } else $err='Invalid credentials.';
    } else $err='Server error. Try again.';
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Login</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/jpeg" href="/adminconsole/uploads/logo.jpeg">
<style>
  :root{
    --bg:#0f172a; --panel:#111827; --line:#1f2937;
    --text:#e2e8f0; --muted:#9ca3af; --brand:#3b82f6;
  }
  *{box-sizing:border-box}
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;margin:0;display:flex;align-items:center;justify-content:center;padding:16px}
  .card{background:var(--panel); border:1px solid var(--line); border-radius:18px; width:100%; max-width:460px;
        box-shadow:0 10px 40px rgba(0,0,0,.45); padding:22px 22px 18px}
  .brand{display:flex;align-items:center;justify-content:center; margin-bottom:12px}
  .brand img{max-width:220px; height:auto; display:block}
  h1.title{margin:6px 0 14px; text-align:center; font-size:24px}
  label{display:block; font-size:14px; margin:12px 0 6px}
  .control{width:100%; display:block; padding:12px 14px; border-radius:12px; border:1px solid #374151;
           background:#0b1220; color:var(--text); outline:none}
  .control:focus{border-color:#4b5563; box-shadow:0 0 0 3px rgba(59,130,246,.25)}
  .btn{width:100%; display:inline-block; text-align:center; padding:12px 14px; border-radius:12px; border:0;
       background:var(--brand); color:#fff; font-weight:700; margin-top:16px; cursor:pointer}
  .err{background:#7f1d1d; color:#fecaca; padding:10px 12px; border-radius:10px; margin:8px 0 12px; text-align:center}
  .hint{font-size:12px; color:var(--muted); text-align:center; margin-top:10px}
</style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <img
        src="/adminconsole/uploads/logo.jpeg?v=<?php echo time()%1000; ?>"
        onerror="this.onerror=null;this.src='/adminconsole/uploads/logo.png?v=<?php echo time()%1000; ?>';"
        alt="Pacific iConnect Logo">
    </div>

    <h1 class="title">Sign in</h1>

    <?php if($err): ?><div class="err"><?=htmlspecialchars($err)?></div><?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">

      <label for="login_id">Email or Mobile</label>
      <input id="login_id" name="login_id" class="control" value="<?=htmlspecialchars($_POST['login_id']??'')?>" required>

      <label for="password">Password</label>
      <input id="password" name="password" type="password" class="control" required>

      <button class="btn" type="submit">Login</button>
    </form>

    
  </div>
</body>
</html>
