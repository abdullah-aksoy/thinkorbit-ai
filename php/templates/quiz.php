<?php 
// Set page title
$title = "Quiz";
// Include the header
include 'header.php';
?>

<div class="quiz-container">
    <h1 class="mb-4">Quiz: <?= htmlspecialchars($topic) ?></h1>
    <p class="mb-4">Zorluk seviyesi: <span class="badge"><?= htmlspecialchars($level) ?></span></p>
    
    <form action="/thinkorbit-ai/php/analyze" method="POST" id="quizForm">
        <!-- Hidden token field for authentication -->
        <input type="hidden" name="token" id="authToken" value="">
        
        <!-- Hidden fields to pass quiz metadata -->
        <input type="hidden" name="topic" value="<?= htmlspecialchars($topic) ?>">
        <input type="hidden" name="level" value="<?= htmlspecialchars($level) ?>">
        
        <div class="questions">
            <?php foreach ($questions as $index => $question): ?>
                <?php $questionNumber = $index + 1; ?>
                <div class="question-card" style="background-color: var(--background-light); border-radius: 8px; padding: 20px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);">
                    <h3 class="question-title" style="margin-bottom: 15px;">
                        <?= $questionNumber ?>. <?= htmlspecialchars($question['question']) ?>
                    </h3>
                    
                    <!-- Hidden fields to store question data -->
                    <input type="hidden" name="text_<?= $questionNumber ?>" value="<?= htmlspecialchars($question['question']) ?>">
                    <input type="hidden" name="c_<?= $questionNumber ?>" value="<?= htmlspecialchars($question['correct_answer']) ?>">
                    <input type="hidden" name="exp_<?= $questionNumber ?>" value="<?= htmlspecialchars($question['explanation']) ?>">
                    
                    <!-- Options -->
                    <div class="options" style="display: grid; gap: 12px;">
                        <?php foreach ($question['options'] as $letter => $optionText): ?>
                            <div class="option" style="display: flex; align-items: center;">
                                <input type="radio" 
                                       id="q_<?= $questionNumber ?>_<?= $letter ?>" 
                                       name="q_<?= $questionNumber ?>" 
                                       value="<?= $letter ?>" 
                                       required>
                                <label for="q_<?= $questionNumber ?>_<?= $letter ?>" 
                                       style="margin-left: 10px; width: 100%; cursor: pointer;">
                                    <span style="font-weight: bold; margin-right: 8px;"><?= $letter ?>)</span> 
                                    <?= htmlspecialchars($optionText) ?>
                                </label>
                                
                                <!-- Hidden field to store option text -->
                                <input type="hidden" name="opt_<?= $questionNumber ?>_<?= $letter ?>" 
                                       value="<?= htmlspecialchars($optionText) ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Feedback button -->
                    <div class="feedback-container" style="margin-top: 20px;">
                        <button type="button" 
                                class="feedback-btn" 
                                onclick="openFeedbackModal(<?= $questionNumber ?>)"
                                style="background: none; border: none; color: var(--accent-color); cursor: pointer; font-size: 0.9rem;">
                            <i class="fas fa-comment"></i> Bu soru hakkında geri bildirim gönder
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Submit button -->
        <div class="submit-container" style="margin-top: 30px; text-align: center;">
            <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 1.1rem;">
                <i class="fas fa-check-circle"></i> Sonuçları Göster
            </button>
        </div>
    </form>
</div>

<!-- Feedback Modal -->
<div id="feedbackModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7);">
    <div class="modal-content" style="background-color: var(--background-light); margin: 10% auto; padding: 30px; border-radius: 8px; max-width: 500px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);">
        <span class="close" onclick="closeFeedbackModal()" style="color: var(--text-muted); float: right; font-size: 28px; cursor: pointer;">&times;</span>
        <h2 style="margin-bottom: 20px;">Geri Bildirim</h2>
        <form id="feedbackForm">
            <input type="hidden" id="feedbackQuestionNumber" name="question_number" value="">
            <input type="hidden" name="topic" value="<?= htmlspecialchars($topic) ?>">
            <input type="hidden" name="level" value="<?= htmlspecialchars($level) ?>">
            <div class="form-group">
                <label for="feedbackText">Lütfen soru ile ilgili görüşlerinizi yazın:</label>
                <textarea id="feedbackText" name="feedback_text" class="form-control" rows="5" required
                          style="width: 100%; padding: 12px; background-color: var(--background-dark); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-light); margin-top: 10px;"></textarea>
            </div>
            <button type="button" onclick="submitFeedback()" class="btn btn-primary" style="margin-top: 20px;">
                <i class="fas fa-paper-plane"></i> Gönder
            </button>
        </form>
    </div>
</div>

<script>
// Sayfa yüklendiğinde form token'ını ayarla
document.addEventListener('DOMContentLoaded', function() {
    const quizForm = document.getElementById('quizForm');
    const authTokenField = document.getElementById('authToken');
    
    if (quizForm && authTokenField) {
        // LocalStorage'dan token'ı al
        const token = localStorage.getItem('access_token');
        if (token) {
            authTokenField.value = token;
            console.log('Token quiz formuna yerleştirildi');
        } else {
            console.log('Token bulunamadı');
        }
    }
});

// Feedback modal functions
function openFeedbackModal(questionNumber) {
    document.getElementById('feedbackModal').style.display = 'block';
    document.getElementById('feedbackQuestionNumber').value = questionNumber;
    document.getElementById('feedbackText').value = '';
}

function closeFeedbackModal() {
    document.getElementById('feedbackModal').style.display = 'none';
}

// When user clicks anywhere outside of the modal, close it
window.onclick = function(event) {
    const modal = document.getElementById('feedbackModal');
    if (event.target == modal) {
        closeFeedbackModal();
    }
}

// Submit feedback via AJAX
function submitFeedback() {
    const form = document.getElementById('feedbackForm');
    const questionNumber = document.getElementById('feedbackQuestionNumber').value;
    const feedbackText = document.getElementById('feedbackText').value.trim();
    
    if (!feedbackText) {
        alert('Lütfen geri bildirim yazın.');
        return;
    }
    
    // Create form data
    const formData = new FormData(form);
    
    // Add token if available
    const token = localStorage.getItem('access_token');
    if (token) {
        formData.append('token', token);
    }
    
    // Send AJAX request
    fetch('/thinkorbit-ai/php/feedback', {
        method: 'POST',
        headers: {
            'Authorization': token ? `Bearer ${token}` : ''
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Geri bildiriminiz için teşekkürler!');
            closeFeedbackModal();
        } else {
            alert('Hata: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Bir hata oluştu. Lütfen tekrar deneyin.');
    });
}
</script>

<?php 
// Include footer
include 'footer.php'; 
?> 