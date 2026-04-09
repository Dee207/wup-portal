<?php
// ══════════════════════════════════════════════════
//  WUP PORTAL — LOGIN PAGE (PHP SESSION-BASED)
// ══════════════════════════════════════════════════
session_start();
require_once 'api/config.php';

// Build base URL dynamically (PHP_SELF gives the web path, not the filesystem path)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$base     = $protocol . '://' . $host . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

// Already logged in? Go straight to dashboard
if (!empty($_SESSION['wup_user'])) {
    header("Location: $base/pages/dashboard.php");
    exit;
}

$error = '';

// Handle login form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND status = "active" LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($pass, $user['password_hash'])) {
                // Generate session token and store in DB
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+8 hours'));
                $db->prepare('UPDATE users SET session_token = ?, token_expires = ? WHERE id = ?')
                   ->execute([$token, $expires, $user['id']]);

                // Store in PHP session
                $_SESSION['wup_user']  = [
                    'id'         => $user['id'],
                    'name'       => $user['name'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                    'department' => $user['department'],
                ];
                $_SESSION['wup_token'] = $token;

                header("Location: $base/pages/dashboard.php");
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please check your connection.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>WUP — Announcement Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --green-dark: #0d3b1e; --green: #155c2e; --green-mid: #1e7a3c;
      --gold: #c9a227; --gold-light: #e8bf4a;
      --white: #ffffff; --cream: #f8f5ee; --text: #1a2e1e;
      --muted: #7a9a80; --border: #d4e6d8;
      --danger: #c0392b;
    }
    html, body { height: 100%; }
    body { font-family: 'Outfit', sans-serif; background: var(--green-dark); color: var(--white); min-height: 100vh; display: flex; overflow: hidden; }

    /* LEFT */
    .left {
      width: 58%; position: relative;
      display: flex; flex-direction: column; justify-content: space-between;
      padding: 52px 60px;
      background:
        linear-gradient(160deg,
          rgba(10,40,20,0.92) 0%,
          rgba(13,59,30,0.78) 38%,
          rgba(13,59,30,0.68) 60%,
          rgba(10,40,20,0.95) 100%),
        url('assets/campus.jpg') center center / cover no-repeat;
      overflow: hidden;
    }
    .left::before { content:''; position:absolute; width:3px; height:120%; background:linear-gradient(to bottom,transparent,var(--gold),transparent); top:-10%; left:220px; transform:rotate(-8deg); opacity:0.22; }
    .geo { position:absolute; inset:0; pointer-events:none; overflow:hidden; }
    .geo span { position:absolute; border-radius:50%; border:1px solid rgba(201,162,39,0.1); }
    .geo span:nth-child(1){width:500px;height:500px;top:-180px;right:-80px;}
    .geo span:nth-child(2){width:320px;height:320px;top:-60px;right:60px;border-color:rgba(201,162,39,0.06);}
    .geo span:nth-child(3){width:700px;height:700px;bottom:-300px;left:-200px;border-color:rgba(255,255,255,0.03);}
    .left::after{content:'';position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,0.04) 1px,transparent 1px);background-size:28px 28px;pointer-events:none;}

    .brand-top{position:relative;z-index:2;display:flex;align-items:center;gap:16px;animation:fadeDown 0.7s ease both;}
    @keyframes fadeDown{from{opacity:0;transform:translateY(-16px)}to{opacity:1;transform:translateY(0)}}
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    @keyframes fadeIn{from{opacity:0}to{opacity:1}}

    .seal{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 3px rgba(201,162,39,0.35),0 12px 30px rgba(0,0,0,0.3);flex-shrink:0;overflow:hidden;background:#fff;}
    .seal img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
    .brand-text h1{font-family:'Cormorant Garamond',serif;font-size:18px;font-weight:600;color:var(--white);line-height:1.2;}
    .brand-text span{font-size:11px;color:var(--gold-light);letter-spacing:2px;text-transform:uppercase;font-weight:500;}

    .hero{position:relative;z-index:2;animation:fadeUp 0.8s 0.15s ease both;}
    .hero-eyebrow{display:inline-flex;align-items:center;gap:8px;background:rgba(201,162,39,0.15);border:1px solid rgba(201,162,39,0.3);color:var(--gold-light);font-size:11px;font-weight:600;letter-spacing:2px;text-transform:uppercase;padding:5px 14px;border-radius:100px;margin-bottom:24px;}
    .live-dot{width:7px;height:7px;background:var(--gold-light);border-radius:50%;animation:livePulse 1.8s infinite;}
    @keyframes livePulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.4;transform:scale(0.7)}}

    .hero h2{font-family:'Cormorant Garamond',serif;font-size:clamp(38px,4.5vw,62px);font-weight:700;line-height:1.1;color:var(--white);margin-bottom:22px;}
    .hero h2 em{font-style:normal;color:var(--gold-light);position:relative;}
    .hero h2 em::after{content:'';position:absolute;bottom:2px;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--gold),transparent);}
    .hero p{font-size:15px;color:rgba(255,255,255,0.5);line-height:1.75;max-width:420px;margin-bottom:40px;}

    .stats-bar{display:flex;gap:0;}
    .stat-item{padding:0 28px 0 0;margin-right:28px;border-right:1px solid rgba(255,255,255,0.1);}
    .stat-item:last-child{border-right:none;padding-right:0;margin-right:0;}
    .stat-num{font-family:'Cormorant Garamond',serif;font-size:30px;font-weight:700;color:var(--gold-light);line-height:1;}
    .stat-lbl{font-size:11px;color:rgba(255,255,255,0.35);letter-spacing:0.5px;margin-top:3px;}
    .left-foot{position:relative;z-index:2;font-size:12px;color:rgba(255,255,255,0.18);letter-spacing:0.5px;animation:fadeIn 1.2s 0.4s both;}

    /* RIGHT */
    .right{width:42%;background:var(--cream);display:flex;align-items:center;justify-content:center;padding:52px 48px;}
    .login-box{width:100%;max-width:380px;animation:fadeUp 0.7s 0.2s ease both;}
    .login-box h3{font-family:'Cormorant Garamond',serif;font-size:32px;font-weight:700;color:var(--text);margin-bottom:6px;}
    .login-box .sub{font-size:14px;color:var(--muted);margin-bottom:30px;}

    .fg{margin-bottom:16px;}
    .fg label{display:block;font-size:12px;font-weight:600;color:var(--text);letter-spacing:0.4px;text-transform:uppercase;margin-bottom:7px;}
    .fg input{width:100%;padding:12px 16px;border:1.5px solid var(--border);border-radius:9px;font-family:'Outfit',sans-serif;font-size:14px;color:var(--text);background:var(--white);outline:none;transition:border-color 0.18s,box-shadow 0.18s;}
    .fg input:focus{border-color:var(--green-mid);box-shadow:0 0 0 3px rgba(30,122,60,0.1);}

    .err{background:rgba(192,57,43,0.08);border:1px solid rgba(192,57,43,0.2);color:var(--danger);padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;}

    .btn-login{width:100%;padding:14px;background:linear-gradient(135deg,var(--green),var(--green-mid));color:var(--white);border:none;border-radius:10px;font-family:'Outfit',sans-serif;font-size:15px;font-weight:600;cursor:pointer;letter-spacing:0.4px;box-shadow:0 8px 22px rgba(21,92,46,0.28);transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:10px;}
    .btn-login:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(21,92,46,0.38);}

    .divider{display:flex;align-items:center;gap:10px;font-size:11px;color:#b0c0b5;margin:22px 0;text-transform:uppercase;letter-spacing:1px;}
    .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border);}

    .demo-box{background:var(--white);border:1px solid var(--border);border-radius:10px;padding:14px 16px;}
    .demo-box p{font-size:10px;font-weight:700;color:#aabfae;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:10px;}
    .demo-row{display:flex;justify-content:space-between;font-size:12px;color:#7a9a80;padding:3px 0;cursor:pointer;border-radius:4px;transition:background 0.1s;}
    .demo-row:hover{background:rgba(30,122,60,0.04);}
    .demo-row strong{color:var(--text);font-weight:600;}

    @media(max-width:800px){body{flex-direction:column;overflow:auto;}.left{width:100%;min-height:45vh;padding:36px 28px;}.right{width:100%;padding:36px 24px;}}
  </style>
</head>
<body>

<div class="left">
  <div class="geo"><span></span><span></span><span></span></div>
  <div class="brand-top">
    <div class="seal"><img src="assets/wup-logo.png" alt="WUP Seal"></div>
    <div class="brand-text">
      <h1>Wesleyan University<br>Philippines</h1>
      <span>Announcement Portal</span>
    </div>
  </div>
  <div class="hero">
    <div class="hero-eyebrow"><div class="live-dot"></div> Live System · A.Y. 2025–2026</div>
    <h2>One <em>Campus.</em><br>Every Voice.<br>Every Update.</h2>
    <p>The official centralized announcement platform of Wesleyan University Philippines — connecting students, faculty, staff, and parents in real time.</p>
    <div class="stats-bar">
      <div class="stat-item"><div class="stat-num">4</div><div class="stat-lbl">User Roles</div></div>
      <div class="stat-item"><div class="stat-num">Live</div><div class="stat-lbl">Real-Time Alerts</div></div>
      <div class="stat-item"><div class="stat-num">100%</div><div class="stat-lbl">Web-Based</div></div>
    </div>
  </div>
  <div class="left-foot">© 2025 Wesleyan University Philippines · Cabanatuan City, Nueva Ecija</div>
</div>

<div class="right">
  <div class="login-box">
    <h3>Sign In</h3>
    <p class="sub">Access your WUP portal account</p>

    <?php if ($error): ?>
      <div class="err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="fg">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="you@wup.edu.ph" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
      </div>
      <div class="fg">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required/>
      </div>

      <button type="submit" class="btn-login">Sign In to Portal →</button>
    </form>

    <div class="divider">Demo Accounts</div>
    <div class="demo-box">
      <p>Click a row to auto-fill</p>
      <div class="demo-row" onclick="fill('admin@wup.edu.ph','admin123')">    <strong>Admin</strong>   <span>admin@wup.edu.ph / admin123</span></div>
      <div class="demo-row" onclick="fill('teacher@wup.edu.ph','teach123')">  <strong>Teacher</strong> <span>teacher@wup.edu.ph / teach123</span></div>
      <div class="demo-row" onclick="fill('student@wup.edu.ph','stud123')">   <strong>Student</strong> <span>student@wup.edu.ph / stud123</span></div>
      <div class="demo-row" onclick="fill('parent@wup.edu.ph','par123')">     <strong>Parent</strong>  <span>parent@wup.edu.ph / par123</span></div>
    </div>
  </div>
</div>

<script>
  function fill(email, pass) {
    document.querySelector('input[name="email"]').value = email;
    document.querySelector('input[name="password"]').value = pass;
  }
</script>
</body>
</html>
