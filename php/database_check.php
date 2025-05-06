<?php
// Yapılandırmayı yükle
require_once 'config.php';

// Hata raporlamasını etkinleştir
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Check</h1>";

// Veritabanı bağlantısını kontrol et
try {
    $pdo = getDbConnection();
    echo "<p style='color:green'>✅ MySQL veritabanına bağlantı başarılı!</p>";

    // Kullanıcı tablosunu kontrol et
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>✅ 'users' tablosu mevcut.</p>";
    } else {
        echo "<p style='color:red'>❌ 'users' tablosu bulunamadı! Oluşturulması gerekiyor.</p>";
        
        // Users tablosu oluşturulacak
        $pdo->exec("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                is_active BOOLEAN DEFAULT true,
                is_superuser BOOLEAN DEFAULT false,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p style='color:green'>✅ 'users' tablosu oluşturuldu.</p>";
    }
    
    // Quiz tablosunu kontrol et
    $stmt = $pdo->query("SHOW TABLES LIKE 'quiz_results'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>✅ 'quiz_results' tablosu mevcut.</p>";
    } else {
        echo "<p style='color:red'>❌ 'quiz_results' tablosu bulunamadı! Oluşturulması gerekiyor.</p>";
        
        // quiz_results tablosu oluşturulacak
        $pdo->exec("
            CREATE TABLE quiz_results (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                topic VARCHAR(100) NOT NULL,
                difficulty VARCHAR(20) NOT NULL,
                total_questions INT NOT NULL,
                correct_answers INT NOT NULL,
                score DECIMAL(5,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p style='color:green'>✅ 'quiz_results' tablosu oluşturuldu.</p>";
    }
    
    // Diğer tabloları kontrol et
    // quiz_questions
    $stmt = $pdo->query("SHOW TABLES LIKE 'quiz_questions'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>✅ 'quiz_questions' tablosu mevcut.</p>";
    } else {
        echo "<p style='color:red'>❌ 'quiz_questions' tablosu bulunamadı! Oluşturulması gerekiyor.</p>";
        
        $pdo->exec("
            CREATE TABLE quiz_questions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                quiz_id INT,
                question_text TEXT NOT NULL,
                correct_answer VARCHAR(1) NOT NULL,
                option_a TEXT NOT NULL,
                option_b TEXT NOT NULL,
                option_c TEXT NOT NULL,
                option_d TEXT NOT NULL,
                explanation TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (quiz_id) REFERENCES quiz_results(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p style='color:green'>✅ 'quiz_questions' tablosu oluşturuldu.</p>";
    }
    
    // chat_messages
    $stmt = $pdo->query("SHOW TABLES LIKE 'chat_messages'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>✅ 'chat_messages' tablosu mevcut.</p>";
    } else {
        echo "<p style='color:red'>❌ 'chat_messages' tablosu bulunamadı! Oluşturulması gerekiyor.</p>";
        
        $pdo->exec("
            CREATE TABLE chat_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                message TEXT NOT NULL,
                response TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p style='color:green'>✅ 'chat_messages' tablosu oluşturuldu.</p>";
    }
    
    // feedback
    $stmt = $pdo->query("SHOW TABLES LIKE 'feedback'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>✅ 'feedback' tablosu mevcut.</p>";
    } else {
        echo "<p style='color:red'>❌ 'feedback' tablosu bulunamadı! Oluşturulması gerekiyor.</p>";
        
        $pdo->exec("
            CREATE TABLE feedback (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                question_number INT NOT NULL,
                feedback_text TEXT NOT NULL,
                topic VARCHAR(100),
                level VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p style='color:green'>✅ 'feedback' tablosu oluşturuldu.</p>";
    }
    
    // study_plans
    $stmt = $pdo->query("SHOW TABLES LIKE 'study_plans'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>✅ 'study_plans' tablosu mevcut.</p>";
    } else {
        echo "<p style='color:red'>❌ 'study_plans' tablosu bulunamadı! Oluşturulması gerekiyor.</p>";
        
        $pdo->exec("
            CREATE TABLE study_plans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                quiz_id INT,
                plan_content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (quiz_id) REFERENCES quiz_results(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p style='color:green'>✅ 'study_plans' tablosu oluşturuldu.</p>";
    }
    
    echo "<h2>Tüm tablolar kontrol edildi ve gerekirse oluşturuldu.</h2>";
    echo "<p>MySQL veritabanınız tamamen hazır! Şimdi <a href='/thinkorbit-ai/php/register'>kayıt sayfasına</a> giderek bir hesap oluşturabilirsiniz.</p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Veritabanı hatası: " . $e->getMessage() . "</p>";
    
    echo "<h2>Hata Çözüm Önerileri:</h2>";
    echo "<ul>";
    echo "<li>MySQL servisinin çalıştığından emin olun.</li>";
    echo "<li>Config.php dosyasındaki veritabanı bilgilerinin doğru olduğundan emin olun.</li>";
    echo "<li>Veritabanı kullanıcısının doğru yetkilere sahip olduğundan emin olun.</li>";
    echo "<li>'thinkorbit' veritabanının oluşturulduğundan emin olun. MySQL'de <code>CREATE DATABASE thinkorbit;</code> komutunu çalıştırabilirsiniz.</li>";
    echo "</ul>";
    
    echo "<h3>Veritabanı Bilgileri:</h3>";
    echo "<pre>";
    echo "Host: " . $db_host . "\n";
    echo "Database Name: " . $db_name . "\n";
    echo "Username: " . $db_user . "\n";
    echo "Password: *****";
    echo "</pre>";
}
?> 