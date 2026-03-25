/**
 * User Management JavaScript
 * Handles all user-related operations
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

// Close modal when clicking outside
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
});

// Close modal with Escape key
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

// Password Toggle
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Generate Random Password
function generatePassword() {
    const length = 12;
    const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    let password = '';
    
    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    
    document.getElementById('add-password').value = password;
    document.getElementById('add-password').type = 'text';
    
    // Copy to clipboard
    navigator.clipboard.writeText(password).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Đã tạo mật khẩu',
            text: 'Mật khẩu đã được sao chép vào clipboard',
            timer: 2000,
            showConfirmButton: false
        });
    });
}

// ========================================
// USER CRUD OPERATIONS
// ========================================

// Add User
function submitAddUser(event) {
    event.preventDefault();
    
    const form = document.getElementById('add-user-form');
    const formData = new FormData(form);
    
    // Validate email domain
    const email = formData.get('email');
    if (!email.endsWith('@vnu.edu.vn')) {
        Swal.fire({
            icon: 'warning',
            title: 'Email không hợp lệ',
            text: 'Email phải có đuôi @vnu.edu.vn'
        });
        return false;
    }
    
    // Show loading
    Swal.fire({
        title: 'Đang xử lý...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('api/users.php?action=create', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Thành công',
                text: 'Đã thêm người dùng mới',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: data.message || 'Không thể thêm người dùng'
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

// View User
function viewUser(userId) {
    Swal.fire({
        title: 'Đang tải...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch(`api/users.php?action=get&id=${userId}`)
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            const user = data.user;
            const roleBadges = {
                'student': '<span class="badge badge-info"><i class="fas fa-user-graduate"></i> Sinh viên</span>',
                'teacher': '<span class="badge badge-primary"><i class="fas fa-chalkboard-teacher"></i> Giảng viên</span>',
                'admin': '<span class="badge badge-warning"><i class="fas fa-user-shield"></i> Admin</span>'
            };
            const statusBadges = {
                'active': '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Hoạt động</span>',
                'inactive': '<span class="badge badge-secondary"><i class="fas fa-minus-circle"></i> Không hoạt động</span>',
                'suspended': '<span class="badge badge-danger"><i class="fas fa-ban"></i> Tạm ngưng</span>'
            };
            
            const avatarUrl = user.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.full_name)}&background=4CAF50&color=fff&size=200`;
            
            const content = `
                <div class="user-profile-header">
                    <div class="user-profile-avatar">
                        <img src="${avatarUrl}" alt="Avatar">
                    </div>
                    <div class="user-profile-info">
                        <h2>${escapeHtml(user.full_name)}</h2>
                        <p class="user-email"><i class="fas fa-envelope"></i> ${escapeHtml(user.email)}</p>
                        <div class="user-profile-badges">
                            ${roleBadges[user.role] || ''}
                            ${statusBadges[user.status] || ''}
                        </div>
                    </div>
                </div>
                <div class="user-details-grid">
                    <div class="detail-item">
                        <span class="detail-label">ID người dùng</span>
                        <span class="detail-value">#${user.id}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Ngày tạo tài khoản</span>
                        <span class="detail-value">${formatDate(user.created_at)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Đăng nhập cuối</span>
                        <span class="detail-value">${user.last_login ? formatDate(user.last_login) : 'Chưa đăng nhập'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Cập nhật lần cuối</span>
                        <span class="detail-value">${formatDate(user.updated_at)}</span>
                    </div>
                </div>
            `;
            
            document.getElementById('view-user-content').innerHTML = content;
            document.getElementById('view-edit-btn').onclick = () => {
                closeModal('view-user-modal');
                editUser(userId);
            };
            openModal('view-user-modal');
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: data.message || 'Không thể tải thông tin người dùng'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Lỗi',
            text: 'Đã xảy ra lỗi khi tải dữ liệu'
        });
    });
}

// Edit User
function editUser(userId) {
    Swal.fire({
        title: 'Đang tải...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch(`api/users.php?action=get&id=${userId}`)
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            const user = data.user;
            
            document.getElementById('edit-user-id').value = user.id;
            document.getElementById('edit-fullname').value = user.fullname;  // SỬA: full_name -> fullname
            document.getElementById('edit-email').value = user.email;
            document.getElementById('edit-role').value = user.role;
            document.getElementById('edit-status').value = user.status;
            document.getElementById('edit-avatar').value = user.avatar || '';
            document.getElementById('edit-password').value = '';
            
            openModal('edit-user-modal');
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: data.message || 'Không thể tải thông tin người dùng'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Lỗi',
            text: 'Đã xảy ra lỗi khi tải dữ liệu'
        });
    });
}

// Submit Edit User
function submitEditUser(event) {
    event.preventDefault();
    
    const form = document.getElementById('edit-user-form');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Đang lưu...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('api/users.php?action=update', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Thành công',
                text: 'Đã cập nhật thông tin người dùng',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: data.message || 'Không thể cập nhật người dùng'
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

// Delete User
function deleteUser(userId, userName) {
    Swal.fire({
        title: 'Xác nhận xóa?',
        html: `Bạn có chắc muốn xóa người dùng <strong>${escapeHtml(userName)}</strong>?<br><br>
               <small class="text-muted">Hành động này không thể hoàn tác.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash"></i> Xóa',
        cancelButtonText: 'Hủy',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Đang xóa...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch(`api/users.php?action=delete&id=${userId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Đã xóa',
                        text: 'Người dùng đã được xóa thành công',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        const row = document.querySelector(`tr[data-user-id="${userId}"]`);
                        if (row) {
                            row.remove();
                        } else {
                            location.reload();
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi',
                        text: data.message || 'Không thể xóa người dùng'
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
        }
    });
}

// ========================================
// BULK ACTIONS
// ========================================

function toggleSelectAll() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.user-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    const bulkActions = document.getElementById('bulk-actions');
    const selectedCount = document.getElementById('selected-count');
    
    if (checkboxes.length > 0) {
        bulkActions.style.display = 'flex';
        selectedCount.textContent = checkboxes.length;
    } else {
        bulkActions.style.display = 'none';
    }
    
    // Update select all checkbox state
    const allCheckboxes = document.querySelectorAll('.user-checkbox');
    const selectAll = document.getElementById('select-all');
    selectAll.checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
    selectAll.indeterminate = checkboxes.length > 0 && checkboxes.length < allCheckboxes.length;
}

function bulkAction(action) {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    const userIds = Array.from(checkboxes).map(cb => cb.value);
    
    if (userIds.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Chưa chọn người dùng',
            text: 'Vui lòng chọn ít nhất một người dùng'
        });
        return;
    }
    
    let title, text, confirmText, confirmColor;
    
    switch (action) {
        case 'activate':
            title = 'Kích hoạt người dùng?';
            text = `Bạn có chắc muốn kích hoạt ${userIds.length} người dùng đã chọn?`;
            confirmText = '<i class="fas fa-check"></i> Kích hoạt';
            confirmColor = '#4CAF50';
            break;
        case 'deactivate':
            title = 'Vô hiệu hóa người dùng?';
            text = `Bạn có chắc muốn vô hiệu hóa ${userIds.length} người dùng đã chọn?`;
            confirmText = '<i class="fas fa-ban"></i> Vô hiệu hóa';
            confirmColor = '#FF9800';
            break;
        case 'delete':
            title = 'Xóa người dùng?';
            text = `Bạn có chắc muốn xóa ${userIds.length} người dùng đã chọn? Hành động này không thể hoàn tác.`;
            confirmText = '<i class="fas fa-trash"></i> Xóa';
            confirmColor = '#d33';
            break;
        default:
            return;
    }
    
    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmText,
        cancelButtonText: 'Hủy',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Đang xử lý...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('api/users.php?action=bulk', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    user_ids: userIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Thành công',
                        text: data.message || 'Đã thực hiện thao tác thành công',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi',
                        text: data.message || 'Không thể thực hiện thao tác'
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
        }
    });
}

// ========================================
// TAB SWITCHING
// ========================================

function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.currentTarget.classList.add('active');
    
    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`tab-${tabName}`).classList.add('active');
}

// ========================================
// BULK IMPORT FUNCTIONS
// ========================================

function previewBulkImport() {
    const data = document.getElementById('bulk-data').value.trim();
    
    if (!data) {
        Swal.fire({
            icon: 'warning',
            title: 'Chưa có dữ liệu',
            text: 'Vui lòng nhập dữ liệu người dùng trước khi xem trước'
        });
        return;
    }
    
    const lines = data.split('\n').filter(line => line.trim());
    const previewBody = document.getElementById('preview-body');
    const previewDiv = document.getElementById('bulk-preview');
    
    previewBody.innerHTML = '';
    let validCount = 0;
    
    const roleLabels = {
        'student': '<span class="badge badge-info badge-sm">Sinh viên</span>',
        'teacher': '<span class="badge badge-primary badge-sm">Giảng viên</span>',
        'admin': '<span class="badge badge-warning badge-sm">Admin</span>'
    };
    
    lines.forEach((line, index) => {
        const parts = line.split(/[,\t]/).map(p => p.trim());
        const fullname = parts[0] || '';
        const email = parts[1] || '';
        const role = (parts[2] || '').toLowerCase();
        
        let isValid = true;
        let errorMsg = '';
        
        if (!fullname) {
            isValid = false;
            errorMsg = 'Thiếu tên';
        } else if (!email) {
            isValid = false;
            errorMsg = 'Thiếu email';
        } else if (!email.endsWith('@vnu.edu.vn')) {
            isValid = false;
            errorMsg = 'Email không hợp lệ';
        } else if (!['student', 'teacher', 'admin'].includes(role)) {
            isValid = false;
            errorMsg = 'Vai trò không hợp lệ';
        }
        
        if (isValid) validCount++;
        
        const row = document.createElement('tr');
        row.className = isValid ? '' : 'error-row';
        row.innerHTML = `
            <td>${index + 1}</td>
            <td>${escapeHtml(fullname) || '<em class="text-muted">-</em>'}</td>
            <td>${escapeHtml(email) || '<em class="text-muted">-</em>'}</td>
            <td>${isValid ? roleLabels[role] : `<span class="text-danger">${errorMsg}</span>`}</td>
            <td><span class="badge badge-success badge-sm">Hoạt động</span></td>
        `;
        previewBody.appendChild(row);
    });
    
    document.getElementById('preview-count').textContent = validCount;
    previewDiv.style.display = 'block';
}

function submitBulkImport(event) {
    event.preventDefault();
    
    const data = document.getElementById('bulk-data').value.trim();
    
    if (!data) {
        Swal.fire({
            icon: 'warning',
            title: 'Chưa có dữ liệu',
            text: 'Vui lòng nhập dữ liệu người dùng'
        });
        return false;
    }
    
    const lines = data.split('\n').filter(line => line.trim());
    
    if (lines.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Không có dữ liệu',
            text: 'Không tìm thấy dữ liệu người dùng hợp lệ'
        });
        return false;
    }
    
    Swal.fire({
        title: `Xác nhận nhập ${lines.length} người dùng?`,
        text: 'Mật khẩu sẽ được tạo tự động cho mỗi người dùng',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4CAF50',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-upload"></i> Nhập dữ liệu',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if (result.isConfirmed) {
            processBulkImport(lines);
        }
    });
    
    return false;
}

function processBulkImport(lines) {
    Swal.fire({
        title: 'Đang xử lý...',
        html: 'Đang nhập người dùng...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const formData = new FormData();
    formData.append('bulk_data', lines.join('\n'));
    
    fetch('api/users.php?action=bulk_import', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.users && data.users.length > 0) {
            // Hiển thị kết quả với mật khẩu
            showBulkImportResult(data);
        } else {
            let errorHtml = data.message;
            if (data.errors && data.errors.length > 0) {
                errorHtml += '<br><br><div style="text-align:left;max-height:200px;overflow:auto;font-size:12px;">';
                data.errors.forEach(err => {
                    errorHtml += `<div>• ${err}</div>`;
                });
                errorHtml += '</div>';
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Có lỗi xảy ra',
                html: errorHtml
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
}

// Hiển thị kết quả nhập hàng loạt với mật khẩu
function showBulkImportResult(data) {
    closeModal('add-user-modal');
    
    const roleLabels = {
        'student': 'Sinh viên',
        'teacher': 'Giảng viên',
        'admin': 'Admin'
    };
    
    let tableRows = data.users.map((user, index) => `
        <tr>
            <td>${index + 1}</td>
            <td>${escapeHtml(user.fullname)}</td>
            <td>${escapeHtml(user.email)}</td>
            <td>${roleLabels[user.role] || user.role}</td>
            <td><code style="background:#fff3e0;padding:2px 6px;border-radius:4px;font-weight:600;color:#e65100;">${user.password}</code></td>
        </tr>
    `).join('');
    
    let resultHtml = `
        <div style="text-align:left;">
            <div style="margin-bottom:16px;padding:12px;background:#e8f5e9;border-radius:8px;color:#2e7d32;">
                <strong>✓ Đã tạo thành công ${data.imported} người dùng</strong>
                ${data.failed > 0 ? `<br><span style="color:#e65100;">${data.failed} dòng bị lỗi</span>` : ''}
            </div>
            
            <div style="margin-bottom:12px;padding:10px;background:#fff3e0;border-radius:6px;font-size:13px;color:#e65100;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Lưu ý:</strong> Hãy lưu lại mật khẩu trước khi đóng. Mật khẩu không thể xem lại sau này!
            </div>
            
            <div style="max-height:300px;overflow:auto;border:1px solid #e0e0e0;border-radius:8px;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:#f5f5f5;">
                            <th style="padding:10px;border-bottom:1px solid #e0e0e0;text-align:left;">#</th>
                            <th style="padding:10px;border-bottom:1px solid #e0e0e0;text-align:left;">Họ tên</th>
                            <th style="padding:10px;border-bottom:1px solid #e0e0e0;text-align:left;">Email</th>
                            <th style="padding:10px;border-bottom:1px solid #e0e0e0;text-align:left;">Vai trò</th>
                            <th style="padding:10px;border-bottom:1px solid #e0e0e0;text-align:left;">Mật khẩu</th>
                        </tr>
                    </thead>
                    <tbody>${tableRows}</tbody>
                </table>
            </div>
        </div>
    `;
    
    Swal.fire({
        icon: 'success',
        title: 'Nhập hàng loạt thành công!',
        html: resultHtml,
        width: '700px',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-download"></i> Tải xuống CSV',
        cancelButtonText: 'Đóng',
        confirmButtonColor: '#4CAF50'
    }).then((result) => {
        if (result.isConfirmed) {
            downloadCreatedUsers(data.users);
        }
        location.reload();
    });
}

// Tải xuống danh sách người dùng đã tạo
function downloadCreatedUsers(users) {
    const roleLabels = {
        'student': 'Sinh viên',
        'teacher': 'Giảng viên',
        'admin': 'Admin'
    };
    
    let csvContent = '\uFEFF'; // BOM for UTF-8
    csvContent += 'STT,Họ tên,Email,Vai trò,Mật khẩu\n';
    
    users.forEach((user, index) => {
        csvContent += `${index + 1},"${user.fullname}","${user.email}","${roleLabels[user.role] || user.role}","${user.password}"\n`;
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `nguoi_dung_moi_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    a.remove();
}

// ========================================
// EXPORT FUNCTIONS (Updated)
// ========================================

function exportUsers() {
    openModal('export-modal');
}

function doExport(format) {
    // Lấy các tùy chọn lọc
    const exportRole = document.getElementById('export-role').value;
    const exportDateFrom = document.getElementById('export-date-from').value;
    const exportDateTo = document.getElementById('export-date-to').value;
    const includePassword = document.getElementById('export-include-password').checked;
    
    closeModal('export-modal');
    
    // Get current filters from URL
    const urlParams = new URLSearchParams(window.location.search);
    const search = urlParams.get('search') || '';
    
    // Build export URL
    const params = new URLSearchParams({
        action: 'export',
        format: format,
        search: search,
        role: exportRole,
        date_from: exportDateFrom,
        date_to: exportDateTo,
        include_password: includePassword ? '1' : '0'
    });
    
    // Trigger file download
    window.location.href = `api/users.php?${params.toString()}`;
}

// ========================================
// EXPORT TAB SWITCHING
// ========================================

function switchExportTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.export-tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.currentTarget.classList.add('active');
    
    // Update tab content
    document.querySelectorAll('.export-tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`export-tab-${tabName}`).classList.add('active');
}

// ========================================
// INDIVIDUAL EXPORT FUNCTIONS
// ========================================

let selectedUsersForExport = [];
let searchTimeout = null;

function searchIndividualUser() {
    const query = document.getElementById('individual-search').value.trim();
    const loadingEl = document.getElementById('search-loading');
    const resultsEl = document.getElementById('search-results');
    
    // Clear previous timeout
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    if (query.length < 2) {
        resultsEl.style.display = 'none';
        return;
    }
    
    // Debounce search
    searchTimeout = setTimeout(() => {
        loadingEl.style.display = 'block';
        
        fetch(`api/users.php?action=search&q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                loadingEl.style.display = 'none';
                
                if (data.success) {
                    displaySearchResults(data.users);
                } else {
                    displayNoResults();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loadingEl.style.display = 'none';
                displayNoResults();
            });
    }, 300);
}

function displaySearchResults(users) {
    const resultsEl = document.getElementById('search-results');
    const listEl = document.getElementById('search-results-list');
    const countEl = document.getElementById('search-count');
    
    // Filter out already selected users
    const selectedIds = selectedUsersForExport.map(u => u.id);
    const filteredUsers = users.filter(u => !selectedIds.includes(u.id));
    
    countEl.textContent = filteredUsers.length;
    
    if (filteredUsers.length === 0) {
        listEl.innerHTML = `
            <div class="no-results">
                <i class="fas fa-search"></i>
                <p>Không tìm thấy người dùng</p>
            </div>
        `;
    } else {
        const roleLabels = {
            'student': 'Sinh viên',
            'teacher': 'Giảng viên',
            'admin': 'Admin'
        };
        
        listEl.innerHTML = filteredUsers.map(user => `
            <div class="search-result-item" onclick="addUserToExport(${user.id}, '${escapeHtml(user.fullname)}', '${escapeHtml(user.email)}', '${user.avatar || ''}')">
                <div class="search-result-info">
                    <div class="search-result-avatar">
                        <img src="${user.avatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.fullname) + '&background=4CAF50&color=fff'}" alt="">
                    </div>
                    <div class="search-result-details">
                        <span class="search-result-name">${escapeHtml(user.fullname)}</span>
                        <span class="search-result-email">${escapeHtml(user.email)}</span>
                    </div>
                </div>
                <div class="search-result-meta">
                    <span class="search-result-id">#${user.id}</span>
                    <span class="badge badge-sm">${roleLabels[user.role] || user.role}</span>
                    <button class="btn-add-user" onclick="event.stopPropagation(); addUserToExport(${user.id}, '${escapeHtml(user.fullname)}', '${escapeHtml(user.email)}', '${user.avatar || ''}')">
                        <i class="fas fa-plus"></i> Chọn
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    resultsEl.style.display = 'block';
}

function displayNoResults() {
    const resultsEl = document.getElementById('search-results');
    const listEl = document.getElementById('search-results-list');
    const countEl = document.getElementById('search-count');
    
    countEl.textContent = '0';
    listEl.innerHTML = `
        <div class="no-results">
            <i class="fas fa-search"></i>
            <p>Không tìm thấy người dùng</p>
        </div>
    `;
    resultsEl.style.display = 'block';
}

function addUserToExport(id, fullname, email, avatar) {
    // Check if already selected
    if (selectedUsersForExport.some(u => u.id === id)) {
        return;
    }
    
    selectedUsersForExport.push({ id, fullname, email, avatar });
    updateSelectedUsersList();
    
    // Refresh search results to remove selected user
    const query = document.getElementById('individual-search').value.trim();
    if (query.length >= 2) {
        searchIndividualUser();
    }
}

function removeUserFromExport(id) {
    selectedUsersForExport = selectedUsersForExport.filter(u => u.id !== id);
    updateSelectedUsersList();
    
    // Refresh search results
    const query = document.getElementById('individual-search').value.trim();
    if (query.length >= 2) {
        searchIndividualUser();
    }
}

function updateSelectedUsersList() {
    const sectionEl = document.getElementById('selected-users-section');
    const listEl = document.getElementById('selected-users-list');
    const countEl = document.getElementById('selected-users-count');
    
    countEl.textContent = selectedUsersForExport.length;
    
    if (selectedUsersForExport.length === 0) {
        sectionEl.style.display = 'none';
        return;
    }
    
    listEl.innerHTML = selectedUsersForExport.map(user => `
        <div class="selected-user-tag">
            <img src="${user.avatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.fullname) + '&background=4CAF50&color=fff&size=48'}" alt="">
            <span class="user-name">${escapeHtml(user.fullname)}</span>
            <button class="remove-btn" onclick="removeUserFromExport(${user.id})" title="Xóa">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
    
    sectionEl.style.display = 'block';
}

function doIndividualExport(format) {
    if (selectedUsersForExport.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Chưa chọn người dùng',
            text: 'Vui lòng tìm và chọn ít nhất một người dùng để xuất'
        });
        return;
    }
    
    const includePassword = document.getElementById('individual-include-password').checked;
    const userIds = selectedUsersForExport.map(u => u.id).join(',');
    
    // Build export URL
    const params = new URLSearchParams({
        action: 'export',
        format: format,
        user_ids: userIds,
        include_password: includePassword ? '1' : '0'
    });
    
    // Trigger file download
    window.location.href = `api/users.php?${params.toString()}`;
    
    // Show success message
    Swal.fire({
        icon: 'success',
        title: 'Đang xuất dữ liệu',
        text: `Xuất ${selectedUsersForExport.length} người dùng...`,
        timer: 2000,
        showConfirmButton: false
    });
}

// Reset export filters
function resetExportFilters() {
    // Reset bulk export filters
    document.getElementById('export-role').value = '';
    document.getElementById('export-status').value = '';
    document.getElementById('export-date-from').value = '';
    document.getElementById('export-date-to').value = '';
    document.getElementById('export-include-password').checked = false;
    
    // Reset individual export
    document.getElementById('individual-search').value = '';
    document.getElementById('individual-include-password').checked = false;
    document.getElementById('search-results').style.display = 'none';
    selectedUsersForExport = [];
    updateSelectedUsersList();
}

// ========================================
// INITIALIZATION
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for checkboxes
    document.querySelectorAll('.user-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });
    
    // Search form submit on Enter
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('filter-form').submit();
            }
        });
    }
    
    // Auto-submit filters on change
    document.querySelectorAll('.filter-select').forEach(select => {
        select.addEventListener('change', function() {
            document.getElementById('filter-form').submit();
        });
    });
});