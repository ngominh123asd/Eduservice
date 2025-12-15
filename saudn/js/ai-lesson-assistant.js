// AI Lesson Assistant Functions

class AILessonAssistant {
    constructor() {
        this.isActive = false;
        this.currentLessonId = null;
        this.currentFilePath = null;
        this.currentTab = 'summary';
        this.summaryLoaded = false;
        this.highlightsLoaded = false;
        this.quizLoaded = false;
        
        this.init();
    }
    
    init() {
        console.log('AI Lesson Assistant initializing...');
        
        // ✅ USE setTimeout TO ENSURE DOM IS READY
        setTimeout(() => {
            this.setupEventListeners();
        }, 500);
    }
    
    setupEventListeners() {
        // Tab switching
        const tabs = document.querySelectorAll('.ai-tab');
        console.log('Found tabs:', tabs.length);
        
        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                const tabName = tab.getAttribute('data-tab');
                console.log('Tab clicked:', tabName);
                this.switchTab(tabName);
            });
        });
        
        // Regenerate button
        const regenerateBtn = document.querySelector('.btn-ai-regenerate');
        if (regenerateBtn) {
            regenerateBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.regenerateContent();
            });
        }
        
        // Export button
        const exportBtn = document.querySelector('.btn-ai-export');
        if (exportBtn) {
            exportBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.exportSummary();
            });
        }
        
        console.log('Event listeners attached');
    }
    
    toggleAssistant() {
        if (this.isActive) {
            this.closeAssistant();
        } else {
            this.openAssistant();
        }
    }
    
    async openAssistant() {
        const assistant = document.getElementById('ai-lesson-assistant');
        const wrapper = document.querySelector('.lesson-viewer-content');
        
        if (!assistant || !wrapper) {
            console.error('AI Assistant elements not found');
            return;
        }
        
        // Activate
        assistant.classList.add('active');
        wrapper.classList.add('with-ai');
        this.isActive = true;
        
        console.log('AI Assistant opened');
        
        // ✅ Load content for current tab
        await this.loadContent(this.currentTab);
    }
    
    closeAssistant() {
        const assistant = document.getElementById('ai-lesson-assistant');
        const wrapper = document.querySelector('.lesson-viewer-content');
        
        if (assistant) {
            assistant.classList.remove('active');
        }
        if (wrapper) {
            wrapper.classList.remove('with-ai');
        }
        
        this.isActive = false;
        console.log('AI Assistant closed');
    }
    
    switchTab(tabName) {
        console.log('Switching to tab:', tabName);
        
        // Update active tab
        document.querySelectorAll('.ai-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        const activeTab = document.querySelector(`.ai-tab[data-tab="${tabName}"]`);
        if (activeTab) {
            activeTab.classList.add('active');
        }
        
        this.currentTab = tabName;
        
        // ✅ Load content for selected tab
        this.loadContent(tabName);
    }
    
    // ✅ ADD THIS METHOD
    async loadContent(tabName = this.currentTab) {
        console.log('Loading AI content for tab:', tabName);
        
        if (!this.currentLessonId && !this.currentFilePath) {
            console.error('No lesson selected');
            const container = document.getElementById('ai-lesson-content');
            if (container) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle" style="color: #FF9800;"></i>
                        <p>Vui lòng chọn một bài học</p>
                    </div>
                `;
            }
            return;
        }
        
        // Load content based on current tab
        switch (tabName) {
            case 'summary':
                if (!this.summaryLoaded) {
                    await this.loadSummary();
                }
                break;
            case 'highlights':
                if (!this.highlightsLoaded) {
                    await this.loadHighlights();
                }
                break;
            case 'quiz':
                if (!this.quizLoaded) {
                    await this.loadQuiz();
                }
                break;
        }
    }
    
    async loadSummary() {
        console.log('Loading summary...');
        
        const loadingDiv = document.getElementById('ai-summary-loading');
        const resultDiv = document.getElementById('ai-summary-result');
        const container = document.getElementById('ai-lesson-content');
        
        // Show loading state
        if (container) {
            container.innerHTML = `
                <div class="ai-loading">
                    <div class="ai-loading-spinner"></div>
                    <p>AI đang phân tích bài học...</p>
                </div>
            `;
        }
        
        try {
            const response = await fetch('api/ai-summarize.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    file_path: this.currentFilePath,
                    lesson_id: this.currentLessonId
                })
            });
            
            // Check content type
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                const text = await response.text();
                console.error('Server returned non-JSON response:', text);
                throw new Error("Server trả về dữ liệu không hợp lệ");
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.displaySummary(data.summary);
                this.summaryLoaded = true;
            } else {
                throw new Error(data.message || 'Không thể tải tóm tắt');
            }
            
        } catch (error) {
            console.error('Error loading summary:', error);
            
            if (container) {
                container.innerHTML = `
                    <div class="ai-error" style="text-align: center; padding: 40px 20px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #FFA726; margin-bottom: 16px;"></i>
                        <p style="color: var(--text-secondary); margin-bottom: 8px;">
                            Không thể tải tóm tắt AI
                        </p>
                        <p style="color: var(--text-secondary); font-size: 13px;">
                            ${error.message || 'Vui lòng thử lại sau'}
                        </p>
                        <button 
                            class="btn btn-outline" 
                            onclick="window.aiLessonAssistant.loadSummary()"
                            style="margin-top: 16px; padding: 10px 20px; border: 2px solid var(--primary-color); color: var(--primary-color); background: transparent; border-radius: 8px; cursor: pointer; font-weight: 600;"
                        >
                            <i class="fas fa-sync-alt"></i> Thử lại
                        </button>
                    </div>
                `;
            }
        }
    }
    
    displaySummary(summary) {
        const container = document.getElementById('ai-lesson-content');
        if (!container) return;
        
        // Handle both string and object responses
        if (typeof summary === 'string') {
            container.innerHTML = `
                <div class="ai-summary-result">
                    <h5><i class="fas fa-book-open"></i> Tóm tắt nội dung</h5>
                    <p>${summary}</p>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="ai-summary-result">
                    <h5><i class="fas fa-book-open"></i> Tóm tắt nội dung</h5>
                    <p>${summary.overview || 'Đang phân tích nội dung...'}</p>
                    
                    ${summary.key_points && summary.key_points.length > 0 ? `
                        <h5 style="margin-top: 20px;"><i class="fas fa-list-ul"></i> Các điểm chính</h5>
                        <ul>
                            ${summary.key_points.map(point => `<li>${point}</li>`).join('')}
                        </ul>
                    ` : ''}
                    
                    ${summary.conclusion ? `
                        <h5 style="margin-top: 20px;"><i class="fas fa-lightbulb"></i> Kết luận</h5>
                        <p>${summary.conclusion}</p>
                    ` : ''}
                </div>
            `;
        }
    }
    
    async loadHighlights() {
        console.log('Loading highlights...');
        
        const container = document.getElementById('ai-lesson-content');
        if (!container) return;
        
        container.innerHTML = `
            <div class="ai-loading">
                <div class="ai-loading-spinner"></div>
                <p>Đang tải điểm nổi bật...</p>
            </div>
        `;
        
        try {
            const response = await fetch('api/ai-highlights.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    file_path: this.currentFilePath,
                    lesson_id: this.currentLessonId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.displayHighlights(data.highlights);
                this.highlightsLoaded = true;
            } else {
                throw new Error(data.message);
            }
            
        } catch (error) {
            console.error('Error loading highlights:', error);
            container.innerHTML = `
                <div class="ai-error" style="text-align: center; padding: 40px 20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #FFA726; margin-bottom: 16px;"></i>
                    <p style="color: var(--text-secondary);">Không thể tải điểm nổi bật</p>
                    <button 
                        class="btn btn-outline" 
                        onclick="window.aiLessonAssistant.loadHighlights()"
                        style="margin-top: 16px;"
                    >
                        <i class="fas fa-sync-alt"></i> Thử lại
                    </button>
                </div>
            `;
        }
    }
    
    displayHighlights(highlights) {
        const container = document.getElementById('ai-lesson-content');
        if (!container) return;
        
        if (!highlights || highlights.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-highlighter"></i>
                    <p>Không có điểm nổi bật</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = `
            <div class="ai-highlights-list">
                ${highlights.map((highlight, index) => `
                    <div class="ai-highlight-item">
                        <div class="ai-highlight-header">
                            <i class="fas fa-star"></i>
                            <span>${highlight.category || `Điểm nổi bật ${index + 1}`}</span>
                        </div>
                        <div class="ai-highlight-text">${highlight.text || highlight}</div>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    async loadQuiz() {
        console.log('Loading quiz...');
        
        const container = document.getElementById('ai-lesson-content');
        if (!container) return;
        
        container.innerHTML = `
            <div class="ai-loading">
                <div class="ai-loading-spinner"></div>
                <p>Đang tạo câu hỏi...</p>
            </div>
        `;
        
        try {
            const response = await fetch('api/ai-quiz.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    file_path: this.currentFilePath,
                    lesson_id: this.currentLessonId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.displayQuiz(data.questions);
                this.quizLoaded = true;
            } else {
                throw new Error(data.message);
            }
            
        } catch (error) {
            console.error('Error loading quiz:', error);
            container.innerHTML = `
                <div class="ai-error" style="text-align: center; padding: 40px 20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #FFA726; margin-bottom: 16px;"></i>
                    <p style="color: var(--text-secondary);">Không thể tạo câu hỏi</p>
                    <button 
                        class="btn btn-outline" 
                        onclick="window.aiLessonAssistant.loadQuiz()"
                        style="margin-top: 16px;"
                    >
                        <i class="fas fa-sync-alt"></i> Thử lại
                    </button>
                </div>
            `;
        }
    }
    
    displayQuiz(questions) {
        const container = document.getElementById('ai-lesson-content');
        if (!container) return;
        
        if (!questions || questions.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-question-circle"></i>
                    <p>Không có câu hỏi</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = `
            <div class="ai-quiz-container">
                ${questions.map((q, index) => `
                    <div class="ai-quiz-item">
                        <div class="ai-quiz-question">
                            <strong>Câu ${index + 1}:</strong> ${q.question}
                        </div>
                        <div class="ai-quiz-options">
                            ${q.options.map((option, optIndex) => `
                                <div class="ai-quiz-option" 
                                     data-question="${index}" 
                                     data-option="${optIndex}"
                                     data-correct="${q.correct_answer}"
                                     onclick="aiSelectOption(this)">
                                    ${String.fromCharCode(65 + optIndex)}. ${option}
                                </div>
                            `).join('')}
                        </div>
                        <div class="ai-quiz-result" id="quiz-result-${index}" style="display: none;"></div>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    async regenerateContent() {
        console.log('Regenerating content for tab:', this.currentTab);
        
        // Reset loaded flag for current tab
        switch (this.currentTab) {
            case 'summary':
                this.summaryLoaded = false;
                break;
            case 'highlights':
                this.highlightsLoaded = false;
                break;
            case 'quiz':
                this.quizLoaded = false;
                break;
        }
        
        // Reload content
        await this.loadContent(this.currentTab);
        
        // Show success message
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
        
        Toast.fire({
            icon: 'success',
            title: 'Đã tạo lại nội dung AI'
        });
    }
    
    exportSummary() {
        const container = document.getElementById('ai-lesson-content');
        if (!container) return;
        
        // Get content text
        const contentText = container.innerText;
        const lessonTitle = document.getElementById('current-lesson-title')?.textContent || 'Bài học';
        
        // Create downloadable text file
        const blob = new Blob([contentText], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${lessonTitle} - AI ${this.currentTab}.txt`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
        // Show success message
        Swal.fire({
            icon: 'success',
            title: 'Đã xuất nội dung',
            text: 'File đã được tải xuống',
            timer: 2000,
            showConfirmButton: false
        });
    }
    
    setLessonId(lessonId) {
        this.currentLessonId = lessonId;
        // Reset loaded flags when changing lesson
        this.summaryLoaded = false;
        this.highlightsLoaded = false;
        this.quizLoaded = false;
    }
}

// ✅ GLOBAL FUNCTION FOR QUIZ SELECTION
function aiSelectOption(element) {
    const questionIndex = element.getAttribute('data-question');
    const selectedOption = parseInt(element.getAttribute('data-option'));
    const correctAnswer = parseInt(element.getAttribute('data-correct'));
    
    // Remove previous selections
    const siblings = element.parentElement.querySelectorAll('.ai-quiz-option');
    siblings.forEach(sib => {
        sib.classList.remove('selected', 'correct', 'incorrect');
    });
    
    // Mark selected
    element.classList.add('selected');
    
    // Show result
    const resultDiv = document.getElementById(`quiz-result-${questionIndex}`);
    
    if (selectedOption === correctAnswer) {
        element.classList.add('correct');
        resultDiv.style.display = 'block';
        resultDiv.style.padding = '12px';
        resultDiv.style.marginTop = '12px';
        resultDiv.style.borderRadius = '8px';
        resultDiv.style.background = '#E8F5E9';
        resultDiv.style.borderLeft = '4px solid #4CAF50';
        resultDiv.innerHTML = `
            <i class="fas fa-check-circle" style="color: #4CAF50;"></i>
            <strong style="color: #2E7D32;">Chính xác!</strong> Bạn đã chọn đúng đáp án.
        `;
    } else {
        element.classList.add('incorrect');
        
        // Highlight correct answer
        const correctOption = element.parentElement.querySelector(`[data-option="${correctAnswer}"]`);
        if (correctOption) {
            correctOption.classList.add('correct');
        }
        
        resultDiv.style.display = 'block';
        resultDiv.style.padding = '12px';
        resultDiv.style.marginTop = '12px';
        resultDiv.style.borderRadius = '8px';
        resultDiv.style.background = '#FFEBEE';
        resultDiv.style.borderLeft = '4px solid #F44336';
        resultDiv.innerHTML = `
            <i class="fas fa-times-circle" style="color: #F44336;"></i>
            <strong style="color: #C62828;">Chưa đúng!</strong> Đáp án đúng là <strong>${String.fromCharCode(65 + correctAnswer)}</strong>
        `;
    }
}

// Initialize AI Assistant when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.aiLessonAssistant = new AILessonAssistant();
    console.log('AI Lesson Assistant ready');
});