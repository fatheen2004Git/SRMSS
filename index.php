<?php

/**
 * Smart Route Management and Scheduling System (SRMSS)
 * Main Gateway, Intelligent Router & Enterprise Landing Page
 * 
 * Technology: PHP 8.2, MySQL, PDO, XAMPP, Bootstrap 5.3, JavaScript ES6, SweetAlert2
 */

declare(strict_types=1);

define('ALLOW_NO_DB', true);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';

// 1. Session Detection & Role-Based Automatic Redirection
if (isLoggedIn()) {
    $role = getUserRole();
    if ($role === 'admin') {
        header("Location: admin/dashboard.php");
        exit();
    } elseif ($role === 'supervisor') {
        header("Location: supervisor/dashboard.php");
        exit();
    } elseif ($role === 'driver') {
        header("Location: driver/dashboard.php");
        exit();
    } elseif ($role === 'maintenance') {
        header("Location: maintenance/dashboard.php");
        exit();
    } else {
        // Invalid Role: redirect to Administrator Login page as specified
        header("Location: auth/admin_login.php");
        exit();
    }
}

// 2. Query Real Database System Statistics with offline catch
$routes_count = 0;
$vehicles_count = 0;
$drivers_count = 0;
$active_schedules_count = 0;
$db_connected = false;
$route_spots = [];
$polylines = [];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        // Real database system statistics with offline catch (using Live Data)
        $routes_count = (int)$pdo->query("SELECT COUNT(*) FROM routes WHERE status = 'active'")->fetchColumn();
        $vehicles_count = (int)$pdo->query("SELECT COUNT(*) FROM vehicles WHERE current_status = 'active'")->fetchColumn();
        $drivers_count = (int)$pdo->query("SELECT COUNT(*) FROM drivers WHERE employment_status = 'active'")->fetchColumn();
        $active_schedules_count = (int)$pdo->query("SELECT COUNT(*) FROM schedules")->fetchColumn();
        $db_connected = true;

        // Fetch active depots coordinates
        $stmtDepot = $pdo->query("SELECT depot_name, latitude, longitude FROM depots WHERE status = 'active'");
        while ($row = $stmtDepot->fetch()) {
            if ($row['latitude'] && $row['longitude']) {
                $route_spots[] = [
                    'name' => htmlspecialchars($row['depot_name'] . ' (Depot)'),
                    'coords' => [(float)$row['latitude'], (float)$row['longitude']],
                    'type' => 'depot'
                ];
            }
        }

        // Fetch active routes coordinates
        $stmtRoutes = $pdo->query("SELECT route_code, route_name, start_location, destination, latitude_start, longitude_start, latitude_destination, longitude_destination, polyline_coordinates FROM routes WHERE status = 'active'");
        while ($row = $stmtRoutes->fetch()) {
            if ($row['latitude_start'] && $row['longitude_start']) {
                $route_spots[] = [
                    'name' => htmlspecialchars($row['start_location'] . ' (Start: ' . $row['route_code'] . ')'),
                    'coords' => [(float)$row['latitude_start'], (float)$row['longitude_start']],
                    'type' => 'start'
                ];
            }
            if ($row['latitude_destination'] && $row['longitude_destination']) {
                $route_spots[] = [
                    'name' => htmlspecialchars($row['destination'] . ' (End: ' . $row['route_code'] . ')'),
                    'coords' => [(float)$row['latitude_destination'], (float)$row['longitude_destination']],
                    'type' => 'destination'
                ];
            }
            if (!empty($row['polyline_coordinates'])) {
                $coords = json_decode($row['polyline_coordinates'], true);
                if (is_array($coords)) {
                    $polylines[] = [
                        'code' => $row['route_code'],
                        'name' => $row['route_name'],
                        'coords' => $coords
                    ];
                }
            }
        }

        // Fetch route stops coordinates
        $stmtStops = $pdo->query("SELECT rs.stop_name, rs.latitude, rs.longitude, r.route_code FROM route_stops rs JOIN routes r ON rs.route_id = r.id WHERE r.status = 'active'");
        while ($row = $stmtStops->fetch()) {
            if ($row['latitude'] && $row['longitude']) {
                $route_spots[] = [
                    'name' => htmlspecialchars($row['stop_name'] . ' (Stop: ' . $row['route_code'] . ')'),
                    'coords' => [(float)$row['latitude'], (float)$row['longitude']],
                    'type' => 'stop'
                ];
            }
        }
    }
} catch (PDOException $e) {
    $db_connected = false;
}

// Fallback spots if database is unseeded or offline
if (empty($route_spots)) {
    $route_spots = [
        ['name' => 'Colombo Central Depot',       'coords' => [6.9271, 79.8612], 'type' => 'depot'],
        ['name' => 'Kandy Junction Depot',        'coords' => [7.2906, 80.6337], 'type' => 'depot'],
        ['name' => 'Galle Coastal Hub',           'coords' => [6.0535, 80.2117], 'type' => 'depot'],
        ['name' => 'Jaffna Northern Station',     'coords' => [9.6615, 80.0255], 'type' => 'depot'],
        ['name' => 'Trincomalee Port Depot',      'coords' => [8.5874, 81.2152], 'type' => 'depot'],
        ['name' => 'Anuradhapura Central Transit','coords' => [8.3114, 80.4037], 'type' => 'depot']
    ];
}


// Dynamic system settings loaded from config/db.php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($system_name) ?> | Gateway</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($org_favicon) ?>">

    <!-- DNS Preconnect for CDNs (eliminates connection latency) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://basemaps.cartocdn.com" crossorigin>

    <!-- Preload the LCP logo image (fixes the 15.5s LCP) -->
    <link rel="preload" as="image" href="<?= htmlspecialchars($org_logo) ?>" fetchpriority="high">

    <!-- Google Fonts: async to avoid render-blocking -->
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Inter:wght@400;500;600&display=swap"></noscript>

    <!-- Critical CSS: Bootstrap (loaded normally as it's needed for layout) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <!-- Non-critical CSS: load asynchronously to avoid render-blocking -->
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></noscript>

    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"></noscript>

    <!-- Leaflet CSS: injected dynamically when radar enters viewport -->
    
    <style>
        :root {
            --primary: #0F172A;
            --secondary: #1E40AF;
            --accent: #00F0FF; /* Cyberpunk Cyan Accent */
            --accent-glow: rgba(0, 240, 255, 0.4);
            --bg-dark: #070B14; /* Deep Space Black */
            --card-bg: rgba(17, 24, 39, 0.7); /* Glassmorphism Base */
            --border-color: rgba(255, 255, 255, 0.1);
            --text-color: #F9FAFB;
            --text-muted: #9CA3AF;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-color);
            overflow-x: hidden;
            position: relative;
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(30, 64, 175, 0.05), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(0, 240, 255, 0.03), transparent 25%);
        }

        .poppins {
            font-family: 'Poppins', sans-serif;
        }

        .hover-white {
            transition: color 0.3s ease, text-shadow 0.3s ease;
        }
        .hover-white:hover {
            color: #ffffff !important;
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }

        /* Advanced Abstract Glow Effects */
        .glow-spot-1 {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(0, 240, 255, 0.15) 0%, transparent 60%);
            top: -10%;
            right: 0%;
            z-index: 0;
            pointer-events: none;
            filter: blur(40px);
            animation: floatGlow 8s infinite alternate ease-in-out;
        }
        .glow-spot-2 {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(30, 64, 175, 0.2) 0%, transparent 60%);
            bottom: 20%;
            left: -10%;
            z-index: 0;
            pointer-events: none;
            filter: blur(50px);
            animation: floatGlow 10s infinite alternate-reverse ease-in-out;
        }

        @keyframes floatGlow {
            0% { transform: translateY(0) scale(1); opacity: 0.8; }
            100% { transform: translateY(-50px) scale(1.1); opacity: 1; }
        }

        /* Hero styling */
        .hero-section {
            padding: 5rem 0 3.5rem 0;
            position: relative;
            z-index: 10;
        }

        .network-illustration {
            border: 1px solid var(--border-color);
            background-color: rgba(17, 24, 39, 0.4);
            border-radius: 20px;
            padding: 30px;
            position: relative;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
            min-height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* Interactive grid lines inside illustration */
        .grid-lines {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 20px 20px;
            z-index: 1;
        }

        .network-node {
            position: absolute;
            width: 8px;
            height: 8px;
            background-color: var(--accent);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--accent);
            z-index: 2;
        }
        .node-1 { top: 25%; left: 20%; animation: pulse-node 3s infinite; }
        .node-2 { top: 60%; left: 40%; animation: pulse-node 4s infinite 1s; }
        .node-3 { top: 35%; left: 75%; animation: pulse-node 3s infinite 2s; }
        .node-4 { top: 75%; left: 80%; animation: pulse-node 5s infinite; }

        @keyframes pulse-node {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.8); opacity: 1; box-shadow: 0 0 15px var(--accent); }
        }

        /* Navigation cards with Glassmorphism */
        .portal-card {
            background-color: var(--card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            height: 100%;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }
        .portal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background-color: var(--accent);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
            box-shadow: 0 0 10px var(--accent);
        }
        .portal-card:hover::before {
            transform: scaleX(1);
        }
        .portal-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px -10px rgba(0, 240, 255, 0.2);
            border-color: rgba(0, 240, 255, 0.5);
            background-color: rgba(17, 24, 39, 0.85);
        }

        .portal-icon {
            width: 55px;
            height: 55px;
            background-color: rgba(0, 240, 255, 0.1);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--accent);
            margin-bottom: 1.25rem;
            transition: all 0.4s ease;
            border: 1px solid rgba(0, 240, 255, 0.2);
        }
        .portal-card:hover .portal-icon {
            background-color: var(--accent);
            color: #000;
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 0 20px var(--accent);
        }

        /* Button Gradients and Hover effects */
        .btn-gradient {
            background: linear-gradient(135deg, #00F0FF 0%, #1E40AF 100%);
            border: none;
            color: #fff;
            box-shadow: 0 4px 15px rgba(0, 240, 255, 0.3);
            transition: all 0.3s ease;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
        }
        .btn-gradient:hover {
            background: linear-gradient(135deg, #3B82F6 0%, #1E40AF 100%);
            color: #fff;
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0, 240, 255, 0.5);
        }

        /* Section Layouts */
        .section-title {
            position: relative;
            padding-bottom: 12px;
            margin-bottom: 2rem;
            font-weight: 700;
        }
        .section-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 3px;
            background-color: var(--accent);
            border-radius: 2px;
        }
        .section-title-center::after {
            left: 50%;
            transform: translateX(-50%);
        }

        /* Stat numbers styling */
        .stat-circle {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem auto;
            background-color: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(8px);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            z-index: 1;
        }
        .stat-circle::before {
            content: '';
            position: absolute;
            top: -4px; left: -4px; right: -4px; bottom: -4px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent) 0%, transparent 50%, #1E40AF 100%);
            z-index: -1;
            opacity: 0.2;
            transition: opacity 0.4s ease, transform 0.4s ease;
            animation: spinRing 10s linear infinite;
        }
        @keyframes spinRing {
            100% { transform: rotate(360deg); }
        }
        .stat-card-wrapper:hover .stat-circle {
            transform: scale(1.1);
        }
        .stat-card-wrapper:hover .stat-circle::before {
            opacity: 1;
            box-shadow: 0 0 20px var(--accent-glow);
        }

        /* Features List cards */
        .feature-item-card {
            background-color: rgba(17, 24, 39, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            height: 100%;
            transition: all 0.2s ease;
        }
        .feature-item-card:hover {
            background-color: var(--card-bg);
            border-color: #2D3748;
        }

        /* Live Clock Box */
        .live-clock-card {
            background: linear-gradient(135deg, #1E3A8A 0%, #0F172A 100%);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4);
        }

        /* Footer styling */
        footer {
            background-color: #0B0F19;
            border-top: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        footer a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        footer a:hover {
            color: var(--text-color);
        }

        /* Stealth Radar Map CSS */
        .radar-container {
            position: relative;
            width: 100%;
            height: 380px;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(0, 240, 255, 0.25);
            box-shadow: 0 0 25px rgba(0, 240, 255, 0.15);
            background-color: #070B14;
        }
        #radarMap {
            width: 100%;
            height: 100%;
            z-index: 1;
            filter: hue-rotate(140deg) brightness(0.8) contrast(1.2); /* Tint map towards green/cyan radar tone */
        }
        .radar-sweep-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            pointer-events: none;
            z-index: 5;
            background: radial-gradient(circle at center, transparent 30%, rgba(7, 11, 20, 0.4) 100%);
        }
        .radar-sweep {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 250%;
            height: 250%;
            transform: translate3d(-50%, -50%, 0);
            pointer-events: none;
            z-index: 2;
            animation: radar-spin 6s linear infinite;
            transform-origin: center;
            will-change: transform;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }
        .radar-grid {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            pointer-events: none;
            z-index: 3;
            background-image: 
                radial-gradient(circle, transparent 20%, rgba(0, 240, 255, 0.08) 21%, transparent 22%, transparent 45%, rgba(0, 240, 255, 0.08) 46%, transparent 47%, transparent 70%, rgba(0, 240, 255, 0.08) 71%, transparent 72%),
                linear-gradient(to right, rgba(0, 240, 255, 0.04) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(0, 240, 255, 0.04) 1px, transparent 1px);
            background-position: center;
            background-size: 100% 100%, 40px 40px, 40px 40px;
        }
        .radar-crosshair {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            pointer-events: none;
            z-index: 4;
        }
        .radar-crosshair::before {
            content: '';
            position: absolute;
            top: 50%; left: 10%; right: 10%;
            height: 1px;
            background: rgba(0, 240, 255, 0.15);
        }
        .radar-crosshair::after {
            content: '';
            position: absolute;
            left: 50%; top: 10%; bottom: 10%;
            width: 1px;
            background: rgba(0, 240, 255, 0.15);
        }
        @keyframes radar-spin {
            from { transform: translate3d(-50%, -50%, 0) rotate(0deg); }
            to { transform: translate3d(-50%, -50%, 0) rotate(360deg); }
        }
        .radar-ping {
            width: 10px;
            height: 10px;
            background-color: var(--accent);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--accent);
            animation: ping-pulse 2s infinite;
        }
        .radar-ping-label {
            position: absolute;
            top: -12px;
            left: 12px;
            font-size: 8px;
            color: var(--accent);
            font-family: monospace;
            text-shadow: 0 0 3px #000;
            white-space: nowrap;
        }
        .radar-tooltip {
            background-color: rgba(7, 11, 20, 0.9) !important;
            border: 1px solid var(--accent) !important;
            color: #fff !important;
            font-family: 'Inter', sans-serif !important;
            font-size: 10px !important;
            border-radius: 4px !important;
            box-shadow: 0 0 10px rgba(0, 240, 255, 0.3) !important;
        }
        @keyframes ping-pulse {
            0% {
                transform: scale(1);
                opacity: 1;
                box-shadow: 0 0 0 0 rgba(0, 240, 255, 0.7);
            }
            70% {
                transform: scale(3);
                opacity: 0;
                box-shadow: 0 0 0 8px rgba(0, 240, 255, 0);
            }
            100% {
                transform: scale(1);
                opacity: 0;
                box-shadow: 0 0 0 0 rgba(0, 240, 255, 0);
            }
        }

        .text-secondary { color: var(--text-muted) !important; }
    </style>

    <!-- Inline critical font-display for system fonts fallback -->
    <style>
        @font-face { font-display: swap; }

        .text-secondary { color: var(--text-muted) !important; }

        /* Premium Light Theme Overrides */
        :root {
            --primary: #1E40AF !important;
            --secondary: #2563EB !important;
            --accent: #2563EB !important; /* Vibrant Blue */
            --accent-glow: rgba(37, 99, 235, 0.15) !important;
            --bg-dark: #F8FAFC !important; /* Slate 50 Light Background */
            --card-bg: rgba(255, 255, 255, 0.8) !important; /* Light Glassmorphic Base */
            --border-color: rgba(15, 23, 42, 0.08) !important; /* Subtle slate border */
            --text-color: #0F172A !important; /* Slate 900 */
            --text-muted: #475569 !important; /* Slate 600 - High Contrast Visible Text */
        }

        body {
            background-color: var(--bg-dark) !important;
            color: var(--text-color) !important;
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(37, 99, 235, 0.04), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(14, 165, 233, 0.03), transparent 25%) !important;
        }

        .glow-spot-1 {
            background: radial-gradient(circle, rgba(37, 99, 235, 0.06) 0%, transparent 60%) !important;
        }
        .glow-spot-2 {
            background: radial-gradient(circle, rgba(14, 165, 233, 0.06) 0%, transparent 60%) !important;
        }

        .hover-primary {
            transition: color 0.3s ease, text-shadow 0.3s ease;
            color: var(--text-muted) !important;
        }
        .hover-primary:hover {
            color: var(--accent) !important;
            text-shadow: 0 0 10px rgba(37, 99, 235, 0.2);
        }

        /* Navigation cards with Glassmorphism */
        .portal-card {
            background-color: var(--card-bg) !important;
            border: 1px solid var(--border-color) !important;
            box-shadow: 0 10px 20px -5px rgba(15, 23, 42, 0.05) !important;
        }
        .portal-card::before {
            background-color: var(--accent) !important;
            box-shadow: 0 0 10px rgba(37, 99, 235, 0.3) !important;
        }
        .portal-card:hover {
            box-shadow: 0 20px 40px -10px rgba(37, 99, 235, 0.15) !important;
            border-color: rgba(37, 99, 235, 0.3) !important;
            background-color: rgba(255, 255, 255, 0.95) !important;
        }

        .portal-icon {
            background-color: rgba(37, 99, 235, 0.08) !important;
            color: var(--accent) !important;
            border: 1px solid rgba(37, 99, 235, 0.15) !important;
        }
        .portal-card:hover .portal-icon {
            background-color: var(--accent) !important;
            color: #fff !important;
            box-shadow: 0 0 20px rgba(37, 99, 235, 0.3) !important;
        }

        /* Button Gradients and Hover effects */
        .btn-gradient {
            background: linear-gradient(135deg, #3B82F6 0%, #1E40AF 100%) !important;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.25) !important;
        }
        .btn-gradient:hover {
            background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%) !important;
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.35) !important;
        }

        .btn-outline-custom {
            border: 1px solid rgba(37, 99, 235, 0.3) !important;
            background-color: rgba(255, 255, 255, 0.6) !important;
            color: #1e3a8a !important;
            transition: all 0.3s ease !important;
        }
        .btn-outline-custom:hover {
            background-color: #2563EB !important;
            color: #ffffff !important;
            border-color: #2563EB !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2) !important;
        }

        /* Stat numbers styling */
        .stat-circle {
            border: 2px solid rgba(15, 23, 42, 0.05) !important;
            background-color: rgba(255, 255, 255, 0.85) !important;
        }

        /* Features List cards */
        .feature-item-card {
            background-color: rgba(255, 255, 255, 0.7) !important;
            border: 1px solid var(--border-color) !important;
        }
        .feature-item-card:hover {
            background-color: var(--card-bg) !important;
            border-color: rgba(37, 99, 235, 0.2) !important;
            box-shadow: 0 10px 20px -5px rgba(15, 23, 42, 0.05) !important;
        }

        /* Live Clock Box */
        .live-clock-card {
            background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%) !important;
            border: 1px solid rgba(37, 99, 235, 0.15) !important;
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.1) !important;
        }

        /* Footer styling */
        footer {
            background-color: #F1F5F9 !important;
            border-top: 1px solid var(--border-color) !important;
        }
        footer a:hover {
            color: var(--primary) !important;
        }

        /* Stealth Radar Map CSS */
        .radar-container {
            border: 1px solid rgba(37, 99, 235, 0.25) !important;
            box-shadow: 0 0 25px rgba(37, 99, 235, 0.1) !important;
            background-color: #F8FAFC !important;
        }
        #radarMap {
            filter: saturate(0.85) contrast(1.05) !important;
        }
        .radar-sweep-overlay {
            background: radial-gradient(circle at center, transparent 30%, rgba(248, 250, 252, 0.3) 100%) !important;
        }
        .radar-sweep {
            background: none !important; /* disabled conic-gradient in favor of hardware accelerated canvas draw */
            will-change: transform;
            transform: translate3d(-50%, -50%, 0);
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }
        .radar-grid {
            background-image: 
                radial-gradient(circle, transparent 20%, rgba(37, 99, 235, 0.05) 21%, transparent 22%, transparent 45%, rgba(37, 99, 235, 0.05) 46%, transparent 47%, transparent 70%, rgba(37, 99, 235, 0.05) 71%, transparent 72%),
                linear-gradient(to right, rgba(37, 99, 235, 0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(37, 99, 235, 0.03) 1px, transparent 1px) !important;
        }
        .radar-crosshair::before {
            background: rgba(37, 99, 235, 0.08) !important;
        }
        .radar-crosshair::after {
            background: rgba(37, 99, 235, 0.08) !important;
        }
        
        /* --- Premium Maps Improvements --- */
        
        /* Custom Depot Marker */
        .custom-depot-marker {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
        }
        .depot-pulse-ring {
            position: absolute;
            width: 48px;
            height: 48px;
            background-color: rgba(37, 99, 235, 0.15);
            border-radius: 50%;
            animation: pulse-ring-anim 2s infinite ease-out;
        }
        .depot-marker-core {
            width: 28px;
            height: 28px;
            background-color: #2563EB;
            color: #ffffff;
            border-radius: 50%;
            border: 2px solid #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
            z-index: 2;
        }
        
        /* Custom Start Marker */
        .custom-start-marker {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
        }
        .start-marker-core {
            width: 20px;
            height: 20px;
            background-color: #10B981;
            color: #ffffff;
            border-radius: 50%;
            border: 1.5px solid #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            box-shadow: 0 3px 6px rgba(16, 185, 129, 0.35);
        }

        /* Custom End Marker */
        .custom-end-marker {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
        }
        .end-marker-core {
            width: 20px;
            height: 20px;
            background-color: #EF4444;
            color: #ffffff;
            border-radius: 50%;
            border: 1.5px solid #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            box-shadow: 0 3px 6px rgba(239, 68, 68, 0.35);
        }

        /* Custom Stop Marker */
        .custom-stop-marker {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 12px;
            height: 12px;
        }
        .stop-marker-core {
            width: 8px;
            height: 8px;
            background-color: #F59E0B;
            border-radius: 50%;
            border: 1px solid #ffffff;
            box-shadow: 0 2px 4px rgba(245, 158, 11, 0.35);
        }

        @keyframes pulse-ring-anim {
            0% { transform: scale(0.5); opacity: 1; }
            100% { transform: scale(1.5); opacity: 0; }
        }

        /* Custom Marker Cluster Badges */
        .custom-cluster {
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 2px solid #ffffff;
            color: #ffffff;
            font-weight: 700;
            font-size: 11px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
            font-family: 'Inter', sans-serif;
        }
        .depot-cluster {
            background-color: #2563EB;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.35);
        }
        .start-end-cluster {
            background-color: #10B981;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.35);
        }
        .stop-cluster {
            background-color: #F59E0B;
            box-shadow: 0 4px 10px rgba(245, 158, 11, 0.35);
        }

        /* Glassmorphism Layer Control */
        .leaflet-control-layers {
            background-color: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(15, 23, 42, 0.08) !important;
            color: #0F172A !important;
            border-radius: 12px !important;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08) !important;
            font-family: 'Inter', sans-serif !important;
            padding: 10px 14px !important;
            transition: all 0.3s ease;
        }
        .leaflet-control-layers:hover {
            background-color: rgba(255, 255, 255, 0.95) !important;
            box-shadow: 0 12px 30px -5px rgba(15, 23, 42, 0.12) !important;
        }
        .leaflet-control-layers-overlays label {
            margin-bottom: 6px !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            font-weight: 500 !important;
            font-size: 0.82rem !important;
            color: #334155 !important;
        }
        .leaflet-control-layers-overlays label:last-child {
            margin-bottom: 0 !important;
        }
        .leaflet-control-layers-overlays input[type="checkbox"] {
            cursor: pointer !important;
            accent-color: #2563EB !important;
            width: 14px;
            height: 14px;
        }
        
        /* Customize standard Leaflet Zoom Control for Premium feel */
        .leaflet-bar {
            border: 1px solid rgba(15, 23, 42, 0.08) !important;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06) !important;
            border-radius: 8px !important;
            overflow: hidden;
        }
        .leaflet-bar a {
            background-color: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(10px);
            color: #0F172A !important;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;
            transition: all 0.2s ease;
        }
        .leaflet-bar a:hover {
            background-color: #ffffff !important;
            color: #2563EB !important;
        }

        .radar-ping-label {
            color: var(--accent) !important;
            text-shadow: 0 0 3px #fff !important;
        }
        .radar-tooltip {
            background-color: rgba(255, 255, 255, 0.95) !important;
            border: 1px solid var(--accent) !important;
            color: #0f172a !important;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.15) !important;
        }

        /* Standard Navigation Links with Epic Slide Animation */
        .nav-link-standard {
            position: relative;
            color: #475569 !important;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .nav-link-standard::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 2px;
            left: 50%;
            background-color: var(--accent);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        .nav-link-standard:hover {
            color: var(--accent) !important;
        }
        .nav-link-standard:hover::after {
            width: 60%;
        }

        /* Epic Portal Buttons in Navbar */
        .nav-portal-btn {
            padding: 0.45rem 1rem !important;
            border-radius: 50px !important;
            border: 1px solid transparent;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .nav-portal-btn:hover {
            transform: translateY(-2px);
        }
        .nav-portal-btn i {
            font-size: 0.95rem;
            transition: transform 0.3s ease;
        }
        .nav-portal-btn:hover i {
            transform: scale(1.15);
        }

        /* Admin - Red */
        .nav-portal-admin {
            background-color: rgba(239, 68, 68, 0.08) !important;
            border-color: rgba(239, 68, 68, 0.25) !important;
            color: #EF4444 !important;
        }
        .nav-portal-admin:hover {
            background-color: rgba(239, 68, 68, 0.14) !important;
            border-color: #EF4444 !important;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.3) !important;
            color: #DC2626 !important;
        }

        /* Supervisor - Blue */
        .nav-portal-supervisor {
            background-color: rgba(59, 130, 246, 0.08) !important;
            border-color: rgba(59, 130, 246, 0.25) !important;
            color: #3B82F6 !important;
        }
        .nav-portal-supervisor:hover {
            background-color: rgba(59, 130, 246, 0.14) !important;
            border-color: #3B82F6 !important;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3) !important;
            color: #2563EB !important;
        }

        /* Driver - Green */
        .nav-portal-driver {
            background-color: rgba(16, 185, 129, 0.08) !important;
            border-color: rgba(16, 185, 129, 0.25) !important;
            color: #10B981 !important;
        }
        .nav-portal-driver:hover {
            background-color: rgba(16, 185, 129, 0.14) !important;
            border-color: #10B981 !important;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.3) !important;
            color: #059669 !important;
        }

        /* Maintenance - Orange */
        .nav-portal-maintenance {
            background-color: rgba(249, 115, 22, 0.08) !important;
            border-color: rgba(249, 115, 22, 0.25) !important;
            color: #F97316 !important;
        }
        .nav-portal-maintenance:hover {
            background-color: rgba(249, 115, 22, 0.14) !important;
            border-color: #F97316 !important;
            box-shadow: 0 0 15px rgba(249, 115, 22, 0.3) !important;
            color: #EA580C !important;
        }

        /* Custom login portals card hover colors */
        .portal-card-admin::before { background-color: #EF4444 !important; box-shadow: 0 0 10px rgba(239, 68, 68, 0.3) !important; }
        .portal-card-admin:hover { border-color: rgba(239, 68, 68, 0.3) !important; box-shadow: 0 20px 40px -10px rgba(239, 68, 68, 0.15) !important; }
        .portal-card-admin .portal-icon { background-color: rgba(239, 68, 68, 0.08) !important; color: #EF4444 !important; border-color: rgba(239, 68, 68, 0.15) !important; }
        .portal-card-admin:hover .portal-icon { background-color: #EF4444 !important; color: #fff !important; box-shadow: 0 0 20px rgba(239, 68, 68, 0.3) !important; }
        .btn-outline-admin { border: 1px solid rgba(239, 68, 68, 0.3) !important; background-color: rgba(255, 255, 255, 0.6) !important; color: #DC2626 !important; transition: all 0.3s ease !important; }
        .btn-outline-admin:hover { background-color: #EF4444 !important; color: #ffffff !important; border-color: #EF4444 !important; transform: translateY(-2px) !important; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2) !important; }

        .portal-card-supervisor::before { background-color: #3B82F6 !important; box-shadow: 0 0 10px rgba(59, 130, 246, 0.3) !important; }
        .portal-card-supervisor:hover { border-color: rgba(59, 130, 246, 0.3) !important; box-shadow: 0 20px 40px -10px rgba(59, 130, 246, 0.15) !important; }
        .portal-card-supervisor .portal-icon { background-color: rgba(59, 130, 246, 0.08) !important; color: #3B82F6 !important; border-color: rgba(59, 130, 246, 0.15) !important; }
        .portal-card-supervisor:hover .portal-icon { background-color: #3B82F6 !important; color: #fff !important; box-shadow: 0 0 20px rgba(59, 130, 246, 0.3) !important; }
        .btn-outline-supervisor { border: 1px solid rgba(59, 130, 246, 0.3) !important; background-color: rgba(255, 255, 255, 0.6) !important; color: #2563EB !important; transition: all 0.3s ease !important; }
        .btn-outline-supervisor:hover { background-color: #3B82F6 !important; color: #ffffff !important; border-color: #3B82F6 !important; transform: translateY(-2px) !important; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2) !important; }

        .portal-card-driver::before { background-color: #10B981 !important; box-shadow: 0 0 10px rgba(16, 185, 129, 0.3) !important; }
        .portal-card-driver:hover { border-color: rgba(16, 185, 129, 0.3) !important; box-shadow: 0 20px 40px -10px rgba(16, 185, 129, 0.15) !important; }
        .portal-card-driver .portal-icon { background-color: rgba(16, 185, 129, 0.08) !important; color: #10B981 !important; border-color: rgba(16, 185, 129, 0.15) !important; }
        .portal-card-driver:hover .portal-icon { background-color: #10B981 !important; color: #fff !important; box-shadow: 0 0 20px rgba(16, 185, 129, 0.3) !important; }
        .btn-outline-driver { border: 1px solid rgba(16, 185, 129, 0.3) !important; background-color: rgba(255, 255, 255, 0.6) !important; color: #059669 !important; transition: all 0.3s ease !important; }
        .btn-outline-driver:hover { background-color: #10B981 !important; color: #ffffff !important; border-color: #10B981 !important; transform: translateY(-2px) !important; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2) !important; }

        .portal-card-maintenance::before { background-color: #F97316 !important; box-shadow: 0 0 10px rgba(249, 115, 22, 0.3) !important; }
        .portal-card-maintenance:hover { border-color: rgba(249, 115, 22, 0.3) !important; box-shadow: 0 20px 40px -10px rgba(249, 115, 22, 0.15) !important; }
        .portal-card-maintenance .portal-icon { background-color: rgba(249, 115, 22, 0.08) !important; color: #F97316 !important; border-color: rgba(249, 115, 22, 0.15) !important; }
        .portal-card-maintenance:hover .portal-icon { background-color: #F97316 !important; color: #fff !important; box-shadow: 0 0 20px rgba(249, 115, 22, 0.3) !important; }
        .btn-outline-maintenance { border: 1px solid rgba(249, 115, 22, 0.3) !important; background-color: rgba(255, 255, 255, 0.6) !important; color: #EA580C !important; transition: all 0.3s ease !important; }
        .btn-outline-maintenance:hover { background-color: #F97316 !important; color: #ffffff !important; border-color: #F97316 !important; transform: translateY(-2px) !important; box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2) !important; }

        /* Hero Dashboard Illustration CSS */
        .hero-illustration-container {
            height: 400px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 1000px;
        }
        .floating-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.08);
            position: absolute;
            width: 260px;
            z-index: 2;
        }
        .floating-card.card-1 {
            top: 20px;
            left: 20px;
        }
        .floating-card.card-2 {
            top: 150px;
            right: 0px;
        }
        .floating-card.card-3 {
            bottom: 40px;
            left: 40px;
        }
        .illustration-glow {
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(37,99,235,0.15) 0%, rgba(255,255,255,0) 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1;
            border-radius: 50%;
        }
        .illustration-glow-2 {
            position: absolute;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(16,185,129,0.12) 0%, rgba(255,255,255,0) 70%);
            bottom: 0;
            right: 20px;
            z-index: 1;
            border-radius: 50%;
        }
        @keyframes floatUp {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        @keyframes floatDown {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(15px); }
        }
        @keyframes pulse-bar {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 1; }
        }

    </style>
</head>
<body>

    <div class="glow-spot-1"></div>
    <div class="glow-spot-2"></div>

    <!-- Header Navigation bar (Landing view) -->
    <nav class="navbar navbar-expand-lg navbar-light border-bottom border-light-subtle py-3" style="background-color: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); position: sticky; top: 0; z-index: 1000;">
        <div class="container-fluid px-lg-5">
            <a class="navbar-brand d-flex align-items-center fw-bold poppins text-dark" href="index.php">
                <img src="<?= htmlspecialchars($org_logo) ?>" alt="SRMSS Logo"
                     fetchpriority="high"
                     decoding="async"
                     style="height: 68px; width: auto; object-fit: contain; margin-right: 12px; position: relative; z-index: 1001;">
            </a>
            <button class="navbar-toggler border-light-subtle" type="button" data-bs-toggle="collapse" data-bs-target="#landingNavbar" aria-controls="landingNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="landingNavbar">
                <ul class="navbar-nav ms-auto gap-2 align-items-center">
                    <li class="nav-item"><a class="nav-link px-3 nav-link-standard" href="index.php"><i class="fas fa-home me-1"></i> Home</a></li>
                    <li class="nav-item"><a class="nav-link px-3 nav-link-standard" href="#features"><i class="fas fa-star me-1"></i> Features</a></li>
                    <li class="nav-item"><a class="nav-link px-3 nav-link-standard" href="#about"><i class="fas fa-info-circle me-1"></i> About</a></li>
                    <li class="nav-item"><a class="nav-link px-3 nav-link-standard" href="#contact"><i class="fas fa-envelope me-1"></i> Contact</a></li>
                    <li class="nav-item"><a class="nav-link nav-portal-btn nav-portal-admin" href="auth/admin_login.php"><i class="fas fa-user-shield me-1"></i> Admin</a></li>
                    <li class="nav-item"><a class="nav-link nav-portal-btn nav-portal-supervisor" href="auth/supervisor_login.php"><i class="fas fa-user-tie me-1"></i> Supervisor</a></li>
                    <li class="nav-item"><a class="nav-link nav-portal-btn nav-portal-driver" href="auth/driver_login.php"><i class="fas fa-id-card me-1"></i> Driver</a></li>
                    <li class="nav-item"><a class="nav-link nav-portal-btn nav-portal-maintenance" href="auth/maintenance_login.php"><i class="fas fa-screwdriver-wrench me-1"></i> Maintenance</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero-section">
        <div class="container">
            <div class="row g-5 align-items-center">
                <!-- Hero left block -->
                <div class="col-lg-7 text-center text-lg-start">
                    <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-50 px-3 py-2 rounded-pill text-uppercase fw-bold mb-3 poppins" style="font-size: 10px; letter-spacing: 1px;">
                        Enterprise Transport Operations
                    </span>
                    <h1 class="poppins fw-bold text-dark mb-3" style="font-size: 2.8rem; line-height: 1.25;">
                        Intelligent Route & Fleet Scheduling System
                    </h1>
                    <p class="text-secondary fs-5 mb-4" style="line-height: 1.6; max-width: 580px;">
                        Automated transit scheduling, real-time driver allocation monitoring, vehicle maintenance diagnostics, and data-driven reporting tailored for transport depot hubs.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center justify-content-lg-start gap-3 mb-4">
                        <a href="#portals" class="btn btn-gradient btn-lg px-4 rounded-pill fw-semibold"><i aria-hidden="true" class="fas fa-play me-2"></i>Get Started</a>
                    </div>
                    <div class="d-flex flex-wrap justify-content-center justify-content-lg-start gap-2">
                        <a href="auth/admin_login.php" class="btn btn-outline-custom btn-sm px-3 py-2 rounded-pill fw-medium"><i aria-hidden="true" class="fas fa-user-shield me-1"></i>Administrator Portal</a>
                        <a href="auth/supervisor_login.php" class="btn btn-outline-custom btn-sm px-3 py-2 rounded-pill fw-medium"><i aria-hidden="true" class="fas fa-user-tie me-1"></i>Supervisor Portal</a>
                        <a href="auth/driver_login.php" class="btn btn-outline-custom btn-sm px-3 py-2 rounded-pill fw-medium"><i aria-hidden="true" class="fas fa-id-card me-1"></i>Driver Portal</a>
                        <a href="auth/maintenance_login.php" class="btn btn-outline-custom btn-sm px-3 py-2 rounded-pill fw-medium"><i aria-hidden="true" class="fas fa-screwdriver-wrench me-1"></i>Maintenance Portal</a>
                    </div>
                </div>

                <!-- Hero right block: live Colombo clock & Radar Map -->
                <div class="col-lg-5">
                    <!-- Live Colombo clock -->
                    <div class="live-clock-card text-center mb-4 border shadow-lg">
                        <div class="poppins text-uppercase text-primary fw-bold mb-2" style="font-size: 9px; letter-spacing: 1.5px;">Asia / Colombo Local Clock</div>
                        <h2 class="poppins fw-bold text-dark mb-1" id="liveTime" style="font-size: 2rem; letter-spacing: 0.5px;">Loading Clock...</h2>
                        <div class="text-secondary small" id="liveDate"></div>
                    </div>
                    
                    <!-- Attractive 3D Dashboard Illustration -->
                    <div class="hero-illustration-container position-relative">
                        <div class="floating-card card-1" style="animation: floatUp 6s ease-in-out infinite;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="icon-box bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded-circle" style="width: 48px; height: 48px;">
                                    <i class="fas fa-bus-alt fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold poppins text-dark">Fleet Availability</h6>
                                    <small class="text-success fw-semibold">98% Optimal <i class="fas fa-arrow-up ms-1"></i></small>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 6px; border-radius: 10px;">
                                <div class="progress-bar bg-primary rounded-pill" role="progressbar" style="width: 98%"></div>
                            </div>
                        </div>

                        <div class="floating-card card-2" style="animation: floatDown 7s ease-in-out infinite; animation-delay: 1s;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="icon-box bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center rounded-circle" style="width: 48px; height: 48px;">
                                    <i class="fas fa-route fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold poppins text-dark">Live Routes</h6>
                                    <small class="text-secondary">AI Scheduling Active</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-end gap-1 mt-3" style="height: 24px;">
                                <div class="bg-success rounded-top" style="width: 15%; height: 40%; animation: pulse-bar 1.5s infinite;"></div>
                                <div class="bg-success rounded-top" style="width: 15%; height: 70%; animation: pulse-bar 1.5s infinite 0.2s;"></div>
                                <div class="bg-success rounded-top" style="width: 15%; height: 50%; animation: pulse-bar 1.5s infinite 0.4s;"></div>
                                <div class="bg-success rounded-top" style="width: 15%; height: 100%; animation: pulse-bar 1.5s infinite 0.6s;"></div>
                                <div class="bg-success rounded-top" style="width: 15%; height: 80%; animation: pulse-bar 1.5s infinite 0.8s;"></div>
                            </div>
                        </div>

                        <div class="floating-card card-3" style="animation: floatUp 8s ease-in-out infinite; animation-delay: 2s;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="icon-box bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center rounded-circle" style="width: 48px; height: 48px;">
                                    <i class="fas fa-chart-line fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold poppins text-dark">Efficiency Score</h6>
                                    <small class="text-secondary">+12.5% this week</small>
                                </div>
                            </div>
                        </div>

                        <div class="illustration-glow"></div>
                        <div class="illustration-glow-2"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SYSTEM STATISTICS SECTION -->
    <section class="py-5 border-top border-bottom border-light-subtle" id="statistics" style="background-color: rgba(241, 245, 249, 0.6);">
        <div class="container">
            <div class="row text-center g-4 justify-content-center">
                <?php if ($db_connected): ?>
                    <!-- Stat 1 -->
                    <div class="col-md-3 col-6 stat-card-wrapper">
                        <div class="stat-circle">
                            <h2 class="poppins fw-bold text-primary mb-0 counter" data-count="<?= $routes_count ?>">0</h2>
                        </div>
                        <h6 class="text-dark fw-semibold mb-1">Active Routes</h6>
                        <small class="text-secondary">Total operational routes</small>
                    </div>
                    <!-- Stat 2 -->
                    <div class="col-md-3 col-6 stat-card-wrapper">
                        <div class="stat-circle">
                            <h2 class="poppins fw-bold text-success mb-0 counter" data-count="<?= $vehicles_count ?>">0</h2>
                        </div>
                        <h6 class="text-dark fw-semibold mb-1">Active Vehicles</h6>
                        <small class="text-secondary">Vehicles in service</small>
                    </div>
                    <!-- Stat 3 -->
                    <div class="col-md-3 col-6 stat-card-wrapper">
                        <div class="stat-circle">
                            <h2 class="poppins fw-bold text-info mb-0 counter" data-count="<?= $drivers_count ?>">0</h2>
                        </div>
                        <h6 class="text-dark fw-semibold mb-1">Active Drivers</h6>
                        <small class="text-secondary">Registered active drivers</small>
                    </div>
                    <!-- Stat 4 -->
                    <div class="col-md-3 col-6 stat-card-wrapper">
                        <div class="stat-circle">
                            <h2 class="poppins fw-bold text-warning mb-0 counter" data-count="<?= $active_schedules_count ?>">0</h2>
                        </div>
                        <h6 class="text-dark fw-semibold mb-1">Total Schedules</h6>
                        <small class="text-secondary">System scheduled trips</small>
                    </div>
                <?php else: ?>
                    <div class="col-12 py-4">
                        <i aria-hidden="true" class="fas fa-database text-danger fa-2x mb-2"></i>
                        <h5 class="poppins text-dark">No operational data available</h5>
                        <p class="text-secondary small">Database offline or tables currently unseeded</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- SYSTEM FEATURES GRID -->
    <section class="py-5" id="features">
        <div class="container">
            <div class="text-center max-width-600 mx-auto mb-5">
                <h2 class="poppins fw-bold text-dark section-title section-title-center">Core Platform Features</h2>
                <p class="text-secondary small">Comprehensive modules designed to handle all aspects of public transport scheduling and depot logistics from one hub.</p>
            </div>
            
            <div class="row g-4">
                <!-- Card 1 -->
                <div class="col-lg-3 col-md-6">
                    <div class="feature-item-card">
                        <i aria-hidden="true" class="fas fa-route text-primary fa-lg mb-3"></i>
                        <h6 class="text-dark fw-semibold">Route Management</h6>
                        <p class="text-secondary small mb-0">Record route coordinates, locations, stops, distance limits, and coordinate maps.</p>
                    </div>
                </div>
                <!-- Card 2 -->
                <div class="col-lg-3 col-md-6">
                    <div class="feature-item-card">
                        <i aria-hidden="true" class="fas fa-bus text-success fa-lg mb-3"></i>
                        <h6 class="text-dark fw-semibold">Vehicle Tracking</h6>
                        <p class="text-secondary small mb-0">Fleet availability logs, vehicle capacities, diagnostics state, and models.</p>
                    </div>
                </div>
                <!-- Card 3 -->
                <div class="col-lg-3 col-md-6">
                    <div class="feature-item-card">
                        <i aria-hidden="true" class="fas fa-id-card text-info fa-lg mb-3"></i>
                        <h6 class="text-dark fw-semibold">Driver Rosters</h6>
                        <p class="text-secondary small mb-0">Driver assignments, working hours limits, NIC validations, and license expiries.</p>
                    </div>
                </div>
                <!-- Card 4 -->
                <div class="col-lg-3 col-md-6">
                    <div class="feature-item-card">
                        <i aria-hidden="true" class="fas fa-calendar-days text-warning fa-lg mb-3"></i>
                        <h6 class="text-dark fw-semibold">Transit Schedules</h6>
                        <p class="text-secondary small mb-0">Define trip times, auto-check overlap conflicts, and track completed/delayed runs.</p>
                    </div>
                </div>
                <!-- Card 5 -->
                <div class="col-lg-3 col-md-6">
                    <div class="feature-item-card">
                        <i aria-hidden="true" class="fas fa-gas-pump text-danger fa-lg mb-3"></i>
                        <h6 class="text-dark fw-semibold">Fuel Monitoring</h6>
                        <p class="text-secondary small mb-0">Track fuel log refills, expenditures, invoices, and liters efficiencies.</p>
                    </div>
                </div>
                <!-- Card 6 -->
                <div class="col-lg-3 col-md-6">
                    <div class="feature-item-card">
                        <i aria-hidden="true" class="fas fa-screwdriver-wrench text-primary fa-lg mb-3"></i>
                        <h6 class="text-dark fw-semibold">Maintenance Diagnostics</h6>
                        <p class="text-secondary small mb-0">Record service costs, schedule overdue service reminders, and inspect checklists.</p>
                    </div>
                </div>
                <!-- Card 7 -->
                <div class="col-lg-3 col-md-6">
                    <div class="feature-item-card">
                        <i aria-hidden="true" class="fas fa-chart-pie text-success fa-lg mb-3"></i>
                        <h6 class="text-dark fw-semibold">Reports & Analytics</h6>
                        <p class="text-secondary small mb-0">Generate executive PDF summaries, Excel workbooks, and CSV records on performance.</p>
                    </div>
                </div>
                <!-- Card 8 -->
                <div class="col-lg-3 col-md-6">
                    <div class="feature-item-card">
                        <i aria-hidden="true" class="fas fa-bell text-info fa-lg mb-3"></i>
                        <h6 class="text-dark fw-semibold">Alert Center</h6>
                        <p class="text-secondary small mb-0">Broadcast priority system reminders, schedule cancel alerts, and updates feeds.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ABOUT SYSTEM & BENEFITS -->
    <section class="py-5 border-top border-light-subtle" id="about" style="background-color: rgba(241, 245, 249, 0.35);">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                    <h2 class="poppins fw-bold text-dark section-title">About the Platform</h2>
                    <p class="text-secondary mb-4" style="line-height: 1.7;">
                        The Smart Route Management and Scheduling System (SRMSS) is an integrated digital platform designed to optimize public transport operations. By centralizing schedules, vehicles, and driver rosters, the platform eliminates scheduling overlaps, flags expired licenses, and calculates refuel efficiency trends dynamically.
                    </p>
                    
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="d-flex align-items-start gap-2">
                                <i aria-hidden="true" class="fas fa-circle-check text-primary mt-1"></i>
                                <div>
                                    <h6 class="text-dark mb-1 small fw-semibold">Fleet Optimization</h6>
                                    <p class="text-secondary small mb-0">Align bus capacities with route density requirements.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-start gap-2">
                                <i aria-hidden="true" class="fas fa-circle-check text-primary mt-1"></i>
                                <div>
                                    <h6 class="text-dark mb-1 small fw-semibold">Schedule Automation</h6>
                                    <p class="text-secondary small mb-0">Conflict checks prevent double driver allocation.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-start gap-2">
                                <i aria-hidden="true" class="fas fa-circle-check text-primary mt-1"></i>
                                <div>
                                    <h6 class="text-dark mb-1 small fw-semibold">Fuel & Service Checks</h6>
                                    <p class="text-secondary small mb-0">Preventive maintenance limits route breakdowns.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-start gap-2">
                                <i aria-hidden="true" class="fas fa-circle-check text-primary mt-1"></i>
                                <div>
                                    <h6 class="text-dark mb-1 small fw-semibold">Real-Time Dashboards</h6>
                                    <p class="text-secondary small mb-0">Specialized consoles optimized for specific user roles.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="border border-light-subtle p-4 rounded-4" style="background-color: rgba(255, 255, 255, 0.7); border: 1px solid rgba(15, 23, 42, 0.08) !important;">
                        <h5 class="poppins fw-semibold text-dark mb-3"><i aria-hidden="true" class="fas fa-circle-info text-primary me-2"></i>Operating Objectives</h5>
                        <ul class="text-secondary small ps-3 mb-0" style="line-height: 1.8;">
                            <li class="mb-2"><strong>Enhance Operational Efficiency</strong>: Minimize schedule delay overheads and simplify allocations.</li>
                            <li class="mb-2"><strong>Improve Fleet Health Score</strong>: Enforce regular oil, filter, and mechanical checks via automated indicators.</li>
                            <li class="mb-2"><strong>Driver Welfare Tracking</strong>: Track individual driver schedules to prevent excessive driving hours exceeding 12 hours.</li>
                            <li><strong>Provide Precise Auditing</strong>: Capture activity and logout logs to record system security and config alterations.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PORTALS QUICK ACCESS SECTION (LOGIN PORTALS) -->
    <section class="py-5 border-top border-light-subtle" id="portals">
        <div class="container">
            <div class="text-center max-width-600 mx-auto mb-5">
                <h2 class="poppins fw-bold text-dark section-title section-title-center">Staff Access Consoles</h2>
                <p class="text-secondary small">Access specialized management environments optimized specifically for your operations category.</p>
            </div>
            
            <div class="row g-4 justify-content-center">
                <!-- Card 1 -->
                <div class="col-lg-3 col-md-6">
                    <div class="portal-card portal-card-admin">
                        <div class="portal-icon">
                            <i aria-hidden="true" class="fas fa-user-shield"></i>
                        </div>
                        <h5 class="poppins fw-semibold text-dark">Administrator</h5>
                        <p class="text-secondary small mb-4" style="min-height: 50px;">Manage users, database parameters, settings configurations, and view reports.</p>
                        <a href="auth/admin_login.php" class="btn btn-outline-admin btn-sm w-100 rounded-pill py-2">Administrator Portal</a>
                    </div>
                </div>
                <!-- Card 2 -->
                <div class="col-lg-3 col-md-6">
                    <div class="portal-card portal-card-supervisor">
                        <div class="portal-icon">
                            <i aria-hidden="true" class="fas fa-user-tie"></i>
                        </div>
                        <h5 class="poppins fw-semibold text-dark">Supervisor</h5>
                        <p class="text-secondary small mb-4" style="min-height: 50px;">Assign routes, align schedules, coordinate drivers/buses, and track delay events.</p>
                        <a href="auth/supervisor_login.php" class="btn btn-outline-supervisor btn-sm w-100 rounded-pill py-2">Supervisor Portal</a>
                    </div>
                </div>
                <!-- Card 3 -->
                <div class="col-lg-3 col-md-6">
                    <div class="portal-card portal-card-driver">
                        <div class="portal-icon">
                            <i aria-hidden="true" class="fas fa-id-card"></i>
                        </div>
                        <h5 class="poppins fw-semibold text-dark">Driver Console</h5>
                        <p class="text-secondary small mb-4" style="min-height: 50px;">View schedules, inspect routes, report delays, and update trip progress states.</p>
                        <a href="auth/driver_login.php" class="btn btn-outline-driver btn-sm w-100 rounded-pill py-2">Driver Portal</a>
                    </div>
                </div>
                <!-- Card 4 -->
                <div class="col-lg-3 col-md-6">
                    <div class="portal-card portal-card-maintenance">
                        <div class="portal-icon">
                            <i aria-hidden="true" class="fas fa-screwdriver-wrench"></i>
                        </div>
                        <h5 class="poppins fw-semibold text-dark">Maintenance</h5>
                        <p class="text-secondary small mb-4" style="min-height: 50px;">Update fuel receipts, service history checklists, logs, and vehicle health metrics.</p>
                        <a href="auth/maintenance_login.php" class="btn btn-outline-maintenance btn-sm w-100 rounded-pill py-2">Maintenance Portal</a>
                    </div>
                </div>
            </div>

            <!-- Recover Password -->
            <div class="text-center mt-4">
                <a href="auth/forgot_password.php" class="text-muted small text-decoration-none hover-primary"><i aria-hidden="true" class="fas fa-key me-1"></i> Forgot Password or Access Recovery?</a>
            </div>
        </div>
    </section>

    <!-- CONTACT SECTION -->
    <section class="py-5 border-top border-light-subtle" id="contact" style="background-color: rgba(241, 245, 249, 0.6);">
        <div class="container">
            <div class="text-center max-width-600 mx-auto mb-5">
                <h2 class="poppins fw-bold text-dark section-title section-title-center">Get in Touch</h2>
                <p class="text-secondary small">Have operational questions or need technical support? Contact the SRMSS support team.</p>
            </div>
            <div class="row g-4 justify-content-center">
                <div class="col-lg-4 col-md-6">
                    <div class="border border-light-subtle p-4 rounded-4 text-center h-100" style="background-color: rgba(255, 255, 255, 0.7); transition: all 0.3s ease; border: 1px solid rgba(15, 23, 42, 0.08) !important;" onmouseover="this.style.transform='translateY(-5px)'; this.style.borderColor='#2563EB';" onmouseout="this.style.transform='none'; this.style.borderColor='rgba(15, 23, 42, 0.08)';">
                        <i aria-hidden="true" class="fas fa-envelope text-primary fa-2x mb-3"></i>
                        <h6 class="text-dark fw-semibold">Email Support</h6>
                        <p class="text-secondary small mb-0"><a href="mailto:support@srmss.gov.lk" class="text-decoration-none text-secondary hover-primary">support@srmss.gov.lk</a></p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="border border-light-subtle p-4 rounded-4 text-center h-100" style="background-color: rgba(255, 255, 255, 0.7); transition: all 0.3s ease; border: 1px solid rgba(15, 23, 42, 0.08) !important;" onmouseover="this.style.transform='translateY(-5px)'; this.style.borderColor='#2563EB';" onmouseout="this.style.transform='none'; this.style.borderColor='rgba(15, 23, 42, 0.08)';">
                        <i aria-hidden="true" class="fas fa-phone text-success fa-2x mb-3"></i>
                        <h6 class="text-dark fw-semibold">Hotline Call</h6>
                        <p class="text-secondary small mb-0"><a href="tel:+94112345678" class="text-decoration-none text-secondary hover-primary">+94 11 234 5678</a></p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="border border-light-subtle p-4 rounded-4 text-center h-100" style="background-color: rgba(255, 255, 255, 0.7); transition: all 0.3s ease; border: 1px solid rgba(15, 23, 42, 0.08) !important;" onmouseover="this.style.transform='translateY(-5px)'; this.style.borderColor='#2563EB';" onmouseout="this.style.transform='none'; this.style.borderColor='rgba(15, 23, 42, 0.08)';">
                        <i aria-hidden="true" class="fas fa-location-dot text-info fa-2x mb-3"></i>
                        <h6 class="text-dark fw-semibold">Head Office</h6>
                        <p class="text-muted small mb-0 text-secondary">National Transport Center, Colombo 05</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER SECTION -->
    <footer class="py-5">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-md-5 text-center text-md-start">
                    <div class="mb-3">
                        <img src="<?= htmlspecialchars($org_logo) ?>" alt="SRMSS Logo"
                             width="800" height="163"
                             loading="lazy" decoding="async"
                             style="height: 70px; width: auto; object-fit: contain; flex-shrink: 0;">
                    </div>
                    <p class="small mb-0">&copy; <?= date('Y') ?> Smart Route Management and Scheduling System. All rights reserved.</p>
                </div>
                <div class="col-md-7 text-center text-md-end">
                    <div class="d-flex flex-wrap justify-content-center justify-content-md-end gap-3 small mb-2">
                        <a href="index.php">Home</a>
                        <span>&bull;</span>
                        <a href="#about">About</a>
                        <span>&bull;</span>
                        <a href="#features">Features</a>
                        <span>&bull;</span>
                        <a href="#contact">Contact</a>
                        <span>&bull;</span>
                        <a href="#" id="privacyLink">Privacy Policy</a>
                        <span>&bull;</span>
                        <a href="#" id="termsLink">Terms & Conditions</a>
                        <span>&bull;</span>
                        <a href="#" id="supportLink">Support</a>
                        <span>&bull;</span>
                        <a href="install_schema.php" id="installSchemaLink" class="text-warning"><i aria-hidden="true" class="fas fa-database me-1"></i>Install Database</a>
                        <span>&bull;</span>
                        <a href="install_sample.php" id="installSampleLink" class="text-info"><i aria-hidden="true" class="fas fa-file-import me-1"></i>Install Sample Data</a>
                        <span>&bull;</span>
                        <a href="#" id="clearDataBtn" class="text-danger fw-bold"><i aria-hidden="true" class="fas fa-trash-alt me-1"></i>Clear Current Data</a>
                    </div>
                    <small>System Version: 1.0 (Enterprise Gateway)</small>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScripts: defer non-critical scripts so they don't block rendering -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
    <!-- Leaflet is loaded dynamically via JS when radar enters viewport -->
    
    <script>


        // ── Clock (vanilla JS, starts immediately — no jQuery needed) ────────────
        (function startClock() {
            function updateClock() {
                var now = new Date();
                var timeStr = now.toLocaleTimeString('en-US', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit',
                    hour12: true, timeZone: 'Asia/Colombo'
                });
                var dateStr = new Intl.DateTimeFormat('en-US', {
                    weekday: 'long', year: 'numeric', month: 'long',
                    day: 'numeric', timeZone: 'Asia/Colombo'
                }).format(now);
                var tEl = document.getElementById('liveTime');
                var dEl = document.getElementById('liveDate');
                if (tEl) tEl.textContent = timeStr;
                if (dEl) dEl.textContent = dateStr;
            }
            updateClock();
            setInterval(updateClock, 1000);
        })();

        // ── Counter animations (pure IntersectionObserver, no jQuery needed) ────
        document.addEventListener('DOMContentLoaded', function() {
            var statsSection = document.getElementById('statistics');
            if (!statsSection) return;

            var countersDone = false;
            var statsObs = new IntersectionObserver(function(entries) {
                if (entries[0].isIntersecting && !countersDone) {
                    countersDone = true;
                    document.querySelectorAll('.counter').forEach(function(el) {
                        var target = parseInt(el.dataset.count, 10) || 0;
                        var start = 0;
                        var duration = 2000;
                        var step = Math.ceil(target / (duration / 16));
                        var timer = setInterval(function() {
                            start += step;
                            if (start >= target) { el.textContent = target; clearInterval(timer); }
                            else { el.textContent = start; }
                        }, 16);
                    });
                    statsObs.unobserve(statsSection);
                }
            }, { threshold: 0.4 });
            statsObs.observe(statsSection);
        });

        // ── jQuery-dependent code (modals, installs) — runs after defer scripts ─
        window.addEventListener('load', function() {
            if (typeof $ === 'undefined') return; // safety guard

            // SweetAlert2 modals for Footer Policy Links
            $('#privacyLink').on('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Privacy Policy',
                    html: `
                        <div class="text-start" style="font-size: 0.9rem; line-height: 1.6; color: #1e293b;">
                            <p><strong>Smart Route Management and Scheduling System (SRMSS)</strong> is dedicated to safeguarding user and driver data privacy.</p>
                            <p><strong>1. Information Collection:</strong> We collect and process driver profiles, licenses, logs, and geolocation coordinates solely for transit optimization and administrative requirements.</p>
                            <p><strong>2. Access Control:</strong> Only authenticated personnel with valid roles (Admin, Supervisor, Driver, Maintenance) can access non-public operational data.</p>
                            <p><strong>3. Data Protection:</strong> All passwords are encrypted using strong bcrypt hashing, and database transactions are secured using prepared PDO statements.</p>
                        </div>
                    `,
                    icon: 'info',
                    confirmButtonText: 'Understand',
                    confirmButtonColor: '#2563EB',
                    background: '#ffffff',
                    color: '#1e293b'
                });
            });

            $('#termsLink').on('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Terms & Conditions',
                    html: `
                        <div class="text-start" style="font-size: 0.9rem; line-height: 1.6; color: #1e293b;">
                            <p>By accessing or utilizing the <strong>SRMSS Portal</strong>, you agree to comply with the following operational guidelines:</p>
                            <p><strong>1. Authorized Access:</strong> Unauthorized access to administrative modules, route settings, or vehicle configurations is strictly prohibited and subject to auditing logs.</p>
                            <p><strong>2. Driver Safety:</strong> Driver rosters must respect maximum driving duration limits (12 hours) as automated by schedule checkers.</p>
                            <p><strong>3. Integrity of Logs:</strong> Maintenance reports and fuel updates must be accurate. Fabricating data is a violation of operating policies.</p>
                        </div>
                    `,
                    icon: 'info',
                    confirmButtonText: 'Accept Terms',
                    confirmButtonColor: '#2563EB',
                    background: '#ffffff',
                    color: '#1e293b'
                });
            });

            $('#supportLink').on('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Technical Support',
                    html: `
                        <div class="text-start" style="font-size: 0.9rem; line-height: 1.6; color: #1e293b;">
                            <p>Need assistance or ran into a system error?</p>
                            <p><strong>IT Support Hotline:</strong> +94 11 234 5678</p>
                            <p><strong>Operations Email:</strong> support@srmss.gov.lk</p>
                            <p><strong>Availability:</strong> 24/7 Operations Desk coverage for transit dispatch emergencies.</p>
                        </div>
                    `,
                    icon: 'question',
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#2563EB',
                    background: '#ffffff',
                    color: '#1e293b'
                });
            });

            // Fetch URL status for Toast notifications (e.g. status=logged_out)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('status') === 'logged_out' || urlParams.get('status') === 'session_expired') {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });

                let reasonText = 'Signed out successfully.';
                if (urlParams.get('status') === 'session_expired') {
                    reasonText = 'Session expired due to inactivity. Signed out.';
                } else {
                    const reason = urlParams.get('reason');
                    if (reason === 'timeout') reasonText = 'Session expired due to inactivity. Signed out.';
                    else if (reason === 'forced') reasonText = 'Session invalidated by security override.';
                    else if (reason === 'inactive') reasonText = 'Logged out due to inactivity limitations.';
                }

                Toast.fire({ icon: 'info', title: reasonText });
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            function installDatabase(url, title) {
                let progress = 0;
                Swal.fire({
                    title: title,
                    html: '<div style="color: #475569; margin-bottom: 15px;">Please wait while we set up the database...</div><div class="progress" style="height: 25px; background-color: #e2e8f0;"><div id="install-progress" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%; transition: width 0.5s ease;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div></div>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    background: '#ffffff',
                    color: '#1e293b',
                    didOpen: () => {
                        const progressBar = document.getElementById('install-progress');
                        const interval = setInterval(() => {
                            if (progress < 95) {
                                progress += Math.floor(Math.random() * 5) + 1;
                                if (progress > 95) progress = 95;
                                progressBar.style.width = progress + '%';
                                progressBar.innerHTML = progress + '%';
                                progressBar.setAttribute('aria-valuenow', progress);
                            }
                        }, 500);

                        fetch(url)
                            .then(response => response.json())
                            .then(data => {
                                clearInterval(interval);
                                progressBar.style.width = '100%';
                                progressBar.innerHTML = '100%';
                                progressBar.setAttribute('aria-valuenow', 100);
                                setTimeout(() => {
                                    if (data.status === 'success') {
                                        Swal.fire({
                                            icon: 'success', title: 'Success!', text: data.message,
                                            confirmButtonColor: '#2563EB', background: '#ffffff', color: '#1e293b'
                                        }).then(() => window.location.reload());
                                    } else {
                                        Swal.fire({
                                            icon: 'error', title: 'Installation Failed', text: data.message,
                                            confirmButtonColor: '#d33', background: '#ffffff', color: '#1e293b'
                                        });
                                    }
                                }, 800);
                            })
                            .catch(error => {
                                clearInterval(interval);
                                Swal.fire({
                                    icon: 'error', title: 'Installation Failed',
                                    text: 'A network error occurred: ' + error,
                                    confirmButtonColor: '#d33', background: '#ffffff', color: '#1e293b'
                                });
                            });
                    }
                });
            }

            $('#installSchemaLink').on('click', function(e) {
                e.preventDefault();
                installDatabase('install_schema.php', 'Installing Schema');
            });

            $('#installSampleLink').on('click', function(e) {
                e.preventDefault();
                installDatabase('install_sample.php', 'Installing Sample Data');
            });

            $('#clearDataBtn').on('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Are you absolutely sure?',
                    text: 'This will wipe all operational data from the database. Users and system settings will be kept.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, clear all data!',
                    background: '#ffffff',
                    color: '#1e293b'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Clearing Database',
                            html: 'Emptying tables, please wait...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        fetch('ajax/clear_database_ajax.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=clear_db'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Data Cleared!',
                                    text: data.message,
                                    confirmButtonColor: '#2563EB'
                                }).then(() => window.location.reload());
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error', 'Network error: ' + error, 'error');
                        });
                    }
                });
            });

            // Animate counter circles
            $('.counter').each(function() {
                var $this = $(this);
                var countTo = $this.attr('data-count');
                $({ countNum: $this.text() }).animate({
                    countNum: countTo
                },
                {
                    duration: 2000,
                    easing: 'swing',
                    step: function() {
                        $this.text(Math.floor(this.countNum));
                    },
                    complete: function() {
                        $this.text(this.countNum);
                    }
                });
            });

        });
    
    </script>
</body>
</html>
