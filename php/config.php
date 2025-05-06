<?php
// Veritabanı bağlantı bilgileri
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'thinkorbit';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';

// JWT ayarları
$jwt_secret = getenv('JWT_SECRET') ?: 'b6977034537ca13eb4f9aad8e521782d20225e7be98dda5e28da2eadc08cbb2c';
$jwt_expiration = 30 * 60; // 30 dakika

// Uygulama yolu
$base_path = '/thinkorbit-ai/php';

// Hata raporlaması
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Oturum başlat
session_start();

// Veritabanı bağlantısını oluştur
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    global $db_host, $db_name, $db_user, $db_pass;
    
    try {
        error_log("Veritabanı bağlantısı kuruluyor: host=$db_host, dbname=$db_name");
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        error_log("Veritabanı bağlantısı başarılı");
        return $pdo;
    } catch (PDOException $e) {
        error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
        error_log("DSN: $dsn");
        throw new Exception("Veritabanına bağlanırken bir hata oluştu. Lütfen daha sonra tekrar deneyin.");
    }
}

// Şifre doğrulama fonksiyonu
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Şifre hash fonksiyonu
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// JWT oluşturma fonksiyonu
function createJwt($user) {
    global $jwt_secret, $jwt_expiration;
    
    $issued_at = time();
    $expiration = $issued_at + $jwt_expiration;
    
    $payload = [
        'iat' => $issued_at,
        'exp' => $expiration,
        'sub' => $user['username']
    ];
    
    // JWT'nin üç bölümünü oluştur
    $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload = base64_encode(json_encode($payload));
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", $jwt_secret, true));
    
    return "$header.$payload.$signature";
}

// JWT doğrulama fonksiyonu
function validateJwt($token) {
    global $jwt_secret;
    
    // Token formatını kontrol et
    $parts = explode('.', $token);
    if (count($parts) != 3) {
        return false;
    }
    
    list($header, $payload, $signature) = $parts;
    
    // İmzayı doğrula
    $valid_signature = base64_encode(hash_hmac('sha256', "$header.$payload", $jwt_secret, true));
    if ($signature !== $valid_signature) {
        return false;
    }
    
    // Payload'ı decode et
    $payload_data = json_decode(base64_decode($payload), true);
    
    // Süreyi kontrol et
    if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
        return false;
    }
    
    return $payload_data;
}

// Kullanıcı kimlik doğrulaması için gerekli fonksiyon
function getCurrentUser() {
    error_log("getCurrentUser called");
    
    // Bearer token'ı kontrol et
    $headers = getallheaders();
    $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        $token = $matches[1];
        error_log("Found Bearer token: " . substr($token, 0, 10) . "...");
        $payload = validateJwt($token);
        
        if ($payload) {
            error_log("Token validated for user: " . $payload['sub']);
            // Kullanıcıyı veritabanından al
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$payload['sub']]);
            $user = $stmt->fetch();
            
            if ($user) {
                error_log("User found in database: " . $user['username']);
                return $user;
            }
        }
    }
    
    // Form'dan token kontrol et
    if (isset($_POST['token'])) {
        $token = $_POST['token'];
        error_log("Found token in POST data: " . substr($token, 0, 10) . "...");
        $payload = validateJwt($token);
        
        if ($payload) {
            error_log("POST token validated for user: " . $payload['sub']);
            // Kullanıcıyı veritabanından al
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$payload['sub']]);
            $user = $stmt->fetch();
            
            if ($user) {
                error_log("User found in database: " . $user['username']);
                return $user;
            }
        }
    }
    
    // Cookie'den token kontrol et
    if (isset($_COOKIE['access_token'])) {
        $token = $_COOKIE['access_token'];
        error_log("Found token in cookie: " . substr($token, 0, 10) . "...");
        $payload = validateJwt($token);
        
        if ($payload) {
            error_log("Cookie token validated for user: " . $payload['sub']);
            // Kullanıcıyı veritabanından al
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$payload['sub']]);
            $user = $stmt->fetch();
            
            if ($user) {
                error_log("User found in database: " . $user['username']);
                return $user;
            }
        }
    }
    
    // Session kontrol et
    if (isset($_SESSION['user_id'])) {
        error_log("Found user_id in session: " . $_SESSION['user_id']);
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            error_log("User found in database from session: " . $user['username']);
            return $user;
        }
    }
    
    error_log("No valid authentication found");
    return null;
}

// URL yönlendirme için basit fonksiyon
function redirect($path) {
    global $base_path;
    $url = $base_path . $path;
    header("Location: $url");
    exit;
}

// JSON yanıt döndürme
function jsonResponse($data, $status = 200) {
    // Ensure headers haven't been sent yet
    if (!headers_sent()) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
    }
    
    if (is_array($data) && !isset($data['timestamp'])) {
        $data['timestamp'] = time();
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// HTML template oluşturma
function renderTemplate($template, $data = []) {
    extract($data);
    ob_start();
    include __DIR__ . "/templates/$template.php";
    return ob_get_clean();
}

// HTML yanıtı
function htmlResponse($html, $status = 200) {
    // Ensure headers haven't been sent yet
    if (!headers_sent()) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Content-Type: text/html; charset=utf-8');
        http_response_code($status);
    }
    
    echo $html;
    exit;
} 