// ==================== HEADER & FOOTER LOADING ====================
function loadComponents() {
    // Load header
    fetch('/components/header.php')
        .then(response => response.text())
        .then(data => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(data, 'text/html');
            
            // Copy styles
            const links = doc.head.getElementsByTagName('link');
            for(let link of links) {
                if(!document.querySelector(`link[href="${link.getAttribute('href')}"]`)) {
                    document.head.appendChild(link.cloneNode(true));
                }
            }
            
            document.getElementById('header-placeholder').innerHTML = doc.body.innerHTML;
        })
        .catch(error => console.error('Error loading header:', error));

    // Load footer
    fetch('/components/footer.html')
        .then(response => response.text())
        .then(data => {
            document.getElementById('footer-placeholder').innerHTML = data;
        })
        .catch(error => console.error('Error loading footer:', error));
}

// ==================== TAB NAVIGATION ====================
function initTabNavigation() {
    const tabs = document.querySelectorAll('.nav-tab');
    const sections = document.querySelectorAll('.content-section');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Remove active class from all
            tabs.forEach(t => t.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));

            // Add active class
            tab.classList.add('active');
            const sectionId = tab.dataset.section + '-section';
            const targetSection = document.getElementById(sectionId);
            
            if (targetSection) {
                targetSection.classList.add('active');
                
                // Smooth scroll to top
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });

                // Save current tab to localStorage
                localStorage.setItem('currentGuideTab', tab.dataset.section);
            }
        });
    });

    // Restore last active tab
    const savedTab = localStorage.getItem('currentGuideTab');
    if (savedTab) {
        const tabToActivate = document.querySelector(`[data-section="${savedTab}"]`);
        if (tabToActivate) {
            tabToActivate.click();
        }
    }
}

// ==================== VIDEO PLAYER ====================
function playVideo(videoId) {
    // Mapping video IDs to actual video URLs or paths
    const videoUrls = {
        'intro': '/videos/guides/student-intro.mp4',
        'join-class': '/videos/guides/join-class.mp4',
        'submit': '/videos/guides/submit-assignment.mp4'
    };

    const videoUrl = videoUrls[videoId];
    
    if (!videoUrl) {
        console.error('Video not found:', videoId);
        showNotification('Video không tồn tại', 'error');
        return;
    }

    // Create video modal
    const modal = document.createElement('div');
    modal.className = 'video-modal';
    modal.innerHTML = `
        <div class="video-modal-overlay" onclick="closeVideoModal()"></div>
        <div class="video-modal-content">
            <button class="video-modal-close" onclick="closeVideoModal()">
                <i class="fas fa-times"></i>
            </button>
            <video controls autoplay>
                <source src="${videoUrl}" type="video/mp4">
                Trình duyệt của bạn không hỗ trợ video.
            </video>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';

    // Add video modal styles dynamically
    if (!document.getElementById('video-modal-styles')) {
        const style = document.createElement('style');
        style.id = 'video-modal-styles';
        style.textContent = `
            .video-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.3s ease;
            }
            
            .video-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.9);
                cursor: pointer;
            }
            
            .video-modal-content {
                position: relative;
                max-width: 90%;
                max-height: 90%;
                z-index: 1;
                animation: scaleIn 0.3s ease;
            }
            
            .video-modal-content video {
                width: 100%;
                max-width: 1200px;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            }
            
            .video-modal-close {
                position: absolute;
                top: -40px;
                right: 0;
                background: white;
                border: none;
                width: 36px;
                height: 36px;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                color: #333;
                transition: all 0.3s ease;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            }
            
            .video-modal-close:hover {
                background: #f44336;
                color: white;
                transform: scale(1.1);
            }
            
            @keyframes scaleIn {
                from {
                    opacity: 0;
                    transform: scale(0.9);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }
        `;
        document.head.appendChild(style);
    }
}

function closeVideoModal() {
    const modal = document.querySelector('.video-modal');
    if (modal) {
        modal.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => {
            modal.remove();
            document.body.style.overflow = '';
        }, 300);
    }
}

// Close video modal on ESC key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeVideoModal();
    }
});

// ==================== KEYBOARD SHORTCUTS ====================
function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Ctrl/Cmd shortcuts
        if (e.ctrlKey || e.metaKey) {
            switch(e.key.toLowerCase()) {
                case 's':
                    e.preventDefault();
                    showNotification('Tính năng lưu nháp đang được phát triển', 'info');
                    break;
                case 'k':
                    e.preventDefault();
                    // Open AI assistant
                    window.location.href = '/saudn/trangchusaudn.php#ai-chat';
                    break;
            }
        }
        
        // Alt shortcuts
        if (e.altKey) {
            switch(e.key.toLowerCase()) {
                case 'h':
                    e.preventDefault();
                    window.location.href = '/saudn/trangchusaudn.php';
                    break;
                case 'c':
                    e.preventDefault();
                    // Navigate to classes section
                    const classTab = document.querySelector('[data-section="classes"]');
                    if (classTab) classTab.click();
                    break;
                case 'a':
                    e.preventDefault();
                    // Navigate to assignments section
                    const assignmentTab = document.querySelector('[data-section="assignments"]');
                    if (assignmentTab) assignmentTab.click();
                    break;
                case 'p':
                    e.preventDefault();
                    // Navigate to portfolio section
                    const portfolioTab = document.querySelector('[data-section="eportfolio"]');
                    if (portfolioTab) portfolioTab.click();
                    break;
            }
        }
    });
}

// ==================== NOTIFICATION SYSTEM ====================
function showNotification(message, type = 'info') {
    // Remove existing notification
    const existing = document.querySelector('.guide-notification');
    if (existing) {
        existing.remove();
    }

    const notification = document.createElement('div');
    notification.className = `guide-notification ${type}`;
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-times-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    notification.innerHTML = `
        <i class="fas ${icons[type]}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Add notification styles
    if (!document.getElementById('notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .guide-notification {
                position: fixed;
                top: 80px;
                right: 24px;
                padding: 16px 24px;
                border-radius: 12px;
                background: white;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
                display: flex;
                align-items: center;
                gap: 12px;
                z-index: 9999;
                animation: slideInRight 0.3s ease;
                font-weight: 500;
                max-width: 400px;
            }
            
            .guide-notification i {
                font-size: 20px;
            }
            
            .guide-notification.success {
                background: #4CAF50;
                color: white;
            }
            
            .guide-notification.error {
                background: #F44336;
                color: white;
            }
            
            .guide-notification.warning {
                background: #FF9800;
                color: white;
            }
            
            .guide-notification.info {
                background: #2196F3;
                color: white;
            }
            
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            @keyframes slideOutRight {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(100px);
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// ==================== GUIDE CARD INTERACTIONS ====================
function initGuideCardAnimations() {
    const guideCards = document.querySelectorAll('.guide-card');
    
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    guideCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'all 0.5s ease';
        observer.observe(card);
    });
}

// ==================== SEARCH FUNCTIONALITY ====================
function initSearch() {
    const searchInput = document.createElement('div');
    searchInput.className = 'guide-search';
    searchInput.innerHTML = `
        <input type="text" id="guide-search-input" placeholder="Tìm kiếm hướng dẫn... (Ctrl + /)">
        <i class="fas fa-search"></i>
    `;
    
    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(searchInput, container.firstChild);
    }
    
    const input = document.getElementById('guide-search-input');
    
    // Focus on Ctrl+/ or Cmd+/
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === '/') {
            e.preventDefault();
            input.focus();
        }
    });
    
    // Search functionality
    input.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const guideCards = document.querySelectorAll('.guide-card');
        
        guideCards.forEach(card => {
            const text = card.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                card.style.display = 'block';
                // Highlight matching text
                highlightText(card, searchTerm);
            } else {
                card.style.display = 'none';
            }
        });
    });
    
    // Add search styles
    if (!document.getElementById('search-styles')) {
        const style = document.createElement('style');
        style.id = 'search-styles';
        style.textContent = `
            .guide-search {
                margin-bottom: 40px;
                position: relative;
                max-width: 600px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .guide-search input {
                width: 100%;
                padding: 14px 48px 14px 20px;
                border: 2px solid var(--border-color);
                border-radius: 12px;
                font-size: 16px;
                font-family: 'Lexend', sans-serif;
                transition: all 0.3s ease;
                background: var(--card-bg);
                color: var(--text-primary);
            }
            
            .guide-search input:focus {
                outline: none;
                border-color: var(--primary-color);
                box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.1);
            }
            
            .guide-search i {
                position: absolute;
                right: 20px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--text-secondary);
                font-size: 18px;
            }
            
            .highlight {
                background: yellow;
                padding: 2px 4px;
                border-radius: 3px;
            }
        `;
        document.head.appendChild(style);
    }
}

function highlightText(element, searchTerm) {
    if (!searchTerm) return;
    
    const walker = document.createTreeWalker(
        element,
        NodeFilter.SHOW_TEXT,
        null,
        false
    );
    
    const nodesToReplace = [];
    while (walker.nextNode()) {
        const node = walker.currentNode;
        if (node.textContent.toLowerCase().includes(searchTerm)) {
            nodesToReplace.push(node);
        }
    }
    
    nodesToReplace.forEach(node => {
        const span = document.createElement('span');
        const regex = new RegExp(`(${searchTerm})`, 'gi');
        span.innerHTML = node.textContent.replace(regex, '<span class="highlight">$1</span>');
        node.parentNode.replaceChild(span, node);
    });
}

// ==================== PRINT FUNCTIONALITY ====================
function initPrintButton() {
    const printBtn = document.createElement('button');
    printBtn.className = 'print-guide-btn';
    printBtn.innerHTML = '<i class="fas fa-print"></i> In hướng dẫn';
    printBtn.onclick = () => window.print();
    
    const header = document.querySelector('.guide-header');
    if (header) {
        header.appendChild(printBtn);
    }
    
    // Add print button styles
    if (!document.getElementById('print-styles')) {
        const style = document.createElement('style');
        style.id = 'print-styles';
        style.textContent = `
            .print-guide-btn {
                position: fixed;
                bottom: 24px;
                right: 24px;
                padding: 14px 24px;
                background: var(--primary-color);
                color: white;
                border: none;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
                box-shadow: 0 4px 16px rgba(46, 125, 50, 0.3);
                transition: all 0.3s ease;
                z-index: 999;
            }
            
            .print-guide-btn:hover {
                background: var(--primary-dark);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(46, 125, 50, 0.4);
            }
            
            @media print {
                .print-guide-btn,
                .nav-tabs,
                .guide-search,
                .video-section {
                    display: none !important;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// ==================== PROGRESS TRACKING ====================
function trackProgress() {
    const sections = document.querySelectorAll('.content-section');
    const progress = localStorage.getItem('guideProgress') || '{}';
    const progressData = JSON.parse(progress);
    
    sections.forEach(section => {
        const sectionId = section.id.replace('-section', '');
        if (progressData[sectionId]) {
            section.classList.add('visited');
        }
    });
    
    // Track when user visits a section
    const tabs = document.querySelectorAll('.nav-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const sectionId = tab.dataset.section;
            progressData[sectionId] = true;
            localStorage.setItem('guideProgress', JSON.stringify(progressData));
        });
    });
}

// ==================== BACK TO TOP BUTTON ====================
function initBackToTop() {
    const backToTopBtn = document.createElement('button');
    backToTopBtn.className = 'back-to-top';
    backToTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
    backToTopBtn.onclick = () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    };
    
    document.body.appendChild(backToTopBtn);
    
    // Show/hide based on scroll
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            backToTopBtn.classList.add('visible');
        } else {
            backToTopBtn.classList.remove('visible');
        }
    });
    
    // Add styles
    if (!document.getElementById('back-to-top-styles')) {
        const style = document.createElement('style');
        style.id = 'back-to-top-styles';
        style.textContent = `
            .back-to-top {
                position: fixed;
                bottom: 80px;
                right: 24px;
                width: 48px;
                height: 48px;
                background: var(--primary-color);
                color: white;
                border: none;
                border-radius: 50%;
                font-size: 20px;
                cursor: pointer;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
                box-shadow: 0 4px 16px rgba(46, 125, 50, 0.3);
                z-index: 998;
            }
            
            .back-to-top.visible {
                opacity: 1;
                visibility: visible;
            }
            
            .back-to-top:hover {
                background: var(--primary-dark);
                transform: translateY(-4px);
                box-shadow: 0 6px 20px rgba(46, 125, 50, 0.4);
            }
        `;
        document.head.appendChild(style);
    }
}

// ==================== INITIALIZE ALL ====================
document.addEventListener('DOMContentLoaded', () => {
    loadComponents();
    initTabNavigation();
    initKeyboardShortcuts();
    initGuideCardAnimations();
    initSearch();
    initPrintButton();
    trackProgress();
    initBackToTop();
    
    console.log('Student Guide initialized successfully! 🎓');
});

// ==================== EXPORT FUNCTIONS ====================
window.playVideo = playVideo;
window.closeVideoModal = closeVideoModal;
window.showNotification = showNotification;