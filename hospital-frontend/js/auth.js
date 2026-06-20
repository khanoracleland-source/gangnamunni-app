/**
 * Authentication Manager
 * Handles user authentication, session management
 */

const AuthManager = {
    // API Base URL
    API_BASE: 'http://localhost:8000/api',

    /**
     * Check if user is logged in
     */
    isLoggedIn() {
        const user = localStorage.getItem('user');
        const token = localStorage.getItem('token');
        return !!(user && token);
    },

    /**
     * Get current user
     */
    getCurrentUser() {
        const userStr = localStorage.getItem('user');
        return userStr ? JSON.parse(userStr) : null;
    },

    /**
     * Get auth token
     */
    getToken() {
        return localStorage.getItem('token');
    },

    /**
     * Register new user
     */
    async register(userData) {
        try {
            const response = await fetch(`${this.API_BASE}/auth/register`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData)
            });

            const data = await response.json();

            if (data.success) {
                // Auto login after register
                const loginData = await this.login({
                    email: userData.email,
                    password: userData.password
                });
                return loginData;
            } else {
                throw new Error(data.message || '회원가입에 실패했습니다.');
            }
        } catch (error) {
            console.error('Register error:', error);
            throw error;
        }
    },

    /**
     * Login user
     */
    async login(credentials) {
        try {
            const response = await fetch(`${this.API_BASE}/auth/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(credentials)
            });

            const data = await response.json();

            if (data.success) {
                // Store user and token
                localStorage.setItem('user', JSON.stringify(data.user));
                localStorage.setItem('token', data.token);
                return data;
            } else {
                throw new Error(data.message || '로그인에 실패했습니다.');
            }
        } catch (error) {
            console.error('Login error:', error);
            throw error;
        }
    },

    /**
     * Logout user
     */
    async logout() {
        try {
            await fetch(`${this.API_BASE}/auth/logout`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.getToken()}`
                }
            });

            // Clear local storage
            localStorage.removeItem('user');
            localStorage.removeItem('token');

            // Redirect to home
            window.location.href = 'index.html';
        } catch (error) {
            console.error('Logout error:', error);
            // Clear anyway
            localStorage.removeItem('user');
            localStorage.removeItem('token');
            window.location.href = 'index.html';
        }
    },

    /**
     * Update "내 예약" button in navigation
     */
    updateNavButtons() {
        const isLoggedIn = this.isLoggedIn();
        const user = this.getCurrentUser();

        // Find all nav-button elements
        document.querySelectorAll('.nav-button').forEach(btn => {
            if (btn.textContent.includes('내 예약') || btn.onclick?.toString().includes('handleMyBooking')) {
                if (isLoggedIn) {
                    // Replace with actual link to my-bookings page
                    btn.onclick = null;
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        window.location.href = 'my-bookings.html';
                    });
                }
            }
        });

        // Add user menu if logged in
        if (isLoggedIn) {
            const headerContent = document.querySelector('.header-content');
            const navMenu = document.querySelector('.nav-menu');

            if (navMenu && !document.querySelector('.user-menu')) {
                const userMenu = document.createElement('div');
                userMenu.className = 'user-menu';
                userMenu.style.cssText = 'position: relative; display: inline-block;';

                userMenu.innerHTML = `
                    <button class="user-menu-btn" style="
                        padding: 10px 20px;
                        background: transparent;
                        color: #333;
                        border: 2px solid #FF6B9D;
                        border-radius: 25px;
                        font-weight: 600;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        gap: 8px;
                    " onclick="toggleUserMenu(event)">
                        <span>👤</span>
                        <span>${user.name}</span>
                        <span style="font-size: 12px;">▼</span>
                    </button>
                    <div class="user-dropdown" style="
                        display: none;
                        position: absolute;
                        top: 100%;
                        right: 0;
                        margin-top: 10px;
                        background: white;
                        border-radius: 10px;
                        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
                        min-width: 180px;
                        z-index: 1000;
                    ">
                        <a href="my-bookings.html" style="
                            display: block;
                            padding: 12px 20px;
                            color: #333;
                            text-decoration: none;
                            border-bottom: 1px solid #f0f0f0;
                        ">📅 내 예약</a>
                        <a href="my-favorites.html" style="
                            display: block;
                            padding: 12px 20px;
                            color: #333;
                            text-decoration: none;
                            border-bottom: 1px solid #f0f0f0;
                        ">❤️ 찜한 병원</a>
                        <a href="my-reviews.html" style="
                            display: block;
                            padding: 12px 20px;
                            color: #333;
                            text-decoration: none;
                            border-bottom: 1px solid #f0f0f0;
                        ">✍️ 내 리뷰</a>
                        <a href="#" onclick="AuthManager.logout(); return false;" style="
                            display: block;
                            padding: 12px 20px;
                            color: #FF6B9D;
                            text-decoration: none;
                            font-weight: 600;
                        ">🚪 로그아웃</a>
                    </div>
                `;

                // Replace "내 예약" button with user menu
                const myBookingBtn = Array.from(navMenu.querySelectorAll('.nav-button')).find(btn =>
                    btn.textContent.includes('내 예약')
                );
                if (myBookingBtn) {
                    myBookingBtn.replaceWith(userMenu);
                }
            }
        }
    },

    /**
     * Require authentication for a page
     */
    requireAuth(redirectUrl = 'login.html') {
        if (!this.isLoggedIn()) {
            window.location.href = redirectUrl;
            return false;
        }
        return true;
    }
};

// Global function to toggle user menu
window.toggleUserMenu = function(event) {
    event.stopPropagation();
    const dropdown = event.currentTarget.nextElementSibling;
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
};

// Close dropdown when clicking outside
document.addEventListener('click', function() {
    const dropdowns = document.querySelectorAll('.user-dropdown');
    dropdowns.forEach(dropdown => {
        dropdown.style.display = 'none';
    });
});

// Update nav buttons on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        AuthManager.updateNavButtons();
    });
} else {
    AuthManager.updateNavButtons();
}
