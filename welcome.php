<?php
// welcome.php – A.S.R Diabetes Management Platform – Main landing page
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>A.S.R | منصة رعايتي لمرضى السكري</title>
    <meta name="description" content="منصة A.S.R الشاملة للتوعية بمرض السكري، مصممة لدعمك وتمكينك في كل خطوة على الطريق.">
    <script src="assets/js/theme.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <style>
/* ─── RESET & BASE ─── */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html { scroll-behavior:smooth; }
body {
    font-family: 'Tajawal', sans-serif;
    background: #FAFAFA;
    color: #1A1A1A;
    overflow-x: hidden;
    transition: background .35s, color .35s;
}
a { text-decoration:none; color:inherit; }

/* ─── DARK MODE VARS (via data-theme on html) ─── */
:root {
    --page-bg: #FAFAFA;
    --card-bg: #FFFFFF;
    --ink: #1A1A1A;
    --muted: #666;
    --border: #E5E7EB;
    --shadow: 0 4px 24px rgba(0,0,0,.08);
    --red: #C8102E;
    --green: #2E7D32;
    --section-alt: #F3F4F6;
}
html[data-theme="dark"] body {
    background: #0D1117 !important;
    color: #E6EDF3 !important;
}
html[data-theme="dark"] {
    --page-bg: #0D1117;
    --card-bg: #161B22;
    --ink: #E6EDF3;
    --muted: #8B949E;
    --border: #30363D;
    --shadow: 0 4px 24px rgba(0,0,0,.4);
    --section-alt: #111620;
}

/* ─── HEADER ─── */
.site-header {
    position: fixed; top:0; left:0; right:0; z-index:300;
    height: 68px;
    display: flex; align-items:center; justify-content:space-between;
    padding: 0 48px;
    background: rgba(8,14,22,.55);
    backdrop-filter: blur(20px) saturate(1.6);
    border-bottom: 1px solid rgba(255,255,255,.08);
    transition: background .3s;
}
.site-header.scrolled { background: rgba(8,14,22,.93); }

.nav-logo { display:flex; align-items:center; gap:11px; }
.nav-logo-icon svg { width:40px; height:40px; display:block; }
.nav-brand-name { display:block; font-size:1.2rem; font-weight:900; color:#fff; letter-spacing:1.5px; line-height:1; }
.nav-brand-sub  { display:block; font-size:.6rem; color:rgba(255,255,255,.45); letter-spacing:2.5px; text-transform:uppercase; margin-top:3px; }

.nav-links { display:flex; gap:30px; list-style:none; }
.nav-links a { font-size:.9rem; font-weight:600; color:rgba(255,255,255,.7); transition:color .2s; position:relative; }
.nav-links a::after { content:''; position:absolute; bottom:-4px; left:0; right:0; height:2px; background:var(--red); transform:scaleX(0); transition:transform .25s; border-radius:2px; }
.nav-links a:hover { color:#fff; }
.nav-links a:hover::after { transform:scaleX(1); }

.nav-actions { display:flex; align-items:center; gap:10px; }
/* Premium Sky Toggle */
.dark-toggle {
    width: 70px; height: 34px;
    background: linear-gradient(to right, #4facfe, #00f2fe);
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 999px;
    position: relative;
    cursor: pointer;
    transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    overflow: hidden;
    display: flex;
    align-items: center;
    box-shadow: 0 8px 20px rgba(0,0.0,0.1);
}

html[data-theme="dark"] .dark-toggle {
    background: linear-gradient(to right, #243b55, #141e30);
    border-color: rgba(255,255,255,0.1);
}

/* Thumb (Sun/Moon) */
.toggle-thumb {
    width: 25px; height: 25px;
    border-radius: 50%;
    background: #ffcc33; /* Sun color */
    position: absolute;
    right: 4px;
    transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    z-index: 10;
    box-shadow: 0 0 10px rgba(255, 204, 51, 0.5);
    display: flex; align-items: center; justify-content: center;
}

html[data-theme="dark"] .toggle-thumb {
    transform: translateX(-37px);
    background: #f5f5f5; /* Moon color */
    box-shadow: 
        0 0 10px rgba(255, 255, 255, 0.3),
        inset -3px -3px 0 rgba(0,0,0,0.1);
}

/* Clouds/Stars Background Elements */
.sky-elements {
    position: absolute;
    inset: 0;
    transition: opacity 0.5s;
    pointer-events: none;
}

.cloud {
    position: absolute;
    width: 12px; height: 12px;
    background: rgba(255,255,255,0.8);
    border-radius: 50%;
    transition: transform 0.5s;
}
.cloud::before, .cloud::after {
    content: '';
    position: absolute;
    background: rgba(255,255,255,0.8);
    border-radius: 50%;
}
.cloud::before { width: 8px; height: 8px; top: 4px; left: -6px; }
.cloud::after { width: 10px; height: 10px; top: -3px; left: 5px; }

.cloud-1 { top: 8px; left: 15px; }
.cloud-2 { top: 18px; left: 35px; }

html[data-theme="dark"] .cloud {
    transform: translateY(40px);
    opacity: 0;
}

.stars {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 0.5s;
    pointer-events: none;
}

.star {
    position: absolute;
    background: white;
    border-radius: 50%;
    animation: twinkle 2s infinite alternate;
}
.star-1 { width: 2px; height: 2px; top: 8px; right: 20px; }
.star-2 { width: 1px; height: 1px; top: 18px; right: 40px; }
.star-3 { width: 1.5px; height: 1.5px; top: 5px; right: 50px; }

html[data-theme="dark"] .stars { opacity: 1; }

@keyframes twinkle {
    from { opacity: 0.3; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1.1); }
}
.nav-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:9px 22px; border-radius:10px; font-family:'Tajawal',sans-serif;
    font-size:.9rem; font-weight:700; cursor:pointer; border:none;
    background:linear-gradient(135deg,#C8102E,#9B0A22);
    color:#fff; box-shadow:0 4px 16px rgba(200,16,46,.35);
    transition:transform .22s, box-shadow .22s;
}
.nav-btn:hover { transform:translateY(-2px); box-shadow:0 6px 22px rgba(200,16,46,.45); }

/* ─── HERO ─── */
.hero {
    position:relative; min-height:100vh;
    display:flex; align-items:center; justify-content:center;
    overflow:hidden; padding-top:68px;
}
.hero-bg-slides { position:absolute; inset:0; z-index:0; }
.hero-bg-slide {
    position:absolute; inset:0; opacity:0;
    transition:opacity 1.6s ease;
    display:flex; align-items:center; justify-content:space-around;
    padding:80px 100px; gap:40px;
}
.hero-bg-slide.bg-active { opacity:1; }
.hbs-1 { background:linear-gradient(115deg,#6B0000 0%,#9B0A22 40%,#003A00 80%,#1B5E20 100%); }
.hbs-2 { background:linear-gradient(115deg,#001A4D 0%,#1A237E 40%,#003030 80%,#004D40 100%); }
.hbs-3 { background:linear-gradient(115deg,#2D004D 0%,#6A1B9A 40%,#4A0010 80%,#880E4F 100%); }
.hbs-4 { background:linear-gradient(115deg,#5C1A00 0%,#BF360C 40%,#003A00 80%,#1B5E20 100%); }
.hbs-5 { background:linear-gradient(115deg,#6B0000 0%,#C8102E 40%,#003A00 80%,#2E7D32 100%); }
.hero-overlay { position:absolute; inset:0; background:rgba(0,0,0,.48); z-index:1; }

.wm-card {
    background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.14);
    border-radius:18px; padding:26px; backdrop-filter:blur(6px); color:#fff;
    min-width:190px; position:relative; z-index:2;
}
.wm-card.lg { min-width:250px; }
.wm-title { font-size:.95rem; font-weight:700; margin-bottom:14px; opacity:.9; }
.wm-stat { font-size:2.3rem; font-weight:900; line-height:1; }
.wm-sub { font-size:.78rem; opacity:.55; margin-top:4px; }
.wm-bars { display:flex; align-items:flex-end; gap:5px; height:56px; margin-top:12px; }
.wm-bar { flex:1; border-radius:3px 3px 0 0; background:rgba(255,255,255,.22); }
.wm-list { list-style:none; display:flex; flex-direction:column; gap:9px; }
.wm-list li { display:flex; align-items:center; gap:9px; font-size:.83rem; opacity:.85; }
.wm-dot { width:18px; height:18px; border-radius:50%; background:rgba(255,255,255,.18); display:flex; align-items:center; justify-content:center; font-size:.65rem; flex-shrink:0; }
.wm-pill { display:inline-block; padding:4px 11px; border-radius:999px; background:rgba(255,255,255,.14); font-size:.78rem; margin:3px 3px 0; }

#hero-particles { position:absolute; inset:0; z-index:2; pointer-events:none; }

.hero-content {
    position:relative; z-index:10; text-align:center;
    max-width:700px; padding:0 24px;
    animation:heroUp .9s ease both;
}
@keyframes heroUp { from{opacity:0;transform:translateY(32px);}to{opacity:1;transform:none;} }
.hero-content h1 {
    font-size:clamp(2.8rem,6vw,4.6rem);
    font-weight:900; color:#fff; line-height:1.15; margin-bottom:20px;
    text-shadow:0 2px 18px rgba(0,0,0,.25);
}
.hero-content h1 .w {
    display:inline-block; opacity:0; transform:translateY(22px);
    animation:wrd .6s ease forwards;
}
.hero-content h1 .w:nth-child(1){animation-delay:.15s}
.hero-content h1 .w:nth-child(2){animation-delay:.30s}
.hero-content h1 .w:nth-child(3){animation-delay:.45s}
.hero-content h1 .w:nth-child(4){animation-delay:.60s}
@keyframes wrd { to{opacity:1;transform:none;} }

.hero-content p {
    font-size:clamp(1rem,2.2vw,1.25rem);
    color:rgba(255,255,255,.88); line-height:1.8; margin-bottom:38px;
}
.cta-ring { position:relative; display:inline-block; }
.cta-ring::before, .cta-ring::after {
    content:''; position:absolute; inset:-7px; border-radius:18px;
    border:2px solid rgba(255,255,255,.45);
    animation:ring 2.5s ease-out infinite;
}
.cta-ring::after { animation-delay:1.25s; }
@keyframes ring { 0%{transform:scale(1);opacity:.8} 100%{transform:scale(1.28);opacity:0} }
.hero-cta {
    display:inline-flex; align-items:center; gap:10px;
    padding:16px 42px; background:#fff; color:#9B0A22;
    font-size:1.15rem; font-weight:800; font-family:'Tajawal',sans-serif;
    border-radius:18px; text-decoration:none;
    box-shadow:0 6px 20px rgba(0,0,0,.15);
    transition:transform .3s, box-shadow .3s;
    position:relative; z-index:2;
}
.hero-cta:hover { transform:translateY(-3px); box-shadow:0 14px 38px rgba(0,0,0,.3); color:#9B0A22; }

.scroll-hint {
    position:absolute; bottom:28px; left:50%; transform:translateX(-50%);
    z-index:10; color:rgba(255,255,255,.55); font-size:.8rem;
    display:flex; flex-direction:column; align-items:center; gap:5px;
    animation:bob 2.2s infinite;
}
.scroll-hint svg { width:22px; height:22px; }
@keyframes bob { 0%,100%{transform:translateX(-50%) translateY(0)} 50%{transform:translateX(-50%) translateY(9px)} }

/* ─── REVEAL ─── */
.rv  { opacity:0; transform:translateY(38px); transition:opacity .65s,transform .65s; }
.rvl { opacity:0; transform:translateX(-38px);transition:opacity .65s,transform .65s; }
.rvr { opacity:0; transform:translateX(38px); transition:opacity .65s,transform .65s; }
.rv.on,.rvl.on,.rvr.on { opacity:1; transform:none; }
.d1{transition-delay:.1s}.d2{transition-delay:.22s}.d3{transition-delay:.34s}

/* ─── STATS STRIP ─── */
.stats-strip {
    background:var(--card-bg); border-bottom:1px solid var(--border);
    padding:32px 60px; transition:background .35s,border-color .35s;
}
.stats-row {
    max-width:1100px; margin:0 auto;
    display:grid; grid-template-columns:repeat(2,1fr); gap:20px;
}
.stat-box { display:flex; flex-direction:column; align-items:center; gap:6px; text-align:center; }
.stat-ico {
    width:48px; height:48px; border-radius:14px;
    display:flex; align-items:center; justify-content:center; margin-bottom:4px;
    transition:transform .3s;
}
.stat-box:hover .stat-ico { transform:scale(1.14) rotate(-5deg); }
.ic-blue { background:#EBF5FF; color:#1565C0; }
.ic-orange { background:#FFF3E0; color:#E65100; }
body.dark-mode .ic-blue { background:rgba(25,118,210,.15); color:#90CAF9; }
body.dark-mode .ic-orange { background:rgba(230,81,0,.15); color:#FFCC80; }
.stat-num { font-size:1.75rem; font-weight:900; color:var(--ink); }
.stat-lbl { font-size:.88rem; color:var(--muted); font-weight:500; }

/* ─── SECTION SPACING & TITLE ─── */
.page-section { padding:80px 24px; }
.sec-wrap { max-width:1140px; margin:0 auto; }
.sec-head { text-align:center; margin-bottom:52px; }
.sec-head h2 { font-size:clamp(1.7rem,3vw,2.5rem); font-weight:900; color:var(--ink); margin-bottom:10px; }
.sec-head p { font-size:1.05rem; color:var(--muted); max-width:520px; margin:0 auto; line-height:1.7; }
.sec-bar { height:4px; width:0; background:linear-gradient(90deg,var(--red),var(--green)); border-radius:4px; margin:16px auto 0; transition:width .9s cubic-bezier(.4,0,.2,1) .3s; }
.sec-head.on .sec-bar { width:60px; }

/* ─── PATHS ─── */
.paths-grid { display:grid; grid-template-columns:1fr 1fr; gap:28px; }
.path-card {
    background:var(--card-bg); border-radius:18px; overflow:hidden;
    border:1px solid var(--border); box-shadow:var(--shadow);
    transition:transform .3s, box-shadow .3s, border-color .35s;
}
.path-card.pc-red  { border-top:3px solid var(--red); }
.path-card.pc-green{ border-top:3px solid var(--green); }
.path-card:hover   { transform:translateY(-7px); box-shadow:0 16px 44px rgba(0,0,0,.14); }
.path-img { width:100%; height:320px; object-fit:cover; display:block; }
.path-body { padding:30px; display:flex; flex-direction:column; align-items:flex-end; text-align:right; }
.path-top { display:flex; align-items:center; justify-content:flex-end; gap:16px; margin-bottom:18px; width:100%; }
.path-badge {
    width:38px; height:38px; border-radius:10px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
}
.pb-red   { background:rgba(200,16,46,.12); }
.pb-green { background:rgba(46,125,50,.12); }
.path-title { font-size:1.35rem; font-weight:800; color:var(--ink); }
.path-desc { font-size:.95rem; color:var(--muted); line-height:1.7; margin-bottom:26px; }
.path-feats { list-style:none; display:flex; flex-direction:column; gap:14px; width:100%; }
.path-feats li { display:flex; align-items:center; justify-content:flex-end; gap:12px; font-size:.92rem; color:var(--muted); font-weight:500; }
.chk { width:20px; height:20px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; }
.chk-r { background:rgba(200,16,46,.15); }
.chk-g { background:rgba(46,125,50,.15); }
.chk svg { width:11px; height:11px; }

/* ─── PHOTO ROW ─── */
.photo-row { display:grid; grid-template-columns:repeat(3,1fr); gap:22px; margin-top:28px; }
.photo-card {
    border-radius:16px; overflow:hidden;
    border:1px solid var(--border); box-shadow:var(--shadow);
    background:var(--card-bg); transition:transform .3s,border-color .35s;
}
.photo-card:hover { transform:translateY(-5px); }
.photo-card img { width:100%; height:190px; object-fit:cover; display:block; }
.photo-label { padding:14px; text-align:center; font-weight:700; font-size:1rem; color:var(--ink); }

/* ─── FEATURES ─── */
.features-bg { background:var(--section-alt); border-top:1px solid var(--border); border-bottom:1px solid var(--border); transition:background .35s,border-color .35s; }
.feats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:34px; }
.feat-card {
    display:flex; flex-direction:column; align-items:center; text-align:center; gap:14px;
    background:var(--card-bg); padding:36px 28px; border-radius:18px;
    border:1px solid var(--border); box-shadow:var(--shadow);
    transition:transform .3s, box-shadow .3s, border-color .35s, background .35s;
}
.feat-card:hover { transform:translateY(-6px); box-shadow:0 14px 40px rgba(0,0,0,.12); }
.feat-ico { width:84px; height:84px; border-radius:22px; overflow:hidden; display:flex; align-items:center; justify-content:center; }
.feat-ico img { width:100%; height:100%; object-fit:cover; }
.feat-card h3 { font-size:1.15rem; font-weight:800; color:var(--ink); }
.feat-card p { font-size:.93rem; color:var(--muted); line-height:1.75; }

/* ─── CTA BANNER ─── */
.cta-section { padding:80px 24px; }
.cta-inner {
    max-width:940px; margin:0 auto;
    background:linear-gradient(135deg,#9B0A22,#C8102E 40%,#1B5E20 80%,#2E7D32);
    border-radius:24px; padding:60px 60px;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:36px;
}
.cta-inner h2 { font-size:clamp(1.5rem,3vw,2.2rem); font-weight:900; color:#fff; margin-bottom:12px; }
.cta-inner p { font-size:1.05rem; color:rgba(255,255,255,.85); line-height:1.7; max-width:450px; }
.cta-btn {
    display:inline-flex; align-items:center; gap:10px;
    padding:16px 38px; background:#fff; color:#9B0A22;
    font-size:1.1rem; font-weight:800; font-family:'Tajawal',sans-serif;
    border-radius:12px; box-shadow:0 8px 28px rgba(0,0,0,.2);
    transition:transform .25s, box-shadow .25s; white-space:nowrap;
}
.cta-btn:hover { transform:translateY(-3px); box-shadow:0 14px 38px rgba(0,0,0,.28); }

/* ─── FOOTER ─── */
.site-footer {
    background:#06090E; color:rgba(255,255,255,.42);
    text-align:center; padding:28px 24px; font-size:.9rem;
    position:relative; overflow:hidden;
}
.site-footer::before {
    content:''; position:absolute; top:0; left:-100%; width:50%; height:2px;
    background:linear-gradient(90deg,transparent,#C8102E,#2E7D32,transparent);
    animation:foot 3s ease infinite;
}
@keyframes foot { 0%{left:-100%}100%{left:200%} }

/* ─── RESPONSIVE ─── */
@media(max-width:900px){
    .paths-grid,.feats-grid,.photo-row{grid-template-columns:1fr;}
    .cta-inner{flex-direction:column;text-align:center;padding:40px 28px;}
    .site-header{padding:0 20px;}
    .nav-links{display:none;}
    .stats-row{grid-template-columns:1fr 1fr;}
}

/* ─── MARQUEE ─── */
.marquee-wrap {
    width: 100%;
    overflow: hidden;
    background: #0b0f16;
    border-top: 1px solid rgba(255,255,255,.06);
    border-bottom: 1px solid rgba(255,255,255,.06);
    padding: 14px 0;
    position: relative;
    z-index: 10;
}
.marquee-wrap::before,
.marquee-wrap::after {
    content: '';
    position: absolute;
    top: 0; bottom: 0; width: 120px;
    z-index: 2; pointer-events: none;
}
.marquee-wrap::before { left:0;  background:linear-gradient(90deg,#0b0f16,transparent); }
.marquee-wrap::after  { right:0; background:linear-gradient(-90deg,#0b0f16,transparent); }
.marquee-track {
    display: flex;
    width: max-content;
    animation: marqueeScroll 28s linear infinite;
}
.marquee-track:hover { animation-play-state: paused; }
.marquee-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 40px;
    white-space: nowrap;
    border-right: 1px solid rgba(255,255,255,.08);
}
.marquee-item img {
    width: 34px; height: 34px;
    object-fit: contain;
    border-radius: 50%;
    opacity: .85;
    filter: drop-shadow(0 0 6px rgba(200,100,50,.4));
}
.marquee-item span {
    font-size: .88rem;
    font-weight: 700;
    color: rgba(255,255,255,.55);
    letter-spacing: 1px;
    text-transform: uppercase;
}
@keyframes marqueeScroll {
    0%   { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}
/* ─── BGFX ANIMATED BACKGROUND ─── */
.bgfx {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    z-index: -1; pointer-events: none; overflow: hidden;
    background: var(--page-bg);
}
.bgfx__gradient {
    position: absolute; inset: 0;
    background: radial-gradient(circle at top right, rgba(200,16,46,0.03), transparent 60%),
                radial-gradient(circle at bottom left, rgba(46,125,50,0.03), transparent 60%);
}
body.dark-mode .bgfx__gradient {
    background: radial-gradient(circle at top right, rgba(200,16,46,0.07), transparent 60%),
                radial-gradient(circle at bottom left, rgba(46,125,50,0.07), transparent 60%);
}
.bgfx__glow {
    position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.4;
    animation: bgfx-float 20s infinite ease-in-out alternate;
}
.bgfx__glow--1 { width: 400px; height: 400px; background: rgba(200,16,46,0.08); top: -100px; left: -100px; animation-duration: 25s; }
.bgfx__glow--2 { width: 500px; height: 500px; background: rgba(46,125,50,0.06); bottom: -150px; right: -150px; animation-duration: 22s; animation-delay: -5s; }
.bgfx__glow--3 { width: 300px; height: 300px; background: rgba(25,118,210,0.05); top: 40%; left: 60%; animation-duration: 28s; animation-delay: -12s; }

body.dark-mode .bgfx__glow--1 { background: rgba(200,16,46,0.12); }
body.dark-mode .bgfx__glow--2 { background: rgba(46,125,50,0.1); }
body.dark-mode .bgfx__glow--3 { background: rgba(25,118,210,0.08); }

.bgfx__noise {
    position: absolute; inset: 0; opacity: 0.03; mix-blend-mode: overlay;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
}
body.dark-mode .bgfx__noise { opacity: 0.05; }

.bgfx__icons { position: absolute; inset: 0; }
.bgfx-icon {
    position: absolute; bottom: -50px;
    animation: bgfx-rise linear infinite;
    color: var(--muted); opacity: 0.15;
}
body.dark-mode .bgfx-icon { opacity: 0.2; }

@keyframes bgfx-float {
    0%   { transform: translate(0, 0) scale(1); }
    100% { transform: translate(15%, 15%) scale(1.1); }
}
@keyframes bgfx-rise {
    0%   { transform: translateY(0) rotate(0deg); }
    100% { transform: translateY(-120vh) rotate(360deg); }
}

.bgfx--paused .bgfx__glow, .bgfx--paused .bgfx-icon { animation-play-state: paused !important; }

@media (prefers-reduced-motion: reduce) {
    .bgfx__glow, .bgfx-icon { animation: none !important; }
}

/* Ensure content stays above background */
.site-header, .hero, .stats-strip, .page-section, .marquee-wrap, .cta-section, .site-footer {
    position: relative; z-index: 1;
}
</style>
</head>
<body>

<!-- ╔╗ BGFX BACKGROUND ╔╗ -->
<div class="bgfx" aria-hidden="true">
  <div class="bgfx__gradient"></div>
  <div class="bgfx__glow bgfx__glow--1"></div>
  <div class="bgfx__glow bgfx__glow--2"></div>
  <div class="bgfx__glow bgfx__glow--3"></div>
  <div class="bgfx__icons" id="bgfxIcons"></div>
  <div class="bgfx__noise"></div>
</div>

<!-- ╔╗ HEADER ╔╗ -->
<header class="site-header" id="siteHeader">
    <a href="#" class="nav-logo">
        <div class="nav-logo-icon">
            <img src="assets/img/logo.png" alt="A.S.R Logo" style="width:40px;height:40px;object-fit:contain;border-radius:50%;">
        </div>
        <div class="nav-brand">
            <span class="nav-brand-name">A.S.R</span>
            <span class="nav-brand-sub">Diabetes Monitoring</span>
        </div>
    </a>
    <div class="nav-actions">
        <button class="dark-toggle" id="darkToggle" aria-label="وضع ليلي">
            <div class="sky-elements">
                <div class="cloud cloud-1"></div>
                <div class="cloud cloud-2"></div>
                <div class="stars">
                    <div class="star star-1"></div>
                    <div class="star star-2"></div>
                    <div class="star star-3"></div>
                </div>
            </div>
            <div class="toggle-thumb" id="toggleThumb"></div>
        </button>
    </div>
</header>

<!-- ╔╗ HERO ╔╗ -->
<section class="hero">
    <canvas id="hero-particles"></canvas>
    <div class="hero-bg-slides">
        <div class="hero-bg-slide hbs-1 bg-active" style="display:flex; justify-content:flex-end; align-items:center; padding-left:5%;">
            <div class="path-card pc-red rvr" style="transform:scale(0.85); box-shadow: 0 25px 50px rgba(0,0,0,0.5); transform-origin: left center;">
                <img src="assets/img/path_diabetes.png" alt="مسار المتابعة الذاتية" class="path-img">
                <div class="path-body">
                  <div class="path-top">
                    <span class="path-title">مسار المتابعة الذاتية</span>
                    <div class="path-badge pb-red">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#C8102E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                      </svg>
                    </div>
                  </div>
                  <p class="path-desc" style="white-space:normal;">دعم شامل للأشخاص المتعايشين مع السكري. احصل على أدوات وموارد وإرشادات مخصصة لإدارة صحتك بفعالية.</p>
                  <ul class="path-feats">
                    <li>أدوات تتبع ومراقبة سكر الدم <div class="chk chk-r"><svg viewBox="0 0 24 24" fill="none" stroke="#C8102E" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div></li>
                    <li>خطط وجبات مخصصة وإرشادات غذائية <div class="chk chk-r"><svg viewBox="0 0 24 24" fill="none" stroke="#C8102E" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div></li>
                  </ul>
                </div>
            </div>
        </div>
        <div class="hero-bg-slide hbs-2" style="display:flex; justify-content:flex-start; align-items:center; padding-right:5%;">
            <div class="path-card pc-green rvl" style="transform:scale(0.85); box-shadow: 0 25px 50px rgba(0,0,0,0.5); transform-origin: right center;">
                <img src="assets/img/path_prevention.png" alt="مسار الوقاية" class="path-img">
                <div class="path-body">
                  <div class="path-top">
                    <span class="path-title">مسار الوقاية</span>
                    <div class="path-badge pb-green">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2E7D32" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                      </svg>
                    </div>
                  </div>
                  <p class="path-desc" style="white-space:normal;">تدابير استباقية لتقليل خطر الإصابة بمرض السكري. تعلم العادات الصحية وتغييرات نمط الحياة للبقاء في المقدمة.</p>
                  <ul class="path-feats">
                    <li>أدوات تقييم المخاطر والفحص <div class="chk chk-g"><svg viewBox="0 0 24 24" fill="none" stroke="#2E7D32" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div></li>
                    <li>برامج نمط حياة صحي وتمارين رياضية <div class="chk chk-g"><svg viewBox="0 0 24 24" fill="none" stroke="#2E7D32" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div></li>
                  </ul>
                </div>
            </div>
        </div>
        <div class="hero-bg-slide hbs-3" style="display:flex; gap:1rem; justify-content:flex-end; align-items:center; padding-left:2%;">
             <div class="photo-card rv" style="transform:scale(0.75); box-shadow: 0 25px 50px rgba(0,0,0,0.5); transform-origin: left center;">
                <img src="assets/img/card_doctor.png" alt="الفريق الطبي" class="photo-img">
                <div class="photo-label">الفريق الطبي</div>
             </div>
             <div class="photo-card rv" style="transform:scale(0.75); box-shadow: 0 25px 50px rgba(0,0,0,0.5); transform-origin: left center;">
                <img src="assets/img/card_exercise.png" alt="جلسات التعافي" class="photo-img">
                <div class="photo-label">جلسات التعافي</div>
             </div>
             <div class="photo-card rv" style="transform:scale(0.75); box-shadow: 0 25px 50px rgba(0,0,0,0.5); transform-origin: left center;">
                <img src="assets/img/card_nutrition.png" alt="التغذية الصحية" class="photo-img">
                <div class="photo-label">التغذية الصحية</div>
             </div>
        </div>
        <div class="hero-bg-slide hbs-4" style="display:flex; justify-content:flex-start; align-items:center; padding-right:5%;">
            <div class="wm-card" style="transform:scale(0.9); transform-origin: right center; text-align:center;">
                <div style="font-size:3rem;margin-bottom:12px">🔔</div>
                <div class="wm-title" style="text-align:center">التنبيهات الذكية</div>
                <div style="font-size:.82rem;opacity:.8;line-height:1.6">أي تغييرات غير طبيعية فى القراءات.</div>
            </div>
        </div>
        <div class="hero-bg-slide hbs-5" style="display:flex; justify-content:flex-end; align-items:center; padding-left:5%;">
            <div class="wm-card" style="text-align:center; transform:scale(0.9); transform-origin: left center;">
                <div style="font-size:3rem;margin-bottom:12px">📚</div>
                <div class="wm-title" style="text-align:center">حتى التوعية</div>
                <div style="font-size:.82rem;opacity:.7">معلومات صحية موثوقة<br>بمصادر علمية معتمدة</div>
            </div>
        </div>
    </div>
    <div class="hero-overlay"></div>

    <div class="hero-content">
        <h1>
            <span class="w">تابع</span>&nbsp;
            <span class="w">رحلتك</span><br>
            <span class="w" style="color:#FFAB91">الصحية</span>
        </h1>
        <p style="display:none"></p>
        <div class="cta-ring">
            <a href="questionnaire/questionnaire.php" class="hero-cta">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
                ابدأ معنا
            </a>
        </div>
    </div>

    <div class="scroll-hint">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
</section>




<!-- ╔╗ FOOTER ╔╗ -->
<footer class="site-footer">
    <p>© 2026 A.S.R Diabetes Care &mdash; جميع الحقوق محفوظة</p>
</footer>

<script>
/* Dark mode – uses same data-theme system as all other pages */
const toggle = document.getElementById('darkToggle');
const thumb  = document.getElementById('toggleThumb');

function updateWelcomeThumb() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    thumb.textContent = isDark ? '🌙' : '☀️';
}
updateWelcomeThumb();

toggle.addEventListener('click', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    localStorage.setItem('asr_dark', newTheme === 'dark' ? '1' : '0');
    updateWelcomeThumb();
});

/* Header scroll */
window.addEventListener('scroll', () => {
    document.getElementById('siteHeader').classList.toggle('scrolled', window.scrollY > 40);
});

/* Hero BG rotation */
const slides = document.querySelectorAll('.hero-bg-slide');
let cur = 0;
setInterval(() => {
    slides[cur].classList.remove('bg-active');
    cur = (cur + 1) % slides.length;
    slides[cur].classList.add('bg-active');
}, 2000);

/* Scroll reveal */
const rvObs = new IntersectionObserver((entries) => {
    entries.forEach(e => { if(e.isIntersecting){ e.target.classList.add('on'); rvObs.unobserve(e.target); } });
}, { threshold: 0.13 });
document.querySelectorAll('.rv,.rvl,.rvr').forEach(el => rvObs.observe(el));

/* Sec-head bar */
const barObs = new IntersectionObserver((entries) => {
    entries.forEach(e => { if(e.isIntersecting) e.target.classList.add('on'); });
}, { threshold: 0.5 });
document.querySelectorAll('.sec-head').forEach(el => barObs.observe(el));

/* Animated counter */
function animCount(el) {
    const target = +el.dataset.target;
    if(isNaN(target)) return;
    let start = 0; const dur = 1800;
    const step = ts => {
        if(!start) start = ts;
        const p = Math.min((ts - start) / dur, 1);
        el.textContent = '+' + Math.floor(p * target).toLocaleString();
        if(p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}
const cntObs = new IntersectionObserver((entries) => {
    entries.forEach(e => { if(e.isIntersecting){ animCount(e.target); cntObs.unobserve(e.target); } });
}, { threshold: 0.5 });
document.querySelectorAll('.stat-num[data-target]').forEach(el => cntObs.observe(el));

/* Particles */
const canvas = document.getElementById('hero-particles');
const ctx    = canvas.getContext('2d');
let W = canvas.width  = window.innerWidth;
let H = canvas.height = window.innerHeight;
window.addEventListener('resize', () => {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
});
const pts = Array.from({length:55}, () => ({
    x: Math.random()*W, y: Math.random()*H,
    vx:(Math.random()-.5)*.42, vy:(Math.random()-.5)*.42,
    r: Math.random()*1.8+.8
}));
(function draw(){
    ctx.clearRect(0,0,W,H);
    pts.forEach(p => {
        p.x += p.vx; p.y += p.vy;
        if(p.x<0||p.x>W) p.vx*=-1;
        if(p.y<0||p.y>H) p.vy*=-1;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
        ctx.fillStyle = 'rgba(255,255,255,.22)';
        ctx.fill();
    });
    requestAnimationFrame(draw);
})();
/* BGFX Icons generator */
(function initBgfx() {
    const container = document.getElementById('bgfxIcons');
    if(!container) return;
    
    const svgs = [
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 3H14V10H21V14H14V21H10V14H3V10H10V3Z"/></svg>',
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="4" width="16" height="16" rx="8" transform="rotate(45 12 12)"/><line x1="12" y1="2" x2="12" y2="22" transform="rotate(45 12 12)"/></svg>',
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>'
    ];

    const iconCount = 35;
    const frag = document.createDocumentFragment();

    for(let i=0; i<iconCount; i++) {
        const el = document.createElement('div');
        el.className = 'bgfx-icon';
        el.innerHTML = svgs[Math.floor(Math.random() * svgs.length)];
        
        const size = Math.random() * 20 + 15;
        const left = Math.random() * 100;
        const delay = Math.random() * -60;
        const duration = Math.random() * 30 + 30;
        const opacity = Math.random() * 0.15 + 0.05;

        el.style.width = size + 'px';
        el.style.height = size + 'px';
        el.style.left = left + 'vw';
        el.style.animationDelay = delay + 's';
        el.style.animationDuration = duration + 's';
        el.style.opacity = opacity;

        frag.appendChild(el);
    }
    container.appendChild(frag);

    document.addEventListener('visibilitychange', () => {
        if(document.hidden) {
            document.querySelector('.bgfx').classList.add('bgfx--paused');
        } else {
            document.querySelector('.bgfx').classList.remove('bgfx--paused');
        }
    });

    if(window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.querySelector('.bgfx').classList.add('bgfx--paused');
    }
})();
</script>
</body>
</html>
