<?php
// index.php (Admin Login)
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u  = trim((string)($_POST['u'] ?? ''));
    $p  = (string)($_POST['p'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!kai_login_allowed($ip)) {
        $err = 'Too many sign-in attempts. Please wait 15 minutes.';
    } elseif (hash_equals(KAI_USER, $u) & hash_equals(KAI_PASS, $p)) {   // constant-time, both always checked
        session_regenerate_id(true); // Prevent session fixation
        $_SESSION['kai_admin'] = true;
        $_SESSION['kai_last']  = time();
        header('Location: dashboard.php');
        exit;
    } else {
        error_log('DharmaVera admin: failed sign-in from ' . $ip);
        usleep(400000);
        $err = 'Invalid username or password.';
    }
}

if (!empty($_SESSION['kai_admin'])) { header('Location: dashboard.php'); exit; }
if (isset($_GET['timeout']) && empty($err)) $err = 'You were signed out after 30 minutes without activity.';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DharmaVera Admin | Secure Login</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
  :root { 
      --bg: #0a0b10; 
      --card: rgba(22, 27, 34, 0.65); 
      --border: rgba(102, 252, 241, 0.15); 
      --accent: #66fcf1; 
      --text: #e6edf3; 
      --muted: #8b949e; 
  }
  * { box-sizing:border-box; }
  body { background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Segoe UI',system-ui,sans-serif; }
  .login-box { 
      background:var(--card); 
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border:1px solid var(--border); 
      box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
      border-radius:16px; 
      padding:2.5rem 2rem; 
      width:100%; 
      max-width:380px; 
  }
  .form-control { background:rgba(0,0,0,0.2); border-color:var(--border); color:var(--text); }
  .form-control:focus { background:rgba(0,0,0,0.4); border-color:var(--accent); color:var(--text); box-shadow:0 0 0 3px rgba(102,252,241,.15); }
  .form-label { color:var(--muted); font-size:.85rem; font-weight:600; letter-spacing: 0.5px; text-transform: uppercase; }
  .btn-primary { background:var(--accent); border-color:var(--accent); color:#000; font-weight:700; letter-spacing: 0.5px; }
  .btn-primary:hover { background:#45e8da; border-color:#45e8da; color:#000; }
</style>
</head>
<body>
<div class="login-box text-center">
  <div style="font-size:2rem;margin-bottom:.5rem">🔐</div>
  <h2 style="font-size:1.25rem;font-weight:700;margin-bottom:.25rem">DharmaVera Admin</h2>
  <p style="font-size:.8rem;color:var(--muted);margin-bottom:1.5rem">Restricted access</p>
  <?php if (!empty($err)): ?>
  <div class="alert alert-danger py-2" style="font-size:.85rem;background:#2d1010;border-color:#e05252;color:#ff9090"><?= htmlspecialchars($err) ?></div>
  <?php endif; ?>
  <form method="POST" autocomplete="off">
    <div class="mb-3 text-start">
      <label class="form-label">Username</label>
      <input type="text" name="u" class="form-control" autocomplete="username" maxlength="100" required autofocus>
    </div>
    <div class="mb-4 text-start">
      <label class="form-label">Password</label>
      <input type="password" name="p" class="form-control" autocomplete="current-password" required>
    </div>
    <button class="btn btn-primary w-100 py-2">Sign In</button>
  </form>
</div>
</body>
</html>
