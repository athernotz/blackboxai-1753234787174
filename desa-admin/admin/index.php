<?php
/**
 * =====================================================
 * ADMIN DASHBOARD - MAIN PAGE
 * =====================================================
 * Main dashboard page that loads React application
 * 
 * @author Village Admin System
 * @version 1.0.0
 * @created 2025
 */

// Include authentication check
require_once 'includes/auth_check.php';

// Check authentication - redirect to login if not authenticated
$user = requireAuth();

// Get user permissions
$auth = new AuthCheck();
$permissions = $auth->getUserPermissions($user['role']);

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

// Get dashboard statistics
$stats = [
    'total_penduduk' => 0,
    'total_surat_pending' => 0,
    'total_surat_selesai' => 0,
    'total_users' => 0
];

try {
    // Get penduduk count
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM penduduk WHERE deleted_at IS NULL");
    $stats['total_penduduk'] = $result['count'] ?? 0;
    
    // Get surat statistics
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM surat_requests WHERE status = 'pending' AND deleted_at IS NULL");
    $stats['total_surat_pending'] = $result['count'] ?? 0;
    
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM surat_requests WHERE status = 'selesai' AND deleted_at IS NULL");
    $stats['total_surat_selesai'] = $result['count'] ?? 0;
    
    // Get users count
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL");
    $stats['total_users'] = $result['count'] ?? 0;
    
} catch (Exception $e) {
    // Use default values if tables don't exist yet
}

// Generate CSRF token for API requests
$csrfToken = $auth->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($appName); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../public/img/favicon.ico">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- React CDN -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    
    <!-- Babel for JSX transformation -->
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    
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
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
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
    </style>
</head>
<body class="bg-gray-50 font-inter">
    
    <!-- React App Container -->
    <div id="root"></div>
    
    <!-- Loading Fallback -->
    <div id="loading-fallback" class="min-h-screen flex items-center justify-center">
        <div class="text-center">
            <div class="spinner mx-auto mb-4"></div>
            <p class="text-gray-600">Memuat dashboard...</p>
        </div>
    </div>
    
    <!-- Global App Configuration -->
    <script>
        // Global configuration for React app
        window.APP_CONFIG = {
            user: <?php echo json_encode([
                'id' => $user['id'],
                'uuid' => $user['uuid'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ]); ?>,
            permissions: <?php echo json_encode($permissions); ?>,
            csrfToken: '<?php echo $csrfToken; ?>',
            apiBaseUrl: '../api',
            appName: '<?php echo htmlspecialchars($appName); ?>',
            appVersion: '<?php echo htmlspecialchars($appVersion); ?>',
            stats: <?php echo json_encode($stats); ?>
        };
        
        // API helper functions
        window.API = {
            async request(endpoint, options = {}) {
                const url = `${window.APP_CONFIG.apiBaseUrl}${endpoint}`;
                const config = {
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.APP_CONFIG.csrfToken,
                        ...options.headers
                    },
                    ...options
                };
                
                try {
                    const response = await fetch(url, config);
                    const data = await response.json();
                    
                    if (!response.ok) {
                        throw new Error(data.message || 'Request failed');
                    }
                    
                    return data;
                } catch (error) {
                    console.error('API Request Error:', error);
                    throw error;
                }
            },
            
            get(endpoint) {
                return this.request(endpoint, { method: 'GET' });
            },
            
            post(endpoint, data) {
                return this.request(endpoint, {
                    method: 'POST',
                    body: JSON.stringify(data)
                });
            },
            
            put(endpoint, data) {
                return this.request(endpoint, {
                    method: 'PUT',
                    body: JSON.stringify(data)
                });
            },
            
            delete(endpoint) {
                return this.request(endpoint, { method: 'DELETE' });
            }
        };
        
        // Utility functions
        window.Utils = {
            formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('id-ID', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            },
            
            formatDateTime(dateString) {
                const date = new Date(dateString);
                return date.toLocaleString('id-ID', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            },
            
            formatNumber(number) {
                return new Intl.NumberFormat('id-ID').format(number);
            },
            
            formatCurrency(amount) {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR'
                }).format(amount);
            }
        };
    </script>
    
    <!-- React Components -->
    <script type="text/babel">
        const { useState, useEffect, useContext, createContext } = React;
        
        // App Context for global state management
        const AppContext = createContext();
        
        // App Provider Component
        function AppProvider({ children }) {
            const [user] = useState(window.APP_CONFIG.user);
            const [permissions] = useState(window.APP_CONFIG.permissions);
            const [currentPage, setCurrentPage] = useState('dashboard');
            const [sidebarOpen, setSidebarOpen] = useState(true);
            const [notifications, setNotifications] = useState([]);
            
            // Add notification function to global scope
            window.showNotification = (message, type = 'info') => {
                const id = Date.now();
                const notification = { id, message, type };
                setNotifications(prev => [...prev, notification]);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    setNotifications(prev => prev.filter(n => n.id !== id));
                }, 5000);
            };
            
            const value = {
                user,
                permissions,
                currentPage,
                setCurrentPage,
                sidebarOpen,
                setSidebarOpen,
                notifications,
                setNotifications
            };
            
            return (
                <AppContext.Provider value={value}>
                    {children}
                </AppContext.Provider>
            );
        }
        
        // Hook to use app context
        function useApp() {
            const context = useContext(AppContext);
            if (!context) {
                throw new Error('useApp must be used within AppProvider');
            }
            return context;
        }
        
        // Sidebar Component
        function Sidebar() {
            const { sidebarOpen, setSidebarOpen, currentPage, setCurrentPage, user } = useApp();
            
            const menuItems = [
                { id: 'dashboard', label: 'Dashboard', icon: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2 2z' },
                { id: 'surat', label: 'Surat Menyurat', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
                { id: 'penduduk', label: 'Data Penduduk', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z' },
                { id: 'laporan', label: 'Laporan', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' }
            ];
            
            // Add admin-only menu items
            if (user.role === 'super_admin' || user.role === 'admin') {
                menuItems.push(
                    { id: 'users', label: 'Manajemen User', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z' },
                    { id: 'settings', label: 'Pengaturan', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' }
                );
            }
            
            return (
                <div className={`fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform transition-transform duration-300 ease-in-out ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'} lg:translate-x-0 lg:static lg:inset-0`}>
                    <div className="flex items-center justify-between h-16 px-6 bg-primary-600">
                        <h1 className="text-white font-semibold text-lg">Admin Panel</h1>
                        <button 
                            onClick={() => setSidebarOpen(false)}
                            className="lg:hidden text-white hover:text-gray-200"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <nav className="mt-8 px-4 custom-scrollbar overflow-y-auto h-full pb-20">
                        {menuItems.map(item => (
                            <button
                                key={item.id}
                                onClick={() => setCurrentPage(item.id)}
                                className={`w-full flex items-center px-4 py-3 mb-2 text-left rounded-lg transition-colors ${
                                    currentPage === item.id 
                                        ? 'bg-primary-50 text-primary-700 border-r-2 border-primary-600' 
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                                }`}
                            >
                                <svg className="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={item.icon}></path>
                                </svg>
                                {item.label}
                            </button>
                        ))}
                    </nav>
                </div>
            );
        }
        
        // Header Component
        function Header() {
            const { sidebarOpen, setSidebarOpen, user } = useApp();
            const [dropdownOpen, setDropdownOpen] = useState(false);
            
            const handleLogout = async () => {
                try {
                    await window.API.post('/auth/logout.php', {});
                    window.location.href = 'login.php';
                } catch (error) {
                    console.error('Logout error:', error);
                    // Force redirect even if API call fails
                    window.location.href = 'login.php';
                }
            };
            
            return (
                <header className="bg-white shadow-sm border-b border-gray-200">
                    <div className="flex items-center justify-between h-16 px-6">
                        <div className="flex items-center">
                            <button 
                                onClick={() => setSidebarOpen(!sidebarOpen)}
                                className="lg:hidden text-gray-500 hover:text-gray-700 mr-4"
                            >
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                            </button>
                            <h2 className="text-xl font-semibold text-gray-800">
                                {window.APP_CONFIG.appName}
                            </h2>
                        </div>
                        
                        <div className="flex items-center space-x-4">
                            <div className="relative">
                                <button 
                                    onClick={() => setDropdownOpen(!dropdownOpen)}
                                    className="flex items-center space-x-3 text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-primary-500"
                                >
                                    <div className="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center">
                                        <span className="text-white font-medium">
                                            {user.full_name.charAt(0).toUpperCase()}
                                        </span>
                                    </div>
                                    <span className="hidden md:block text-gray-700 font-medium">
                                        {user.full_name}
                                    </span>
                                    <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                
                                {dropdownOpen && (
                                    <div className="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                                        <div className="px-4 py-2 text-sm text-gray-700 border-b">
                                            <div className="font-medium">{user.full_name}</div>
                                            <div className="text-gray-500">{user.email}</div>
                                        </div>
                                        <button 
                                            onClick={() => {/* Profile page */}}
                                            className="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                        >
                                            Profil Saya
                                        </button>
                                        <button 
                                            onClick={handleLogout}
                                            className="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                        >
                                            Keluar
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </header>
            );
        }
        
        // Dashboard Stats Component
        function DashboardStats() {
            const stats = window.APP_CONFIG.stats;
            
            const statItems = [
                { label: 'Total Penduduk', value: stats.total_penduduk, icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z', color: 'blue' },
                { label: 'Surat Pending', value: stats.total_surat_pending, icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', color: 'yellow' },
                { label: 'Surat Selesai', value: stats.total_surat_selesai, icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', color: 'green' },
                { label: 'Total Users', value: stats.total_users, icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', color: 'purple' }
            ];
            
            const colorClasses = {
                blue: 'text-blue-600',
                yellow: 'text-yellow-600',
                green: 'text-green-600',
                purple: 'text-purple-600'
            };
            
            return (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    {statItems.map((item, index) => (
                        <div key={index} className="bg-white rounded-lg shadow p-6">
                            <div className="flex items-center">
                                <div className={`p-3 rounded-full bg-${item.color}-50`}>
                                    <svg className={`w-6 h-6 ${colorClasses[item.color]}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={item.icon}></path>
                                    </svg>
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm font-medium text-gray-600">{item.label}</p>
                                    <p className="text-2xl font-semibold text-gray-900">{window.Utils.formatNumber(item.value)}</p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            );
        }
        
        // Dashboard Content Component
        function DashboardContent() {
            return (
                <div className="p-6">
                    <div className="mb-6">
                        <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
                        <p className="text-gray-600">Selamat datang di sistem administrasi desa</p>
                    </div>
                    
                    <DashboardStats />
                    
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div className="bg-white rounded-lg shadow p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">Surat Terbaru</h3>
                            <div className="space-y-3">
                                <div className="flex items-center justify-between p-3 bg-gray-50 rounded">
                                    <div>
                                        <p className="font-medium text-gray-900">Surat Keterangan Domisili</p>
                                        <p className="text-sm text-gray-600">Ahmad Wijaya</p>
                                    </div>
                                    <span className="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded">Pending</span>
                                </div>
                                <div className="flex items-center justify-between p-3 bg-gray-50 rounded">
                                    <div>
                                        <p className="font-medium text-gray-900">Surat Keterangan Usaha</p>
                                        <p className="text-sm text-gray-600">Siti Nurhaliza</p>
                                    </div>
                                    <span className="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">Selesai</span>
                                </div>
                            </div>
                        </div>
                        
                        <div className="bg-white rounded-lg shadow p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">Aktivitas Terbaru</h3>
                            <div className="space-y-3">
                                <div className="flex items-start space-x-3">
                                    <div className="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                                    <div>
                                        <p className="text-sm text-gray-900">Data penduduk baru ditambahkan</p>
                                        <p className="text-xs text-gray-500">2 jam yang lalu</p>
                                    </div>
                                </div>
                                <div className="flex items-start space-x-3">
                                    <div className="w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                                    <div>
                                        <p className="text-sm text-gray-900">Surat keterangan domisili disetujui</p>
                                        <p className="text-xs text-gray-500">4 jam yang lalu</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            );
        }
        
        // Page Content Router
        function PageContent() {
            const { currentPage } = useApp();
            
            switch (currentPage) {
                case 'dashboard':
                    return <DashboardContent />;
                case 'surat':
                    return (
                        <div className="p-6">
                            <h1 className="text-2xl font-bold text-gray-900 mb-6">Surat Menyurat</h1>
                            <div className="bg-white rounded-lg shadow p-6">
                                <p className="text-gray-600">Fitur surat menyurat akan segera tersedia.</p>
                            </div>
                        </div>
                    );
                case 'penduduk':
                    return (
                        <div className="p-6">
                            <h1 className="text-2xl font-bold text-gray-900 mb-6">Data Penduduk</h1>
                            <div className="bg-white rounded-lg shadow p-6">
                                <p className="text-gray-600">Fitur data penduduk akan segera tersedia.</p>
                            </div>
                        </div>
                    );
                default:
                    return (
                        <div className="p-6">
                            <h1 className="text-2xl font-bold text-gray-900 mb-6">{currentPage}</h1>
                            <div className="bg-white rounded-lg shadow p-6">
                                <p className="text-gray-600">Halaman ini sedang dalam pengembangan.</p>
                            </div>
                        </div>
                    );
            }
        }
        
        // Main App Component
        function App() {
            const { sidebarOpen, setSidebarOpen } = useApp();
            
            // Close sidebar when clicking outside on mobile
            useEffect(() => {
                const handleClickOutside = (event) => {
                    if (window.innerWidth < 1024 && sidebarOpen) {
                        const sidebar = document.querySelector('[data-sidebar]');
                        if (sidebar && !sidebar.contains(event.target)) {
                            setSidebarOpen(false);
                        }
                    }
                };
                
                document.addEventListener('mousedown', handleClickOutside);
                return () => document.removeEventListener('mousedown', handleClickOutside);
            }, [sidebarOpen, setSidebarOpen]);
            
            return (
                <div className="flex h-screen bg-gray-50">
                    <div data-sidebar>
                        <Sidebar />
                    </div>
                    
                    <div className="flex-1 flex flex-col overflow-hidden">
                        <Header />
                        <main className="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50">
                            <PageContent />
                        </main>
                    </div>
                    
                    {/* Mobile sidebar overlay */}
                    {sidebarOpen && (
                        <div 
                            className="fixed inset-0 z-40 bg-black bg-opacity-50 lg:hidden"
                            onClick={() => setSidebarOpen(false)}
                        ></div>
                    )}
                </div>
            );
        }
        
        // Render the app
        function renderApp() {
            const root = ReactDOM.createRoot(document.getElementById('root'));
            root.render(
                <AppProvider>
                    <App />
                </AppProvider>
            );
            
            // Hide loading fallback
            const loadingFallback = document.getElementById('loading-fallback');
            if (loadingFallback) {
                loadingFallback.style.display = 'none';
            }
        }
        
        // Initialize app when DOM is ready
        document.addEventListener('DOMContentLoaded', renderApp);
    </script>
    
</body>
</html>
