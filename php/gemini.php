<?php
// Gemini API entegrasyonu için sınıf
class GeminiAPI {
    private $api_key;
    
    public function __construct() {
        // API anahtarını doğrudan ayarla (gerçek uygulamada .env dosyasından çekilmeli)
        $this->api_key = "AIzaSyAhkVO9Mo96-7tCAkIeIaR3l8s-otnh9rY";
    }
    
    /**
     * Gemini API'ye istek gönderen genel fonksiyon
     */
    private function generateContent($prompt) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $this->api_key;
        
        $data = [
            "contents" => [
                ["parts" => [["text" => $prompt]]]
            ],
            "generationConfig" => [
                "temperature" => 0.9,
                "topP" => 1,
                "topK" => 1,
                "maxOutputTokens" => 2048
            ],
            "safetySettings" => [
                ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"],
                ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"],
                ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"],
                ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"]
            ]
        ];
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === FALSE) {
            error_log("Gemini API error: Request failed");
            return null;
        }
        
        $result = json_decode($response, true);
        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            error_log("Gemini API error: Unexpected response format");
            return null;
        }
        
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }
    
    /**
     * Quiz soruları üretme
     */
    public function generateQuizQuestions($topic, $level, $num_questions) {
        $prompt = "Lütfen {$level} düzeyinde {$topic} konusunda {$num_questions} adet çoktan seçmeli soru üret.
Her soru için tam olarak aşağıdaki formatı kullan:

1. Soru metni buraya yazılacak?
A) Birinci şık
B) İkinci şık
C) Üçüncü şık
D) Dördüncü şık
Doğru Cevap: B
Açıklama: Sorunun detaylı çözüm açıklaması buraya yazılacak.

2. İkinci soru metni?
A) Şık
B) Şık
C) Şık
D) Şık
Doğru Cevap: A
Açıklama: İkinci sorunun detaylı çözüm açıklaması buraya yazılacak.

Lütfen her soruyu bu formatta yaz ve aralarında boş satır bırak. Her soru için mutlaka bir açıklama ekle.";
        
        $response = $this->generateContent($prompt);
        if (!$response) {
            error_log("Gemini API yanıt vermedi veya boş yanıt döndü");
            return [];
        }
        
        $questions = $this->parseQuestionsOutput($response);
        
        // Soruları veritabanına kaydet
        if (!empty($questions)) {
            try {
                $pdo = getDbConnection();
                
                // Quiz sonucunu kaydet
                $stmt = $pdo->prepare("
                    INSERT INTO quiz_results (user_id, topic, difficulty, total_questions, correct_answers, score)
                    VALUES (?, ?, ?, ?, 0, 0.00)
                ");
                
                $user = getCurrentUser();
                $user_id = $user ? $user['id'] : null;
                
                $stmt->execute([$user_id, $topic, $level, $num_questions]);
                $quiz_id = $pdo->lastInsertId();
                
                // Her soruyu kaydet
                foreach ($questions as $question) {
                    $stmt = $pdo->prepare("
                        INSERT INTO quiz_questions (
                            quiz_id, question_text, correct_answer,
                            option_a, option_b, option_c, option_d,
                            explanation
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $quiz_id,
                        $question['question'],
                        $question['correct_answer'],
                        $question['options']['A'],
                        $question['options']['B'],
                        $question['options']['C'],
                        $question['options']['D'],
                        $question['explanation']
                    ]);
                }
            } catch (PDOException $e) {
                error_log("Quiz kaydetme hatası: " . $e->getMessage());
                // Veritabanı hatası olsa bile soruları döndür, 
                // böylece API yine de çalışacak ve kullanıcı quizi görebilecek
            }
        }
        
        // Soruları döndür, boş ise bir hata mesajı log'la
        if (empty($questions)) {
            error_log("Sorular ayrıştırılamadı veya boş soru listesi döndü");
        }
        
        return $questions;
    }
    
    /**
     * Gemini yanıtından soruları ayrıştırma
     */
    private function parseQuestionsOutput($text) {
        $questions = [];
        $blocks = explode("\n\n", trim($text));
        
        foreach ($blocks as $block) {
            $lines = explode("\n", trim($block));
            if (count($lines) < 6) { // En az 6 satır olmalı
                continue;
            }
            
            // Soru metnini çıkar
            $question_line = trim($lines[0]);
            $question_text = preg_replace('/^\d+\.\s*/', '', $question_line);
            
            $options = [];
            $correct_option = '';
            $explanation = '';
            
            // Şıkları, doğru cevabı ve açıklamayı ayır
            foreach (array_slice($lines, 1) as $line) {
                if (preg_match('/^([A-D])\)\s*(.+)$/', $line, $matches)) {
                    $options[$matches[1]] = $matches[2];
                } 
                elseif (preg_match('/Doğru Cevap:\s*([A-D])/', $line, $matches)) {
                    $correct_option = $matches[1];
                }
                elseif (preg_match('/Açıklama:\s*(.+)/', $line, $matches)) {
                    $explanation = $matches[1];
                }
                elseif (!empty($explanation)) {
                    // Açıklama birden fazla satır olabilir
                    $explanation .= ' ' . $line;
                }
            }
            
            // Doğru cevap ve seçenekler kontrol ediliyor
            if ($question_text && !empty($options) && isset($options[$correct_option])) {
                $questions[] = [
                    'question' => $question_text,
                    'options' => $options,
                    'correct_answer' => $correct_option,
                    'explanation' => $explanation
                ];
            }
        }
        
        return $questions;
    }
    
    /**
     * Quiz sonuçlarına göre çalışma planı oluşturma
     */
    public function analyzeQuizResults($topic, $correct_answers, $wrong_answers, $total_questions) {
        $warning = '';
        if ($total_questions < 20) {
            $warning = "Not: Daha sağlıklı bir çalışma planı için en az 20 soru çözmenizi öneririz.";
        }
        
        $wrong_answers_text = implode(', ', $wrong_answers);
        
        $prompt = "Lütfen aşağıdaki quiz sonuçlarını analiz ederek kişiselleştirilmiş bir çalışma planı oluştur.
Yanıtını düz metin formatında ver ve emojiler kullan.
Her bölüm başlığı için uygun bir emoji seç.

Konu: {$topic}
Toplam Soru Sayısı: {$total_questions}
Doğru Cevaplanan Sorular: " . count($correct_answers) . "
Yanlış Cevaplanan Sorular: " . count($wrong_answers) . "

Yanlış Yapılan Sorular:
{$wrong_answers_text}

Lütfen:
1. Sonuçların kısa bir analizini yap
2. Zayıf noktaları belirt
3. Gelişim için özel öneriler sun
4. Günlük çalışma planı oluştur
5. Önerilen kaynaklar listele

Yanıtını aşağıdaki formatta ver:

📊 1. Sonuçların Analizi
[Detaylı analiz metni]

❗ 2. Zayıf Noktalar
 [Konu başlığı]: [Detaylı açıklama]

💡 3. Gelişim Önerileri
 [Öneri metni]

📅 4. Günlük Çalışma Planı
 [Plan detayları]

📚 5. Önerilen Kaynaklar
 [Kaynak listesi]

Her bölümü yeni satırda başlat ve uygun emojilerle destekle.
Pozitif ve motive edici bir dil kullan.";
        
        $response = $this->generateContent($prompt);
        
        $result = [
            'success' => !empty($response),
            'plan' => $response ?: 'Çalışma planı oluşturulamadı.',
            'warning' => $warning
        ];
        
        // Çalışma planını veritabanına kaydet
        if ($result['success']) {
            try {
                $pdo = getDbConnection();
                
                // En son quiz_id'yi bul
                $stmt = $pdo->prepare("
                    SELECT id FROM quiz_results 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute();
                $quiz = $stmt->fetch();
                
                if ($quiz) {
                    $stmt = $pdo->prepare("
                        INSERT INTO study_plans (quiz_id, plan_content)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$quiz['id'], $response]);
                }
            } catch (PDOException $e) {
                error_log("Çalışma planı kaydetme hatası: " . $e->getMessage());
            }
        }
        
        return $result;
    }
    
    /**
     * Yapay zeka ile sohbet
     */
    public function chatWithAI($user_message) {
        if (empty(trim($user_message))) {
            return "Üzgünüm, boş bir mesaj aldım. Lütfen bir şeyler yazın. 😊";
        }
        
        $prompt = "Kullanıcı mesajı: {$user_message}

Lütfen ThinkOrbit AI eğitim asistanı olarak, eğitimle ilgili konularda yardımcı ol.
Cevabın eğitici, yardımcı ve arkadaşça olsun.

Yanıt verirken:
Önemli noktaları vurgulamak için uygun emojiler ekle fakat çok kullanma(📚, 💡, ✨, 🎯, 📝 gibi)
Madde işaretleri için emoji kullan fakat çok kullanma(• yerine ➡️ veya 📌)
Pozitif ve motive edici bir dil kullan
Paragraflar arasında yeterli boşluk bırak
Önemli kelimeleri veya cümleleri emojilerle vurgula";
        
        $response = $this->generateContent($prompt);
        $message = $response ?: "Üzgünüm, şu anda yanıt veremiyorum. Lütfen tekrar deneyin. 😔";
        
        // Sohbet mesajını veritabanına kaydet
        try {
            $pdo = getDbConnection();
            $user = getCurrentUser();
            
            if ($user) {
                $stmt = $pdo->prepare("
                    INSERT INTO chat_messages (user_id, message, response)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$user['id'], $user_message, $message]);
            }
        } catch (PDOException $e) {
            error_log("Sohbet mesajı kaydetme hatası: " . $e->getMessage());
        }
        
        return $message;
    }
} 