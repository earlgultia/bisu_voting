<?php include 'config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BISU Online Voting System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/theme.css">
    <style>
        .hero {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 2rem;
            align-items: center;
            animation: rise 0.7s ease both;
        }
        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.3em;
            font-size: 0.72rem;
            color: var(--accent-2);
            margin-bottom: 0.95rem;
            font-weight: 700;
        }
        .hero-copy h1 {
            font-size: clamp(2.35rem, 4vw, 3.65rem);
            margin-bottom: 1rem;
            max-width: 11ch;
        }
        .hero-lead {
            font-size: 1.08rem;
            color: var(--ink);
            line-height: 1.75;
            max-width: 42ch;
            margin-bottom: 1.6rem;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
            margin-bottom: 1.5rem;
        }
        .hero-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem;
        }
        .meta-item {
            padding: 1rem 1rem 1rem 1rem;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff, #f7fbff);
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
            position: relative;
        }
        .meta-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            color: var(--muted);
            display: block;
            margin-bottom: 0.4rem;
        }
        .meta-value {
            font-weight: 600;
            color: var(--accent-strong);
            display: block;
        }
        .hero-card {
            padding: 1.9rem;
            background:
                radial-gradient(circle at top right, rgba(17, 124, 107, 0.1), transparent 22%),
                linear-gradient(180deg, #ffffff, #f9fbff);
            border-top: 4px solid var(--accent-2);
        }
        .hero-card h3 {
            margin-bottom: 1rem;
        }
        .hero-card .hero-note {
            padding-top: 1rem;
            margin-top: 1rem;
            border-top: 1px solid var(--line);
        }
        .nav-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--accent-strong);
            cursor: pointer;
            transition: 0.2s ease;
        }
        .nav-toggle:hover {
            background: var(--accent-soft);
        }
        .nav-toggle-icon {
            width: 18px;
            height: 2px;
            background: currentColor;
            position: relative;
            border-radius: 999px;
        }
        .nav-toggle-icon::before,
        .nav-toggle-icon::after {
            content: '';
            position: absolute;
            left: 0;
            width: 18px;
            height: 2px;
            background: currentColor;
            border-radius: 999px;
            transition: transform 0.2s ease, top 0.2s ease, bottom 0.2s ease, opacity 0.2s ease;
        }
        .nav-toggle-icon::before {
            top: -6px;
        }
        .nav-toggle-icon::after {
            bottom: -6px;
        }
        .nav-toggle[aria-expanded="true"] .nav-toggle-icon {
            background: transparent;
        }
        .nav-toggle[aria-expanded="true"] .nav-toggle-icon::before {
            top: 0;
            transform: rotate(45deg);
        }
        .nav-toggle[aria-expanded="true"] .nav-toggle-icon::after {
            bottom: 0;
            transform: rotate(-45deg);
        }
        .steps {
            list-style: none;
            display: grid;
            gap: 0.85rem;
            margin-bottom: 1.4rem;
        }
        .steps li {
            background: linear-gradient(135deg, #f8fafc, #eef5fb);
            padding: 0.95rem 1rem;
            border-radius: 14px;
            border: 1px solid var(--line);
            color: var(--accent-strong);
            line-height: 1.5;
        }
        .hero-note {
            color: var(--muted);
            font-size: 0.95rem;
        }
        .features {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 2.4rem;
        }
        .feature-card {
            padding: 1.55rem;
            animation: rise 0.8s ease both;
            position: relative;
            overflow: hidden;
        }
        .feature-card:nth-child(2) {
            animation-delay: 0.08s;
        }
        .feature-card:nth-child(3) {
            animation-delay: 0.16s;
        }
        .feature-number {
            font-weight: 700;
            color: var(--accent-2);
            margin-bottom: 0.65rem;
            letter-spacing: 0.12em;
        }
        .feature-card p {
            color: var(--muted);
            margin-top: 0.55rem;
            line-height: 1.65;
        }
        .cta {
            margin-top: 3rem;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            background:
                linear-gradient(135deg, rgba(23, 59, 114, 0.06), rgba(17, 124, 107, 0.07)),
                #fff;
        }
        .site-footer {
            text-align: center;
            padding: 2rem 0 3rem;
            color: var(--muted);
            font-size: 0.95rem;
        }
        .topbar {
            padding-left: 3vw;
            padding-right: 3vw;
        }
        .container {
            width: min(1120px, 90vw);
        }
        .site-logo {
            width: 56px;
            height: 56px;
            object-fit: cover;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
            border: 2px solid rgba(15, 23, 42, 0.08);
            flex: 0 0 auto;
        }
        @media (max-width: 900px) {
            .hero {
                grid-template-columns: 1fr;
            }
            .features {
                grid-template-columns: 1fr;
            }
            .cta {
                flex-direction: column;
                align-items: flex-start;
            }
            .hero-meta {
                grid-template-columns: 1fr;
            }
            .topbar {
                flex-direction: row;
                align-items: center;
                gap: 0.75rem;
                flex-wrap: wrap;
            }
            .nav-toggle {
                display: inline-flex;
                margin-left: auto;
            }
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                gap: 0.5rem;
                padding-top: 0.2rem;
            }
            .nav-links.open {
                display: flex;
            }
            .nav-links a {
                width: 100%;
            }
            .topbar {
                padding-left: 5vw;
                padding-right: 5vw;
            }
            .container {
                width: min(1120px, 92vw);
            }
        }
        @media (max-width: 640px) {
            .topbar {
                padding-left: 6vw;
                padding-right: 6vw;
            }

            .container {
                width: min(1120px, 90vw);
                padding-top: 1.25rem;
                padding-bottom: 1.5rem;
            }

            .hero-card,
            .feature-card,
            .cta {
                padding-left: 1.1rem;
                padding-right: 1.1rem;
            }

            .hero-actions {
                width: 100%;
            }

            .hero-actions .btn,
            .cta .btn {
                width: 100%;
            }

            .hero-copy h1 {
                max-width: none;
            }
        }
        @media (max-width: 420px) {
            .topbar {
                padding-left: 7vw;
                padding-right: 7vw;
            }

            .container {
                width: min(1120px, 88vw);
            }

            .hero-copy h1 {
                font-size: clamp(2rem, 8vw, 2.6rem);
            }

            .hero-card,
            .feature-card,
            .cta {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">
            <img src="assets/bisu-logo.png" alt="BISU logo" class="site-logo">
            <div>
                <div class="brand-title">BISU Voting System</div>
                <div class="brand-sub">SSG and FTP Elections</div>
            </div>
        </div>
        <button type="button" class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
            <span class="nav-toggle-icon"></span>
        </button>
        <nav class="nav-links">
            <a href="index.php" class="active">Home</a>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        </nav>
    </header>

    <main class="container">
        <section class="hero">
            <div class="hero-copy">
                <p class="eyebrow">BISU Student Portal</p>
                <h1>Secure, fast, and transparent student elections.</h1>
                <p class="hero-lead">Cast your vote with confidence. Review candidates by position and submit a verified ballot in minutes.</p>
                <div class="hero-actions">
                    <a href="login.php" class="btn btn-primary">Login to Vote</a>
                    <a href="register.php" class="btn btn-ghost">Create an account</a>
                </div>
                <div class="hero-meta">
                    <div class="meta-item">
                        <span class="meta-label">Trusted access</span>
                        <span class="meta-value">BISU email verified</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">One vote only</span>
                        <span class="meta-value">Automatic duplicate checks</span>
                    </div>
                </div>
            </div>
            <div class="hero-card card">
                <h3>Voting in 3 steps</h3>
                <ol class="steps">
                    <li>Sign in with your BISU email address.</li>
                    <li>Review candidates by position.</li>
                    <li>Confirm and submit your ballot.</li>
                </ol>
                <div class="hero-note">Need help? Coordinate with the SSG office for assistance.</div>
            </div>
        </section>

        <section class="features">
            <div class="feature-card card">
                <div class="feature-number">01</div>
                <h3>Secure ballots</h3>
                <p>Single vote enforcement and audit-ready logs keep elections fair and clean.</p>
            </div>
            <div class="feature-card card">
                <div class="feature-number">02</div>
                <h3>Live visibility</h3>
                <p>Election managers see real-time counts and leading candidates at a glance.</p>
            </div>
            <div class="feature-card card">
                <div class="feature-number">03</div>
                <h3>Clear decisions</h3>
                <p>Organized by position so voters stay focused and informed throughout the ballot.</p>
            </div>
        </section>

        <section class="cta card">
            <div>
                <h2>Ready to participate?</h2>
                <p class="muted">Log in to cast your vote or register your student account in minutes.</p>
            </div>
            <a href="login.php" class="btn btn-primary">Go to login</a>
        </section>
    </main>

    <footer class="site-footer">
        <p>&copy; <?php echo date('Y'); ?> BISU Online Voting System. All rights reserved.</p>
        <p>Developer: EARL O. GULTIA</p>
    </footer>
    <script>
        const navToggle = document.getElementById('nav-toggle');
        const navLinks = document.querySelector('.nav-links');

        if (navToggle && navLinks) {
            navToggle.addEventListener('click', () => {
                const isOpen = navLinks.classList.toggle('open');
                navToggle.setAttribute('aria-expanded', String(isOpen));
            });
        }
    </script>
</body>
</html>