<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $pageTitle ?? 'User Dashboard' ?> - <?= \App\Config\Config::getSiteName() ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',
                        secondary: '#64748b',
                        success: '#10b981',
                        warning: '#f59e0b',
                        danger: '#ef4444',
                        info: '#06b6d4',
                        dark: '#1e293b'
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            --header-height: 70px;
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --radius: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--bg-primary);
            border-right: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .sidebar-brand .crypto-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 1.5rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
            flex: 1;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: 2rem;
        }

        .nav-section-title {
            padding: 0 1.5rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .nav-item {
            margin: 0.25rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            text-decoration: none;
        }

        .nav-link.active {
            background-color: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .nav-link .nav-text {
            flex: 1;
        }

        .nav-link .nav-badge {
            background: var(--danger-color);
            color: white;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .main-header {
            height: var(--header-height);
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: var(--shadow-sm);
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-menu {
            position: relative;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-avatar:hover {
            transform: scale(1.05);
        }

        /* User Dropdown */
        .user-menu {
            position: relative;
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            min-width: 250px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
        }

        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-secondary);
        }

        .user-info {
            text-align: center;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .user-email {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .dropdown-menu {
            padding: 0.5rem 0;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            text-decoration: none;
        }

        .dropdown-item.logout:hover {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .dropdown-item i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 0.875rem;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--border-color);
            margin: 0.5rem 0;
        }

        .content-area {
            padding: 2rem;
            min-height: calc(100vh - 200px);
        }

        /* Footer Styles */
        .main-footer {
            background: var(--bg-primary);
            border-top: 1px solid var(--border-color);
            margin-top: 3rem;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            text-align: center;
        }

        .footer-section h4 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .footer-section p {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1.5rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .social-links {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .social-link {
            width: 40px;
            height: 40px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            text-decoration: none;
        }

        .footer-bottom {
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            padding: 1.5rem 2rem;
        }

        .footer-bottom-content {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .footer-bottom p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 0.875rem;
        }

        @media (max-width: 1024px) {
            .content-area {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .footer-content {
                padding: 2rem 1rem;
            }
            
            .footer-bottom {
                padding: 1rem;
            }
        }

        /* Beautiful Gradient Button Style from Uiverse */
        .gradient-btn {
            position: relative;
            border: none;
            background: transparent;
            padding: 0;
            outline: none;
            cursor: pointer;
            font-family: 'Inter', monospace;
            font-weight: 300;
            text-transform: uppercase;
            font-size: 1rem;
        }

        .gradient-btn .shadow-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.25);
            border-radius: 0.5rem;
            transform: translateY(2px);
            transition: all 600ms cubic-bezier(0.3, 0.7, 0.4, 1);
        }

        .gradient-btn:hover .shadow-layer {
            transform: translateY(4px);
            transition: all 250ms cubic-bezier(0.3, 0.7, 0.4, 1);
        }

        .gradient-btn:active .shadow-layer {
            transform: translateY(1px);
        }

        .gradient-btn .bg-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 0.5rem;
            background: linear-gradient(to left, hsl(217, 33%, 16%), hsl(217, 33%, 32%), hsl(217, 33%, 16%));
        }

        .gradient-btn .content-layer {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1.5rem;
            font-size: 1.125rem;
            color: white;
            border-radius: 0.5rem;
            transform: translateY(-4px);
            background: linear-gradient(to right, #f27121, #e94057, #8a2387);
            gap: 0.75rem;
            transition: all 600ms cubic-bezier(0.3, 0.7, 0.4, 1);
            filter: brightness(100%);
        }

        .gradient-btn:hover .content-layer {
            transform: translateY(-6px);
            transition: all 250ms cubic-bezier(0.3, 0.7, 0.4, 1);
            filter: brightness(110%);
        }

        .gradient-btn:active .content-layer {
            transform: translateY(-2px);
        }

        .gradient-btn .content-layer span {
            user-select: none;
        }

        .gradient-btn .content-layer svg {
            width: 1.25rem;
            height: 1.25rem;
            margin-left: 0.5rem;
            margin-right: -0.25rem;
            transition: transform 250ms;
        }

        .gradient-btn:hover .content-layer svg {
            transform: translateX(4px);
        }

        /* Variants */
        .gradient-btn.primary .content-layer {
            background: linear-gradient(to right, #f27121, #e94057, #8a2387);
        }

        .gradient-btn.success .content-layer {
            background: linear-gradient(to right, #10b981, #059669, #047857);
        }

        .gradient-btn.warning .content-layer {
            background: linear-gradient(to right, #f59e0b, #d97706, #b45309);
        }

        .gradient-btn.danger .content-layer {
            background: linear-gradient(to right, #ef4444, #dc2626, #b91c1c);
        }

        .gradient-btn.info .content-layer {
            background: linear-gradient(to right, #06b6d4, #0891b2, #0e7490);
        }

        /* Gradient Integration Throughout Application */
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .gradient-bg-alt {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .gradient-bg-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .gradient-bg-warning {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .gradient-bg-danger {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .gradient-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .gradient-border {
            border: 2px solid transparent;
            background: linear-gradient(white, white) padding-box,
                        linear-gradient(135deg, #667eea 0%, #764ba2 100%) border-box;
        }

        .gradient-shadow {
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .gradient-hover:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius);
            transition: all 0.2s ease;
        }

        .mobile-toggle:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-toggle {
                display: block;
            }

            .content-area {
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .main-header {
                padding: 0 1rem;
            }

            .page-title {
                font-size: 1.25rem;
            }
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Custom scrollbar */
        .sidebar-nav::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 2px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        /* Impersonation Alert */
        .impersonation-alert {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
            padding: 1rem 2rem;
            margin-bottom: 0;
            border: none;
            border-radius: 0;
        }

        .impersonation-alert .d-flex {
            align-items: center;
        }

        .impersonation-alert .btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .impersonation-alert .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            text-decoration: none;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal.show {
            display: block;
        }
        
        .modal-dialog {
            position: relative;
            width: auto;
            margin: 1.75rem auto;
            max-width: 800px;
        }
        
        .modal-content {
            position: relative;
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            outline: 0;
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }
        
        .modal-body {
            position: relative;
            padding: 1.5rem;
        }
        
        .modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 1rem 1.5rem;
            border-top: 1px solid #dee2e6;
            border-bottom-left-radius: 0.5rem;
            border-bottom-right-radius: 0.5rem;
        }
        
        .btn-close {
            background: transparent;
            border: 0;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 1.5rem;
            height: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        
        .btn-close:hover {
            color: #000;
        }
        
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-open {
            overflow: hidden;
        }

        /* Form Styles */
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: var(--text-primary);
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            color: var(--text-primary);
            background-color: #fff;
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .form-text {
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .btn {
            display: inline-block;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            text-align: center;
            text-decoration: none;
            vertical-align: middle;
            cursor: pointer;
            user-select: none;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            border-radius: 0.375rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .btn-primary {
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-primary:hover {
            color: #fff;
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }

        .btn-secondary {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            color: #fff;
            background-color: #5c636a;
            border-color: #565e64;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -0.75rem;
            margin-left: -0.75rem;
        }

        .col-md-4 {
            flex: 0 0 auto;
            width: 33.33333333%;
            padding-right: 0.75rem;
            padding-left: 0.75rem;
        }

        .col-md-6 {
            flex: 0 0 auto;
            width: 50%;
            padding-right: 0.75rem;
            padding-left: 0.75rem;
        }

        .col-12 {
            flex: 0 0 auto;
            width: 100%;
            padding-right: 0.75rem;
            padding-left: 0.75rem;
        }

        .mb-3 {
            margin-bottom: 1rem;
        }

        .mt-3 {
            margin-top: 1rem;
        }

        .text-center {
            text-align: center;
        }

        .h4 {
            font-size: 1.5rem;
            font-weight: 500;
            line-height: 1.2;
        }

        .h6 {
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.2;
        }

        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 1px solid rgba(0, 0, 0, 0.125);
            border-radius: 0.375rem;
        }

        .card-body {
            flex: 1 1 auto;
            padding: 1rem;
        }

        .bg-light {
            background-color: #f8f9fa !important;
        }

        .bg-primary {
            background-color: #0d6efd !important;
        }

        .text-white {
            color: #fff !important;
        }

        .text-muted {
            color: var(--text-secondary) !important;
        }

        .small {
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .col-md-4,
            .col-md-6 {
                width: 100%;
            }
            
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <div class="crypto-icon">₿</div>
                <?= explode(' ', \App\Config\Config::getSiteName())[0] ?>
            </a>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <div class="nav-item">
                    <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                        <i class="fas fa-home"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="deposit.php" class="nav-link <?= $currentPage === 'deposit' ? 'active' : '' ?>">
                        <i class="fas fa-plus-circle"></i>
                        <span class="nav-text">Deposit</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="withdraw.php" class="nav-link <?= $currentPage === 'withdraw' ? 'active' : '' ?>">
                        <i class="fas fa-minus-circle"></i>
                        <span class="nav-text">Withdraw</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="transfer.php" class="nav-link <?= $currentPage === 'transfer' ? 'active' : '' ?>">
                        <i class="fas fa-exchange-alt"></i>
                        <span class="nav-text">Transfer</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="invest.php" class="nav-link <?= $currentPage === 'invest' ? 'active' : '' ?>">
                        <i class="fas fa-rocket"></i>
                        <span class="nav-text">Invest</span>
                    </a>
                </div>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Account</div>
                <div class="nav-item">
                    <a href="transactions.php" class="nav-link <?= $currentPage === 'transactions' ? 'active' : '' ?>">
                        <i class="fas fa-history"></i>
                        <span class="nav-text">Transactions</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="referrals.php" class="nav-link <?= $currentPage === 'referrals' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Referrals</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="profile.php" class="nav-link <?= $currentPage === 'profile' ? 'active' : '' ?>">
                        <i class="fas fa-user"></i>
                        <span class="nav-text">Profile</span>
                    </a>
                </div>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Support</div>
                <div class="nav-item">
                    <a href="support.php" class="nav-link <?= $currentPage === 'support' ? 'active' : '' ?>">
                        <i class="fas fa-headset"></i>
                        <span class="nav-text">Support</span>
                    </a>
                </div>


                <div class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="nav-text">Logout</span>
                    </a>
                </div>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Main Header -->
        <header class="main-header">
            <div class="header-left">
                <button class="mobile-toggle" id="mobileToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title"><?= $pageTitle ?? 'Dashboard' ?></h1>
            </div>
            <div class="header-right">
                <div class="user-menu">
                    <div class="user-avatar" id="userMenuToggle" title="<?= htmlspecialchars($currentUser['first_name'] ?? 'User') ?>">
                        <?= strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="dropdown-header">
                            <div class="user-info">
                                <div class="user-name"><?= htmlspecialchars($currentUser['first_name'] ?? '') ?> <?= htmlspecialchars($currentUser['last_name'] ?? '') ?></div>
                                <div class="user-email"><?= htmlspecialchars($currentUser['email'] ?? '') ?></div>
                            </div>
                        </div>
                        <div class="dropdown-menu">
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i>
                                <span>Profile</span>
                            </a>
                            <a href="settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                <span>Settings</span>
                            </a>
                            <a href="referrals.php" class="dropdown-item">
                                <i class="fas fa-users"></i>
                                <span>Referrals</span>
                            </a>
                            <a href="transfer.php" class="dropdown-item">
                                <i class="fas fa-exchange-alt"></i>
                                <span>Transfer</span>
                            </a>
                            <a href="support.php" class="dropdown-item">
                                <i class="fas fa-headset"></i>
                                <span>Support</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="../logout.php" class="dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <main class="content-area">