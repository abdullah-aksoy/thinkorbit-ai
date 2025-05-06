<?php 
// Set page title
$title = "Hata";
// Include the header
include 'header.php';
?>

<div class="error-container" style="text-align: center; padding: 50px 20px;">
    <div class="error-icon" style="font-size: 5rem; color: var(--error-color); margin-bottom: 20px;">
        <i class="fas fa-exclamation-circle"></i>
    </div>
    
    <h1 style="margin-bottom: 20px;">Bir Hata Oluştu</h1>
    
    <div class="error-message" style="padding: 20px; background-color: rgba(231, 76, 60, 0.1); border-radius: 8px; border: 1px solid var(--error-color); max-width: 600px; margin: 0 auto 30px auto;">
        <p><?= htmlspecialchars($error_message ?? 'Beklenmeyen bir hata oluştu. Lütfen tekrar deneyin.') ?></p>
    </div>
    
    <div class="actions">
        <a href="/php" class="btn btn-primary" style="margin-right: 15px;">
            <i class="fas fa-home"></i> Ana Sayfaya Dön
        </a>
        <button onclick="window.history.back()" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Geri Dön
        </button>
    </div>
</div>

<?php 
// Include footer
include 'footer.php'; 
?> 