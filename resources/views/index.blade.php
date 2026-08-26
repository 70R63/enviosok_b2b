<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZIGO | Cotiza y genera tus guías</title>
    <link rel="stylesheet" href="{{ asset('css/b2c-responsive.css') }}">

    <style>
        :root {
            --zigo-blue: #3867f5;
            --zigo-blue-dark: #1d4ed8;
            --zigo-orange: #f97316;
            --zigo-yellow: #facc15;
            --zigo-text: #111827;
            --zigo-muted: #52607a;
            --zigo-soft: #f7f9ff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: var(--zigo-text);
            background: #ffffff;
        }

        a {
            text-decoration: none;
        }

        .page-wrap {
            min-height: 100vh;
            background:
                linear-gradient(180deg, #ffffff 0%, #ffffff 62%, #f7f9ff 100%);
        }

        .top-header {
            background: #ffffff;
            border-bottom: 1px solid #edf0f7;
        }

        .nav {
            max-width: 1220px;
            margin: 0 auto;
            padding: 8px 24px 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: nowrap;
        }

        .brand { display:flex; flex-direction:column; align-items:flex-start; justify-content:center; text-decoration:none; gap:2; min-width:330px; }
        .brand-logo-frame { width:330px; max-width:100%; min-height:72px; display:flex; align-items:center; justify-content:flex-start; background:transparent; box-shadow:none; border-radius:0; overflow:visible; }

        .brand-logo {
            width: 310px;
            max-width: 100%;
            height: auto;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 10px 20px rgba(56,103,245,.20));
            animation: zigoEnter .8s ease-out;
            transition: transform .35s ease, filter .35s ease;
            will-change: transform;
        }

        .brand:hover .brand-logo {
            transform: translateX(8px) scale(1.04);
            filter:
                drop-shadow(0 0 8px rgba(56,189,248,.8))
                drop-shadow(0 0 14px rgba(37,99,235,.6));
        }

        .brand-tagline { margin-top:-14px; font-size:12px; letter-spacing:3px; text-transform:uppercase; color:#1e40af; font-weight:900; line-height:1.2; white-space:nowrap; }

        @keyframes zigoEnter {
            from {
                opacity: 0;
                transform: translateX(-28px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .main-nav {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 16px;
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        .main-nav a {
            color: #08152f;
            font-weight: 900;
            font-size: 16px;
        }

        .main-nav a:hover {
            color: var(--zigo-blue);
        }

        .nav-login {
            padding: 14px 22px;
            border-radius: 14px;
            background: #ffffff;
            color: var(--zigo-blue) !important;
            box-shadow: 0 14px 32px rgba(17,24,39,.10);
        }

        .nav-register,
        .nav-account {
            background: var(--zigo-blue);
            color: #ffffff !important;
            padding: 14px 24px;
            border-radius: 14px;
            font-weight: 900;
            box-shadow: 0 14px 32px rgba(56,103,245,.28);
        }

        .nav-register:hover,
        .nav-account:hover {
            background: var(--zigo-blue-dark);
            color: #ffffff !important;
        }

        .quote-box {
            max-width: 1220px;
            margin: 0 auto;
            padding: 34px 24px 34px;
        }

        .quote-card {
            width: 100%;
            padding: 18px 20px 20px;
            background: var(--zigo-blue);
            color: #ffffff;
            border-radius: 18px;
            box-shadow: 0 18px 42px rgba(56,103,245,.25);
        }

        .quote-title {
            text-align: center;
            font-size: 22px;
            font-weight: 900;
            margin: 0 0 14px;
            color: #ffffff;
        }

        .quote-form { display: grid; grid-template-columns: 1.25fr 1.25fr 1.05fr .8fr 1.45fr 1.1fr; gap: 16px; align-items: end; }

        .field label {
            display: block;
            font-size: 15px;
            margin-bottom: 7px;
            font-weight: 800;
            color: #ffffff;
        }

        .field input,
        .field select {
            width: 100%;
            height: 46px;
            border-radius: 13px;
            border: 1px solid rgba(255,255,255,.25);
            padding: 0 15px;
            background: #ffffff;
            color: #111827;
            font-size: 15px;
            outline: none;
        }

        .field input:focus,
        .field select:focus {
            border-color: var(--zigo-yellow);
            box-shadow: 0 0 0 3px rgba(250,204,21,.20);
        }

        .btn-yellow {
            height: 46px;
            border: none;
            border-radius: 13px;
            background: var(--zigo-orange);
            color: #ffffff;
            font-weight: 900;
            cursor: pointer;
            font-size: 15px;
            box-shadow: 0 12px 24px rgba(249,115,22,.24);
        }

        .btn-yellow:hover {
            background: #ea580c;
        }

        .hero {
            max-width: 1220px;
            margin: 0 auto;
            padding: 28px 24px 76px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 58px;
            align-items: center;
            position: relative;
        }

        .hero::before {
            content: '';
            position: absolute;
            left: 24px;
            right: 24px;
            bottom: 0;
            height: 160px;
            background:
                linear-gradient(90deg, rgba(56,103,245,.06), rgba(249,115,22,.04));
            border-radius: 32px 32px 0 0;
            z-index: 0;
        }

        .hero-copy,
        .hero-visual {
            position: relative;
            z-index: 1;
        }

        .hero-kicker {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #08152f;
            font-size: 18px;
            font-weight: 900;
            margin: 0 0 18px;
        }

        .hero-kicker span {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #08152f;
            border-radius: 999px;
            color: var(--zigo-blue);
            font-size: 16px;
        }

        .hero h1 {
            font-size: 56px;
            line-height: 1.1;
            margin: 0 0 20px;
            color: #0f172a;
            letter-spacing: -1.8px;
        }

        .hero h1 strong {
            color: var(--zigo-orange);
        }

        .hero p {
            font-size: 19px;
            line-height: 1.75;
            color: var(--zigo-muted);
            margin: 0 0 28px;
            max-width: 610px;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: center;
        }

        .hero-primary {
            display: inline-block;
            padding: 15px 24px;
            border-radius: 14px;
            background: var(--zigo-orange);
            color: white;
            font-weight: 900;
            box-shadow: 0 16px 34px rgba(249,115,22,.28);
        }

        .hero-secondary {
            display: inline-block;
            padding: 15px 24px;
            border-radius: 14px;
            color: var(--zigo-blue);
            font-weight: 900;
            background: #ffffff;
            box-shadow: 0 12px 28px rgba(17,24,39,.08);
        }

        .hero-visual {
            min-height: 390px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-card {
            width: 100%;
            max-width: 520px;
            min-height: 340px;
            border-radius: 32px;
            background:
                radial-gradient(circle at 15% 20%, rgba(255,255,255,.95), rgba(255,255,255,.65) 28%, rgba(56,103,245,.08) 62%, rgba(249,115,22,.08));
            box-shadow: 0 30px 70px rgba(15,23,42,.15);
            border: 1px solid #edf0f7;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .hero-card::before,
        .hero-card::after {
            content: '';
            position: absolute;
            border-radius: 999px;
            background: rgba(56,103,245,.08);
        }

        .hero-card::before {
            width: 210px;
            height: 210px;
            right: -80px;
            top: -70px;
        }

        .hero-card::after {
            width: 160px;
            height: 160px;
            left: -65px;
            bottom: -55px;
            background: rgba(249,115,22,.08);
        }

        .hero-logo {
            width: 82%;
            max-width: 430px;
            position: relative;
            z-index: 1;
            object-fit: contain;
            filter: drop-shadow(0 18px 28px rgba(56,103,245,.20));
        }

        .section {
            max-width: 1220px;
            margin: 0 auto;
            padding: 70px 24px;
        }

        .section h2 {
            font-size: 36px;
            margin: 0 0 18px;
            text-align: center;
            color: #0f172a;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
            margin-top: 35px;
        }

        .card {
            padding: 28px;
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 14px 36px rgba(15,23,42,.08);
            border: 1px solid #eef2ff;
        }

        .card h3 {
            margin-top: 0;
            color: var(--zigo-blue);
        }

        .feature-card-link {
            display: block;
            color: inherit;
            transition: .2s ease;
        }

        .feature-card-link:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 40px rgba(37,99,235,.18);
        }

        .feature-card-link span {
            display: inline-block;
            margin-top: 14px;
            color: var(--zigo-blue);
            font-weight: 900;
        }

        .logos {
            display: flex;
            justify-content: center;
            gap: 45px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 35px;
        }

        .logos img {
            max-height: 55px;
            max-width: 160px;
            object-fit: contain;
        }

        .faq-section {
            padding: 80px 24px;
            background: var(--zigo-soft);
        }

        .faq-section h2 {
            text-align: center;
            font-size: 38px;
            font-weight: 900;
            margin: 0 0 36px;
            color: #111827;
        }

        .faq-grid {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(2,1fr);
            gap: 20px;
        }

        .faq-item {
            background: white;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 12px 30px rgba(15,23,42,.08);
        }

        .faq-item h3 {
            margin: 0 0 10px;
            color: var(--zigo-blue-dark);
        }

        .faq-item p {
            margin: 0;
            color: #475569;
            line-height: 1.6;
        }

        .cta {
            background: #fff7d6;
            padding: 55px 24px;
            text-align: center;
        }

        .cta h2 {
            font-size: 34px;
            margin: 0 0 12px;
            color: #0f172a;
        }

        .cta a {
            display: inline-block;
            margin-top: 22px;
            padding: 14px 26px;
            background: var(--zigo-orange);
            color: white;
            border-radius: 12px;
            font-weight: 900;
        }

        .whatsapp {
            position: fixed;
            left: 22px;
            bottom: 22px;
            width: 58px;
            height: 58px;
            background: #25d366;
            color: white;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 14px 28px rgba(37,211,102,.32);
            z-index: 50;
        }

        .whatsapp svg {
            width: 31px;
            height: 31px;
            fill: currentColor;
        }

        .autocomplete-wrap {
            position: relative;
        }

        .suggestions { display:none; position:absolute; top:76px; left:0; width:520px; max-height:230px; overflow-y:auto; background:#ffffff!important; color:#111827!important; border-radius:10px; box-shadow:0 14px 35px rgba(0,0,0,.25); z-index:9999; }
        .suggestion-item { padding:12px 14px; font-size:14px; cursor:pointer; border-bottom:1px solid #e5e7eb; white-space:normal; line-height:1.35; }

        .suggestion-item:hover {
            background: #f3f4f6;
        }

        .suggestion-item strong {
            color: #111827;
        }

        .suggestion-item small {
            color: #475569;
        }

        .cp-help { display:none !important; margin-top:6px; font-size:12px; color:#ffffff; }
        .cp-help.is-error { display:block !important; color:#fee2e2; font-weight:800; }
        .box-dimensions { display:grid; grid-template-columns:repeat(3, 1fr); overflow:hidden; border-radius:13px; background:#ffffff; height:46px; }
        .box-dimensions input { height:46px!important; border-radius:0!important; border:none!important; border-right:1px solid #d1d5db!important; text-align:center; padding:0 8px!important; }
        .box-dimensions input:last-child { border-right:none!important; }

        .peso-volumetrico-box { grid-column:1 / -1; margin-top:4px; padding:14px 18px; border-radius:16px; background:rgba(255,255,255,.16); color:#ffffff; font-size:14px; line-height:1.8; box-shadow:inset 0 0 0 1px rgba(255,255,255,.18); display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; }        
        .peso-volumetrico-box strong { font-weight: 900; color: #ffffff; }
        .peso-volumetrico-box span { font-weight: 800; }
        .peso-volumetrico-box div { background:rgba(255,255,255,.10); border-radius:12px; padding:10px 12px; }

        .landing-alert {
            max-width: 780px;
            margin: 0 auto 18px auto;
            background: #fff7ed;
            border: 1px solid #fdba74;
            color: #9a3412;
            padding: 16px 20px;
            border-radius: 14px;
            font-weight: 900;
            text-align: center;
            line-height: 1.45;
        }

        .landing-alert a {
            color: #ea580c;
            text-decoration: underline;
            text-underline-offset: 3px;
            font-weight: 900;
        }

        .landing-alert a:hover {
            color: #c2410c;
        }

        .landing-alert-actions a:last-child {
            background: #4361ee;
        }

        .quote-reset-wrap {
            width: 100%;
            text-align: center;
            margin: 14px 0 0;
        }

        .quote-reset-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 18px;
            border-radius: 999px;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.22);
            color: #ffffff;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
        }

        .quote-reset-link:hover {
            background: rgba(255,255,255,.22);
        }

        .field-dimensions.is-disabled { opacity: .45; pointer-events: none; }

        .solutions-section {
            background: #ffffff;
            padding: 70px 24px;
        }

        .section-container {
            max-width: 1220px;
            margin: 0 auto;
        }

        .solutions-section h2 {
            text-align: center;
            font-size: 38px;
            line-height: 1.15;
            margin: 0 0 36px;
            color: #0f172a;
            font-weight: 900;
        }

        .solutions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .solution-card {
            background: #ffffff;
            border: 1px solid #e8edf7;
            border-radius: 24px;
            padding: 30px;
            min-height: 230px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .06);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .solution-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 24px 55px rgba(15, 23, 42, .10);
        }

        .solution-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: #eef4ff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
            font-size: 22px;
        }

        .solution-card h3 {
            margin: 0 0 12px;
            font-size: 22px;
            color: #4361ee;
            font-weight: 900;
        }

        .solution-card p {
            margin: 0 0 18px;
            color: #334155;
            line-height: 1.45;
            font-size: 16px;
        }

        .solution-card a,
        .solution-card span {
            color: #4361ee;
            font-weight: 900;
            text-decoration: none;
        }

        @media (max-width: 900px) {
            .solutions-grid {
                grid-template-columns: 1fr;
            }

            .solutions-section h2 {
                font-size: 30px;
            }
        }

        @media (max-width: 900px) {
            .suggestions {
                width: 100%;
            }

            .box-dimensions {
                grid-template-columns: 1fr;
            }

            .box-dimensions input {
                border-right: none !important;
                border-bottom: 1px solid #d1d5db !important;
            }

            .box-dimensions input:last-child {
                border-bottom: none !important;
            }
        }

        .zigo-footer {
            background: #050a1a;
            color: #ffffff;
            padding: 36px 0;
        }

        .footer-inner {
            width: min(1180px, calc(100% - 40px));
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr repeat(4, 1fr);
            gap: 28px;
        }

        .zigo-footer h4 {
            margin: 0 0 14px;
            font-size: 16px;
            font-weight: 900;
        }

        .zigo-footer p {
            margin: 0;
            color: #dbe4ff;
            line-height: 1.5;
        }

        .zigo-footer a {
            display: block;
            color: #dbe4ff;
            text-decoration: none;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .zigo-footer a:hover {
            color: #ffffff;
        }

        .zigo-social-follow {
            text-align: center;
        }

        .zigo-social-links {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .zigo-footer .zigo-social-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
            padding: 6px 8px;
            border-radius: 999px;
            font-size: 13px;
            line-height: 1;
            transition: color .2s ease, background-color .2s ease, transform .2s ease;
        }

        .zigo-social-link svg {
            width: 17px;
            height: 17px;
            flex: 0 0 auto;
            fill: currentColor;
        }

        .zigo-footer .zigo-social-link:hover {
            background: rgba(255, 255, 255, .1);
            transform: translateY(-2px);
        }

        .zigo-social-link:focus-visible {
            outline: 2px solid #ffffff;
            outline-offset: 3px;
        }

        @media (max-width: 768px) {
            .footer-inner {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }

        .business-types {
    max-width: 1120px;
    margin: 88px auto 72px;
    padding: 0 24px;
}

        .section-title {
            text-align: center;
            margin-bottom: 30px;
        }

        .section-title span {
            color: #4361ee;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-size: 13px;
        }

        .section-title h2 {
            font-size: 38px;
            margin: 10px 0;
            color: #111827;
            font-weight: 900;
        }

        .section-title p {
            max-width: 760px;
            margin: 0 auto;
            color: #64748b;
            font-size: 17px;
            line-height: 1.5;
        }

        .business-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .business-card {
            background: #ffffff;
            border-radius: 22px;
            padding: 28px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .08);
            border: 1px solid #e5e7eb;
        }

        .business-icon {
            font-size: 34px;
            margin-bottom: 16px;
        }

        .business-card h3 {
            font-size: 22px;
            color: #111827;
            margin: 0 0 10px;
            font-weight: 900;
        }

        .business-card p {
            color: #64748b;
            line-height: 1.45;
            margin: 0;
        }

        @media(max-width: 900px) {
            .business-grid {
                grid-template-columns: 1fr;
            }

            .section-title h2 {
                font-size: 30px;
            }
        }

        .business-types .section-title .business-main-title {
            color: #4361ee !important;
            font-size: 40px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 4px;
            margin: 0 0 14px;
        }

        .business-types .section-title .business-subtitle {
            color: #111827;
            font-size: 38px;
            font-weight: 900;
            margin: 0 0 14px;
        }

        @media(max-width: 900px) {
            .business-types .section-title .business-main-title {
                font-size: 30px;
                letter-spacing: 2px;
            }

            .business-types .section-title .business-subtitle {
                font-size: 28px;
            }
        }

        .business-link {
            display: inline-block;
            margin-top: 16px;
            color: #4361ee;
            font-weight: 900;
            text-decoration: none;
        }

        .business-link:hover {
            text-decoration: underline;
        }

        .hero-image-card {
            padding: 0;
            overflow: hidden;
            position: relative;
            min-height: 340px;
        }

        .hero-image {
            width: 100%;
            height: 100%;
            min-height: 340px;
            object-fit: cover;
            display: block;
            border-radius: 28px;
        }

        .hero-image-card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(67, 97, 238, .20), rgba(255, 255, 255, .08));
            pointer-events: none;
        }

        .mobile-nav-toggle { display: none; }

        /* B2C_RC05_MOBILE_START: intentionally last in the landing cascade. */
        @media (max-width: 767px) {
            html, body, .page-wrap, .top-header, .nav, .quote-box, .quote-card,
            .quote-form, .quote-form > *, .field, .field-dimensions,
            .box-dimensions, .landing-quote-option {
                width: 100% !important; max-width: 100% !important;
                min-width: 0 !important; box-sizing: border-box !important;
            }
            html, body { margin: 0 !important; overflow-x: clip !important; }
            .page-wrap { overflow: visible !important; }
            .nav { display: grid !important; grid-template-columns: minmax(0,1fr) auto !important; padding: 10px 14px !important; gap: 8px 12px !important; }
            .brand { min-width: 0 !important; width: 100% !important; overflow: hidden; }
            .brand-logo-frame { width: 100% !important; min-height: 48px !important; overflow: visible !important; }
            .brand-logo { width: min(250px,72vw) !important; max-width: 100% !important; transform: none !important; }
            .brand:hover .brand-logo { transform: none !important; }
            .brand-tagline { margin-top: -6px !important; max-width: 100%; white-space: normal !important; letter-spacing: 1.5px !important; }
            .mobile-nav-toggle { display: inline-flex; width: 48px; height: 48px; align-items: center; justify-content: center; border: 1px solid #dbeafe; border-radius: 12px; background: #fff; color: #1d4ed8; font-size: 24px; cursor: pointer; }
            .main-nav { display: none !important; grid-column: 1 / -1; width: 100% !important; max-width: 100% !important; min-width: 0 !important; grid-template-columns: minmax(0,1fr) !important; gap: 8px !important; }
            .main-nav.is-open { display: grid !important; }
            .main-nav a { display: flex !important; width: 100% !important; max-width: 100% !important; min-width: 0 !important; min-height: 44px; align-items: center; justify-content: center; padding: 10px 12px !important; white-space: normal !important; text-align: center; }
            .quote-box { padding: 16px 12px !important; margin: 0 !important; }
            .quote-card { padding: 16px 14px !important; margin: 0 !important; }
            .quote-form { display: grid !important; grid-template-columns: minmax(0,1fr) !important; gap: 12px !important; align-items: stretch !important; }
            .quote-form > *, .field, .field input, .field select, .btn-yellow { position: static !important; width: 100% !important; max-width: 100% !important; min-width: 0 !important; }
            .box-dimensions { display: grid !important; grid-template-columns: minmax(0,1fr) !important; gap: 8px !important; }
            .box-dimensions input { width: 100% !important; min-width: 0 !important; border: 1px solid #d1d5db !important; border-radius: 10px !important; }
            .suggestions { left: 0 !important; right: auto !important; width: 100% !important; max-width: 100% !important; }
            .btn-yellow, .quote-reset-link { display: flex !important; width: 100% !important; min-height: 46px; align-items: center; justify-content: center; }
            .quote-reset-wrap { width: 100% !important; margin: 12px 0 0 !important; }
            .landing-quote-option { display: grid !important; grid-template-columns: minmax(0,1fr) !important; gap: 14px !important; padding: 16px !important; overflow: visible !important; }
            .landing-quote-service, .landing-quote-metadata, .landing-quote-price { min-width: 0 !important; max-width: 100% !important; overflow-wrap: anywhere !important; white-space: normal !important; }
            .landing-quote-metadata { grid-template-columns: minmax(0,1fr) !important; }
            .landing-quote-price { width: 100%; text-align: center; }
            .landing-quote-select { width: 100% !important; min-height: 46px; }
            .landing-quote-modal { padding: 3vw !important; }
            .landing-quote-modal-card { width: min(94vw,620px) !important; max-width: min(94vw,620px) !important; max-height: 90vh !important; overflow-y: auto !important; overflow-x: hidden !important; }
            .landing-quote-modal-actions { display: flex !important; flex-direction: column-reverse !important; }
            .landing-quote-modal-actions button { width: 100% !important; min-height: 44px; }
        }
        /* B2C_RC05_MOBILE_END */

    </style>
</head>
<body class="zigo-public-landing">
<div class="page-wrap">
    <header class="top-header">
        <div class="nav">
            <a href="/" class="brand">
                <div class="brand-logo-frame">
                    <img src="{{ asset('img/zigo-logo.png') }}" alt="ZIGO" class="brand-logo">
                </div>
                <div class="brand-tagline">
                    Tecnología • Logística • Conexión
                </div>
            </a>
            <button type="button" class="mobile-nav-toggle" aria-label="Abrir menú" aria-controls="main-navigation" aria-expanded="false">☰</button>
            <nav class="main-nav" id="main-navigation"><a href="{{ route('public.nosotros') }}">Nosotros</a><a href="{{ route('public.paqueteria') }}">Paquetería</a><a href="{{ route('public.faqs') }}">FAQ'S</a><a href="{{ route('zigo-platform.landing', [], false) }}">ZIGO Platform</a><a href="{{ route('agentes-ia.landing') }}">Agentes IA</a><a href="{{ url('/login') }}" class="nav-login">Iniciar sesión</a><a href="{{ route('b2c.register') }}" class="nav-register">Registro</a></nav>
        </div>
    </header>

    <section class="quote-box" id="cotizar">
        <div class="quote-card">
            <div class="quote-title">Cotiza gratis tu envío</div>

            @if(session('rate_error'))
                <div class="landing-alert" role="alert">{{ session('rate_error') }}</div>
            @endif

            @if(session('login_required'))
                <div class="landing-alert">
                    Para continuar con envíos tipo caja necesitas
                    <a href="{{ route('login') }}">iniciar sesión</a>
                    o
                    <a href="{{ route('b2c.register') }}">crear una cuenta</a>.
                </div>
            @endif

            <form class="quote-form" method="POST" action="{{ route('b2c.cotizar') }}">
                @csrf

                <div class="field autocomplete-wrap">
                    <label>Origen</label>

                    <input type="text"
                        id="cp_origen"
                        name="cp_origen"
                        value="{{ old('cp_origen', isset($cotizacion_publica) ? trim(($cotizacion_publica->cp_origen ?? '') . ($cotizacion_publica->colonia_origen ? ' - ' . $cotizacion_publica->colonia_origen : '')) : '') }}"
                        placeholder="Código postal origen"
                        maxlength="120"
                        autocomplete="off">

                    <input type="hidden" id="colonia_origen" name="colonia_origen" value="{{ old('colonia_origen', $cotizacion_publica->colonia_origen ?? '') }}">
                    <input type="hidden" id="ciudad_origen" name="ciudad_origen" value="{{ old('ciudad_origen', $cotizacion_publica->ciudad_origen ?? '') }}">
                    <input type="hidden" id="estado_origen" name="estado_origen" value="{{ old('estado_origen', $cotizacion_publica->estado_origen ?? '') }}">

                    <div id="colonias_origen_list" class="suggestions"></div>

                    <small id="cp_origen_msg" class="cp-help"></small>
                </div>

                <div class="field autocomplete-wrap">
                    <label>Destino</label>

                    <input type="text"
                        id="cp_destino"
                        name="cp_destino"
                        value="{{ old('cp_destino', isset($cotizacion_publica) ? trim(($cotizacion_publica->cp_destino ?? '') . ($cotizacion_publica->colonia_destino ? ' - ' . $cotizacion_publica->colonia_destino : '')) : '') }}"
                        placeholder="Código postal destino"
                        maxlength="120"
                        autocomplete="off">

                    <input type="hidden" id="colonia_destino" name="colonia_destino" value="{{ old('colonia_destino', $cotizacion_publica->colonia_destino ?? '') }}">
                    <input type="hidden" id="ciudad_destino" name="ciudad_destino" value="{{ old('ciudad_destino', $cotizacion_publica->ciudad_destino ?? '') }}">
                    <input type="hidden" id="estado_destino" name="estado_destino" value="{{ old('estado_destino', $cotizacion_publica->estado_destino ?? '') }}">

                    <div id="colonias_destino_list" class="suggestions"></div>

                    <small id="cp_destino_msg" class="cp-help"></small>
                </div>

                <div class="field">
                    <label>Tipo de envío</label>
                    <select name="tipo_envio" id="tipo_envio">
                        <option value="caja">Caja</option>
                        <option value="sobre">Sobre</option>
                    </select>
                </div>

                <div class="field">
                    <label>Peso (kg)</label>
                    <input type="number" id="peso" name="peso" placeholder="Kg" min="0.1" step="0.1" value="{{ old('peso', isset($cotizacion_publica) ? $cotizacion_publica->peso : '') }}">
                </div>

                <div class="field field-dimensions">
                    <label>Tamaño de caja en (cm)</label>

                    <div class="box-dimensions">
                        <input type="number" id="largo" placeholder="Largo" min="1">
                        <input type="number" id="alto" placeholder="Alto" min="1">
                        <input type="number" id="ancho" placeholder="Ancho" min="1">
                    </div>

                    <input type="hidden" id="medidas" name="medidas" value="{{ old('medidas') }}">
                </div>

                <input type="hidden" id="peso_cotizar" name="peso_cotizar" value="{{ old('peso_cotizar') }}">

                <div id="peso_volumetrico_box" class="peso-volumetrico-box" style="display:none;">
                    <div><strong>Peso real:</strong> <span id="peso_real_text">0.00</span> kg</div>
                    <div><strong>Peso volumétrico:</strong> <span id="peso_vol_text">0.00</span> kg</div>
                    <div><strong>Peso a cotizar:</strong> <span id="peso_cotizar_text">0.00</span> kg</div>
                </div>

                <button class="btn-yellow" type="submit">Cotizar envío</button>
                
            </form>

            @if(isset($cotizacion_publica) || session('login_required'))
                <div class="quote-reset-wrap">
                    <a href="{{ route('landing.cotizacion.limpiar') }}" class="quote-reset-link">Limpiar cotización</a>
                </div>
            @endif

            @if(isset($cotizacion_id) && isset($opciones))
                <div style="margin:25px auto 0;">
                    <div style="background:white;color:#111827;border-radius:20px;padding:24px;box-shadow:0 12px 30px rgba(0,0,0,.12);">
                        <h2 style="margin-top:0;color:#111827;text-align:center;">Opciones disponibles</h2>

                        @foreach($opciones as $opcion)
                            @php
                                $deliveryDate = \Carbon\Carbon::parse($opcion['estimated_delivery_date'])->format('d/m/Y');
                                $operatingDays = implode(', ', $opcion['operating_days']);
                                $isReexpedition = (bool) $opcion['is_reexpedition'];
                            @endphp
                            <form method="POST" action="{{ route('b2c.seleccionar', $cotizacion_id) }}"
                                class="landing-quote-option">
                                @csrf

                                <input type="hidden" name="logistico" value="{{ $opcion['logistico'] }}">
                                <input type="hidden" name="servicio" value="{{ $opcion['servicio'] }}">

                                <div class="landing-quote-service">
                                    <img src="{{ asset($opcion['logo']) }}" alt="{{ $opcion['logistico'] }}" style="width:90px;height:auto;object-fit:contain;">
                                    <div>
                                        <strong>{{ $opcion['logistico'] }}</strong>
                                        <div>{{ $opcion['servicio'] }}</div>
                                    </div>
                                </div>

                                <div class="landing-quote-metadata">
                                    <div><strong>Entrega estimada:</strong> {{ $deliveryDate }}</div>
                                    <div><strong>Frecuencia:</strong> {{ $opcion['periodicity_name'] }}</div>
                                    <div><strong>Opera:</strong> {{ $operatingDays }}</div>
                                    <div><strong>Zona:</strong> {{ $opcion['zone_code'] }}</div>
                                    <div>
                                        <strong>{{ $isReexpedition ? 'Área extendida' : 'Área regular' }}</strong>
                                        @if($isReexpedition)
                                            <br>Cargo incluido en el precio
                                        @endif
                                    </div>
                                    @if($opcion['restriction'])
                                        <div class="landing-quote-restriction">
                                            <strong>Restricción:</strong> {{ $opcion['restriction_description'] }}
                                        </div>
                                    @endif
                                </div>

                                <div class="landing-quote-price">
                                    ${{ number_format($opcion['commercial_price'], 2) }} MXN
                                </div>

                                <button
                                    type="button"
                                    class="landing-quote-select"
                                    data-service="{{ $opcion['logistico'] }} {{ $opcion['servicio'] }}"
                                    data-origin="{{ $cotizacion_publica->cp_origen }}"
                                    data-destination="{{ $cotizacion_publica->cp_destino }}"
                                    data-weight="{{ $opcion['weight_billable'] }} kg"
                                    data-dimensions="{{ $opcion['dimensions'] }}"
                                    data-delivery="{{ $deliveryDate }}"
                                    data-frequency="{{ $opcion['periodicity_name'] }}"
                                    data-zone="{{ $opcion['zone_code'] }}"
                                    data-area="{{ $isReexpedition ? 'Área extendida / reexpedición' : 'Área regular' }}"
                                    data-insurance="{{ $opcion['insurance_enabled'] ? 'Incluido' : 'No incluido' }}"
                                    data-price="${{ number_format($opcion['commercial_price'], 2) }} MXN"
                                >
                                    Seleccionar
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>

                <div class="landing-quote-modal" id="landing-quote-modal" role="dialog" aria-modal="true" aria-labelledby="landing-quote-modal-title">
                    <div class="landing-quote-modal-card">
                        <h2 id="landing-quote-modal-title">Resumen de tu cotización</h2>
                        @if(!auth()->check() && strtolower((string) $cotizacion_publica->tipo_envio) === 'caja')
                            <p class="landing-quote-auth-message">Para continuar con un envío tipo caja necesitas iniciar sesión o crear una cuenta.</p>
                        @endif
                        <div class="landing-quote-modal-grid" id="landing-quote-modal-details"></div>
                        <div class="landing-quote-modal-price" id="landing-quote-modal-price"></div>
                        <div class="landing-quote-modal-actions">
                            <button type="button" id="landing-quote-cancel">Cancelar</button>
                            @if(!auth()->check() && strtolower((string) $cotizacion_publica->tipo_envio) === 'caja')
                                <button type="button" id="landing-quote-register">Crear cuenta</button>
                                <button type="button" id="landing-quote-login">Iniciar sesión</button>
                            @else
                                <button type="button" id="landing-quote-continue">Continuar</button>
                            @endif
                        </div>
                    </div>
                </div>

                <style>
                    .landing-quote-option{display:grid;grid-template-columns:minmax(180px,.8fr) minmax(260px,1.5fr) minmax(150px,.6fr) auto;gap:18px;align-items:center;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-top:12px}
                    .landing-quote-service{display:flex;align-items:center;gap:15px}.landing-quote-metadata{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 14px;font-size:14px;line-height:1.4}.landing-quote-restriction{color:#9a3412}.landing-quote-price{font-size:22px;font-weight:900}.landing-quote-select,.landing-quote-modal-actions button{background:#f97316;color:#fff;border:0;border-radius:10px;padding:12px 22px;font-weight:900;cursor:pointer}.landing-quote-modal{display:none;position:fixed;inset:0;z-index:10000;padding:20px;background:rgba(15,23,42,.65);align-items:center;justify-content:center}.landing-quote-modal.is-open{display:flex}.landing-quote-modal-card{width:min(620px,100%);max-height:calc(100vh - 40px);overflow:auto;background:#fff;color:#111827;border-radius:20px;padding:26px;box-shadow:0 24px 60px rgba(0,0,0,.3)}.landing-quote-auth-message{padding:12px;border-radius:12px;background:#fff7ed;color:#9a3412;font-weight:800}.landing-quote-modal-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:18px 0}.landing-quote-modal-grid div{padding:12px;border-radius:12px;background:#f8fafc;color:#475569}.landing-quote-modal-grid strong{display:block;color:#111827;margin-top:4px}.landing-quote-modal-price{padding:16px;border-radius:14px;background:#fff7ed;color:#9a3412;text-align:center;font-size:24px;font-weight:900}.landing-quote-modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}.landing-quote-modal-actions #landing-quote-cancel{background:#e2e8f0;color:#334155}
                    @media(max-width:850px){.landing-quote-option{grid-template-columns:1fr}.landing-quote-metadata{grid-template-columns:1fr}.landing-quote-price{text-align:center}.landing-quote-select{width:100%}}
                    @media(max-width:520px){.landing-quote-service{flex-direction:column;text-align:center}.landing-quote-modal-grid{grid-template-columns:1fr}.landing-quote-modal-actions{flex-direction:column-reverse}.landing-quote-modal-actions button{width:100%}}
                </style>

                <script>
                    (() => {
                        const modal = document.getElementById('landing-quote-modal');
                        const details = document.getElementById('landing-quote-modal-details');
                        const price = document.getElementById('landing-quote-modal-price');
                        const labels = {service:'Servicio',origin:'CP origen',destination:'CP destino',weight:'Peso facturable',dimensions:'Dimensiones',delivery:'Entrega estimada',frequency:'Frecuencia',zone:'Zona',area:'Área extendida / reexpedición',insurance:'Seguro'};
                        let selectedForm = null;
                        document.querySelectorAll('.landing-quote-select').forEach((button) => button.addEventListener('click', () => {
                            selectedForm = button.closest('form');
                            details.replaceChildren();
                            Object.entries(labels).forEach(([key,label]) => {
                                const item = document.createElement('div');
                                item.append(document.createTextNode(label));
                                const value = document.createElement('strong');
                                value.textContent = button.dataset[key];
                                item.append(value); details.append(item);
                            });
                            price.textContent = button.dataset.price;
                            modal.classList.add('is-open');
                        }));
                        const close = () => modal.classList.remove('is-open');
                        document.getElementById('landing-quote-cancel').addEventListener('click', close);
                        modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
                        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
                        const submitWithAction = (action) => {
                            if (!selectedForm) return;
                            let input = selectedForm.querySelector('input[name="auth_action"]');
                            if (!input) {
                                input = document.createElement('input');
                                input.type = 'hidden'; input.name = 'auth_action';
                                selectedForm.append(input);
                            }
                            input.value = action;
                            selectedForm.requestSubmit();
                        };
                        document.getElementById('landing-quote-continue')?.addEventListener('click', () => submitWithAction(''));
                        document.getElementById('landing-quote-login')?.addEventListener('click', () => submitWithAction('login'));
                        document.getElementById('landing-quote-register')?.addEventListener('click', () => submitWithAction('register'));
                    })();
                </script>
            @endif
        </div>
    </section>

    {{--
    <section class="tracking-section">
        <h2>Rastrea tu envío</h2>
        <form class="tracking-form" method="POST" action="{{ route('b2c.rastreo.buscar') }}">
            @csrf
            <input type="text" name="tracking_number" placeholder="Ingresa tu número de rastreo" required>
            <button type="submit">Rastrear</button>
        </form>
    </section>
    --}}

    <section class="hero">
        <div class="hero-copy">
            <div class="hero-kicker"><span>✓</span> Envíos nacionales en sencillos pasos</div>
            <h1>Envía paquetes de forma <strong>segura</strong> y rápida</h1>
            <p>
                Cotiza, paga y genera guías digitales desde una plataforma simple para personas,
                emprendedores y empresas que necesitan operar sus envíos de forma confiable.
            </p>
            <div class="hero-actions">
                <a href="#cotizar" class="hero-primary">Cotizar ahora</a>
                <a href="{{ route('landing.empresas') }}" class="hero-secondary">Soluciones Empresas</a>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-card hero-image-card">
                <img src="{{ asset('img/hero-logistica.jpg') }}" alt="Soluciones logísticas ZIGO" class="hero-image">
            </div>
        </div>
    </section>

    <section class="business-types">
        <div class="section-title">
            <h2 class="business-main-title">Soluciones Empresas</h2>

            <h3 class="business-subtitle">¿Qué tipo de negocio eres?</h3>

            <p>
                Soluciones logísticas para empresas y emprendedores que necesitan control,
                automatización y crecimiento.
            </p>
        </div>

        <div class="business-grid">
            <div class="business-card">
                <div class="business-icon">🚀</div>
                <h3>Ecommerce</h3>
                <p>
                    Vende en tu tienda online y entrega a toda la república.
                </p>

                <a href="{{ route('landing.empresas') }}" class="business-link">
                    Conocer solución →
                </a>
            </div>

            <div class="business-card">
                <div class="business-icon">🏬</div>
                <h3>Retail</h3>
                <p>
                    Distribuye a sucursales, tiendas y clientes finales.
                </p>

                <a href="{{ route('landing.empresas') }}" class="business-link">
                    Conocer solución →
                </a>
            </div>

            <div class="business-card">
                <div class="business-icon">📦</div>
                <h3>Fulfillment</h3>
                <p>
                    Nosotros almacenamos, preparamos y enviamos tus pedidos.
                </p>

                <a href="{{ route('landing.empresas') }}" class="business-link">
                    Conocer solución →
                </a>
            </div>
        </div>
    </section>

    <section class="solutions-section">
    <div class="section-container">
        <h2>Todo para tus envíos en un solo lugar</h2>

        <div class="solutions-grid">
            <div class="solution-card">
                <div class="solution-icon">📦</div>
                <h3>Portal de envios</h3>
                <p>Cotiza, paga en línea y genera guías para envíos sencillos sin crear una cuenta.</p>
                <span>Ideal para envíos ocasionales</span>
            </div>

            <div class="solution-card">
                <div class="solution-icon">👤</div>
                <h3>Perfil personal</h3>
                <p>Guarda direcciones, consulta historial, descarga tus guías y agiliza futuras cotizaciones.</p>
                <a href="{{ route('b2c.register') }}">Crear cuenta →</a>
            </div>

            <div class="solution-card">
                <div class="solution-icon">🏢</div>
                <h3>Empresas </h3>
                <p>Centraliza usuarios, saldos, direcciones, reportes, tarifas preferenciales y guías para tu operación.</p>
                <a href="{{ route('landing.empresas') }}">Conocer solución empresarial →</a>
            </div>

            <div class="solution-card">
                <div class="solution-icon">🔌</div>
                <h3>API Hub</h3>
                <p>Integra servicios como códigos postales, colonias, cotización, rastreo y generación de guías desde tu tienda en línea.</p>
                <a href="{{ route('public.api-hub') }}">Ver API Hub →</a>
            </div>

            <div class="solution-card">
                <div class="solution-icon">🛟</div>
                <h3>Soporte</h3>
                <p>Recibe ayuda para tus envíos, pagos, guías, incidencias y seguimiento de paquetes.</p>
                <a href="{{ route('public.soporte') }}">Ir a soporte →</a>
            </div>

            <div class="solution-card">
                <div class="solution-icon">🚚</div>
                <h3>Paquetería</h3>
                <p>Gestiona envíos nacionales con aliados logísticos y opciones pensadas para personas y negocios.</p>
                <a href="{{ route('public.paqueteria') }}">Ver paquetería →</a>
            </div>
        </div>
    </div>
</section>

    <section class="section" id="paqueterias">
        <h2>Paqueterías integradas</h2>

        <div class="logos">
            <img src="{{ asset('img/fedex.png') }}" alt="FedEx">
            <img src="{{ asset('img/estafeta.png') }}" alt="Estafeta">
            <img src="{{ asset('img/dhl.png') }}" alt="DHL">
            <img src="{{ asset('img/ups.png') }}" alt="UPS">
        </div>
    </section>

    <section class="faq-section" id="faq">
        <h2>Preguntas frecuentes</h2>
        <div class="faq-grid">
            <div class="faq-item">
                <h3>¿Puedo cotizar sin registrarme?</h3>
                <p>Sí. Puedes cotizar como visitante. Para funciones avanzadas o envíos tipo caja, será necesario crear una cuenta o iniciar sesión.</p>
            </div>

            <div class="faq-item">
                <h3>¿Qué tipo de envío puedo hacer?</h3>
                <p>Actualmente puedes operar envíos sencillos y consultar opciones disponibles según cobertura, tipo de paquete y servicio.</p>
            </div>

            <div class="faq-item">
                <h3>¿ZIGO tiene solución para empresas?</h3>
                <p>Sí. ZIGO Empresas permite centralizar usuarios, direcciones, saldos, reportes y generación de guías.</p>
            </div>

            <div class="faq-item">
                <h3>¿Puedo integrar ZIGO a mi sistema?</h3>
                <p>Sí. API Hub permite integrar servicios como códigos postales, colonias, consumo y futuras funciones logísticas.</p>
            </div>
        </div>
    </section>

    <section class="cta">
        <h2>Comienza a vender y generar guías hoy</h2>
        <p>Compra una guía como usuario final o solicita acceso empresarial.</p>
        <a href="{{ url('/register') }}">Crear cuenta</a>
    </section>

    <footer class="zigo-footer">
        <div class="footer-inner">
            <div>
                <h4>ZIGO</h4>
                <p>
                    Plataforma logística digital para personas, negocios e integradores.
                </p>
            </div>

            <div>
                <h4>Soluciones</h4>
                <!--a href="{{ url('/') }}">B2C</a-->
                <a href="{{ route('landing.empresas') }}">Empresas</a>
                <a href="{{ route('public.api-hub') }}">API Hub</a>
            </div>

            <div>
                <h4>Ayuda</h4>
                <a href="{{ route('public.faqs') }}">FAQ'S</a>
                <a href="{{ route('public.soporte') }}">Soporte</a>
                <!--a href="{{ url('/rastreo') }}">Rastrear envío</a-->
            </div>

            <div>
                <h4>Legal</h4>
                <a href="{{ route('legal.aviso-privacidad') }}">Aviso de privacidad</a>
                <a href="{{ route('legal.terminos') }}">Términos y condiciones</a>
                <a href="{{ route('legal.politica-envios') }}">Política de envíos</a>
            </div>

            @include('public.partials.social-links')
        </div>
    </footer>

    @php($publicWhatsapp = app(\App\Services\Marketing\PublicChannelService::class)->active()->firstWhere('channel', 'whatsapp'))
    @if($publicWhatsapp)
    <a class="whatsapp" href="{{ $publicWhatsapp['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp ZIGO">
        <svg viewBox="0 0 32 32" aria-hidden="true">
            <path d="M16.02 3.2c-7.07 0-12.82 5.64-12.82 12.58 0 2.37.68 4.68 1.96 6.67L3.2 28.8l6.56-1.88a13.03 13.03 0 0 0 6.26 1.6c7.07 0 12.82-5.64 12.82-12.58S23.09 3.2 16.02 3.2Zm0 22.95c-1.96 0-3.88-.54-5.55-1.56l-.4-.24-3.9 1.12 1.14-3.75-.26-.39a10.2 10.2 0 0 1-1.6-5.55c0-5.63 4.74-10.22 10.57-10.22s10.57 4.59 10.57 10.22-4.74 10.37-10.57 10.37Zm5.8-7.67c-.32-.16-1.88-.91-2.18-1.02-.29-.1-.5-.16-.72.16-.21.31-.82 1.02-1 1.23-.19.21-.37.24-.69.08-.32-.16-1.34-.49-2.55-1.56-.94-.82-1.58-1.84-1.77-2.15-.18-.31-.02-.48.14-.64.15-.14.32-.37.48-.55.16-.18.21-.31.32-.52.1-.21.05-.39-.03-.55-.08-.16-.72-1.7-.98-2.33-.26-.61-.52-.53-.72-.54h-.61c-.21 0-.55.08-.84.39-.29.31-1.1 1.05-1.1 2.56s1.13 2.98 1.29 3.18c.16.21 2.22 3.33 5.38 4.67.75.32 1.34.51 1.8.65.76.24 1.45.2 2 .12.61-.09 1.88-.75 2.14-1.48.26-.73.26-1.36.18-1.48-.08-.13-.29-.21-.61-.37Z"/>
        </svg>
    </a>
    @endif
</div>

    <script>
        async function cargarColonias(cpInputId, listId, msgId, hiddenColoniaId) {
            const cpInput = document.getElementById(cpInputId);
            const list = document.getElementById(listId);
            const msg = document.getElementById(msgId);
            const hiddenColonia = document.getElementById(hiddenColoniaId);

            const cp = cpInput.value.replace(/\D/g, '').substring(0, 5);

            list.innerHTML = '';
            list.style.display = 'none';
            msg.style.display = 'none';
            hiddenColonia.value = '';

            if (cp.length !== 5) {
                return;
            }

            try {
                const response = await fetch(`/b2c/cp/colonias?cp=${encodeURIComponent(cp)}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const json = await response.json();
                const colonias = json?.data || [];

                if (!response.ok || !Array.isArray(colonias) || colonias.length === 0) {
                    msg.textContent = 'Código postal no encontrado';
                    msg.classList.add('is-error');;
                    msg.style.color = '#fee2e2';
                    msg.style.display = 'block';
                    return;
                }

                msg.textContent = '';
                msg.style.display = 'none';
                msg.classList.remove('is-error');

                colonias.forEach(item => {
                    const cpValue = item.d_codigo || item.codigo_postal || cp;
                    const colonia = item.d_asenta || item.colonia || '';
                    const municipio = item.D_mnpio || item.d_mnpio || item.municipio || item.d_ciudad || '';
                    const estado = item.d_estado || item.estado || '';

                    const texto = `${cpValue} - ${colonia} - ${municipio} - ${estado}`;

                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    div.innerHTML = `
                        <strong>${texto}</strong>
                        <br>
                        <small>${municipio}, ${estado}</small>
                    `;

                    div.addEventListener('click', function () {
                        cpInput.value = texto;
                        hiddenColonia.value = colonia;

                        msg.textContent = '';
                        msg.style.display = 'none';
                        msg.classList.remove('is-error');

                        if (cpInputId === 'cp_origen') {
                            document.getElementById('ciudad_origen').value = municipio;
                            document.getElementById('estado_origen').value = estado;
                        }

                        if (cpInputId === 'cp_destino') {
                            document.getElementById('ciudad_destino').value = municipio;
                            document.getElementById('estado_destino').value = estado;
                        }

                        list.style.display = 'none';
                    });

                    list.appendChild(div);
                });

                list.style.display = 'block';
            } catch (error) {
                msg.textContent = 'No fue posible consultar el código postal';
                msg.style.color = '#fee2e2';
                msg.style.display = 'block';
            }
        }

        document.getElementById('cp_origen').addEventListener('input', function () {
            if (this.value.replace(/\D/g, '').length === 5) {
                cargarColonias('cp_origen', 'colonias_origen_list', 'cp_origen_msg', 'colonia_origen');
            }
        });

        document.getElementById('cp_destino').addEventListener('input', function () {
            if (this.value.replace(/\D/g, '').length === 5) {
                cargarColonias('cp_destino', 'colonias_destino_list', 'cp_destino_msg', 'colonia_destino');
            }
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.autocomplete-wrap')) {
                document.querySelectorAll('.suggestions').forEach(item => {
                    item.style.display = 'none';
                });
            }
        });

        const quoteForm = document.querySelector('.quote-form');

        const tipoEnvioInput = document.querySelector('select[name="tipo_envio"]');
        const pesoInput = document.getElementById('peso');
        const largoInput = document.getElementById('largo');
        const altoInput = document.getElementById('alto');
        const anchoInput = document.getElementById('ancho');
        const medidasInput = document.getElementById('medidas');
        const pesoCotizarInput = document.getElementById('peso_cotizar');

        const pesoBox = document.getElementById('peso_volumetrico_box');
        const pesoRealText = document.getElementById('peso_real_text');
        const pesoVolText = document.getElementById('peso_vol_text');
        const pesoCotizarText = document.getElementById('peso_cotizar_text');

        function toNumber(value) {
            return parseFloat(String(value || '').replace(',', '.')) || 0;
        }

        function limpiarCalculoDimensiones() {
            if (medidasInput) medidasInput.value = '';
            if (pesoCotizarInput) pesoCotizarInput.value = '';
            if (pesoBox) pesoBox.style.display = 'none';
        }

        function configurarTipoEnvioLanding() {
            const tipo = tipoEnvioInput ? tipoEnvioInput.value : 'caja';
            const esSobre = tipo === 'sobre';

            [largoInput, altoInput, anchoInput].forEach(function (input) {
                if (!input) return;

                input.disabled = esSobre;
                input.required = !esSobre;
                input.style.opacity = esSobre ? '0.55' : '1';
                input.style.cursor = esSobre ? 'not-allowed' : 'text';

                if (esSobre) {
                    input.value = '';
                }
            });

            const dimensionsField = document.querySelector('.field-dimensions');

            if (dimensionsField) {
                if (esSobre) {
                    dimensionsField.classList.add('is-disabled');
                } else {
                    dimensionsField.classList.remove('is-disabled');
                }
            }

            if (esSobre) {
                if (pesoInput) {
                    pesoInput.value = '1.00';
                    pesoInput.readOnly = true;
                    pesoInput.style.opacity = '0.75';
                    pesoInput.style.cursor = 'not-allowed';
                }

                limpiarCalculoDimensiones();

                if (pesoCotizarInput) {
                    pesoCotizarInput.value = '1.00';
                }

                return;
            }

            if (pesoInput) {
                pesoInput.readOnly = false;
                pesoInput.style.opacity = '1';
                pesoInput.style.cursor = 'text';
            }

            calcularPesoVolumetricoLanding();
        }

        function calcularPesoVolumetricoLanding() {
            const tipo = tipoEnvioInput ? tipoEnvioInput.value : 'caja';

            if (tipo === 'sobre') {
                configurarTipoEnvioLanding();
                return;
            }

            const pesoReal = toNumber(pesoInput ? pesoInput.value : 0);
            const largo = toNumber(largoInput ? largoInput.value : 0);
            const alto = toNumber(altoInput ? altoInput.value : 0);
            const ancho = toNumber(anchoInput ? anchoInput.value : 0);

            if (medidasInput) {
                medidasInput.value = largo && alto && ancho ? `${largo}x${alto}x${ancho}` : '';
            }

            if (!pesoReal || !largo || !alto || !ancho) {
                limpiarCalculoDimensiones();
                return;
            }

            const pesoVolumetrico = (largo * alto * ancho) / 5000;
            const pesoCotizar = Math.ceil(Math.max(pesoReal, pesoVolumetrico));

            if (pesoRealText) pesoRealText.textContent = pesoReal.toFixed(2);
            if (pesoVolText) pesoVolText.textContent = pesoVolumetrico.toFixed(2);
            if (pesoCotizarText) pesoCotizarText.textContent = pesoCotizar.toFixed(2);

            if (pesoCotizarInput) {
                pesoCotizarInput.value = pesoCotizar.toFixed(2);
            }

            if (pesoBox) {
                pesoBox.style.display = 'grid';
            }
        }

        [tipoEnvioInput, pesoInput, largoInput, altoInput, anchoInput].forEach(function (input) {
            if (!input) return;

            input.addEventListener('input', function () {
                if (input === tipoEnvioInput) {
                    configurarTipoEnvioLanding();
                    calcularPesoVolumetricoLanding();
                } else {
                    calcularPesoVolumetricoLanding();
                }
            });

            input.addEventListener('change', function () {
                if (input === tipoEnvioInput) {
                    configurarTipoEnvioLanding();
                    calcularPesoVolumetricoLanding();
                } else {
                    calcularPesoVolumetricoLanding();
                }
            });
        });

        if (quoteForm) {
            quoteForm.addEventListener('submit', function () {
                const tipo = tipoEnvioInput ? tipoEnvioInput.value : 'caja';

                if (tipo === 'sobre') {
                    configurarTipoEnvioLanding();

                    if (pesoInput) {
                        pesoInput.value = '1.00';
                    }

                    if (pesoCotizarInput) {
                        pesoCotizarInput.value = '1.00';
                    }
                } else {
                    calcularPesoVolumetricoLanding();
                }

                const btn = quoteForm.querySelector('button[type="submit"]');

                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Cotizando...';
                    btn.style.opacity = '.75';
                    btn.style.cursor = 'not-allowed';
                }
            });
        }

        const mobileNavToggle = document.querySelector('.mobile-nav-toggle');
        const mainNavigation = document.getElementById('main-navigation');
        mobileNavToggle?.addEventListener('click', function () {
            const open = mainNavigation.classList.toggle('is-open');
            mobileNavToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            mobileNavToggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
        });

        configurarTipoEnvioLanding();

    </script>

</body>
</html>
