/**
 * Class Management JavaScript
 */

// ========================================
// MODAL FUNCTIONS
// ========================================

function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    document.body.style.overflow = '';
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(modal => {
            modal.classList.remove('show');
        });
        document.body.style.overflow = '';
    }
});

// ========================================
// UTILITY FUNCTIONS
// ========================================

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getStatusLabel(status) {
    const labels = {
        'active': 'Hoạt động',
        'archived': 'Lưu trữ',
        'draft': 'Bản nháp'
    };
    return labels[status] || status;
}

function getStatusBadgeClass(status) {
    const classes = {
        'active': 'badge-success',
        'archived': 'badge-secondary',
        'draft': 'badge-warning'
    };
    return classes[status] || 'badge-info';
}

// ========================================
// CLASS CRUD OPERATIONS
// ========================================

// Add Class
function submitAddClass(event) {
    event.preventDefault();
    
    const form = document.getElementById('add-class-form');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Đang tạo lớp học...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    fetch('api/classes.php?action=create', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Thành công',
                text: 'Đã tạo lớp học mới',
                timer: 1500,
                showConfirmButton: false
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: data.message || 'Không thể tạo lớp học'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Lỗi',
            text: 'Đã xảy ra lỗi khi xử lý yêu cầu'
        });
    });
    
    return false;
}

// View Class
function viewClass(classId) {
    Swal.fire({
        title: 'Đang tải...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    fetch(`api/classes.php?action=get&id=${classId}`)
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            const cls = data.class;
            Swal.fire({
                title: escapeHtml(cls.class_name),
                html: `
                    <div style="text-align: left; line-height: 1.8;">
                        <p><strong><i class="fas fa-hashtag"></i> Mã lớp:</strong> ${escapeHtml(cls.code)}</p>
                        <p><strong><i class="fas fa-user-tie"></i> Giảng viên:</strong> ${escapeHtml(cls.teacher_name) || '<em>Chưa phân công</em>'}</p>
                        <p><strong><i class="fas fa-info-circle"></i> Trạng thái:</strong> 
                            <span class="badge ${getStatusBadgeClass(cls.status)}">${getStatusLabel(cls.status)}</span>
                        </p>
                        <p><strong><i class="fas fa-users"></i> Số học sinh:</strong> ${cls.student_count || 0}</p>
                        <p><strong><i class="fas fa-align-left"></i> Mô tả:</strong></p>
                        <p style="padding: 10px; background: #f5f5f5; border-radius: 8px; margin-top: 5px;">
                            ${escapeHtml(cls.description) || '<em>Không có mô tả</em>'}
                        </p>
                        <p><strong><i class="fas fa-calendar-plus"></i> Ngày tạo:</strong> ${formatDate(cls.created_at)}</p>
                        <p><strong><i class="fas fa-calendar-check"></i> Cập nhật:</strong> ${formatDate(cls.updated_at)}</p>
                    </div>
                `,
                width: 500,
                showCloseButton: true,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-edit"></i> Chỉnh sửa',
                cancelButtonText: 'Đóng',
                confirmButtonColor: '#4CAF50'
            }).then(result => {
                if (result.isConfirmed) {
                    editClass(classId);
                }
            });
        } else {
            Swal.fire('Lỗi', data.message || 'Không thể tải thông tin lớp học', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Lỗi', 'Không thể tải thông tin lớp học', 'error');
    });
}

// Edit Class
function editClass(classId) {
    Swal.fire({
        title: 'Đang tải...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    fetch(`api/classes.php?action=get&id=${classId}`)
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            const cls = data.class;
            
            Swal.fire({
                title: '<i class="fas fa-edit"></i> Chỉnh sửa lớp học',
                html: `
                    <form id="edit-class-form" style="text-align: left;">
                        <input type="hidden" name="class_id" value="${cls.id}">
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Mã lớp <span style="color: red;">*</span></label>
                            <input type="text" name="class_code" class="swal2-input" value="${escapeHtml(cls.code)}" required style="margin: 0; width: 100%;">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Tên lớp học <span style="color: red;">*</span></label>
                            <input type="text" name="class_name" class="swal2-input" value="${escapeHtml(cls.class_name)}" required style="margin: 0; width: 100%;">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Mô tả</label>
                            <textarea name="description" class="swal2-textarea" style="margin: 0; width: 100%; min-height: 80px;">${escapeHtml(cls.description) || ''}</textarea>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Trạng thái</label>
                            <select name="status" class="swal2-select" style="margin: 0; width: 100%;">
                                <option value="draft" ${cls.status === 'draft' ? 'selected' : ''}>Bản nháp</option>
                                <option value="active" ${cls.status === 'active' ? 'selected' : ''}>Hoạt động</option>
                                <option value="archived" ${cls.status === 'archived' ? 'selected' : ''}>Lưu trữ</option>
                            </select>
                        </div>
                    </form>
                `,
                width: 500,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save"></i> Lưu thay đổi',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#4CAF50',
                preConfirm: () => {
                    const form = document.getElementById('edit-class-form');
                    const classCode = form.querySelector('[name="class_code"]').value.trim();
                    const className = form.querySelector('[name="class_name"]').value.trim();
                    
                    if (!classCode || !className) {
                        Swal.showValidationMessage('Vui lòng điền đầy đủ thông tin bắt buộc');
                        return false;
                    }
                    
                    return new FormData(form);
                }
            }).then(result => {
                if (result.isConfirmed && result.value) {
                    updateClass(result.value);
                }
            });
        } else {
            Swal.fire('Lỗi', data.message || 'Không thể tải thông tin lớp học', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Lỗi', 'Không thể tải thông tin lớp học', 'error');
    });
}

function updateClass(formData) {
    Swal.fire({
        title: 'Đang lưu...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    fetch('api/classes.php?action=update', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Đã cập nhật',
                text: 'Thông tin lớp học đã được cập nhật',
                timer: 1500,
                showConfirmButton: false
            }).then(() => location.reload());
        } else {
            Swal.fire('Lỗi', data.message || 'Không thể cập nhật lớp học', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Lỗi', 'Đã xảy ra lỗi khi xử lý yêu cầu', 'error');
    });
}

// Clone Class
function cloneClass(classId) {
    Swal.fire({
        title: 'Nhân bản lớp học?',
        html: `
            <p>Sẽ tạo một bản sao của lớp học với cấu trúc bài giảng.</p>
            <p style="color: #666; font-size: 14px; margin-top: 10px;">
                <i class="fas fa-info-circle"></i> Không sao chép dữ liệu học sinh và bài nộp.
            </p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-copy"></i> Nhân bản',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#4CAF50'
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Đang nhân bản...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            fetch(`api/classes.php?action=clone&id=${classId}`, { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Đã nhân bản',
                        text: 'Lớp học mới đã được tạo thành công',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Lỗi', data.message || 'Không thể nhân bản lớp học', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Lỗi', 'Đã xảy ra lỗi khi xử lý yêu cầu', 'error');
            });
        }
    });
}

// Delete Class
function deleteClass(classId, className) {
    Swal.fire({
        title: 'Xác nhận xóa?',
        html: `
            <p>Bạn có chắc muốn xóa lớp học <strong>${escapeHtml(className)}</strong>?</p>
            <p style="color: #d32f2f; font-size: 14px; margin-top: 10px;">
                <i class="fas fa-exclamation-triangle"></i> Tất cả dữ liệu liên quan (bài học, bài tập, điểm số) sẽ bị xóa vĩnh viễn.
            </p>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash"></i> Xóa',
        cancelButtonText: 'Hủy',
        reverseButtons: true
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Đang xóa...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            fetch(`api/classes.php?action=delete&id=${classId}`, { method: 'DELETE' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Đã xóa',
                        text: 'Lớp học đã được xóa thành công',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // Remove card from grid
                        const card = document.querySelector(`.class-card[data-class-id="${classId}"]`);
                        if (card) {
                            card.remove();
                        } else {
                            location.reload();
                        }
                    });
                } else {
                    Swal.fire('Lỗi', data.message || 'Không thể xóa lớp học', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Lỗi', 'Đã xảy ra lỗi khi xử lý yêu cầu', 'error');
            });
        }
    });
}

// Archive Class
function archiveClass(classId) {
    Swal.fire({
        title: 'Lưu trữ lớp học?',
        text: 'Lớp học sẽ được chuyển sang trạng thái lưu trữ',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-archive"></i> Lưu trữ',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#607D8B'
    }).then(result => {
        if (result.isConfirmed) {
            changeClassStatus(classId, 'archived');
        }
    });
}

// Restore Class
function restoreClass(classId) {
    Swal.fire({
        title: 'Khôi phục lớp học?',
        text: 'Lớp học sẽ được chuyển sang trạng thái hoạt động',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-undo"></i> Khôi phục',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#4CAF50'
    }).then(result => {
        if (result.isConfirmed) {
            changeClassStatus(classId, 'active');
        }
    });
}

function changeClassStatus(classId, status) {
    Swal.fire({
        title: 'Đang xử lý...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    const formData = new FormData();
    formData.append('class_id', classId);
    formData.append('status', status);
    
    fetch('api/classes.php?action=change_status', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Thành công',
                timer: 1500,
                showConfirmButton: false
            }).then(() => location.reload());
        } else {
            Swal.fire('Lỗi', data.message || 'Không thể thay đổi trạng thái', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Lỗi', 'Đã xảy ra lỗi khi xử lý yêu cầu', 'error');
    });
}

// ========================================
// MANAGE CLASS MEMBERS
// ========================================

function manageMembers(classId) {
    Swal.fire({
        title: 'Đang tải...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    fetch(`api/classes.php?action=get_members&id=${classId}`)
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            showMembersModal(classId, data.class_name, data.members);
        } else {
            Swal.fire('Lỗi', data.message || 'Không thể tải danh sách thành viên', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Lỗi', 'Đã xảy ra lỗi khi tải dữ liệu', 'error');
    });
}

function showMembersModal(classId, className, members) {
    let membersList = '';
    if (members.length === 0) {
        membersList = '<p style="text-align: center; color: #666;">Chưa có học sinh nào trong lớp</p>';
    } else {
        membersList = '<div style="max-height: 300px; overflow-y: auto;">';
        members.forEach(member => {
            membersList += `
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <img src="${member.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(member.full_name)}&background=4CAF50&color=fff`}" 
                             style="width: 36px; height: 36px; border-radius: 50%;" alt="">
                        <div>
                            <div style="font-weight: 500;">${escapeHtml(member.full_name)}</div>
                            <div style="font-size: 12px; color: #666;">${escapeHtml(member.email)}</div>
                        </div>
                    </div>
                    <button onclick="removeMember(${classId}, ${member.id}, '${escapeHtml(member.full_name)}')" 
                            style="background: #ffebee; color: #d32f2f; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        });
        membersList += '</div>';
    }
    
    Swal.fire({
        title: `<i class="fas fa-users"></i> Thành viên lớp ${escapeHtml(className)}`,
        html: `
            <div style="margin-bottom: 15px;">
                <button onclick="addMemberDialog(${classId})" class="swal2-confirm swal2-styled" style="background: #4CAF50;">
                    <i class="fas fa-user-plus"></i> Thêm học sinh
                </button>
            </div>
            ${membersList}
        `,
        width: 500,
        showCloseButton: true,
        showConfirmButton: false
    });
}

function addMemberDialog(classId) {
    Swal.fire({
        title: 'Thêm học sinh vào lớp',
        input: 'email',
        inputLabel: 'Email học sinh',
        inputPlaceholder: 'Nhập email học sinh',
        showCancelButton: true,
        confirmButtonText: 'Thêm',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#4CAF50',
        inputValidator: (value) => {
            if (!value) {
                return 'Vui lòng nhập email';
            }
            if (!value.includes('@')) {
                return 'Email không hợp lệ';
            }
        }
    }).then(result => {
        if (result.isConfirmed) {
            addMember(classId, result.value);
        }
    });
}

function addMember(classId, email) {
    Swal.fire({
        title: 'Đang thêm...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    const formData = new FormData();
    formData.append('class_id', classId);
    formData.append('email', email);
    
    fetch('api/classes.php?action=add_member', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Đã thêm',
                text: data.message,
                timer: 1500,
                showConfirmButton: false
            }).then(() => manageMembers(classId));
        } else {
            Swal.fire('Lỗi', data.message || 'Không thể thêm học sinh', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Lỗi', 'Đã xảy ra lỗi khi xử lý yêu cầu', 'error');
    });
}

function removeMember(classId, userId, userName) {
    Swal.fire({
        title: 'Xóa học sinh?',
        text: `Bạn có chắc muốn xóa ${userName} khỏi lớp học?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    }).then(result => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('class_id', classId);
            formData.append('user_id', userId);
            
            fetch('api/classes.php?action=remove_member', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Đã xóa',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => manageMembers(classId));
                } else {
                    Swal.fire('Lỗi', data.message || 'Không thể xóa học sinh', 'error');
                }
            });
        }
    });
}

// ========================================
// INITIALIZATION
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    // Search form submit on Enter
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.form.submit();
            }
        });
    }
});