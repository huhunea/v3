/**
 * Authentication JavaScript
 * Tạo bởi: MiniMax Agent
 */

// API Base URL
const API_BASE = '/backend/api';

// Global user state
let currentUser = null;
let availableAvatars = [];

// Initialize authentication
document.addEventListener('DOMContentLoaded', function() {
    loadAvatars();
    checkAuthentication();
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    // Auth modal events
    document.getElementById('authModalClose').addEventListener('click', closeAuthModal);
    document.getElementById('loginFormSubmit').addEventListener('submit', handleLogin);
    document.getElementById('registerFormSubmit').addEventListener('submit', handleRegister);
    
    // Profile modal events
    document.getElementById('profileModalClose').addEventListener('click', closeProfileModal);
    document.getElementById('profileFormSubmit').addEventListener('submit', handleProfileUpdate);
    
    // Close modals when clicking outside
    document.getElementById('authModal').addEventListener('click', function(e) {
        if (e.target === this) closeAuthModal();
    });
    
    document.getElementById('profileModal').addEventListener('click', function(e) {
        if (e.target === this) closeProfileModal();
    });
    
    // Close user menu when clicking outside
    document.addEventListener('click', function(e) {
        const userMenu = document.getElementById('userMenuDropdown');
        const userBtn = document.getElementById('userMenuBtn');
        if (userMenu && !userMenu.contains(e.target) && e.target !== userBtn) {
            userMenu.style.display = 'none';
        }
    });
}

// Load available avatars
async function loadAvatars() {
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=get_avatars`);
        const data = await response.json();
        
        if (data.success) {
            availableAvatars = data.avatars;
            renderAvatarSelection('avatarSelection');
            renderAvatarSelection('profileAvatarSelection');
        }
    } catch (error) {
        console.error('Error loading avatars:', error);
    }
}

// Render avatar selection
function renderAvatarSelection(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '';
    
    availableAvatars.forEach(avatar => {
        const option = document.createElement('div');
        option.className = 'avatar-option';
        option.innerHTML = avatar.icon_emoji;
        option.title = avatar.icon_name;
        option.dataset.iconName = avatar.icon_name;
        
        option.addEventListener('click', function() {
            // Remove selected class from siblings
            container.querySelectorAll('.avatar-option').forEach(opt => opt.classList.remove('selected'));
            // Add selected class to clicked option
            this.classList.add('selected');
            
            // Update hidden input
            const hiddenInput = containerId === 'avatarSelection' 
                ? document.getElementById('selectedAvatar')
                : document.getElementById('profileSelectedAvatar');
            if (hiddenInput) {
                hiddenInput.value = avatar.icon_name;
            }
        });
        
        container.appendChild(option);
    });
    
    // Select first avatar by default
    if (availableAvatars.length > 0) {
        const firstOption = container.querySelector('.avatar-option');
        if (firstOption) {
            firstOption.classList.add('selected');
        }
    }
}

// Check authentication status
async function checkAuthentication() {
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=verify`);
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            updateUIForLoggedInUser();
        } else {
            currentUser = null;
            updateUIForLoggedOutUser();
        }
    } catch (error) {
        console.error('Error checking authentication:', error);
        updateUIForLoggedOutUser();
    }
}

// Update UI for logged in user
function updateUIForLoggedInUser() {
    // Hide login button, show user menu
    const loginBtn = document.getElementById('loginBtn');
    const userMenuBtn = document.getElementById('userMenuBtn');
    
    if (loginBtn) loginBtn.style.display = 'none';
    if (userMenuBtn) {
        userMenuBtn.style.display = 'block';
        
        // Update user info in button
        const avatar = getAvatarEmoji(currentUser.avatar_icon);
        const badge = currentUser.admin_badge;
        
        userMenuBtn.innerHTML = `
            <span class="user-avatar">${avatar}</span>
            <span class="user-name">${currentUser.display_name}</span>
            ${badge ? `<span class="user-badge">${badge}</span>` : ''}
        `;
    }
    
    // Update user menu dropdown
    updateUserMenuDropdown();
}

// Update UI for logged out user
function updateUIForLoggedOutUser() {
    const loginBtn = document.getElementById('loginBtn');
    const userMenuBtn = document.getElementById('userMenuBtn');
    
    if (loginBtn) loginBtn.style.display = 'block';
    if (userMenuBtn) userMenuBtn.style.display = 'none';
}

// Update user menu dropdown
function updateUserMenuDropdown() {
    const userName = document.getElementById('userName');
    const userAvatar = document.getElementById('userAvatar');
    const userBadge = document.getElementById('userBadge');
    
    if (userName) userName.textContent = currentUser.display_name;
    if (userAvatar) userAvatar.textContent = getAvatarEmoji(currentUser.avatar_icon);
    
    if (userBadge) {
        if (currentUser.admin_badge) {
            userBadge.textContent = currentUser.admin_badge;
            userBadge.style.display = 'inline-block';
        } else {
            userBadge.style.display = 'none';
        }
    }
}

// Get avatar emoji by name
function getAvatarEmoji(iconName) {
    const avatar = availableAvatars.find(a => a.icon_name === iconName);
    return avatar ? avatar.icon_emoji : '👤';
}

// Switch auth tab
function switchAuthTab(tab) {
    const loginTab = document.getElementById('loginTab');
    const registerTab = document.getElementById('registerTab');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    if (tab === 'login') {
        loginTab.classList.add('active');
        registerTab.classList.remove('active');
        loginForm.style.display = 'block';
        registerForm.style.display = 'none';
    } else {
        registerTab.classList.add('active');
        loginTab.classList.remove('active');
        registerForm.style.display = 'block';
        loginForm.style.display = 'none';
    }
}

// Show auth modal
function showAuthModal(tab = 'login') {
    document.getElementById('authModal').style.display = 'flex';
    switchAuthTab(tab);
    clearAuthMessages();
}

// Close auth modal
function closeAuthModal() {
    document.getElementById('authModal').style.display = 'none';
    clearAuthForms();
}

// Clear auth forms
function clearAuthForms() {
    document.getElementById('loginFormSubmit').reset();
    document.getElementById('registerFormSubmit').reset();
    clearAuthMessages();
}

// Clear auth messages
function clearAuthMessages() {
    const message = document.getElementById('authMessage');
    message.style.display = 'none';
    message.className = 'auth-message';
}

// Show auth message
function showAuthMessage(text, type = 'error') {
    const message = document.getElementById('authMessage');
    message.textContent = text;
    message.className = `auth-message ${type}`;
    message.style.display = 'block';
}

// Show loading
function showAuthLoading(show = true) {
    const loading = document.getElementById('authLoading');
    const forms = document.querySelectorAll('.auth-form');
    
    if (show) {
        loading.style.display = 'block';
        forms.forEach(form => form.style.display = 'none');
    } else {
        loading.style.display = 'none';
        // Show current active form
        const activeTab = document.querySelector('.auth-tab.active').id;
        const formId = activeTab === 'loginTab' ? 'loginForm' : 'registerForm';
        document.getElementById(formId).style.display = 'block';
    }
}

// Handle login
async function handleLogin(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {
        username: formData.get('username'),
        password: formData.get('password')
    };
    
    if (!data.username || !data.password) {
        showAuthMessage('Vui lòng điền đầy đủ thông tin');
        return;
    }
    
    showAuthLoading(true);
    
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            currentUser = result.user;
            showAuthMessage('Đăng nhập thành công!', 'success');
            
            setTimeout(() => {
                closeAuthModal();
                updateUIForLoggedInUser();
                // Reload comments if current page has them
                if (typeof reloadComments === 'function') {
                    reloadComments();
                }
            }, 1000);
        } else {
            showAuthMessage(result.message || 'Đăng nhập thất bại');
        }
    } catch (error) {
        console.error('Login error:', error);
        showAuthMessage('Có lỗi xảy ra khi đăng nhập');
    } finally {
        showAuthLoading(false);
    }
}

// Handle register
async function handleRegister(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {
        username: formData.get('username'),
        email: formData.get('email'),
        display_name: formData.get('display_name'),
        password: formData.get('password'),
        avatar_icon: formData.get('avatar_icon') || 'user'
    };
    
    const confirmPassword = formData.get('confirm_password');
    
    // Validate
    if (!data.username || !data.email || !data.display_name || !data.password) {
        showAuthMessage('Vui lòng điền đầy đủ thông tin');
        return;
    }
    
    if (data.password !== confirmPassword) {
        showAuthMessage('Mật khẩu xác nhận không khớp');
        return;
    }
    
    if (data.password.length < 6) {
        showAuthMessage('Mật khẩu phải có ít nhất 6 ký tự');
        return;
    }
    
    showAuthLoading(true);
    
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=register`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAuthMessage('Đăng ký thành công! Hãy đăng nhập.', 'success');
            
            setTimeout(() => {
                switchAuthTab('login');
                // Auto fill username
                document.getElementById('loginUsername').value = data.username;
            }, 1500);
        } else {
            showAuthMessage(result.message || 'Đăng ký thất bại');
        }
    } catch (error) {
        console.error('Register error:', error);
        showAuthMessage('Có lỗi xảy ra khi đăng ký');
    } finally {
        showAuthLoading(false);
    }
}

// Show profile modal
async function openProfileModal() {
    document.getElementById('profileModal').style.display = 'flex';
    
    // Hide user menu
    document.getElementById('userMenuDropdown').style.display = 'none';
    
    // Load current profile data
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=get_profile`);
        const data = await response.json();
        
        if (data.success) {
            const profile = data.profile;
            document.getElementById('profileDisplayName').value = profile.display_name || '';
            document.getElementById('profileBio').value = profile.profile_bio || '';
            
            // Select current avatar
            selectAvatarInProfile(profile.avatar_icon);
        }
    } catch (error) {
        console.error('Error loading profile:', error);
    }
}

// Select avatar in profile modal
function selectAvatarInProfile(iconName) {
    const container = document.getElementById('profileAvatarSelection');
    const options = container.querySelectorAll('.avatar-option');
    
    options.forEach(option => {
        option.classList.remove('selected');
        if (option.dataset.iconName === iconName) {
            option.classList.add('selected');
            document.getElementById('profileSelectedAvatar').value = iconName;
        }
    });
}

// Close profile modal
function closeProfileModal() {
    document.getElementById('profileModal').style.display = 'none';
    clearProfileMessages();
}

// Clear profile messages
function clearProfileMessages() {
    const message = document.getElementById('profileMessage');
    message.style.display = 'none';
    message.className = 'profile-message';
}

// Show profile message
function showProfileMessage(text, type = 'error') {
    const message = document.getElementById('profileMessage');
    message.textContent = text;
    message.className = `profile-message ${type}`;
    message.style.display = 'block';
}

// Handle profile update
async function handleProfileUpdate(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {
        display_name: formData.get('display_name'),
        avatar_icon: formData.get('avatar_icon') || 'user',
        profile_bio: formData.get('profile_bio') || ''
    };
    
    if (!data.display_name) {
        showProfileMessage('Tên hiển thị không được để trống');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=update_profile`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update current user data
            currentUser.display_name = data.display_name;
            currentUser.avatar_icon = data.avatar_icon;
            
            showProfileMessage('Cập nhật profile thành công!', 'success');
            updateUIForLoggedInUser();
            
            setTimeout(() => {
                closeProfileModal();
            }, 1500);
        } else {
            showProfileMessage(result.message || 'Cập nhật thất bại');
        }
    } catch (error) {
        console.error('Profile update error:', error);
        showProfileMessage('Có lỗi xảy ra khi cập nhật profile');
    }
}

// Toggle user menu
function toggleUserMenu() {
    const dropdown = document.getElementById('userMenuDropdown');
    const isVisible = dropdown.style.display === 'block';
    dropdown.style.display = isVisible ? 'none' : 'block';
}

// Logout
async function logout() {
    try {
        await fetch(`${API_BASE}/auth.php?action=logout`, {
            method: 'POST'
        });
        
        currentUser = null;
        updateUIForLoggedOutUser();
        
        // Hide user menu
        document.getElementById('userMenuDropdown').style.display = 'none';
        
        // Reload comments if current page has them
        if (typeof reloadComments === 'function') {
            reloadComments();
        }
        
    } catch (error) {
        console.error('Logout error:', error);
    }
}

// Utility function to check if user is logged in
function isLoggedIn() {
    return currentUser !== null;
}

// Utility function to check if user is admin
function isAdmin() {
    return currentUser && currentUser.is_admin == 1;
}

// Get current user
function getCurrentUser() {
    return currentUser;
}

// Export functions for global use
window.showAuthModal = showAuthModal;
window.switchAuthTab = switchAuthTab;
window.openProfileModal = openProfileModal;
window.toggleUserMenu = toggleUserMenu;
window.logout = logout;
window.isLoggedIn = isLoggedIn;
window.isAdmin = isAdmin;
window.getCurrentUser = getCurrentUser;
