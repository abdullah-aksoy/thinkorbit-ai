<?php
// Gerekli dosyaları dahil et
require_once 'config.php';
require_once 'gemini.php';

// URI'yi al ve temizle
$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$request_uri = parse_url($request_uri, PHP_URL_PATH);
$base_path_pattern = preg_quote($base_path, '/');
$request_uri = preg_replace('/^' . $base_path_pattern . '/', '', $request_uri);
$request_uri = trim($request_uri, '/');

// HTTP metodu
$request_method = $_SERVER['REQUEST_METHOD'];

// Authorization header'ı kontrol et
$auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
$token = null;

if ($auth_header && preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
    $token = $matches[1];
    error_log("Found token in Authorization header: " . substr($token, 0, 10) . "...");
} elseif (isset($_POST['token'])) {
    $token = $_POST['token'];
    error_log("Found token in POST data: " . substr($token, 0, 10) . "...");
} elseif (isset($_COOKIE['access_token'])) {
    $token = $_COOKIE['access_token'];
    error_log("Found token in cookie: " . substr($token, 0, 10) . "...");
}

// Gemini API'yi başlat
$gemini = new GeminiAPI();

// Debug için
error_log("Request URI: " . $request_uri);
error_log("Request Method: " . $request_method);

// Yönlendirme (Basit Router)
switch ($request_uri) {
    case '':
    case '/':
        // Ana sayfa
        $html = renderTemplate('index');
        htmlResponse($html);
        break;
        
    case 'login':
        if ($request_method === 'GET') {
            // Login sayfası
            $html = renderTemplate('login');
            htmlResponse($html);
        } else {
            // Login işlemi
            handleLogin();
        }
        break;
        
    case 'register':
        if ($request_method === 'GET') {
            // Kayıt sayfası
            $html = renderTemplate('register');
            htmlResponse($html);
        } else {
            // Kayıt işlemi
            handleRegister();
        }
        break;
        
    case 'generate':
        try {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                     strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

            // Kullanıcı doğrulama
            $user = validateUserToken($token) ?? getCurrentUser();
            
            if (!$user) {
                throw new Exception("Oturum süresi dolmuş veya geçersiz. Lütfen tekrar giriş yapın.");
            }

            // Form verilerini doğrula
            $topic = $_POST['topic'] ?? '';
            $level = $_POST['level'] ?? 'orta';
            $numQuestions = intval($_POST['num_questions'] ?? 10);
            
            if (empty($topic)) {
                throw new Exception("Lütfen bir konu girin.");
            }

            if (!in_array($level, ['kolay', 'orta', 'zor'])) {
                throw new Exception("Geçersiz zorluk seviyesi.");
            }

            if (!in_array($numQuestions, [5, 10, 15, 20])) {
                throw new Exception("Geçersiz soru sayısı. Lütfen 5, 10, 15 veya 20 seçin.");
            }

            // Quiz oluştur
            $questions = $gemini->generateQuizQuestions($topic, $level, $numQuestions);
            
            if (empty($questions)) {
                error_log("Quiz üretilemedi: Boş soru listesi döndü");
                throw new Exception("Quiz üretilemedi. Lütfen tekrar deneyin.");
            }

            // Quiz sayfasını göster
            $html = renderTemplate('quiz', [
                'topic' => $topic,
                'level' => $level,
                'questions' => $questions,
                'user' => $user
            ]);
            
            htmlResponse($html);
        } catch (Exception $e) {
            error_log("Quiz oluşturma hatası: " . $e->getMessage());
            handleError($e, $isAjax);
        }
        break;
        
    case 'analyze':
        // Oturum kontrolü
        $user = getCurrentUser();
        
        // Token kontrolü
        if (!$user && isset($_POST['token'])) {
            // Token varsa doğrula
            $token = $_POST['token'];
            $payload = validateJwt($token);
            
            if ($payload) {
                // Kullanıcıyı token'dan al
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$payload['sub']]);
                $user = $stmt->fetch();
            }
        }
        
        if (!$user) {
            // Kullanıcı bulunamadı, hata göster
            htmlResponse(renderTemplate('error', [
                'error_message' => 'Oturum bilgisi bulunamadı. Lütfen giriş yapın veya misafir olarak devam edin.'
            ]), 401);
            return;
        }
        
        // Quiz analiz işlemi
        handleAnalyzeQuiz($gemini, $user);
        break;
        
    case 'feedback':
        // Oturum kontrolü
        $user = getCurrentUser();
        
        // Token kontrolü
        if (!$user && isset($_POST['token'])) {
            // Token varsa doğrula
            $token = $_POST['token'];
            $payload = validateJwt($token);
            
            if ($payload) {
                // Kullanıcıyı token'dan al
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$payload['sub']]);
                $user = $stmt->fetch();
            }
        }
        
        if (!$user) {
            jsonResponse(['status' => 'error', 'message' => 'Oturum bilgisi bulunamadı. Lütfen giriş yapın.'], 401);
            return;
        }
        
        // Geri bildirim işlemi
        handleFeedback($user);
        break;
        
    case 'chat/send':
        // Oturum kontrolü
        $user = getCurrentUser();
        
        // Token kontrolü
        if (!$user && isset($_POST['token'])) {
            // Token varsa doğrula
            $token = $_POST['token'];
            $payload = validateJwt($token);
            
            if ($payload) {
                // Kullanıcıyı token'dan al
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$payload['sub']]);
                $user = $stmt->fetch();
            }
        }
        
        if (!$user) {
            jsonResponse(['status' => 'error', 'message' => 'Oturum bilgisi bulunamadı. Lütfen giriş yapın.'], 401);
            return;
        }
        
        // Sohbet işlemi
        handleChat($gemini);
        break;
        
    case 'logout':
        // Session'ı temizle
        $_SESSION = [];
        session_destroy();
        
        // Cookie'yi sil
        setcookie('access_token', '', time() - 3600, '/', '', false, true);
        
        // Başarılı yanıt
        jsonResponse(['status' => 'success', 'message' => 'Başarıyla çıkış yapıldı']);
        break;
        
    case 'chat':
        // Chat sayfasını göster
        $user = getCurrentUser();
        $html = renderTemplate('chat', ['user' => $user]);
        htmlResponse($html);
        break;
        
    case 'refresh-token':
        try {
            // Token kontrolü
            $user = validateUserToken($token);
            
            if (!$user) {
                throw new Exception("Geçersiz veya süresi dolmuş token.");
            }
            
            // Yeni token oluştur
            $new_token = createJwt($user);
            
            jsonResponse([
                'status' => 'success',
                'token' => $new_token
            ]);
        } catch (Exception $e) {
            jsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 401);
        }
        break;
        
    default:
        // 404 Sayfası
        $html = renderTemplate('error', ['error_message' => 'Sayfa bulunamadı']);
        htmlResponse($html, 404);
        break;
}

/**
 * Giriş işlemi
 */
function handleLogin() {
    // Form verilerini al
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        jsonResponse([
            'status' => 'error',
            'message' => 'Kullanıcı adı ve şifre gereklidir'
        ], 400);
    }
    
    // Kullanıcıyı veritabanında ara
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    // Kullanıcı bulunamadı veya şifre yanlış
    if (!$user || !verifyPassword($password, $user['password_hash'])) {
        jsonResponse([
            'status' => 'error',
            'message' => 'Geçersiz kullanıcı adı veya şifre'
        ], 401);
    }
    
    // Son giriş zamanını güncelle
    $stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // JWT oluştur
    $token = createJwt($user);
    
    // Session'a kullanıcı bilgilerini kaydet
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['access_token'] = $token;
    
    // Cookie'ye token'ı kaydet (30 gün)
    setcookie('access_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
    
    // Başarılı yanıt
    jsonResponse([
        'status' => 'success',
        'access_token' => $token,
        'token_type' => 'bearer',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]
    ]);
}

/**
 * Kayıt işlemi
 */
function handleRegister() {
    // Debug için
    error_log("Register işlemi başladı");
    
    // Form verilerini al
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    error_log("Form verileri: username=$username, email=$email, password=****");
    
    if (empty($username) || empty($email) || empty($password)) {
        error_log("Eksik veri hatası");
        jsonResponse([
            'status' => 'error',
            'message' => 'Tüm alanlar gereklidir'
        ], 400);
    }
    
    try {
        $pdo = getDbConnection();
        
        // Kullanıcı adı veya e-posta zaten var mı kontrol et
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            error_log("Kullanıcı zaten var hatası");
            jsonResponse([
                'status' => 'error',
                'message' => 'Bu kullanıcı adı veya e-posta zaten kullanılıyor'
            ], 400);
        }
        
        // Şifreyi hash'le
        $password_hash = hashPassword($password);
        
        // Kullanıcıyı kaydet - RETURNING id kullanmadan
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $password_hash]);
        
        // Yeni eklenen kullanıcı ID'sini al
        $id = $pdo->lastInsertId();
        
        error_log("Kullanıcı başarıyla kaydedildi. ID: $id");
        
        // Başarılı yanıt
        jsonResponse([
            'status' => 'success',
            'message' => 'Kayıt başarılı',
            'user' => [
                'id' => $id,
                'username' => $username,
                'email' => $email
            ]
        ], 201);
    } catch (PDOException $e) {
        error_log("PDO hatası: " . $e->getMessage());
        jsonResponse([
            'status' => 'error',
            'message' => 'Veritabanı hatası: ' . $e->getMessage()
        ], 500);
    } catch (Exception $e) {
        error_log("Genel hata: " . $e->getMessage());
        jsonResponse([
            'status' => 'error',
            'message' => 'Bir hata oluştu: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Quiz analiz işlemi
 */
function handleAnalyzeQuiz($gemini, $user) {
    // Form verilerini al
    $form_data = $_POST;
    
    // Debug için form verilerini yazdır
    error_log("Analyze Form Data: " . print_r($form_data, true));
    
    // Soru indekslerini bul
    $question_indices = [];
    foreach (array_keys($form_data) as $key) {
        if (preg_match('/^q_(\d+)$/', $key, $matches)) {
            $question_indices[] = $matches[1];
        }
    }
    
    error_log("Question indices: " . implode(', ', $question_indices));
    
    if (empty($question_indices)) {
        htmlResponse(renderTemplate('error', [
            'error_message' => 'Soru verileri bulunamadı. Lütfen tüm soruları cevaplayın.'
        ]), 400);
        return;
    }
    
    // Soru sayısını tespit et
    $total_questions = count($question_indices);
    error_log("Total questions: $total_questions");
    
    // Soruları işle
    $correct = 0;
    $question_data = [];
    $correct_questions = [];
    $wrong_questions = [];
    
    foreach ($question_indices as $idx) {
        $selected = isset($form_data['q_' . $idx]) ? trim($form_data['q_' . $idx]) : '';
        $question_text = isset($form_data['text_' . $idx]) ? trim($form_data['text_' . $idx]) : '';
        $correct_answer = isset($form_data['c_' . $idx]) ? trim($form_data['c_' . $idx]) : '';
        
        error_log("Question $idx: selected='$selected', correct='$correct_answer', text='$question_text'");
        
        // Seçenekleri topla
        $options = [
            'A' => isset($form_data['opt_' . $idx . '_A']) ? trim($form_data['opt_' . $idx . '_A']) : '',
            'B' => isset($form_data['opt_' . $idx . '_B']) ? trim($form_data['opt_' . $idx . '_B']) : '',
            'C' => isset($form_data['opt_' . $idx . '_C']) ? trim($form_data['opt_' . $idx . '_C']) : '',
            'D' => isset($form_data['opt_' . $idx . '_D']) ? trim($form_data['opt_' . $idx . '_D']) : ''
        ];
        
        // Boş cevapları ve eksik bilgileri kontrol et
        if (empty($selected)) {
            $selected = 'X'; // Cevap verilmemiş
            error_log("Question $idx: No answer selected");
        }
        
        if (empty($correct_answer)) {
            $correct_answer = 'A'; // Varsayılan doğru cevap
            error_log("Question $idx: No correct answer provided, defaulting to A");
        }
        
        // Doğru mu kontrol et - İki değeri de büyük harfe çevirerek karşılaştır
        $is_correct = strtoupper($selected) === strtoupper($correct_answer);
        error_log("Question $idx: is_correct=" . ($is_correct ? "true" : "false"));
        
        if ($is_correct) {
            $correct++;
            $correct_questions[] = $question_text;
            error_log("Question $idx: Correct answer");
        } else {
            $wrong_questions[] = $question_text;
            error_log("Question $idx: Wrong answer");
        }
        
        // Açıklamayı al
        $explanation = isset($form_data['exp_' . $idx]) ? $form_data['exp_' . $idx] : '';
        
        // Soru verisini ekle
        $question_data[] = [
            'question' => $question_text,
            'selected' => $selected,
            'correct' => $correct_answer,
            'is_correct' => $is_correct,
            'options' => $options,
            'explanation' => $explanation
        ];
    }
    
    // İstatistikleri hesapla
    $total = count($question_data);
    $wrong = $total - $correct;
    $percent = ($total > 0) ? round(($correct / $total) * 100, 2) : 0;
    $topic = $form_data['topic'] ?? '';
    $level = $form_data['level'] ?? '';
    
    error_log("Quiz results: total=$total, correct=$correct, wrong=$wrong, percent=$percent");
    
    // Çalışma planı oluştur
    $study_plan = $gemini->analyzeQuizResults($topic, $correct_questions, $wrong_questions, $total);
    
    // Sonuç sayfasını göster
    $html = renderTemplate('result', [
        'correct' => $correct,
        'wrong' => $wrong,
        'percent' => $percent,
        'total' => $total,
        'questions' => $question_data,
        'topic' => $topic,
        'level' => $level,
        'study_plan' => $study_plan,
        'user' => $user
    ]);
    
    htmlResponse($html);
}

/**
 * Geri bildirim işlemi
 */
function handleFeedback($user) {
    // Form verilerini al
    $question_number = $_POST['question_number'] ?? '';
    $feedback_text = $_POST['feedback_text'] ?? '';
    $topic = $_POST['topic'] ?? 'Belirtilmemiş';
    $level = $_POST['level'] ?? 'Belirtilmemiş';
    
    if (empty(trim($feedback_text))) {
        jsonResponse([
            'status' => 'error',
            'message' => 'Geri bildirim boş olamaz'
        ], 400);
    }
    
    try {
        $pdo = getDbConnection();
        
        // Geri bildirimi veritabanına kaydet
        $stmt = $pdo->prepare("
            INSERT INTO feedback (user_id, question_number, feedback_text, topic, level)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user['id'],
            $question_number,
            $feedback_text,
            $topic,
            $level
        ]);
        
        // Başarılı yanıt
        jsonResponse([
            'status' => 'success',
            'message' => 'Geri bildiriminiz için teşekkürler!'
        ]);
        
    } catch (PDOException $e) {
        error_log("Geri bildirim kaydetme hatası: " . $e->getMessage());
        jsonResponse([
            'status' => 'error',
            'message' => 'Geri bildirim kaydedilirken bir hata oluştu'
        ], 500);
    }
}

/**
 * Sohbet işlemi
 */
function handleChat($gemini) {
    $message = $_POST['message'] ?? '';
    
    if (empty(trim($message))) {
        jsonResponse([
            'status' => 'error',
            'message' => 'Mesaj boş olamaz'
        ], 400);
    }
    
    // Gemini API ile sohbet
    $response = $gemini->chatWithAI($message);
    
    // Başarılı yanıt
    jsonResponse([
        'status' => 'success',
        'response' => $response
    ]);
}

// Token doğrulama fonksiyonu
function validateUserToken($token) {
    if (empty($token)) {
        return null;
    }

    try {
        $payload = validateJwt($token);
        if (!$payload) {
            return null;
        }

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$payload['sub']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        return null;
    }
}

// Genel hata yakalama fonksiyonu
function handleError($e, $isAjax = false) {
    $message = $e->getMessage();
    $code = 500; // Default to internal server error
    
    // Common error messages and appropriate status codes
    if (strpos($message, "izin verilmiyor") !== false || 
        strpos($message, "yetkiniz yok") !== false || 
        strpos($message, "yetkisiz") !== false) {
        $code = 403;
    } else if (strpos($message, "bulunamadı") !== false) {
        $code = 404;
    } else if (strpos($message, "geçersiz") !== false) {
        $code = 400;
    } else {
        $code = 400; // Default to 400 for client errors
    }
    
    error_log("Error occurred: " . $message);
    error_log("Status code: " . $code);
    error_log("Stack trace: " . $e->getTraceAsString());

    if ($isAjax) {
        jsonResponse([
            'status' => 'error',
            'message' => $message,
            'code' => $code
        ], $code);
    } else {
        htmlResponse(renderTemplate('error', [
            'error_message' => $message,
            'error_code' => $code
        ]), $code);
    }
} 