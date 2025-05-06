<!DOCTYPE html>
<html lang="tr" style="background-color: #121212;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThinkOrbit AI - <?= $title ?? 'Eğitim Asistanı' ?></title>
    <link rel="stylesheet" href="/thinkorbit-ai/php/templates/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="/thinkorbit-ai/php/templates/js/auth.js" defer></script>
    <style>
        :root {
            --primary-color: #2c3e50;        /* Koyu lacivert */
            --secondary-color: #34495e;      /* Daha açık lacivert */
            --accent-color: #3498db;         /* Mavi aksan */
            --accent-secondary: #2980b9;     /* Daha koyu mavi */
            --background-dark: #121212;      /* Koyu arka plan */
            --background-medium: #121212;    /* Orta arka plan */
            --background-light: #1e1e1e;     /* Açık arka plan */
            --text-light: #ecf0f1;           /* Açık metin */
            --text-muted: #bdc3c7;           /* Soluk metin */
            --border-color: #2d2d2d;         /* Kenarlık rengi */
            --hover-color: #2d2d2d;          /* Fare üstündeyken renk */
            --shadow-color: rgba(0, 0, 0, 0.3); /* Gölge rengi */
            --success-color: #27ae60;        /* Başarı rengi */
            --error-color: #e74c3c;          /* Hata rengi */
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            background-color: #121212;
            color: var(--text-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            background-color: var(--background-dark);
            border-right: 1px solid var(--border-color);
            width: 250px;
            padding: 20px;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 10;
        }
        
        .logo {
            display: flex;
            align-items: center;
            padding: 10px 0;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .logo-icon {
            font-size: 1.5rem;
            color: var(--accent-color);
            margin-right: 10px;
            position: relative;
        }
        
        .logo-icon i {
            position: relative;
        }
        
        .logo-icon i:after {
            content: '';
            position: absolute;
            width: 28px;
            height: 28px;
            left: -4px;
            top: -4px;
            border: 2px solid var(--accent-secondary);
            border-radius: 50%;
            border-left-color: transparent;
            border-bottom-color: transparent;
            transform: rotate(45deg);
        }
        
        .logo-text {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-light);
        }
        
        .new-chat-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 12px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
            margin-bottom: 20px;
            text-decoration: none;
        }
        
        .new-chat-btn:hover {
            background-color: var(--accent-secondary);
            transform: translateY(-2px);
        }
        
        .new-chat-btn i {
            margin-right: 8px;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px 50px;
            margin: 0 0 0 250px;
            width: calc(100% - 250px);
            background-color: var(--background-dark);
        }
        
        .container {
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
        }
        
        .chat-container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: #121212;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .chat-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .chat-header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: var(--text-light);
        }
        
        /* Form Styles */
        .form-container {
            background-color: var(--background-light);
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.9rem;
            margin-bottom: 8px;
            color: var(--text-light);
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            background-color: var(--background-dark);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-light);
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        .form-select {
            width: 100%;
            padding: 12px;
            background-color: var(--background-dark);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-light);
            font-size: 1rem;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23ecf0f1' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: var(--accent-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--accent-secondary);
            transform: translateY(-2px);
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        /* Alert Styles */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        
        .alert-success {
            background-color: rgba(39, 174, 96, 0.1);
            border: 1px solid #27ae60;
            color: #27ae60;
        }
        
        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-brain"></i>
            </div>
            <div class="logo-text">ThinkOrbit AI</div>
        </div>
        
        <a href="/thinkorbit-ai/php" class="new-chat-btn">
            <i class="fas fa-plus"></i> Yeni Quiz
        </a>
        
        <div id="userInfo" style="display: none;">
            <div style="padding: 10px; border-top: 1px solid var(--border-color);">
                <span class="username" style="color: var(--text-light);"></span>
                <button id="logoutBtn" class="btn" style="float: right; padding: 5px 10px;">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>
        
        <div id="guestActions">
            <div style="padding: 10px; border-top: 1px solid var(--border-color);">
                <a href="/thinkorbit-ai/php/login" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                    <i class="fas fa-sign-in-alt"></i> Giriş Yap
                </a>
                <a href="/thinkorbit-ai/php/register" class="btn" style="width: 100%; background: var(--background-light);">
                    <i class="fas fa-user-plus"></i> Kayıt Ol
                </a>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="container">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 