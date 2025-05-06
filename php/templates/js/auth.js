// Token yönetimi
class TokenManager {
    constructor() {
        this.tokenKey = 'access_token';
        this.usernameKey = 'username';
        this.tokenRefreshThreshold = 5 * 60 * 1000; // 5 dakika
        this.setupTokenRefresh();
    }

    getToken() {
        return localStorage.getItem(this.tokenKey);
    }

    setToken(token) {
        localStorage.setItem(this.tokenKey, token);
    }

    getUsername() {
        return localStorage.getItem(this.usernameKey);
    }

    setUsername(username) {
        localStorage.setItem(this.usernameKey, username);
    }

    removeToken() {
        localStorage.removeItem(this.tokenKey);
        localStorage.removeItem(this.usernameKey);
    }

    isTokenExpired(token) {
        try {
            const payload = JSON.parse(atob(token.split('.')[1]));
            const expiryTime = payload.exp * 1000; // Convert to milliseconds
            return Date.now() >= expiryTime - this.tokenRefreshThreshold;
        } catch (e) {
            console.error('Token parsing error:', e);
            return true;
        }
    }

    async refreshToken() {
        try {
            const response = await fetch('/thinkorbit-ai/php/refresh-token', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.getToken()}`
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.token) {
                    this.setToken(data.token);
                    return true;
                }
            }
            return false;
        } catch (e) {
            console.error('Token refresh error:', e);
            return false;
        }
    }

    setupTokenRefresh() {
        setInterval(async () => {
            const token = this.getToken();
            if (token && this.isTokenExpired(token)) {
                const success = await this.refreshToken();
                if (!success) {
                    this.removeToken();
                    window.location.href = '/thinkorbit-ai/php/login';
                }
            }
        }, 60000); // Her dakika kontrol et
    }
}

// Form validasyonu
class FormValidator {
    constructor(form) {
        this.form = form;
        this.setupValidation();
    }

    setupValidation() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validateForm()) {
                e.preventDefault();
            }
        });
    }

    validateForm() {
        let isValid = true;
        const inputs = this.form.querySelectorAll('input[required], textarea[required]');

        inputs.forEach(input => {
            if (!this.validateInput(input)) {
                isValid = false;
            }
        });

        return isValid;
    }

    validateInput(input) {
        const value = input.value.trim();
        const type = input.type;

        // Input boş mu kontrol et
        if (!value) {
            this.showError(input, 'Bu alan zorunludur.');
            return false;
        }

        // Email formatı kontrolü
        if (type === 'email' && !this.isValidEmail(value)) {
            this.showError(input, 'Geçerli bir email adresi giriniz.');
            return false;
        }

        // Şifre uzunluğu kontrolü
        if (type === 'password' && value.length < 6) {
            this.showError(input, 'Şifre en az 6 karakter olmalıdır.');
            return false;
        }

        this.clearError(input);
        return true;
    }

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    showError(input, message) {
        const errorDiv = this.getErrorDiv(input);
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        input.classList.add('error');
    }

    clearError(input) {
        const errorDiv = this.getErrorDiv(input);
        errorDiv.style.display = 'none';
        input.classList.remove('error');
    }

    getErrorDiv(input) {
        let errorDiv = input.nextElementSibling;
        if (!errorDiv || !errorDiv.classList.contains('error-message')) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.style.color = 'var(--error-color)';
            errorDiv.style.fontSize = '0.8rem';
            errorDiv.style.marginTop = '5px';
            input.parentNode.insertBefore(errorDiv, input.nextSibling);
        }
        return errorDiv;
    }
}

// CSRF koruması
class CSRFProtection {
    constructor() {
        this.tokenName = 'csrf_token';
        this.setupCSRFToken();
    }

    generateToken() {
        const array = new Uint8Array(32);
        window.crypto.getRandomValues(array);
        return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
    }

    setupCSRFToken() {
        const token = this.generateToken();
        localStorage.setItem(this.tokenName, token);
        
        // Tüm formlara CSRF token ekle
        document.querySelectorAll('form').forEach(form => {
            if (!form.querySelector(`input[name="${this.tokenName}"]`)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = this.tokenName;
                input.value = token;
                form.appendChild(input);
            }
        });
    }
}

// Global instance'ları oluştur
const tokenManager = new TokenManager();
const csrfProtection = new CSRFProtection();

// Form validasyonunu tüm formlara uygula
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach(form => {
        new FormValidator(form);
    });
}); 