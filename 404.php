<?php
// 404.php
http_response_code(404);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found | NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <style>
        body {
            background-color: #0a0a0a;
        }

        @keyframes floatY {
            0%, 100% { transform: translateY(0px); }
            50%       { transform: translateY(-14px); }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse404 {
            0%, 100% { text-shadow: 0 0 40px rgba(245,158,11,0.3); }
            50%       { text-shadow: 0 0 80px rgba(245,158,11,0.7); }
        }

        .float-anim   { animation: floatY 3.5s ease-in-out infinite; }
        .fade-up-1    { animation: fadeUp 0.6s ease forwards; opacity: 0; }
        .fade-up-2    { animation: fadeUp 0.6s ease 0.15s forwards; opacity: 0; }
        .fade-up-3    { animation: fadeUp 0.6s ease 0.3s forwards; opacity: 0; }
        .fade-up-4    { animation: fadeUp 0.6s ease 0.45s forwards; opacity: 0; }
        .fade-up-5    { animation: fadeUp 0.6s ease 0.6s forwards; opacity: 0; }

        .text-404 {
            font-size: clamp(7rem, 22vw, 11rem);
            font-weight: 900;
            line-height: 1;
            letter-spacing: -4px;
            animation: pulse404 3s ease-in-out infinite;
        }

        .btn-home {
            border: 2px solid #f59e0b;
            transition: background 0.25s, color 0.25s, transform 0.15s;
        }
        .btn-home:hover {
            background: #f59e0b;
            color: #0a0a0a;
            transform: scale(1.04);
        }
        .btn-home:active { transform: scale(0.98); }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center px-6 py-16 text-center">

    <!-- Logo -->
    <div class="fade-up-1 flex flex-col items-center gap-2 mb-10">
        <div class="w-16 h-16 bg-white rounded-xl flex items-center justify-center shadow-lg">
            <img src="<?= defined('BASE_URL') ? BASE_URL : '' ?>/icon/logo.png"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                 class="w-12 h-12 object-contain" alt="NobleHome Logo">
            <!-- Fallback SVG house icon if logo missing -->
            <div style="display:none;" class="w-full h-full items-center justify-center">
                <i class="fa-solid fa-house text-amber-500 text-2xl"></i>
            </div>
        </div>
        <span class="text-white text-sm font-bold tracking-[0.3em] uppercase">NobleHome</span>
    </div>

    <!-- 404 Number -->
    <div class="fade-up-2 relative select-none mb-2">
        <div class="text-404 flex items-center justify-center gap-2 leading-none">
            <span class="text-amber-500">4</span>
            <span class="text-gray-700">0</span>
            <span class="text-amber-500">4</span>
        </div>
    </div>

    <!-- Page Not Found text -->
    <div class="fade-up-3 mb-4">
        <h1 class="text-2xl md:text-3xl font-black uppercase tracking-widest text-white">
            Page <span class="text-amber-500">Not</span> Found
        </h1>
    </div>

    <!-- Description -->
    <div class="fade-up-3 mb-10">
        <p class="text-gray-400 text-sm md:text-base max-w-sm leading-relaxed">
            Sorry, the page you're looking for doesn't exist or has been moved.
        </p>
    </div>

    <!-- House Illustration -->
    <div class="fade-up-4 float-anim mb-12">
        <svg width="200" height="140" viewBox="0 0 200 140" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Ground shadow -->
            <ellipse cx="88" cy="132" rx="60" ry="6" fill="#1a1a1a"/>

            <!-- Bush left -->
            <circle cx="36" cy="118" r="12" fill="#2a2a2a"/>
            <circle cx="48" cy="114" r="10" fill="#2a2a2a"/>
            <circle cx="28" cy="115" r="9" fill="#2a2a2a"/>

            <!-- House body -->
            <rect x="45" y="80" width="85" height="52" rx="3" fill="#2d2d2d"/>

            <!-- Roof -->
            <polygon points="38,82 87.5,42 137,82" fill="#f59e0b"/>

            <!-- Door -->
            <rect x="76" y="100" width="23" height="32" rx="2" fill="#f59e0b"/>

            <!-- Window -->
            <rect x="105" y="90" width="18" height="16" rx="2" fill="#1a1a1a" stroke="#3a3a3a" stroke-width="1.5"/>
            <line x1="114" y1="90" x2="114" y2="106" stroke="#3a3a3a" stroke-width="1"/>
            <line x1="105" y1="98" x2="123" y2="98" stroke="#3a3a3a" stroke-width="1"/>

            <!-- Sign post -->
            <rect x="144" y="88" width="4" height="44" rx="2" fill="#4a4a4a"/>

            <!-- Sign board -->
            <rect x="130" y="70" width="52" height="38" rx="5" fill="#f59e0b" transform="rotate(-8 130 70)"/>
            <text x="156" y="89" text-anchor="middle" font-family="'Plus Jakarta Sans', sans-serif" font-weight="900" font-size="11" fill="#0a0a0a" transform="rotate(-8 156 89)">NOT</text>
            <text x="156" y="103" text-anchor="middle" font-family="'Plus Jakarta Sans', sans-serif" font-weight="900" font-size="11" fill="#0a0a0a" transform="rotate(-8 156 103)">FOUND</text>

            <!-- Grass blades -->
            <path d="M40 130 Q42 122 44 130" stroke="#3a3a3a" stroke-width="1.5" fill="none"/>
            <path d="M128 130 Q130 122 132 130" stroke="#3a3a3a" stroke-width="1.5" fill="none"/>
            <path d="M135 130 Q137 124 139 130" stroke="#3a3a3a" stroke-width="1.5" fill="none"/>
        </svg>
    </div>

    <!-- Go Back Button -->
    <div class="fade-up-5">
        <a href="<?= defined('BASE_URL') ? BASE_URL . '/loginuser' : '/' ?>"
           class="btn-home inline-flex items-center gap-3 px-8 py-3 rounded-full text-white text-sm font-bold tracking-widest uppercase">
            <i class="fa-solid fa-house text-amber-500"></i>
            Go Back Home
        </a>
    </div>

</body>
</html>