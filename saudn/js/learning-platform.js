// File: js/learning-platform.js

let currentLesson = null;
let lessonStartTime = null;
let timeSpentInterval = null;

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  initializeSidebar();
  setupMenuItems();
  loadDashboardData();
});

// ==================== SIDEBAR FUNCTIONS ====================

function initializeSidebar() {
  const sidebarTrigger = document.querySelector(".sidebar-trigger");
  const sidebar = document.getElementById("sidebar");
  const mainContainer = document.querySelector(".main-container");
  let sidebarTimer;

  if (sidebarTrigger && sidebar && mainContainer) {
    // Show sidebar with slight delay on hover
    sidebarTrigger.addEventListener("mouseenter", () => {
      clearTimeout(sidebarTimer);
      sidebarTimer = setTimeout(() => {
        sidebar.classList.add("active");
        mainContainer.classList.add("sidebar-open");
      }, 100);
    });

    // Hide sidebar when mouse leaves both trigger and sidebar
    sidebarTrigger.addEventListener("mouseleave", (e) => {
      if (!e.relatedTarget?.closest(".sidebar")) {
        clearTimeout(sidebarTimer);
        sidebarTimer = setTimeout(() => {
          if (!sidebar.matches(":hover")) {
            closeSidebar();
          }
        }, 300);
      }
    });

    sidebar.addEventListener("mouseleave", () => {
      clearTimeout(sidebarTimer);
      sidebarTimer = setTimeout(() => {
        if (!sidebarTrigger.matches(":hover")) {
          closeSidebar();
        }
      }, 300);
    });

    // Keep sidebar open while hovering
    sidebar.addEventListener("mouseenter", () => {
      clearTimeout(sidebarTimer);
    });
  }
}

function closeSidebar() {
  const sidebar = document.getElementById("sidebar");
  const mainContainer = document.querySelector(".main-container");

  if (sidebar && mainContainer) {
    sidebar.classList.remove("active");
    mainContainer.classList.remove("sidebar-open");
  }
}

// ==================== MENU SETUP ====================

function setupMenuItems() {
  const menuItems = document.querySelectorAll("[data-section]");

  menuItems.forEach((item) => {
    item.addEventListener("click", function (e) {
      if (this.onclick) return; // Skip if has onclick handler

      e.preventDefault();
      const sectionName = this.getAttribute("data-section");

      // Update active state
      document.querySelectorAll("[data-section]").forEach((el) => {
        el.classList.remove("active");
      });
      this.classList.add("active");

      // Hide all sections
      document.querySelectorAll(".content-section").forEach((section) => {
        section.classList.remove("active");
      });

      // Show selected section
      const section = document.getElementById(sectionName + "-section");
      if (section) {
        section.classList.add("active");

        // Load data for specific sections
        switch (sectionName) {
          case "dashboard":
            loadDashboardData();
            break;
          case "tasks":
            loadTasks("all");
            break;
          case "submissions":
            loadSubmissions();
            break;
          case "classes":
            loadClasses();
            break;
          case "progress":
            loadProgress();
            break;
          case "achievements":
            loadAchievements();
            break;
        }
      }

      // Close sidebar on mobile
      if (window.innerWidth <= 768) {
        closeSidebar();
      }
    });
  });

  // Filter tabs for tasks
  const filterTabs = document.querySelectorAll(".filter-tab");
  filterTabs.forEach((tab) => {
    tab.addEventListener("click", function () {
      filterTabs.forEach((t) => t.classList.remove("active"));
      this.classList.add("active");

      const filter = this.getAttribute("data-filter");
      loadTasks(filter);
    });
  });
}

// ==================== DASHBOARD ====================

let currentActivityPage = 1;
let currentActivityFilter = 'all';

async function loadDashboardData() {
    try {
        console.log('Loading dashboard data...');
        
        const response = await fetch(`api/dashboard.php?page=${currentActivityPage}&filter=${currentActivityFilter}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Dashboard data:', data);
        
        if (data.success) {
            // Update stats with animation
            updateStatWithAnimation('total-classes', data.stats.total_classes);
            updateStatWithAnimation('pending-tasks', data.stats.pending_tasks);
            updateStatWithAnimation('completed-lessons', data.stats.completed_lessons);
            updateStatWithAnimation('avg-score', data.stats.avg_score);
            
            // Load recent activities with pagination
            loadRecentActivities(data.recent_activity, data.pagination);
        } else {
            console.error('Dashboard error:', data.message);
            showError('Không thể tải dữ liệu dashboard: ' + data.message);
        }
        
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showError('Có lỗi xảy ra khi tải dữ liệu dashboard');
    }
}

// Tìm và thay thế hàm loadRecentActivities

function loadRecentActivities(activities, pagination) {
    const container = document.getElementById('recent-activity-list');
    if (!container) return;
    
    // ✅ Check if no activities
    if (!activities || activities.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="text-align: center; padding: 50px 20px; color: #999;">
                <i class="fas fa-inbox" style="font-size: 56px; margin-bottom: 20px; display: block; opacity: 0.4;"></i>
                <p style="margin: 0; font-size: 16px; font-weight: 600; color: #666;">Chưa có hoạt động nào</p>
                <p style="margin: 8px 0 0 0; font-size: 14px; color: #999;">
                    Các hoạt động của bạn sẽ được hiển thị tại đây
                </p>
            </div>
        `;
        return;
    }
    
    const iconMap = {
        'lesson': 'fa-book-open',
        'submission': 'fa-file-upload',
        'enrollment': 'fa-user-plus'
    };
    
    const colorMap = {
        'lesson': '#4CAF50',
        'submission': '#2196F3',
        'enrollment': '#FF9800'
    };
    
    // ✅ Render activities
    const activitiesHTML = activities.map(activity => {
        const icon = iconMap[activity.type] || 'fa-circle';
        const color = colorMap[activity.type] || '#999';
        
        return `
            <div class="activity-item" style="animation: fadeInUp 0.3s ease-out;">
                <div class="activity-icon" style="background: ${color};">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="activity-content">
                    <p class="activity-title">${activity.title}</p>
                    <p class="activity-description">
                        ${activity.description}
                        ${activity.class_name ? `<span style="color: #999;"> • ${activity.class_name}</span>` : ''}
                    </p>
                    <p class="activity-time">
                        <i class="far fa-clock"></i> ${activity.relative_time || activity.formatted_time}
                    </p>
                </div>
            </div>
        `;
    }).join('');
    
    // ✅ Render pagination with correct info
    const paginationHTML = `
        <div class="activity-pagination">
            <button 
                class="pagination-btn ${!pagination.has_prev ? 'disabled' : ''}" 
                id="prev-page" 
                ${!pagination.has_prev ? 'disabled' : ''}
                onclick="changeActivityPage(${pagination.current_page - 1})"
            >
                <i class="fas fa-chevron-left"></i>
                <span>Trước</span>
            </button>
            
            <div class="pagination-info">
                <span class="page-numbers">
                    Trang <strong>${pagination.current_page}</strong> / <strong>${pagination.total_pages}</strong>
                </span>
                <span class="items-count">
                    (<strong>${pagination.showing_from}</strong>-<strong>${pagination.showing_to}</strong> / <strong>${pagination.total_items}</strong> hoạt động)
                </span>
            </div>
            
            <button 
                class="pagination-btn ${!pagination.has_next ? 'disabled' : ''}" 
                id="next-page" 
                ${!pagination.has_next ? 'disabled' : ''}
                onclick="changeActivityPage(${pagination.current_page + 1})"
            >
                <span>Sau</span>
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    `;
    
    container.innerHTML = activitiesHTML + paginationHTML;
}

// Add fadeInUp animation
const activityAnimationStyle = document.createElement('style');
activityAnimationStyle.textContent += `
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .pagination-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .pagination-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }
    
    .pagination-info .page-numbers {
        font-size: 15px;
        font-weight: 600;
        color: #333;
    }
    
    .pagination-info .items-count {
        font-size: 13px;
        color: #666;
    }
    
    .pagination-info strong {
        color: #2E7D32;
        font-weight: 700;
    }
`;
document.head.appendChild(activityAnimationStyle);

// Change activity page
function changeActivityPage(page) {
    currentActivityPage = page;
    loadDashboardData();
}

// Filter activities
function filterActivities(filter) {
    currentActivityFilter = filter;
    currentActivityPage = 1; // Reset to page 1
    
    // Update active filter button
    document.querySelectorAll('.activity-filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-filter="${filter}"]`)?.classList.add('active');
    
    loadDashboardData();
}

// Function to update stat with animation
function updateStatWithAnimation(elementId, newValue) {
    const element = document.getElementById(elementId);
    if (!element) {
        console.warn(`Element ${elementId} not found`);
        return;
    }
    
    const currentValue = parseInt(element.textContent) || 0;
    
    if (currentValue === newValue) {
        return;
    }
    
    const duration = 1000;
    const steps = 30;
    const increment = (newValue - currentValue) / steps;
    let current = currentValue;
    let step = 0;
    
    const timer = setInterval(() => {
        step++;
        current += increment;
        
        if (step >= steps) {
            element.textContent = newValue;
            clearInterval(timer);
        } else {
            element.textContent = Math.round(current);
        }
    }, duration / steps);
}

// Show error message
function showError(message) {
    const container = document.getElementById('recent-activity-list');
    if (container) {
        container.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <p>${message}</p>
            </div>
        `;
    }
}

// Call loadDashboardData when page loads
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('dashboard-section')) {
        loadDashboardData();
        
        // Refresh every 5 minutes
        setInterval(loadDashboardData, 5 * 60 * 1000);
    }
});

// ==================== TASKS ====================

async function loadTasks(filter = "all") {
  const container = document.getElementById("tasks-list");
  if (!container) return;

  container.innerHTML = '<p class="empty-state">Đang tải...</p>';

  try {
    const response = await fetch(`api/tasks.php?filter=${filter}`);
    const data = await response.json();

    if (data.success && data.tasks.length > 0) {
      container.innerHTML = data.tasks
        .map((task) => createTaskCard(task))
        .join("");

      // Start countdowns for tasks that haven't started
      data.tasks.forEach((task) => {
        if (!task.has_started && task.start_date) {
          startCountdown(task.id, task.start_date);
        }
      });
    } else {
      container.innerHTML = '<p class="empty-state">Không có nhiệm vụ nào</p>';
    }
  } catch (error) {
    console.error("Error loading tasks:", error);
    container.innerHTML = '<p class="empty-state">Lỗi khi tải dữ liệu</p>';
  }
}

function startCountdown(taskId, startDate) {
  const countdownEl = document.getElementById(`countdown-${taskId}`);
  if (!countdownEl) return;

  const updateCountdown = () => {
    const now = new Date().getTime();
    const start = new Date(startDate).getTime();
    const distance = start - now;

    if (distance < 0) {
      countdownEl.innerHTML = "Đã bắt đầu!";
      setTimeout(() => {
        // Reload tasks to update UI
        const activeFilter = document.querySelector(".filter-tab.active");
        const filter = activeFilter
          ? activeFilter.getAttribute("data-filter")
          : "all";
        loadTasks(filter);
      }, 1000);
      return;
    }

    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor(
      (distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)
    );
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

    let countdownText = "";
    if (days > 0) countdownText += `${days} ngày `;
    if (hours > 0) countdownText += `${hours} giờ `;
    if (minutes > 0) countdownText += `${minutes} phút `;
    countdownText += `${seconds} giây`;

    countdownEl.innerHTML = countdownText;
  };

  updateCountdown();
  setInterval(updateCountdown, 1000);
}

function createTaskCard(task) {
  const statusClass = getTaskStatus(task);
  const isOverdue = task.is_overdue;
  const hasStarted = task.has_started;
  const canSubmit = hasStarted && !task.submitted && !isOverdue;
  const isGraded = task.score !== null && task.score !== undefined;
  const canWithdraw = task.submitted && hasStarted && !isOverdue && !isGraded;
  const isNotSubmitted = !task.submitted && hasStarted && !isOverdue; // ✨ Thêm điều kiện chưa nộp

  return `
        <div class="task-card ${!hasStarted ? "task-not-started" : ""} ${
    isOverdue ? "task-overdue" : ""
  }">
            <div class="task-header">
                <div>
                    <h3 class="task-title">${task.task_name}</h3>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
                        ${
                          !hasStarted
                            ? `
                            <span class="task-badge not-started">
                                <i class="fas fa-clock"></i> Chưa bắt đầu
                            </span>
                        `
                            : ""
                        }
                        ${
                          isNotSubmitted
                            ? `
                            <span class="task-badge not-submitted">
                                <i class="fas fa-exclamation-circle"></i> Chưa nộp
                            </span>
                        `
                            : ""
                        }
                        ${
                          isGraded
                            ? `
                            <span class="task-badge graded">
                                <i class="fas fa-check-double"></i> Đã chấm điểm
                            </span>
                        `
                            : ""
                        }
                    </div>
                </div>
                <span class="task-status ${statusClass}">
                    ${getStatusText(statusClass)}
                </span>
            </div>
            <p class="task-description">${
              task.description || "Không có mô tả"
            }</p>
            <div class="task-meta">
                ${
                  task.start_date
                    ? `
                    <div class="task-meta-item">
                        <i class="fas fa-play-circle"></i>
                        <span>Bắt đầu: ${formatDateTime(task.start_date)}</span>
                    </div>
                `
                    : ""
                }
                <div class="task-meta-item">
                    <i class="fas fa-calendar"></i>
                    <span>Hạn nộp: ${formatDateTime(task.deadline)}</span>
                </div>
                <div class="task-meta-item">
                    <i class="fas fa-book"></i>
                    <span>Lớp: ${task.class_name}</span>
                </div>
                <div class="task-meta-item">
                    <i class="fas fa-star"></i>
                    <span>Điểm: ${
                      isGraded
                        ? `${task.score}/${task.max_score}`
                        : `${task.max_score} điểm`
                    }</span>
                </div>
            </div>
            <div class="task-actions">
                ${
                  !task.submitted
                    ? `
                    ${
                      canSubmit
                        ? `
                        <button class="btn btn-primary" onclick="uploadSubmission(${task.id})">
                            <i class="fas fa-upload"></i> Nộp bài
                        </button>
                    `
                        : !hasStarted
                        ? `
                        <button class="btn btn-disabled" disabled title="Nhiệm vụ chưa bắt đầu">
                            <i class="fas fa-lock"></i> Chưa thể nộp
                        </button>
                    `
                        : `
                        <button class="btn btn-disabled" disabled title="Đã quá hạn">
                            <i class="fas fa-exclamation-circle"></i> Quá hạn
                        </button>
                    `
                    }
                `
                    : `
                    <button class="btn btn-outline" onclick="viewSubmission(${
                      task.id
                    })">
                        <i class="fas fa-eye"></i> Xem bài nộp
                    </button>
                    ${
                      canWithdraw
                        ? `
                        <button class="btn btn-warning" onclick="withdrawSubmission(${task.id})">
                            <i class="fas fa-undo"></i> Thu hồi
                        </button>
                    `
                        : isGraded
                        ? `
                        <button class="btn btn-disabled" disabled title="Bài đã được chấm điểm, không thể thu hồi">
                            <i class="fas fa-lock"></i> Đã chấm
                        </button>
                    `
                        : ""
                    }
                `
                }
            </div>
            ${
              !hasStarted && task.start_date
                ? `
                <div class="task-countdown">
                    <i class="fas fa-hourglass-start"></i>
                    Bắt đầu sau: <span id="countdown-${task.id}"></span>
                </div>
            `
                : ""
            }
        </div>
    `;
}

// ==================== WITHDRAW SUBMISSION ====================
async function withdrawSubmission(taskId) {
  // Check if submission is already graded
  try {
    const response = await fetch(`api/tasks.php?filter=all`);
    const data = await response.json();
    const task = data.tasks.find((t) => t.id === taskId);

    if (task && task.score !== null && task.score !== undefined) {
      Swal.fire({
        icon: "error",
        title: "Không thể thu hồi",
        html: `
                    <p>Bài nộp này đã được giảng viên chấm điểm.</p>
                    <p><strong>Điểm:</strong> ${task.score}/${task.max_score}</p>
                    <p style="color: #666; font-size: 14px; margin-top: 12px;">
                        <i class="fas fa-info-circle"></i> 
                        Bạn không thể thu hồi bài đã được chấm điểm.
                    </p>
                `,
        confirmButtonColor: "#2E7D32",
      });
      return;
    }
  } catch (error) {
    console.error("Error checking task status:", error);
  }

  const result = await Swal.fire({
    title: "Xác nhận thu hồi",
    html: `
            <p style="margin-bottom: 16px;">Bạn có chắc chắn muốn thu hồi bài nộp?</p>
            <p style="color: #f57c00; font-size: 14px;">
                <i class="fas fa-exclamation-triangle"></i> 
                Sau khi thu hồi, bạn có thể nộp lại bài mới.
            </p>
        `,
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Thu hồi",
    cancelButtonText: "Hủy",
    confirmButtonColor: "#ff9800",
    cancelButtonColor: "#999",
  });

  if (result.isConfirmed) {
    try {
      Swal.fire({
        title: "Đang xử lý...",
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });

      const response = await fetch("api/withdraw-submission.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          task_id: taskId,
        }),
      });

      const data = await response.json();

      if (data.success) {
        Swal.fire({
          icon: "success",
          title: "Thành công",
          text: "Đã thu hồi bài nộp. Bạn có thể nộp lại bài mới.",
          confirmButtonColor: "#2E7D32",
        });

        // Reload tasks
        const activeFilter = document.querySelector(".filter-tab.active");
        const filter = activeFilter
          ? activeFilter.getAttribute("data-filter")
          : "all";
        loadTasks(filter);
      } else {
        Swal.fire({
          icon: "error",
          title: "Lỗi",
          text: data.message || "Không thể thu hồi bài nộp",
          confirmButtonColor: "#2E7D32",
        });
      }
    } catch (error) {
      console.error("Error withdrawing submission:", error);
      Swal.fire({
        icon: "error",
        title: "Lỗi",
        text: "Không thể thu hồi bài nộp. Vui lòng thử lại.",
        confirmButtonColor: "#2E7D32",
      });
    }
  }
}

function getTaskStatus(task) {
  if (task.submitted) return "completed";
  if (new Date(task.deadline) < new Date()) return "overdue";
  return "pending";
}

function getStatusText(status) {
  const texts = {
    pending: "Chưa hoàn thành",
    completed: "Đã hoàn thành",
    overdue: "Quá hạn",
  };
  return texts[status] || status;
}

// ==================== SUBMISSIONS ====================

async function loadSubmissions() {
  const container = document.getElementById("submissions-list");
  if (!container) return;

  container.innerHTML = '<p class="empty-state">Đang tải...</p>';

  try {
    const response = await fetch("api/submissions.php");
    const data = await response.json();

    if (data.success && data.submissions.length > 0) {
      container.innerHTML = `
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Nhiệm vụ</th>
                                <th>Lớp học</th>
                                <th>Thời gian nộp</th>
                                <th>Điểm</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.submissions
                              .map(
                                (sub) => `
                                <tr>
                                    <td>${sub.task_name}</td>
                                    <td>${sub.class_name}</td>
                                    <td>${formatDateTime(sub.submitted_at)}</td>
                                    <td>
                                        ${
                                          sub.score !== null
                                            ? `
                                            <span class="score-badge ${getScoreClass(
                                              sub.score,
                                              sub.max_score
                                            )}">
                                                ${sub.score}/${sub.max_score}
                                            </span>
                                        `
                                            : '<span style="color: #999;">Chưa chấm</span>'
                                        }
                                    </td>
                                    <td>
                                        ${
                                          sub.graded_at
                                            ? '<span style="color: #2E7D32;">✓ Đã chấm</span>'
                                            : '<span style="color: #F57C00;">⏳ Chờ chấm</span>'
                                        }
                                    </td>
                                    <td>
                                        <button class="btn btn-outline" onclick="viewSubmissionDetail(${
                                          sub.id
                                        })">
                                            <i class="fas fa-eye"></i> Chi tiết
                                        </button>
                                    </td>
                                </tr>
                            `
                              )
                              .join("")}
                        </tbody>
                    </table>
                </div>
            `;
    } else {
      container.innerHTML =
        '<p class="empty-state">Bạn chưa có bài nộp nào</p>';
    }
  } catch (error) {
    console.error("Error loading submissions:", error);
    container.innerHTML = '<p class="empty-state">Lỗi khi tải dữ liệu</p>';
  }
}

function getScoreClass(score, maxScore) {
  const percentage = (score / maxScore) * 100;
  if (percentage >= 80) return "score-excellent";
  if (percentage >= 65) return "score-good";
  if (percentage >= 50) return "score-average";
  return "score-poor";
}

async function viewSubmissionDetail(submissionId) {
  try {
    const response = await fetch(
      `api/submission-detail.php?id=${submissionId}`
    );
    const data = await response.json();

    if (data.success) {
      Swal.fire({
        title: "Chi tiết bài nộp",
        html: `
                    <div style="text-align: left;">
                        <p><strong>Nhiệm vụ:</strong> ${
                          data.submission.task_name
                        }</p>
                        <p><strong>Lớp học:</strong> ${
                          data.submission.class_name
                        }</p>
                        <p><strong>Thời gian nộp:</strong> ${formatDateTime(
                          data.submission.submitted_at
                        )}</p>
                        ${
                          data.submission.score !== null
                            ? `
                            <p><strong>Điểm:</strong> ${data.submission.score}/${data.submission.max_score}</p>
                        `
                            : "<p><strong>Điểm:</strong> Chưa chấm</p>"
                        }
                        ${
                          data.submission.feedback
                            ? `
                            <p><strong>Nhận xét:</strong> ${data.submission.feedback}</p>
                        `
                            : ""
                        }
                        <p style="margin-top: 16px;">
                            <a href="${
                              data.submission.file_path
                            }" target="_blank" class="btn btn-primary" style="display: inline-block; padding: 10px 20px; background: #2E7D32; color: white; text-decoration: none; border-radius: 8px;">
                                <i class="fas fa-download"></i> Tải file
                            </a>
                        </p>
                    </div>
                `,
        confirmButtonText: "Đóng",
        confirmButtonColor: "#2E7D32",
      });
    }
  } catch (error) {
    console.error("Error loading submission detail:", error);
    Swal.fire("Lỗi", "Không thể tải chi tiết bài nộp", "error");
  }
}

// ==================== CLASSES ====================

function getLessonIcon(type) {
  const icons = {
    theory: "fas fa-book",
    practice: "fas fa-pencil-alt",
    test: "fas fa-clipboard-check",
  };
  return icons[type] || "fas fa-file";
}

function getLessonTypeName(type) {
  const names = {
    theory: "Lý thuyết",
    practice: "Thực hành",
    test: "Kiểm tra",
  };
  return names[type] || type;
}

async function loadClasses() {
  const container = document.getElementById("classes-list");
  if (!container) return;

  container.innerHTML = '<p class="empty-state">Đang tải...</p>';

  try {
    const response = await fetch("api/classes.php");
    const data = await response.json();

    if (data.success && data.classes.length > 0) {
      container.innerHTML = data.classes
        .map(
          (cls) => `
                <div class="class-card" onclick="viewClassDetail(${cls.id})">
                    <div class="class-header">
                        <h3 class="class-name">${cls.class_name}</h3>
                        <p class="class-teacher">Giảng viên: ${
                          cls.teacher_name || "Chưa có"
                        }</p>
                    </div>
                    <div class="class-body">
                        <p class="class-code" style="font-size: 14px; color: var(--text-secondary); margin-bottom: 12px; font-weight: 500;">
                            <i class="fas fa-tag"></i> Mã lớp: ${
                              cls.code || "Chưa có"
                            }
                        </p>
                        <p class="class-description">${
                          cls.description || "Không có mô tả"
                        }</p>
                        <div class="class-stats">
                            <div class="class-stat">
                                <i class="fas fa-book"></i>
                                <span>${cls.total_lessons || 0} bài học</span>
                            </div>
                            <div class="class-stat">
                                <i class="fas fa-check-circle"></i>
                                <span>${
                                  cls.completed_lessons || 0
                                } đã hoàn thành</span>
                            </div>
                            ${
                              cls.progress !== undefined
                                ? `
                                <div class="class-stat">
                                    <i class="fas fa-chart-line"></i>
                                    <span>${cls.progress}% hoàn thành</span>
                                </div>
                            `
                                : ""
                            }
                        </div>
                    </div>
                </div>
            `
        )
        .join("");
    } else {
      container.innerHTML =
        '<p class="empty-state">Bạn chưa tham gia lớp học nào</p>';
    }
  } catch (error) {
    console.error("Error loading classes:", error);
    container.innerHTML = '<p class="empty-state">Lỗi khi tải dữ liệu</p>';
  }
}

async function viewClassDetail(classId) {
  const container = document.getElementById("classes-list");
  if (!container) return;

  container.innerHTML = '<p class="empty-state">Đang tải...</p>';

  try {
    const response = await fetch(`api/class-detail.php?id=${classId}`);
    const data = await response.json();

    if (data.success) {
      container.innerHTML = `
                <div class="class-detail">
                    <div class="class-detail-header">
                        <div>
                            <h2>${data.class.class_name}</h2>
                            <p>Giảng viên: ${data.class.teacher_name}</p>
                        </div>
                        <button class="back-btn" onclick="loadClasses()">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </button>
                    </div>
                    <!-- Thay thế phần class-content trong viewClassDetail -->
<div class="class-content">
    <div class="chapters-tree">
        ${data.chapters.map((chapter) => createChapterTree(chapter)).join("")}
    </div>
    
    <!-- AI Assistant tích hợp trực tiếp -->
    <div class="lesson-viewer-panel" id="lesson-viewer">
        <!-- Empty state khi chưa chọn bài -->
        <div class="lesson-viewer-empty" id="lesson-viewer-empty">
            <div class="empty-state">
                <i class="fas fa-hand-pointer" style="font-size: 48px; color: #ccc;"></i>
                <p style="margin-top: 16px;">Chọn một bài học để bắt đầu</p>
            </div>
        </div>
        
        <!-- Lesson content với AI Assistant bên cạnh -->
        <div class="lesson-viewer-content" id="lesson-viewer-content" style="display: none;">
            <div class="lesson-pdf-container">
        <div class="lesson-viewer-header">
            <div class="lesson-viewer-header-left">
                <h3 id="current-lesson-title">Tiêu đề bài học</h3>
                <div class="lesson-meta-tags">
                    <span class="lesson-type-tag theory" id="current-lesson-type">
                        <i class="fas fa-book"></i>
                        Lý thuyết
                    </span>
                    <span class="lesson-time-tag">
                        <i class="far fa-clock"></i>
                        <span id="current-lesson-time">0:00</span>
                    </span>
                </div>
            </div>
            <div class="lesson-viewer-header-right">
                <button class="btn-toggle-ai" id="btn-toggle-ai" onclick="toggleAIAssistant()">
                    <i class="fas fa-robot"></i>
                    <span>AI Assistant</span>
                </button>
                <button class="btn-complete-lesson" id="btn-complete-lesson" onclick="completeLesson()">
                    <i class="fas fa-check"></i>
                    <span>Hoàn thành</span>
                </button>
            </div>
        </div>
        
        <!-- PDF VIEWER - LEFT COLUMN -->
    <div class="pdf-viewer-frame">
        <iframe id="lesson-pdf-frame" src=""></iframe>
    </div>
    
    <!-- AI ASSISTANT - RIGHT COLUMN -->
    <div class="ai-lesson-assistant" id="ai-lesson-assistant">
        <div class="ai-lesson-header">
            <div class="ai-lesson-header-info">
                <i class="fas fa-robot"></i>
                <div>
                    <h4>AI Assistant</h4>
                    <span>Trợ lý học tập thông minh</span>
                </div>
            </div>
            <button class="ai-lesson-close" id="ai-lesson-close" onclick="closeAIAssistant()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="ai-lesson-tabs">
            <button class="ai-tab active" data-tab="summary">
                <i class="fas fa-file-alt"></i>
                <span>Tóm tắt</span>
            </button>
            <button class="ai-tab" data-tab="highlights">
                <i class="fas fa-highlighter"></i>
                <span>Điểm nổi bật</span>
            </button>
            <button class="ai-tab" data-tab="quiz">
                <i class="fas fa-question-circle"></i>
                <span>Câu hỏi</span>
            </button>
        </div>
        
        <div class="ai-lesson-content" id="ai-lesson-content">
            <div class="empty-state">
                <i class="fas fa-robot"></i>
                <p>Chọn một tab để xem nội dung AI</p>
            </div>
        </div>
        
        <div class="ai-lesson-footer">
            <button class="btn-ai-regenerate">
                <i class="fas fa-sync-alt"></i>
                <span>Tạo lại</span>
            </button>
            <button class="btn-ai-export">
                <i class="fas fa-download"></i>
                <span>Xuất</span>
            </button>
        </div>
    </div>
        </div>
    </div>
</div>
            `;

      // Setup chapter toggle listeners
      setupChapterToggle();
    }
  } catch (error) {
    console.error("Error loading class detail:", error);
    container.innerHTML = '<p class="empty-state">Lỗi khi tải dữ liệu</p>';
  }
}

// Thay thế hàm createChapterTree

function createChapterTree(chapter) {
    return `
        <div class="chapter">
            <div class="chapter-header">
                <span class="chapter-title">
                    <i class="fas fa-folder"></i>
                    <span>Chương ${chapter.chapter_order || chapter.order_index}: ${chapter.chapter_name}</span>
                </span>
                <i class="fas fa-chevron-right chapter-toggle"></i>
            </div>
            <div class="lessons-list">
                ${chapter.lessons && chapter.lessons.length > 0
                    ? chapter.lessons.map(lesson => {
                        const lessonType = (lesson.lesson_type || lesson.type || 'theory').toLowerCase().trim();
                        const hasStarted = lesson.has_started !== 0 && lesson.has_started !== false;
                        const isLocked = !hasStarted;
                        
                        return `
                            <div class="lesson-item ${isLocked ? 'lesson-locked' : ''}" 
                                 data-lesson-id="${lesson.id}"
                                 ${isLocked ? 'title="Bài học chưa mở"' : ''}>
                                <div class="lesson-name">
                                    <div class="lesson-type-icon ${lessonType}">
                                        ${isLocked 
                                            ? '<i class="fas fa-lock"></i>' 
                                            : `<i class="${getLessonIcon(lessonType)}"></i>`
                                        }
                                    </div>
                                    <span>${lesson.lesson_name || lesson.title}</span>
                                </div>
                                <span class="lesson-status">
                                    ${isLocked
                                        ? '<i class="fas fa-lock" style="color: #FF9800;"></i>'
                                        : lesson.is_completed == 1 || lesson.is_completed === true
                                            ? '<i class="fas fa-check-circle" style="color: #2E7D32;"></i>'
                                            : '<i class="far fa-circle" style="color: #ccc;"></i>'
                                    }
                                </span>
                            </div>
                        `;
                    }).join('')
                    : '<p style="padding: 12px 20px; color: #999; font-size: 13px;">Chưa có bài học</p>'
                }
            </div>
        </div>
    `;
}

function setupChapterToggle() {
  // Remove old event listeners by cloning
  const headers = document.querySelectorAll(".chapter-header");
  headers.forEach((header) => {
    const newHeader = header.cloneNode(true);
    header.parentNode.replaceChild(newHeader, header);
  });

  // Add new event listeners to chapter headers
  document.querySelectorAll(".chapter-header").forEach((header) => {
    header.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const chapter = this.closest(".chapter");
      if (!chapter) return;

      // Toggle expanded state
      chapter.classList.toggle("expanded");

      // Rotate the arrow icon
      const arrow = this.querySelector(".chapter-toggle");
      if (arrow) {
        if (chapter.classList.contains("expanded")) {
          arrow.style.transform = "rotate(90deg)";
        } else {
          arrow.style.transform = "rotate(0deg)";
        }
      }
    });
  });

  // Add event listeners to lesson items
  document.querySelectorAll(".lesson-item").forEach((item) => {
    item.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const lessonId = this.getAttribute("data-lesson-id");
      if (lessonId) {
        viewLesson(parseInt(lessonId), e);
      }
    });
  });
}

// Remove the old toggleChapter and handleChapterClick functions
// and replace with this simplified version
function toggleChapter(event) {
  if (!event) return;

  let element = event.currentTarget || event.target;
  const chapter = element.closest(".chapter");

  if (!chapter) return;

  chapter.classList.toggle("expanded");

  const arrow = chapter.querySelector(".chapter-toggle");
  if (arrow) {
    arrow.style.transform = chapter.classList.contains("expanded")
      ? "rotate(90deg)"
      : "rotate(0deg)";
  }
}

function getLessonTypeText(type) {
  const types = {
    theory: "Lý thuyết",
    practice: "Thực hành",
    test: "Kiểm tra",
  };
  return types[type] || type;
}

function startLessonTimer(lessonId) {
  // Clear any existing timer
  if (timeSpentInterval) {
    clearInterval(timeSpentInterval);
  }

  // Set current lesson and start time
  currentLesson = { id: lessonId };
  lessonStartTime = Date.now();

  // Update time display every second
  timeSpentInterval = setInterval(() => {
    updateTimeSpentDisplay();
  }, 1000);
}

function closeLessonModal() {
  const modal = document.getElementById("lesson-modal");
  if (modal) {
    modal.style.display = "none";
  }

  // Clear timer
  if (timeSpentInterval) {
    clearInterval(timeSpentInterval);
    timeSpentInterval = null;
  }

  // Reset variables
  currentLesson = null;
  lessonStartTime = null;

  // Close AI Assistant if open
  if (window.aiLessonAssistant && window.aiLessonAssistant.isActive) {
    window.aiLessonAssistant.closeAssistant();
  }
}

function updateTimeSpentDisplay() {
  if (!lessonStartTime) return;

  const elapsed = Math.floor((Date.now() - lessonStartTime) / 1000);
  const minutes = Math.floor(elapsed / 60);
  const seconds = elapsed % 60;

  const timeDisplay = document.getElementById("time-spent");
  if (timeDisplay) {
    timeDisplay.textContent = `${minutes}:${seconds
      .toString()
      .padStart(2, "0")}`;
  }
}

async function completeLesson() {
  if (!currentLesson) {
    Swal.fire("Lỗi", "Không tìm thấy thông tin bài học", "error");
    return;
  }

  const btn = document.getElementById("complete-lesson-btn");
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
  }

  try {
    const timeSpent = lessonStartTime
      ? Math.floor((Date.now() - lessonStartTime) / 1000)
      : 0;

    const response = await fetch("api/complete-lesson.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        lesson_id: currentLesson.id,
        time_spent: timeSpent,
        start_time: new Date(lessonStartTime).toISOString(),
        end_time: new Date().toISOString(),
      }),
    });

    const data = await response.json();

    if (data.success) {
      // Stop timer
      if (timeSpentInterval) {
        clearInterval(timeSpentInterval);
        timeSpentInterval = null;
      }

      Swal.fire({
        icon: "success",
        title: "Thành công!",
        text: "Bạn đã hoàn thành bài học",
        confirmButtonColor: "#2E7D32",
      });

      // Update lesson status icon
      const lessonItem = document.querySelector(
        `[data-lesson-id="${currentLesson.id}"]`
      );
      if (lessonItem) {
        const statusIcon = lessonItem.querySelector(".lesson-status");
        if (statusIcon) {
          statusIcon.innerHTML =
            '<i class="fas fa-check-circle" style="color: #2E7D32;"></i>';
        }
      }

      // Update button
      if (btn) {
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Đã hoàn thành';
        btn.classList.add("completed");
        btn.disabled = true;
      }

      // Reload dashboard stats
      loadDashboardData();

      // Close modal after 2 seconds
      setTimeout(() => {
        closeLessonModal();
      }, 2000);
    } else {
      throw new Error(data.message || "Không thể hoàn thành bài học");
    }
  } catch (error) {
    console.error("Error completing lesson:", error);
    Swal.fire("Lỗi", error.message || "Không thể hoàn thành bài học", "error");

    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check"></i> Hoàn thành bài học';
    }
  }
}

async function viewLesson(lessonId) {
    try {
        console.log('Loading lesson:', lessonId);
        
        const response = await fetch(`api/lesson.php?id=${lessonId}`);
        const data = await response.json();

        // CHECK IF LESSON IS LOCKED
        if (data.locked === true) {
            const lesson = data.lesson;
            const timeUntil = lesson.time_until_start;
            
            let countdownText = '';
            if (timeUntil) {
                if (timeUntil.days > 0) {
                    countdownText = `${timeUntil.days} ngày ${timeUntil.hours} giờ ${timeUntil.minutes} phút`;
                } else if (timeUntil.hours > 0) {
                    countdownText = `${timeUntil.hours} giờ ${timeUntil.minutes} phút`;
                } else {
                    countdownText = `${timeUntil.minutes} phút ${timeUntil.seconds} giây`;
                }
            }
            
            Swal.fire({
                icon: 'warning',
                title: '<i class="fas fa-lock"></i> Bài học chưa mở',
                html: `
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 64px; color: #FF9800; margin-bottom: 20px;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 style="margin: 0 0 16px 0; color: #333;">${lesson.lesson_name}</h3>
                        <div style="background: #FFF3E0; padding: 20px; border-radius: 12px; margin: 20px 0; border-left: 4px solid #FF9800;">
                            <p style="margin: 0 0 8px 0; font-size: 15px; color: #666;">
                                <i class="fas fa-calendar-alt"></i>
                                <strong>Thời gian mở:</strong>
                            </p>
                            <p style="margin: 0; font-size: 18px; font-weight: 600; color: #F57C00;">
                                ${lesson.start_date_formatted}
                            </p>
                        </div>
                        ${countdownText ? `
                            <div style="background: #E3F2FD; padding: 16px; border-radius: 12px; margin: 16px 0; border-left: 4px solid #2196F3;">
                                <p style="margin: 0 0 8px 0; font-size: 14px; color: #666;">
                                    <i class="fas fa-hourglass-start"></i>
                                    <strong>Mở sau:</strong>
                                </p>
                                <p id="lesson-countdown" style="margin: 0; font-size: 24px; font-weight: bold; color: #1976D2;">
                                    ${countdownText}
                                </p>
                            </div>
                        ` : ''}
                        <p style="margin: 16px 0 0 0; font-size: 14px; color: #999;">
                            <i class="fas fa-info-circle"></i>
                            Vui lòng quay lại sau khi bài học được mở
                        </p>
                    </div>
                `,
                confirmButtonText: 'Đóng',
                confirmButtonColor: '#FF9800',
                allowOutsideClick: true
            });
            
            // Start countdown if lesson opens soon (within 1 hour)
            if (timeUntil && timeUntil.total_seconds < 3600) {
                startLessonCountdown(lessonId, lesson.start_date);
            }
            
            return;
        }

        if (data.success) {
            const lesson = data.lesson;
            console.log('Lesson data:', lesson);
            
            // Get lesson type (check multiple possible field names)
            const lessonType = lesson.type || lesson.lesson_type || 'theory';
            console.log('Detected lesson type:', lessonType);
            
            // REMOVE ALL ACTIVE STATES
            document.querySelectorAll('.lesson-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // ADD ACTIVE STATE TO CURRENT LESSON
            const currentLessonItem = document.querySelector(`[data-lesson-id="${lessonId}"]`);
            if (currentLessonItem) {
                currentLessonItem.classList.add('active');
                currentLessonItem.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
                console.log('Active lesson set:', currentLessonItem);
            }
            
            // Show content, hide empty state
            const emptyState = document.getElementById('lesson-viewer-empty');
            const content = document.getElementById('lesson-viewer-content');
            
            if (emptyState) emptyState.style.display = 'none';
            if (content) {
                content.style.display = 'flex';
                content.classList.add('active');
            }
            
            // UPDATE LESSON TITLE
            const titleEl = document.getElementById('current-lesson-title');
            if (titleEl) {
                titleEl.textContent = lesson.title || lesson.lesson_name || 'Bài học';
            }
            
            // UPDATE LESSON TYPE WITH ICON & LABEL
            const typeEl = document.getElementById('current-lesson-type');
            if (typeEl) {
                const typeConfig = {
                    'theory': {
                        icon: 'fa-book',
                        text: 'Lý thuyết',
                        class: 'theory'
                    },
                    'practice': {
                        icon: 'fa-pencil-alt',
                        text: 'Thực hành',
                        class: 'practice'
                    },
                    'test': {
                        icon: 'fa-clipboard-check',
                        text: 'Kiểm tra',
                        class: 'test'
                    },
                    'quiz': {
                        icon: 'fa-question-circle',
                        text: 'Bài kiểm tra',
                        class: 'test'
                    },
                    'exercise': {
                        icon: 'fa-dumbbell',
                        text: 'Bài tập',
                        class: 'practice'
                    }
                };
                
                // Normalize lesson type (lowercase, trim)
                const normalizedType = lessonType.toLowerCase().trim();
                const config = typeConfig[normalizedType] || {
                    icon: 'fa-file',
                    text: lessonType || 'Khác',
                    class: 'theory'
                };
                
                // Update class - remove all possible classes first
                typeEl.className = 'lesson-type-tag';
                typeEl.classList.add(config.class);
                
                // Update content
                typeEl.innerHTML = `
                    <i class="fas ${config.icon}"></i>
                    ${config.text}
                `;
                
                console.log('Type updated:', normalizedType, '=>', config.text);
            }
            
            // UPDATE TIME
            const timeEl = document.getElementById('current-lesson-time');
            if (timeEl) {
                timeEl.textContent = '0:00';
            }
            
            // Load PDF/Image
            const pdfFrame = document.getElementById('lesson-pdf-frame');
            if (pdfFrame && lesson.file_path) {
                const fileExtension = lesson.file_path.split('.').pop().toLowerCase();
                
                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExtension)) {
                    pdfFrame.src = `data:text/html;charset=utf-8,
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <style>
                                body { 
                                    margin: 0; 
                                    padding: 20px; 
                                    display: flex; 
                                    justify-content: center; 
                                    align-items: flex-start; 
                                    min-height: 100vh; 
                                    background: #f5f5f5; 
                                }
                                img { 
                                    max-width: 900px; 
                                    width: 100%;
                                    height: auto; 
                                    object-fit: contain; 
                                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                                    border-radius: 8px;
                                }
                            </style>
                        </head>
                        <body><img src="../${lesson.file_path}" alt="Lesson content"></body>
                        </html>
                    `;
                } else {
                    pdfFrame.src = `../${lesson.file_path}`;
                }
            }
            
            // Store current lesson with normalized type
            window.currentLessonId = lessonId;
            window.currentLesson = {
                id: lessonId,
                type: lessonType
            };
            
            // Store for AI Assistant
            if (window.aiLessonAssistant) {
                window.aiLessonAssistant.currentFilePath = lesson.file_path;
                window.aiLessonAssistant.setLessonId(lessonId);
            }
            
            // Start timer
            startLessonTimer(lessonId);
            
            // Update complete button
            const completeBtn = document.getElementById('btn-complete-lesson');
            if (completeBtn) {
                if (lesson.is_completed === 1 || lesson.is_completed === true || lesson.completed === 1) {
                    completeBtn.disabled = true;
                    completeBtn.innerHTML = '<i class="fas fa-check-circle"></i><span>Đã hoàn thành</span>';
                    completeBtn.classList.add('completed');
                } else {
                    const minDuration = lesson.min_duration_minutes || 0;
                    
                    if (minDuration > 0) {
                        completeBtn.disabled = true;
                        completeBtn.innerHTML = `<i class="fas fa-lock"></i><span>Học tối thiểu ${minDuration} phút</span>`;
                        
                        setTimeout(() => {
                            if (completeBtn && !completeBtn.classList.contains('completed')) {
                                completeBtn.disabled = false;
                                completeBtn.innerHTML = '<i class="fas fa-check"></i><span>Hoàn thành</span>';
                            }
                        }, minDuration * 60 * 1000);
                    } else {
                        completeBtn.disabled = false;
                        completeBtn.innerHTML = '<i class="fas fa-check"></i><span>Hoàn thành</span>';
                        completeBtn.classList.remove('completed');
                    }
                }
            }
            
            // Auto-expand chapter if not expanded
            const lessonItem = document.querySelector(`[data-lesson-id="${lessonId}"]`);
            if (lessonItem) {
                const chapter = lessonItem.closest('.chapter');
                if (chapter && !chapter.classList.contains('expanded')) {
                    chapter.classList.add('expanded');
                }
            }
            
            console.log('Lesson loaded successfully');
            
        } else {
            throw new Error(data.message || 'Không thể tải bài học');
        }
    } catch (error) {
        console.error('Error loading lesson:', error);
        Swal.fire({
            icon: 'error',
            title: 'Lỗi',
            text: 'Không thể tải bài học. Vui lòng thử lại.',
            confirmButtonColor: '#2E7D32'
        });
    }
}

// COUNTDOWN FOR LOCKED LESSONS
function startLessonCountdown(lessonId, startDate) {
    const countdownEl = document.getElementById('lesson-countdown');
    if (!countdownEl) return;
    
    const updateCountdown = () => {
        const now = new Date().getTime();
        const start = new Date(startDate).getTime();
        const distance = start - now;
        
        if (distance < 0) {
            countdownEl.innerHTML = '<span style="color: #4CAF50;"><i class="fas fa-check-circle"></i> Đã mở!</span>';
            
            setTimeout(() => {
                Swal.close();
                // Reload lesson
                viewLesson(lessonId);
            }, 1500);
            return;
        }
        
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        let countdownText = '';
        if (days > 0) countdownText += `${days} ngày `;
        if (hours > 0) countdownText += `${hours} giờ `;
        if (minutes > 0) countdownText += `${minutes} phút `;
        countdownText += `${seconds} giây`;
        
        countdownEl.innerHTML = countdownText;
    };
    
    updateCountdown();
    const interval = setInterval(updateCountdown, 1000);
    
    // Store interval to clear later
    window.lessonCountdownInterval = interval;
}

// ==================== UPDATE TIME DISPLAY - FIXED ====================

function updateTimeSpentDisplay() {
    if (!lessonStartTime) return;

    const elapsed = Math.floor((Date.now() - lessonStartTime) / 1000);
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;

    // Update time display in viewer
    const timeEl = document.getElementById('current-lesson-time');
    if (timeEl) {
        timeEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
    
    // Also update old time-spent element if it exists
    const timeDisplay = document.getElementById('time-spent');
    if (timeDisplay) {
        timeDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
}

// ==================== COMPLETE LESSON - FIXED ====================

async function completeLesson() {
    if (!currentLesson) {
        Swal.fire('Lỗi', 'Không tìm thấy thông tin bài học', 'error');
        return;
    }

    const btn = document.getElementById('btn-complete-lesson');
    const originalHTML = btn ? btn.innerHTML : '';
    
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Đang xử lý...</span>';
    }

    try {
        const timeSpent = lessonStartTime 
            ? Math.floor((Date.now() - lessonStartTime) / 1000) 
            : 0;

        const response = await fetch('api/complete-lesson.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                lesson_id: currentLesson.id,
                time_spent: timeSpent,
                start_time: lessonStartTime ? new Date(lessonStartTime).toISOString() : new Date().toISOString(),
                end_time: new Date().toISOString()
            })
        });

        const data = await response.json();

        if (data.success) {
            // Stop timer
            if (timeSpentInterval) {
                clearInterval(timeSpentInterval);
                timeSpentInterval = null;
            }

            // Update lesson status icon in sidebar
            const lessonItem = document.querySelector(`[data-lesson-id="${currentLesson.id}"]`);
            if (lessonItem) {
                const statusIcon = lessonItem.querySelector('.lesson-status');
                if (statusIcon) {
                    statusIcon.innerHTML = '<i class="fas fa-check-circle" style="color: #2E7D32;"></i>';
                }
            }

            // Update button
            if (btn) {
                btn.innerHTML = '<i class="fas fa-check-circle"></i><span>Đã hoàn thành</span>';
                btn.classList.add('completed');
                btn.disabled = true;
            }

            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Thành công!',
                text: 'Bạn đã hoàn thành bài học',
                timer: 2000,
                showConfirmButton: false
            });

            // Reload dashboard stats
            loadDashboardData();

        } else {
            throw new Error(data.message || 'Không thể hoàn thành bài học');
        }

    } catch (error) {
        console.error('Error completing lesson:', error);
        Swal.fire('Lỗi', error.message || 'Không thể hoàn thành bài học', 'error');

        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    }
}

function toggleAIAssistant() {
    const aiAssistant = document.getElementById('ai-lesson-assistant');
    const lessonContent = document.getElementById('lesson-viewer-content');
    const toggleBtn = document.getElementById('btn-toggle-ai');
    
    if (!aiAssistant || !lessonContent) {
        console.error('AI Assistant elements not found');
        return;
    }
    
    // Toggle active state
    const isActive = aiAssistant.classList.contains('active');
    
    if (isActive) {
        // ❌ CLOSE AI
        aiAssistant.classList.remove('active');
        lessonContent.classList.remove('with-ai');
        
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-robot"></i><span>AI Assistant</span>';
            toggleBtn.classList.remove('active');
        }
        
        console.log('AI Assistant closed');
    } else {
        // ✅ OPEN AI
        aiAssistant.classList.add('active');
        lessonContent.classList.add('with-ai');
        
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-times"></i><span>Đóng AI</span>';
            toggleBtn.classList.add('active');
        }
        
        console.log('AI Assistant opened');
        
        // Load AI content if not loaded yet
        if (window.currentLessonId && window.aiLessonAssistant) {
            window.aiLessonAssistant.loadContent();
        }
    }
}

// Close AI Assistant
function closeAIAssistant() {
    const aiAssistant = document.getElementById('ai-lesson-assistant');
    const lessonContent = document.getElementById('lesson-viewer-content');
    const toggleBtn = document.getElementById('btn-toggle-ai');
    
    if (aiAssistant) {
        aiAssistant.classList.remove('active');
    }
    
    if (lessonContent) {
        lessonContent.classList.remove('with-ai');
    }
    
    if (toggleBtn) {
        toggleBtn.innerHTML = '<i class="fas fa-robot"></i><span>AI Assistant</span>';
        toggleBtn.classList.remove('active');
    }
    
    console.log('AI Assistant closed');
}

// Đảm bảo event listeners được gắn đúng
document.addEventListener('DOMContentLoaded', function() {
    // Toggle AI button
    const aiToggleBtn = document.getElementById('btn-toggle-ai');
    if (aiToggleBtn) {
        aiToggleBtn.addEventListener('click', toggleAIAssistant);
    }
    
    // Close AI button
    const aiCloseBtn = document.getElementById('ai-lesson-close');
    if (aiCloseBtn) {
        aiCloseBtn.addEventListener('click', closeAIAssistant);
    }
});

// ==================== UPLOAD SUBMISSION WITH AI VALIDATION ====================

async function uploadSubmission(taskId) {
  // Check if task has started
  try {
    const response = await fetch(`api/tasks.php?filter=all`);
    const data = await response.json();
    const task = data.tasks.find((t) => t.id === taskId);

    if (task && !task.has_started) {
      Swal.fire({
        icon: "warning",
        title: "Chưa thể nộp bài",
        html: `
          <p>Nhiệm vụ này chưa bắt đầu.</p>
          <p><strong>Thời gian bắt đầu:</strong> ${formatDateTime(task.start_date)}</p>
        `,
        confirmButtonColor: "#2E7D32",
      });
      return;
    }

    if (task && task.is_overdue) {
      Swal.fire({
        icon: "error",
        title: "Đã quá hạn",
        text: "Nhiệm vụ này đã hết hạn nộp bài.",
        confirmButtonColor: "#2E7D32",
      });
      return;
    }

    // Store task info for AI validation
    window.currentTaskForValidation = task;
  } catch (error) {
    console.error("Error checking task status:", error);
  }

  // ✅ Show file upload with AI validation option
  const { value: formValues } = await Swal.fire({
    title: 'Nộp bài làm',
    html: `
      <div class="upload-submission-container">
        <div class="upload-zone" id="upload-zone">
          <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #2E7D32; margin-bottom: 16px;"></i>
          <p style="margin: 8px 0; font-size: 16px; font-weight: 600;">Chọn file để tải lên</p>
          <p style="margin: 4px 0; font-size: 13px; color: #666;">Hỗ trợ: PDF, DOC, DOCX, ZIP, TXT, JPG, PNG</p>
          <input type="file" id="submission-file" accept=".pdf,.doc,.docx,.zip,.txt,.jpg,.jpeg,.png" style="display: none;">
          <button type="button" class="btn-browse-file" onclick="document.getElementById('submission-file').click()" style="margin-top: 16px; padding: 10px 24px; background: #2E7D32; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
            <i class="fas fa-folder-open"></i> Chọn file
          </button>
        </div>
        
        <div class="file-info" id="file-info" style="display: none; margin-top: 16px; padding: 16px; background: #f5f5f5; border-radius: 8px;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-file-alt" style="font-size: 32px; color: #2E7D32;"></i>
            <div style="flex: 1; text-align: left;">
              <p id="file-name" style="margin: 0; font-weight: 600;"></p>
              <p id="file-size" style="margin: 4px 0 0 0; font-size: 13px; color: #666;"></p>
            </div>
            <button type="button" class="btn-remove-file" onclick="removeSelectedFile()" style="padding: 8px 12px; background: #f44336; color: white; border: none; border-radius: 6px; cursor: pointer;">
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>

        <div class="ai-validation-option" style="margin-top: 20px; padding: 16px; background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%); border-radius: 12px; border: 2px solid #4CAF50;">
          <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <i class="fas fa-robot" style="font-size: 24px; color: #2E7D32;"></i>
            <div style="flex: 1; text-align: left;">
              <p style="margin: 0; font-weight: 600; color: #2E7D32;">AI Đánh giá minh chứng</p>
              <p style="margin: 4px 0 0 0; font-size: 13px; color: #1B5E20;">Để AI kiểm tra minh chứng trước khi nộp</p>
            </div>
          </div>
          <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;">
            <input type="checkbox" id="enable-ai-validation" checked style="width: 18px; height: 18px; cursor: pointer;">
            <span style="font-size: 14px; color: #1B5E20; font-weight: 500;">Bật đánh giá AI (Khuyến nghị)</span>
          </label>
        </div>
      </div>
    `,
    width: '600px',
    showCancelButton: true,
    confirmButtonText: '<i class="fas fa-check"></i> Tiếp tục',
    cancelButtonText: '<i class="fas fa-times"></i> Hủy',
    confirmButtonColor: '#2E7D32',
    cancelButtonColor: '#999',
    didOpen: () => {
      setupFileUploadHandlers();
    },
    preConfirm: () => {
      const fileInput = document.getElementById('submission-file');
      const enableAI = document.getElementById('enable-ai-validation').checked;
      
      if (!fileInput.files || !fileInput.files[0]) {
        Swal.showValidationMessage('Vui lòng chọn file để nộp');
        return false;
      }
      
      return {
        file: fileInput.files[0],
        enableAI: enableAI
      };
    }
  });

  if (formValues && formValues.file) {
    // If AI validation is enabled, validate first
    if (formValues.enableAI) {
      await validateSubmissionWithAI(formValues.file, taskId);
    } else {
      await submitFile(formValues.file, taskId);
    }
  }
}

// ==================== FILE UPLOAD HANDLERS ====================

function setupFileUploadHandlers() {
  const fileInput = document.getElementById('submission-file');
  const uploadZone = document.getElementById('upload-zone');
  
  // File input change handler
  fileInput.addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
      displaySelectedFile(this.files[0]);
    }
  });
  
  // Drag and drop handlers
  uploadZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.style.borderColor = '#2E7D32';
    this.style.background = '#E8F5E9';
  });
  
  uploadZone.addEventListener('dragleave', function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.style.borderColor = '#ddd';
    this.style.background = '#fafafa';
  });
  
  uploadZone.addEventListener('drop', function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.style.borderColor = '#ddd';
    this.style.background = '#fafafa';
    
    const files = e.dataTransfer.files;
    if (files && files[0]) {
      fileInput.files = files;
      displaySelectedFile(files[0]);
    }
  });
}

function displaySelectedFile(file) {
  const fileInfo = document.getElementById('file-info');
  const fileName = document.getElementById('file-name');
  const fileSize = document.getElementById('file-size');
  const uploadZone = document.getElementById('upload-zone');
  
  fileName.textContent = file.name;
  fileSize.textContent = formatFileSize(file.size);
  
  fileInfo.style.display = 'block';
  uploadZone.style.display = 'none';
}

function removeSelectedFile() {
  const fileInput = document.getElementById('submission-file');
  const fileInfo = document.getElementById('file-info');
  const uploadZone = document.getElementById('upload-zone');
  
  fileInput.value = '';
  fileInfo.style.display = 'none';
  uploadZone.style.display = 'flex';
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// ==================== AI VALIDATION ====================

async function validateSubmissionWithAI(file, taskId) {
  Swal.fire({
    title: '<i class="fas fa-robot"></i> AI đang phân tích...',
    html: `
      <div style="text-align: center; padding: 20px;">
        <div class="ai-analyzing-animation" style="margin: 20px 0;">
          <i class="fas fa-brain" style="font-size: 64px; color: #2E7D32; animation: pulse 1.5s ease-in-out infinite;"></i>
        </div>
        <p style="margin: 16px 0 8px 0; font-size: 15px; font-weight: 600;">Đang kiểm tra minh chứng</p>
        <p style="margin: 0; font-size: 13px; color: #666;">Vui lòng đợi trong giây lát...</p>
        <div class="validation-steps" style="margin-top: 20px; text-align: left;">
          <div class="step-item" id="step-1">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Đang phân tích nội dung file...</span>
          </div>
          <div class="step-item" id="step-2" style="color: #999;">
            <i class="far fa-circle"></i>
            <span>Kiểm tra tính phù hợp với yêu cầu...</span>
          </div>
          <div class="step-item" id="step-3" style="color: #999;">
            <i class="far fa-circle"></i>
            <span>Đánh giá tính xác thực...</span>
          </div>
        </div>
      </div>
    `,
    allowOutsideClick: false,
    showConfirmButton: false,
    didOpen: () => {
      // Add CSS animation
      const pulseAnimationStyle = document.createElement('style');
      pulseAnimationStyle.textContent = `
        @keyframes pulse {
          0%, 100% { transform: scale(1); opacity: 1; }
          50% { transform: scale(1.1); opacity: 0.8; }
        }
        .step-item {
          display: flex;
          align-items: center;
          gap: 12px;
          padding: 8px 0;
          font-size: 14px;
        }
        .step-item i {
          width: 20px;
          text-align: center;
        }
      `;
      document.head.appendChild(pulseAnimationStyle);
    }
  });

  try {
    // Prepare form data
    const formData = new FormData();
    formData.append('file', file);
    formData.append('task_id', taskId);

    // Simulate step progression
    setTimeout(() => updateValidationStep(1), 1000);
    setTimeout(() => updateValidationStep(2), 2000);
    setTimeout(() => updateValidationStep(3), 3000);

    // Call AI validation API
    const response = await fetch('api/ai-validate-submission.php', {
      method: 'POST',
      body: formData
    });

    const data = await response.json();

    if (data.success) {
      showAIValidationResult(data.validation, file, taskId);
    } else {
      throw new Error(data.message || 'AI validation failed');
    }

  } catch (error) {
    console.error('AI validation error:', error);
    
    // Ask if user wants to submit anyway
    const result = await Swal.fire({
      icon: 'warning',
      title: 'Không thể đánh giá AI',
      html: `
        <p>Đã xảy ra lỗi khi AI đánh giá minh chứng.</p>
        <p style="color: #666; font-size: 14px; margin-top: 12px;">
          ${error.message || 'Vui lòng thử lại hoặc nộp bài không qua AI'}
        </p>
      `,
      showCancelButton: true,
      confirmButtonText: 'Nộp bài ngay',
      cancelButtonText: 'Thử lại',
      confirmButtonColor: '#2E7D32',
      cancelButtonColor: '#FF9800'
    });

    if (result.isConfirmed) {
      await submitFile(file, taskId);
    } else {
      uploadSubmission(taskId);
    }
  }
}

function updateValidationStep(step) {
  const steps = ['step-1', 'step-2', 'step-3'];
  
  // Mark previous steps as completed
  for (let i = 0; i < step; i++) {
    const stepEl = document.getElementById(steps[i]);
    if (stepEl) {
      stepEl.style.color = '#2E7D32';
      stepEl.querySelector('i').className = 'fas fa-check-circle';
    }
  }
  
  // Update current step
  if (step < 3) {
    const currentStep = document.getElementById(steps[step]);
    if (currentStep) {
      currentStep.style.color = '#000';
      currentStep.querySelector('i').className = 'fas fa-spinner fa-spin';
    }
  }
}

function showAIValidationResult(validation, file, taskId) {
  const scoreColor = validation.overall_score >= 80 ? '#4CAF50' : 
                     validation.overall_score >= 60 ? '#FF9800' : '#F44336';
  
  const scoreIcon = validation.overall_score >= 80 ? 'fa-check-circle' : 
                    validation.overall_score >= 60 ? 'fa-exclamation-circle' : 'fa-times-circle';

  Swal.fire({
    title: '<i class="fas fa-robot"></i> Kết quả đánh giá AI',
    html: `
      <div class="ai-validation-result">
        <!-- Overall Score -->
        <div class="validation-score-card" style="background: linear-gradient(135deg, ${scoreColor}15 0%, ${scoreColor}25 100%); border: 2px solid ${scoreColor}; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
          <div style="display: flex; align-items: center; justify-content: center; gap: 16px;">
            <i class="fas ${scoreIcon}" style="font-size: 48px; color: ${scoreColor};"></i>
            <div>
              <p style="margin: 0; font-size: 14px; color: #666;">Điểm tổng quan</p>
              <p style="margin: 4px 0 0 0; font-size: 32px; font-weight: bold; color: ${scoreColor};">${validation.overall_score}/100</p>
            </div>
          </div>
          <p style="margin: 12px 0 0 0; font-size: 14px; font-weight: 600; color: ${scoreColor};">
            ${validation.overall_assessment}
          </p>
        </div>

        <!-- Detailed Assessments -->
        <div class="validation-details" style="text-align: left;">
          
          <!-- Relevance Check -->
          <div class="validation-item" style="margin-bottom: 16px; padding: 16px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid ${getScoreColor(validation.relevance_score)};">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span style="font-weight: 600; color: #333;">
                <i class="fas fa-bullseye"></i> Phù hợp với yêu cầu
              </span>
              <span style="font-weight: bold; color: ${getScoreColor(validation.relevance_score)};">
                ${validation.relevance_score}/100
              </span>
            </div>
            <p style="margin: 0; font-size: 13px; color: #666; line-height: 1.6;">
              ${validation.relevance_feedback}
            </p>
          </div>

          <!-- Authenticity Check -->
          <div class="validation-item" style="margin-bottom: 16px; padding: 16px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid ${getScoreColor(validation.authenticity_score)};">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span style="font-weight: 600; color: #333;">
                <i class="fas fa-shield-alt"></i> Tính xác thực
              </span>
              <span style="font-weight: bold; color: ${getScoreColor(validation.authenticity_score)};">
                ${validation.authenticity_score}/100
              </span>
            </div>
            <p style="margin: 0; font-size: 13px; color: #666; line-height: 1.6;">
              ${validation.authenticity_feedback}
            </p>
          </div>

          <!-- Quality Check -->
          <div class="validation-item" style="margin-bottom: 16px; padding: 16px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid ${getScoreColor(validation.quality_score)};">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span style="font-weight: 600; color: #333;">
                <i class="fas fa-star"></i> Chất lượng minh chứng
              </span>
              <span style="font-weight: bold; color: ${getScoreColor(validation.quality_score)};">
                ${validation.quality_score}/100
              </span>
            </div>
            <p style="margin: 0; font-size: 13px; color: #666; line-height: 1.6;">
              ${validation.quality_feedback}
            </p>
          </div>

        </div>

        <!-- Recommendations -->
        ${validation.recommendations && validation.recommendations.length > 0 ? `
          <div class="validation-recommendations" style="margin-top: 20px; padding: 16px; background: #E3F2FD; border-radius: 8px; border-left: 4px solid #2196F3;">
            <p style="margin: 0 0 12px 0; font-weight: 600; color: #1976D2;">
              <i class="fas fa-lightbulb"></i> Đề xuất cải thiện
            </p>
            <ul style="margin: 0; padding-left: 20px; text-align: left;">
              ${validation.recommendations.map(rec => `<li style="margin: 6px 0; font-size: 13px; color: #555;">${rec}</li>`).join('')}
            </ul>
          </div>
        ` : ''}

        <!-- File Info -->
        <div class="file-summary" style="margin-top: 20px; padding: 12px; background: #f5f5f5; border-radius: 8px;">
          <p style="margin: 0; font-size: 13px; color: #666;">
            <i class="fas fa-file-alt"></i> <strong>${file.name}</strong> (${formatFileSize(file.size)})
          </p>
        </div>
      </div>
    `,
    width: '700px',
    showCancelButton: true,
    confirmButtonText: validation.overall_score >= 60 ? '<i class="fas fa-check"></i> Nộp bài' : '<i class="fas fa-edit"></i> Chọn lại file',
    cancelButtonText: validation.overall_score >= 60 ? '<i class="fas fa-edit"></i> Chọn lại file' : '<i class="fas fa-times"></i> Hủy',
    confirmButtonColor: validation.overall_score >= 60 ? '#2E7D32' : '#FF9800',
    cancelButtonColor: validation.overall_score >= 60 ? '#999' : '#F44336',
    reverseButtons: validation.overall_score < 60
  }).then(async (result) => {
    if (result.isConfirmed) {
      if (validation.overall_score >= 60) {
        await submitFile(file, taskId);
      } else {
        uploadSubmission(taskId);
      }
    } else if (result.dismiss === Swal.DismissReason.cancel && validation.overall_score >= 60) {
      uploadSubmission(taskId);
    }
  });
}

function getScoreColor(score) {
  if (score >= 80) return '#4CAF50';
  if (score >= 60) return '#FF9800';
  return '#F44336';
}

// ==================== SUBMIT FILE ====================

async function submitFile(file, taskId) {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('task_id', taskId);

  try {
    Swal.fire({
      title: 'Đang tải lên...',
      html: `
        <div style="padding: 20px;">
          <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #2E7D32; margin-bottom: 16px;"></i>
          <p>Đang nộp bài của bạn...</p>
          <div class="upload-progress" style="margin-top: 16px;">
            <div style="width: 100%; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
              <div style="width: 0%; height: 100%; background: linear-gradient(90deg, #2E7D32, #4CAF50); animation: progress 2s ease-in-out forwards;"></div>
            </div>
          </div>
        </div>
      `,
      allowOutsideClick: false,
      showConfirmButton: false,
      didOpen: () => {
        const progressAnimationStyle = document.createElement('style');
        progressAnimationStyle.textContent = `
          @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
          }
        `;
        document.head.appendChild(progressAnimationStyle);
      }
    });

    const response = await fetch('api/upload-submission.php', {
      method: 'POST',
      body: formData
    });

    const data = await response.json();

    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Thành công!',
        text: 'Bài làm đã được nộp thành công',
        confirmButtonColor: '#2E7D32'
      });
      
      const activeFilter = document.querySelector('.filter-tab.active');
      const filter = activeFilter ? activeFilter.getAttribute('data-filter') : 'all';
      loadTasks(filter);
    } else {
      throw new Error(data.message || 'Không thể nộp bài');
    }
  } catch (error) {
    console.error('Error uploading submission:', error);
    Swal.fire('Lỗi', error.message || 'Không thể nộp bài', 'error');
  }
}

// ... rest of existing code ...

async function viewSubmission(taskId) {
  try {
    const response = await fetch(`api/submission-detail.php?task_id=${taskId}`);

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success && data.submission) {
      const scorePercentage =
        (data.submission.score / data.submission.max_score) * 100;
      let scoreClass = "poor";
      if (scorePercentage >= 80) scoreClass = "excellent";
      else if (scorePercentage >= 65) scoreClass = "good";
      else if (scorePercentage >= 50) scoreClass = "average";

      Swal.fire({
        title: "Bài nộp của bạn",
        html: `
          <div class="submission-content">
            <div class="submission-info-grid">
              <div class="submission-info-item">
                <div class="submission-info-label">
                  <i class="fas fa-tasks"></i>
                  <span>Nhiệm vụ</span>
                </div>
                <div class="submission-info-value">${
                  data.submission.task_name || "N/A"
                }</div>
              </div>
              
              <div class="submission-info-item">
                <div class="submission-info-label">
                  <i class="fas fa-book"></i>
                  <span>Lớp học</span>
                </div>
                <div class="submission-info-value">${
                  data.submission.class_name || "N/A"
                }</div>
              </div>
              
              <div class="submission-info-item">
                <div class="submission-info-label">
                  <i class="fas fa-clock"></i>
                  <span>Thời gian nộp</span>
                </div>
                <div class="submission-info-value">${formatDateTime(
                  data.submission.submitted_at
                )}</div>
              </div>
              
              <div class="submission-info-item">
                <div class="submission-info-label">
                  <i class="fas fa-info-circle"></i>
                  <span>Trạng thái</span>
                </div>
                <div class="submission-info-value">
                  <span class="submission-status-badge ${
                    data.submission.is_graded ? "graded" : "pending"
                  }">
                    <i class="fas ${
                      data.submission.is_graded ? "fa-check-double" : "fa-clock"
                    }"></i>
                    ${data.submission.is_graded ? "Đã chấm" : "Chờ chấm"}
                  </span>
                </div>
              </div>
            </div>

            ${
              data.submission.score !== null
                ? `
              <div class="submission-info-item" style="text-align: center; margin: 24px 0;">
                <div class="submission-info-label" style="justify-content: center;">
                  <i class="fas fa-star"></i>
                  <span>Điểm số</span>
                </div>
                <div class="submission-info-value" style="margin-top: 12px;">
                  <span class="submission-score-display ${scoreClass}">
                    <i class="fas fa-award"></i>
                    ${data.submission.score}/${data.submission.max_score}
                  </span>
                </div>
              </div>
            `
                : `
              <div class="submission-info-item" style="text-align: center; margin: 24px 0; border-left-color: #FF9800;">
                <div class="submission-info-label" style="justify-content: center; color: #F57C00;">
                  <i class="fas fa-hourglass-half"></i>
                  <span>Điểm số</span>
                </div>
                <div class="submission-info-value" style="margin-top: 8px; color: #F57C00;">
                  Chưa được chấm điểm
                </div>
              </div>
            `
            }

            ${
              data.submission.feedback
                ? `
              <div class="submission-feedback-box">
                <div class="submission-feedback-header">
                  <i class="fas fa-comment-dots"></i>
                  <span>Nhận xét từ giảng viên</span>
                </div>
                <p class="submission-feedback-text">${data.submission.feedback}</p>
              </div>
            `
                : `
              <div class="submission-no-feedback">
                <i class="fas fa-comment-slash"></i>
                <p style="margin: 0;">Chưa có nhận xét từ giảng viên</p>
              </div>
            `
            }

            ${
              data.submission.file_path
                ? `
              <div class="submission-file-section">
                <div class="submission-file-icon">
                  <i class="fas fa-file-alt"></i>
                </div>
                <p class="submission-file-label">
                  <i class="fas fa-paperclip"></i>
                  File bài làm đã nộp
                </p>
                <button 
                  onclick="viewSubmissionFile('${data.submission.file_path}')" 
                  class="submission-view-file-btn"
                >
                  <i class="fas fa-eye"></i>
                  <span>Xem bài làm</span>
                </button>
              </div>
            `
                : `
              <div class="submission-no-file">
                <i class="fas fa-file-excel"></i>
                <p style="margin: 0;">Không có file đính kèm</p>
              </div>
            `
            }
          </div>
        `,
        width: "700px",
        confirmButtonText: "Đóng",
        confirmButtonColor: "#2E7D32",
        customClass: {
          popup: "submission-detail-popup",
        },
      });
    } else {
      Swal.fire({
        icon: "error",
        title: "Lỗi",
        text: data.message || "Không thể tải thông tin bài nộp",
        confirmButtonColor: "#2E7D32",
      });
    }
  } catch (error) {
    console.error("Error viewing submission:", error);
    Swal.fire({
      icon: "error",
      title: "Lỗi",
      text: "Không thể tải bài nộp. Vui lòng thử lại.",
      confirmButtonColor: "#2E7D32",
    });
  }
}

function viewSubmissionFile(filePath) {
  if (!filePath) {
    Swal.fire("Lỗi", "Không tìm thấy file", "error");
    return;
  }

  // Get file extension
  const extension = filePath.split(".").pop().toLowerCase();
  const fullPath = `../uploads/submissions/${filePath}`;

  // Check if file is viewable
  const viewableExtensions = ["pdf", "jpg", "jpeg", "png", "gif", "txt"];
  const isViewable = viewableExtensions.includes(extension);

  if (isViewable) {
    // Show file in modal
    Swal.fire({
      title: "Bài làm của bạn",
      html: `
                            <div style="margin-top: 10px;">
                    <a 
                        href="${fullPath}" 
                        download 
                        class="btn btn-outline" 
                        style="display: inline-block; padding: 10px 20px; border: 2px solid #2E7D32; color: #2E7D32; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.3s;"
                        onmouseover="this.style.background='#2E7D32'; this.style.color='white';"
                        onmouseout="this.style.background='transparent'; this.style.color='#2E7D32';"
                    >
                        <i class="fas fa-download"></i> Tải xuống
                    </a>
                </div>
                <div style="width: 100%; height: 600px; overflow: auto;">
                    ${
                      extension === "pdf"
                        ? `
                        <iframe 
                            src="${fullPath}" 
                            style="width: 100%; height: 100%; border: none;"
                            type="application/pdf"
                        ></iframe>
                    `
                        : extension === "txt"
                        ? `
                        <iframe 
                            src="${fullPath}" 
                            style="width: 100%; height: 100%; border: none; background: white; padding: 20px;"
                        ></iframe>
                    `
                        : `
                        <img 
                            src="${fullPath}" 
                            style="max-width: 100%; height: auto; display: block; margin: 0 auto;"
                            alt="Bài làm"
                        />
                    `
                    }
                </div>
            `,
      width: "900px",
      showConfirmButton: false,
      showCloseButton: true,
      customClass: {
        popup: "file-viewer-popup",
      },
    });
  } else {
    // File type not viewable, show download option
    Swal.fire({
      icon: "info",
      title: "File không thể xem trực tiếp",
      html: `
                <p style="margin-bottom: 20px;">
                    File có định dạng <strong>.${extension}</strong> không thể xem trực tiếp trong trình duyệt.
                </p>
                <a 
                    href="${fullPath}" 
                    download 
                    class="btn btn-primary" 
                    style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #2E7D32 0%, #1B5E20 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;"
                >
                    <i class="fas fa-download"></i> Tải xuống file
                </a>
            `,
      showConfirmButton: false,
      showCloseButton: true,
      confirmButtonColor: "#2E7D32",
    });
  }
}

// Helper function to get score styling
function getScoreStyle(score, maxScore) {
  const percentage = (score / maxScore) * 100;
  if (percentage >= 80) {
    return "background: #E8F5E9; color: #2E7D32;"; // Green
  } else if (percentage >= 50) {
    return "background: #FFF3E0; color: #F57C00;"; // Orange
  } else {
    return "background: #FFEBEE; color: #C62828;"; // Red
  }
}
// ==================== UTILITY FUNCTIONS ====================

function formatDate(dateString) {
  if (!dateString) return "";
  const date = new Date(dateString);
  return date.toLocaleDateString("vi-VN");
}

function formatDateTime(dateString) {
  if (!dateString) return "";
  const date = new Date(dateString);
  return date.toLocaleString("vi-VN", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

// Close modal when clicking outside
document.addEventListener("DOMContentLoaded", function () {
  const lessonModal = document.getElementById("lesson-modal");
  if (lessonModal) {
    lessonModal.addEventListener("click", function (e) {
      if (e.target === this) {
        closeLessonModal();
      }
    });
  }
});

// Logout function
function xacNhanDangXuat() {
  Swal.fire({
    title: "Bạn có chắc chắn muốn đăng xuất?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Đăng xuất",
    cancelButtonText: "Hủy",
    confirmButtonColor: "#2E7D32",
    cancelButtonColor: "#3085d6",
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = "../dangnhap/dangxuat.php";
    }
  });
}

// Add CSS animations
const slideAnimationStyle = document.createElement("style");
slideAnimationStyle.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(slideAnimationStyle);