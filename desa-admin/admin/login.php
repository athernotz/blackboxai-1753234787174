<?php
/**
 * =====================================================
 * ADMIN LOGIN PAGE
 * =====================================================
 * Modern login interface for village administration system
 * 
 * @author Village Admin System
 * @version 1.0.0
 * @created 2025
 */

// Start session
session_start();

// Include database connection
require_once '../api/config/database.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Handle login message from URL parameter
$message = '';
$messageType = 'info';
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
    $messageType = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : 'info';
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get app settings
$db = Database::getInstance();
$appName = 'Sistem Administrasi Desa';
$appVersion = '1.0.0';

try {
    $settings = $db->fetchAll("SELECT `key`, `value` FROM settings WHERE `key` IN ('app_name', 'app_version')");
    foreach ($settings as $setting) {
        if ($setting['key'] === 'app_name') {
            $appName = $setting['value'];
        } elseif ($setting['key'] === 'app_version') {
            $appVersion = $setting['value'];
        }
    }
} catch (Exception $e) {
    // Use default values if settings table doesn't exist yet
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($appName); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../public/img/favicon.ico">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        /* Custom animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: .5;
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .animate-pulse-slow {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* Loading spinner */
        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Form focus effects */
        .form-input:focus {
            transform: translateY(-1px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.15);
        }
        
        /* Background pattern */
        .bg-pattern {
            background-image: 
                radial-gradient(circle at 25px 25px, rgba(59, 130, 246, 0.1) 2px, transparent 0),
                radial-gradient(circle at 75px 75px, rgba(59, 130, 246, 0.1) 2px, transparent 0);
            background-size: 100px 100px;
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-blue-50 font-inter">
    
    <!-- Background Pattern -->
    <div class="fixed inset-0 bg-pattern opacity-30"></div>
    
    <!-- Main Container -->
    <div class="relative min-h-screen flex items-center justify-center px-4 py-12">
        
        <!-- Login Card -->
        <div class="w-full max-w-md">
            
            <!-- Header -->
            <div class="text-center mb-8 animate-fade-in-up">
                <!-- Logo/Icon -->
                <div class="mx-auto w-16 h-16 bg-primary-600 rounded-2xl flex items-center justify-center mb-4 shadow-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                
                <h1 class="text-2xl font-bold text-gray-900 mb-2">
                    <?php echo htmlspecialchars($appName); ?>
                </h1>
                <p class="text-gray-600">
                    Masuk ke dashboard administrasi
                </p>
            </div>
            
            <!-- Alert Message -->
            <?php if (!empty($message)): ?>
            <div id="alert-message" class="mb-6 animate-fade-in-up">
                <div class="p-4 rounded-lg border-l-4 <?php 
                    echo $messageType === 'error' ? 'bg-red-50 border-red-400 text-red-700' : 
                         ($messageType === 'success' ? 'bg-green-50 border-green-400 text-green-700' : 
                          'bg-blue-50 border-blue-400 text-blue-700'); 
                ?>">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <?php if ($messageType === 'error'): ?>
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            <?php elseif ($messageType === 'success'): ?>
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            <?php else: ?>
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            <?php endif; ?>
                        </svg>
                        <span><?php echo $message; ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <div class="bg-white rounded-2xl shadow-xl p-8 animate-fade-in-up" style="animation-delay: 0.2s;">
                
                <form id="loginForm" method="POST" action="../api/auth/login.php" class="space-y-6">
                    
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Username atau Email
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                required
                                class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all duration-200 pl-11"
                                placeholder="Masukkan username atau email"
                                autocomplete="username"
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                        </div>
                        <div id="username-error" class="text-red-600 text-sm mt-1 hidden"></div>
                    </div>
                    
                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                                class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all duration-200 pl-11 pr-11"
                                placeholder="Masukkan password"
                                autocomplete="current-password"
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <button 
                                type="button" 
                                id="togglePassword"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center"
                            >
                                <svg id="eyeIcon" class="h-5 w-5 text-gray-400 hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                        <div id="password-error" class="text-red-600 text-sm mt-1 hidden"></div>
                    </div>
                    
                    <!-- Remember Me -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input 
                                id="remember_me" 
                                name="remember_me" 
                                type="checkbox" 
                                class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                            >
                            <label for="remember_me" class="ml-2 block text-sm text-gray-700">
                                Ingat saya
                            </label>
                        </div>
                        
                        <div class="text-sm">
                            <a href="forgot_password.php" class="font-medium text-primary-600 hover:text-primary-500 transition-colors">
                                Lupa password?
                            </a>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div>
                        <button 
                            type="submit" 
                            id="submitBtn"
                            class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-200 transform hover:scale-105"
                        >
                            <span id="submitText">Masuk</span>
                            <div id="submitSpinner" class="spinner ml-2 hidden"></div>
                        </button>
                    </div>
                    
                </form>
                
                <!-- Additional Links -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Belum punya akun? 
                        <a href="register.php" class="font-medium text-primary-600 hover:text-primary-500 transition-colors">
                            Daftar di sini
                        </a>
                    </p>
                </div>
                
            </div>
            
            <!-- Footer -->
            <div class="text-center mt-8 animate-fade-in-up" style="animation-delay: 0.4s;">
                <p class="text-sm text-gray-500">
                    © 2025 <?php echo htmlspecialchars($appName); ?> v<?php echo htmlspecialchars($appVersion); ?>
                </p>
                <p class="text-xs text-gray-400 mt-1">
                    Dikembangkan untuk kemudahan administrasi desa
                </p>
            </div>
            
        </div>
        
    </div>
    
    <!-- JavaScript -->
    <script>
        // DOM Elements
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const submitText = document.getElementById('submitText');
        const submitSpinner = document.getElementById('submitSpinner');
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        const alertMessage = document.getElementById('alert-message');
        
        // Auto-hide alert message
        if (alertMessage) {
            setTimeout(() => {
                alertMessage.style.opacity = '0';
                alertMessage.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    alertMessage.remove();
                }, 300);
            }, 5000);
        }
        
        // Toggle password visibility
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle eye icon
            if (type === 'text') {
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                `;
            } else {
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                `;
            }
        });
        
        // Form validation
        function validateForm() {
            let isValid = true;
            
            // Clear previous errors
            document.querySelectorAll('.text-red-600').forEach(el => {
                el.classList.add('hidden');
            });
            
            // Validate username
            const username = document.getElementById('username').value.trim();
            if (username.length < 3) {
                showError('username-error', 'Username minimal 3 karakter');
                isValid = false;
            }
            
            // Validate password
            const password = document.getElementById('password').value;
            if (password.length < 6) {
                showError('password-error', 'Password minimal 6 karakter');
                isValid = false;
            }
            
            return isValid;
        }
        
        function showError(elementId, message) {
            const errorElement = document.getElementById(elementId);
            errorElement.textContent = message;
            errorElement.classList.remove('hidden');
        }
        
        // Handle form submission
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Validate form
            if (!validateForm()) {
                return;
            }
            
            // Show loading state
            setLoadingState(true);
            
            try {
                // Prepare form data
                const formData = new FormData(loginForm);
                const data = {
                    username: formData.get('username'),
                    password: formData.get('password'),
                    remember_me: formData.get('remember_me') === 'on',
                    csrf_token: formData.get('csrf_token')
                };
                
                // Send login request
                const response = await fetch('../api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Success - redirect to dashboard
                    showSuccessMessage('Login berhasil! Mengalihkan ke dashboard...');
                    
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1500);
                } else {
                    // Error - show message
                    showErrorMessage(result.message || 'Login gagal. Silakan coba lagi.');
                }
                
            } catch (error) {
                console.error('Login error:', error);
                showErrorMessage('Terjadi kesalahan. Silakan coba lagi.');
            } finally {
                setLoadingState(false);
            }
        });
        
        function setLoadingState(loading) {
            if (loading) {
                submitBtn.disabled = true;
                submitText.textContent = 'Memproses...';
                submitSpinner.classList.remove('hidden');
                submitBtn.classList.add('opacity-75');
            } else {
                submitBtn.disabled = false;
                submitText.textContent = 'Masuk';
                submitSpinner.classList.add('hidden');
                submitBtn.classList.remove('opacity-75');
            }
        }
        
        function showSuccessMessage(message) {
            showMessage(message, 'success');
        }
        
        function showErrorMessage(message) {
            showMessage(message, 'error');
        }
        
        function showMessage(message, type) {
            // Remove existing message
            const existingMessage = document.getElementById('dynamic-message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            // Create new message
            const messageDiv = document.createElement('div');
            messageDiv.id = 'dynamic-message';
            messageDiv.className = 'mb-6 animate-fade-in-up';
            
            const bgColor = type === 'success' ? 'bg-green-50 border-green-400 text-green-700' : 'bg-red-50 border-red-400 text-red-700';
            const icon = type === 'success' ? 
                '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>' :
                '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>';
            
            messageDiv.innerHTML = `
                <div class="p-4 rounded-lg border-l-4 ${bgColor}">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            ${icon}
                        </svg>
                        <span>${message}</span>
                    </div>
                </div>
            `;
            
            // Insert before form
            loginForm.parentNode.insertBefore(messageDiv, loginForm);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                messageDiv.style.opacity = '0';
                messageDiv.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    messageDiv.remove();
                }, 300);
            }, 5000);
        }
        
        // Focus first input on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Handle Enter key in form fields
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loginForm.dispatchEvent(new Event('submit'));
                }
            });
        });
    </script>
    
</body>
</html>
