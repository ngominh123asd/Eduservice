/**
 * Content Management JavaScript
 */

const Content = {
    // Initialize
    init: function() {
        this.loadContent();
        this.bindEvents();
    },

    // Load content based on current tab
    loadContent: function() {
        switch(currentTab) {
            case 'lessons':
                this.loadPendingLessons();
                this.loadAllLessons();
                break;
            case 'assignments':
                this.loadPendingSubmissions();
                break;
            case 'documents':
                this.loadDocuments();
                break;
        }
    },

    // Bind events
    bindEvents: function() {
        // Search and filter for lessons
        const lessonsSearch = document.getElementById('lessons-search');
        const lessonsFilter = document.getElementById('lessons-status-filter');
        
        if (lessonsSearch) {
            lessonsSearch.addEventListener('input', this.debounce(() => this.loadAllLessons(), 300));
        }
        if (lessonsFilter) {
            lessonsFilter.addEventListener('change', () => this.loadAllLessons());
        }

        // Search and filter for documents
        const docsSearch = document.getElementById('documents-search');
        const docsFilter = document.getElementById('documents-type-filter');
        
        if (docsSearch) {
            docsSearch.addEventListener('input', this.debounce(() => this.loadDocuments(), 300));
        }
        if (docsFilter) {
            docsFilter.addEventListener('change', () => this.loadDocuments());
        }

        // Modal close on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            });
        });
    },

    // Debounce helper
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // Load pending lessons
    loadPendingLessons: async function() {
        const container = document.getElementById('pending-lessons-list');
        if (!container) return;

        try {
            const response = await fetch('api/content.php?action=get_pending_lessons');
            const data = await response.json();

            if (data.success && data.lessons.length > 0) {
                container.innerHTML = data.lessons.map(lesson => this.renderLessonItem(lesson, true)).join('');
                document.getElementById('pending-count').textContent = data.lessons.length;
            } else {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>Không có bài học chờ duyệt</h3>
                        <p>Tất cả bài học đã được xử lý</p>
                    </div>
                `;
                document.getElementById('pending-count').textContent = '0';
            }
        } catch (error) {
            console.error('Error loading pending lessons:', error);
            container.innerHTML = '<div class="empty-state"><p>Lỗi tải dữ liệu</p></div>';
        }
    },

    // Load all lessons
    loadAllLessons: async function() {
        const container = document.getElementById('all-lessons-list');
        if (!container) return;

        const search = document.getElementById('lessons-search')?.value || '';
        const status = document.getElementById('lessons-status-filter')?.value || '';

        container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';

        try {
            const response = await fetch(`api/content.php?action=get_lessons&search=${encodeURIComponent(search)}&status=${status}`);
            const data = await response.json();

            if (data.success && data.lessons.length > 0) {
                container.innerHTML = data.lessons.map(lesson => this.renderLessonItem(lesson, false)).join('');
            } else {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h3>Không tìm thấy bài học</h3>
                        <p>Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading lessons:', error);
            container.innerHTML = '<div class="empty-state"><p>Lỗi tải dữ liệu</p></div>';
        }
    },

    // Render lesson item
    renderLessonItem: function(lesson, isPending) {
        const typeIcons = {
            'theory': 'fa-book',
            'practice': 'fa-laptop-code',
            'test': 'fa-clipboard-check'
        };
        const statusLabels = {
            'published': 'Đã xuất bản',
            'pending': 'Chờ duyệt',
            'rejected': 'Từ chối',
            'draft': 'Bản nháp'
        };

        const icon = typeIcons[lesson.lesson_type] || 'fa-book';
        const statusLabel = statusLabels[lesson.status] || lesson.status;

        let actions = `
            <button class="btn-preview" onclick="Content.previewLesson(${lesson.id})" title="Xem trước">
                <i class="fas fa-eye"></i>
            </button>
        `;

        if (isPending) {
            actions += `
                <button class="btn-approve" onclick="Content.approveLesson(${lesson.id})" title="Duyệt">
                    <i class="fas fa-check"></i>
                </button>
                <button class="btn-reject" onclick="Content.showRejectModal(${lesson.id})" title="Từ chối">
                    <i class="fas fa-times"></i>
                </button>
            `;
        } else {
            actions += `
                <button class="btn-delete" onclick="Content.deleteLesson(${lesson.id}, '${this.escapeHtml(lesson.title)}')" title="Xóa">
                    <i class="fas fa-trash"></i>
                </button>
            `;
        }

        return `
            <div class="lesson-item" data-lesson-id="${lesson.id}">
                <div class="lesson-icon ${lesson.lesson_type || 'theory'}">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="lesson-info">
                    <div class="lesson-title">${this.escapeHtml(lesson.title)}</div>
                    <div class="lesson-meta">
                        <span><i class="fas fa-chalkboard"></i> ${this.escapeHtml(lesson.class_name || 'N/A')}</span>
                        <span><i class="fas fa-user"></i> ${this.escapeHtml(lesson.teacher_name || 'N/A')}</span>
                        <span><i class="fas fa-calendar"></i> ${lesson.created_at ? this.formatDate(lesson.created_at) : 'N/A'}</span>
                    </div>
                </div>
                ${!isPending ? `
                <div class="lesson-stats">
                    <div>
                        <div class="lesson-stat-value">${lesson.views || 0}</div>
                        <div class="lesson-stat-label">Lượt xem</div>
                    </div>
                    <div>
                        <div class="lesson-stat-value">${lesson.avg_duration ? this.formatDuration(lesson.avg_duration) : '-'}</div>
                        <div class="lesson-stat-label">TB thời gian</div>
                    </div>
                </div>
                ` : ''}
                <span class="lesson-status status-${lesson.status || 'draft'}">${statusLabel}</span>
                <div class="lesson-actions">
                    ${actions}
                </div>
            </div>
        `;
    },

    // Load pending submissions
    loadPendingSubmissions: async function() {
        const container = document.getElementById('pending-submissions-list');
        if (!container) return;

        try {
            const response = await fetch('api/content.php?action=get_pending_submissions');
            const data = await response.json();

            if (data.success && data.submissions.length > 0) {
                container.innerHTML = data.submissions.map(sub => this.renderSubmissionItem(sub)).join('');
            } else {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>Không có bài chờ chấm</h3>
                        <p>Tất cả bài nộp đã được chấm điểm</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading submissions:', error);
            container.innerHTML = '<div class="empty-state"><p>Lỗi tải dữ liệu</p></div>';
        }
    },

    // Render submission item
    renderSubmissionItem: function(sub) {
        const isLate = sub.due_date && new Date(sub.submitted_at) > new Date(sub.due_date);
        const initials = (sub.student_name || 'U').charAt(0).toUpperCase();

        return `
            <div class="submission-item" data-submission-id="${sub.id}">
                <div class="submission-avatar">${initials}</div>
                <div class="submission-info">
                    <div class="submission-title">${this.escapeHtml(sub.assignment_title)}</div>
                    <div class="submission-meta">
                        ${this.escapeHtml(sub.student_name)} • 
                        ${this.escapeHtml(sub.class_name || 'N/A')} •
                        Nộp lúc: ${this.formatDateTime(sub.submitted_at)}
                    </div>
                </div>
                <span class="submission-status ${isLate ? 'late' : 'ontime'}">
                    ${isLate ? 'Nộp muộn' : 'Đúng hạn'}
                </span>
                <div class="lesson-actions">
                    <button class="btn-preview" onclick="Content.viewSubmission(${sub.id})" title="Xem bài">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-grade" onclick="Content.showGradeModal(${sub.id}, '${this.escapeHtml(sub.assignment_title)}', '${this.escapeHtml(sub.student_name)}')" title="Chấm điểm">
                        <i class="fas fa-star"></i> Chấm
                    </button>
                </div>
            </div>
        `;
    },

    // Load documents
    loadDocuments: async function() {
        const container = document.getElementById('documents-list');
        if (!container) return;

        const search = document.getElementById('documents-search')?.value || '';
        const type = document.getElementById('documents-type-filter')?.value || '';

        container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';

        try {
            const response = await fetch(`api/content.php?action=get_documents&search=${encodeURIComponent(search)}&type=${type}`);
            const data = await response.json();

            if (data.success && data.documents.length > 0) {
                container.innerHTML = data.documents.map(doc => this.renderDocumentItem(doc)).join('');
            } else {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>Không tìm thấy tài liệu</h3>
                        <p>Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading documents:', error);
            container.innerHTML = '<div class="empty-state"><p>Lỗi tải dữ liệu</p></div>';
        }
    },

    // Render document item
    renderDocumentItem: function(doc) {
        const iconClass = this.getFileIconClass(doc.file_type);
        const scanLabels = {
            'clean': 'An toàn',
            'pending': 'Chờ quét',
            'suspicious': 'Nghi ngờ',
            'infected': 'Nhiễm virus'
        };

        return `
            <div class="document-item" data-document-id="${doc.id}">
                <div class="document-icon ${iconClass.color}">
                    <i class="fas ${iconClass.icon}"></i>
                </div>
                <div class="document-info">
                    <div class="document-name">${this.escapeHtml(doc.original_name || doc.title)}</div>
                    <div class="document-meta">
                        <span><i class="fas fa-hdd"></i> ${this.formatSize(doc.file_size)}</span>
                        <span><i class="fas fa-download"></i> ${doc.download_count || 0} lượt</span>
                        <span><i class="fas fa-user"></i> ${this.escapeHtml(doc.uploader_name || 'Hệ thống')}</span>
                        <span><i class="fas fa-calendar"></i> ${this.formatDate(doc.created_at)}</span>
                    </div>
                </div>
                <span class="scan-status scan-${doc.scan_status}">${scanLabels[doc.scan_status] || doc.scan_status}</span>
                <div class="lesson-actions">
                    <button class="btn-preview" onclick="Content.downloadDocument(${doc.id})" title="Tải về">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="btn-approve" onclick="Content.scanDocument(${doc.id})" title="Quét virus">
                        <i class="fas fa-shield-virus"></i>
                    </button>
                    <button class="btn-delete" onclick="Content.deleteDocument(${doc.id}, '${this.escapeHtml(doc.original_name || doc.title)}')" title="Xóa">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    },

    // Preview lesson
    previewLesson: async function(id) {
        try {
            const response = await fetch(`api/content.php?action=get_lesson&id=${id}`);
            const data = await response.json();

            if (data.success) {
                const lesson = data.lesson;
                document.getElementById('preview-title').textContent = lesson.title;
                document.getElementById('preview-content').innerHTML = `
                    <div class="submission-info-box">
                        <div class="info-row">
                            <span class="info-label">Lớp học:</span>
                            <span class="info-value">${this.escapeHtml(lesson.class_name || 'N/A')}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Loại bài học:</span>
                            <span class="info-value">${this.getLessonTypeLabel(lesson.lesson_type)}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Trạng thái:</span>
                            <span class="info-value">${this.getStatusLabel(lesson.status)}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Lượt xem:</span>
                            <span class="info-value">${lesson.view_count || 0}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Ngày tạo:</span>
                            <span class="info-value">${this.formatDateTime(lesson.created_at)}</span>
                        </div>
                    </div>
                    <div class="lesson-content-preview">
                        <h4><i class="fas fa-file-alt"></i> Nội dung bài học</h4>
                        <div class="content-body">${lesson.content || '<em>Chưa có nội dung</em>'}</div>
                    </div>
                `;
                this.openModal('preview-modal');
            } else {
                this.showAlert('error', data.message || 'Không thể tải bài học');
            }
        } catch (error) {
            console.error('Error preview lesson:', error);
            this.showAlert('error', 'Lỗi khi tải bài học');
        }
    },

    // Approve lesson
    approveLesson: async function(id) {
        const result = await Swal.fire({
            title: 'Duyệt bài học?',
            text: 'Bài học sẽ được xuất bản và hiển thị cho học sinh',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Duyệt',
            cancelButtonText: 'Hủy',
            confirmButtonColor: '#4CAF50'
        });

        if (result.isConfirmed) {
            try {
                const formData = new FormData();
                formData.append('action', 'approve_lesson');
                formData.append('lesson_id', id);

                const response = await fetch('api/content.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    this.showAlert('success', 'Đã duyệt bài học thành công');
                    this.loadPendingLessons();
                    this.loadAllLessons();
                } else {
                    this.showAlert('error', data.message || 'Không thể duyệt bài học');
                }
            } catch (error) {
                console.error('Error approve lesson:', error);
                this.showAlert('error', 'Lỗi khi duyệt bài học');
            }
        }
    },

    // Show reject modal
    showRejectModal: function(id) {
        document.getElementById('reject-lesson-id').value = id;
        document.getElementById('reject-reason').value = '';
        this.openModal('reject-modal');
    },

    // Submit reject
    submitReject: async function(e) {
        e.preventDefault();
        
        const id = document.getElementById('reject-lesson-id').value;
        const reason = document.getElementById('reject-reason').value;

        if (!reason.trim()) {
            this.showAlert('error', 'Vui lòng nhập lý do từ chối');
            return false;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'reject_lesson');
            formData.append('lesson_id', id);
            formData.append('reason', reason);

            const response = await fetch('api/content.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                this.closeModal('reject-modal');
                this.showAlert('success', 'Đã từ chối bài học');
                this.loadPendingLessons();
                this.loadAllLessons();
            } else {
                this.showAlert('error', data.message || 'Không thể từ chối bài học');
            }
        } catch (error) {
            console.error('Error reject lesson:', error);
            this.showAlert('error', 'Lỗi khi từ chối bài học');
        }

        return false;
    },

    // Delete lesson
    deleteLesson: async function(id, title) {
        const result = await Swal.fire({
            title: 'Xóa bài học?',
            html: `Bạn có chắc muốn xóa bài học "<strong>${this.escapeHtml(title)}</strong>"?<br><span style="color: #d32f2f;">Hành động này không thể hoàn tác!</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Xóa',
            cancelButtonText: 'Hủy',
            confirmButtonColor: '#d32f2f'
        });

        if (result.isConfirmed) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_lesson');
                formData.append('lesson_id', id);

                const response = await fetch('api/content.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    this.showAlert('success', 'Đã xóa bài học');
                    this.loadAllLessons();
                } else {
                    this.showAlert('error', data.message || 'Không thể xóa bài học');
                }
            } catch (error) {
                console.error('Error delete lesson:', error);
                this.showAlert('error', 'Lỗi khi xóa bài học');
            }
        }
    },

    // View submission
    viewSubmission: async function(id) {
        try {
            const response = await fetch(`api/content.php?action=get_submission&id=${id}`);
            const data = await response.json();

            if (data.success) {
                const sub = data.submission;
                document.getElementById('preview-title').textContent = sub.assignment_title;
                document.getElementById('preview-content').innerHTML = `
                    <div class="submission-info-box">
                        <div class="info-row">
                            <span class="info-label">Học sinh:</span>
                            <span class="info-value">${this.escapeHtml(sub.student_name)}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Lớp:</span>
                            <span class="info-value">${this.escapeHtml(sub.class_name || 'N/A')}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Thời gian nộp:</span>
                            <span class="info-value">${this.formatDateTime(sub.submitted_at)}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Trạng thái:</span>
                            <span class="info-value">${this.getSubmissionStatusLabel(sub.status)}</span>
                        </div>
                        ${sub.score !== null ? `
                        <div class="info-row">
                            <span class="info-label">Điểm số:</span>
                            <span class="info-value" style="font-size: 20px; font-weight: 700; color: #4CAF50;">${sub.score}</span>
                        </div>
                        ` : ''}
                    </div>
                    <div class="lesson-content-preview">
                        <h4><i class="fas fa-file-alt"></i> Nội dung bài nộp</h4>
                        <div class="content-body">${sub.content || '<em>Không có nội dung văn bản</em>'}</div>
                    </div>
                    ${sub.file_path ? `
                    <div class="attachment-section">
                        <h4><i class="fas fa-paperclip"></i> File đính kèm</h4>
                        <a href="${sub.file_path}" class="btn btn-outline" target="_blank">
                            <i class="fas fa-download"></i> Tải file
                        </a>
                    </div>
                    ` : ''}
                    ${sub.feedback ? `
                    <div class="feedback-section">
                        <h4><i class="fas fa-comment"></i> Nhận xét của giảng viên</h4>
                        <div class="feedback-content">${this.escapeHtml(sub.feedback)}</div>
                    </div>
                    ` : ''}
                `;
                this.openModal('preview-modal');
            } else {
                this.showAlert('error', data.message || 'Không thể tải bài nộp');
            }
        } catch (error) {
            console.error('Error view submission:', error);
            this.showAlert('error', 'Lỗi khi tải bài nộp');
        }
    },

    // Show grade modal
    showGradeModal: function(id, assignmentTitle, studentName) {
        document.getElementById('grade-submission-id').value = id;
        document.getElementById('grade-score').value = '';
        document.getElementById('grade-feedback').value = '';
        document.getElementById('grade-submission-info').innerHTML = `
            <div class="submission-info-box">
                <div class="info-row">
                    <span class="info-label">Bài tập:</span>
                    <span class="info-value">${this.escapeHtml(assignmentTitle)}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Học sinh:</span>
                    <span class="info-value">${this.escapeHtml(studentName)}</span>
                </div>
            </div>
        `;
        this.openModal('grade-modal');
    },

    // Submit grade
    submitGrade: async function(e) {
        e.preventDefault();
        
        const id = document.getElementById('grade-submission-id').value;
        const score = document.getElementById('grade-score').value;
        const feedback = document.getElementById('grade-feedback').value;

        if (!score || score < 0 || score > 10) {
            this.showAlert('error', 'Điểm số phải từ 0 đến 10');
            return false;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'grade_submission');
            formData.append('submission_id', id);
            formData.append('score', score);
            formData.append('feedback', feedback);

            const response = await fetch('api/content.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                this.closeModal('grade-modal');
                this.showAlert('success', 'Đã chấm điểm thành công');
                this.loadPendingSubmissions();
            } else {
                this.showAlert('error', data.message || 'Không thể chấm điểm');
            }
        } catch (error) {
            console.error('Error grade submission:', error);
            this.showAlert('error', 'Lỗi khi chấm điểm');
        }

        return false;
    },

    // Download document
    downloadDocument: function(id) {
        window.open(`api/content.php?action=download_document&id=${id}`, '_blank');
    },

    // Scan document
    scanDocument: async function(id) {
        try {
            Swal.fire({
                title: 'Đang quét virus...',
                text: 'Vui lòng đợi',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => Swal.showLoading()
            });

            const formData = new FormData();
            formData.append('action', 'scan_document');
            formData.append('document_id', id);

            const response = await fetch('api/content.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            Swal.close();

            if (data.success) {
                const statusIcon = data.scan_status === 'clean' ? 'success' : (data.scan_status === 'suspicious' ? 'warning' : 'error');
                const statusText = {
                    'clean': 'File an toàn',
                    'suspicious': 'File có dấu hiệu nghi ngờ',
                    'infected': 'File nhiễm virus'
                };

                Swal.fire({
                    icon: statusIcon,
                    title: 'Kết quả quét',
                    text: statusText[data.scan_status] || 'Hoàn tất quét'
                });

                this.loadDocuments();
            } else {
                this.showAlert('error', data.message || 'Không thể quét file');
            }
        } catch (error) {
            Swal.close();
            console.error('Error scan document:', error);
            this.showAlert('error', 'Lỗi khi quét file');
        }
    },

    // Delete document
    deleteDocument: async function(id, name) {
        const result = await Swal.fire({
            title: 'Xóa tài liệu?',
            html: `Bạn có chắc muốn xóa "<strong>${this.escapeHtml(name)}</strong>"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Xóa',
            cancelButtonText: 'Hủy',
            confirmButtonColor: '#d32f2f'
        });

        if (result.isConfirmed) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_document');
                formData.append('document_id', id);

                const response = await fetch('api/content.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    this.showAlert('success', 'Đã xóa tài liệu');
                    this.loadDocuments();
                } else {
                    this.showAlert('error', data.message || 'Không thể xóa tài liệu');
                }
            } catch (error) {
                console.error('Error delete document:', error);
                this.showAlert('error', 'Lỗi khi xóa tài liệu');
            }
        }
    },

    // Cleanup files
    cleanupFiles: async function() {
        const result = await Swal.fire({
            title: 'Dọn dẹp file rác?',
            text: 'Các file không sử dụng hơn 30 ngày sẽ bị xóa',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Dọn dẹp',
            cancelButtonText: 'Hủy',
            confirmButtonColor: '#f57c00'
        });

        if (result.isConfirmed) {
            try {
                Swal.fire({
                    title: 'Đang dọn dẹp...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => Swal.showLoading()
                });

                const formData = new FormData();
                formData.append('action', 'cleanup_files');

                const response = await fetch('api/content.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                Swal.close();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Hoàn tất',
                        text: data.message || 'Đã dọn dẹp file rác'
                    });
                    this.loadDocuments();
                } else {
                    this.showAlert('error', data.message || 'Không thể dọn dẹp');
                }
            } catch (error) {
                Swal.close();
                console.error('Error cleanup files:', error);
                this.showAlert('error', 'Lỗi khi dọn dẹp');
            }
        }
    },

    // Scan all files
    scanAllFiles: async function() {
        const result = await Swal.fire({
            title: 'Quét virus toàn bộ?',
            text: 'Quá trình này có thể mất vài phút',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Bắt đầu quét',
            cancelButtonText: 'Hủy',
            confirmButtonColor: '#d32f2f'
        });

        if (result.isConfirmed) {
            try {
                Swal.fire({
                    title: 'Đang quét virus...',
                    html: 'Vui lòng đợi<br><small>Đang quét các file...</small>',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => Swal.showLoading()
                });

                const formData = new FormData();
                formData.append('action', 'scan_all_files');

                const response = await fetch('api/content.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                Swal.close();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Hoàn tất quét',
                        html: `
                            <p>Đã quét <strong>${data.total_scanned || 0}</strong> file</p>
                            <p style="color: #4CAF50;">An toàn: ${data.clean || 0}</p>
                            <p style="color: #f57c00;">Nghi ngờ: ${data.suspicious || 0}</p>
                            <p style="color: #d32f2f;">Nhiễm virus: ${data.infected || 0}</p>
                        `
                    });
                    this.loadDocuments();
                } else {
                    this.showAlert('error', data.message || 'Không thể quét');
                }
            } catch (error) {
                Swal.close();
                console.error('Error scan all files:', error);
                this.showAlert('error', 'Lỗi khi quét');
            }
        }
    },

    // Helper: Open modal
    openModal: function(modalId) {
        document.getElementById(modalId).classList.add('active');
    },

    // Helper: Close modal
    closeModal: function(modalId) {
        document.getElementById(modalId).classList.remove('active');
    },

    // Helper: Show alert
    showAlert: function(type, message) {
        const container = document.getElementById('alert-container');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

        container.innerHTML = `
            <div class="alert ${alertClass}">
                <i class="fas ${icon}"></i>
                <span>${this.escapeHtml(message)}</span>
            </div>
        `;

        setTimeout(() => {
            container.innerHTML = '';
        }, 5000);
    },

    // Helper: Escape HTML
    escapeHtml: function(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    // Helper: Format date
    formatDate: function(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('vi-VN');
    },

    // Helper: Format datetime
    formatDateTime: function(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleString('vi-VN');
    },

    // Helper: Format duration
    formatDuration: function(seconds) {
        if (!seconds) return '-';
        if (seconds < 60) return seconds + ' giây';
        if (seconds < 3600) return Math.round(seconds / 60) + ' phút';
        return (seconds / 3600).toFixed(1) + ' giờ';
    },

    // Helper: Format file size
    formatSize: function(bytes) {
        if (!bytes) return '0 B';
        if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
        return bytes + ' B';
    },

    // Helper: Get file icon class
    getFileIconClass: function(type) {
        const icons = {
            'pdf': { icon: 'fa-file-pdf', color: 'text-red' },
            'doc': { icon: 'fa-file-word', color: 'text-blue' },
            'docx': { icon: 'fa-file-word', color: 'text-blue' },
            'xls': { icon: 'fa-file-excel', color: 'text-green' },
            'xlsx': { icon: 'fa-file-excel', color: 'text-green' },
            'ppt': { icon: 'fa-file-powerpoint', color: 'text-orange' },
            'pptx': { icon: 'fa-file-powerpoint', color: 'text-orange' },
            'jpg': { icon: 'fa-file-image', color: 'text-purple' },
            'jpeg': { icon: 'fa-file-image', color: 'text-purple' },
            'png': { icon: 'fa-file-image', color: 'text-purple' },
            'gif': { icon: 'fa-file-image', color: 'text-purple' },
            'zip': { icon: 'fa-file-archive', color: 'text-yellow' },
            'rar': { icon: 'fa-file-archive', color: 'text-yellow' },
            'mp4': { icon: 'fa-file-video', color: 'text-pink' },
            'mp3': { icon: 'fa-file-audio', color: 'text-teal' }
        };
        return icons[(type || '').toLowerCase()] || { icon: 'fa-file', color: 'text-gray' };
    },

    // Helper: Get lesson type label
    getLessonTypeLabel: function(type) {
        const labels = {
            'theory': 'Lý thuyết',
            'practice': 'Thực hành',
            'test': 'Kiểm tra'
        };
        return labels[type] || type || 'Lý thuyết';
    },

    // Helper: Get status label
    getStatusLabel: function(status) {
        const labels = {
            'published': 'Đã xuất bản',
            'pending': 'Chờ duyệt',
            'rejected': 'Bị từ chối',
            'draft': 'Bản nháp'
        };
        return labels[status] || status;
    },

    // Helper: Get submission status label
    getSubmissionStatusLabel: function(status) {
        const labels = {
            'submitted': 'Đã nộp',
            'grading': 'Đang chấm',
            'graded': 'Đã chấm điểm',
            'returned': 'Đã trả bài'
        };
        return labels[status] || status;
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    Content.init();
});
