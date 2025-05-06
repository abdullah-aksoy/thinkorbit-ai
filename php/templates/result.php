<?php 
// Set page title
$title = "Quiz Sonuçları";
// Include the header
include 'header.php';
?>

<div class="results-container">
    <h1 class="mb-4">Quiz Sonuçları: <?= htmlspecialchars($topic) ?></h1>
    <p>Zorluk seviyesi: <span class="badge"><?= htmlspecialchars($level) ?></span></p>
    
    <!-- Results summary -->
    <div class="results-summary" style="background-color: var(--background-light); border-radius: 8px; padding: 30px; margin-bottom: 30px; display: flex; justify-content: space-between; flex-wrap: wrap;">
        <div class="result-card" style="text-align: center; flex: 1; min-width: 120px; margin: 10px; padding: 15px; background-color: var(--background-dark); border-radius: 8px;">
            <div class="result-value" style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;"><?= $total ?></div>
            <div class="result-label">Toplam Soru</div>
        </div>
        
        <div class="result-card" style="text-align: center; flex: 1; min-width: 120px; margin: 10px; padding: 15px; background-color: var(--background-dark); border-radius: 8px; color: var(--success-color);">
            <div class="result-value" style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;"><?= $correct ?></div>
            <div class="result-label">Doğru</div>
        </div>
        
        <div class="result-card" style="text-align: center; flex: 1; min-width: 120px; margin: 10px; padding: 15px; background-color: var(--background-dark); border-radius: 8px; color: var(--error-color);">
            <div class="result-value" style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;"><?= $wrong ?></div>
            <div class="result-label">Yanlış</div>
        </div>
        
        <div class="result-card" style="text-align: center; flex: 1; min-width: 120px; margin: 10px; padding: 15px; background-color: var(--background-dark); border-radius: 8px; color: <?= $percent >= 70 ? 'var(--success-color)' : ($percent >= 50 ? '#f39c12' : 'var(--error-color)') ?>;">
            <div class="result-value" style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;">%<?= $percent ?></div>
            <div class="result-label">Başarı</div>
        </div>
    </div>
    
    <!-- Study plan -->
    <div class="study-plan" style="background-color: var(--background-light); border-radius: 8px; padding: 30px; margin-bottom: 30px;">
        <h2 style="margin-bottom: 20px;"><i class="fas fa-book"></i> Kişiselleştirilmiş Çalışma Planı</h2>
        
        <?php if (!empty($study_plan['warning'])): ?>
            <div class="alert alert-warning" style="margin-bottom: 20px; padding: 12px; border-radius: 6px; background-color: rgba(243, 156, 18, 0.1); border: 1px solid #f39c12; color: #f39c12;">
                <?= $study_plan['warning'] ?>
            </div>
        <?php endif; ?>
        
        <div class="plan-content" style="white-space: pre-line; line-height: 1.7;">
            <?= $study_plan['plan'] ?>
        </div>
    </div>
    
    <!-- Detailed results -->
    <div class="detailed-results" style="background-color: var(--background-light); border-radius: 8px; padding: 30px;">
        <h2 style="margin-bottom: 20px;"><i class="fas fa-list-check"></i> Detaylı Sonuçlar</h2>
        
        <?php foreach ($questions as $index => $question): ?>
            <div class="question-result" style="margin-bottom: 30px; padding: 20px; background-color: var(--background-dark); border-radius: 8px; border-left: 5px solid <?= $question['is_correct'] ? 'var(--success-color)' : 'var(--error-color)' ?>;">
                <h3 style="margin-bottom: 15px;">
                    <?= ($index + 1) ?>. <?= htmlspecialchars($question['question']) ?>
                </h3>
                
                <!-- Options -->
                <div class="options" style="margin-bottom: 15px;">
                    <?php foreach ($question['options'] as $letter => $text): ?>
                        <?php 
                            // Kullanıcının seçtiği ve doğru cevap
                            $userSelected = strtoupper($question['selected']);
                            $correctAnswer = strtoupper($question['correct']);
                            $currentLetter = strtoupper($letter);
                            
                            // Farklı durumlara göre stil belirleme
                            $bgStyle = '';
                            $borderStyle = '';
                            $iconHtml = '';
                            
                            if ($currentLetter === $correctAnswer && $currentLetter === $userSelected) {
                                // Doğru cevap ve kullanıcı bunu seçmiş
                                $bgStyle = 'background-color: rgba(39, 174, 96, 0.1);';
                                $borderStyle = 'border: 1px solid var(--success-color);';
                                $iconHtml = '<span style="margin-left: auto; color: var(--success-color);"><i class="fas fa-check"></i></span>';
                            } elseif ($currentLetter === $correctAnswer) {
                                // Doğru cevap ama kullanıcı seçmemiş
                                $bgStyle = 'background-color: rgba(39, 174, 96, 0.1);';
                                $borderStyle = 'border: 1px solid var(--success-color);';
                                $iconHtml = '<span style="margin-left: auto; color: var(--success-color);"><i class="fas fa-check"></i></span>';
                            } elseif ($currentLetter === $userSelected) {
                                // Yanlış cevap ve kullanıcı bunu seçmiş
                                $bgStyle = 'background-color: rgba(231, 76, 60, 0.1);';
                                $borderStyle = 'border: 1px solid var(--error-color);';
                                $iconHtml = '<span style="margin-left: auto; color: var(--error-color);"><i class="fas fa-times"></i></span>';
                            }
                        ?>
                        <div class="option" style="padding: 10px; margin-bottom: 5px; border-radius: 6px; display: flex; align-items: center; <?= $bgStyle . $borderStyle ?>">
                            <span style="font-weight: bold; margin-right: 10px; min-width: 24px;"><?= $letter ?>)</span>
                            <?= htmlspecialchars($text) ?>
                            <?= $iconHtml ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($question['selected'] === 'X'): ?>
                        <div class="unanswered" style="color: var(--error-color); margin-top: 10px;">
                            <i class="fas fa-exclamation-triangle"></i> Bu soruyu yanıtlamadınız.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Explanation -->
                <?php if (!empty($question['explanation'])): ?>
                    <div class="explanation" style="margin-top: 15px; padding: 15px; background-color: var(--background-medium); border-radius: 6px; border-left: 3px solid var(--accent-color);">
                        <h4 style="margin-bottom: 10px; color: var(--accent-color);"><i class="fas fa-lightbulb"></i> Açıklama</h4>
                        <p><?= htmlspecialchars($question['explanation']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Action buttons -->
    <div class="action-buttons" style="margin-top: 30px; display: flex; justify-content: space-between;">
        <a href="/thinkorbit-ai/php" class="btn btn-primary" style="padding: 12px 24px;">
            <i class="fas fa-home"></i> Ana Sayfaya Dön
        </a>
        <a href="/thinkorbit-ai/php/chat" class="btn btn-primary" style="padding: 12px 24px;">
            <i class="fas fa-comments"></i> AI Asistan ile Sohbet Et
        </a>
    </div>
</div>

<?php 
// Include footer
include 'footer.php'; 
?> 