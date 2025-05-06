<?php 
// Include the header
include 'header.php';
?>

<h1 class="mb-4">ThinkOrbit AI Quiz Platformu</h1>

<div class="form-container">
    <h2><i class="fas fa-brain"></i> Yeni Quiz Oluştur</h2>
    <p class="mb-4">Dilediğiniz konuda, istediğiniz zorluk seviyesinde quiz soruları oluşturun.</p>
    
    <form action="<?php echo $base_path; ?>/generate" method="POST" id="quizForm">
        <!-- Hidden token field for authentication -->
        <input type="hidden" name="token" id="authToken" value="">
        
        <div class="form-group">
            <label for="topic">Konu</label>
            <input type="text" id="topic" name="topic" class="form-control" placeholder="Örn: Python Programlama, Türkiye Tarihi, Organik Kimya..." required>
        </div>
        
        <div class="form-group">
            <label for="level">Zorluk Seviyesi</label>
            <select id="level" name="level" class="form-select" required>
                <option value="">Seviye Seçin</option>
                <option value="kolay">Kolay</option>
                <option value="orta">Orta</option>
                <option value="zor">Zor</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="numQuestions">Soru Sayısı</label>
            <select id="numQuestions" name="numQuestions" class="form-select" required>
                <option value="">Soru Sayısı Seçin</option>
                <option value="5">5 Soru</option>
                <option value="10">10 Soru</option>
                <option value="15">15 Soru</option>
                <option value="20">20 Soru</option>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-magic"></i> Quiz Oluştur
        </button>
    </form>
</div>

<div class="features-container" style="margin-top: 40px; background-color: var(--background-light); border-radius: 8px; padding: 30px;">
    <h2 class="text-center mb-4"><i class="fas fa-star"></i> Özellikler</h2>
    
    <div class="feature-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
        <div class="feature-card" style="background-color: var(--background-dark); padding: 20px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);">
            <div class="feature-icon" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 15px;">
                <i class="fas fa-robot"></i>
            </div>
            <h3>Yapay Zeka Destekli</h3>
            <p>Gelişmiş yapay zeka algoritmalarıyla, istediğiniz konuda kaliteli sorular oluşturuyoruz.</p>
        </div>
        
        <div class="feature-card" style="background-color: var(--background-dark); padding: 20px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);">
            <div class="feature-icon" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 15px;">
                <i class="fas fa-chart-line"></i>
            </div>
            <h3>Detaylı Analiz</h3>
            <p>Quiz sonuçlarınızı analiz ederek, güçlü ve zayıf yönlerinizi belirliyoruz.</p>
        </div>
        
        <div class="feature-card" style="background-color: var(--background-dark); padding: 20px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);">
            <div class="feature-icon" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 15px;">
                <i class="fas fa-book"></i>
            </div>
            <h3>Kişiselleştirilmiş Çalışma Planı</h3>
            <p>Performansınıza göre özel çalışma planları oluşturarak öğrenme sürecinizi hızlandırıyoruz.</p>
        </div>
    </div>
</div>

<?php 
// Include footer
include 'footer.php'; 
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const quizForm = document.getElementById('quizForm');
        const authTokenField = document.getElementById('authToken');
        
        if (quizForm && authTokenField) {
            // LocalStorage'dan token'ı al
            const token = localStorage.getItem('access_token');
            if (token) {
                authTokenField.value = token;
            }
            
            // Form submit olayını dinle
            quizForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Form verilerini al
                const formData = new FormData(quizForm);
                
                // Token kontrolü
                const token = localStorage.getItem('access_token');
                if (token) {
                    formData.set('token', token);
                }
                
                // Form gönder
                fetch(quizForm.action, {
                    method: 'POST',
                    headers: {
                        'Authorization': token ? `Bearer ${token}` : '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html,application/json'
                    },
                    body: formData
                })
                .then(async response => {
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        // JSON yanıt
                        const jsonResponse = await response.json();
                        if (!response.ok) {
                            throw new Error(jsonResponse.message || 'Quiz oluşturma başarısız oldu');
                        }
                        return jsonResponse;
                    } else {
                        // HTML yanıt
                        if (!response.ok) {
                            const text = await response.text();
                            console.error('Server response:', text);
                            throw new Error('Quiz oluşturma başarısız oldu. Status: ' + response.status);
                        }
                        return response.text();
                    }
                })
                .then(result => {
                    if (typeof result === 'string') {
                        // HTML yanıt
                        document.documentElement.innerHTML = result;
                    } else {
                        // JSON yanıt
                        console.log('Quiz created:', result);
                        // Başarılı yanıt durumunda yönlendirme yapılabilir
                        window.location.href = result.redirect || quizForm.action;
                    }
                })
                .catch(error => {
                    console.error('Error details:', error);
                    // Hata mesajını göster
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger';
                    errorDiv.style.marginTop = '20px';
                    errorDiv.innerHTML = `
                        <strong>Hata!</strong><br>
                        ${error.message}<br>
                        <small>Lütfen tekrar deneyin veya sayfayı yenileyin.</small>
                    `;
                    quizForm.insertAdjacentElement('beforebegin', errorDiv);
                });
            });
        }
    });
</script> 