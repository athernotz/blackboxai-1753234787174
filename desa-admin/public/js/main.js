/**
 * =====================================================
 * MAIN JAVASCRIPT FOR VILLAGE ADMINISTRATION SYSTEM
 * =====================================================
 * Created: 2025
 * Description: Interactive features for landing page
 */

(function() {
    'use strict';

    // =====================================================
    // GLOBAL CONFIGURATION
    // =====================================================

    const CONFIG = {
        APP_NAME: 'Sistem Administrasi Desa',
        VERSION: '1.0.0',
        API_BASE_URL: '../api',
        ANIMATION_DURATION: 600,
        SCROLL_DURATION: 800,
        COUNT_ANIMATION_DURATION: 2000,
        CAROUSEL_DURATION: 5000,
        FAQ_DURATION: 300
    };

    // =====================================================
    // NAVIGATION SYSTEM
    // =====================================================

    /**
     * Smooth scrolling navigation
     */
    function initSmoothScroll() {
        const navLinks = document.querySelectorAll('.nav-link, .mobile-nav-link');
        
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    /**
     * Mobile menu toggle
     */
    function initMobileMenu() {
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', function() {
                const isHidden = mobileMenu.classList.contains('hidden');
                mobileMenu.classList.toggle('hidden', !isHidden);
                mobileMenuButton.classList.toggle('active', !isHidden);
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!mobileMenuButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                    mobileMenu.classList.add('hidden');
                    mobileMenuButton.classList.remove('active');
                }
            });
        }
    }

    // =====================================================
    // STATISTICS COUNTER ANIMATION
    // =====================================================

    /**
     * Animate statistics counters
     */
    function initStatisticsCounters() {
        const counters = document.querySelectorAll('.statistics-number');
        
        counters.forEach(counter => {
            const finalValue = parseInt(counter.textContent);
            let currentValue = 0;
            const increment = Math.ceil(finalValue / (CONFIG.COUNT_ANIMATION_DURATION / 100));
            
            const animateCounter = () => {
                if (currentValue < finalValue) {
                    currentValue += increment;
                    if (currentValue > finalValue) {
                        currentValue = finalValue;
                    }
                    counter.textContent = currentValue.toLocaleString('id-ID');
                }
            };
            
            const observer = new IntersectionObserver(animateCounter, {
                threshold: 0.5
            });
            observer.observe(counter);
        });
    }

    // =====================================================
    // TESTIMONIAL CAROUSEL
    // =====================================================

    /**
     * Testimonial carousel functionality
     */
    function initTestimonialCarousel() {
        const carousel = document.getElementById('testimonial-carousel');
        const testimonials = document.querySelectorAll('.testimonial-card');
        let currentIndex = 0;
        
        if (carousel && testimonials.length > 0) {
            testimonials.forEach((testimonial, index) => {
                testimonial.style.display = index === 0 ? 'block' : 'none';
            });
            
            const showNext = () => {
                testimonials[currentIndex].style.display = 'none';
                currentIndex = (currentIndex + 1) % testimonials.length;
                testimonials[currentIndex].style.display = 'block';
            };
            
            setInterval(showNext, CONFIG.CAROUSEL_DURATION);
        }
    }

    // =====================================================
    // FAQ ACCORDION
    // =====================================================

    /**
     * FAQ accordion functionality
     */
    function initFAQAccordion() {
        const faqItems = document.querySelectorAll('.faq-item');
        
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            const answer = item.querySelector('.faq-answer');
            
            if (question && answer) {
                question.addEventListener('click', function() {
                    const isActive = item.classList.contains('active');
                    
                    faqItems.forEach(otherItem => {
                        otherItem.classList.remove('active');
                    });
                    
                    if (!isActive) {
                        item.classList.add('active');
                    }
                });
            }
        });
    }

    // =====================================================
    // FORM VALIDATION
    // =====================================================

    /**
     * Contact form validation
     */
    function initContactForm() {
        const contactForm = document.getElementById('contact-form');
        const contactSubmit = document.getElementById('contact-submit');
        
        if (contactForm && contactSubmit) {
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(contactForm);
                const name = formData.get('name');
                const email = formData.get('email');
                const message = formData.get('message');
                
                if (!validateForm(name, email, message)) {
                    return;
                }
                
                sendContactForm(name, email, message);
            });
        }
    }

    /**
     * Validate form data
     */
    function validateForm(name, email, message) {
        const errors = [];
        
        if (!name || name.length < 2) {
            errors.push('Nama minimal 2 karakter');
        }
        
        if (!email || !isValidEmail(email)) {
            errors.push('Email tidak valid');
        }
        
        if (!message || message.length < 10) {
            errors.push('Pesan minimal 10 karakter');
        }
        
        if (errors.length > 0) {
            showErrorMessages(errors);
            return false;
        }
        
        return true;
    }

    /**
     * Validate email format
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Show error messages
     */
    function showErrorMessages(errors) {
        const errorContainer = document.getElementById('form-errors');
        if (errorContainer) {
            errorContainer.innerHTML = '';
            errors.forEach(error => {
                const errorItem = document.createElement('div');
                errorItem.className = 'error-message';
                errorItem.textContent = error;
                errorContainer.appendChild(errorItem);
            });
        }
    }

    /**
     * Send contact form
     */
    function sendContactForm(name, email, message) {
        const submitButton = document.getElementById('contact-submit');
        const originalText = submitButton.textContent;
        
        submitButton.textContent = 'Memproses...';
        submitButton.disabled = true;
        
        fetch('../api/contact.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('input[name="csrf_token"]').value
            },
            body: JSON.stringify({
                name: name,
                email: email,
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessMessage('Pesan terkirim! Kami akan membalas segera.');
                contactForm.reset();
            } else {
                showErrorMessages([data.message || 'Terjadi kesalahan']);
            }
        })
        .catch(error => {
            showErrorMessages(['Terjadi kesalahan teknik']);
        })
        .finally(() => {
            submitButton.textContent = originalText;
            submitButton.disabled = false;
        });
    }

    // =====================================================
    // LOADING ANIMATION
    // =====================================================

    /**
     * Page loading animation
     */
    function initPageLoading() {
        const loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'loading-overlay';
        loadingOverlay.className = 'loading-overlay';
        loadingOverlay.innerHTML = `
            <div class="loading-spinner"></div>
            <p>Memuat sistem...</p>
        `;
        
        document.body.appendChild(loadingOverlay);
        
        window.addEventListener('load', function() {
            loadingOverlay.style.opacity = 0;
            setTimeout(() => {
                loadingOverlay.remove();
            }, 500);
        });
    }

    // =================================================
