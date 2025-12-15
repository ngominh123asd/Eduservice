// E-Portfolio Functions

// ==================== INDEXEDDB STORAGE ====================
const DB_NAME = 'EPortfolioDB';
const DB_VERSION = 1;
const STORE_NAME = 'settings';

let db = null;

// Khởi tạo IndexedDB
function initDB() {
    return new Promise((resolve, reject) => {
        if (db) {
            resolve(db);
            return;
        }
        
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        
        request.onerror = (event) => {
            console.error('IndexedDB error:', event.target.error);
            reject(event.target.error);
        };
        
        request.onsuccess = (event) => {
            db = event.target.result;
            resolve(db);
        };
        
        request.onupgradeneeded = (event) => {
            const database = event.target.result;
            if (!database.objectStoreNames.contains(STORE_NAME)) {
                database.createObjectStore(STORE_NAME, { keyPath: 'id' });
            }
        };
    });
}

// Lưu dữ liệu vào IndexedDB
async function saveToIndexedDB(data) {
    try {
        const database = await initDB();
        return new Promise((resolve, reject) => {
            const transaction = database.transaction([STORE_NAME], 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.put({ id: 'portfolioSettings', data: data });
            
            request.onsuccess = () => resolve(true);
            request.onerror = (event) => reject(event.target.error);
        });
    } catch (error) {
        console.error('Error saving to IndexedDB:', error);
        return false;
    }
}

// Đọc dữ liệu từ IndexedDB
async function loadFromIndexedDB() {
    try {
        const database = await initDB();
        return new Promise((resolve, reject) => {
            const transaction = database.transaction([STORE_NAME], 'readonly');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.get('portfolioSettings');
            
            request.onsuccess = (event) => {
                if (event.target.result) {
                    resolve(event.target.result.data);
                } else {
                    resolve(null);
                }
            };
            request.onerror = (event) => reject(event.target.error);
        });
    } catch (error) {
        console.error('Error loading from IndexedDB:', error);
        return null;
    }
}

// Xóa dữ liệu trong IndexedDB
async function clearIndexedDB() {
    try {
        const database = await initDB();
        return new Promise((resolve, reject) => {
            const transaction = database.transaction([STORE_NAME], 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.delete('portfolioSettings');
            
            request.onsuccess = () => resolve(true);
            request.onerror = (event) => reject(event.target.error);
        });
    } catch (error) {
        console.error('Error clearing IndexedDB:', error);
        return false;
    }
}

// Migrate từ localStorage sang IndexedDB (chỉ chạy 1 lần)
async function migrateFromLocalStorage() {
    const oldData = localStorage.getItem('portfolioSettings');
    if (oldData) {
        try {
            const parsed = JSON.parse(oldData);
            await saveToIndexedDB(parsed);
            localStorage.removeItem('portfolioSettings'); // Xóa dữ liệu cũ
            console.log('Migrated data from localStorage to IndexedDB');
        } catch (error) {
            console.error('Migration error:', error);
        }
    }
}

// ==================== PORTFOLIO SETTINGS ====================

let portfolioSettings = {
    theme: 'default',
    backgroundImage: null,
    coverImage: null,
    avatarImage: null,
    quote: null,
    layout: 'default',
    cardPositions: {},
    cardSizes: {},
    iconColors: {},
    // Thêm galleries cho từng section - mỗi item là một folder chứa nhiều ảnh
    // Cấu trúc: [{name: string, images: [{image: base64, caption: string, date: string}]}]
    journeyGallery: [],
    skillsGallery: [],
    evidenceGallery: [],
    contactGallery: [],
    // Thông tin liên hệ tùy chỉnh
    contactInfo: {
        email: null,
        phone: null,
        address: null
    }
};

// Biến để theo dõi lightbox gallery
let currentLightboxGallery = [];
let currentLightboxIndex = 0;

let isEditMode = false;
let draggedElement = null;
let resizingElement = null;
let startX, startY, startWidth, startHeight;
let initialLeft, initialTop;

const CARD_GAP = 20; // Khoảng cách tối thiểu giữa các card (px)
const MIN_CARD_WIDTH = 150; // Chiều rộng tối thiểu
const MIN_CARD_HEIGHT = 80; // Chiều cao tối thiểu
const MAX_IMAGE_SIZE = 800; // Kích thước tối đa của ảnh (px)
const IMAGE_QUALITY = 0.7; // Chất lượng nén ảnh (0-1)

// Hàm nén ảnh trước khi lưu
function compressImage(base64String, maxSize = MAX_IMAGE_SIZE, quality = IMAGE_QUALITY) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
            let { width, height } = img;
            
            // Tính toán kích thước mới
            if (width > maxSize || height > maxSize) {
                if (width > height) {
                    height = (height / width) * maxSize;
                    width = maxSize;
                } else {
                    width = (width / height) * maxSize;
                    height = maxSize;
                }
            }
            
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);
            
            // Chuyển sang JPEG để giảm dung lượng
            const compressedBase64 = canvas.toDataURL('image/jpeg', quality);
            resolve(compressedBase64);
        };
        img.onerror = () => resolve(base64String); // Trả về ảnh gốc nếu lỗi
        img.src = base64String;
    });
}

// Tính dung lượng localStorage đang sử dụng
function getStorageUsage() {
    let total = 0;
    for (let key in localStorage) {
        if (localStorage.hasOwnProperty(key)) {
            total += localStorage[key].length * 2; // UTF-16
        }
    }
    return (total / (1024 * 1024)).toFixed(2); // MB
}

// Đọc file thành base64
function readFileAsBase64(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.readAsDataURL(file);
    });
}

async function loadEPortfolio() {
    const container = document.getElementById('eportfolio-section');
    if (!container) return;

    container.innerHTML = `
        <div style="text-align: center; padding: 60px 20px;">
            <div class="spinner" style="width: 60px; height: 60px; border: 4px solid #f3f3f3; border-top: 4px solid var(--primary-color); border-radius: 50%; margin: 0 auto 20px; animation: spin 1s linear infinite;"></div>
            <p style="color: var(--text-secondary);">Đang tải E-Portfolio...</p>
        </div>
    `;

    try {
        const response = await fetch('api/eportfolio.php');
        const data = await response.json();

        console.log('E-Portfolio data:', data);

        if (data.success) {
            await loadPortfolioSettings();
            renderEPortfolio(data);
            initScrollAnimations();
        } else {
            container.innerHTML = `
                <div class="empty-state" style="padding: 60px 20px; text-align: center;">
                    <i class="fas fa-exclamation-circle" style="font-size: 64px; color: #f44336; margin-bottom: 20px;"></i>
                    <h3 style="color: var(--text-primary); margin-bottom: 12px;">Không thể tải E-Portfolio</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 8px;">${data.message || 'Vui lòng thử lại sau'}</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading e-portfolio:', error);
        container.innerHTML = `
            <div class="empty-state" style="padding: 60px 20px; text-align: center;">
                <i class="fas fa-exclamation-circle" style="font-size: 64px; color: #f44336; margin-bottom: 20px;"></i>
                <h3 style="color: var(--text-primary); margin-bottom: 12px;">Lỗi khi tải dữ liệu</h3>
                <p style="color: var(--text-secondary);">Vui lòng kiểm tra kết nối và thử lại</p>
                <button onclick="loadEPortfolio()" style="margin-top: 20px; padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-redo"></i> Thử lại
                </button>
            </div>
        `;
    }
}

async function loadPortfolioSettings() {
    // Migrate từ localStorage sang IndexedDB nếu cần
    await migrateFromLocalStorage();
    
    // Đọc từ IndexedDB
    const saved = await loadFromIndexedDB();
    if (saved) {
        portfolioSettings = saved;
        await cleanupCardPositions();
    }
}

async function savePortfolioSettings() {
    try {
        const success = await saveToIndexedDB(portfolioSettings);
        if (!success) {
            throw new Error('Failed to save to IndexedDB');
        }
    } catch (error) {
        console.error('Error saving portfolio settings:', error);
        Swal.fire({
            title: 'Lỗi lưu dữ liệu!',
            text: 'Không thể lưu cài đặt. Vui lòng thử lại.',
            icon: 'error',
            confirmButtonColor: '#2E7D32'
        });
    }
}

function getCardStyle(cardId) {
    const position = portfolioSettings.cardPositions[cardId];
    const size = portfolioSettings.cardSizes[cardId];
    let style = '';
    
    if (position && (position.x !== 0 || position.y !== 0)) {
        style += `position: relative; left: ${position.x}px; top: ${position.y}px;`;
    }
    
    if (size) {
        if (size.width && size.width !== 'auto' && size.width !== '0px') {
            style += `width: ${size.width};`;
        }
    }
    
    return style;
}

function renderStatCard(id, icon, value, label, delay) {
    return `
        <div class="stat-card-flow scroll-reveal-scale" style="animation-delay: ${delay}s; ${portfolioSettings.iconColors[id] ? `--icon-color: ${portfolioSettings.iconColors[id]};` : ''}">
            <div class="stat-icon-flow" style="${portfolioSettings.iconColors[id] ? `background: linear-gradient(135deg, ${portfolioSettings.iconColors[id]}, ${portfolioSettings.iconColors[id]}dd);` : ''}">
                <i class="${icon}"></i>
                ${isEditMode ? `
                    <button class="color-picker-btn-small" onclick="changeIconColor('${id}')" title="Đổi màu">
                        <i class="fas fa-palette"></i>
                    </button>
                ` : ''}
            </div>
            <div class="stat-number-flow">${value}</div>
            <div class="stat-label-flow">${label}</div>
        </div>
    `;
}

function renderCustomizeModal(displayQuote) {
    const currentTheme = portfolioSettings.theme || 'default';
    const hasBackgroundImage = portfolioSettings.backgroundImage ? true : false;
    
    return `
        <div id="customize-modal" class="customize-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><i class="fas fa-palette"></i> Tùy chỉnh E-Portfolio</h2>
                    <button class="modal-close" onclick="closeCustomizeModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Theme Selection -->
                    <div class="customize-section">
                        <h3><i class="fas fa-swatchbook"></i> Chủ đề màu sắc</h3>
                        <div class="theme-options">
                            <div class="theme-option ${currentTheme === 'default' ? 'active' : ''}" 
                                 data-theme="default" 
                                 onclick="changeTheme('default')">
                                <div class="theme-preview"></div>
                                <div class="theme-name">Mặc định</div>
                            </div>
                            <div class="theme-option ${currentTheme === 'ocean' ? 'active' : ''}" 
                                 data-theme="ocean" 
                                 onclick="changeTheme('ocean')">
                                <div class="theme-preview"></div>
                                <div class="theme-name">Đại dương</div>
                            </div>
                            <div class="theme-option ${currentTheme === 'sunset' ? 'active' : ''}" 
                                 data-theme="sunset" 
                                 onclick="changeTheme('sunset')">
                                <div class="theme-preview"></div>
                                <div class="theme-name">Hoàng hôn</div>
                            </div>
                            <div class="theme-option ${currentTheme === 'forest' ? 'active' : ''}" 
                                 data-theme="forest" 
                                 onclick="changeTheme('forest')">
                                <div class="theme-preview"></div>
                                <div class="theme-name">Rừng xanh</div>
                            </div>
                            <div class="theme-option ${currentTheme === 'lavender' ? 'active' : ''}" 
                                 data-theme="lavender" 
                                 onclick="changeTheme('lavender')">
                                <div class="theme-preview"></div>
                                <div class="theme-name">Lavender</div>
                            </div>
                        </div>
                    </div>

                    <!-- Background Image -->
                    <div class="customize-section">
                        <h3><i class="fas fa-image"></i> Ảnh nền tùy chỉnh</h3>
                        <div class="btn-group">
                            <button class="btn-customize" onclick="uploadBackgroundImage()">
                                <i class="fas fa-upload"></i>
                                Tải ảnh nền lên
                            </button>
                            ${hasBackgroundImage ? `
                                <button class="btn-customize danger" onclick="removeBackground()">
                                    <i class="fas fa-trash"></i>
                                    Xóa ảnh nền
                                </button>
                            ` : ''}
                        </div>
                        ${hasBackgroundImage ? `
                            <div style="margin-top: 12px; padding: 12px; background: rgba(46, 125, 50, 0.1); border-radius: 8px; font-size: 14px; color: #2E7D32;">
                                <i class="fas fa-check-circle"></i> Đang sử dụng ảnh nền tùy chỉnh
                            </div>
                        ` : ''}
                    </div>

                    <!-- Quote Edit -->
                    <div class="customize-section">
                        <h3><i class="fas fa-quote-left"></i> Câu trích dẫn cá nhân</h3>
                        <div style="margin-bottom: 16px; padding: 16px; background: #f5f5f5; border-radius: 12px; font-style: italic; color: #666; font-size: 14px; line-height: 1.6;">
                            "${displayQuote || 'Chưa có câu trích dẫn'}"
                        </div>
                        <div class="btn-group">
                            <button class="btn-customize" onclick="editQuote()">
                                <i class="fas fa-edit"></i>
                                Chỉnh sửa câu trích dẫn
                            </button>
                        </div>
                    </div>

                    <!-- Reset Settings -->
                    <div class="customize-section">
                        <h3><i class="fas fa-undo"></i> Đặt lại cài đặt</h3>
                        <div class="btn-group">
                            <button class="btn-customize danger" onclick="resetAllSettings()">
                                <i class="fas fa-refresh"></i>
                                Đặt lại về mặc định
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function renderEPortfolio(data) {
    const container = document.getElementById('eportfolio-section');
    const { user, activities, skills, stats } = data;

    const userInitial = user.fullname ? user.fullname.charAt(0).toUpperCase() : 'U';
    const userName = user.fullname || 'Người dùng';
    const userSchool = user.school_name || 'EDUSERVICE';
    const userMajor = user.major || 'Thành viên tình nguyện';
    
    const defaultQuote = "Tổng hợp hành trình thiện nguyện và phát triển bản thân, thể hiện tinh thần trách nhiệm và giá trị nhân văn.";
    const displayQuote = portfolioSettings.quote || defaultQuote;

    const themeBackgrounds = {
        default: 'linear-gradient(135deg, #E8F5E9 0%, #F1F8E9 50%, #E3F2FD 100%)',
        ocean: 'linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 50%, #90CAF9 100%)',
        sunset: 'linear-gradient(135deg, #FFE0B2 0%, #FFCCBC 50%, #FFAB91 100%)',
        forest: 'linear-gradient(135deg, #C8E6C9 0%, #A5D6A7 50%, #81C784 100%)',
        lavender: 'linear-gradient(135deg, #E1BEE7 0%, #CE93D8 50%, #BA68C8 100%)'
    };

    const backgroundStyle = portfolioSettings.backgroundImage 
        ? `background-image: url(${portfolioSettings.backgroundImage}); background-size: cover; background-position: center;`
        : `background: ${themeBackgrounds[portfolioSettings.theme] || themeBackgrounds.default};`;

    container.innerHTML = `
        <div class="eportfolio-container" style="${backgroundStyle}">
            <div class="eportfolio-wrapper">
                <!-- Hero Section -->
                ${renderHeroSection(user, userName, userInitial, userMajor, userSchool, displayQuote)}

                <!-- Stats Section -->
                <div class="portfolio-stats draggable-card" data-card-id="stats" style="${getCardStyle('stats')}">
                    ${renderStatCard('activities', 'fas fa-trophy', stats.total_activities, 'Hoạt động tham gia', 0)}
                    ${renderStatCard('hours', 'fas fa-clock', stats.total_hours, 'Giờ học tập', 0.1)}
                    ${renderStatCard('score', 'fas fa-star', stats.avg_score, 'Điểm trung bình', 0.2)}
                    ${renderStatCard('skills', 'fas fa-certificate', stats.total_skills, 'Kỹ năng phát triển', 0.3)}
                    ${isEditMode ? '<div class="resize-handle"></div>' : ''}
                </div>

                <!-- Journey Section - Redesigned -->
                ${renderJourneySection(activities)}

                <!-- Skills Section - Redesigned -->
                ${renderSkillsSection(skills)}

                <!-- Evidence Section - Redesigned -->
                ${renderEvidenceSection(activities)}

                <!-- Contact Section - Redesigned -->
                ${renderContactSection(user)}
            </div>

            <!-- Edit Buttons -->
            <div class="edit-mode">
                ${!portfolioSettings.coverImage ? `
                    <button class="cover-add-btn" onclick="uploadCoverImage()" title="Thêm ảnh bìa">
                        <i class="fas fa-image"></i>
                    </button>
                ` : ''}
                <button class="customize-btn" onclick="openCustomizeModal()" title="Tùy chỉnh giao diện">
                    <i class="fas fa-palette"></i>
                </button>
                <button class="edit-btn ${isEditMode ? 'active' : ''}" onclick="toggleEditMode()" title="${isEditMode ? 'Thoát chế độ chỉnh sửa' : 'Chế độ chỉnh sửa'}">
                    <i class="fas ${isEditMode ? 'fa-check' : 'fa-edit'}"></i>
                </button>
            </div>
        </div>

        <!-- Customize Modal -->
        ${renderCustomizeModal(displayQuote)}
        
        <!-- Image Lightbox with Navigation -->
        <div id="image-lightbox" class="image-lightbox" onclick="closeLightbox()">
            <button class="lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></button>
            <button id="lightbox-prev" class="lightbox-nav lightbox-prev" onclick="lightboxPrev(event)"><i class="fas fa-chevron-left"></i></button>
            <img id="lightbox-image" src="" alt="" onclick="event.stopPropagation()">
            <button id="lightbox-next" class="lightbox-nav lightbox-next" onclick="lightboxNext(event)"><i class="fas fa-chevron-right"></i></button>
            <p id="lightbox-caption"></p>
        </div>
    `;

    if (isEditMode) {
        initDragAndDrop();
        initResize();
    }
    
    // Khởi tạo carousel tự động cho stack images
    initStackCarousel();
}

function renderHeroSection(user, userName, userInitial, userMajor, userSchool, displayQuote) {
    return `
        <div class="portfolio-hero scroll-reveal draggable-card" data-card-id="hero" style="position: relative; ${getCardStyle('hero')}">
            ${portfolioSettings.coverImage ? `
                <div class="hero-cover-image" style="
                    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                    background-image: url(${portfolioSettings.coverImage});
                    background-size: cover; background-position: center;
                    border-radius: 32px; z-index: 0;
                "></div>
                <div class="hero-overlay" style="
                    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                    background: linear-gradient(135deg, rgba(0, 0, 0, 0.75) 0%, rgba(0, 0, 0, 0.85) 100%);
                    border-radius: 32px; z-index: 1;
                "></div>
                <button class="cover-upload-btn" onclick="uploadCoverImage()" title="Thay đổi ảnh bìa" style="z-index: 10;">
                    <i class="fas fa-camera"></i>
                </button>
            ` : ''}
            
            <div style="position: relative; z-index: 2;">
                <div class="hero-avatar-container">
                    <div class="hero-avatar" style="${portfolioSettings.iconColors.avatar ? `background: ${portfolioSettings.iconColors.avatar};` : ''}">
                        ${portfolioSettings.avatarImage ? 
                            `<img src="${portfolioSettings.avatarImage}" alt="${userName}">` : 
                            userInitial
                        }
                    </div>
                    <button class="avatar-upload-btn" onclick="uploadAvatar()" title="Thay đổi ảnh đại diện">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
                <h1 class="hero-title" style="${portfolioSettings.coverImage ? 'color: white; text-shadow: 2px 2px 8px rgba(0,0,0,0.5);' : ''}">${userName}</h1>
                <p class="hero-subtitle" style="${portfolioSettings.coverImage ? 'color: rgba(255,255,255,0.95);' : ''}">${userMajor} | ${userSchool}</p>
                <div class="hero-quote" style="${portfolioSettings.coverImage ? 'color: rgba(255,255,255,0.95); background: rgba(255,255,255,0.15);' : ''}">
                    <i class="fas fa-quote-left" style="margin-right: 8px;"></i>
                    ${displayQuote}
                    <i class="fas fa-quote-right" style="margin-left: 8px;"></i>
                </div>
            </div>
            
            ${isEditMode ? '<div class="resize-handle"></div>' : ''}
        </div>
    `;
}

function renderJourneySection(activities) {
    const gallery = portfolioSettings.journeyGallery || [];
    const hasActivities = activities && activities.length > 0;
    const hasGallery = gallery.length > 0;
    const isEmpty = !hasActivities && !hasGallery;
    
    return `
        <div class="portfolio-section scroll-reveal draggable-card" data-card-id="journey" style="${getCardStyle('journey')}">
            <div class="section-header-new">
                <div class="section-icon-wrapper journey-icon">
                    <i class="fas fa-route"></i>
                </div>
                <div class="section-title-wrapper">
                    <h2>Hành Trình Học Tập</h2>
                    <p class="section-subtitle">Những bước chân ý nghĩa trên con đường học tập</p>
                </div>
                <button class="section-add-btn" onclick="addGalleryImage('journey')" title="Thêm ảnh">
                    <i class="fas fa-plus"></i>
                </button>
            </div>

            <!-- Gallery Grid -->
            ${hasGallery ? `
                <div class="portfolio-gallery ${gallery.length === 1 ? 'single' : gallery.length === 2 ? 'double' : 'multi'}">
                    ${gallery.map((folder, folderIndex) => renderGalleryFolder('journey', folder, folderIndex)).join('')}
                </div>
            ` : ''}

            <!-- Timeline -->
            ${hasActivities ? `
                <div class="journey-timeline">
                    ${activities.map((activity, index) => `
                        <div class="journey-card scroll-reveal-left" style="animation-delay: ${index * 0.1}s;">
                            <div class="journey-card-header">
                                <div class="journey-date-badge">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>${formatDate(activity.submitted_at)}</span>
                                </div>
                                ${activity.score !== null ? `
                                    <div class="journey-score-badge">
                                        <i class="fas fa-star"></i>
                                        <span>${activity.score}/${activity.max_score}</span>
                                    </div>
                                ` : ''}
                            </div>
                            <h3 class="journey-title">${activity.activity_name}</h3>
                            <div class="journey-org">
                                <i class="fas fa-building"></i>
                                <span>${activity.organization}</span>
                            </div>
                            <p class="journey-description">${activity.description || 'Không có mô tả'}</p>
                            ${activity.feedback ? `
                                <div class="journey-feedback">
                                    <i class="fas fa-comment-dots"></i>
                                    <p>${activity.feedback}</p>
                                </div>
                            ` : ''}
                        </div>
                    `).join('')}
                </div>
            ` : ''}

            <!-- Empty State - chỉ hiện khi cả gallery và activities đều trống -->
            ${isEmpty ? `
                <div class="empty-section">
                    <i class="fas fa-hiking"></i>
                    <p>Chưa có hoạt động thiện nguyện nào</p>
                    <span>Hãy bắt đầu hành trình của bạn!</span>
                </div>
            ` : ''}
            
            ${isEditMode ? '<div class="resize-handle"></div>' : ''}
        </div>
    `;
}

function renderSkillsSection(skills) {
    const gallery = portfolioSettings.skillsGallery || [];
    const hasGallery = gallery.length > 0;
    
    const skillMap = {
        theory: { icon: 'fas fa-book-open', name: 'Kiến thức lý thuyết', color: '#2196F3' },
        practice: { icon: 'fas fa-hands', name: 'Kỹ năng thực hành', color: '#FF9800' },
        test: { icon: 'fas fa-award', name: 'Chứng nhận đánh giá', color: '#9C27B0' }
    };

    const groupedSkills = skills ? skills.reduce((acc, skill) => {
        if (!acc[skill.lesson_type]) acc[skill.lesson_type] = [];
        acc[skill.lesson_type].push(skill);
        return acc;
    }, {}) : {};

    const hasSkills = Object.keys(groupedSkills).length > 0;
    const isEmpty = !hasSkills && !hasGallery;

    return `
        <div class="portfolio-section scroll-reveal draggable-card" data-card-id="skills-section" style="${getCardStyle('skills-section')}">
            <div class="section-header-new">
                <div class="section-icon-wrapper skills-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <div class="section-title-wrapper">
                    <h2>Kỹ Năng & Năng Lực</h2>
                    <p class="section-subtitle">Những giá trị và kỹ năng được phát triển qua hoạt động</p>
                </div>
                <button class="section-add-btn" onclick="addGalleryImage('skills')" title="Thêm ảnh chứng nhận">
                    <i class="fas fa-plus"></i>
                </button>
            </div>

            <!-- Gallery Grid for Certificates -->
            ${hasGallery ? `
                <div class="certificate-gallery">
                    ${gallery.map((folder, folderIndex) => renderCertificateFolder('skills', folder, folderIndex)).join('')}
                </div>
            ` : ''}

            <!-- Skills Grid -->
            ${hasSkills ? `
                <div class="skills-showcase">
                    ${Object.entries(groupedSkills).map(([type, items]) => {
                        const skillInfo = skillMap[type] || { icon: 'fas fa-star', name: type, color: '#4CAF50' };
                        return `
                            <div class="skill-category-card" style="--skill-color: ${skillInfo.color}">
                                <div class="skill-category-icon">
                                    <i class="${skillInfo.icon}"></i>
                                </div>
                                <div class="skill-category-content">
                                    <h3>${skillInfo.name}</h3>
                                    <div class="skill-progress">
                                        <div class="skill-progress-bar" style="width: ${Math.min(items.length * 20, 100)}%"></div>
                                    </div>
                                    <span class="skill-count">${items.length} bài học hoàn thành</span>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            ` : ''}

            <!-- Empty State -->
            ${isEmpty ? `
                <div class="empty-section">
                    <i class="fas fa-graduation-cap"></i>
                    <p>Chưa có kỹ năng nào được ghi nhận</p>
                    <span>Hoàn thành các bài học để phát triển kỹ năng</span>
                </div>
            ` : ''}
            
            ${isEditMode ? '<div class="resize-handle"></div>' : ''}
        </div>
    `;
}

function renderEvidenceSection(activities) {
    const gallery = portfolioSettings.evidenceGallery || [];
    const filesWithPath = activities ? activities.filter(a => a.file_path) : [];
    const hasGallery = gallery.length > 0;
    const hasFiles = filesWithPath.length > 0;
    const isEmpty = !hasGallery && !hasFiles;

    return `
        <div class="portfolio-section scroll-reveal draggable-card" data-card-id="evidence" style="${getCardStyle('evidence')}">
            <div class="section-header-new">
                <div class="section-icon-wrapper evidence-icon">
                    <i class="fas fa-images"></i>
                </div>
                <div class="section-title-wrapper">
                    <h2>Minh Chứng Hoạt Động</h2>
                    <p class="section-subtitle">Hình ảnh và tài liệu ghi lại quá trình tham gia</p>
                </div>
                <button class="section-add-btn" onclick="addGalleryImage('evidence')" title="Thêm minh chứng">
                    <i class="fas fa-plus"></i>
                </button>
            </div>

            <!-- Main Gallery -->
            ${!isEmpty ? `
                <div class="evidence-masonry ${(gallery.length + filesWithPath.length) <= 2 ? 'few-items' : ''}">
                    ${gallery.map((folder, folderIndex) => renderEvidenceFolder('evidence', folder, folderIndex)).join('')}
                    
                    ${filesWithPath.map((activity, index) => `
                        <div class="evidence-item file-item" onclick="viewSubmissionFile('${activity.file_path}')">
                            <div class="file-preview">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="evidence-overlay">
                                <p>${activity.activity_name}</p>
                                <span><i class="fas fa-clock"></i> ${formatDate(activity.submitted_at)}</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            ` : ''}

            <!-- Empty State -->
            ${isEmpty ? `
                <div class="empty-section">
                    <i class="fas fa-camera-retro"></i>
                    <p>Chưa có minh chứng nào</p>
                    <span>Thêm hình ảnh để ghi lại hoạt động của bạn</span>
                </div>
            ` : ''}
            
            ${isEditMode ? '<div class="resize-handle"></div>' : ''}
        </div>
    `;
}

function renderContactSection(user) {
    const gallery = portfolioSettings.contactGallery || [];
    
    // Lấy thông tin từ settings nếu có, nếu không thì từ user
    const contactInfo = portfolioSettings.contactInfo || {};
    const email = contactInfo.email || user.email || 'Chưa cập nhật';
    const phone = contactInfo.phone || user.phone || 'Chưa cập nhật';
    const address = contactInfo.address || user.address || 'Chưa cập nhật';

    return `
        <div class="portfolio-section scroll-reveal draggable-card" data-card-id="contact" style="${getCardStyle('contact')}">
            <div class="section-header-new">
                <div class="section-icon-wrapper contact-icon">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="section-title-wrapper">
                    <h2>Thông Tin Liên Hệ</h2>
                    <p class="section-subtitle">Kết nối và liên lạc</p>
                </div>
                <button class="section-add-btn" onclick="editContactInfo()" title="Sửa thông tin liên hệ">
                    <i class="fas fa-pen"></i>
                </button>
            </div>

            <div class="contact-layout">
                <!-- Contact Info -->
                <div class="contact-info-grid">
                    <div class="contact-info-card">
                        <div class="contact-info-icon email-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-info-content">
                            <label>Email</label>
                            <p>${email}</p>
                        </div>
                    </div>
                    <div class="contact-info-card">
                        <div class="contact-info-icon phone-icon">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <div class="contact-info-content">
                            <label>Điện thoại</label>
                            <p>${phone}</p>
                        </div>
                    </div>
                    <div class="contact-info-card">
                        <div class="contact-info-icon address-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-info-content">
                            <label>Địa chỉ</label>
                            <p>${address}</p>
                        </div>
                    </div>
                </div>

                <!-- Photo Gallery -->
                ${gallery.length > 0 ? `
                    <div class="contact-gallery">
                        ${gallery.map((item, index) => `
                            <div class="contact-photo" onclick="openLightbox('${item.image}', '${item.caption || ''}')">
                                <img src="${item.image}" alt="${item.caption || ''}">
                                ${item.caption ? `<span class="photo-caption">${item.caption}</span>` : ''}
                                ${isEditMode ? `
                                    <button class="gallery-delete-btn" onclick="event.stopPropagation(); deleteGalleryImage('contact', ${index})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                ` : ''}
                            </div>
                        `).join('')}
                    </div>
                ` : ''}
            </div>
            
            ${isEditMode ? '<div class="resize-handle"></div>' : ''}
        </div>
    `;
}

// ==================== STACK CAROUSEL FUNCTIONS ====================

let stackCarouselIntervals = [];

// Khởi tạo carousel tự động cho tất cả stack
function initStackCarousel() {
    // Xóa các interval cũ
    stackCarouselIntervals.forEach(interval => clearInterval(interval));
    stackCarouselIntervals = [];
    
    // Tìm tất cả các folder có nhiều hơn 1 ảnh
    const stackFolders = document.querySelectorAll('.gallery-folder.has-stack');
    
    stackFolders.forEach((folder) => {
        const stackImages = folder.querySelectorAll('.stack-image');
        if (stackImages.length <= 1) return;
        
        // Tạo interval cho mỗi folder - mỗi 5 giây chuyển ảnh
        const interval = setInterval(() => {
            rotateStackImages(folder);
        }, 5000);
        
        stackCarouselIntervals.push(interval);
    });
}

// Xoay ảnh trong stack
function rotateStackImages(folder) {
    const stackImages = folder.querySelectorAll('.stack-image');
    if (stackImages.length <= 1) return;
    
    // Tìm ảnh active hiện tại
    let activeIndex = -1;
    stackImages.forEach((img, idx) => {
        if (img.classList.contains('active')) {
            activeIndex = idx;
        }
    });
    
    if (activeIndex === -1) activeIndex = 0;
    
    // Chuyển sang ảnh tiếp theo
    const nextIndex = (activeIndex + 1) % stackImages.length;
    
    // Bỏ tất cả class cũ trước
    stackImages.forEach((img) => {
        img.classList.remove('to-back', 'from-back');
    });
    
    // Thêm class animation
    stackImages[activeIndex].classList.add('to-back');
    stackImages[activeIndex].classList.remove('active');
    
    stackImages[nextIndex].classList.add('active', 'from-back');
    
    // Sau animation, xóa class animation
    setTimeout(() => {
        stackImages.forEach((img) => {
            img.classList.remove('to-back', 'from-back');
        });
    }, 650);
}

// ==================== GALLERY FOLDER FUNCTIONS ====================

// Render gallery folder với stack effect
function renderGalleryFolder(section, folder, folderIndex) {
    // Hỗ trợ cả cấu trúc cũ (single image) và mới (folder với nhiều ảnh)
    const images = folder.images || [{ image: folder.image, caption: folder.caption, date: folder.date }];
    const folderName = folder.name || folder.caption || 'Album ' + (folderIndex + 1);
    const imageCount = images.length;
    const firstImage = images[0];
    
    // Tạo stack images (hiển thị tối đa 3 ảnh trong stack)
    const stackImages = images.slice(0, Math.min(3, imageCount));
    
    return `
        <div class="gallery-item gallery-folder ${imageCount > 1 ? 'has-stack' : ''}" 
             onclick="openFolderLightbox('${section}', ${folderIndex})"
             data-count="${imageCount}"
             data-section="${section}"
             data-folder="${folderIndex}">
            <!-- Stack container với carousel effect -->
            <div class="stack-container">
                ${stackImages.map((img, idx) => `
                    <div class="stack-image ${idx === 0 ? 'active' : ''}" 
                         data-index="${idx}"
                         style="background-image: url(${img.image});">
                    </div>
                `).join('')}
            </div>
            
            <div class="gallery-overlay">
                ${folderName ? `<p class="gallery-caption">${folderName}</p>` : ''}
                ${imageCount > 1 ? `<span class="gallery-count"><i class="fas fa-images"></i> ${imageCount} ảnh</span>` : ''}
                ${firstImage.date ? `<span class="gallery-date"><i class="fas fa-calendar"></i> ${firstImage.date}</span>` : ''}
            </div>
            
            ${isEditMode ? `
                <div class="folder-actions">
                    <button class="folder-add-btn" onclick="event.stopPropagation(); addImageToFolder('${section}', ${folderIndex})" title="Thêm ảnh vào album">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="gallery-delete-btn" onclick="event.stopPropagation(); deleteGalleryFolder('${section}', ${folderIndex})" title="Xóa album">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            ` : ''}
        </div>
    `;
}

// Render certificate folder
function renderCertificateFolder(section, folder, folderIndex) {
    const images = folder.images || [{ image: folder.image, caption: folder.caption, date: folder.date }];
    const folderName = folder.name || folder.caption || 'Chứng nhận ' + (folderIndex + 1);
    const imageCount = images.length;
    const firstImage = images[0];
    
    const stackImages = images.slice(0, Math.min(3, imageCount));
    
    return `
        <div class="certificate-item gallery-folder ${imageCount > 1 ? 'has-stack' : ''}" 
             onclick="openFolderLightbox('${section}', ${folderIndex})"
             data-count="${imageCount}"
             data-section="${section}"
             data-folder="${folderIndex}">
            <div class="stack-container">
                ${stackImages.map((img, idx) => `
                    <div class="stack-image ${idx === 0 ? 'active' : ''}" 
                         data-index="${idx}"
                         style="background-image: url(${img.image});">
                    </div>
                `).join('')}
            </div>
            
            <div class="certificate-info">
                ${folderName ? `<h4>${folderName}</h4>` : ''}
                ${imageCount > 1 ? `<span class="cert-count"><i class="fas fa-images"></i> ${imageCount}</span>` : ''}
                ${firstImage.date ? `<span><i class="fas fa-calendar"></i> ${firstImage.date}</span>` : ''}
            </div>
            
            ${isEditMode ? `
                <div class="folder-actions">
                    <button class="folder-add-btn" onclick="event.stopPropagation(); addImageToFolder('${section}', ${folderIndex})" title="Thêm ảnh">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="gallery-delete-btn" onclick="event.stopPropagation(); deleteGalleryFolder('${section}', ${folderIndex})" title="Xóa">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            ` : ''}
        </div>
    `;
}

// Render evidence folder
function renderEvidenceFolder(section, folder, folderIndex) {
    const images = folder.images || [{ image: folder.image, caption: folder.caption, date: folder.date }];
    const folderName = folder.name || folder.caption || '';
    const imageCount = images.length;
    const firstImage = images[0];
    
    const stackImages = images.slice(0, Math.min(3, imageCount));
    
    return `
        <div class="evidence-item gallery-folder ${imageCount > 1 ? 'has-stack' : ''}" 
             onclick="openFolderLightbox('${section}', ${folderIndex})"
             data-count="${imageCount}"
             data-section="${section}"
             data-folder="${folderIndex}">
            <div class="stack-container">
                ${stackImages.map((img, idx) => `
                    <div class="stack-image ${idx === 0 ? 'active' : ''}" 
                         data-index="${idx}"
                         style="background-image: url(${img.image});">
                    </div>
                `).join('')}
            </div>
            
            <div class="evidence-overlay">
                ${folderName ? `<p>${folderName}</p>` : ''}
                ${imageCount > 1 ? `<span class="evidence-count"><i class="fas fa-images"></i> ${imageCount} ảnh</span>` : ''}
                ${firstImage.date ? `<span><i class="fas fa-clock"></i> ${firstImage.date}</span>` : ''}
            </div>
            
            ${isEditMode ? `
                <div class="folder-actions">
                    <button class="folder-add-btn" onclick="event.stopPropagation(); addImageToFolder('${section}', ${folderIndex})" title="Thêm ảnh">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="gallery-delete-btn" onclick="event.stopPropagation(); deleteGalleryFolder('${section}', ${folderIndex})" title="Xóa">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            ` : ''}
        </div>
    `;
}

// Gallery Functions - Tạo album mới
function addGalleryImage(section) {
    Swal.fire({
        title: 'Tạo Album Ảnh Mới',
        html: `
            <div style="text-align: left;">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                        <i class="fas fa-folder" style="color: #FF9800; margin-right: 8px;"></i>Tên album
                    </label>
                    <input type="text" id="folderName" placeholder="VD: Hoạt động tình nguyện 2024..." 
                           style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                        <i class="fas fa-images" style="color: #2196F3; margin-right: 8px;"></i>Chọn ảnh (có thể chọn nhiều)
                    </label>
                    <input type="file" id="galleryImageInput" accept="image/*" multiple style="width: 100%;">
                </div>
                <div id="imagePreview" style="margin-top: 16px; display: none;">
                    <p style="font-size: 14px; color: #666; margin-bottom: 8px;">Ảnh đã chọn:</p>
                    <div id="previewContainer" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-plus"></i> Tạo album',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#2E7D32',
        width: 500,
        didOpen: () => {
            document.getElementById('galleryImageInput').addEventListener('change', (e) => {
                const files = e.target.files;
                const previewContainer = document.getElementById('previewContainer');
                previewContainer.innerHTML = '';
                
                if (files.length > 0) {
                    document.getElementById('imagePreview').style.display = 'block';
                    
                    Array.from(files).forEach((file, index) => {
                        const reader = new FileReader();
                        reader.onload = (event) => {
                            const img = document.createElement('img');
                            img.src = event.target.result;
                            img.style.cssText = 'width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid #e0e0e0;';
                            previewContainer.appendChild(img);
                        };
                        reader.readAsDataURL(file);
                    });
                }
            });
        },
        preConfirm: () => {
            const fileInput = document.getElementById('galleryImageInput');
            const folderName = document.getElementById('folderName').value.trim();
            
            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.showValidationMessage('Vui lòng chọn ít nhất một ảnh');
                return false;
            }
            
            // Hiển thị loading
            Swal.showLoading();
            
            return new Promise(async (resolve) => {
                const images = [];
                const files = Array.from(fileInput.files);
                
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const base64 = await readFileAsBase64(file);
                    const compressed = await compressImage(base64);
                    
                    images.push({
                        image: compressed,
                        caption: '',
                        date: new Date().toLocaleDateString('vi-VN')
                    });
                }
                
                resolve({
                    name: folderName || 'Album ' + new Date().toLocaleDateString('vi-VN'),
                    images: images
                });
            });
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            const galleryKey = section + 'Gallery';
            if (!portfolioSettings[galleryKey]) {
                portfolioSettings[galleryKey] = [];
            }
            portfolioSettings[galleryKey].push(result.value);
            await savePortfolioSettings();
            loadEPortfolio();
            
            Swal.fire({
                title: 'Đã tạo album!',
                text: `Album "${result.value.name}" với ${result.value.images.length} ảnh đã được tạo`,
                icon: 'success',
                confirmButtonColor: '#2E7D32',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

// Thêm ảnh vào folder có sẵn
function addImageToFolder(section, folderIndex) {
    const galleryKey = section + 'Gallery';
    const folder = portfolioSettings[galleryKey][folderIndex];
    
    // Hỗ trợ cấu trúc cũ
    if (!folder.images) {
        folder.images = [{ image: folder.image, caption: folder.caption, date: folder.date }];
        delete folder.image;
        delete folder.caption;
        delete folder.date;
    }
    
    Swal.fire({
        title: `Thêm ảnh vào "${folder.name || 'Album'}"`,
        html: `
            <div style="text-align: left;">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                        <i class="fas fa-images" style="color: #2196F3; margin-right: 8px;"></i>Chọn ảnh (có thể chọn nhiều)
                    </label>
                    <input type="file" id="galleryImageInput" accept="image/*" multiple style="width: 100%;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Mô tả chung (tùy chọn)</label>
                    <input type="text" id="galleryCaption" placeholder="Nhập mô tả..." 
                           style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; box-sizing: border-box;">
                </div>
                <div id="imagePreview" style="margin-top: 16px; display: none;">
                    <p style="font-size: 14px; color: #666; margin-bottom: 8px;">Ảnh đã chọn:</p>
                    <div id="previewContainer" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-plus"></i> Thêm ảnh',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#2E7D32',
        didOpen: () => {
            document.getElementById('galleryImageInput').addEventListener('change', (e) => {
                const files = e.target.files;
                const previewContainer = document.getElementById('previewContainer');
                previewContainer.innerHTML = '';
                
                if (files.length > 0) {
                    document.getElementById('imagePreview').style.display = 'block';
                    
                    Array.from(files).forEach((file) => {
                        const reader = new FileReader();
                        reader.onload = (event) => {
                            const img = document.createElement('img');
                            img.src = event.target.result;
                            img.style.cssText = 'width: 60px; height: 60px; object-fit: cover; border-radius: 8px;';
                            previewContainer.appendChild(img);
                        };
                        reader.readAsDataURL(file);
                    });
                }
            });
        },
        preConfirm: () => {
            const fileInput = document.getElementById('galleryImageInput');
            const caption = document.getElementById('galleryCaption').value;
            
            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.showValidationMessage('Vui lòng chọn ít nhất một ảnh');
                return false;
            }
            
            // Hiển thị loading
            Swal.showLoading();
            
            return new Promise(async (resolve) => {
                const newImages = [];
                const files = Array.from(fileInput.files);
                
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const base64 = await readFileAsBase64(file);
                    const compressed = await compressImage(base64);
                    
                    newImages.push({
                        image: compressed,
                        caption: caption,
                        date: new Date().toLocaleDateString('vi-VN')
                    });
                }
                
                resolve(newImages);
            });
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            folder.images.push(...result.value);
            await savePortfolioSettings();
            loadEPortfolio();
            
            Swal.fire({
                title: 'Đã thêm!',
                text: `${result.value.length} ảnh đã được thêm vào album`,
                icon: 'success',
                confirmButtonColor: '#2E7D32',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
}

// Xóa folder
function deleteGalleryFolder(section, folderIndex) {
    const galleryKey = section + 'Gallery';
    const folder = portfolioSettings[galleryKey][folderIndex];
    const images = folder.images || [folder];
    
    Swal.fire({
        title: 'Xóa album?',
        html: `Bạn có chắc muốn xóa album này?<br><small style="color: #666;">Album chứa ${images.length} ảnh</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#f44336'
    }).then(async (result) => {
        if (result.isConfirmed) {
            portfolioSettings[galleryKey].splice(folderIndex, 1);
            await savePortfolioSettings();
            loadEPortfolio();
        }
    });
}

// Mở lightbox cho folder với navigation
function openFolderLightbox(section, folderIndex) {
    const galleryKey = section + 'Gallery';
    const folder = portfolioSettings[galleryKey][folderIndex];
    
    // Hỗ trợ cấu trúc cũ
    const images = folder.images || [{ image: folder.image, caption: folder.caption, date: folder.date }];
    
    currentLightboxGallery = images;
    currentLightboxIndex = 0;
    
    showLightboxImage();
}

function showLightboxImage() {
    const lightbox = document.getElementById('image-lightbox');
    const img = document.getElementById('lightbox-image');
    const captionEl = document.getElementById('lightbox-caption');
    
    const currentImage = currentLightboxGallery[currentLightboxIndex];
    
    img.src = currentImage.image;
    captionEl.innerHTML = `
        ${currentImage.caption || ''}
        ${currentLightboxGallery.length > 1 ? `
            <span class="lightbox-counter">${currentLightboxIndex + 1} / ${currentLightboxGallery.length}</span>
        ` : ''}
    `;
    
    // Hiển thị/ẩn nút navigation
    const prevBtn = document.getElementById('lightbox-prev');
    const nextBtn = document.getElementById('lightbox-next');
    
    if (prevBtn && nextBtn) {
        prevBtn.style.display = currentLightboxGallery.length > 1 ? 'flex' : 'none';
        nextBtn.style.display = currentLightboxGallery.length > 1 ? 'flex' : 'none';
    }
    
    lightbox.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function lightboxPrev(e) {
    if (e) e.stopPropagation();
    currentLightboxIndex = (currentLightboxIndex - 1 + currentLightboxGallery.length) % currentLightboxGallery.length;
    showLightboxImage();
}

function lightboxNext(e) {
    if (e) e.stopPropagation();
    currentLightboxIndex = (currentLightboxIndex + 1) % currentLightboxGallery.length;
    showLightboxImage();
}

function openLightbox(imageSrc, caption) {
    currentLightboxGallery = [{ image: imageSrc, caption: caption }];
    currentLightboxIndex = 0;
    showLightboxImage();
}

function closeLightbox() {
    const lightbox = document.getElementById('image-lightbox');
    lightbox.classList.remove('active');
    document.body.style.overflow = 'auto';
    currentLightboxGallery = [];
    currentLightboxIndex = 0;
}

// Keyboard navigation cho lightbox
document.addEventListener('keydown', (e) => {
    const lightbox = document.getElementById('image-lightbox');
    if (lightbox && lightbox.classList.contains('active')) {
        if (e.key === 'ArrowLeft') {
            lightboxPrev();
        } else if (e.key === 'ArrowRight') {
            lightboxNext();
        } else if (e.key === 'Escape') {
            closeLightbox();
        }
    }
});

// Hàm sửa thông tin liên hệ
function editContactInfo() {
    const contactInfo = portfolioSettings.contactInfo || {};
    
    Swal.fire({
        title: 'Sửa thông tin liên hệ',
        html: `
            <div style="text-align: left;">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        <i class="fas fa-envelope" style="color: #4CAF50; margin-right: 8px;"></i>Email
                    </label>
                    <input type="email" id="contactEmail" value="${contactInfo.email || ''}" 
                           placeholder="example@email.com"
                           style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; transition: border-color 0.3s; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        <i class="fas fa-phone-alt" style="color: #2196F3; margin-right: 8px;"></i>Số điện thoại
                    </label>
                    <input type="tel" id="contactPhone" value="${contactInfo.phone || ''}" 
                           placeholder="0912 345 678"
                           style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; transition: border-color 0.3s; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 8px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        <i class="fas fa-map-marker-alt" style="color: #FF9800; margin-right: 8px;"></i>Địa chỉ
                    </label>
                    <textarea id="contactAddress" placeholder="Nhập địa chỉ của bạn..."
                              style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; min-height: 80px; resize: vertical; transition: border-color 0.3s; box-sizing: border-box;">${contactInfo.address || ''}</textarea>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> Lưu thay đổi',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#2E7D32',
        cancelButtonColor: '#757575',
        width: 500,
        preConfirm: () => {
            const email = document.getElementById('contactEmail').value.trim();
            const phone = document.getElementById('contactPhone').value.trim();
            const address = document.getElementById('contactAddress').value.trim();
            
            // Validate email nếu có nhập
            if (email && !isValidEmail(email)) {
                Swal.showValidationMessage('Email không hợp lệ');
                return false;
            }
            
            return { email, phone, address };
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            // Lưu vào portfolioSettings
            portfolioSettings.contactInfo = {
                email: result.value.email || null,
                phone: result.value.phone || null,
                address: result.value.address || null
            };
            
            await savePortfolioSettings();
            loadEPortfolio();
            
            Swal.fire({
                title: 'Đã lưu!',
                text: 'Thông tin liên hệ đã được cập nhật',
                icon: 'success',
                confirmButtonColor: '#2E7D32',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
}

// Hàm validate email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Scroll Animation với reset
function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
            } else {
                // Reset animation khi scroll ra khỏi viewport
                entry.target.classList.remove('revealed');
            }
        });
    }, observerOptions);

    document.querySelectorAll('.scroll-reveal, .scroll-reveal-left, .scroll-reveal-right, .scroll-reveal-scale').forEach(el => {
        observer.observe(el);
    });
}

// Toggle Edit Mode
async function toggleEditMode() {
    isEditMode = !isEditMode;
    
    if (isEditMode) {
        Swal.fire({
            title: 'Chế độ chỉnh sửa',
            html: `
                <p>✨ Bạn có thể:</p>
                <ul style="text-align: left; margin-top: 16px;">
                    <li>🖱️ <strong>Kéo thả</strong> các card để sắp xếp lại</li>
                    <li>📏 <strong>Thay đổi kích thước</strong> bằng cách kéo góc dưới phải</li>
                    <li>🎨 <strong>Đổi màu icon</strong> bằng nút palette trên mỗi section</li>
                    <li>💾 Nhấn <strong>✓</strong> để lưu thay đổi</li>
                </ul>
            `,
            icon: 'info',
            confirmButtonText: 'Bắt đầu',
            confirmButtonColor: '#2E7D32'
        }).then(() => {
            loadEPortfolio();
        });
    } else {
        await savePortfolioSettings();
        Swal.fire({
            title: 'Đã lưu!',
            text: 'Các thay đổi đã được lưu thành công',
            icon: 'success',
            confirmButtonColor: '#2E7D32',
            timer: 2000
        }).then(() => {
            loadEPortfolio();
        });
    }
}

// Drag and Drop
function initDragAndDrop() {
    const draggables = document.querySelectorAll('.draggable-card');
    
    draggables.forEach(card => {
        card.style.cursor = 'move';
        
        card.addEventListener('mousedown', startDrag);
    });
}

function startDrag(e) {
    // Bỏ qua nếu click vào các button hoặc resize handle
    if (e.target.classList.contains('resize-handle') || 
        e.target.classList.contains('color-picker-btn') ||
        e.target.classList.contains('color-picker-btn-small') ||
        e.target.closest('.color-picker-btn') ||
        e.target.closest('.color-picker-btn-small') ||
        e.target.closest('button')) {
        return;
    }
    
    // Tìm draggable-card gần nhất
    draggedElement = e.target.closest('.draggable-card');
    if (!draggedElement) return;
    
    e.preventDefault();
    
    // Lấy vị trí hiện tại của element
    const computedStyle = window.getComputedStyle(draggedElement);
    initialLeft = parseInt(computedStyle.left) || 0;
    initialTop = parseInt(computedStyle.top) || 0;
    
    // Lưu vị trí chuột ban đầu
    startX = e.clientX;
    startY = e.clientY;
    
    draggedElement.style.opacity = '0.8';
    draggedElement.style.zIndex = '1000';
    draggedElement.style.transition = 'none';
    
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', stopDrag);
}

function drag(e) {
    if (!draggedElement) return;
    
    e.preventDefault();
    
    // Tính khoảng cách di chuyển
    const deltaX = e.clientX - startX;
    const deltaY = e.clientY - startY;
    
    // Cập nhật vị trí mới dựa trên vị trí ban đầu + khoảng di chuyển
    const newLeft = initialLeft + deltaX;
    const newTop = initialTop + deltaY;
    
    draggedElement.style.position = 'relative';
    draggedElement.style.left = newLeft + 'px';
    draggedElement.style.top = newTop + 'px';
    
    // Kiểm tra và hiển thị cảnh báo va chạm
    highlightCollisions();
}

async function stopDrag() {
    if (!draggedElement) return;
    
    // Xóa highlight trước
    clearCollisionHighlights();
    
    const cardId = draggedElement.dataset.cardId;
    if (cardId) {
        // Kiểm tra và điều chỉnh vị trí nếu bị đè
        const adjustedPosition = adjustPositionForCollision(draggedElement);
        
        draggedElement.style.left = adjustedPosition.x + 'px';
        draggedElement.style.top = adjustedPosition.y + 'px';
        
        portfolioSettings.cardPositions[cardId] = {
            x: adjustedPosition.x,
            y: adjustedPosition.y
        };
        await savePortfolioSettings();
    }
    
    draggedElement.style.opacity = '1';
    draggedElement.style.zIndex = '';
    draggedElement.style.transition = 'left 0.3s ease, top 0.3s ease';
    draggedElement.style.boxShadow = '';
    
    // Reset transition sau khi animation hoàn tất
    setTimeout(() => {
        const cards = document.querySelectorAll('.draggable-card');
        cards.forEach(card => {
            card.style.transition = '';
        });
    }, 300);
    
    document.removeEventListener('mousemove', drag);
    document.removeEventListener('mouseup', stopDrag);
    
    draggedElement = null;
    initialLeft = 0;
    initialTop = 0;
}

// Highlight các card đang bị va chạm
function highlightCollisions() {
    if (!draggedElement) return;
    
    const allCards = document.querySelectorAll('.draggable-card');
    const currentRect = getElementRect(draggedElement);
    
    allCards.forEach(card => {
        if (card === draggedElement) return;
        
        const otherRect = getElementRect(card);
        
        if (isColliding(currentRect, otherRect, 0)) {
            // Đang va chạm - highlight màu đỏ
            card.style.boxShadow = '0 0 0 3px rgba(244, 67, 54, 0.5)';
            draggedElement.style.boxShadow = '0 0 0 3px rgba(244, 67, 54, 0.5)';
        } else if (isColliding(currentRect, otherRect, CARD_GAP)) {
            // Gần va chạm - highlight màu vàng
            card.style.boxShadow = '0 0 0 3px rgba(255, 193, 7, 0.5)';
        } else {
            // Không va chạm
            card.style.boxShadow = '';
        }
    });
}

// Xóa tất cả highlight khi dừng drag
function clearCollisionHighlights() {
    const allCards = document.querySelectorAll('.draggable-card');
    allCards.forEach(card => {
        card.style.boxShadow = '';
    });
}

// Kiểm tra 2 rect có va chạm không (có tính gap)
function isColliding(rect1, rect2, gap = CARD_GAP) {
    return !(
        rect1.right + gap < rect2.left ||
        rect1.left > rect2.right + gap ||
        rect1.bottom + gap < rect2.top ||
        rect1.top > rect2.bottom + gap
    );
}

// Lấy rect của element với vị trí thực tế (đã tính offset)
function getElementRect(element) {
    const rect = element.getBoundingClientRect();
    const wrapper = element.closest('.eportfolio-wrapper');
    if (!wrapper) return null;
    
    const wrapperRect = wrapper.getBoundingClientRect();
    
    return {
        left: rect.left - wrapperRect.left,
        top: rect.top - wrapperRect.top,
        right: rect.right - wrapperRect.left,
        bottom: rect.bottom - wrapperRect.top,
        width: rect.width,
        height: rect.height
    };
}

// Điều chỉnh vị trí để tránh va chạm - sửa lại logic
function adjustPositionForCollision(currentCard) {
    const allCards = document.querySelectorAll('.draggable-card');
    const wrapper = currentCard.closest('.eportfolio-wrapper');
    if (!wrapper) return { x: 0, y: 0 };
    
    const wrapperRect = wrapper.getBoundingClientRect();
    const currentRect = currentCard.getBoundingClientRect();
    
    // Vị trí hiện tại của card relative to wrapper
    let currentLeft = currentRect.left - wrapperRect.left;
    let currentTop = currentRect.top - wrapperRect.top;
    
    // Giữ nguyên vị trí style
    let newX = parseInt(currentCard.style.left) || 0;
    let newY = parseInt(currentCard.style.top) || 0;
    
    let hasCollision = true;
    let iterations = 0;
    const maxIterations = 30;
    
    while (hasCollision && iterations < maxIterations) {
        hasCollision = false;
        iterations++;
        
        // Tính rect giả định với vị trí mới
        const testRect = {
            left: currentLeft,
            top: currentTop,
            right: currentLeft + currentRect.width,
            bottom: currentTop + currentRect.height,
            width: currentRect.width,
            height: currentRect.height
        };
        
        for (const card of allCards) {
            if (card === currentCard) continue;
            
            const otherBoundingRect = card.getBoundingClientRect();
            const otherRect = {
                left: otherBoundingRect.left - wrapperRect.left,
                top: otherBoundingRect.top - wrapperRect.top,
                right: otherBoundingRect.right - wrapperRect.left,
                bottom: otherBoundingRect.bottom - wrapperRect.top,
                width: otherBoundingRect.width,
                height: otherBoundingRect.height
            };
            
            if (isColliding(testRect, otherRect)) {
                hasCollision = true;
                
                // Tính overlap theo từng hướng
                const overlapLeft = testRect.right - otherRect.left + CARD_GAP;
                const overlapRight = otherRect.right - testRect.left + CARD_GAP;
                const overlapTop = testRect.bottom - otherRect.top + CARD_GAP;
                const overlapBottom = otherRect.bottom - testRect.top + CARD_GAP;
                
                // Tìm hướng có overlap nhỏ nhất
                const minOverlap = Math.min(overlapLeft, overlapRight, overlapTop, overlapBottom);
                
                if (minOverlap === overlapTop) {
                    newY -= overlapTop;
                    currentTop -= overlapTop;
                } else if (minOverlap === overlapBottom) {
                    newY += overlapBottom;
                    currentTop += overlapBottom;
                } else if (minOverlap === overlapLeft) {
                    newX -= overlapLeft;
                    currentLeft -= overlapLeft;
                } else {
                    newX += overlapRight;
                    currentLeft += overlapRight;
                }
                
                break;
            }
        }
    }
    
    return { x: newX, y: newY };
}

// Resize Functions - cập nhật để hỗ trợ thu nhỏ
function initResize() {
    const handles = document.querySelectorAll('.resize-handle');
    
    handles.forEach(handle => {
        handle.addEventListener('mousedown', startResize);
    });
}

function startResize(e) {
    e.stopPropagation();
    e.preventDefault();
    
    resizingElement = e.target.closest('.draggable-card');
    if (!resizingElement) return;
    
    startX = e.clientX;
    startY = e.clientY;
    startWidth = resizingElement.offsetWidth;
    startHeight = resizingElement.offsetHeight;
    
    resizingElement.style.transition = 'none';
    resizingElement.classList.add('resizing');
    
    document.addEventListener('mousemove', resize);
    document.addEventListener('mouseup', stopResize);
}

function resize(e) {
    if (!resizingElement) return;
    
    e.preventDefault();
    
    const deltaX = e.clientX - startX;
    const deltaY = e.clientY - startY;
    
    const newWidth = Math.max(MIN_CARD_WIDTH, startWidth + deltaX);
    // Chỉ resize width, không resize height
    resizingElement.style.width = newWidth + 'px';
    
    // Visual feedback
    if (newWidth <= MIN_CARD_WIDTH) {
        resizingElement.style.boxShadow = '0 0 0 2px rgba(255, 152, 0, 0.5)';
    } else {
        resizingElement.style.boxShadow = '0 0 0 2px rgba(46, 125, 50, 0.5)';
    }
}

async function stopResize() {
    if (!resizingElement) return;
    
    const cardId = resizingElement.dataset.cardId;
    if (cardId) {
        const currentWidth = resizingElement.offsetWidth;
        
        // Reset height về auto để card tự co theo nội dung
        resizingElement.style.height = '';
        
        portfolioSettings.cardSizes[cardId] = {
            width: currentWidth + 'px',
            height: 'auto' // Luôn để auto
        };
        await savePortfolioSettings();
    }
    
    resizingElement.style.transition = '';
    resizingElement.style.boxShadow = '';
    resizingElement.classList.remove('resizing');
    
    document.removeEventListener('mousemove', resize);
    document.removeEventListener('mouseup', stopResize);
    
    resizingElement = null;
}

// Color Picker
function changeIconColor(iconId) {
    Swal.fire({
        title: 'Chọn màu cho icon',
        html: `
            <input type="color" id="colorPicker" value="${portfolioSettings.iconColors[iconId] || '#2E7D32'}" 
                   style="width: 100%; height: 60px; border: none; border-radius: 12px; cursor: pointer;">
        `,
        showCancelButton: true,
        confirmButtonText: 'Áp dụng',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#2E7D32',
        preConfirm: () => {
            return document.getElementById('colorPicker').value;
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            portfolioSettings.iconColors[iconId] = result.value;
            await savePortfolioSettings();
            loadEPortfolio();
        }
    });
}

// Upload Functions
function uploadAvatar() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async (e) => {
        const file = e.target.files[0];
        if (file) {
            try {
                Swal.fire({
                    title: 'Đang xử lý...',
                    text: 'Đang nén ảnh đại diện',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });
                
                const base64 = await readFileAsBase64(file);
                const compressed = await compressImage(base64, 400, 0.8); // Avatar nhỏ hơn
                
                portfolioSettings.avatarImage = compressed;
                await savePortfolioSettings();
                
                Swal.fire({
                    title: 'Thành công!',
                    text: 'Ảnh đại diện đã được cập nhật',
                    icon: 'success',
                    confirmButtonColor: '#2E7D32',
                    timer: 2000
                }).then(() => {
                    loadEPortfolio();
                });
            } catch (error) {
                console.error('Error uploading avatar:', error);
                Swal.fire('Lỗi!', 'Không thể tải ảnh lên', 'error');
            }
        }
    };
    input.click();
}

function uploadCoverImage() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async (e) => {
        const file = e.target.files[0];
        if (file) {
            try {
                Swal.fire({
                    title: 'Đang xử lý...',
                    text: 'Đang nén ảnh bìa',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });
                
                const base64 = await readFileAsBase64(file);
                const compressed = await compressImage(base64, 1200, 0.75); // Cover lớn hơn
                
                portfolioSettings.coverImage = compressed;
                await savePortfolioSettings();
                
                Swal.fire({
                    title: 'Thành công!',
                    text: 'Ảnh bìa đã được cập nhật',
                    icon: 'success',
                    confirmButtonColor: '#2E7D32',
                    timer: 2000
                }).then(() => {
                    loadEPortfolio();
                });
            } catch (error) {
                console.error('Error uploading cover:', error);
                Swal.fire('Lỗi!', 'Không thể tải ảnh lên', 'error');
            }
        }
    };
    input.click();
}

function uploadAvatarFromModal(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            portfolioSettings.avatarImage = e.target.result;
            document.querySelector('[for="avatarImageUpload"]').previousElementSibling?.remove();
            const preview = document.createElement('img');
            preview.src = e.target.result;
            preview.style.cssText = 'width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-color);';
            document.querySelector('[for="avatarImageUpload"]').parentElement.insertBefore(preview, document.querySelector('[for="avatarImageUpload"]'));
        };
        reader.readAsDataURL(file);
    }
}

function uploadCoverFromModal(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            portfolioSettings.coverImage = e.target.result;
            document.querySelector('[for="coverImageUpload"]').previousElementSibling?.remove();
            const preview = document.createElement('img');
            preview.src = e.target.result;
            preview.style.cssText = 'width: 160px; height: 90px; border-radius: 12px; object-fit: cover; border: 2px solid var(--primary-color);';
            document.querySelector('[for="coverImageUpload"]').parentElement.insertBefore(preview, document.querySelector('[for="coverImageUpload"]'));
        };
        reader.readAsDataURL(file);
    }
}

function removeAvatar() {
    portfolioSettings.avatarImage = null;
    Swal.fire({
        title: 'Đã xóa!',
        text: 'Ảnh đại diện đã được xóa',
        icon: 'success',
        confirmButtonColor: '#2E7D32',
        timer: 2000
    });
}

function removeCover() {
    portfolioSettings.coverImage = null;
    Swal.fire({
        title: 'Đã xóa!',
        text: 'Ảnh bìa đã được xóa',
        icon: 'success',
        confirmButtonColor: '#2E7D32',
        timer: 2000
    });
}

// Customize Modal
function openCustomizeModal() {
    console.log('Opening customize modal...');
    const modal = document.getElementById('customize-modal');
    console.log('Modal element:', modal);
    
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        console.log('Modal opened successfully');
    } else {
        console.error('Customize modal not found in DOM');
        // Create modal if not exists
        const displayQuote = portfolioSettings.quote || "Tổng hợp hành trình thiện nguyện và phát triển bản thân, thể hiện tinh thần trách nhiệm và giá trị nhân văn.";
        const modalHTML = renderCustomizeModal(displayQuote);
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Try again
        setTimeout(() => {
            const newModal = document.getElementById('customize-modal');
            if (newModal) {
                newModal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }, 100);
    }
}

function closeCustomizeModal() {
    const modal = document.getElementById('customize-modal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

async function changeTheme(theme) {
    portfolioSettings.theme = theme;
    
    // Remove active class from all theme options
    document.querySelectorAll('.theme-option').forEach(opt => {
        opt.classList.remove('active');
    });
    
    // Add active class to selected theme
    const selectedTheme = document.querySelector(`.theme-option[data-theme="${theme}"]`);
    if (selectedTheme) {
        selectedTheme.classList.add('active');
    }
    
    // Apply theme background
    const themeBackgrounds = {
        default: 'linear-gradient(135deg, #E8F5E9 0%, #F1F8E9 50%, #E3F2FD 100%)',
        ocean: 'linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 50%, #90CAF9 100%)',
        sunset: 'linear-gradient(135deg, #FFE0B2 0%, #FFCCBC 50%, #FFAB91 100%)',
        forest: 'linear-gradient(135deg, #C8E6C9 0%, #A5D6A7 50%, #81C784 100%)',
        lavender: 'linear-gradient(135deg, #E1BEE7 0%, #CE93D8 50%, #BA68C8 100%)'
    };
    
    const container = document.querySelector('.eportfolio-container');
    if (container && !portfolioSettings.backgroundImage) {
        container.style.background = themeBackgrounds[theme] || themeBackgrounds.default;
    }
    
    await savePortfolioSettings();
    
    Swal.fire({
        title: 'Đã đổi chủ đề!',
        text: `Chủ đề ${getThemeName(theme)} đã được áp dụng`,
        icon: 'success',
        confirmButtonColor: '#2E7D32',
        timer: 1500,
        showConfirmButton: false
    });
}

function getThemeName(theme) {
    const names = {
        default: 'Mặc định',
        ocean: 'Đại dương',
        sunset: 'Hoàng hôn',
        forest: 'Rừng xanh',
        lavender: 'Lavender'
    };
    return names[theme] || theme;
}

// Upload Background Image
function uploadBackgroundImage() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    
    input.onchange = async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validate file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({
                title: 'Lỗi!',
                text: 'File ảnh quá lớn. Vui lòng chọn ảnh dưới 5MB',
                icon: 'error',
                confirmButtonColor: '#2E7D32'
            });
            return;
        }
        
        // Read as base64
        const reader = new FileReader();
        reader.onload = async (event) => {
            portfolioSettings.backgroundImage = event.target.result;
            await savePortfolioSettings();
            
            // Apply background immediately
            const container = document.querySelector('.eportfolio-container');
            if (container) {
                container.style.backgroundImage = `url(${event.target.result})`;
                container.style.backgroundSize = 'cover';
                container.style.backgroundPosition = 'center';
            }
            
            Swal.fire({
                title: 'Thành công!',
                text: 'Đã cập nhật ảnh nền',
                icon: 'success',
                confirmButtonColor: '#2E7D32',
                timer: 1500,
                showConfirmButton: false
            });
        };
        reader.readAsDataURL(file);
    };
}

// Remove Background Image
async function removeBackground() {
    portfolioSettings.backgroundImage = null;
    await savePortfolioSettings();
    
    // Reset to theme background
    const themeBackgrounds = {
        default: 'linear-gradient(135deg, #E8F5E9 0%, #F1F8E9 50%, #E3F2FD 100%)',
        ocean: 'linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 50%, #90CAF9 100%)',
        sunset: 'linear-gradient(135deg, #FFE0B2 0%, #FFCCBC 50%, #FFAB91 100%)',
        forest: 'linear-gradient(135deg, #C8E6C9 0%, #A5D6A7 50%, #81C784 100%)',
        lavender: 'linear-gradient(135deg, #E1BEE7 0%, #CE93D8 50%, #BA68C8 100%)'
    };
    
    const container = document.querySelector('.eportfolio-container');
    if (container) {
        container.style.background = themeBackgrounds[portfolioSettings.theme] || themeBackgrounds.default;
        container.style.backgroundImage = 'none';
    }
    
    Swal.fire({
        title: 'Đã xóa!',
        text: 'Ảnh nền đã được xóa',
        icon: 'success',
        confirmButtonColor: '#2E7D32',
        timer: 1500,
        showConfirmButton: false
    });
}

// Edit Quote
function editQuote() {
    const currentQuote = portfolioSettings.quote || "Tổng hợp hành trình thiện nguyện và phát triển bản thân, thể hiện tinh thần trách nhiệm và giá trị nhân văn.";
    
    Swal.fire({
        title: 'Chỉnh sửa câu trích dẫn',
        input: 'textarea',
        inputValue: currentQuote,
        inputPlaceholder: 'Nhập câu trích dẫn của bạn...',
        inputAttributes: {
            'aria-label': 'Nhập câu trích dẫn',
            style: 'min-height: 100px; resize: vertical;'
        },
        showCancelButton: true,
        confirmButtonText: 'Lưu',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#2E7D32',
        cancelButtonColor: '#757575',
        preConfirm: (value) => {
            if (!value || value.trim().length === 0) {
                Swal.showValidationMessage('Vui lòng nhập câu trích dẫn');
                return false;
            }
            return value.trim();
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            portfolioSettings.quote = result.value;
            await savePortfolioSettings();
            
            // Update quote in DOM
            const quoteElement = document.querySelector('.hero-quote');
            if (quoteElement) {
                quoteElement.innerHTML = `
                    <i class="fas fa-quote-left" style="margin-right: 8px;"></i>
                    ${result.value}
                    <i class="fas fa-quote-right" style="margin-left: 8px;"></i>
                `;
            }
            
            Swal.fire({
                title: 'Đã lưu!',
                text: 'Câu trích dẫn đã được cập nhật',
                icon: 'success',
                confirmButtonColor: '#2E7D32',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
}

// Thêm hàm để reset layout nếu có vấn đề
async function cleanupCardPositions() {
    // Xóa các position không hợp lệ (0,0)
    Object.keys(portfolioSettings.cardPositions).forEach(key => {
        const pos = portfolioSettings.cardPositions[key];
        if (pos.x === 0 && pos.y === 0) {
            delete portfolioSettings.cardPositions[key];
        }
    });
    
    // Xóa các size không hợp lệ
    Object.keys(portfolioSettings.cardSizes).forEach(key => {
        const size = portfolioSettings.cardSizes[key];
        if (!size.width || size.width === 'auto' || size.width === '0px') {
            delete portfolioSettings.cardSizes[key];
        }
    });
    
    await savePortfolioSettings();
}

// Add spinner animation
const spinnerStyle = document.createElement('style');
spinnerStyle.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(spinnerStyle);

// Load E-Portfolio when menu item is clicked
document.addEventListener('DOMContentLoaded', function() {
    const eportfolioMenuItem = document.querySelector('[data-section="eportfolio"]');
    if (eportfolioMenuItem) {
        eportfolioMenuItem.addEventListener('click', loadEPortfolio);
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
        const modal = document.getElementById('customize-modal');
        if (modal && e.target === modal) {
            closeCustomizeModal();
        }
    });
});

// Auto load if eportfolio section is visible
function checkAndLoadEPortfolio() {
    const container = document.getElementById('eportfolio-section');
    if (container && container.offsetParent !== null && container.innerHTML.trim() === '') {
        loadEPortfolio();
    }
}

// Check periodically if section becomes visible
setInterval(checkAndLoadEPortfolio, 500);

// Helper functions - thêm các hàm còn thiếu
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${day}/${month}/${year} ${hours}:${minutes}`;
}

function viewSubmissionFile(filePath) {
    if (!filePath) {
        Swal.fire({
            title: 'Lỗi',
            text: 'Không tìm thấy file',
            icon: 'error',
            confirmButtonColor: '#2E7D32'
        });
        return;
    }
    window.open(filePath, '_blank');
}

// Reset Functions
async function resetLayout() {
    Swal.fire({
        title: 'Xác nhận đặt lại?',
        text: 'Tất cả vị trí và kích thước card sẽ về mặc định',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Đặt lại',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#f44336'
    }).then(async (result) => {
        if (result.isConfirmed) {
            portfolioSettings.cardPositions = {};
            portfolioSettings.cardSizes = {};
            await savePortfolioSettings();
            Swal.fire('Đã đặt lại!', 'Bố cục đã về mặc định', 'success').then(() => {
                loadEPortfolio();
            });
        }
    });
}

async function resetAllSettings() {
    Swal.fire({
        title: 'Đặt lại tất cả?',
        text: 'Bạn có chắc muốn đặt lại tất cả cài đặt về mặc định?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Đặt lại',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#f44336',
        cancelButtonColor: '#757575',
    }).then(async (result) => {
        if (result.isConfirmed) {
            portfolioSettings = {
                theme: 'default',
                backgroundImage: null,
                coverImage: null,
                avatarImage: null,
                quote: null,
                layout: 'default',
                cardPositions: {},
                cardSizes: {},
                iconColors: {},
                journeyGallery: [],
                skillsGallery: [],
                evidenceGallery: [],
                contactGallery: [],
                contactInfo: { email: null, phone: null, address: null }
            };
            
            // Xóa cả localStorage và IndexedDB
            localStorage.removeItem('portfolioSettings');
            await clearIndexedDB();
            await savePortfolioSettings();
            
            Swal.fire({
                title: 'Đã đặt lại!',
                text: 'Đang tải lại trang...',
                icon: 'success',
                confirmButtonColor: '#2E7D32',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                loadEPortfolio();
            });
        }
    });
}

// Close modal when clicking outside
document.addEventListener('click', (e) => {
    const modal = document.getElementById('customize-modal');
    if (modal && e.target === modal) {
        closeCustomizeModal();
    }
});

// Keyboard shortcut - ESC to close modal
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeCustomizeModal();
    }
});