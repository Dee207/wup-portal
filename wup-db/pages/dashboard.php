<?php
// ══════════════════════════════════════════════════
//  WUP PORTAL — DASHBOARD (PHP SESSION GUARD)
// ══════════════════════════════════════════════════
session_start();

// If no session → kick to login immediately (server-side, no JS race)
if (empty($_SESSION['wup_user']) || empty($_SESSION['wup_token'])) {
    // PHP_SELF = web path (/wup-db/pages/dashboard.php); go up one level for root
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base     = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\');
    header("Location: $base/index.php");
    exit;
}

$token = $_SESSION['wup_token'];

// Always re-fetch from DB so avatar_url and other updated fields are current
require_once __DIR__ . '/../api/config.php';
$_db = getDB();
$_stmt = $_db->prepare('SELECT * FROM users WHERE session_token = ? AND token_expires > NOW()');
$_stmt->execute([$token]);
$user = $_stmt->fetch();
if (!$user) {
    // Token expired or invalid — kick to login
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base     = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\');
    header("Location: $base/index.php");
    exit;
}
// Refresh session so it stays in sync
$_SESSION['wup_user'] = $user;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard — WUP Announcement Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --green-dark:#071f0f; --green:#0d3b1e; --green-mid:#155c2e; --green-light:#1e7a3c;
      --gold:#c9a227; --gold-light:#e8bf4a; --gold-pale:rgba(201,162,39,0.15);
      --white:#ffffff; --cream:#f8f5ee; --off:rgba(255,255,255,0.06); --text:rgba(255,255,255,0.92);
      --muted:rgba(255,255,255,0.45); --border:rgba(255,255,255,0.1);
      --card-bg:rgba(10,40,18,0.72); --card-border:rgba(255,255,255,0.1);
      --sidebar-w:268px; --danger:#e05555; --purple:#9b7fd4; --sky:#5dade2;
    }
    body{
      font-family:'Inter',sans-serif;
      background: linear-gradient(160deg,#071f0f 0%,#0d3b1e 40%,#0d3b1e 70%,#071f0f 100%);
      color:var(--text);display:flex;min-height:100vh;position:relative;
    }
    /* Campus photo as fixed layer — GPU-composited, no scroll repaint */
    body::before{
      content:'';
      position:fixed;inset:0;z-index:-1;
      background: url('../assets/campus.jpg') center center / cover no-repeat;
      opacity:0.18;
      pointer-events:none;
    }
    .sidebar{width:var(--sidebar-w);background:var(--green-dark);min-height:100vh;display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:100;overflow-y:auto;}
    .sidebar::before{content:'';position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,0.04) 1px,transparent 1px);background-size:24px 24px;pointer-events:none;}
    .sb-brand{position:relative;padding:26px 22px 20px;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;gap:12px;}
    .sb-seal{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 2px rgba(201,162,39,0.4);flex-shrink:0;overflow:hidden;background:#fff;}
    .sb-seal img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
    .sb-name{font-family:'Cormorant Garamond',serif;font-size:14px;font-weight:600;color:var(--white);line-height:1.3;}
    .sb-sub{font-size:10px;color:var(--gold-light);letter-spacing:1.2px;text-transform:uppercase;}
    .sb-user{margin:14px 14px 6px;padding:12px;background:rgba(255,255,255,0.05);border-radius:10px;display:flex;align-items:center;gap:10px;position:relative;}
    .sb-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--green-mid),var(--green-light));display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;border:2px solid rgba(201,162,39,0.4);overflow:hidden;}
    .sb-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
    .avatar-upload-wrap{position:relative;display:inline-block;cursor:pointer;}
    .avatar-upload-wrap:hover .avatar-upload-overlay{opacity:1;}
    .avatar-upload-overlay{position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,0.55);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity 0.2s;pointer-events:none;}
    .avatar-upload-overlay span{font-size:20px;}
    .sb-uname{font-size:13px;font-weight:600;color:var(--white);}
    .role-tag{display:inline-block;font-size:10px;background:var(--gold-pale);color:var(--gold-light);border:1px solid rgba(201,162,39,0.3);padding:1px 8px;border-radius:100px;font-weight:600;letter-spacing:0.5px;text-transform:capitalize;margin-top:2px;}
    .nav-group-label{padding:14px 22px 4px;font-size:9px;font-weight:700;color:rgba(122,154,128,0.6);text-transform:uppercase;letter-spacing:2px;}
    .nav-item{display:flex;align-items:center;gap:11px;padding:10px 22px;color:rgba(255,255,255,0.55);font-size:13.5px;font-weight:500;cursor:pointer;border:none;background:none;width:100%;text-align:left;font-family:'Inter',sans-serif;transition:all 0.18s;border-left:3px solid transparent;}
    .nav-item .ni{font-size:16px;width:20px;text-align:center;}
    .nav-item:hover{color:var(--white);background:rgba(255,255,255,0.05);}
    .nav-item.active{color:var(--white);background:rgba(201,162,39,0.1);border-left-color:var(--gold);}
    .nav-badge{margin-left:auto;background:var(--danger);color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:100px;min-width:18px;text-align:center;}
    .sb-footer{margin-top:auto;padding:14px;border-top:1px solid rgba(255,255,255,0.07);position:relative;}
    .logout-btn{display:flex;align-items:center;gap:9px;padding:9px 12px;color:rgba(255,255,255,0.4);font-size:13px;cursor:pointer;border-radius:8px;transition:all 0.18s;border:none;background:none;font-family:'Inter',sans-serif;width:100%;}
    .logout-btn:hover{color:#e57373;background:rgba(192,57,43,0.1);}
    .main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}
    /* Topbar: backdrop-blur is fine here — only ONE element, not per-card */
    .topbar{background:rgba(7,31,15,0.88);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-bottom:1px solid var(--border);padding:15px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
    .topbar-left{display:flex;align-items:center;gap:14px;}
    .page-title{font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:700;color:var(--gold-light);}
    .hamburger{display:none;background:none;border:none;font-size:20px;cursor:pointer;color:var(--text);}
    .topbar-right{display:flex;align-items:center;gap:12px;}
    .search-wrap{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.08);border:1px solid var(--border);border-radius:8px;padding:8px 14px;}
    .search-wrap input{border:none;background:none;font-family:'Inter',sans-serif;font-size:13px;color:var(--text);outline:none;width:200px;}
    .search-wrap input::placeholder{color:var(--muted);}
    .icon-btn{width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,0.08);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:16px;position:relative;transition:all 0.18s;}
    .icon-btn:hover{background:var(--gold-pale);border-color:var(--gold);}
    .notif-dot{position:absolute;top:7px;right:7px;width:7px;height:7px;background:var(--danger);border-radius:50%;border:2px solid rgba(7,31,15,0.9);}
    /* notification dropdown */
    .notif-wrap{position:relative;}
    .notif-dropdown{display:none;position:absolute;top:calc(100% + 10px);right:0;width:340px;background:rgba(7,31,15,0.96);backdrop-filter:blur(16px);border:1px solid var(--border);border-radius:14px;box-shadow:0 12px 36px rgba(0,0,0,0.4);z-index:150;overflow:hidden;animation:fvIn 0.2s ease;}
    .notif-dropdown.open{display:block;}
    .notif-dhead{padding:14px 18px 10px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .notif-dhead h4{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:700;color:var(--gold-light);}
    .notif-dhead button{font-size:11px;color:var(--gold-light);background:none;border:none;cursor:pointer;font-family:'Inter',sans-serif;font-weight:600;}
    .notif-item{display:flex;gap:10px;padding:12px 18px;border-bottom:1px solid var(--border);cursor:pointer;transition:background 0.15s;align-items:flex-start;}
    .notif-item:last-child{border-bottom:none;}
    .notif-item:hover{background:rgba(255,255,255,0.05);}
    .notif-item.unread{background:rgba(201,162,39,0.07);}
    .notif-dot2{width:8px;height:8px;background:var(--danger);border-radius:50%;flex-shrink:0;margin-top:5px;}
    .notif-item-title{font-size:13px;font-weight:600;color:var(--text);margin-bottom:2px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .notif-item-meta{font-size:11px;color:var(--muted);}
    .notif-empty{padding:28px;text-align:center;color:var(--muted);font-size:13px;}
    .content{padding:30px 32px;flex:1;}
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:26px;}
    /* Cards: solid dark-green — NO backdrop-filter (too many cards = lag) */
    .sc{background:#0a2812;border:1px solid rgba(255,255,255,0.1);border-radius:14px;padding:22px 20px;position:relative;overflow:hidden;transition:transform 0.2s,box-shadow 0.2s;cursor:default;}
    .sc:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(0,0,0,0.3);}
    .sc-accent{position:absolute;top:0;left:0;width:100%;height:3px;}
    .sc.green .sc-accent{background:linear-gradient(90deg,var(--green-light),var(--gold));}
    .sc.gold  .sc-accent{background:linear-gradient(90deg,var(--gold),var(--gold-light));}
    .sc.blue  .sc-accent{background:linear-gradient(90deg,#2980b9,#5dade2);}
    .sc.red   .sc-accent{background:linear-gradient(90deg,#c0392b,#e74c3c);}
    .sc-icon{font-size:24px;margin-bottom:12px;}
    .sc-num{font-family:'Cormorant Garamond',serif;font-size:36px;font-weight:700;color:var(--gold-light);line-height:1;margin-bottom:4px;}
    .sc-lbl{font-size:13px;color:var(--muted);}
    .sc-badge{display:inline-block;font-size:11px;font-weight:600;margin-top:8px;padding:2px 8px;border-radius:100px;}
    .sc-badge.up{background:rgba(30,122,60,0.25);color:#7ddc9a;}
    .sc-badge.down{background:rgba(220,50,50,0.2);color:#ff9090;}
    .sc-badge.warn{background:rgba(201,162,39,0.2);color:var(--gold-light);}
    .two-col{display:grid;grid-template-columns:1fr 340px;gap:20px;margin-bottom:24px;}
    /* Cards: solid dark-green — NO backdrop-filter */
    .card{background:#0a2812;border:1px solid rgba(255,255,255,0.1);border-radius:14px;overflow:hidden;}
    .card-head{padding:18px 22px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);}
    .card-head h3{font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:700;color:var(--gold-light);}
    .card-link{font-size:12px;color:var(--gold-light);font-weight:600;cursor:pointer;background:none;border:none;font-family:'Inter',sans-serif;text-decoration:none;transition:color 0.15s;}
    .card-link:hover{color:var(--gold);text-decoration:underline;}
    .ann-row{display:flex;gap:14px;padding:15px 22px;border-bottom:1px solid var(--border);cursor:pointer;transition:background 0.15s;align-items:flex-start;}
    .ann-row:last-child{border-bottom:none;}
    .ann-row:hover{background:rgba(255,255,255,0.05);}
    .ann-stripe{width:4px;min-height:52px;border-radius:4px;flex-shrink:0;margin-top:2px;}
    .ann-stripe.event{background:var(--sky);} .ann-stripe.exam{background:var(--danger);} .ann-stripe.notice{background:var(--gold);} .ann-stripe.activity{background:var(--green-light);} .ann-stripe.holiday{background:var(--purple);}
    .ann-body{flex:1;}
    .ann-meta{display:flex;align-items:center;gap:8px;margin-bottom:5px;flex-wrap:wrap;}
    .cat-chip{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;padding:2px 8px;border-radius:100px;}
    .cat-chip.event{background:rgba(93,173,226,0.18);color:var(--sky);} .cat-chip.exam{background:rgba(224,85,85,0.18);color:var(--danger);} .cat-chip.notice{background:rgba(201,162,39,0.2);color:var(--gold-light);} .cat-chip.activity{background:rgba(30,122,60,0.2);color:#7ddc9a;} .cat-chip.holiday{background:rgba(155,127,212,0.2);color:var(--purple);}
    .ann-date{font-size:11px;color:var(--muted);}
    .pin-badge{font-size:10px;color:var(--gold-light);background:var(--gold-pale);border:1px solid rgba(201,162,39,0.3);padding:1px 7px;border-radius:100px;}
    .read-badge{font-size:10px;color:#7ddc9a;background:rgba(30,122,60,0.18);border:1px solid rgba(30,122,60,0.3);padding:1px 7px;border-radius:100px;}
    .ann-title{font-size:14px;font-weight:600;color:rgba(255,255,255,0.9);margin-bottom:4px;}
    .ann-preview{font-size:12px;color:var(--muted);line-height:1.55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .aud-pills{display:flex;gap:4px;margin-top:7px;}
    .aud-pill{font-size:10px;color:var(--muted);background:rgba(255,255,255,0.06);border:1px solid var(--border);padding:1px 8px;border-radius:100px;}
    .qa-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:14px;}
    .qa-card{padding:15px 12px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;transition:all 0.18s;text-align:center;background:rgba(255,255,255,0.05);}
    .qa-card:hover{border-color:var(--gold);background:var(--gold-pale);transform:translateY(-1px);}
    .qa-icon{font-size:22px;margin-bottom:6px;} .qa-lbl{font-size:12px;font-weight:600;color:var(--text);}
    .ev-item{display:flex;gap:12px;padding:12px 14px;align-items:center;}
    .ev-datebox{width:44px;height:44px;background:linear-gradient(135deg,var(--green),var(--green-light));border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;}
    .ev-day{font-size:16px;font-weight:700;color:var(--white);line-height:1;}
    .ev-mon{font-size:9px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:0.5px;}
    .ev-name{font-size:13px;font-weight:600;color:rgba(255,255,255,0.9);} .ev-time{font-size:11px;color:var(--muted);margin-top:2px;}
    .events-grid{padding:18px;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));max-width:100%;gap:14px;}
    @media(min-width:900px){.events-grid{grid-template-columns:repeat(3,1fr);}}
    .ev-full-card{background:#0a2812;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:18px;transition:all 0.2s;}
    .ev-full-card:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(0,0,0,0.3);border-color:var(--gold);}
    .cf{padding:24px;} .cf-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .fg2{margin-bottom:16px;}
    .fg2 label{display:block;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px;}
    .fg2 input,.fg2 select,.fg2 textarea{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:'Inter',sans-serif;font-size:14px;color:var(--text);background:rgba(255,255,255,0.08);outline:none;transition:border-color 0.18s;}
    .fg2 input:focus,.fg2 select:focus,.fg2 textarea:focus{border-color:var(--gold);background:rgba(255,255,255,0.12);}
    .fg2 select option{background:#0d3b1e;color:#fff;}
    .fg2 textarea{resize:vertical;min-height:90px;}
    .cf-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:4px;}
    .btn-primary{padding:10px 22px;background:linear-gradient(135deg,var(--gold),#a8831a);color:var(--green-dark);border:none;border-radius:8px;font-family:'Inter',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;box-shadow:0 6px 18px rgba(201,162,39,0.3);}
    .btn-primary:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(201,162,39,0.4);}
    .btn-primary:disabled{opacity:0.6;cursor:not-allowed;transform:none;}
    .btn-sec{padding:10px 22px;background:rgba(255,255,255,0.08);color:var(--text);border:1.5px solid var(--border);border-radius:8px;font-family:'Inter',sans-serif;font-size:14px;cursor:pointer;transition:all 0.18s;}
    .btn-sec:hover{border-color:var(--gold);color:var(--gold-light);}
    .btn-danger{padding:8px 18px;background:rgba(224,85,85,0.15);color:#ff9090;border:1px solid rgba(224,85,85,0.3);border-radius:8px;font-family:'Inter',sans-serif;font-size:13px;cursor:pointer;transition:all 0.18s;}
    .btn-danger:hover{background:rgba(224,85,85,0.25);}
    .filter-bar{display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;}
    .filter-bar select{padding:9px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:'Inter',sans-serif;font-size:13px;background:var(--card-bg);backdrop-filter:blur(8px);cursor:pointer;outline:none;color:var(--text);transition:border-color 0.18s;}
    .filter-bar select option{background:#0d3b1e;}
    .filter-bar select:focus{border-color:var(--gold);}
    /* ── Latest News Section ── */
    .lne-section{margin-bottom:32px;}
    .lne-heading{text-align:center;margin-bottom:22px;position:relative;}
    .lne-heading h2{font-family:'Cormorant Garamond',serif;font-size:28px;font-weight:700;letter-spacing:4px;text-transform:uppercase;background:linear-gradient(135deg,var(--gold-light),#fff 60%,var(--gold));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;display:inline-block;}
    .lne-heading::before,.lne-heading::after{content:'';position:absolute;top:50%;width:calc(50% - 180px);height:1px;background:linear-gradient(90deg,transparent,var(--gold));}
    .lne-heading::before{left:0;}
    .lne-heading::after{right:0;background:linear-gradient(270deg,transparent,var(--gold));}
    .lne-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;}
    @media(min-width:960px){.lne-grid{grid-template-columns:repeat(3,1fr);}}
    .lne-card{background:#0a2812;border:1px solid rgba(255,255,255,0.1);border-radius:14px;overflow:hidden;cursor:pointer;transition:transform 0.22s,box-shadow 0.22s,border-color 0.22s;position:relative;}
    .lne-card:hover{transform:translateY(-4px);box-shadow:0 14px 36px rgba(0,0,0,0.45);border-color:rgba(201,162,39,0.4);}
    .lne-img-wrap{position:relative;width:100%;height:190px;overflow:hidden;background:linear-gradient(135deg,#0a2812,#155c2e);}
    .lne-img-wrap img{width:100%;height:100%;object-fit:cover;display:block;transition:transform 0.4s ease;}
    .lne-card:hover .lne-img-wrap img{transform:scale(1.05);}
    .lne-img-placeholder{width:100%;height:190px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0a2812,#0d3b1e);font-size:48px;}
    .lne-badge{position:absolute;bottom:10px;left:12px;background:linear-gradient(135deg,var(--gold),#a8831a);color:var(--green-dark);font-size:9px;font-weight:800;letter-spacing:2px;text-transform:uppercase;padding:3px 10px;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.4);}
    .lne-body{padding:16px 18px 18px;}
    .lne-cat{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;color:var(--gold-light);margin-bottom:7px;display:flex;align-items:center;gap:6px;}
    .lne-cat::before{content:'';display:inline-block;width:12px;height:2px;background:var(--gold);border-radius:2px;}
    .lne-title{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:700;color:var(--white);line-height:1.35;margin-bottom:8px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
    .lne-preview{font-size:12px;color:var(--muted);line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:10px;}
    .lne-meta{display:flex;align-items:center;justify-content:space-between;font-size:11px;color:var(--muted);border-top:1px solid var(--border);padding-top:10px;margin-top:6px;}
    .lne-read-more{font-size:11px;font-weight:700;color:var(--gold-light);background:none;border:none;cursor:pointer;font-family:'Inter',sans-serif;display:flex;align-items:center;gap:4px;transition:color 0.15s;padding:0;}
    .lne-read-more:hover{color:var(--gold);}
    .lne-load-more{display:block;margin:18px auto 0;padding:10px 28px;background:rgba(201,162,39,0.12);border:1.5px solid rgba(201,162,39,0.4);color:var(--gold-light);font-family:'Inter',sans-serif;font-size:13px;font-weight:600;border-radius:100px;cursor:pointer;transition:all 0.2s;letter-spacing:0.5px;}
    .lne-load-more:hover{background:rgba(201,162,39,0.22);transform:translateY(-1px);}
    .utbl{width:100%;border-collapse:collapse;font-size:13.5px;}
    .utbl th{text-align:left;padding:11px 16px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--muted);border-bottom:2px solid var(--border);}
    .utbl td{padding:13px 16px;border-bottom:1px solid var(--border);}
    .utbl tr:last-child td{border-bottom:none;}
    .utbl tr:hover td{background:rgba(255,255,255,0.04);}
    /* Split pane: solid dark backgrounds — NO per-pane backdrop-filter */
    .ann-split{display:flex;gap:0;overflow:hidden;border-radius:14px;border:1px solid rgba(255,255,255,0.1);background:#0a2812;}
    .ann-list-pane{flex:1;min-width:0;overflow-y:auto;transition:max-width 0.32s cubic-bezier(.4,0,.2,1);}
    .ann-detail-pane{width:0;overflow:hidden;border-left:0 solid rgba(255,255,255,0.1);transition:width 0.32s cubic-bezier(.4,0,.2,1),border-left-width 0.32s;background:#061710;display:flex;flex-direction:column;}
    .ann-split.detail-open .ann-detail-pane{width:54%;min-width:340px;border-left-width:1px;}
    .ann-split.detail-open .ann-list-pane{max-width:46%;}
    .ann-split.detail-open .ann-preview{display:none;}
    .ann-split.detail-open .ann-row{padding:11px 16px;}
    .ann-split.detail-open .ann-row .aud-pills{display:none;}
    .ann-split.detail-open .ann-thumb{display:none;}
    .ann-row.selected{background:rgba(201,162,39,0.1);border-left:3px solid var(--gold);}
    .ann-row.selected .ann-title{color:var(--gold-light);}
    .dp-header{background:linear-gradient(145deg,rgba(7,31,15,0.95),rgba(13,59,30,0.9));padding:26px 26px 22px;position:relative;flex-shrink:0;border-bottom:1px solid var(--border);}
    .dp-cat{font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--gold-light);margin-bottom:6px;}
    .dp-title{font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:700;color:var(--white);line-height:1.3;padding-right:36px;}
    .dp-close{position:absolute;top:14px;right:14px;background:rgba(255,255,255,0.1);border:none;color:var(--white);width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:18px;line-height:1;transition:background 0.15s;display:flex;align-items:center;justify-content:center;}
    .dp-close:hover{background:rgba(255,255,255,0.22);}
    .dp-body{padding:24px;overflow-y:auto;flex:1;}
    .dp-img{width:100%;max-height:260px;object-fit:cover;border-radius:10px;margin-bottom:20px;display:block;}
    .dp-content{font-size:15px;line-height:1.85;color:var(--text);white-space:pre-wrap;word-break:break-word;margin-bottom:24px;}
    .dp-meta{display:flex;gap:16px;flex-wrap:wrap;padding-top:16px;border-top:1px solid var(--border);font-size:12px;color:var(--muted);}
    .dp-meta span{display:flex;align-items:center;gap:5px;}
    .dp-actions{display:flex;gap:10px;margin-top:18px;flex-wrap:wrap;}
    .dp-loading{display:flex;align-items:center;justify-content:center;height:200px;}
    .ann-thumb{width:68px;height:56px;object-fit:cover;border-radius:8px;flex-shrink:0;border:1px solid var(--border);}
    @media(max-width:800px){.ann-split.detail-open .ann-list-pane{display:none;}.ann-split.detail-open .ann-detail-pane{width:100%;min-width:0;}}
    /* Full-view modal: blur on overlay only (shown rarely, not every card) */
    .fv-overlay{display:none;position:fixed;inset:0;background:rgba(2,12,6,0.85);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:300;overflow-y:auto;padding:32px 20px;}
    .fv-overlay.open{display:flex;align-items:flex-start;justify-content:center;}
    .fv-box{background:#0a2812;border:1px solid rgba(255,255,255,0.12);border-radius:20px;overflow:hidden;max-width:780px;width:100%;animation:fvIn 0.28s cubic-bezier(.4,0,.2,1);box-shadow:0 24px 60px rgba(0,0,0,0.5);}
    @keyframes fvIn{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
    .fv-head{background:linear-gradient(145deg,rgba(7,20,10,0.98),rgba(13,59,30,0.95));padding:36px 36px 30px;position:relative;border-bottom:1px solid var(--border);}
    .fv-cat{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--gold-light);margin-bottom:10px;}
    .fv-title{font-family:'Cormorant Garamond',serif;font-size:32px;font-weight:700;color:var(--white);line-height:1.25;padding-right:44px;}
    .fv-close{position:absolute;top:18px;right:18px;background:rgba(255,255,255,0.1);border:none;color:var(--white);width:34px;height:34px;border-radius:10px;cursor:pointer;font-size:20px;line-height:1;transition:background 0.15s;display:flex;align-items:center;justify-content:center;}
    .fv-close:hover{background:rgba(255,255,255,0.22);}
    .fv-body{padding:36px;}
    .fv-img{width:100%;max-height:380px;object-fit:cover;border-radius:12px;margin-bottom:28px;display:block;}
    .fv-divider{height:1px;background:var(--border);margin:24px 0;}
    .fv-text{font-size:16px;line-height:2;color:var(--text);white-space:pre-wrap;word-break:break-word;}
    .fv-meta{display:flex;gap:20px;flex-wrap:wrap;font-size:13px;color:var(--muted);}
    .fv-meta span{display:flex;align-items:center;gap:6px;}
    .profile-grid{display:grid;grid-template-columns:300px 1fr;gap:20px;}
    .profile-card{background:#0a2812;border:1px solid rgba(255,255,255,0.1);border-radius:14px;overflow:hidden;}
    .profile-banner{height:90px;background:linear-gradient(135deg,rgba(7,31,15,0.9),rgba(13,59,30,0.8)),url('../assets/campus.jpg') center/cover;}
    .profile-avatar-wrap{padding:0 22px 20px;margin-top:-32px;}
    .big-avatar{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#a8831a);border:3px solid rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:10px;}
    .profile-name{font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:700;color:var(--gold-light);}
    .profile-role{font-size:13px;color:var(--muted);margin-top:2px;}
    .profile-details{padding:18px 22px;border-top:1px solid var(--border);}
    .pd-row{display:flex;justify-content:space-between;font-size:13px;padding:7px 0;border-bottom:1px solid var(--border);}
    .pd-row:last-child{border-bottom:none;} .pd-label{color:var(--muted);font-size:12px;} .pd-val{font-weight:500;color:var(--text);}
    /* ── Image Upload Widget ── */
    .img-dropzone{border:2px dashed var(--border);border-radius:12px;padding:28px 20px;text-align:center;cursor:pointer;transition:all 0.2s;background:rgba(255,255,255,0.03);}
    .img-dropzone:hover,.img-dropzone.dragover{border-color:var(--gold);background:rgba(201,162,39,0.07);}
    .toast{position:fixed;bottom:28px;right:28px;background:rgba(7,31,15,0.95);backdrop-filter:blur(12px);color:var(--white);padding:14px 20px;border-radius:10px;font-size:14px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,0.4);display:flex;align-items:center;gap:10px;border-left:4px solid var(--gold);transform:translateY(80px);opacity:0;transition:all 0.3s;z-index:999;}
    .toast.show{transform:translateY(0);opacity:1;}
    .loading{padding:40px;text-align:center;color:var(--muted);}
    .loading-spinner{width:28px;height:28px;border:3px solid rgba(255,255,255,0.1);border-top-color:var(--gold);border-radius:50%;animation:spin 0.7s linear infinite;margin:0 auto 12px;}
    @keyframes spin{to{transform:rotate(360deg)}}
    .empty{padding:40px;text-align:center;color:var(--muted);font-size:14px;} .empty-icon{font-size:36px;margin-bottom:10px;}
    @media(max-width:1100px){.stats-grid{grid-template-columns:1fr 1fr;}}
    @media(max-width:900px){.two-col{grid-template-columns:1fr;}.profile-grid{grid-template-columns:1fr;}}
    @media(max-width:768px){.sidebar{transform:translateX(-100%);transition:transform 0.3s;}.sidebar.open{transform:translateX(0);}.main{margin-left:0;}.hamburger{display:block;}.stats-grid{grid-template-columns:1fr 1fr;}.content{padding:20px;}.cf-row{grid-template-columns:1fr;}.topbar{padding:13px 18px;}}
    @media(max-width:500px){.stats-grid{grid-template-columns:1fr;}.search-wrap{display:none;}}
  </style>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="sb-brand">
    <div class="sb-seal"><img src="../assets/wup-logo.png" alt="WUP Seal"></div>
    <div><div class="sb-name">Wesleyan University<br>Philippines</div><div class="sb-sub">Portal</div></div>
  </div>
  <div class="sb-user">
    <div class="sb-avatar" id="sbAvatar"><?php
      $avatarUrl = $user['avatar_url'] ?? null;
      if ($avatarUrl): ?><img src="../<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" id="sbAvatarImg"><?php
      else: echo ['admin'=>'👑','teacher'=>'👨‍🏫','student'=>'🎓','parent'=>'👨‍👩‍👦'][$user['role']] ?? '👤';
      endif; ?></div>
    <div><div class="sb-uname"><?= htmlspecialchars($user['name']) ?></div><div class="role-tag"><?= htmlspecialchars($user['role']) ?></div></div>
  </div>

  <div class="nav-group-label">Main</div>
  <button class="nav-item active" onclick="goTo('dashboard',this)"><span class="ni">🏠</span> Dashboard</button>
  <button class="nav-item" onclick="goTo('announcements',this)"><span class="ni">📢</span> Announcements <span class="nav-badge" id="unreadBadge">0</span></button>
  <button class="nav-item" onclick="goTo('events',this)"><span class="ni">📅</span> Events</button>
  <?php if ($user['role'] === 'admin'): ?>
  <button class="nav-item" onclick="goTo('archive',this)"><span class="ni">🗂️</span> Archive</button>
  <?php endif; ?>
  <button class="nav-item" onclick="goTo('profile',this)"><span class="ni">👤</span> My Profile</button>

  <?php if ($user['role'] === 'admin'): ?>
  <div class="nav-group-label">Administration</div>
  <button class="nav-item" onclick="goTo('create',this)"><span class="ni">✏️</span> Post Announcement</button>
  <button class="nav-item" onclick="goTo('users',this)"><span class="ni">👥</span> Manage Users</button>
  <?php endif; ?>

  <div class="sb-footer">
    <form method="POST" action="../logout.php">
      <button type="submit" class="logout-btn">🚪 Sign Out</button>
    </form>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <button class="hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
      <div class="page-title" id="pageTitle">Dashboard</div>
    </div>
    <div class="topbar-right">
      <div class="search-wrap">
        <span style="font-size:14px;color:var(--muted)">🔍</span>
        <input type="text" placeholder="Search announcements…" id="searchInput" oninput="onSearch()">
      </div>
      <div class="notif-wrap">
        <div class="icon-btn" title="Notifications" onclick="toggleNotif()">🔔<div class="notif-dot" id="notifDot"></div></div>
        <div class="notif-dropdown" id="notifDropdown">
          <div class="notif-dhead"><h4>🔔 Notifications</h4><button onclick="markAllRead()">Mark all read</button></div>
          <div id="notifList"><div class="notif-empty">Loading…</div></div>
        </div>
      </div>
    </div>
  </div>

  <div class="content">
    <!-- DASHBOARD -->
    <div id="sec-dashboard">
      <div class="stats-grid">
        <div class="sc green" style="cursor:pointer" onclick="goTo('announcements',document.querySelector('.nav-item[onclick*=announcements]'))" title="View all announcements"><div class="sc-accent"></div><div class="sc-icon">📢</div><div class="sc-num" id="scTotal">—</div><div class="sc-lbl">Total Announcements</div><span class="sc-badge up" id="scTotalBadge">Loading…</span></div>
        <div class="sc gold"  style="cursor:pointer" onclick="goTo('events',document.querySelector('.nav-item[onclick*=events]'))"        title="View all events">        <div class="sc-accent"></div><div class="sc-icon">📅</div><div class="sc-num" id="scEvents">—</div><div class="sc-lbl">Upcoming Events</div><span class="sc-badge warn">School calendar</span></div>
        <div class="sc blue"  style="cursor:pointer" onclick="goTo('announcements',document.querySelector('.nav-item[onclick*=announcements]'))" title="View announcements"><div class="sc-accent"></div><div class="sc-icon">✅</div><div class="sc-num" id="scRead">—</div><div class="sc-lbl">Read Announcements</div><span class="sc-badge up">Good progress</span></div>
        <div class="sc red" style="cursor:pointer" onclick="(function(){const n=parseInt(document.getElementById('scUnread').textContent)||0;if(n===0){showToast('✅ No unread announcements at the moment!');}else{goTo('announcements',document.querySelector('.nav-item[onclick*=announcements]'));}})();" title="View unread announcements"><div class="sc-accent"></div><div class="sc-icon">🔔</div><div class="sc-num" id="scUnread">—</div><div class="sc-lbl">Unread</div><span class="sc-badge down">Needs attention</span></div>
      </div>
      <div class="two-col">
        <div class="card">
          <div class="card-head"><h3>Recent Announcements</h3><button class="card-link" onclick="goTo('announcements',null)">View All →</button></div>
          <div id="dashAnnList"><div class="loading"><div class="loading-spinner"></div>Loading…</div></div>
        </div>
        <div class="card">
          <div class="card-head"><h3>Upcoming Events</h3></div>
          <div id="upcomingList"><div class="loading"><div class="loading-spinner"></div>Loading…</div></div>
        </div>
      </div>
    </div>

    <!-- ANNOUNCEMENTS -->
    <div id="sec-announcements" style="display:none">
      <!-- LATEST NEWS & EVENTS GRID -->
      <div class="lne-section">
        <div class="lne-heading"><h2>Latest News and Events</h2></div>
        <div class="lne-grid" id="lneGrid"><div class="loading"><div class="loading-spinner"></div>Loading…</div></div>
        <button class="lne-load-more" id="lneLoadMore" style="display:none" onclick="loadMoreNews()">View All Announcements ↓</button>
      </div>
      <!-- FILTER + LIST -->
      <div class="filter-bar">
        <select id="fCat" onchange="renderAnnPage()"><option value="">All Categories</option><option value="event">Event</option><option value="exam">Exam</option><option value="notice">Notice</option><option value="activity">Activity</option><option value="holiday">Holiday</option></select>
        <select id="fAud" onchange="renderAnnPage()"><option value="">All Audiences / Everyone</option><option value="student">Students</option><option value="teacher">Teachers</option><option value="parent">Parents</option></select>
        <select id="fSort" onchange="renderAnnPage()"><option value="newest">Newest First</option><option value="oldest">Oldest First</option></select>
      </div>
      <div class="card">
        <div class="card-head"><h3>All Announcements</h3><span id="annCount" style="font-size:13px;color:var(--muted)"></span></div>
        <div id="annPageList"><div class="loading"><div class="loading-spinner"></div>Loading…</div></div>
      </div>
    </div>

    <!-- EVENTS -->
    <div id="sec-events" style="display:none">
      <div class="card">
        <div class="card-head"><h3>School Events — A.Y. 2025–2026</h3></div>
        <div class="events-grid" id="eventsGrid"><div class="loading"><div class="loading-spinner"></div>Loading…</div></div>
      </div>
      <!-- CAMPUS MAP -->
      <div class="card" style="margin-top:20px;overflow:hidden;">
        <div class="card-head" style="background:linear-gradient(135deg,var(--green-dark),var(--green));padding:16px 22px;">
          <h3 style="color:var(--white);font-size:18px;">📍 Campus Map</h3>
          <a href="../assets/campus-map.jpg" target="_blank" class="card-link" style="color:var(--gold-light);font-size:12px;font-weight:600;">Open Full Size ↗</a>
        </div>
        <div style="overflow:hidden;line-height:0;cursor:zoom-in;" onclick="window.open('../assets/campus-map.jpg','_blank')">
          <img src="../assets/campus-map.jpg" alt="WUP Campus Map — Mabini Extension, Cabanatuan City"
               style="width:100%;display:block;transition:transform 0.4s ease;"
               onmouseover="this.style.transform='scale(1.03)'"
               onmouseout="this.style.transform='scale(1)'">
        </div>
        <div style="padding:10px 22px 14px;font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;">
          <span>🏫</span> Wesleyan University-Philippines · Mabini Extension, Cabanatuan City, Nueva Ecija
          <span style="margin-left:auto;font-size:11px;">Click map to enlarge</span>
        </div>
      </div>
    </div>

    <!-- ARCHIVE -->
    <div id="sec-archive" style="display:none">
      <div class="card">
        <div class="card-head"><h3>Archived Announcements</h3><span style="font-size:12px;color:var(--muted)">Older posts for reference</span></div>
        <div id="archiveList"><div class="loading"><div class="loading-spinner"></div>Loading…</div></div>
      </div>
    </div>

    <!-- CREATE (admin only) -->
    <?php if ($user['role'] === 'admin'): ?>
    <div id="sec-create" style="display:none">
      <div class="card">
        <div class="card-head"><h3>Post New Announcement</h3></div>
        <div class="cf">
          <div class="cf-row">
            <div class="fg2"><label>Title *</label><input type="text" id="cfTitle" placeholder="Announcement title…"/></div>
            <div class="fg2"><label>Category *</label><select id="cfCat"><option value="event">Event</option><option value="exam">Exam</option><option value="notice">Notice</option><option value="activity">Activity</option><option value="holiday">Holiday</option></select></div>
          </div>
          <div class="fg2"><label>Announcement Content *</label><textarea id="cfContent" placeholder="Write the full announcement details here…"></textarea></div>
          <!-- IMAGE UPLOAD WIDGET -->
          <div class="fg2">
            <label>Image <span style="font-weight:400;color:var(--muted)">(optional — upload a file or paste a URL)</span></label>
            <input type="file" id="cfImageFile" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" onchange="uploadAnnouncementImage(this)">
            <div id="cfDropZone" class="img-dropzone" onclick="document.getElementById('cfImageFile').click()"
                 ondragover="event.preventDefault();this.classList.add('dragover')"
                 ondragleave="this.classList.remove('dragover')"
                 ondrop="handleImgDrop(event)">
              <div id="cfDropZoneInner">
                <div style="font-size:36px;margin-bottom:8px;">🖼️</div>
                <div style="font-weight:600;color:var(--text);margin-bottom:4px;">Click to upload or drag &amp; drop</div>
                <div style="font-size:12px;color:var(--muted);">JPG, PNG, GIF, WebP — max 5 MB</div>
              </div>
            </div>
            <div id="cfImgProgress" style="display:none;margin-top:8px;">
              <div style="height:4px;background:var(--border);border-radius:4px;overflow:hidden;">
                <div id="cfImgProgressBar" style="height:100%;width:0%;background:linear-gradient(90deg,var(--gold),var(--gold-light));transition:width 0.3s;"></div>
              </div>
              <div id="cfImgProgressText" style="font-size:11px;color:var(--muted);margin-top:4px;">Uploading…</div>
            </div>
            <div id="cfImagePreview" style="display:none;margin-top:10px;position:relative;">
              <img id="cfImgThumb" src="" alt="preview" style="max-height:180px;width:100%;object-fit:cover;border-radius:10px;border:1px solid var(--border);display:block;">
              <button onclick="clearCFImage()" title="Remove image" style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.6);color:#fff;border:none;border-radius:50%;width:28px;height:28px;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">×</button>
            </div>
            <div style="display:flex;align-items:center;gap:10px;margin-top:10px;">
              <div style="flex:1;height:1px;background:var(--border);"></div>
              <span style="font-size:11px;color:var(--muted);">or paste URL</span>
              <div style="flex:1;height:1px;background:var(--border);"></div>
            </div>
            <input type="url" id="cfImage" placeholder="https://example.com/image.jpg" oninput="previewCFImage(this.value)" style="margin-top:10px;"/>
          </div>
          <div class="cf-row">
            <div class="fg2"><label>Target Audience</label><select id="cfAud"><option value="all">Everyone</option><option value="student">Students Only</option><option value="teacher">Teachers Only</option><option value="parent">Parents Only</option><option value="staff">Staff Only</option></select></div>
            <div class="fg2"><label>Date</label><input type="date" id="cfDate"/></div>
          </div>
          <div class="fg2" style="display:flex;align-items:center;gap:10px">
            <input type="checkbox" id="cfPinned" style="accent-color:var(--gold);width:16px;height:16px"/>
            <label for="cfPinned" style="font-size:13px;font-weight:600;color:var(--text);cursor:pointer">📌 Pin this announcement</label>
          </div>
          <div class="cf-actions">
            <button class="btn-sec" onclick="clearCF()">Clear</button>
            <button class="btn-primary" id="publishBtn" onclick="publishAnn()">📢 Publish Announcement</button>
          </div>
        </div>
      </div>
    </div>

    <!-- USERS (admin only) -->
    <div id="sec-users" style="display:none">
      <div class="card" style="margin-bottom:20px">
        <div class="card-head"><h3>Add New User</h3></div>
        <div class="cf">
          <div class="cf-row">
            <div class="fg2"><label>Full Name *</label><input type="text" id="uName" placeholder="Full name"/></div>
            <div class="fg2"><label>Email *</label><input type="email" id="uEmail" placeholder="email@wup.edu.ph"/></div>
          </div>
          <div class="cf-row">
            <div class="fg2"><label>Password *</label><input type="password" id="uPass" placeholder="Minimum 8 characters"/></div>
            <div class="fg2"><label>Role</label><select id="uRole"><option value="student">Student</option><option value="teacher">Teacher</option><option value="parent">Parent</option><option value="admin">Admin</option></select></div>
          </div>
          <div class="fg2"><label>Department / Course</label><input type="text" id="uDept" placeholder="e.g. BS Information Technology"/></div>
          <div class="cf-actions"><button class="btn-primary" onclick="addUser()">➕ Add User</button></div>
        </div>
      </div>
      <div class="card">
        <div class="card-head"><h3>Registered Users</h3><span id="userCount" style="font-size:13px;color:var(--muted)"></span></div>
        <div style="padding:18px;overflow-x:auto"><table class="utbl"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Action</th></tr></thead><tbody id="usersBody"></tbody></table></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- PROFILE -->
    <div id="sec-profile" style="display:none">
      <div class="profile-grid">
        <div>
          <div class="profile-card">
            <div class="profile-banner"></div>
            <div class="profile-avatar-wrap">
              <input type="file" id="avatarFileInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" onchange="uploadAvatar(this)">
              <div class="avatar-upload-wrap" onclick="document.getElementById('avatarFileInput').click()" title="Click to change profile picture" style="margin-bottom:6px;">
                <div class="big-avatar" id="profAvatar" style="overflow:hidden;"><?php
                  if (!empty($user['avatar_url'])): ?><img src="../<?= htmlspecialchars($user['avatar_url']) ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"><?php
                  else: echo ['admin'=>'👑','teacher'=>'👨‍🏫','student'=>'🎓','parent'=>'👨‍👩‍👦'][$user['role']] ?? '👤';
                  endif; ?></div>
                <div class="avatar-upload-overlay"><span>📷</span></div>
              </div>
              <div style="font-size:11px;color:var(--gold-light);text-align:center;margin-bottom:8px;cursor:pointer;text-decoration:underline;" onclick="document.getElementById('avatarFileInput').click()">📷 Click to change photo</div>
              <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
              <div class="profile-role"><?= ucfirst(htmlspecialchars($user['role'])) ?></div>
            </div>
            <div class="profile-details">
              <div class="pd-row"><span class="pd-label">Email</span><span class="pd-val"><?= htmlspecialchars($user['email']) ?></span></div>
              <div class="pd-row"><span class="pd-label">Role</span><span class="pd-val"><?= ucfirst(htmlspecialchars($user['role'])) ?></span></div>
              <div class="pd-row"><span class="pd-label">Department</span><span class="pd-val"><?= htmlspecialchars($user['department'] ?? '—') ?></span></div>
              <div class="pd-row"><span class="pd-label">University</span><span class="pd-val">Wesleyan University Philippines</span></div>
              <div class="pd-row"><span class="pd-label">Academic Year</span><span class="pd-val">2025 – 2026</span></div>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-head"><h3>Recent Announcements</h3></div>
          <div id="profActivity"><div class="loading"><div class="loading-spinner"></div>Loading…</div></div>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<!-- DETAIL PANE (rendered inside ann-split, injected by JS) -->

<!-- FULL VIEW MODAL -->
<div class="fv-overlay" id="fvOverlay" onclick="fvClickOutside(event)">
  <div class="fv-box" id="fvBox">
    <div class="fv-head">
      <div class="fv-cat" id="fvCat"></div>
      <div class="fv-title" id="fvTitle"></div>
      <button class="fv-close" onclick="closeFullView()" title="Close">&#x2715;</button>
    </div>
    <div class="fv-body">
      <img id="fvImg" class="fv-img" src="" alt="" style="display:none">
      <div class="fv-text" id="fvText"></div>
      <div class="fv-divider"></div>
      <div class="fv-meta">
        <span>&#128197; <span id="fvDate"></span></span>
        <span>&#128101; <span id="fvAud"></span></span>
        <span>&#9997;&#65039; <span id="fvAuthor"></span></span>
      </div>
    </div>
  </div>
</div>

<!-- User View Modal Overlay -->
<div id="userViewOverlay" onclick="if(event.target===this)this.style.display='none'" style="display:none;position:fixed;inset:0;background:rgba(2,12,6,0.85);backdrop-filter:blur(6px);z-index:400;align-items:center;justify-content:center;padding:24px;"></div>

<!-- Edit Announcement Modal Overlay -->
<div id="editAnnOverlay" onclick="if(event.target===this)this.style.display='none'" style="display:none;position:fixed;inset:0;background:rgba(2,12,6,0.85);backdrop-filter:blur(6px);z-index:400;align-items:center;justify-content:center;padding:24px;">
  <div style="background:#0a2812;border:1px solid rgba(255,255,255,0.12);border-radius:20px;max-width:600px;width:100%;animation:fvIn 0.25s ease;box-shadow:0 24px 60px rgba(0,0,0,0.5);overflow:hidden;">
    <div style="background:linear-gradient(145deg,#071f0f,#0d3b1e);padding:24px 28px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:space-between;">
      <div style="font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:700;color:var(--gold-light);">✏️ Edit Announcement</div>
      <button onclick="document.getElementById('editAnnOverlay').style.display='none'" style="background:rgba(255,255,255,0.1);border:none;color:#fff;width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:20px;display:flex;align-items:center;justify-content:center;">×</button>
    </div>
    <div style="padding:28px;display:flex;flex-direction:column;gap:14px;">
      <input type="hidden" id="eaId">
      <div class="fg2"><label>Title</label><input type="text" id="eaTitle" placeholder="Announcement title"></div>
      <div class="fg2"><label>Content</label><textarea id="eaContent" style="min-height:120px;" placeholder="Announcement content…"></textarea></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="fg2"><label>Category</label>
          <select id="eaCat">
            <option value="notice">Notice</option>
            <option value="event">Event</option>
            <option value="exam">Exam</option>
            <option value="activity">Activity</option>
            <option value="holiday">Holiday</option>
          </select>
        </div>
        <div class="fg2"><label>Audience</label>
          <select id="eaAud">
            <option value="all">Everyone</option>
            <option value="student">Students</option>
            <option value="teacher">Teachers</option>
            <option value="parent">Parents</option>
          </select>
        </div>
      </div>
      <div class="fg2"><label>Image URL (optional)</label><input type="url" id="eaImage" placeholder="https://…"></div>
      <div style="display:flex;align-items:center;gap:10px;">
        <input type="checkbox" id="eaPin" style="width:16px;height:16px;accent-color:var(--gold);">
        <label for="eaPin" style="font-size:13px;color:var(--text);cursor:pointer;">📌 Pin this announcement</label>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px;">
        <button class="btn-sec" onclick="document.getElementById('editAnnOverlay').style.display='none'">Cancel</button>
        <button class="btn-primary" id="eaSaveBtn" onclick="saveEditAnn()">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast">✅ <span id="toastMsg">Done!</span></div>

<script>
// ── PHP session data injected server-side (no localStorage needed) ──
const API_BASE = '../api';
const token    = <?= json_encode($token) ?>;
const user     = <?= json_encode($user) ?>;

const AVATAR = { admin:'👑', teacher:'👩‍🏫', student:'🎒', parent:'👨‍👩‍👧' };
const TITLES = { dashboard:'Dashboard', announcements:'Announcements', events:'Events', archive:'Archive', create:'Post Announcement', users:'Manage Users', profile:'My Profile' };
const SECTIONS = Object.keys(TITLES);
let currentAnnId   = null;
let currentAnnData = null;

// Set sidebar avatar (only if PHP didn't already render an img)
if (!document.getElementById('sbAvatar').querySelector('img')) {
  document.getElementById('sbAvatar').textContent = AVATAR[user.role] || '👤';
}

// ── API HELPER ──
async function apiFetch(endpoint, options = {}) {
  const res = await fetch(`${API_BASE}/${endpoint}`, {
    ...options,
    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}`, ...(options.headers || {}) }
  });
  return res.json();
}

// ── STATS ──
async function loadStats() {
  try {
    const res = await apiFetch('users.php?action=stats');
    if (!res.success) return;
    const d = res.data;
    document.getElementById('scTotal').textContent  = d.total;
    document.getElementById('scEvents').textContent = d.events;
    document.getElementById('scRead').textContent   = d.read;
    document.getElementById('scUnread').textContent = d.unread;
    document.getElementById('unreadBadge').textContent = d.unread;
    document.getElementById('scTotalBadge').textContent = `${d.total} active`;
    if (d.unread === 0) document.getElementById('notifDot').style.display = 'none';
  } catch {}
}

// ── AVATAR UPLOAD ──
async function uploadAvatar(input) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 3 * 1024 * 1024) { showToast('⚠️ File too large. Max 3 MB.'); input.value=''; return; }
  showToast('⏳ Uploading…');
  const fd = new FormData();
  fd.append('avatar', file);
  try {
    const res = await fetch(`${API_BASE}/upload_avatar.php`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` }, // NO Content-Type — browser sets multipart boundary
      body: fd
    });
    const json = await res.json();
    if (json.success) {
      const url = '../' + json.data.avatar_url;
      // Update profile big-avatar
      const pa = document.getElementById('profAvatar');
      if (pa) { pa.innerHTML = `<img src="${url}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="Avatar">`; }
      // Update sidebar avatar
      const sb = document.getElementById('sbAvatar');
      if (sb) { sb.innerHTML = `<img src="${url}" alt="Avatar">`; }
      showToast('✅ Profile picture updated!');
    } else {
      showToast('⚠️ ' + json.message);
    }
  } catch (e) { showToast('❌ Upload failed: ' + e.message); }
  input.value = '';
}

// ── HELPERS ──
function fmtDate(d) { if (!d) return ''; return new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' }); }
function loadingHTML() { return '<div class="loading"><div class="loading-spinner"></div>Loading…</div>'; }
function emptyHTML(msg) { return `<div class="empty"><div class="empty-icon">📭</div>${msg}</div>`; }

function annHTML(ann) {
  const thumb = ann.image_url ? `<img class="ann-thumb" src="${ann.image_url}" alt="" onerror="this.style.display='none'">` : '';
  const unread = ann.is_read != 1 ? 'style="font-weight:700"' : '';
  return `<div class="ann-row" id="annRow${ann.id}" onclick="openAnn(${ann.id})" title="Click to read">
    <div class="ann-stripe ${ann.category}"></div>
    <div class="ann-body">
      <div class="ann-meta">
        <span class="cat-chip ${ann.category}">${ann.category}</span>
        <span class="ann-date">${fmtDate(ann.created_at)}</span>
        ${ann.pinned == 1 ? '<span class="pin-badge">📌</span>' : ''}
        ${ann.is_read == 1 ? '<span class="read-badge">✓ Read</span>' : '<span style="font-size:10px;color:var(--green-mid);font-weight:700">● New</span>'}
      </div>
      <div class="ann-title" ${unread}>${ann.title}</div>
      <div class="ann-preview">${ann.content}</div>
      <div class="aud-pills">
        <span class="aud-pill">👥 ${ann.audience === 'all' ? 'Everyone' : ann.audience.charAt(0).toUpperCase()+ann.audience.slice(1)}</span>
        <span class="aud-pill">✍️ ${ann.author_name}</span>
      </div>
    </div>
    ${thumb}
  </div>`;
}

// ── DASHBOARD ──
async function renderDashboard() {
  document.getElementById('dashAnnList').innerHTML = loadingHTML();
  try {
    const res = await apiFetch('announcements.php?limit=6');
    if (!res.success) throw new Error();
    if (res.data.items.length) {
      // Dashboard rows open a popup (no split-pane shrink)
      document.getElementById('dashAnnList').innerHTML =
        res.data.items.map(ann => {
          const unread = ann.is_read != 1 ? 'font-weight:700' : '';
          return `<div class="ann-row" onclick="openFullView(${ann.id})" title="Click to read" style="cursor:pointer">
            <div class="ann-stripe ${ann.category}"></div>
            <div class="ann-body">
              <div class="ann-meta">
                <span class="cat-chip ${ann.category}">${ann.category}</span>
                <span class="ann-date">${fmtDate(ann.created_at)}</span>
                ${ann.pinned == 1 ? '<span class="pin-badge">📌</span>' : ''}
                ${ann.is_read == 1 ? '<span class="read-badge">✓ Read</span>' : '<span style="font-size:10px;color:var(--gold-light);font-weight:700">● New</span>'}
              </div>
              <div class="ann-title" style="${unread}">${ann.title}</div>
              <div class="ann-preview">${ann.content}</div>
            </div>
          </div>`;
        }).join('');
    } else {
      document.getElementById('dashAnnList').innerHTML = emptyHTML('No announcements yet.');
    }
  } catch { document.getElementById('dashAnnList').innerHTML = emptyHTML('Could not load announcements.'); }
}

// ── ANNOUNCEMENTS ──
let searchTimer = null;
function onSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    // Navigate to announcements tab if not already there
    const sec = document.getElementById('sec-announcements');
    if (sec && sec.style.display === 'none') {
      const btn = document.querySelector('.nav-item[onclick*="announcements"]');
      goTo('announcements', btn);
    } else {
      renderAnnPage();
    }
  }, 400);
}

// ── LATEST NEWS & EVENTS ──
let lneAllItems = [];
let lneShowing = 6;

async function renderNewsGrid() {
  const grid = document.getElementById('lneGrid');
  if (!grid) return;
  grid.innerHTML = loadingHTML();
  try {
    const res = await apiFetch('announcements.php?archived=0&sort=newest&limit=50');
    if (!res.success) throw new Error();
    lneAllItems = res.data.items;
    lneShowing = 6;
    renderLneCards();
    const btn = document.getElementById('lneLoadMore');
    if (btn) btn.style.display = lneAllItems.length > 6 ? 'block' : 'none';
  } catch {
    grid.innerHTML = emptyHTML('Could not load news.');
  }
}

function renderLneCards() {
  const grid = document.getElementById('lneGrid');
  if (!grid) return;
  const items = lneAllItems.slice(0, lneShowing);
  if (!items.length) { grid.innerHTML = emptyHTML('No announcements yet.'); return; }
  const catEmoji = { event:'📅', exam:'📋', notice:'📌', activity:'🎉', holiday:'🌿' };
  grid.innerHTML = items.map(ann => {
    const imgHtml = ann.image_url
      ? `<img src="${ann.image_url}" alt="${ann.title}" onerror="this.parentElement.innerHTML='<div class=\'lne-img-placeholder\'>🏫</div>'">`
      : `<div class="lne-img-placeholder">${catEmoji[ann.category]||'📢'}</div>`;
    return `<div class="lne-card" onclick="openFullView(${ann.id})" title="${ann.title}">
      <div class="lne-img-wrap">${imgHtml}<span class="lne-badge">News</span></div>
      <div class="lne-body">
        <div class="lne-cat">${ann.category.toUpperCase()}</div>
        <div class="lne-title">${ann.title}</div>
        <div class="lne-preview">${ann.content}</div>
        <div class="lne-meta">
          <span>📅 ${fmtDate(ann.created_at)}</span>
          <button class="lne-read-more" onclick="event.stopPropagation();openFullView(${ann.id})">Read full article →</button>
        </div>
      </div>
    </div>`;
  }).join('');
}

function loadMoreNews() {
  lneShowing += 6;
  renderLneCards();
  const btn = document.getElementById('lneLoadMore');
  if (btn) btn.style.display = lneShowing >= lneAllItems.length ? 'none' : 'block';
}

async function renderAnnPage() {
  document.getElementById('annPageList').innerHTML = loadingHTML();
  closeDetailPane(); // reset any open detail on filter change
  const cat  = document.getElementById('fCat').value;
  const aud  = document.getElementById('fAud').value;
  const sort = document.getElementById('fSort').value;
  const q    = document.getElementById('searchInput').value.trim();
  let url = `announcements.php?archived=0&sort=${sort}`;
  if (cat) url += `&category=${cat}`;
  if (aud) url += `&audience=${aud}`;
  if (q)   url += `&q=${encodeURIComponent(q)}`;
  try {
    const res = await apiFetch(url);
    if (!res.success) throw new Error();
    document.getElementById('annCount').textContent = `${res.data.total} result${res.data.total !== 1 ? 's' : ''}`;
    if (res.data.items.length) {
      document.getElementById('annPageList').innerHTML =
        `<div class="ann-split" id="split-announcements"><div class="ann-list-pane">${res.data.items.map(annHTML).join('')}</div><div class="ann-detail-pane" id="dp-announcements"></div></div>`;
    } else {
      document.getElementById('annPageList').innerHTML = emptyHTML('No announcements match your filters.');
    }
  } catch { document.getElementById('annPageList').innerHTML = emptyHTML('Could not load announcements.'); }
}

// ── EVENTS ──
let eventsCache = [];
async function renderEvents() {
  document.getElementById('eventsGrid').innerHTML = loadingHTML();
  try {
    // Fetch both dedicated events AND event-category announcements in parallel
    const [evRes, annRes] = await Promise.all([
      apiFetch('events.php'),
      apiFetch('announcements.php?category=event&archived=0&limit=50')
    ]);

    // Normalise dedicated events
    const dedicated = (evRes.success ? evRes.data : []).map(e => ({
      _type: 'event',
      _raw: e,
      title: e.title,
      date: e.event_date,
      time: e.event_time,
      location: e.location || '—',
      description: e.description || ''
    }));

    // Normalise event announcements — prefer event_date field for sorting
    const fromAnn = (annRes.success ? annRes.data.items : []).map(a => ({
      _type: 'announcement',
      _raw: a,
      title: a.title,
      date: a.event_date || (a.created_at ? a.created_at.split(' ')[0] : ''),
      time: '',
      location: '',
      description: a.content
    }));

    // Merge and sort by date ascending, then DEDUPLICATE by normalised title
    const seen = new Set();
    const all = [...dedicated, ...fromAnn]
      .sort((a,b) => a.date < b.date ? -1 : a.date > b.date ? 1 : 0)
      .filter(e => {
        const key = e.title.toLowerCase().replace(/[^a-z0-9]/g,'');
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
      });
    eventsCache = all;

    if (!all.length) {
      document.getElementById('eventsGrid').innerHTML = emptyHTML('No events scheduled.');
      return;
    }

    document.getElementById('eventsGrid').innerHTML = all.map((e, i) => {
      const d = new Date(e.date + 'T00:00:00');
      const validDate = !isNaN(d);
      const dayStr  = validDate ? d.getDate().toString().padStart(2,'0') : '—';
      const monStr  = validDate ? d.toLocaleString('en-US',{month:'short'}) : '';
      const isAnn   = e._type === 'announcement';
      // Category chip styling
      const chipStyle = isAnn
        ? 'background:rgba(93,173,226,0.15);color:#5dade2;border:1px solid rgba(93,173,226,0.25);'
        : 'background:rgba(30,122,60,0.2);color:#7ddc9a;border:1px solid rgba(30,122,60,0.3);';
      const chipLabel = isAnn ? '📢 Announcement' : '📅 Event';
      const hasLocation = e.location && e.location !== '—' && e.location.trim() !== '';
      return `<div class="ev-full-card" style="cursor:pointer" onclick="openEvent(${i})" title="Click for details">
        <div style="display:inline-block;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;${chipStyle}padding:2px 9px;border-radius:100px;margin-bottom:10px;">${chipLabel}</div>
        <div style="display:flex;gap:14px;align-items:center">
          <div class="ev-datebox" style="width:56px;height:56px"><div class="ev-day" style="font-size:20px">${dayStr}</div><div class="ev-mon">${monStr}</div></div>
          <div style="min-width:0;flex:1">
            <div style="font-family:'Cormorant Garamond',serif;font-size:16px;font-weight:700;color:var(--text);margin-bottom:4px;word-break:break-word;">${e.title}</div>
            ${e.time ? `<div style="font-size:12px;color:var(--muted)">🕐 ${e.time}</div>` : ''}
            ${hasLocation ? `<div style="font-size:12px;color:var(--muted)">📍 ${e.location}</div>` : ''}
            ${!e.time && !hasLocation ? `<div style="font-size:12px;color:var(--muted)">${fmtDate(e.date)}</div>` : ''}
          </div>
        </div>
      </div>`;
    }).join('');
  } catch { document.getElementById('eventsGrid').innerHTML = emptyHTML('Could not load events.'); }
}

function openEvent(idx) {
  const e = eventsCache[idx];
  if (!e) return;

  // If it came from the announcements table, reuse the announcement full-view
  if (e._type === 'announcement') {
    openFullView(e._raw.id);
    return;
  }

  // Dedicated event: show date / time / location detail
  const d = new Date(e.date + 'T00:00:00');
  const fullDate = d.toLocaleDateString('en-PH', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
  document.getElementById('fvCat').textContent   = '&#128197; UPCOMING EVENT';
  document.getElementById('fvTitle').textContent = e.title;
  document.getElementById('fvText').innerHTML =
    `<div style="display:flex;flex-direction:column;gap:12px;font-size:14px;">
       <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;background:var(--off);border-radius:10px;border:1px solid var(--border);">
         <div style="width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,var(--green),var(--green-mid));display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">&#128197;</div>
         <div><div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:2px;">Date</div><div style="font-weight:600;color:var(--text);font-size:14px;">${fullDate}</div></div>
       </div>
       <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;background:var(--off);border-radius:10px;border:1px solid var(--border);">
         <div style="width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,var(--gold),#b8911f);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">&#128336;</div>
         <div><div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:2px;">Time</div><div style="font-weight:600;color:var(--text);font-size:14px;">${e.time}</div></div>
       </div>
       <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;background:var(--off);border-radius:10px;border:1px solid var(--border);">
         <div style="width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,#2980b9,#5dade2);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">&#128205;</div>
         <div><div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:2px;">Location</div><div style="font-weight:600;color:var(--text);font-size:14px;">${e.location||'&mdash;'}</div></div>
       </div>
       ${e.description ? `<div style="padding:14px 16px;background:rgba(255,255,255,0.05);border-radius:10px;border:1px solid rgba(255,255,255,0.1);line-height:1.75;color:var(--text);font-size:14px;">${e.description}</div>` : ''}
     </div>`;
  document.getElementById('fvDate').textContent   = '';
  document.getElementById('fvAud').textContent    = '';
  document.getElementById('fvAuthor').textContent = '';
  const fvImg = document.getElementById('fvImg');
  fvImg.style.display = 'none'; fvImg.src = '';
  const divEl = document.querySelector('.fv-divider');
  const metaEl = document.querySelector('.fv-meta');
  if (divEl) divEl.style.display = 'none';
  if (metaEl) metaEl.style.display = 'none';
  document.getElementById('fvOverlay').classList.add('open');
}

// ── UPCOMING ──
async function loadUpcoming() {
  try {
    const [evRes, annRes] = await Promise.all([
      apiFetch('events.php'),
      apiFetch('announcements.php?category=event&archived=0&limit=50')
    ]);
    const dedicated = (evRes.success ? evRes.data : []).map(e => ({
      _type:'event', _raw:e,
      title:e.title, date:e.event_date, time:e.event_time, location:e.location||'—'
    }));
    const fromAnn = (annRes.success ? annRes.data.items : []).map(a => ({
      _type:'announcement', _raw:a,
      title:a.title,
      date: a.event_date || (a.created_at ? a.created_at.split(' ')[0] : ''),
      time:'', location:''
    }));
    const seen = new Set();
    const all = [...dedicated, ...fromAnn]
      .sort((a,b) => a.date < b.date ? -1 : a.date > b.date ? 1 : 0)
      .filter(e => {
        const key = e.title.toLowerCase().replace(/[^a-z0-9]/g,'');
        if (seen.has(key)) return false;
        seen.add(key); return true;
      });
    // Keep eventsCache in sync so openEvent() indices match
    if (!eventsCache.length) eventsCache = all;
    const top5 = all.slice(0,5);
    document.getElementById('upcomingList').innerHTML = top5.map((e, localIdx) => {
      // Find real index in eventsCache for openEvent()
      const cacheIdx = eventsCache.findIndex(c => c.title === e.title);
      const idx = cacheIdx >= 0 ? cacheIdx : localIdx;
      const d = new Date(e.date + 'T00:00:00');
      const dayStr = !isNaN(d) ? d.getDate().toString().padStart(2,'0') : '—';
      const monStr = !isNaN(d) ? d.toLocaleString('en-US',{month:'short'}) : '';
      return `<div class="ev-item" style="cursor:pointer" onclick="openEvent(${idx})" title="Click for details">
        <div class="ev-datebox"><div class="ev-day">${dayStr}</div><div class="ev-mon">${monStr}</div></div>
        <div><div class="ev-name">${e.title}</div>
        <div class="ev-time">${e.time ? '🕐 '+e.time : ''}${e.time && e.location ? ' · ' : ''}${e.location && e.location!=='—' ? '📍 '+e.location : fmtDate(e.date)}</div>
        </div></div>`;
    }).join('') || emptyHTML('No upcoming events.');
  } catch {}
}

// ── ARCHIVE ──
async function renderArchive() {
  document.getElementById('archiveList').innerHTML = loadingHTML();
  closeDetailPane();
  try {
    const res = await apiFetch('announcements.php?archived=1');
    if (res.data.items.length) {
      document.getElementById('archiveList').innerHTML =
        `<div class="ann-split" id="split-archive"><div class="ann-list-pane">${res.data.items.map(annHTML).join('')}</div><div class="ann-detail-pane" id="dp-archive"></div></div>`;
    } else {
      document.getElementById('archiveList').innerHTML = emptyHTML('No archived announcements.');
    }
  } catch { document.getElementById('archiveList').innerHTML = emptyHTML('Could not load archive.'); }
}

// ── QUICK ACTIONS ──
function renderQA() {
  const acts = user.role === 'admin'
    ? [{icon:'✏️',label:'Post Announcement',fn:"goTo('create',null)"},{icon:'👥',label:'Manage Users',fn:"goTo('users',null)"},{icon:'📅',label:'Events',fn:"goTo('events',null)"},{icon:'🗂️',label:'Archive',fn:"goTo('archive',null)"}]
      : [{icon:'📢',label:'Announcements',fn:"goTo('announcements',null)"},{icon:'📅',label:'Events',fn:"goTo('events',null)"},{icon:'👤',label:'My Profile',fn:"goTo('profile',null)"},{icon:'🔍',label:'Search',fn:"document.getElementById('searchInput').focus()"}];
  document.getElementById('qaGrid').innerHTML = acts.map(a => `<div class="qa-card" onclick="${a.fn}"><div class="qa-icon">${a.icon}</div><div class="qa-lbl">${a.label}</div></div>`).join('');
}

// ── USERS ──
async function renderUsers() {
  document.getElementById('usersBody').innerHTML = '<tr><td colspan="6"><div class="loading"><div class="loading-spinner"></div>Loading…</div></td></tr>';
  try {
    const res = await apiFetch('users.php');
    document.getElementById('userCount').textContent = `${res.data.length} registered`;
    document.getElementById('usersBody').innerHTML = res.data.map(u => {
      const avatarHtml = u.avatar_url
        ? `<img src="../${u.avatar_url}" style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:2px solid rgba(201,162,39,0.4);vertical-align:middle;margin-right:8px;" onerror="this.style.display='none'">`
        : `<span style="font-size:20px;vertical-align:middle;margin-right:8px;">${AVATAR[u.role]||'👤'}</span>`;
      return `<tr>
        <td style="display:flex;align-items:center;">${avatarHtml}<strong>${u.name}</strong></td>
        <td style="color:var(--muted)">${u.email}</td>
        <td><span class="cat-chip ${u.role}" style="font-size:10px">${u.role}</span></td>
        <td style="color:var(--muted)">${u.department||'—'}</td>
        <td><span style="color:#7ddc9a;font-weight:600;font-size:12px">● ${u.status}</span></td>
        <td style="display:flex;gap:6px;align-items:center;">
          <button onclick='viewUser(${JSON.stringify(JSON.stringify(u))})' style="padding:5px 12px;font-size:12px;background:rgba(201,162,39,0.15);color:var(--gold-light);border:1px solid rgba(201,162,39,0.3);border-radius:8px;cursor:pointer;font-family:'Outfit',sans-serif;">View</button>
          ${u.id !== user.id ? `<button class="btn-danger" style="padding:5px 12px;font-size:12px" onclick="deleteUser(${u.id},'${u.name}')">Delete</button>` : '<span style="font-size:12px;color:var(--muted)">You</span>'}
        </td>
      </tr>`;
    }).join('');
  } catch { document.getElementById('usersBody').innerHTML = '<tr><td colspan="6" style="color:var(--muted);padding:20px">Could not load users.</td></tr>'; }
}

async function addUser() {
  const name=document.getElementById('uName').value.trim(), email=document.getElementById('uEmail').value.trim(),
        pass=document.getElementById('uPass').value, role=document.getElementById('uRole').value, dept=document.getElementById('uDept').value.trim();
  if (!name||!email||!pass) { showToast('⚠️ Name, email, and password are required'); return; }
  try {
    const res = await apiFetch('users.php', { method:'POST', body: JSON.stringify({ name, email, password:pass, role, department:dept }) });
    if (res.success) { showToast('👤 User added!'); ['uName','uEmail','uPass','uDept'].forEach(id=>document.getElementById(id).value=''); renderUsers(); }
    else showToast('⚠️ ' + res.message);
  } catch { showToast('❌ Failed to add user'); }
}

async function deleteUser(id, name) {
  if (!confirm(`Delete user "${name}"? This cannot be undone.`)) return;
  try {
    const res = await apiFetch(`users.php?id=${id}`, { method:'DELETE' });
    if (res.success) { showToast('🗑 User deleted'); renderUsers(); }
    else showToast('⚠️ ' + res.message);
  } catch { showToast('❌ Failed to delete user'); }
}

function viewUser(jsonStr) {
  const u = JSON.parse(jsonStr);
  const ov = document.getElementById('userViewOverlay');
  const av = u.avatar_url
    ? `<img src="../${u.avatar_url}" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid var(--gold);display:block;" onerror="this.style.display='none'">`
    : `<div style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#a8831a);display:flex;align-items:center;justify-content:center;font-size:42px;border:3px solid var(--gold);">${AVATAR[u.role]||'👤'}</div>`;
  const joined = u.created_at ? new Date(u.created_at).toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'}) : '—';
  ov.innerHTML = `<div style="background:#0a2812;border:1px solid rgba(255,255,255,0.12);border-radius:20px;max-width:440px;width:100%;animation:fvIn 0.25s ease;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,0.5);">
    <div style="background:linear-gradient(145deg,#071f0f,#0d3b1e);padding:32px;text-align:center;position:relative;border-bottom:1px solid rgba(255,255,255,0.08);">
      <button onclick="document.getElementById('userViewOverlay').style.display='none'" style="position:absolute;top:14px;right:14px;background:rgba(255,255,255,0.1);border:none;color:#fff;width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:20px;line-height:1;display:flex;align-items:center;justify-content:center;">×</button>
      <div style="display:flex;justify-content:center;margin-bottom:14px;">${av}</div>
      <div style="font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:700;color:var(--gold-light);">${u.name}</div>
      <span class="cat-chip ${u.role}" style="margin-top:6px;display:inline-block;font-size:11px;">${u.role}</span>
    </div>
    <div style="padding:24px;display:flex;flex-direction:column;gap:10px;">
      ${uvRow('📧','Email',u.email)}
      ${uvRow('🏢','Department',u.department||'—')}
      ${uvRow('✅','Status',u.status)}
      ${uvRow('📅','Joined',joined)}
    </div>
  </div>`;
  ov.style.display = 'flex';
}
function uvRow(icon,label,val){return `<div style="display:flex;align-items:center;gap:12px;padding:11px 14px;background:rgba(255,255,255,0.04);border-radius:10px;border:1px solid rgba(255,255,255,0.07);"><span style="font-size:18px;">${icon}</span><div><div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:0.8px;">${label}</div><div style="font-size:14px;font-weight:500;color:rgba(255,255,255,0.9);margin-top:2px;">${val}</div></div></div>`;}

// ── PROFILE ──
async function renderProfile() {
  document.getElementById('profAvatar').textContent = AVATAR[user.role] || '👤';
  document.getElementById('profActivity').innerHTML = loadingHTML();
  try {
    const res = await apiFetch('announcements.php?limit=6');
    document.getElementById('profActivity').innerHTML = res.data.items.map(a => `
      <div style="padding:10px 22px;border-bottom:1px solid var(--border);font-size:13px">
        <span class="cat-chip ${a.category}" style="margin-right:8px">${a.category}</span>
        <span style="font-weight:600">${a.title}</span>
        <div style="font-size:11px;color:var(--muted);margin-top:4px">${fmtDate(a.created_at)}</div>
      </div>`).join('') || emptyHTML('No recent activity.');
  } catch {}
}

// ── GMAIL-STYLE DETAIL PANE ──
let activeSplitId = null; // tracks which split container is open

function getActiveSplit() {
  // Only return a split that lives inside a VISIBLE section (not display:none)
  const ids = ['split-dashboard','split-announcements','split-archive'];
  for (const id of ids) {
    const el = document.getElementById(id);
    if (el && el.offsetParent !== null) return el;
  }
  return null;
}

function closeDetailPane() {
  const split = getActiveSplit();
  if (!split) return;
  split.classList.remove('detail-open');
  const dp = split.querySelector('.ann-detail-pane');
  if (dp) dp.innerHTML = '';
  split.querySelectorAll('.ann-row.selected').forEach(r => r.classList.remove('selected'));
  currentAnnId = null;
}

async function openAnn(id) {
  currentAnnId = id;
  const split = getActiveSplit();
  if (!split) return;

  // Highlight selected row
  split.querySelectorAll('.ann-row.selected').forEach(r => r.classList.remove('selected'));
  const row = document.getElementById('annRow' + id);
  if (row) row.classList.add('selected');

  // Open split pane & show loading
  const dp = split.querySelector('.ann-detail-pane');
  if (!dp) return;
  split.classList.add('detail-open');
  dp.innerHTML = `<div class="dp-loading">${loadingHTML()}</div>`;

  // Mark as read
  apiFetch(`announcements.php?action=read&id=${id}`, { method:'POST' }).then(() => {
    loadStats();
    if (row) { const nb = row.querySelector('.ann-row span[style]'); if(nb) nb.remove(); }
  });

  try {
    const res = await apiFetch(`announcements.php?id=${id}`);
    if (!res.success) throw new Error();
    const a = res.data;
    currentAnnData = a; // store for editAnn()
    const imgHtml = a.image_url
      ? `<img class="dp-img" src="${a.image_url}" alt="Announcement image" onerror="this.style.display='none'">`
      : '';
    const adminHtml = user.role === 'admin'
      ? `<div class="dp-actions">
           <button class="btn-sec" style="padding:8px 16px;font-size:13px;" onclick="editAnn()">&#9998; Edit</button>
           <button class="btn-danger" onclick="deleteAnn()">&#128465; Delete</button>
           <button class="btn-sec" onclick="archiveAnn()">&#128230; Archive</button>
         </div>`
      : '';
    dp.innerHTML = `
      <div class="dp-header">
        <div class="dp-cat">${a.category.toUpperCase()}</div>
        <div class="dp-title">${a.title}</div>
        <button class="dp-close" onclick="closeDetailPane()" title="Close">&#x2715;</button>
      </div>
      <div class="dp-body">
        ${imgHtml}
        <div class="dp-content">${a.content}</div>
        <div class="dp-meta">
          <span>&#128197; ${fmtDate(a.created_at)}</span>
          <span>&#128101; ${a.audience === 'all' ? 'Everyone' : a.audience}</span>
          <span>&#9997;&#65039; ${a.author_name}</span>
        </div>
        ${adminHtml ? `<div class="dp-actions">${adminHtml}</div>` : ''}
      </div>`;
  } catch {
    dp.innerHTML = `<div class="dp-body"><div style="color:var(--muted);padding:20px">⚠️ Could not load this announcement.</div></div>`;
  }
}

function closeModal() { closeDetailPane(); }
document.addEventListener('keydown', e => { if (e.key==='Escape') { closeFullView(); closeDetailPane(); document.getElementById('editAnnOverlay').style.display='none'; } });

// ── EDIT ANNOUNCEMENT ──
function editAnn() {
  const a = currentAnnData;
  if (!a) return;
  document.getElementById('eaId').value      = a.id;
  document.getElementById('eaTitle').value   = a.title;
  document.getElementById('eaContent').value = a.content;
  document.getElementById('eaCat').value     = a.category;
  document.getElementById('eaAud').value     = a.audience;
  document.getElementById('eaPin').checked   = a.pinned == 1;
  document.getElementById('eaImage').value   = a.image_url || '';
  document.getElementById('editAnnOverlay').style.display = 'flex';
}

async function saveEditAnn() {
  const id      = document.getElementById('eaId').value;
  const title   = document.getElementById('eaTitle').value.trim();
  const content = document.getElementById('eaContent').value.trim();
  const category= document.getElementById('eaCat').value;
  const audience= document.getElementById('eaAud').value;
  const pinned  = document.getElementById('eaPin').checked;
  const image_url = document.getElementById('eaImage').value.trim();
  if (!title || !content) { showToast('\u26a0\ufe0f Title and content are required'); return; }
  const btn = document.getElementById('eaSaveBtn'); btn.disabled=true; btn.textContent='Saving\u2026';
  try {
    const res = await apiFetch(`announcements.php?id=${id}`, { method:'PUT', body: JSON.stringify({ title, content, category, audience, pinned, image_url }) });
    if (res.success) {
      showToast('\u2705 Announcement updated!');
      document.getElementById('editAnnOverlay').style.display = 'none';
      renderDashboard(); renderAnnPage();
      // Refresh the detail pane
      if (currentAnnId) openAnn(currentAnnId);
    } else { showToast('\u26a0\ufe0f ' + res.message); }
  } catch { showToast('\u274c Failed to save'); }
  btn.disabled=false; btn.textContent='Save Changes';
}

// ── FULL VIEW MODAL ──
async function openFullView(id) {
  const overlay = document.getElementById('fvOverlay');
  // If we already have the data cached from openAnn, just populate from DOM
  // Otherwise fetch fresh
  overlay.classList.add('open');
  document.getElementById('fvCat').textContent    = '';
  document.getElementById('fvTitle').textContent  = 'Loading…';
  document.getElementById('fvText').textContent   = '';
  document.getElementById('fvDate').textContent   = '';
  document.getElementById('fvAud').textContent    = '';
  document.getElementById('fvAuthor').textContent = '';
  const fvImg = document.getElementById('fvImg');
  fvImg.style.display = 'none'; fvImg.src = '';
  try {
    const res = await apiFetch(`announcements.php?id=${id}`);
    if (!res.success) throw new Error();
    const a = res.data;
    document.getElementById('fvCat').textContent    = a.category.toUpperCase();
    document.getElementById('fvTitle').textContent  = a.title;
    document.getElementById('fvText').textContent   = a.content;
    document.getElementById('fvDate').textContent   = fmtDate(a.created_at);
    document.getElementById('fvAud').textContent    = a.audience === 'all' ? 'Everyone' : a.audience;
    document.getElementById('fvAuthor').textContent = a.author_name;
    if (a.image_url) {
      fvImg.src = a.image_url;
      fvImg.style.display = 'block';
      fvImg.onerror = () => { fvImg.style.display = 'none'; };
    }
  } catch {
    document.getElementById('fvTitle').textContent = 'Could not load announcement.';
  }
}

function closeFullView() {
  document.getElementById('fvOverlay').classList.remove('open');
  // Restore fv-meta/divider for announcement usage
  const divEl = document.querySelector('.fv-divider');
  const metaEl = document.querySelector('.fv-meta');
  if (divEl) divEl.style.display = '';
  if (metaEl) metaEl.style.display = '';
}

function fvClickOutside(e) {
  if (e.target === document.getElementById('fvOverlay')) closeFullView();
}

async function deleteAnn() {
  if (!currentAnnId||!confirm('Delete this announcement permanently?')) return;
  try {
    const res = await apiFetch(`announcements.php?id=${currentAnnId}`, { method:'DELETE' });
    if (res.success) { showToast('🗑 Deleted'); closeModal(); renderDashboard(); renderAnnPage(); loadStats(); }
    else showToast('⚠️ ' + res.message);
  } catch { showToast('❌ Failed'); }
}

async function archiveAnn() {
  if (!currentAnnId) return;
  try {
    const res = await apiFetch(`announcements.php?id=${currentAnnId}`, { method:'PUT', body: JSON.stringify({ archived:1 }) });
    if (res.success) { showToast('📦 Archived'); closeModal(); renderDashboard(); renderAnnPage(); loadStats(); }
    else showToast('⚠️ ' + res.message);
  } catch { showToast('❌ Failed'); }
}

// ── IMAGE UPLOAD (announcement form) ──
async function uploadAnnouncementImage(input) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) { showToast('⚠️ File too large. Max 5 MB.'); input.value=''; return; }

  // Show progress bar
  const prog = document.getElementById('cfImgProgress');
  const bar  = document.getElementById('cfImgProgressBar');
  const txt  = document.getElementById('cfImgProgressText');
  prog.style.display = 'block';
  bar.style.width = '0%';
  txt.textContent = 'Uploading…';

  // Animate progress bar (fake progress until server responds)
  let pct = 0;
  const ticker = setInterval(() => {
    pct = Math.min(pct + Math.random() * 12, 85);
    bar.style.width = pct + '%';
  }, 200);

  const fd = new FormData();
  fd.append('image', file);
  try {
    const res = await fetch(`${API_BASE}/upload_image.php`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` },
      body: fd
    });
    const json = await res.json();
    clearInterval(ticker);
    if (json.success) {
      bar.style.width = '100%';
      txt.textContent = '✅ Upload complete!';
      setTimeout(() => { prog.style.display = 'none'; }, 1200);
      const url = '../' + json.data.image_url;
      // Populate URL field & show preview
      document.getElementById('cfImage').value = url;
      previewCFImage(url);
      // Shrink drop zone to show it's done
      document.getElementById('cfDropZoneInner').innerHTML =
        '<div style="font-size:22px;">✅</div><div style="font-size:12px;color:var(--gold-light);font-weight:600;margin-top:4px;">Image uploaded — click to replace</div>';
      showToast('🖼️ Image uploaded!');
    } else {
      clearInterval(ticker);
      prog.style.display = 'none';
      showToast('⚠️ ' + json.message);
    }
  } catch(e) {
    clearInterval(ticker);
    prog.style.display = 'none';
    showToast('❌ Upload failed: ' + e.message);
  }
  input.value = '';
}

function handleImgDrop(e) {
  e.preventDefault();
  document.getElementById('cfDropZone').classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (!file || !file.type.startsWith('image/')) { showToast('⚠️ Please drop an image file.'); return; }
  const dt = new DataTransfer();
  dt.items.add(file);
  const input = document.getElementById('cfImageFile');
  input.files = dt.files;
  uploadAnnouncementImage(input);
}

function clearCFImage() {
  document.getElementById('cfImage').value = '';
  document.getElementById('cfImagePreview').style.display = 'none';
  document.getElementById('cfImgThumb').src = '';
  document.getElementById('cfDropZoneInner').innerHTML =
    '<div style="font-size:36px;margin-bottom:8px;">🖼️</div>' +
    '<div style="font-weight:600;color:var(--text);margin-bottom:4px;">Click to upload or drag &amp; drop</div>' +
    '<div style="font-size:12px;color:var(--muted);">JPG, PNG, GIF, WebP — max 5 MB</div>';
}

// ── IMAGE PREVIEW (create form) ──
function previewCFImage(url) {
  const wrap = document.getElementById('cfImagePreview');
  const img  = document.getElementById('cfImgThumb');
  if (url) {
    img.src = url;
    img.onerror = () => { wrap.style.display = 'none'; };
    img.onload  = () => { wrap.style.display = 'block'; };
  } else {
    wrap.style.display = 'none';
  }
}

// ── CREATE ──
async function publishAnn() {
  const title     = document.getElementById('cfTitle').value.trim();
  const content   = document.getElementById('cfContent').value.trim();
  const cat       = document.getElementById('cfCat').value;
  const aud       = document.getElementById('cfAud').value;
  const pinned    = document.getElementById('cfPinned').checked;
  const image_url = document.getElementById('cfImage').value.trim();
  const event_date = document.getElementById('cfDate').value.trim(); // YYYY-MM-DD
  if (!title||!content) { showToast('⚠️ Title and content are required'); return; }
  const btn=document.getElementById('publishBtn'); btn.disabled=true; btn.textContent='Publishing…';
  try {
    const res = await apiFetch('announcements.php', { method:'POST', body: JSON.stringify({ title, content, category:cat, audience:aud, pinned, image_url, event_date }) });
    if (res.success) {
      showToast('📢 Published!');
      clearCF();
      renderDashboard();
      loadStats();
      // If it's an event, immediately refresh the Events grid and sidebar
      if (cat === 'event') {
        eventsCache = []; // force re-fetch
        renderEvents();
        loadUpcoming();
      }
    }
    else showToast('⚠️ ' + res.message);
  } catch { showToast('❌ Failed to publish'); }
  btn.disabled=false; btn.textContent='📢 Publish Announcement';
}

function clearCF() {
  ['cfTitle','cfContent','cfDate','cfImage'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('cfCat').value='event'; document.getElementById('cfAud').value='all'; document.getElementById('cfPinned').checked=false;
  clearCFImage();
}

// ── NAVIGATION ──
function goTo(name, btn) {
  SECTIONS.forEach(s => { const el=document.getElementById('sec-'+s); if(el) el.style.display='none'; });
  const t=document.getElementById('sec-'+name); if(t) t.style.display='';
  document.querySelectorAll('.nav-item').forEach(b=>b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  document.getElementById('pageTitle').textContent = TITLES[name]||name;
  document.getElementById('sidebar').classList.remove('open');
  if (name==='announcements') { renderNewsGrid(); renderAnnPage(); }
  if (name==='events')        renderEvents();
  if (name==='archive') {
    if (user.role !== 'admin') { showToast('⛔ Archive is accessible by admins only.'); goTo('dashboard', document.querySelector('.nav-item[onclick*="dashboard"]')); return; }
    renderArchive();
  }
  if (name==='users')         renderUsers();
  if (name==='profile')       renderProfile();
}

// ── TOAST ──
function showToast(msg) {
  document.getElementById('toastMsg').textContent = msg;
  const t=document.getElementById('toast'); t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'), 3200);
}

// ── NOTIFICATION DROPDOWN ──
let notifOpen = false;
let notifLoaded = false;

async function toggleNotif() {
  const dd = document.getElementById('notifDropdown');
  notifOpen = !notifOpen;
  dd.classList.toggle('open', notifOpen);
  if (notifOpen && !notifLoaded) {
    notifLoaded = true;
    await loadNotifDropdown();
  }
}

async function loadNotifDropdown() {
  document.getElementById('notifList').innerHTML = '<div class="notif-empty">Loading…</div>';
  try {
    const res = await apiFetch('announcements.php?limit=10&sort=newest');
    if (!res.success) throw new Error();
    const items = res.data.items;
    if (!items.length) {
      document.getElementById('notifList').innerHTML = '<div class="notif-empty">📢 No announcements yet.</div>';
      return;
    }
    document.getElementById('notifList').innerHTML = items.map(a => `
      <div class="notif-item ${a.is_read != 1 ? 'unread' : ''}" onclick="notifClick(${a.id})">
        <div class="notif-dot2" style="${a.is_read == 1 ? 'background:var(--border)' : ''}"></div>
        <div>
          <div class="notif-item-title">${a.title}</div>
          <div class="notif-item-meta">
            <span class="cat-chip ${a.category}" style="font-size:9px">${a.category}</span>
            &nbsp;${fmtDate(a.created_at)}
          </div>
        </div>
      </div>`).join('');
  } catch {
    document.getElementById('notifList').innerHTML = '<div class="notif-empty">⚠️ Could not load.</div>';
  }
}

async function notifClick(id) {
  // Close dropdown, navigate to Announcements, open the detail pane
  toggleNotif();
  const btn = document.querySelector('.nav-item[onclick*="announcements"]');
  goTo('announcements', btn);
  // Wait for the section to render, then open the item
  setTimeout(() => openAnn(id), 600);
}

async function markAllRead() {
  // Mark all as read by fetching all unread and posting read receipts
  try {
    const res = await apiFetch('announcements.php?limit=50');
    if (res.success) {
      const promises = res.data.items
        .filter(a => a.is_read != 1)
        .map(a => apiFetch(`announcements.php?action=read&id=${a.id}`, { method:'POST' }));
      await Promise.all(promises);
      notifLoaded = false;
      await loadNotifDropdown();
      loadStats();
    }
  } catch {}
}

// Close notif dropdown when clicking outside
document.addEventListener('click', e => {
  if (notifOpen && !e.target.closest('.notif-wrap')) {
    notifOpen = false;
    document.getElementById('notifDropdown').classList.remove('open');
  }
});

// ── INIT ──
loadStats();
renderDashboard();
loadUpcoming();
</script>
</body>
</html>
