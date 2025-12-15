// Suppress Chrome extension errors
(function() {
    const originalError = console.error;
    console.error = function(...args) {
        if (args[0] && args[0].includes && args[0].includes('Could not establish connection')) {
            return;
        }
        originalError.apply(console, args);
    };
})();

// Global variables
let currentSection = "dashboard";
let currentClassId = null;
let allClasses = [];
let allAssignments = [];
let allSubmissions = [];

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  initializePage();
  setupEventListeners();
  loadDashboardData();
  initializeSidebar();
});

// Load recent activities
let currentActivitiesPage = 1;
async function loadRecentActivities(page = 1) {
  try {
    currentActivitiesPage = page;
    console.log('Loading activities, page:', page);
    
    const response = await fetch(`api/get_recent_activities.php?page=${page}`);
    
    // ✅ Check response status
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    
    const data = await response.json();
    console.log('Activities response:', data);

    const activityList = document.getElementById("recent-activity-list");

    if (!activityList) {
      console.error('Activity list element not found');
      return;
    }

    if (data.success && data.activities && data.activities.length > 0) {
      const activitiesHTML = data.activities
        .map((activity) => {
          // ✅ Comprehensive validation
          if (!activity) {
            console.warn('Null activity item');
            return '';
          }

          // ✅ Validate required fields with detailed logging
          if (!activity.type) {
            console.warn('Missing type:', activity);
            return '';
          }
          
          if (!activity.activity_time) {
            console.warn('Missing activity_time:', activity);
            return '';
          }

          let icon, text, iconColor;
          const activityDate = new Date(activity.activity_time);
          
          // ✅ Validate date
          if (isNaN(activityDate.getTime())) {
            console.warn('Invalid date:', activity.activity_time, 'for activity:', activity);
            return '';
          }
          
          const timeAgo = formatTimeAgo(activityDate);

          // Use fallback values
          const itemTitle = activity.item_title || 'Bài tập';
          const className = activity.class_name || 'Lớp học';
          const studentName = activity.student_name || 'Học sinh';

          switch(activity.type) {
            case "submission":
              icon = "fas fa-file-upload";
              iconColor = "#4CAF50";
              text = `<strong>${studentName}</strong> đã nộp bài <strong>${itemTitle}</strong> trong lớp <strong>${className}</strong>`;
              break;
            case "enrollment":
              icon = "fas fa-user-plus";
              iconColor = "#2196F3";
              text = `<strong>${studentName}</strong> đã tham gia lớp <strong>${className}</strong>`;
              break;
            case "assignment_created":
              icon = "fas fa-plus-circle";
              iconColor = "#FF9800";
              text = `Nhiệm vụ mới <strong>${itemTitle}</strong> đã được tạo trong lớp <strong>${className}</strong>`;
              break;
            case "assignment_scheduled":
              icon = "fas fa-calendar-plus";
              iconColor = "#9C27B0";
              text = `Nhiệm vụ <strong>${itemTitle}</strong> đã được lên lịch trong lớp <strong>${className}</strong>`;
              break;
            default:
              icon = "fas fa-bell";
              iconColor = "#757575";
              text = `Hoạt động mới: ${activity.type}`;
          }

          return `
            <div class="activity-item">
              <div class="activity-icon" style="background: ${iconColor}15; color: ${iconColor}">
                <i class="${icon}"></i>
              </div>
              <div class="activity-content">
                <p class="activity-text">${text}</p>
                <span class="activity-time"><i class="fas fa-clock"></i> ${timeAgo}</span>
              </div>
            </div>
          `;
        })
        .filter(html => html && html.trim() !== '') // ✅ Remove empty/null entries
        .join("");

      if (activitiesHTML) {
        const paginationHTML = data.pagination ? createPagination(
          data.pagination,
          "loadRecentActivities"
        ) : '';

        activityList.innerHTML = activitiesHTML + paginationHTML;
      } else {
        activityList.innerHTML =
          '<p class="empty-state"><i class="fas fa-info-circle"></i> Chưa có hoạt động nào</p>';
      }
    } else {
      console.log('No activities or empty response:', data);
      activityList.innerHTML =
        '<p class="empty-state"><i class="fas fa-info-circle"></i> Chưa có hoạt động nào</p>';
    }
  } catch (error) {
    console.error("Error loading recent activities:", error);
    const activityList = document.getElementById("recent-activity-list");
    if (activityList) {
      activityList.innerHTML =
        `<p class="empty-state error"><i class="fas fa-exclamation-triangle"></i> Không thể tải hoạt động<br><small>${error.message}</small></p>`;
    }
  }
}

// Load upcoming deadlines
let currentDeadlinesPage = 1;
async function loadUpcomingDeadlines(page = 1) {
  try {
    currentDeadlinesPage = page;
    const response = await fetch(`api/get_upcoming_deadlines.php?page=${page}`);
    const data = await response.json();

    const deadlinesList = document.getElementById("upcoming-deadlines-list");

    if (data.success && data.deadlines.length > 0) {
      const deadlinesHTML = data.deadlines
        .map((deadline) => {
          const deadlineDate = new Date(deadline.deadline);
          const startDate = deadline.start_date ? new Date(deadline.start_date) : null;
          const now = new Date();
          const diffMs = deadlineDate - now;
          const diffDays = Math.ceil(diffMs / 86400000);

          let urgencyClass = "";
          let urgencyBadge = "";
          let statusIcon = "";

          // Determine status
          if (startDate && startDate > now) {
            urgencyClass = "scheduled";
            urgencyBadge = "Sắp diễn ra";
            statusIcon = "fas fa-calendar-alt";
          } else if (diffDays <= 1) {
            urgencyClass = "urgent";
            urgencyBadge = "Khẩn cấp";
            statusIcon = "fas fa-exclamation-circle";
          } else if (diffDays <= 3) {
            urgencyClass = "soon";
            urgencyBadge = "Sắp hết hạn";
            statusIcon = "fas fa-clock";
          } else if (diffDays <= 7) {
            urgencyClass = "week";
            urgencyBadge = "Trong tuần";
            statusIcon = "fas fa-calendar-week";
          } else {
            urgencyClass = "normal";
            urgencyBadge = "Còn thời gian";
            statusIcon = "fas fa-calendar-check";
          }

          const daysText = diffDays === 0 ? "Hôm nay" : 
                          diffDays === 1 ? "Ngày mai" : 
                          `Còn ${diffDays} ngày`;

          const submissionRate = deadline.total_students > 0 
            ? Math.round((deadline.submission_count / deadline.total_students) * 100) 
            : 0;

          return `
            <div class="deadline-item ${urgencyClass}">
              <div class="deadline-header">
                <div class="deadline-status-badge ${urgencyClass}">
                  <i class="${statusIcon}"></i>
                  ${urgencyBadge}
                </div>
                <div class="deadline-progress">
                  <span class="progress-text">${deadline.submission_count}/${deadline.total_students}</span>
                  <div class="progress-bar-mini">
                    <div class="progress-fill-mini" style="width: ${submissionRate}%"></div>
                  </div>
                </div>
              </div>
              <div class="deadline-content">
                <h4 class="deadline-title">
                  <i class="fas fa-clipboard-list"></i>
                  ${deadline.title}
                </h4>
                <p class="deadline-class">
                  <i class="fas fa-chalkboard"></i>
                  ${deadline.class_name}
                </p>
                <div class="deadline-footer">
                  <span class="deadline-date">
                    <i class="fas fa-calendar-alt"></i>
                    ${daysText}
                  </span>
                  <span class="deadline-time">
                    ${deadlineDate.toLocaleDateString("vi-VN", {
                      day: "2-digit",
                      month: "2-digit",
                      year: "numeric"
                    })}
                  </span>
                </div>
              </div>
            </div>
          `;
        })
        .join("");

      const paginationHTML = createPagination(
        data.pagination,
        "loadUpcomingDeadlines"
      );

      deadlinesList.innerHTML = deadlinesHTML + paginationHTML;
    } else {
      deadlinesList.innerHTML =
        '<p class="empty-state"><i class="fas fa-check-circle"></i> Không có hạn chót sắp tới</p>';
    }
  } catch (error) {
    console.error("Error loading upcoming deadlines:", error);
    document.getElementById("upcoming-deadlines-list").innerHTML =
      '<p class="empty-state error"><i class="fas fa-exclamation-triangle"></i> Không thể tải hạn chót</p>';
  }
}

// Initialize page
function initializePage() {
  // Set up sidebar trigger
  const sidebarTrigger = document.querySelector(".sidebar-trigger");
  const sidebar = document.getElementById("sidebar");

  if (sidebarTrigger && sidebar) {
    sidebarTrigger.addEventListener("mouseenter", () => {
      sidebar.classList.add("active");
    });

    sidebar.addEventListener("mouseleave", () => {
      sidebar.classList.remove("active");
    });
  }
}

// Initialize sidebar functionality
function initializeSidebar() {
  const sidebarTrigger = document.querySelector(".sidebar-trigger");
  const sidebar = document.getElementById("sidebar");
  const mainContainer = document.querySelector(".main-container");
  let sidebarTimer;

  if (sidebarTrigger && sidebar && mainContainer) {
    // Show sidebar
    sidebarTrigger.addEventListener("mouseenter", () => {
      clearTimeout(sidebarTimer);
      requestAnimationFrame(() => {
        sidebar.style.transform = "translateX(-300px)";
        mainContainer.style.marginRight = "300px";
        sidebar.classList.add("active");
      });
    });

    // Hide sidebar
    const closeSidebarWithTransition = () => {
      clearTimeout(sidebarTimer);
      sidebarTimer = setTimeout(() => {
        if (!sidebar.matches(":hover") && !sidebarTrigger.matches(":hover")) {
          requestAnimationFrame(() => {
            sidebar.style.transform = "translateX(0)";
            mainContainer.style.marginRight = "0";
            sidebar.addEventListener(
              "transitionend",
              () => {
                sidebar.classList.remove("active");
              },
              { once: true }
            );
          });
        }
      }, 200);
    };

    sidebarTrigger.addEventListener("mouseleave", closeSidebarWithTransition);
    sidebar.addEventListener("mouseleave", closeSidebarWithTransition);
  }
}

function closeSidebar() {
  const sidebar = document.getElementById("sidebar");
  const mainContainer = document.querySelector(".main-container");

  if (sidebar && mainContainer) {
    requestAnimationFrame(() => {
      sidebar.classList.remove("active");
      mainContainer.classList.remove("sidebar-open");
    });
  }
}

function closeSidebarSmooth() {
  const sidebar = document.getElementById("sidebar");
  const mainContainer = document.querySelector(".main-container");

  if (sidebar && mainContainer) {
    sidebar.classList.remove("active");
    mainContainer.classList.remove("sidebar-open");
  }
}

// Setup event listeners
function setupEventListeners() {
  // Menu items
  document.querySelectorAll(".menu-item").forEach((item) => {
    item.addEventListener("click", function () {
      const section = this.getAttribute("data-section");
      showSection(section);
    });
  });

  // Filter tabs for assignments
  document.querySelectorAll(".filter-tab").forEach((tab) => {
    tab.addEventListener("click", function () {
      document
        .querySelectorAll(".filter-tab")
        .forEach((t) => t.classList.remove("active"));
      this.classList.add("active");
      const filter = this.getAttribute("data-filter");
      filterAssignments(filter);
    });
  });

  // Class filter for submissions
  const classFilter = document.getElementById("class-filter");
  const statusFilter = document.getElementById("status-filter");

  if (classFilter) {
    classFilter.addEventListener("change", loadSubmissions);
  }

  if (statusFilter) {
    statusFilter.addEventListener("change", loadSubmissions);
  }

  // Statistics class filter
  const statsClassFilter = document.getElementById("stats-class-filter");
  if (statsClassFilter) {
    statsClassFilter.addEventListener("change", function () {
      if (this.value) {
        loadClassStatistics(this.value);
      }
    });
  }

  // Students class filter
  const studentsClassFilter = document.getElementById("students-class-filter");
  if (studentsClassFilter) {
    studentsClassFilter.addEventListener("change", function () {
      if (this.value) {
        loadStudentsList(this.value);
      }
    });
  }
  // Create class form
  const createClassForm = document.getElementById("create-class-form");
  if (createClassForm) {
    createClassForm.addEventListener("submit", handleCreateClass);
  }

  // Create assignment form
  const createAssignmentForm = document.getElementById(
    "create-assignment-form"
  );
  if (createAssignmentForm) {
    createAssignmentForm.addEventListener("submit", handleCreateAssignment);
  }

  // Add chapter form
  const addChapterForm = document.getElementById("add-chapter-form");
  if (addChapterForm) {
    addChapterForm.addEventListener("submit", handleAddChapter);
  }

  // Add lesson form
  const addLessonForm = document.getElementById("add-lesson-form");
  if (addLessonForm) {
    addLessonForm.addEventListener("submit", handleAddLesson);
  }

  // File upload handling
  setupFileUpload();
}

async function viewStudentDetail(studentId, classId) {
    try {
        const response = await fetch(`api/get_student_detail.php?student_id=${studentId}&class_id=${classId}`);
        const data = await response.json();

        if (data.success) {
            const now = new Date();
            
            Swal.fire({
                title: 'Chi tiết học sinh',
                html: `
                    <div class="student-detail-modal">
                        <div class="student-info">
                            <h4>${data.student.name}</h4>
                            <p class="student-email">${data.student.email}</p>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Ngày tham gia:</label>
                                    <span>${formatDateTime(data.student.enrolled_at)}</span>
                                </div>
                                <div class="info-item">
                                    <label>Tiến độ học tập:</label>
                                    <span>${data.student.completion_rate || 0}%</span>
                                </div>
                                <div class="info-item">
                                    <label>Điểm trung bình:</label>
                                    <span>${data.student.avg_score || 'N/A'}</span>
                                </div>
                            </div>
                        </div>

                        <div class="progress-section">
                            <h5>Tiến độ chi tiết</h5>
                            <div class="progress-list">
                                ${data.progress.map(item => {
                                    let statusText = '';
                                    let statusClass = '';
                                    let itemPrefix = ''; // ✅ Thêm biến tiền tố
                                    
                                    if (item.type === 'assignment') {
                                        itemPrefix = 'Nhiệm vụ:'; // ✅ Tiền tố cho bài tập
                                        
                                        const deadline = item.deadline ? new Date(item.deadline) : null;
                                        
                                        if (item.score !== null && item.score !== undefined) {
                                            statusText = 'Đã hoàn thành';
                                            statusClass = 'completed';
                                        } else if (item.submitted_at) {
                                            statusText = 'Đang chấm';
                                            statusClass = 'in_progress';
                                        } else if (deadline && deadline < now) {
                                            statusText = 'Quá hạn';
                                            statusClass = 'overdue';
                                        } else {
                                            statusText = 'Chưa nộp';
                                            statusClass = 'not_started';
                                        }
                                    } else {
                                        itemPrefix = 'Lý thuyết:'; // ✅ Tiền tố cho bài học
                                        
                                        if (item.status === 'completed') {
                                            statusText = 'Đã hoàn thành';
                                            statusClass = 'completed';
                                        } else if (item.status === 'in_progress') {
                                            statusText = 'Đang học';
                                            statusClass = 'in_progress';
                                        } else {
                                            statusText = 'Chưa bắt đầu';
                                            statusClass = 'not_started';
                                        }
                                    }
                                    
                                    return `
                                        <div class="progress-item">
                                            <div class="progress-header">
                                                <span class="title">${itemPrefix} ${item.title}</span>
                                                <span class="status ${statusClass}">${statusText}</span>
                                            </div>
                                            ${item.score !== null && item.score !== undefined ? `
                                                <div class="score-info">
                                                    <i class="fas fa-star"></i>
                                                    <span>${item.score}/${item.max_score}</span>
                                                </div>
                                            ` : ''}
                                            ${item.type === 'assignment' && item.deadline ? `
                                                <div style="margin-top: 8px; font-size: 12px; color: ${statusClass === 'overdue' ? '#D32F2F' : '#666'};">
                                                    <i class="fas fa-clock"></i> 
                                                    Hạn nộp: ${formatDateTime(item.deadline)}
                                                    ${statusClass === 'overdue' ? ' <span style="color: #D32F2F; font-weight: 600;">(Đã quá hạn)</span>' : ''}
                                                </div>
                                            ` : ''}
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>

                        ${data.submissions && data.submissions.length > 0 ? `
                            <div class="submissions-section">
                                <h5>Bài nộp gần đây</h5>
                                <div class="submissions-list">
                                    ${data.submissions.map(sub => `
                                        <div class="submission-item">
                                            <div class="submission-header">
                                                <span class="title">${sub.assignment_title}</span>
                                                <span class="date">${formatDateTime(sub.submitted_at)}</span>
                                            </div>
                                            <div class="submission-status">
                                                <span class="status ${sub.status}">
                                                    ${sub.status === 'pending' ? 'Chờ chấm' : 'Đã chấm'}
                                                </span>
                                                ${sub.score !== null && sub.score !== undefined ? `
                                                    <span class="score">
                                                        <i class="fas fa-star"></i> ${sub.score}/${sub.max_score}
                                                    </span>
                                                ` : ''}
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                `,
                width: 800,
                showCloseButton: true,
                showConfirmButton: false,
                customClass: {
                    container: 'student-detail-container'
                }
            });
        } else {
            showError(data.message || 'Không thể tải thông tin học sinh');
        }
    } catch (error) {
        console.error('Error viewing student detail:', error);
        showError('Có lỗi xảy ra khi tải thông tin học sinh');
    }
}

// Show section
function showSection(section) {
    // Update menu items
    document.querySelectorAll(".menu-item").forEach((item) => {
        item.classList.remove("active");
    });
    document.querySelector(`[data-section="${section}"]`)?.classList.add("active");

    // Hide all sections
    document.querySelectorAll(".content-section").forEach((sec) => {
        sec.classList.remove("active");
    });

    // Show selected section
    const sectionElement = document.getElementById(`${section}-section`);
    if (sectionElement) {
        sectionElement.classList.add("active");
    }

    currentSection = section;

    // Load data based on section
    switch (section) {
        case "dashboard":
            loadDashboardData();
            break;
        case "classes":
            loadClasses();
            break;
        case "assignments":
            loadAssignments();
            break;
        case "academic-products":
            loadStudentProducts();
            break;
        case "statistics":
            loadStatisticsFilters();
            break;
        case "students":
            loadStudentsFilters();
            break;
    }
}

// Load dashboard data
async function loadDashboardData() {
  try {
    const response = await fetch("api/get_dashboard_stats.php");

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const contentType = response.headers.get("content-type");
    if (!contentType || !contentType.includes("application/json")) {
      throw new TypeError("Received non-JSON response from server");
    }

    const data = await response.json();

    if (data.success) {
      // Update stats with fallback to 0
      document.getElementById("total-classes").textContent =
        data.stats.total_classes || 0;
      document.getElementById("total-students").textContent =
        data.stats.total_students || 0;
      document.getElementById("pending-submissions").textContent =
        data.stats.pending_submissions || 0;
      document.getElementById("total-lessons").textContent =
        data.stats.total_lessons || 0;

      // ✅ Load other dashboard components - Đảm bảo gọi với page = 1
      await Promise.all([
        loadRecentActivities(1), 
        loadUpcomingDeadlines(1)
      ]);
    } else {
      console.error("Dashboard data fetch failed:", data.message);
      showError("Không thể tải dữ liệu bảng điều khiển");
    }
  } catch (error) {
    console.error("Error loading dashboard:", error);
    showError("Không thể kết nối với máy chủ");
  }
}

// Load upcoming deadlines
async function loadUpcomingDeadlines() {
  try {
    const response = await fetch("api/get_upcoming_deadlines.php");
    const data = await response.json();

    const deadlinesList = document.getElementById("upcoming-deadlines-list");

    if (data.success && data.deadlines.length > 0) {
      deadlinesList.innerHTML = data.deadlines
        .map(
          (deadline) => `
                <div class="deadline-item">
                    <div class="deadline-info">
                        <h4>${deadline.title}</h4>
                        <p class="deadline-class">${deadline.class_name}</p>
                    </div>
                    <div class="deadline-time ${getDeadlineUrgency(
                      deadline.deadline
                    )}">
                        <i class="fas fa-clock"></i>
                        ${formatDeadline(deadline.deadline)}
                    </div>
                </div>
            `
        )
        .join("");
    } else {
      deadlinesList.innerHTML =
        '<p class="empty-state">Không có hạn chót sắp tới</p>';
    }
  } catch (error) {
    console.error("Error loading deadlines:", error);
  }
}

// Load classes
async function loadClasses() {
  try {
    const response = await fetch("api/get_teacher_classes.php");

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success) {
      allClasses = data.classes;
      displayClasses(data.classes);
    } else {
      console.error("Classes fetch failed:", data.message);
      showError("Không thể tải danh sách lớp học");
    }
  } catch (error) {
    console.error("Error loading classes:", error);
    showError("Không thể kết nối với máy chủ");
  }
}

async function displayClasses(classes) {
  const classesContainer = document.getElementById("classes-list");
  if (!classesContainer) {
    console.error("Classes container not found");
    return;
  }

  if (!classes || classes.length === 0) {
    classesContainer.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-book"></i>
                <p>Chưa có lớp học nào được tạo</p>
                <button onclick="showCreateClassModal()" class="btn-primary">
                    <i class="fas fa-plus"></i> Tạo lớp học mới
                </button>
            </div>
        `;
    return;
  }

  try {
    const updatedClasses = await Promise.all(
      classes.map(async (classItem) => {
        try {
          const response = await fetch(
            `api/get_class_detail.php?id=${classItem.id}`
          );
          const data = await response.json();
          
          console.log(`Class ${classItem.id} detail:`, data); // Debug log
          
          if (data.success) {
            return {
              ...classItem,
              student_count: data.class.student_count || 0,
            };
          }
          return classItem;
        } catch (error) {
          console.error(
            `Error fetching details for class ${classItem.id}:`,
            error
          );
          return classItem;
        }
      })
    );

    classesContainer.innerHTML = updatedClasses
      .map(
        (classItem) => `
            <div class="class-card" data-id="${classItem.id}">
                <div class="class-header">
                    <h3>${classItem.class_name}</h3>
                    <span class="class-code">${
                      classItem.code || "No code"
                    }</span>
                </div>
                <p class="class-description">Mô tả: ${
                  classItem.description || "Không có mô tả"
                }</p>
                <div class="class-stats">
                    <div class="stat">
                        <i class="fas fa-user-graduate"></i>
                        <span>${classItem.student_count || 0} học viên</span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-book-open"></i>
                        <span>${classItem.chapter_count || 0} chương</span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-tasks"></i>
                        <span>${classItem.assignment_count || 0} bài tập</span>
                    </div>
                </div>
                <div class="class-footer">
                    <span class="created-date">
                        Tạo ngày: ${new Date(
                          classItem.created_at
                        ).toLocaleDateString("vi-VN")}
                    </span>
                    <div class="action-buttons">
                        <button onclick="viewClass(${
                          classItem.id
                        })" class="btn-secondary">
                            <i class="fas fa-eye"></i> Xem chi tiết
                        </button>
                        <button onclick="editClass(${
                          classItem.id
                        })" class="btn-secondary" title="Chỉnh sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteClass(${
                          classItem.id
                        })" class="btn-danger">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `
      )
      .join("");
  } catch (error) {
    console.error("Error displaying classes:", error);
    showError("Không thể hiển thị danh sách lớp học");
  }
}

// View class detail
async function viewClass(classId) {
  try {
    const response = await fetch(`api/get_class_detail.php?id=${classId}`);
    const data = await response.json();

    if (data.success) {
      currentClassId = classId;
      document.querySelectorAll(".content-section").forEach((section) => {
        section.classList.remove("active");
      });

      const detailSection = document.getElementById("class-detail-section");
      detailSection.classList.add("active");

      // Build class detail view
      detailSection.querySelector(".class-detail-view").innerHTML = `
                <div class="section-header">
                    <div class="header-left">
                        <button class="btn btn-outline" onclick="showSection('classes')">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </button>
                        <h2>${data.class.class_name}</h2>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="showClassStudents(${classId})">
                            <i class="fas fa-users"></i> Danh sách học sinh
                        </button>
                        <button class="btn btn-primary" onclick="openAddChapterModal(${classId})">
                            <i class="fas fa-plus"></i> Thêm chương
                        </button>
                    </div>
                </div>
                
                <div class="class-info-grid">
                    <div class="info-card">
                        <div class="card-header">
                            <h3><i class="fas fa-info-circle"></i> Thông tin lớp học</h3>
                        </div>
                        <div class="card-content">
                            <p><strong>Mã lớp:</strong> ${
                              data.class.code || "Chưa có"
                            }</p>
                            <p><strong>Số học sinh:</strong> ${
                              data.class.student_count || 0
                            }</p>
                            <p><strong>Số chương:</strong> ${
                              data.class.chapter_count || 0
                            }</p>
                            <p><strong>Ngày tạo:</strong> ${new Date(
                              data.class.created_at
                            ).toLocaleDateString("vi-VN")}</p>
                            <p><strong>Mô tả:</strong> ${
                              data.class.description || "Không có mô tả"
                            }</p>
                        </div>
                    </div>
                    
                    <div class="chapters-container">
                        ${renderChapters(data.chapters)}
                    </div>
                </div>
            `;
    } else {
      showError("Không thể tải thông tin lớp học");
    }
  } catch (error) {
    console.error("Error viewing class:", error);
    showError("Đã xảy ra lỗi khi tải thông tin lớp học");
  }
}

function renderChapters(chapters) {
  if (!chapters || chapters.length === 0) {
    return `
            <div class="empty-state">
                <i class="fas fa-book"></i>
                <p>Chưa có chương nào được tạo</p>
                <button class="btn btn-primary" onclick="openAddChapterModal(${currentClassId})">
                    <i class="fas fa-plus"></i> Thêm chương đầu tiên
                </button>
            </div>
        `;
  }

  return `
        <div class="chapters-list">
            ${chapters
              .map(
                (chapter, index) => `
                <div class="chapter-card">
                    <div class="chapter-header">
                        <h4>Chương ${chapter.order_index}: ${chapter.title}</h4>
                        <div class="chapter-actions">
                            <button class="btn btn-outline" onclick="openAddLessonModal(${
                              chapter.id
                            })">
                                <i class="fas fa-plus"></i> Thêm bài học
                            </button>
                            <button class="btn-icon" onclick="editChapter(${
                              chapter.id
                            })" title="Sửa">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon" onclick="deleteChapter(${
                              chapter.id
                            })" title="Xóa">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="chapter-content">
                        ${renderLessons(chapter.lessons)}
                    </div>
                </div>
            `
              )
              .join("")}
        </div>
    `;
}

function renderLessons(lessons) {
  if (!lessons || lessons.length === 0) {
    return '<p class="no-lessons">Chưa có bài học nào trong chương này</p>';
  }

  return `
        <div class="lessons-list">
            ${lessons
              .map(
                (lesson) => `
                <div class="lesson-item">
                    <i class="fas ${getLessonIcon(lesson.type)}"></i>
                    <span>${lesson.title}</span>
                    <div class="lesson-actions">
                        <button class="btn btn-icon" onclick="viewLesson(${
                          lesson.id
                        })">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-icon" onclick="editLesson(${
                          lesson.id
                        })" title="Sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-icon" onclick="deleteLesson(${
                          lesson.id
                        })" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `
              )
              .join("")}
        </div>
    `;
}

// ==================== ACADEMIC PRODUCTS (TEACHER VIEW) ====================

async function loadStudentProducts(filter = 'all') {
    try {
        const classId = document.getElementById('products-class-filter')?.value || '';
        const status = document.getElementById('products-status-filter')?.value || '';
        const type = document.getElementById('products-type-filter')?.value || '';
        
        let url = `api/get_student_products.php?filter=${filter}`;
        if (classId) url += `&class_id=${classId}`;
        if (status) url += `&status=${status}`;
        if (type) url += `&type=${type}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        const container = document.getElementById('teacher-products-list');
        
        if (!data.success || !data.products || data.products.length === 0) {
            container.innerHTML = '<p class="empty-state">Chưa có sản phẩm nào</p>';
            return;
        }
        
        container.innerHTML = data.products.map(product => `
            <div class="product-card teacher-view" data-product-id="${product.id}">
                <div class="product-card-header">
                    <div class="product-icon ${product.type}">
                        <i class="fas ${getProductIcon(product.type)}"></i>
                    </div>
                    <span class="product-status-badge ${product.status}">
                        ${getStatusText(product.status)}
                    </span>
                </div>
                
                <div class="product-card-body">
                    <h3 class="product-title">${product.title || 'Chưa có tiêu đề'}</h3>
                    
                    <div class="product-student-info">
                        <i class="fas fa-user-graduate"></i>
                        <strong>${product.student_name}</strong>
                        <span class="student-email">${product.student_email}</span>
                    </div>
                    
                    <p class="product-class">
                        <i class="fas fa-book"></i> ${product.class_name}
                        ${product.class_code ? `<span class="class-code">(${product.class_code})</span>` : ''}
                    </p>
                    
                    <div class="product-meta">
                        <span class="product-meta-item">
                            <i class="fas fa-calendar"></i>
                            Nộp: ${formatDate(product.submitted_at || product.created_at)}
                        </span>
                        ${product.word_count ? `
                            <span class="product-meta-item">
                                <i class="fas fa-file-word"></i>
                                ${product.word_count} từ
                            </span>
                        ` : ''}
                    </div>
                    
                    ${product.score ? `
                        <div class="product-score">
                            <i class="fas fa-star"></i>
                            <strong>${product.score}/10</strong>
                        </div>
                    ` : ''}
                </div>
                
                <div class="product-card-footer">
                    <button class="btn-product-action" onclick="reviewStudentProduct(${product.id})">
                        <i class="fas fa-eye"></i> Xem và chấm điểm
                    </button>
                    ${product.status === 'submitted' ? `
                        <button class="btn-product-action primary" onclick="reviewStudentProduct(${product.id})">
                            <i class="fas fa-edit"></i> Chấm điểm
                        </button>
                    ` : ''}
                </div>
            </div>
        `).join('');
        
        // Populate class filter
        await populateProductsClassFilter();
        
    } catch (error) {
        console.error('Error loading student products:', error);
        document.getElementById('teacher-products-list').innerHTML = 
            '<p class="empty-state">Không thể tải sản phẩm</p>';
    }
}

async function populateProductsClassFilter() {
    try {
        // Always reload classes to ensure fresh data
        const response = await fetch('api/get_teacher_classes.php');
        const data = await response.json();
        
        if (data.success && data.classes) {
            allClasses = data.classes;
            
            const classFilter = document.getElementById('products-class-filter');
            if (classFilter) {
                classFilter.innerHTML = 
                    '<option value="">Tất cả lớp học</option>' +
                    allClasses.map(cls => 
                        `<option value="${cls.id}">${cls.class_name || cls.name || 'Không có tên'}</option>`
                    ).join('');
            }
        } else {
            console.error('Failed to load classes:', data.message);
        }
    } catch (error) {
        console.error('Error loading classes for filter:', error);
    }
}

async function reviewStudentProduct(productId) {
    try {
        // KIỂM TRA QUYỀN TRUY CẬP CỦA GIÁO VIÊN
        const response = await fetch(`api/get_product_detail.php?id=${productId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Không thể tải sản phẩm');
        }
        
        const product = data.product;
        
        // Check if shared
        if (!product.share_token || product.share_token === '') {
            Swal.fire({
                icon: 'warning',
                title: 'Chưa được chia sẻ',
                text: 'Học sinh chưa chia sẻ sản phẩm này.',
                confirmButtonColor: '#2E7D32'
            });
            return;
        }
        
        // SET FLAG GIÁO VIÊN ĐANG XEM
        window.isTeacherReviewing = true;
        window.teacherReviewData = {
            score: product.score || '',
            feedback: product.feedback || '',
            status: product.status || 'submitted'
        };
        
        // MỞ EDITOR CHUNG
        await openProductEditor(productId);
        
    } catch (error) {
        console.error('Error reviewing product:', error);
        Swal.fire({
            icon: 'error',
            title: 'Lỗi',
            text: 'Không thể tải sản phẩm: ' + error.message,
            confirmButtonColor: '#2E7D32'
        });
    }
}

function openTeacherReviewEditor(product) {
    // Create teacher review editor modal if not exists
    if (!document.getElementById('teacher-review-editor')) {
        createTeacherReviewEditor();
    }
    
    // Populate editor with product data
    document.getElementById('teacher-review-title').textContent = product.title || 'Không có tiêu đề';
    document.getElementById('teacher-review-student-name').textContent = product.student_name || 'Học sinh';
    document.getElementById('teacher-review-student-email').textContent = product.student_email || '';
    document.getElementById('teacher-review-class').textContent = `${product.class_name || 'Lớp'} ${product.class_code ? '(' + product.class_code + ')' : ''}`;
    document.getElementById('teacher-review-submitted-date').textContent = product.submitted_at ? formatDateTime(product.submitted_at) : 'Chưa nộp';
    
    // Set content (READ-ONLY for teacher)
    const contentViewer = document.getElementById('teacher-review-content');
    contentViewer.innerHTML = product.content || '<p style="color: #999; text-align: center; padding: 40px;">Chưa có nội dung</p>';
    
    // Update word count
    const textContent = contentViewer.innerText || '';
    const words = textContent.trim().split(/\s+/).filter(w => w.length > 0).length;
    const chars = textContent.length;
    document.getElementById('teacher-review-word-count').textContent = `${words} từ`;
    document.getElementById('teacher-review-char-count').textContent = `${chars} ký tự`;
    
    // Set existing score and feedback if any
    document.getElementById('teacher-score-input').value = product.score || '';
    document.getElementById('teacher-feedback-input').value = product.feedback || '';
    document.getElementById('teacher-status-select').value = product.status || 'submitted';
    
    // Store current product
    window.currentReviewProduct = product;
    
    // Show modal
    document.getElementById('teacher-review-editor').classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Load comments if available
    loadTeacherComments(product.id);
}

function createTeacherReviewEditor() {
    const editorHTML = `
        <div id="teacher-review-editor" class="modal teacher-editor-modal">
            <div class="modal-content teacher-editor-container">
                <!-- Editor Header -->
                <div class="editor-header teacher-header">
                    <div class="editor-header-left">
                        <button class="btn-back" onclick="closeTeacherReviewEditor()">
                            <i class="fas fa-arrow-left"></i>
                            <span>Quay lại</span>
                        </button>
                        <div class="product-info-compact">
                            <h3 id="teacher-review-title">Tiêu đề sản phẩm</h3>
                            <div class="student-info-compact">
                                <i class="fas fa-user-graduate"></i>
                                <span id="teacher-review-student-name">Học sinh</span>
                                <span class="separator">|</span>
                                <span id="teacher-review-student-email">email</span>
                            </div>
                        </div>
                    </div>
                    <div class="editor-header-right">
                        <button class="btn-editor-action" onclick="highlightText()" title="Đánh dấu văn bản">
                            <i class="fas fa-highlighter"></i>
                        </button>
                        <button class="btn-editor-action" onclick="addTeacherComment()" title="Thêm nhận xét">
                            <i class="fas fa-comment-medical"></i>
                        </button>
                        <button class="btn-primary" onclick="saveTeacherReview()">
                            <i class="fas fa-save"></i> Lưu đánh giá
                        </button>
                    </div>
                </div>

                <!-- Product Meta Info Bar -->
                <div class="product-meta-bar">
                    <div class="meta-item">
                        <i class="fas fa-book"></i>
                        <span id="teacher-review-class">Lớp học</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <span id="teacher-review-submitted-date">Ngày nộp</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-file-word"></i>
                        <span id="teacher-review-word-count">0 từ</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-font"></i>
                        <span id="teacher-review-char-count">0 ký tự</span>
                    </div>
                </div>

                <!-- Editor Body -->
                <div class="editor-body teacher-editor-body">
                    <!-- Content Viewer (Left Side) -->
                    <div class="content-viewer-panel">
                        <div class="viewer-header">
                            <h4><i class="fas fa-file-alt"></i> Nội dung sản phẩm</h4>
                            <div class="viewer-actions">
                                <button class="btn-icon" onclick="printProduct()" title="In">
                                    <i class="fas fa-print"></i>
                                </button>
                                <button class="btn-icon" onclick="exportProductPDF()" title="Xuất PDF">
                                    <i class="fas fa-file-pdf"></i>
                                </button>
                                <button class="btn-icon" onclick="toggleFullscreen()" title="Toàn màn hình">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="content-viewer-wrapper">
                            <div class="content-viewer" id="teacher-review-content">
                                <p>Đang tải nội dung...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Review Panel (Right Side) -->
                    <div class="review-panel">
                        <div class="review-tabs">
                            <button class="review-tab active" data-tab="scoring" onclick="switchReviewTab('scoring')">
                                <i class="fas fa-star"></i>
                                <span>Chấm điểm</span>
                            </button>
                            <button class="review-tab" data-tab="comments" onclick="switchReviewTab('comments')">
                                <i class="fas fa-comments"></i>
                                <span>Nhận xét</span>
                                <span class="badge" id="teacher-comments-count">0</span>
                            </button>
                            <button class="review-tab" data-tab="history" onclick="switchReviewTab('history')">
                                <i class="fas fa-history"></i>
                                <span>Lịch sử</span>
                            </button>
                        </div>

                        <!-- Scoring Tab -->
                        <div class="review-tab-content active" data-content="scoring">
                            <div class="scoring-form">
                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-star"></i>
                                        Điểm số (0-10) *
                                    </label>
                                    <div class="score-input-wrapper">
                                        <input type="number" 
                                               id="teacher-score-input" 
                                               class="score-input" 
                                               min="0" 
                                               max="10" 
                                               step="0.5" 
                                               placeholder="0.0">
                                        <span class="score-suffix">/10</span>
                                    </div>
                                    <div class="score-preset">
                                        <button class="score-btn" onclick="setScore(10)">10</button>
                                        <button class="score-btn" onclick="setScore(9)">9</button>
                                        <button class="score-btn" onclick="setScore(8)">8</button>
                                        <button class="score-btn" onclick="setScore(7)">7</button>
                                        <button class="score-btn" onclick="setScore(6)">6</button>
                                        <button class="score-btn" onclick="setScore(5)">5</button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-comment-alt"></i>
                                        Nhận xét chi tiết *
                                    </label>
                                    <textarea id="teacher-feedback-input" 
                                              class="feedback-textarea" 
                                              rows="12"
                                              placeholder="Nhập nhận xét chi tiết cho học sinh...&#10;&#10;Ví dụ:&#10;- Điểm mạnh của bài&#10;- Điểm cần cải thiện&#10;- Đề xuất phát triển"></textarea>
                                    <div class="char-counter">
                                        <span id="feedback-char-count">0</span> ký tự
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-flag"></i>
                                        Trạng thái đánh giá
                                    </label>
                                    <select id="teacher-status-select" class="status-select">
                                        <option value="reviewed">✓ Đã chấm - Hoàn thành</option>
                                        <option value="returned">↺ Trả lại - Cần sửa lại</option>
                                    </select>
                                </div>

                                <div class="rubric-quick-select">
                                    <h5><i class="fas fa-clipboard-list"></i> Tiêu chí đánh giá nhanh</h5>
                                    <div class="rubric-items">
                                        <button class="rubric-item" onclick="insertFeedback('Nội dung đầy đủ, logic rõ ràng.')">
                                            <i class="fas fa-check"></i> Nội dung tốt
                                        </button>
                                        <button class="rubric-item" onclick="insertFeedback('Cần bổ sung thêm dẫn chứng và ví dụ cụ thể.')">
                                            <i class="fas fa-plus"></i> Cần bổ sung
                                        </button>
                                        <button class="rubric-item" onclick="insertFeedback('Cấu trúc bài viết chưa rõ ràng.')">
                                            <i class="fas fa-sitemap"></i> Cải thiện cấu trúc
                                        </button>
                                        <button class="rubric-item" onclick="insertFeedback('Cần chú ý lỗi chính tả và ngữ pháp.')">
                                            <i class="fas fa-spell-check"></i> Sửa lỗi
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Comments Tab -->
                        <div class="review-tab-content" data-content="comments">
                            <div class="comments-section">
                                <div class="comments-header">
                                    <h5>Nhận xét của giáo viên</h5>
                                    <button class="btn-add-comment" onclick="addTeacherComment()">
                                        <i class="fas fa-plus"></i> Thêm
                                    </button>
                                </div>
                                <div id="teacher-comments-list" class="comments-list">
                                    <p class="empty-state-small">Chưa có nhận xét nào</p>
                                </div>
                            </div>
                        </div>

                        <!-- History Tab -->
                        <div class="review-tab-content" data-content="history">
                            <div class="history-section">
                                <h5>Lịch sử chỉnh sửa</h5>
                                <div id="product-history-list" class="history-list">
                                    <p class="empty-state-small">Đang tải...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Bar (Bottom) -->
                <div class="editor-footer teacher-footer">
                    <div class="footer-left">
                        <button class="btn-secondary" onclick="closeTeacherReviewEditor()">
                            <i class="fas fa-times"></i> Hủy
                        </button>
                    </div>
                    <div class="footer-right">
                        <button class="btn-secondary" onclick="saveAsDraft()">
                            <i class="fas fa-save"></i> Lưu nháp
                        </button>
                        <button class="btn-primary" onclick="saveTeacherReview()">
                            <i class="fas fa-paper-plane"></i> Lưu và gửi học sinh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', editorHTML);
    
    // Setup character counter
    const feedbackInput = document.getElementById('teacher-feedback-input');
    if (feedbackInput) {
        feedbackInput.addEventListener('input', () => {
            const count = feedbackInput.value.length;
            document.getElementById('feedback-char-count').textContent = count;
        });
    }
}

function closeTeacherReviewEditor() {
    const modal = document.getElementById('teacher-review-editor');
    if (modal) {
        modal.classList.remove('active');
    }
    document.body.style.overflow = '';
    window.currentReviewProduct = null;
}

function switchReviewTab(tab) {
    // Update active tab
    document.querySelectorAll('.review-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    
    // Update active content
    document.querySelectorAll('.review-tab-content').forEach(c => c.classList.remove('active'));
    document.querySelector(`[data-content="${tab}"]`).classList.add('active');
    
    // Load content if needed
    if (tab === 'history' && window.currentReviewProduct) {
        loadProductVersionHistory(window.currentReviewProduct.id);
    }
}

function setScore(score) {
    document.getElementById('teacher-score-input').value = score;
}

function insertFeedback(text) {
    const feedbackInput = document.getElementById('teacher-feedback-input');
    const currentValue = feedbackInput.value;
    feedbackInput.value = currentValue + (currentValue ? '\n' : '') + text;
    feedbackInput.dispatchEvent(new Event('input'));
}

async function saveTeacherReview() {
    if (!window.currentReviewProduct) return;
    
    const score = document.getElementById('teacher-score-input').value;
    const feedback = document.getElementById('teacher-feedback-input').value.trim();
    const status = document.getElementById('teacher-status-select').value;
    
    // Validate
    if (!score || score === '') {
        Swal.fire({
            icon: 'warning',
            title: 'Thiếu thông tin',
            text: 'Vui lòng nhập điểm số',
            confirmButtonColor: '#2E7D32'
        });
        return;
    }
    
    const numScore = parseFloat(score);
    if (isNaN(numScore) || numScore < 0 || numScore > 10) {
        Swal.fire({
            icon: 'warning',
            title: 'Điểm không hợp lệ',
            text: 'Điểm phải từ 0 đến 10',
            confirmButtonColor: '#2E7D32'
        });
        return;
    }
    
    if (!feedback) {
        Swal.fire({
            icon: 'warning',
            title: 'Thiếu nhận xét',
            text: 'Vui lòng nhập nhận xét cho học sinh',
            confirmButtonColor: '#2E7D32'
        });
        return;
    }
    
    // Confirm before saving
    const result = await Swal.fire({
        title: 'Xác nhận lưu đánh giá',
        html: `
            <div style="text-align: left; padding: 16px;">
                <p style="margin-bottom: 12px;"><strong>Điểm:</strong> ${numScore}/10</p>
                <p style="margin-bottom: 12px;"><strong>Trạng thái:</strong> ${status === 'reviewed' ? 'Đã chấm' : 'Trả lại'}</p>
                <p style="color: #666; font-size: 14px; margin-top: 16px;">
                    Học sinh sẽ nhận được thông báo về đánh giá này.
                </p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-paper-plane"></i> Lưu và gửi',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#2E7D32'
    });
    
    if (!result.isConfirmed) return;
    
    await saveProductReview(window.currentReviewProduct.id, {
        score: numScore,
        feedback: feedback,
        status: status
    });
}

async function loadTeacherComments(productId) {
    // Implement loading teacher comments/feedback history
    // Similar to loadComments but filter by teacher role
}

async function addTeacherComment() {
    if (!window.currentReviewProduct) return;
    
    const selection = window.getSelection();
    const selectedText = selection.toString().trim();
    
    const { value: commentText } = await Swal.fire({
        title: 'Thêm nhận xét',
        html: `
            ${selectedText ? `
                <div class="selected-text-preview" style="background: #fff3cd; padding: 12px; border-radius: 8px; margin-bottom: 16px; border-left: 4px solid #ffc107;">
                    <i class="fas fa-quote-left"></i>
                    <em>"${selectedText}"</em>
                </div>
            ` : ''}
            <textarea id="comment-input" class="swal2-textarea" rows="6" placeholder="Nhập nhận xét..."></textarea>
        `,
        showCancelButton: true,
        confirmButtonText: 'Thêm',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#2E7D32',
        preConfirm: () => {
            const input = document.getElementById('comment-input');
            if (!input.value.trim()) {
                Swal.showValidationMessage('Vui lòng nhập nội dung');
                return false;
            }
            return input.value.trim();
        }
    });
    
    if (commentText) {
        // Save comment via API
        try {
            const response = await fetch('api/product-comments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add',
                    product_id: window.currentReviewProduct.id,
                    content: commentText,
                    selected_text: selectedText || null
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Đã thêm!',
                    timer: 1000,
                    showConfirmButton: false
                });
                loadTeacherComments(window.currentReviewProduct.id);
            }
        } catch (error) {
            console.error('Error adding comment:', error);
        }
    }
}

function toggleFullscreen() {
    const viewer = document.querySelector('.content-viewer-wrapper');
    if (!document.fullscreenElement) {
        viewer.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

function printProduct() {
    const content = document.getElementById('teacher-review-content').innerHTML;
    const printWindow = window.open('', '', 'height=800,width=1000');
    printWindow.document.write('<html><head><title>In sản phẩm</title>');
    printWindow.document.write('<style>body{font-family: Arial; padding: 40px;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

async function exportProductPDF() {
    // Implement PDF export
    Swal.fire({
        icon: 'info',
        title: 'Chức năng đang phát triển',
        text: 'Tính năng xuất PDF sẽ sớm được cập nhật',
        confirmButtonColor: '#2E7D32'
    });
}

async function saveProductReview(productId, reviewData) {
    try {
        Swal.fire({
            title: 'Đang lưu...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        
        const response = await fetch('../saudn/api/review-product.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: productId,
                score: parseFloat(reviewData.score),
                feedback: reviewData.feedback,
                status: reviewData.status
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await Swal.fire({
                icon: 'success',
                title: 'Thành công!',
                text: 'Đã lưu đánh giá và gửi thông báo cho học sinh',
                confirmButtonColor: '#2E7D32'
            });
            
            // Reload products list
            loadStudentProducts();
            
        } else {
            throw new Error(data.message);
        }
        
    } catch (error) {
        console.error('Error saving review:', error);
        Swal.fire({
            icon: 'error',
            title: 'Lỗi',
            text: 'Không thể lưu đánh giá: ' + error.message,
            confirmButtonColor: '#2E7D32'
        });
    }
}

// Helper functions
function getProductIcon(type) {
    const icons = {
        'essay': 'fa-file-alt',
        'report': 'fa-file-pdf',
        'research': 'fa-flask',
        'presentation': 'fa-presentation',
        'other': 'fa-file'
    };
    return icons[type] || 'fa-file';
}

function getStatusText(status) {
    const texts = {
        'draft': 'Bản nháp',
        'submitted': 'Đã nộp',
        'reviewed': 'Đã chấm',
        'returned': 'Trả lại'
    };
    return texts[status] || status;
}

// Setup event listeners - Thêm vào setupEventListeners()
const productsClassFilter = document.getElementById('products-class-filter');
const productsStatusFilter = document.getElementById('products-status-filter');
const productsTypeFilter = document.getElementById('products-type-filter');

if (productsClassFilter) {
    productsClassFilter.addEventListener('change', () => loadStudentProducts());
}

if (productsStatusFilter) {
    productsStatusFilter.addEventListener('change', () => loadStudentProducts());
}

if (productsTypeFilter) {
    productsTypeFilter.addEventListener('change', () => loadStudentProducts());
}

// Filter tabs for products
document.querySelectorAll('#academic-products-section .filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('#academic-products-section .filter-tab')
            .forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const filter = this.getAttribute('data-filter');
        loadStudentProducts(filter);
    });
});

function getLessonIcon(type) {
    // Normalize type
    const normalizedType = (type || 'theory').toLowerCase().trim();
    
    const icons = {
        'theory': 'fas fa-book',
        'practice': 'fas fa-pencil-alt',
        'test': 'fas fa-clipboard-check',
        'quiz': 'fas fa-question-circle',
        'exercise': 'fas fa-dumbbell',
        'video': 'fas fa-video',
        'document': 'fas fa-file-alt'
    };
    
    return icons[normalizedType] || 'fas fa-file';
}

function getLessonTypeName(type) {
    // Normalize type
    const normalizedType = (type || 'theory').toLowerCase().trim();
    
    const names = {
        'theory': 'Lý thuyết',
        'practice': 'Thực hành',
        'test': 'Kiểm tra',
        'quiz': 'Bài kiểm tra',
        'exercise': 'Bài tập',
        'video': 'Video',
        'document': 'Tài liệu'
    };
    
    return names[normalizedType] || type || 'Khác';
}

// Load assignments
async function loadAssignments() {
  try {
    const response = await fetch("api/get_teacher_assignments.php");
    const data = await response.json();

    if (data.success) {
      allAssignments = data.assignments;
      displayAssignments(allAssignments);

      // Populate assignment class dropdown
      const assignmentClassSelect = document.getElementById("assignment-class");
      if (assignmentClassSelect && allClasses.length === 0) {
        const classesResponse = await fetch("api/get_teacher_classes.php");
        const classesData = await classesResponse.json();
        if (classesData.success) {
          allClasses = classesData.classes;
        }
      }

      if (assignmentClassSelect) {
        assignmentClassSelect.innerHTML =
          '<option value="">Chọn lớp học</option>' +
          allClasses
            .map((cls) => `<option value="${cls.id}">${cls.class_name || cls.name || 'Không có tên'}</option>`)
            .join("");
      }
    }
  } catch (error) {
    console.error("Error loading assignments:", error);
  }
}

function getAssignmentStatus(assignment) {
  const now = new Date();
  const deadline = new Date(assignment.deadline);
  const startDate = assignment.start_date
    ? new Date(assignment.start_date)
    : null;

  if (startDate && startDate > now) {
    return "upcoming";
  } else if (deadline < now) {
    return "expired";
  } else if (startDate && startDate <= now && deadline >= now) {
    return "active";
  } else if (!startDate && deadline >= now) {
    return "active";
  }
  return "expired";
}

function getAssignmentStatusText(assignment) {
  const status = getAssignmentStatus(assignment);
  switch (status) {
    case "upcoming":
      return "Sắp diễn ra";
    case "active":
      return "Đang hoạt động";
    case "expired":
      return "Đã hết hạn";
    default:
      return "Không xác định";
  }
}

// Filter assignments
function filterAssignments(filter) {
  let filtered = allAssignments;

  if (filter !== "all") {
    filtered = allAssignments.filter((assignment) => {
      const status = getAssignmentStatus(assignment);
      return status === filter;
    });
  }

  displayAssignments(filtered);
}

async function editAssignment(assignmentId) {
  try {
    const response = await fetch(`api/get_assignment.php?id=${assignmentId}`);
    const data = await response.json();

    if (data.success) {
      const result = await Swal.fire({
        title: "Chỉnh sửa nhiệm vụ",
        html: `
                    <form class="assignment-form">
                        <div class="form-group">
                            <label for="edit-assignment-class">Lớp học *</label>
                            <select id="edit-assignment-class" class="swal2-input" required disabled>
                                <option value="${data.assignment.class_id}">${
          data.assignment.class_name
        }</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit-assignment-title">Tiêu đề *</label>
                            <input type="text" id="edit-assignment-title" class="swal2-input" value="${
                              data.assignment.title
                            }" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-assignment-description">Mô tả</label>
                            <textarea id="edit-assignment-description" class="swal2-textarea" rows="4">${
                              data.assignment.description || ""
                            }</textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit-assignment-start">Ngày bắt đầu *</label>
                                <input type="datetime-local" id="edit-assignment-start" class="swal2-input" value="${formatDateForInput(
                                  data.assignment.start_date
                                )}" required>
                            </div>
                            <div class="form-group">
                                <label for="edit-assignment-deadline">Hạn nộp *</label>
                                <input type="datetime-local" id="edit-assignment-deadline" class="swal2-input" value="${formatDateForInput(
                                  data.assignment.deadline
                                )}" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit-assignment-max-score">Điểm tối đa</label>
                            <input type="number" id="edit-assignment-max-score" class="swal2-input" value="${
                              data.assignment.max_score || 10
                            }" min="0">
                        </div>
                    </form>
                `,
        showCancelButton: true,
        confirmButtonText: "Lưu thay đổi",
        cancelButtonText: "Hủy",
        confirmButtonColor: "#2E7D32",
        customClass: {
          container: "edit-assignment-modal",
        },
        preConfirm: () => {
          const startDate = document.getElementById(
            "edit-assignment-start"
          ).value;
          const deadline = document.getElementById(
            "edit-assignment-deadline"
          ).value;

          if (new Date(startDate) > new Date(deadline)) {
            Swal.showValidationMessage("Ngày bắt đầu không thể sau hạn nộp");
            return false;
          }

          return {
            assignment_id: assignmentId,
            title: document.getElementById("edit-assignment-title").value,
            description: document.getElementById("edit-assignment-description")
              .value,
            start_date: startDate,
            deadline: deadline,
            max_score: document.getElementById("edit-assignment-max-score")
              .value,
          };
        },
      });

      if (result.isConfirmed) {
        const updateResponse = await fetch("api/update_assignment.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(result.value),
        });

        const updateData = await updateResponse.json();

        if (updateData.success) {
          showSuccess("Cập nhật nhiệm vụ thành công!");
          loadAssignments();
        } else {
          showError(updateData.message || "Không thể cập nhật nhiệm vụ");
        }
      }
    } else {
      showError(data.message || "Không thể tải thông tin nhiệm vụ");
    }
  } catch (error) {
    console.error("Error editing assignment:", error);
    showError("Có lỗi xảy ra khi chỉnh sửa nhiệm vụ");
  }
}

async function viewSubmissions(assignmentId) {
  try {
    const response = await fetch(
      `api/get_assignment_submissions.php?assignment_id=${assignmentId}`
    );
    const data = await response.json();

    if (data.success) {
      Swal.fire({
        title: "Danh sách bài nộp",
        html: `
        <div class="submissions-list-modal">
            <div class="assignment-info">
                <h4>${data.assignment.title}</h4>
                <p class="class-name">${data.assignment.class_name}</p>
                <div class="assignment-meta">
                    <span><i class="fas fa-calendar"></i> Bắt đầu: ${formatDateTime(
                      data.assignment.start_date
                    )}</span>
                    <span><i class="fas fa-clock"></i> Hạn nộp: ${formatDateTime(
                      data.assignment.deadline
                    )}</span>
                    <span><i class="fas fa-users"></i> ${
                      data.submissions ? data.submissions.length : 0
                    } bài nộp</span>
                </div>
            </div>

            ${
              data.submissions && data.submissions.length > 0
                ? `
                <div class="submissions-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Học sinh</th>
                                <th>Thời gian nộp</th>
                                <th>Trạng thái</th>
                                <th>Điểm</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.submissions
                              .map(
                                (submission) => `
                                <tr>
                                    <td>${submission.student_name}</td>
                                    <td>${formatDateTime(
                                      submission.submitted_at
                                    )}</td>
                                    <td>
                                        <span class="status-badge ${
                                          submission.status
                                        }">
                                            ${
                                              submission.status === "pending"
                                                ? "Chờ chấm"
                                                : "Đã chấm"
                                            }
                                        </span>
                                    </td>
                                    <td>${
                                      submission.score !== null
                                        ? submission.score
                                        : "-"
                                    }/${data.assignment.max_score}</td>
                                    <td>
                                        ${
                                          submission.status === "pending"
                                            ? `
                                            <button class="btn btn-primary btn-sm" onclick="openGradingModal(${submission.id})">
                                                <i class="fas fa-edit"></i> Chấm điểm
                                            </button>
                                        `
                                            : `
                                            <button class="btn btn-outline btn-sm" onclick="viewSubmissionDetail(${submission.id})">
                                                <i class="fas fa-eye"></i> Xem chi tiết
                                            </button>
                                        `
                                        }
                                    </td>
                                </tr>
                            `
                              )
                              .join("")}
                        </tbody>
                    </table>
                </div>
            `
                : '<p class="empty-state">Chưa có bài nộp nào</p>'
            }
        </div>
                `,
        width: 900,
        showCloseButton: true,
        showConfirmButton: false,
        customClass: {
          container: "submissions-modal-container",
        },
      });
    } else {
      showError(data.message || "Không thể tải danh sách bài nộp");
    }
  } catch (error) {
    console.error("Error viewing submissions:", error);
    showError("Có lỗi xảy ra khi tải danh sách bài nộp");
  }
}

async function viewSubmissionDetail(submissionId) {
  try {
    const response = await fetch(`api/get_submission_detail.php?id=${submissionId}`);
    const data = await response.json();

    if (data.success) {
      Swal.fire({
        title: "Chi tiết bài nộp",
        html: `
                    <div class="submission-detail">
                        <div class="student-info">
                            <h4>${data.submission.student_name}</h4>
                            <p>${data.submission.class_name}</p>
                        </div>
                        
                        <div class="submission-meta">
                            <span><i class="fas fa-clock"></i> Nộp lúc: ${formatDateTime(
                              data.submission.submitted_at
                            )}</span>
                            <span><i class="fas fa-star"></i> Điểm: ${
                              data.submission.score
                            }/${data.submission.max_score}</span>
                        </div>

                        ${
                          data.submission.submission_text
                            ? `
                            <div class="submission-content">
                                <h5>Nội dung bài nộp:</h5>
                                <p>${data.submission.submission_text}</p>
                            </div>
                        `
                            : ""
                        }

                        ${
                          data.submission.file_path
                            ? `
                            <div class="submission-file">
                                <h5>File đính kèm:</h5>
                                <a href="../uploads/submissions/${data.submission.file_path}" target="_blank" class="btn btn-outline">
                                    <i class="fas fa-file-alt"></i> Xem file
                                </a>
                            </div>
                        `
                            : ""
                        }

                        ${
                          data.submission.feedback
                            ? `
                            <div class="submission-feedback">
                                <h5>Nhận xét:</h5>
                                <p>${data.submission.feedback}</p>
                            </div>
                        `
                            : ""
                        }
                    </div>
                `,
        width: 700,
        showCloseButton: true,
        showConfirmButton: false,
        customClass: {
          container: "submission-detail-modal",
        },
        backdrop: true,
        allowOutsideClick: true,
        didOpen: () => {
          // Get the SweetAlert2 container
          const swalContainer = Swal.getContainer();
          
          if (swalContainer) {
            // Reset any existing styles
            swalContainer.removeAttribute('style');
            
            // Apply full viewport styles with !important
            swalContainer.style.cssText = `
              position: fixed !important;
              top: 0 !important;
              left: 0 !important;
              right: 0 !important;
              bottom: 0 !important;
              width: 100vw !important;
              height: 100vh !important;
              max-width: 100vw !important;
              max-height: 100vh !important;
              margin: 0 !important;
              padding: 0 !important;
              z-index: 99999 !important;
              background-color: rgba(0, 0, 0, 0.75) !important;
              display: flex !important;
              align-items: center !important;
              justify-content: center !important;
              overflow: auto !important;
            `;
          }
          
          // Lock body and html scroll
          const htmlElement = document.documentElement;
          const bodyElement = document.body;
          
          htmlElement.style.overflow = 'hidden';
          bodyElement.style.overflow = 'hidden';
          
          // Store original styles
          htmlElement.dataset.originalOverflow = htmlElement.style.overflow || '';
          bodyElement.dataset.originalOverflow = bodyElement.style.overflow || '';
        },
        willClose: () => {
          // Restore scroll
          const htmlElement = document.documentElement;
          const bodyElement = document.body;
          
          htmlElement.style.overflow = htmlElement.dataset.originalOverflow || '';
          bodyElement.style.overflow = bodyElement.dataset.originalOverflow || '';
          
          // Clean up data attributes
          delete htmlElement.dataset.originalOverflow;
          delete bodyElement.dataset.originalOverflow;
        }
      });
    } else {
      showError(data.message || 'Không thể tải thông tin bài nộp');
    }
  } catch (error) {
    console.error('Error viewing submission detail:', error);
    showError('Có lỗi xảy ra khi tải thông tin bài nộp');
  }
}

// Load submissions
async function loadSubmissions() {
  try {
    const classId = document.getElementById("class-filter").value;
    const status = document.getElementById("status-filter").value;

    let url = "api/get_teacher_submissions.php?";
    if (classId) url += `class_id=${classId}&`;
    if (status) url += `status=${status}`;

    const response = await fetch(url);
    const data = await response.json();

    const submissionsList = document.getElementById("submissions-list");

    if (data.success && data.submissions.length > 0) {
      allSubmissions = data.submissions;
      submissionsList.innerHTML = data.submissions
        .map(
          (submission) => `
                <div class="submission-card">
                    <div class="submission-header">
                        <div class="student-info">
                            <i class="fas fa-user-circle"></i>
                            <div>
                                <h4>${submission.student_name}</h4>
                                <p>${submission.class_name}</p>
                            </div>
                        </div>
                        <span class="submission-badge ${submission.status}">
                            ${
                              submission.status === "pending"
                                ? "Chờ chấm"
                                : "Đã chấm"
                            }
                        </span>
                    </div>
                    <div class="submission-content">
                        <h4>${
                          submission.assignment_title || submission.lesson_title
                        }</h4>
                        <p class="submission-text">${
                          submission.submission_text || "Không có nội dung"
                        }</p>
                        ${
                          submission.file_path
                            ? `
                            <a href="${submission.file_path}" target="_blank" class="submission-file">
                                <i class="fas fa-file-alt"></i> Xem file đính kèm
                            </a>
                        `
                            : ""
                        }
                    </div>
                    <div class="submission-footer">
                        <span class="submission-time">
                            <i class="fas fa-clock"></i> ${formatDateForInput(
                              submission.submitted_at
                            )}
                        </span>
                        ${
                          submission.status === "pending"
                            ? `
                            <button class="btn btn-primary btn-sm" onclick="openGradingModal(${submission.id})">
                                <i class="fas fa-edit"></i> Chấm điểm
                            </button>
                        `
                            : `
                            <div class="submission-grade">
                                <i class="fas fa-star"></i> ${submission.score}/${submission.max_score}
                            </div>
                        `
                        }
                    </div>
                </div>
            `
        )
        .join("");

      // Populate class filter
      populateClassFilter();
    } else {
      submissionsList.innerHTML =
        '<p class="empty-state">Chưa có bài nộp nào</p>';
    }
  } catch (error) {
    console.error("Error loading submissions:", error);
  }
}

// Populate class filter
async function populateClassFilter() {
  if (allClasses.length === 0) {
    const response = await fetch("api/get_teacher_classes.php");
    const data = await response.json();
    if (data.success) {
      allClasses = data.classes;
    }
  }

  const classFilter = document.getElementById("class-filter");
  if (classFilter && allClasses.length > 0) {
    classFilter.innerHTML =
      '<option value="">Tất cả lớp học</option>' +
      allClasses
        .map((cls) => `<option value="${cls.id}">${cls.class_name || cls.name || 'Không có tên'}</option>`)
        .join("");
  }
}
// Load statistics filters
async function loadStatisticsFilters() {
  if (allClasses.length === 0) {
    const response = await fetch("api/get_teacher_classes.php");
    const data = await response.json();
    if (data.success) {
      allClasses = data.classes;
    }
  }

  const statsClassFilter = document.getElementById("stats-class-filter");
  if (statsClassFilter) {
    statsClassFilter.innerHTML =
      '<option value="">Chọn lớp học</option>' +
      allClasses
        .map((cls) => `<option value="${cls.id}">${cls.class_name || 'Không có tên'}</option>`)
        .join("");
  }
}

// Load class statistics
async function loadClassStatistics(classId) {
  try {
    const response = await fetch(
      `api/get_class_statistics.php?class_id=${classId}`
    );
    const data = await response.json();

    const statsContent = document.getElementById("statistics-content");

    if (data.success) {
      statsContent.innerHTML = `
                <div class="stats-overview">
                    <div class="stat-box">
                        <h3>${data.stats.total_students}</h3>
                        <p>Tổng học sinh</p>
                    </div>
                    <div class="stat-box">
                        <h3>${data.stats.total_lessons}</h3>
                        <p>Tổng bài học</p>
                    </div>
                    <div class="stat-box">
                        <h3>${data.stats.avg_completion}%</h3>
                        <p>Tỷ lệ hoàn thành TB</p>
                    </div>
                    <div class="stat-box">
                        <h3>${data.stats.avg_score}</h3>
                        <p>Điểm trung bình</p>
                    </div>
                </div>
                
                <div class="progress-chart">
                    <h3>Tiến độ học tập</h3>
                    <canvas id="progress-chart"></canvas>
                </div>
                
                <div class="top-students">
                    <h3>Học sinh xuất sắc</h3>
                    <div class="students-list">
                        ${data.top_students
                          .map(
                            (student, index) => `
                            <div class="student-rank">
                                <span class="rank">#${index + 1}</span>
                                <span class="name">${student.name}</span>
                                <span class="score">${
                                  student.avg_score
                                } điểm</span>
                            </div>
                        `
                          )
                          .join("")}
                    </div>
                </div>
            `;
    }
  } catch (error) {
    console.error("Error loading statistics:", error);
  }
}


// Add event listener to initialize filters when switching to students section
document.querySelector('[data-section="students"]').addEventListener('click', () => {
    loadStudentsFilters();
});

// Also initialize when document loads
document.addEventListener('DOMContentLoaded', () => {
    if (currentSection === 'students') {
        loadStudentsFilters();
    }
});

// Load students list
async function loadStudentsList(classId) {
  try {
    const response = await fetch(
      `api/get_class_students.php?class_id=${classId}`
    );
    const data = await response.json();

    const studentsList = document.getElementById("students-list");

    if (data.success && data.students.length > 0) {
      studentsList.innerHTML = `
                <div class="students-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Học sinh</th>
                                <th>Email</th>
                                <th>Tiến độ</th>
                                <th>Điểm TB</th>
                                <th>Ngày tham gia</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.students
                              .map(
                                (student) => `
                                <tr>
                                    <td>
                                        <div class="student-cell">
                                            <i class="fas fa-user-circle"></i>
                                            <span>${student.name}</span>
                                        </div>
                                    </td>
                                    <td>${student.email}</td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: ${
                                              student.completion_rate
                                            }%"></div>
                                        </div>
                                        <span>${student.completion_rate}%</span>
                                    </td>
                                    <td>${student.avg_score || "N/A"}</td>
                                    <td>${formatDate(student.enrolled_at)}</td>
                                    <td>
                                        <button class="btn-icon" onclick="viewStudentDetail(${
                                          student.user_id
                                        }, ${classId})">
                                            <i class="fas fa-eye"></i>
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
      studentsList.innerHTML =
        '<p class="empty-state">Chưa có học sinh nào</p>';
    }
  } catch (error) {
    console.error("Error loading students:", error);
  }
}

// Update openAddLessonModal function
function openAddLessonModal(chapterId) {
    Swal.fire({
        title: 'Thêm bài học mới',
        html: `
            <form id="add-lesson-form" class="lesson-form">
                <input type="hidden" id="lesson-chapter-id" value="${chapterId}">
                
                <div class="form-group">
                    <label>Tiêu đề bài học *</label>
                    <input type="text" id="lesson-title" class="swal2-input" placeholder="Nhập tiêu đề bài học" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Loại bài học *</label>
                        <select id="lesson-type" class="swal2-select">
                            <option value="theory">Lý thuyết</option>
                            <option value="practice">Thực hành</option>
                            <option value="test">Kiểm tra</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Thứ tự</label>
                        <input type="number" id="lesson-order" class="swal2-input" value="1" min="1">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Thời gian tối thiểu (phút) *</label>
                        <input type="number" id="lesson-min-duration" class="swal2-input" value="5" min="1">
                    </div>
                    <div class="form-group">
                        <label>Điểm tối đa (nếu có)</label>
                        <input type="number" id="lesson-max-score" class="swal2-input" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Ngày bắt đầu</label>
                        <input type="datetime-local" id="lesson-start-date" class="swal2-input">
                    </div>
                    <div class="form-group">
                        <label>Ngày kết thúc</label>
                        <input type="datetime-local" id="lesson-end-date" class="swal2-input">
                    </div>
                </div>

                <div class="form-group">
                    <label>Mô tả</label>
                    <textarea id="lesson-description" class="swal2-textarea" rows="4" placeholder="Nhập mô tả chi tiết về bài học..."></textarea>
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-file-upload"></i> Tải lên tài liệu *
                    </label>
                    <input type="file" id="lesson-file" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" class="swal2-file" required>
                    <small style="display: block; margin-top: 8px; color: #666;">
                        <i class="fas fa-info-circle"></i> 
                        Chấp nhận file PDF hoặc ảnh (JPG, PNG, GIF, WebP). Tối đa <strong>100MB</strong>
                    </small>
                    <div id="file-preview" style="display: none; margin-top: 12px;"></div>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-plus"></i> Thêm bài học',
        cancelButtonText: '<i class="fas fa-times"></i> Hủy',
        confirmButtonColor: '#2E7D32',
        customClass: {
            popup: 'lesson-modal',
            confirmButton: 'btn-primary',
            cancelButton: 'btn-secondary'
        },
        width: 800,
        padding: 0,
        didOpen: () => {
            setupFileUpload();
        },
        preConfirm: () => {
            const fileInput = document.getElementById('lesson-file');
            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.showValidationMessage('Vui lòng chọn file để tải lên');
                return false;
            }
            
            const file = fileInput.files[0];
            if (file.size > 100 * 1024 * 1024) {
                Swal.showValidationMessage(`File quá lớn: ${(file.size / 1024 / 1024).toFixed(2)}MB. Tối đa 100MB`);
                return false;
            }
            
            return true;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            handleAddLesson(new Event('submit'));
        }
    });
}

// Modal functions
function openCreateClassModal() {
  document.getElementById("create-class-modal").style.display = "flex";
}

function closeCreateClassModal() {
  document.getElementById("create-class-modal").style.display = "none";
  document.getElementById("create-class-form").reset();
}

function openCreateAssignmentModal() {
  const modal = document.getElementById("create-assignment-modal");
  modal.style.display = "flex";
  loadClassesForAssignment(); // Load danh sách lớp khi mở modal
}

function closeCreateAssignmentModal() {
  document.getElementById("create-assignment-modal").style.display = "none";
  document.getElementById("create-assignment-form").reset();
}

function openAddChapterModal(classId) {
  document.getElementById("chapter-class-id").value = classId;
  document.getElementById("add-chapter-modal").style.display = "flex";
}

function closeAddChapterModal() {
  document.getElementById("add-chapter-modal").style.display = "none";
  document.getElementById("add-chapter-form").reset();
}

function openAddLessonModal(chapterId) {
  document.getElementById("lesson-chapter-id").value = chapterId;
  document.getElementById("add-lesson-modal").style.display = "flex";
}

function closeAddLessonModal() {
  document.getElementById("add-lesson-modal").style.display = "none";
  document.getElementById("add-lesson-form").reset();
  document.getElementById("file-preview").style.display = "none";
}

async function openGradingModal(submissionId) {
  try {
    const response = await fetch(
      `api/get_submission_detail.php?id=${submissionId}`
    );
    const data = await response.json();

    if (data.success) {
      const result = await Swal.fire({
        title: 'Chấm điểm bài nộp',
        html: `
          <div class="grading-modal-content">
            <div class="grading-info">
              <h4><i class="fas fa-user"></i> Học sinh: ${data.submission.student_name}</h4>
              <p><i class="fas fa-book"></i> Bài tập: ${data.submission.assignment_title || data.submission.title}</p>
              <p><i class="fas fa-clock"></i> Ngày nộp: ${formatDateTime(data.submission.submitted_at)}</p>
            </div>
            
            ${data.submission.file_path ? `
              <div class="submission-file-section">
                <h5><i class="fas fa-file-alt"></i> File đã nộp:</h5>
                <a href="/uploads/submissions/${data.submission.file_path}" target="_blank" class="btn btn-outline">
                  <i class="fas fa-download"></i> Xem/Tải file
                </a>
              </div>
            ` : '<p class="no-file">Không có file đính kèm</p>'}
            
            <div class="grading-form-section">
              <div class="form-group">
                <label for="grade-score">
                  <i class="fas fa-star"></i> Điểm (Tối đa: ${data.submission.max_score})
                </label>
                <input 
                  type="number" 
                  id="grade-score" 
                  class="swal2-input" 
                  min="0" 
                  max="${data.submission.max_score}" 
                  step="0.5" 
                  value="${data.submission.score || ''}"
                  placeholder="Nhập điểm"
                  required
                  style="width: 90%;"
                >
              </div>
              
              <div class="form-group">
                <label for="grade-feedback">
                  <i class="fas fa-comment"></i> Nhận xét
                </label>
                <textarea 
                  id="grade-feedback" 
                  class="swal2-textarea" 
                  rows="5" 
                  placeholder="Nhập nhận xét cho học sinh..."
                  style="width: 90%; resize: vertical;"
                >${data.submission.feedback || ''}</textarea>
              </div>
            </div>
          </div>
        `,
        width: 700,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> Lưu điểm',
        cancelButtonText: '<i class="fas fa-times"></i> Hủy',
        confirmButtonColor: '#2E7D32',
        cancelButtonColor: '#999',
        customClass: {
          popup: 'grading-modal',
          confirmButton: 'btn-primary',
          cancelButton: 'btn-secondary'
        },
        preConfirm: () => {
          const score = document.getElementById('grade-score').value;
          const feedback = document.getElementById('grade-feedback').value;
          
          if (!score || score === '') {
            Swal.showValidationMessage('Vui lòng nhập điểm');
            return false;
          }
          
          const numScore = parseFloat(score);
          if (numScore < 0 || numScore > parseFloat(data.submission.max_score)) {
            Swal.showValidationMessage(`Điểm phải từ 0 đến ${data.submission.max_score}`);
            return false;
          }
          
          return {
            submission_id: submissionId,
            score: numScore,
            feedback: feedback.trim()
          };
        }
      });

      if (result.isConfirmed) {
        await handleGrading(result.value);
      }
    } else {
      showError(data.message || 'Không thể tải thông tin bài nộp');
    }
  } catch (error) {
    console.error('Error opening grading modal:', error);
    showError('Có lỗi xảy ra khi tải thông tin bài nộp');
  }
}

async function handleGrading(gradingData) {
  try {
    Swal.fire({
      title: 'Đang lưu...',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    const response = await fetch('api/grade_submission.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(gradingData),
    });

    const data = await response.json();

    if (data.success) {
      await Swal.fire({
        icon: 'success',
        title: 'Thành công!',
        text: 'Đã chấm điểm và lưu nhận xét',
        timer: 2000,
        showConfirmButton: false
      });
      
      // Reload data
      await Promise.all([
        loadSubmissions(),
        loadDashboardData()
      ]);
      
      // Đóng modal hiện tại nếu có
      Swal.close();
    } else {
      showError(data.message || 'Không thể chấm điểm');
    }
  } catch (error) {
    console.error('Error grading submission:', error);
    showError('Có lỗi xảy ra khi chấm điểm');
  }
}

// Form handlers
async function handleCreateClass(e) {
  e.preventDefault();

  const formData = {
    name: document.getElementById("class-name").value,
    description: document.getElementById("class-description").value,
    code: document.getElementById("class-code").value,
    max_students: document.getElementById("max-students").value,
  };

  try {
    const response = await fetch("api/create_class.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(formData),
    });

    const data = await response.json();

    if (data.success) {
      showSuccess("Tạo lớp học thành công!");
      closeCreateClassModal();
      loadClasses();
      loadDashboardData();
    } else {
      showError(data.message || "Không thể tạo lớp học");
    }
  } catch (error) {
    console.error("Error creating class:", error);
    showError("Có lỗi xảy ra khi tạo lớp học");
  }
}

async function handleCreateAssignment(e) {
  e.preventDefault();

  const startDate = document.getElementById("assignment-start").value;
  const deadline = document.getElementById("assignment-deadline").value;

  // Validate dates
  if (startDate && new Date(startDate) > new Date(deadline)) {
    showError("Ngày bắt đầu không thể sau hạn nộp");
    return;
  }

  const formData = {
    class_id: document.getElementById("assignment-class").value,
    title: document.getElementById("assignment-title").value,
    description: document.getElementById("assignment-description").value,
    start_date: startDate || null,
    deadline: deadline,
    max_score: document.getElementById("assignment-max-score").value || 10,
  };

  // Validate required fields
  if (!formData.class_id || !formData.title || !formData.deadline) {
    showError("Vui lòng điền đầy đủ thông tin bắt buộc");
    return;
  }

  try {
    const response = await fetch("api/create_assignment.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(formData),
    });

    const data = await response.json();

    if (data.success) {
      showSuccess("Tạo nhiệm vụ thành công!");
      closeCreateAssignmentModal();
      loadAssignments();
      loadDashboardData();
    } else {
      showError(data.message || "Không thể tạo nhiệm vụ");
    }
  } catch (error) {
    console.error("Error creating assignment:", error);
    showError("Có lỗi xảy ra khi tạo nhiệm vụ");
  }
}

// Change handleAddChapter function
async function handleAddChapter(e) {
  e.preventDefault();

  const formData = {
    class_id: document.getElementById("chapter-class-id").value,
    title: document.getElementById("chapter-title").value,
    description: document.getElementById("chapter-description").value,
    order_index: document.getElementById("chapter-order").value,
  };

  try {
    const response = await fetch("api/create_chapter.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(formData),
    });

    const data = await response.json();

    if (data.success) {
      showSuccess("Thêm chương thành công!");
      closeAddChapterModal();
      viewClass(formData.class_id);
    } else {
      showError(data.message || "Không thể thêm chương");
    }
  } catch (error) {
    console.error("Error adding chapter:", error);
    showError("Có lỗi xảy ra khi thêm chương");
  }
}

async function handleAddLesson(e) {
  e.preventDefault();

  const formData = new FormData();
  formData.append(
    "chapter_id",
    document.getElementById("lesson-chapter-id").value
  );
  formData.append("title", document.getElementById("lesson-title").value);
  formData.append(
    "description",
    document.getElementById("lesson-description").value
  );
  formData.append("type", document.getElementById("lesson-type").value);
  formData.append("order_index", document.getElementById("lesson-order").value);
  formData.append(
    "min_duration_minutes",
    document.getElementById("lesson-min-duration").value
  );
  formData.append(
    "max_score",
    document.getElementById("lesson-max-score").value
  );
  formData.append(
    "start_date",
    document.getElementById("lesson-start-date").value
  );
  formData.append("end_date", document.getElementById("lesson-end-date").value);

  const fileInput = document.getElementById("lesson-file");
  if (fileInput.files.length > 0) {
    formData.append("pdf_file", fileInput.files[0]);
  }

  try {
    const response = await fetch("api/create_lesson.php", {
      method: "POST",
      body: formData,
    });

    const data = await response.json();

    if (data.success) {
      showSuccess("Thêm bài học thành công!");
      closeAddLessonModal();
      viewClass(currentClassId);
      loadDashboardData();
    } else {
      showError(data.message || "Không thể thêm bài học");
    }
  } catch (error) {
    console.error("Error adding lesson:", error);
    showError("Có lỗi xảy ra khi thêm bài học");
  }
}

// Delete functions
async function deleteClass(classId) {
  const result = await Swal.fire({
    title: "Xác nhận xóa",
    text: "Bạn có chắc chắn muốn xóa lớp học này? Hành động này không thể hoàn tác!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Xóa",
    cancelButtonText: "Hủy",
  });

  if (result.isConfirmed) {
    try {
      const response = await fetch("api/delete_class.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ class_id: classId }),
      });

      const data = await response.json();

      if (data.success) {
        showSuccess("Xóa lớp học thành công!");
        loadClasses();
        loadDashboardData();
      } else {
        showError(data.message || "Không thể xóa lớp học");
      }
    } catch (error) {
      console.error("Error deleting class:", error);
      showError("Có lỗi xảy ra khi xóa lớp học");
    }
  }
}

async function deleteChapter(chapterId) {
  const result = await Swal.fire({
    title: "Xác nhận xóa",
    text: "Bạn có chắc chắn muốn xóa chương này?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Xóa",
    cancelButtonText: "Hủy",
  });

  if (result.isConfirmed) {
    try {
      const response = await fetch("api/delete_chapter.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ chapter_id: chapterId }),
      });

      const data = await response.json();

      if (data.success) {
        showSuccess("Xóa chương thành công!");
        // Change viewClassDetail to viewClass
        viewClass(currentClassId);
      } else {
        showError(data.message || "Không thể xóa chương");
      }
    } catch (error) {
      console.error("Error deleting chapter:", error);
      showError("Có lỗi xảy ra khi xóa chương");
    }
  }
}

async function deleteLesson(lessonId) {
  const result = await Swal.fire({
    title: "Xác nhận xóa",
    text: "Bạn có chắc chắn muốn xóa bài học này?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Xóa",
    cancelButtonText: "Hủy",
  });

  if (result.isConfirmed) {
    try {
      const response = await fetch("api/delete_lesson.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ lesson_id: lessonId }),
      });

      const data = await response.json();

      if (data.success) {
        showSuccess("Xóa bài học thành công!");
        // Change viewClassDetail to viewClass
        viewClass(currentClassId);
        loadDashboardData();
      } else {
        showError(data.message || "Không thể xóa bài học");
      }
    } catch (error) {
      console.error("Error deleting lesson:", error);
      showError("Có lỗi xảy ra khi xóa bài học");
    }
  }
}

async function deleteAssignment(assignmentId) {
  const result = await Swal.fire({
    title: "Xác nhận xóa",
    text: "Bạn có chắc chắn muốn xóa nhiệm vụ này?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Xóa",
    cancelButtonText: "Hủy",
  });

  if (result.isConfirmed) {
    try {
      const response = await fetch("api/delete_assignment.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ assignment_id: assignmentId }),
      });

      const data = await response.json();

      if (data.success) {
        showSuccess("Xóa nhiệm vụ thành công!");
        loadAssignments();
        loadDashboardData();
      } else {
        showError(data.message || "Không thể xóa nhiệm vụ");
      }
    } catch (error) {
      console.error("Error deleting assignment:", error);
      showError("Có lỗi xảy ra khi xóa nhiệm vụ");
    }
  }
}

// Edit functions
async function editClass(classId) {
  try {
    const response = await fetch(`api/edit_class.php?id=${classId}`);
    const data = await response.json();

    if (data.success) {
      const cls = data.class;

      const result = await Swal.fire({
        title: "Chỉnh sửa lớp học",
        html: `
          <form class="edit-class-form">
            <div class="form-group">
              <label>Tên lớp học *</label>
              <input type="text" id="edit-class-name" class="swal2-input" 
                value="${cls.class_name || cls.name || ''}" 
                placeholder="Nhập tên lớp học"
                required>
            </div>
            
            <div class="form-group">
              <label>Mô tả</label>
              <textarea id="edit-class-description" class="swal2-textarea" 
                placeholder="Nhập mô tả về lớp học..."
                rows="4">${cls.description || ""}</textarea>
            </div>
            
            <div class="form-row">
              <div class="form-group">
                <label>Mã lớp</label>
                <input type="text" id="edit-class-code" class="swal2-input" 
                  value="${cls.code || ""}"
                  placeholder="VD: EDT4001">
              </div>
              
              <div class="form-group">
                <label>Số học sinh tối đa</label>
                <input type="number" id="edit-class-max-students" class="swal2-input" 
                  value="${cls.max_students || 50}"
                  min="1"
                  placeholder="50">
              </div>
            </div>
          </form>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> Lưu thay đổi',
        cancelButtonText: '<i class="fas fa-times"></i> Hủy',
        confirmButtonColor: '#2E7D32',
        cancelButtonColor: '#999',
        width: 600,
        customClass: {
          popup: 'edit-class-modal',
          confirmButton: 'btn-primary',
          cancelButton: 'btn-secondary'
        },
        preConfirm: () => {
          const name = document.getElementById("edit-class-name").value.trim();
          if (!name) {
            Swal.showValidationMessage("Vui lòng nhập tên lớp học");
            return false;
          }
          return {
            class_id: classId,
            name: name,
            description: document.getElementById("edit-class-description").value.trim(),
            code: document.getElementById("edit-class-code").value.trim(),
            max_students: document.getElementById("edit-class-max-students").value,
          };
        },
      });

      if (result.isConfirmed) {
        const updateResponse = await fetch("api/edit_class.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(result.value),
        });

        const updateData = await updateResponse.json();

        if (updateData.success) {
          showSuccess("Cập nhật lớp học thành công!");
          loadClasses();
        } else {
          showError(updateData.message || "Không thể cập nhật lớp học");
        }
      }
    }
  } catch (error) {
    console.error("Error editing class:", error);
    showError("Có lỗi xảy ra khi chỉnh sửa lớp học");
  }
}

async function editChapter(chapterId) {
  try {
    const response = await fetch(`api/edit_chapter.php?id=${chapterId}`);
    const data = await response.json();

    if (data.success) {
      const chapter = data.chapter;

      const result = await Swal.fire({
        title: "Chỉnh sửa chương",
        html: `
                    <div style="text-align: left;">
                        <div class="form-group">
                            <label>Tiêu đề chương *</label>
                            <input type="text" id="edit-chapter-title" class="swal2-input" value="${
                              chapter.title
                            }" style="width: 90%;">
                        </div>
                        <div class="form-group">
                            <label>Mô tả</label>
                            <textarea id="edit-chapter-description" class="swal2-textarea" style="width: 90%;">${
                              chapter.description || ""
                            }</textarea>
                        </div>
                        <div class="form-group">
                            <label>Thứ tự</label>
                            <input type="number" id="edit-chapter-order" class="swal2-input" value="${
                              chapter.order_index
                            }" style="width: 90%;">
                        </div>
                    </div>
                `,
        showCancelButton: true,
        confirmButtonText: "Lưu",
        cancelButtonText: "Hủy",
        preConfirm: () => {
          const title = document.getElementById("edit-chapter-title").value;
          if (!title) {
            Swal.showValidationMessage("Vui lòng nhập tiêu đề chương");
            return false;
          }
          return {
            chapter_id: chapterId,
            title: title,
            description: document.getElementById("edit-chapter-description")
              .value,
            order_index: document.getElementById("edit-chapter-order").value,
          };
        },
      });

      if (result.isConfirmed) {
        const updateResponse = await fetch("api/edit_chapter.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(result.value),
        });

        const updateData = await updateResponse.json();

        if (updateData.success) {
          showSuccess("Cập nhật chương thành công!");
          viewClass(currentClassId);
        } else {
          showError(updateData.message || "Không thể cập nhật chương");
        }
      }
    }
  } catch (error) {
    console.error("Error editing chapter:", error);
    showError("Có lỗi xảy ra khi chỉnh sửa chương");
  }
}

async function editLesson(lessonId) {
  try {
    const response = await fetch(`api/get_lesson.php?id=${lessonId}`);
    const data = await response.json();

    if (data.success) {
      const lesson = data.lesson;

      const result = await Swal.fire({
        title: "Chỉnh sửa bài học",
        html: `
                    <form class="lesson-form">
                        <!-- Tiêu đề bài học - Full width -->
                        <div class="form-group">
                            <label>Tiêu đề bài học *</label>
                            <input type="text" id="edit-lesson-title" class="swal2-input" value="${
                              lesson.title
                            }" required>
                        </div>

                        <!-- Row 1: Loại bài học + Thứ tự -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>Loại bài học *</label>
                                <select id="edit-lesson-type" class="swal2-select">
                                    <option value="theory" ${
                                      lesson.type === "theory" ? "selected" : ""
                                    }>Lý thuyết</option>
                                    <option value="practice" ${
                                      lesson.type === "practice"
                                        ? "selected"
                                        : ""
                                    }>Thực hành</option>
                                    <option value="test" ${
                                      lesson.type === "test" ? "selected" : ""
                                    }>Kiểm tra</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Thứ tự</label>
                                <input type="number" id="edit-lesson-order" class="swal2-input" value="${
                                  lesson.order_index
                                }" min="1">
                            </div>
                        </div>

                        <!-- Row 2: Thời gian + Điểm -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>Thời gian tối thiểu (phút) *</label>
                                <input type="number" id="edit-lesson-duration" class="swal2-input" value="${
                                  lesson.min_duration_minutes
                                }" min="1">
                                <small>Học sinh phải học tối thiểu thời gian này để hoàn thành</small>
                            </div>
                            <div class="form-group">
                                <label>Điểm tối đa (nếu có)</label>
                                <input type="number" id="edit-lesson-score" class="swal2-input" value="${
                                  lesson.max_score || ""
                                }" min="0">
                            </div>
                        </div>

                        <!-- Row 3: Ngày bắt đầu + Ngày kết thúc -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>Ngày bắt đầu</label>
                                <input type="datetime-local" id="edit-lesson-start-date" class="swal2-input" 
                                    value="${formatDateForInput(
                                      lesson.start_date
                                    )}">
                            </div>
                            <div class="form-group">
                                <label>Ngày kết thúc</label>
                                <input type="datetime-local" id="edit-lesson-end-date" class="swal2-input" 
                                    value="${formatDateForInput(
                                      lesson.end_date
                                    )}">
                            </div>
                        </div>

                        <!-- Mô tả - Full width -->
                        <div class="form-group">
                            <label>Mô tả</label>
                            <textarea id="edit-lesson-description" class="swal2-textarea" rows="4"
                                placeholder="Nhập mô tả chi tiết về bài học...">${
                                  lesson.description || ""
                                }</textarea>
                        </div>

                        <!-- File Upload - Full width -->
                        <div class="form-group">
                            <label>Tài liệu PDF</label>
                            <div class="current-file">
                                <i class="fas fa-file-pdf"></i>
                                <span>PDF hiện tại: ${
                                  lesson.file_path
                                    ? lesson.file_path.split("/").pop()
                                    : "Chưa có file"
                                }</span>
                            </div>
                            <input type="file" id="edit-lesson-file" accept=".pdf" class="swal2-file">
                            <small>Để trống nếu không muốn thay đổi file. Chỉ chấp nhận file PDF, tối đa 10MB.</small>
                        </div>
                    </form>
                `,
        showCancelButton: true,
        confirmButtonText: "Lưu thay đổi",
        cancelButtonText: "Hủy",
        confirmButtonColor: "#2E7D32",
        customClass: {
          popup: "lesson-modal",
          confirmButton: "btn-primary",
          cancelButton: "btn-secondary",
        },
        width: 800,
        padding: 0,
      });

      if (result.isConfirmed) {
        const formData = new FormData();
        formData.append("lesson_id", lessonId);
        formData.append(
          "title",
          document.getElementById("edit-lesson-title").value
        );
        formData.append(
          "type",
          document.getElementById("edit-lesson-type").value
        );
        formData.append(
          "min_duration_minutes",
          document.getElementById("edit-lesson-duration").value
        );
        formData.append(
          "max_score",
          document.getElementById("edit-lesson-score").value
        );
        formData.append(
          "description",
          document.getElementById("edit-lesson-description").value
        );
        formData.append(
          "start_date",
          document.getElementById("edit-lesson-start-date").value
        );
        formData.append(
          "end_date",
          document.getElementById("edit-lesson-end-date").value
        );

        const fileInput = document.getElementById("edit-lesson-file");
        if (fileInput.files.length > 0) {
          formData.append("pdf_file", fileInput.files[0]);
        }

        const updateResponse = await fetch("api/edit_lesson.php", {
          method: "POST",
          body: formData,
        });

        const updateData = await updateResponse.json();

        if (updateData.success) {
          showSuccess("Cập nhật bài học thành công!");
          viewClass(currentClassId);
        } else {
          showError(updateData.message || "Không thể cập nhật bài học");
        }
      }
    }
  } catch (error) {
    console.error("Error editing lesson:", error);
    showError("Có lỗi xảy ra khi chỉnh sửa bài học");
  }
}

async function viewLesson(lessonId) {
  try {
    const response = await fetch(`api/get_lesson.php?id=${lessonId}`);
    const data = await response.json();

    if (data.success) {
      const lesson = data.lesson;

      Swal.fire({
        title: lesson.title,
        html: `
                    <div class="lesson-detail">
                        <div class="lesson-info">
                            <div class="info-item">
                                <i class="fas ${getLessonIcon(
                                  lesson.type
                                )}"></i>
                                <span>${
                                  lesson.type === "theory"
                                    ? "Lý thuyết"
                                    : lesson.type === "practice"
                                    ? "Thực hành"
                                    : "Kiểm tra"
                                }</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <span>${lesson.min_duration_minutes} phút</span>
                            </div>
                            ${
                              lesson.max_score
                                ? `
                                <div class="info-item">
                                    <i class="fas fa-star"></i>
                                    <span>${lesson.max_score} điểm</span>
                                </div>
                            `
                                : ""
                            }
                        </div>

                        <div class="lesson-dates">
                            <div class="date-item">
                                <label>Bắt đầu:</label>
                                <span>${
                                  lesson.start_date
                                    ? new Date(
                                        lesson.start_date
                                      ).toLocaleString("vi-VN")
                                    : "Không có"
                                }</span>
                            </div>
                            <div class="date-item">
                                <label>Kết thúc:</label>
                                <span>${
                                  lesson.end_date
                                    ? new Date(lesson.end_date).toLocaleString(
                                        "vi-VN"
                                      )
                                    : "Không có"
                                }</span>
                            </div>
                        </div>

                        ${
                          lesson.description
                            ? `
                            <div class="lesson-description">
                                <h4>Mô tả:</h4>
                                <p>${lesson.description}</p>
                            </div>
                        `
                            : ""
                        }

                        ${
                          lesson.file_path
                            ? `
                            <div class="lesson-file">
                                <h4>Tài liệu:</h4>
                                <a href="${lesson.file_path}" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-file-pdf"></i> Xem tài liệu PDF
                                </a>
                            </div>
                        `
                            : ""
                        }

                        <div class="lesson-stats">
                            <div class="stat-item">
                                <label>Số học sinh đã hoàn thành:</label>
                                <span>${lesson.completed_students || 0}/${
          lesson.enrolled_students || 0
        }</span>
                            </div>
                        </div>
                    </div>
                `,
        width: 800,
        showCloseButton: true,
        showConfirmButton: false,
        customClass: {
          container: "lesson-view-modal",
        },
      });
    } else {
      showError("Không thể tải thông tin bài học");
    }
  } catch (error) {
    console.error("Error viewing lesson:", error);
    showError("Có lỗi xảy ra khi xem bài học");
  }
}

// Helper function to format date for datetime-local input
function formatDateForInput(dateStr) {
  if (!dateStr) return "";
  const date = new Date(dateStr);
  return date.toISOString().slice(0, 16);
}

// Load assignments
async function loadAssignments() {
  try {
    const response = await fetch("api/get_teacher_assignments.php");
    const data = await response.json();

    if (data.success) {
      allAssignments = data.assignments;
      displayAssignments(allAssignments);

      // Populate assignment class dropdown
      const assignmentClassSelect = document.getElementById("assignment-class");
      if (assignmentClassSelect && allClasses.length === 0) {
        const classesResponse = await fetch("api/get_teacher_classes.php");
        const classesData = await classesResponse.json();
        if (classesData.success) {
          allClasses = classesData.classes;
        }
      }

      if (assignmentClassSelect) {
        assignmentClassSelect.innerHTML =
          '<option value="">Chọn lớp học</option>' +
          allClasses
            .map((cls) => `<option value="${cls.id}">${cls.name}</option>`)
            .join("");
      }
    }
  } catch (error) {
    console.error("Error loading assignments:", error);
  }
}

function displayAssignments(assignments) {
  const assignmentsList = document.getElementById("assignments-list");

  if (!assignmentsList) {
    console.error("Assignments list container not found");
    return;
  }

  if (!assignments || assignments.length === 0) {
    assignmentsList.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-tasks"></i>
                <p>Chưa có nhiệm vụ nào</p>
            </div>
        `;
    return;
  }

  assignmentsList.innerHTML = assignments
    .map(
      (assignment) => `
        <div class="assignment-card">
            <div class="assignment-header">
                <h3>${assignment.title}</h3>
                <span class="assignment-badge ${getAssignmentStatus(
                  assignment
                )}">
                    ${getAssignmentStatusText(assignment)}
                </span>
            </div>
            <p class="assignment-class">Lớp: ${
              assignment.class_name || "Không có tên lớp"
            }</p>
            <p class="assignment-description">Mô tả: ${
              assignment.description || "Không có mô tả"
            }</p>
            <div class="assignment-meta">
            <div class="meta-item start-date">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Bắt đầu: ${formatDateTime(
                      assignment.start_date
                    )}</span>
                </div>
                <div class="meta-item deadline">
                    <i class="fas fa-clock"></i>
                    <span>Hạn nộp: ${formatDateTime(assignment.deadline)}</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-users"></i>
                    <span>${assignment.submission_count || 0} bài nộp</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-star"></i>
                    <span>${assignment.max_score || 10} điểm</span>
                </div>
            </div>
            <div class="assignment-actions">
                <button class="btn btn-sm" onclick="viewSubmissions(${
                  assignment.id
                })">
                    <i class="fas fa-eye"></i> Xem bài nộp
                </button>
                <div class="action-buttons">
                    <button class="btn-icon edit" onclick="editAssignment(${
                      assignment.id
                    })" title="Sửa">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon delete" onclick="deleteAssignment(${
                      assignment.id
                    })" title="Xóa">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `
    )
    .join("");
}

function formatDateTime(dateStr) {
  if (!dateStr) return "Chưa có";
  const date = new Date(dateStr);
  return date.toLocaleString("vi-VN", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  });
}

// Filter assignments
function filterAssignments(filter) {
  let filtered = allAssignments;

  if (filter !== "all") {
    filtered = allAssignments.filter((assignment) => {
      const status = getAssignmentStatus(assignment);
      return status === filter;
    });
  }

  displayAssignments(filtered);
}

// Load submissions
async function loadSubmissions() {
  try {
    const classId = document.getElementById("class-filter").value;
    const status = document.getElementById("status-filter").value;

    let url = "api/get_teacher_submissions.php?";
    if (classId) url += `class_id=${classId}&`;
    if (status) url += `status=${status}`;

    const response = await fetch(url);
    const data = await response.json();

    const submissionsList = document.getElementById("submissions-list");

    if (data.success && data.submissions.length > 0) {
      allSubmissions = data.submissions;
      submissionsList.innerHTML = data.submissions
        .map(
          (submission) => `
                <div class="submission-card">
                    <div class="submission-header">
                        <div class="student-info">
                            <i class="fas fa-user-circle"></i>
                            <div>
                                <h4>${submission.student_name}</h4>
                                <p>${submission.class_name}</p>
                            </div>
                        </div>
                        <span class="submission-badge ${submission.status}">
                            ${
                              submission.status === "pending"
                                ? "Chờ chấm"
                                : "Đã chấm"
                            }
                        </span>
                    </div>
                    <div class="submission-content">
                        <h4>${
                          submission.assignment_title || submission.lesson_title
                        }</h4>
                        <p class="submission-text">${
                          submission.submission_text || "Không có nội dung"
                        }</p>
                        ${
                          submission.file_path
                            ? `
                            <a href="${submission.file_path}" target="_blank" class="submission-file">
                                <i class="fas fa-file-alt"></i> Xem file đính kèm
                            </a>
                        `
                            : ""
                        }
                    </div>
                    <div class="submission-footer">
                        <span class="submission-time">
                            <i class="fas fa-clock"></i> ${formatDate(
                              submission.submitted_at
                            )}
                        </span>
                        ${
                          submission.status === "pending"
                            ? `
                            <button class="btn btn-primary btn-sm" onclick="openGradingModal(${submission.id})">
                                <i class="fas fa-edit"></i> Chấm điểm
                            </button>
                        `
                            : `
                            <div class="submission-grade">
                                <i class="fas fa-star"></i> ${submission.score}/${submission.max_score}
                            </div>
                        `
                        }
                    </div>
                </div>
            `
        )
        .join("");

      // Populate class filter
      populateClassFilter();
    } else {
      submissionsList.innerHTML =
        '<p class="empty-state">Chưa có bài nộp nào</p>';
    }
  } catch (error) {
    console.error("Error loading submissions:", error);
  }
}

// Populate class filter
async function populateClassFilter() {
  if (allClasses.length === 0) {
    const response = await fetch("api/get_teacher_classes.php");
    const data = await response.json();
    if (data.success) {
      allClasses = data.classes;
    }
  }

  const classFilter = document.getElementById("class-filter");
  if (classFilter && allClasses.length > 0) {
    classFilter.innerHTML =
      '<option value="">Tất cả lớp học</option>' +
      allClasses
        .map((cls) => `<option value="${cls.id}">${cls.class_name || 'Không có tên'}</option>`)
        .join("");
  }
}

// Load class statistics
async function loadClassStatistics(classId) {
  try {
    const response = await fetch(
      `api/get_class_statistics.php?class_id=${classId}`
    );
    const data = await response.json();

    const statsContent = document.getElementById("statistics-content");

    if (data.success) {
      statsContent.innerHTML = `
                <div class="stats-overview">
                    <div class="stat-box">
                        <h3>${data.stats.total_students}</h3>
                        <p>Tổng học sinh</p>
                    </div>
                    <div class="stat-box">
                        <h3>${data.stats.total_lessons}</h3>
                        <p>Tổng bài học</p>
                    </div>
                    <div class="stat-box">
                        <h3>${data.stats.avg_completion}%</h3>
                        <p>Tỷ lệ hoàn thành TB</p>
                    </div>
                    <div class="stat-box">
                        <h3>${data.stats.avg_score}</h3>
                        <p>Điểm trung bình</p>
                    </div>
                </div>
                
                <div class="progress-chart">
                    <h3>Tiến độ học tập</h3>
                    <canvas id="progress-chart"></canvas>
                </div>
                
                <div class="top-students">
                    <h3>Học sinh xuất sắc</h3>
                    <div class="students-list">
                        ${data.top_students
                          .map(
                            (student, index) => `
                            <div class="student-rank">
                                <span class="rank">#${index + 1}</span>
                                <span class="name">${student.name}</span>
                                <span class="score">${
                                  student.avg_score
                                } điểm</span>
                            </div>
                        `
                          )
                          .join("")}
                    </div>
                </div>
            `;
    }
  } catch (error) {
    console.error("Error loading statistics:", error);
  }
}

// Load students filters
async function loadStudentsFilters() {
  if (allClasses.length === 0) {
    const response = await fetch("api/get_teacher_classes.php");
    const data = await response.json();
    if (data.success) {
      allClasses = data.classes;
    }
  }

  const studentsClassFilter = document.getElementById("students-class-filter");
  if (studentsClassFilter) {
    studentsClassFilter.innerHTML =
      '<option value="">Chọn lớp học</option>' +
      allClasses
        .map((cls) => `<option value="${cls.id}">${cls.class_name}</option>`)
        .join("");
  }
}

// File upload handling - FIXED
function setupFileUpload() {
  // File input preview for lesson uploads
  const fileInput = document.getElementById("lesson-file");
  const filePreview = document.getElementById("file-preview");

  if (fileInput && filePreview) {
    fileInput.addEventListener("change", function () {
      if (this.files && this.files[0]) {
        const file = this.files[0];

        // ✅ Check if file is PDF or Image
        const allowedTypes = [
          'application/pdf',
          'image/jpeg',
          'image/jpg',
          'image/png',
          'image/gif',
          'image/webp'
        ];
        
        if (!allowedTypes.includes(file.type)) {
          showError("Chỉ chấp nhận file PDF hoặc ảnh (JPG, PNG, GIF, WebP)");
          this.value = "";
          filePreview.style.display = "none";
          return;
        }

        // ✅ Check file size (max 100MB)
        if (file.size > 100 * 1024 * 1024) {
          showError("File không được vượt quá 100MB");
          this.value = "";
          filePreview.style.display = "none";
          return;
        }

        // ✅ Show file preview with icon based on type
        const fileIcon = file.type.startsWith('image/') ? 'fa-file-image' : 'fa-file-pdf';
        const fileColor = file.type.startsWith('image/') ? '#4CAF50' : '#2E7D32';
        
        filePreview.innerHTML = `
          <div class="file-info">
            <i class="fas ${fileIcon}" style="color: ${fileColor};"></i>
            <span>${file.name}</span>
            <small>(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
            <span class="file-type-badge">${file.type.startsWith('image/') ? 'Hình ảnh' : 'PDF'}</span>
          </div>
        `;
        filePreview.style.display = "block";
      }
    });
  }

  // File input preview for edit lesson
  const editFileInput = document.getElementById("edit-lesson-file");
  const editFilePreview = document.getElementById("edit-file-preview");

  if (editFileInput && editFilePreview) {
    editFileInput.addEventListener("change", function () {
      if (this.files && this.files[0]) {
        const file = this.files[0];

        // ✅ Check if file is PDF or Image
        const allowedTypes = [
          'application/pdf',
          'image/jpeg',
          'image/jpg',
          'image/png',
          'image/gif',
          'image/webp'
        ];
        
        if (!allowedTypes.includes(file.type)) {
          showError("Chỉ chấp nhận file PDF hoặc ảnh (JPG, PNG, GIF, WebP)");
          this.value = "";
          editFilePreview.style.display = "none";
          return;
        }

        // ✅ Check file size (max 100MB)
        if (file.size > 100 * 1024 * 1024) {
          showError("File không được vượt quá 100MB");
          this.value = "";
          editFilePreview.style.display = "none";
          return;
        }

        // ✅ Show file preview
        const fileIcon = file.type.startsWith('image/') ? 'fa-file-image' : 'fa-file-pdf';
        const fileColor = file.type.startsWith('image/') ? '#4CAF50' : '#2E7D32';
        
        editFilePreview.innerHTML = `
          <div class="file-info">
            <i class="fas ${fileIcon}" style="color: ${fileColor};"></i>
            <span>${file.name}</span>
            <small>(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
            <span class="file-type-badge">${file.type.startsWith('image/') ? 'Hình ảnh' : 'PDF'}</span>
          </div>
        `;
        editFilePreview.style.display = "block";
      }
    });
  }
}

// ✅ Improved error handling for add lesson
async function handleAddLesson(e) {
  e.preventDefault();

  const formData = new FormData();
  formData.append(
    "chapter_id",
    document.getElementById("lesson-chapter-id").value
  );
  formData.append("title", document.getElementById("lesson-title").value);
  formData.append(
    "description",
    document.getElementById("lesson-description").value
  );
  formData.append("type", document.getElementById("lesson-type").value);
  formData.append("order_index", document.getElementById("lesson-order").value);
  formData.append(
    "min_duration_minutes",
    document.getElementById("lesson-min-duration").value
  );
  formData.append(
    "max_score",
    document.getElementById("lesson-max-score").value
  );
  formData.append(
    "start_date",
    document.getElementById("lesson-start-date").value
  );
  formData.append("end_date", document.getElementById("lesson-end-date").value);

  const fileInput = document.getElementById("lesson-file");
  if (fileInput.files.length > 0) {
    formData.append("pdf_file", fileInput.files[0]);
  }

  try {
    showLoading('Đang thêm bài học...');
    
    const response = await fetch("api/create_lesson.php", {
      method: "POST",
      body: formData,
    });

    // ✅ Check if response is JSON
    const contentType = response.headers.get("content-type");
    if (!contentType || !contentType.includes("application/json")) {
      const text = await response.text();
      console.error('Server returned non-JSON response:', text);
      throw new Error("Server trả về dữ liệu không hợp lệ. Vui lòng kiểm tra lại cấu hình server.");
    }

    const data = await response.json();

    if (data.success) {
      Swal.close();
      showSuccess("Thêm bài học thành công!");
      closeAddLessonModal();
      viewClass(currentClassId);
      loadDashboardData();
    } else {
      Swal.close();
      showError(data.message || "Không thể thêm bài học");
    }
  } catch (error) {
    console.error("Error adding lesson:", error);
    Swal.close();
    showError(error.message || "Có lỗi xảy ra khi thêm bài học");
  }
}

function formatDate(dateStr) {
  if (!dateStr) return "Chưa có";
  const date = new Date(dateStr);
  const now = new Date();
  const isToday = date.toDateString() === now.toDateString();
  const isTomorrow =
    new Date(now.setDate(now.getDate() + 1)).toDateString() ===
    date.toDateString();

  const timeStr = date.toLocaleTimeString("vi-VN", {
    hour: "2-digit",
    minute: "2-digit",
  });

  if (isToday) {
    return `Hôm nay, ${timeStr}`;
  } else if (isTomorrow) {
    return `Ngày mai, ${timeStr}`;
  } else {
    return date.toLocaleDateString("vi-VN", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  }
}

// Notification functions
function showSuccess(message) {
  Swal.fire({
    icon: "success",
    title: "Thành công",
    text: message,
    timer: 2000,
    showConfirmButton: false,
    position: "top-end",
    toast: true,
  });
}

function showError(message) {
  Swal.fire({
    icon: "error",
    title: "Lỗi",
    text: message,
    timer: 3000,
    showConfirmButton: false,
    position: "top-end",
    toast: true,
  });
}

// Modal functions
function openAddStudentsModal(classId) {
  currentClassId = classId;
  document.getElementById("add-students-modal").style.display = "flex";
}

function closeAddStudentsModal() {
  document.getElementById("add-students-modal").style.display = "none";
  document.getElementById("student-ids").value = "";
  document.getElementById("student-emails").value = "";
}

function openExportModal(classId) {
  currentClassId = classId;
  document.getElementById("export-modal").style.display = "flex";
}

function closeExportModal() {
  document.getElementById("export-modal").style.display = "none";
}

// Tab handling
document.querySelectorAll(".tab-btn").forEach((btn) => {
  btn.addEventListener("click", function () {
    // Remove active class from all tabs
    document
      .querySelectorAll(".tab-btn")
      .forEach((b) => b.classList.remove("active"));
    document
      .querySelectorAll(".tab-pane")
      .forEach((p) => p.classList.remove("active"));

    // Add active class to clicked tab
    this.classList.add("active");
    document.getElementById(`${this.dataset.tab}-tab`).classList.add("active");
  });
});

async function handleAddStudents() {
  try {
    const activeTab = document.querySelector(".tab-btn.active").dataset.tab;
    let students;

    // Validate input
    if (activeTab === "id") {
      const idsText = document.getElementById("student-ids").value;
      students = idsText
        .split(/[\n,]+/)
        .map((id) => id.trim())
        .filter((id) => id);
      if (students.length === 0) {
        showError("Vui lòng nhập ID của học sinh");
        return;
      }
    } else {
      const emailsText = document.getElementById("student-emails").value;
      students = emailsText
        .split("\n")
        .map((email) => email.trim())
        .filter((email) => email);
      if (students.length === 0) {
        showError("Vui lòng nhập email của học sinh");
        return;
      }
    }

    const response = await fetch("api/add_students.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        class_id: currentClassId,
        type: activeTab,
        students: students,
      }),
    });

    const data = await response.json();

    // Xử lý phản hồi từ server
    if (data.success) {
      // Thu thập thông tin về lỗi và thành công
      const errors = [];
      let addedCount = 0;

      if (data.results && Array.isArray(data.results)) {
        data.results.forEach((result) => {
          const identifier = activeTab === "id" ? result.id : result.email;

          if (result.status === "error") {
            switch (result.reason) {
              case "not_found":
                errors.push(`${identifier}: Không tìm thấy học sinh`);
                break;
              case "already_enrolled":
                errors.push(`${identifier}: Đã có trong lớp học`);
                break;
              case "teacher":
                errors.push(`${identifier}: Là tài khoản giáo viên`);
                break;
              case "admin":
                errors.push(`${identifier}: Là tài khoản admin`);
                break;
              default:
                errors.push(
                  `${identifier}: ${result.message || "Lỗi không xác định"}`
                );
            }
          } else if (result.status === "success") {
            addedCount++;
          }
        });
      }

      // Hiển thị kết quả
      if (addedCount > 0) {
        closeAddStudentsModal();
        await Promise.all([viewClass(currentClassId), loadClasses()]);

        if (errors.length > 0) {
          showSuccess(
            `✓ Đã thêm thành công ${addedCount} học sinh\n\n⚠ Không thể thêm ${
              errors.length
            } học sinh:\n${errors.join("\n")}`
          );
        } else {
          showSuccess(`✓ Đã thêm thành công ${addedCount} học sinh`);
        }
      } else {
        // Không có học sinh nào được thêm
        if (errors.length > 0) {
          showError(`Không thể thêm học sinh có ID:\n\n${errors.join("\n")}`);
        } else {
          showError("Không có học sinh nào được thêm vào lớp");
        }
      }
    } else {
      // Xử lý lỗi từ server (success = false)
      let errorMessage = "Không thể thêm học sinh";

      if (data.error_code) {
        switch (data.error_code) {
          case "class_full":
            errorMessage = "Lớp học đã đạt số lượng học sinh tối đa";
            break;
          case "class_closed":
            errorMessage = "Lớp học đã đóng, không thể thêm học sinh";
            break;
          case "invalid_input":
            errorMessage = "Dữ liệu không hợp lệ, vui lòng kiểm tra lại";
            break;
          default:
            errorMessage = data.message || "Đã xảy ra lỗi không xác định";
        }
      } else if (data.message) {
        errorMessage = data.message;
      }

      if (data.errors && Array.isArray(data.errors) && data.errors.length > 0) {
        errorMessage += "\n\n" + data.errors.join("\n");
      }

      showError(errorMessage);
    }
  } catch (error) {
    console.error("Error adding students:", error);
    showError("Không thể kết nối với máy chủ, vui lòng thử lại sau");
  }
}

function showLoading(message = 'Đang xử lý...') {
  Swal.fire({
    title: message,
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
}

function formatTimeAgo(dateStr) {
  const date = new Date(dateStr);
  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'Vừa xong';
  if (diffMins < 60) return `${diffMins} phút trước`;
  if (diffHours < 24) return `${diffHours} giờ trước`;
  if (diffDays < 7) return `${diffDays} ngày trước`;
  
  return date.toLocaleDateString('vi-VN');
}

function getActivityIcon(type) {
  switch (type) {
    case 'submission':
      return 'fa-file-upload';
    case 'assignment':
      return 'fa-tasks';
    case 'lesson':
      return 'fa-book';
    case 'class':
      return 'fa-users';
    default:
      return 'fa-bell';
  }
}

function getDeadlineUrgency(deadline) {
  const now = new Date();
  const deadlineDate = new Date(deadline);
  const diffDays = Math.ceil((deadlineDate - now) / (1000 * 60 * 60 * 24));
  
  if (diffDays < 0) return 'expired';
  if (diffDays <= 1) return 'urgent';
  if (diffDays <= 3) return 'warning';
  return 'normal';
}

function formatDeadline(deadline) {
  const now = new Date();
  const deadlineDate = new Date(deadline);
  const diffMs = deadlineDate - now;
  const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
  
  if (diffDays < 0) return 'Đã hết hạn';
  if (diffDays === 0) return 'Hôm nay';
  if (diffDays === 1) return 'Ngày mai';
  if (diffDays <= 7) return `Còn ${diffDays} ngày`;
  
  return deadlineDate.toLocaleDateString('vi-VN');
}

// Export handler - Updated with better error handling
// Export handler - Fixed type parameter
async function handleExport(type) {
  if (!currentClassId) {
    showError('Vui lòng chọn lớp học trước');
    return;
  }

  // ✅ Validate and map type parameter
  const validTypes = ['basic', 'scores', 'full'];
  if (!validTypes.includes(type)) {
    showError('Loại xuất không hợp lệ');
    return;
  }

  try {
    showLoading('Đang xuất dữ liệu...');
    
    const response = await fetch(
      `api/export_students.php?class_id=${currentClassId}&type=${type}`
    );

    if (response.ok) {
      const contentType = response.headers.get('content-type');
      
      // Check if response is actually an Excel file
      if (contentType && contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
        const blob = await response.blob();
        
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        
        // ✅ Better filename based on type
        const typeLabel = {
          'basic': 'danh_sach_co_ban',
          'scores': 'danh_sach_diem',
          'full': 'danh_sach_day_du'
        }[type];
        
        a.download = `students_class${currentClassId}_${typeLabel}_${Date.now()}.xlsx`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        Swal.close();
        showSuccess('Xuất dữ liệu thành công!');
        
        // Close export modal
        closeExportModal();
      } else {
        // Response is JSON error
        const errorData = await response.json();
        throw new Error(errorData.error || errorData.message || 'Export failed');
      }
    } else {
      // HTTP error
      try {
        const errorData = await response.json();
        console.error('Server error:', errorData);
        throw new Error(errorData.details || errorData.error || 'Export failed');
      } catch (jsonError) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
    }
  } catch (error) {
    console.error('Error exporting:', error);
    Swal.fire({
      icon: 'error',
      title: 'Lỗi xuất dữ liệu',
      html: `
        <div style="text-align: left;">
          <p><strong>Lỗi:</strong> ${error.message}</p>
          <p style="color: #666; font-size: 14px;">Vui lòng kiểm tra:</p>
          <ul style="color: #666; font-size: 14px;">
            <li>Đã chọn lớp học chưa?</li>
            <li>Lớp học có học sinh không?</li>
            <li>Kết nối mạng có ổn định không?</li>
          </ul>
        </div>
      `,
      confirmButtonText: 'Đóng',
      confirmButtonColor: '#2E7D32'
    });
  }
}

async function showClassStudents(classId) {
  try {
    const response = await fetch(
      `api/get_class_students.php?class_id=${classId}`
    );
    if (!response.ok) {
      throw new Error("Network response was not ok");
    }

    const data = await response.json();

    if (data && data.success) {
      Swal.fire({
        title: "Danh sách học sinh",
        html: `
                    <div class="students-list-modal">
                        <div class="modal-actions students-actions">
                            <button class="btn btn-primary" onclick="openAddStudentsModal(${classId})">
                                <i class="fas fa-user-plus"></i> Thêm học sinh
                            </button>
                            <button class="btn btn-outline" onclick="openExportModal(${classId})">
                                <i class="fas fa-file-export"></i> Xuất danh sách
                            </button>
                        </div>
                        
                        ${
                          data.students && data.students.length > 0
                            ? `
                            <div class="students-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Họ và tên</th>
                                            <th>Email</th>
                                            <th>Ngày tham gia</th>
                                            <th>Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${data.students
                                          .map(
                                            (student) => `
                                            <tr>
                                                <td>${
                                                  student.user_id || ""
                                                }</td>
                                                <td>${student.name || ""}</td>
                                                <td>${student.email || ""}</td>
                                                <td>${
                                                  student.enrolled_at
                                                    ? new Date(
                                                        student.enrolled_at
                                                      ).toLocaleDateString(
                                                        "vi-VN"
                                                      )
                                                    : ""
                                                }</td>
                                                <td>${
                                                  student.status || "Đang học"
                                                }</td>
                                            </tr>
                                        `
                                          )
                                          .join("")}
                                    </tbody>
                                </table>
                            </div>
                        `
                            : '<p class="empty-state">Chưa có học sinh nào trong lớp</p>'
                        }
                    </div>
                `,
        width: 800,
        showCloseButton: true,
        showConfirmButton: false,
        customClass: {
          container: "students-list-modal-container",
          popup: "students-list-popup",
        },
      });
    } else {
      console.error("API Error:", data);
      throw new Error(data.message || "Không thể tải danh sách học sinh");
    }
  } catch (error) {
    console.error("Error in showClassStudents:", error);
    showError(error.message || "Có lỗi xảy ra khi tải danh sách học sinh");
  }
}

async function loadClassesForAssignment() {
  try {
    const response = await fetch("api/get_teacher_classes.php");
    const data = await response.json();

    if (data.success) {
      const classSelect = document.querySelector("#assignment-class");
      if (!classSelect) {
        console.error("Class select element not found");
        return;
      }

      // Clear existing options
      classSelect.innerHTML = '<option value="">Chọn lớp học</option>';

      // Add options for each class
      if (Array.isArray(data.classes)) {
        data.classes.forEach((classItem) => {
          const option = document.createElement("option");
          option.value = classItem.id;
          option.textContent = classItem.class_name || classItem.name || 'Không có tên';
          classSelect.appendChild(option);
        });
      }
    } else {
      showError("Không thể tải danh sách lớp học");
    }
  } catch (error) {
    console.error("Error loading classes:", error);
    showError("Có lỗi xảy ra khi tải danh sách lớp học");
  }
}

// Thêm hàm khởi tạo form
function initCreateAssignmentModal() {
  const form = document.getElementById("create-assignment-form");
  loadClassesForAssignment();

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
  });
}

// Helper function to create pagination
function createPagination(pagination, functionName) {
  if (pagination.total_pages <= 1) return "";

  let paginationHTML = '<div class="pagination">';

  // Previous button
  if (pagination.current_page > 1) {
    paginationHTML += `
            <button class="pagination-btn" onclick="${functionName}(${
      pagination.current_page - 1
    })">
                <i class="fas fa-chevron-left"></i>
            </button>
        `;
  }

  // Page numbers
  for (let i = 1; i <= pagination.total_pages; i++) {
    if (
      i === 1 ||
      i === pagination.total_pages ||
      (i >= pagination.current_page - 1 && i <= pagination.current_page + 1)
    ) {
      paginationHTML += `
                <button class="pagination-btn ${
                  i === pagination.current_page ? "active" : ""
                }" 
                    onclick="${functionName}(${i})">
                    ${i}
                </button>
            `;
    } else if (
      i === pagination.current_page - 2 ||
      i === pagination.current_page + 2
    ) {
      paginationHTML += '<span class="pagination-dots">...</span>';
    }
  }

  // Next button
  if (pagination.current_page < pagination.total_pages) {
    paginationHTML += `
            <button class="pagination-btn" onclick="${functionName}(${
      pagination.current_page + 1
    })">
                <i class="fas fa-chevron-right"></i>
            </button>
        `;
  }

  paginationHTML += "</div>";
  return paginationHTML;
}

// Đảm bảo formatTimeAgo được định nghĩa
function formatTimeAgo(date) {
  try {
    const now = new Date();
    const activityDate = new Date(date);
    
    // Validate date
    if (isNaN(activityDate.getTime())) {
      return 'Không xác định';
    }
    
    const diffMs = now - activityDate;
    
    // Handle future dates
    if (diffMs < 0) {
      return 'Sắp diễn ra';
    }
    
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Vừa xong';
    if (diffMins < 60) return `${diffMins} phút trước`;
    if (diffHours < 24) return `${diffHours} giờ trước`;
    if (diffDays < 7) return `${diffDays} ngày trước`;
    
    return activityDate.toLocaleDateString('vi-VN', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  } catch (error) {
    console.error('Error formatting time:', error);
    return 'Không xác định';
  }
}

// ...existing code...

// ==================== MODAL FUNCTIONS ====================

function showCreateClassModal() {
    const modal = document.getElementById('create-class-modal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Clear form
        const form = document.getElementById('create-class-form');
        if (form) form.reset();
        
        // Focus on first input
        setTimeout(() => {
            const firstInput = document.getElementById('class-name');
            if (firstInput) firstInput.focus();
        }, 300);
    }
}

function closeCreateClassModal() {
    const modal = document.getElementById('create-class-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.style.overflow = '';
        
        // Clear form
        const form = document.getElementById('create-class-form');
        if (form) form.reset();
    }
}

function showAddChapterModal(classId) {
    const modal = document.getElementById('add-chapter-modal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Set class ID
        const classIdInput = document.getElementById('chapter-class-id');
        if (classIdInput) classIdInput.value = classId;
        
        // Clear form
        const form = document.getElementById('add-chapter-form');
        if (form) form.reset();
        
        // Focus on first input
        setTimeout(() => {
            const firstInput = document.getElementById('chapter-title');
            if (firstInput) firstInput.focus();
        }, 300);
    }
}

function closeAddChapterModal() {
    const modal = document.getElementById('add-chapter-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.style.overflow = '';
        
        // Clear form
        const form = document.getElementById('add-chapter-form');
        if (form) form.reset();
    }
}

function showAddLessonModal(chapterId) {
    // Use SweetAlert2 for better UX (code already exists in current file)
    openAddLessonModal(chapterId);
}

function closeAddLessonModal() {
    Swal.close();
}

function showAddStudentsModal(classId) {
    currentClassId = classId;
    const modal = document.getElementById('add-students-modal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Clear inputs
        const idsInput = document.getElementById('student-ids');
        const emailsInput = document.getElementById('student-emails');
        if (idsInput) idsInput.value = '';
        if (emailsInput) emailsInput.value = '';
        
        // Activate first tab
        const firstTab = document.querySelector('.tab-btn');
        if (firstTab) firstTab.click();
    }
}

function closeAddStudentsModal() {
    const modal = document.getElementById('add-students-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.style.overflow = '';
        
        // Clear inputs
        const idsInput = document.getElementById('student-ids');
        const emailsInput = document.getElementById('student-emails');
        if (idsInput) idsInput.value = '';
        if (emailsInput) emailsInput.value = '';
    }
}

function showGradingModal(submissionId) {
    openGradingModal(submissionId);
}

function closeGradingModal() {
    Swal.close();
}

function showExportModal(classId) {
    currentClassId = classId;
    const modal = document.getElementById('export-modal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeExportModal() {
    const modal = document.getElementById('export-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

function removeFile() {
    const fileInput = document.getElementById('lesson-file');
    const filePreview = document.getElementById('file-preview');
    
    if (fileInput) fileInput.value = '';
    if (filePreview) {
        filePreview.style.display = 'none';
        filePreview.innerHTML = '';
    }
}

// ==================== CLOSE MODAL ON OUTSIDE CLICK ====================

document.addEventListener('DOMContentLoaded', () => {
    // Close modals when clicking outside
    const modals = document.querySelectorAll('.modal');
    
    modals.forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
                modal.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    });
    
    // Close modals with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show, .modal[style*="display: flex"]');
            if (openModal) {
                openModal.style.display = 'none';
                openModal.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
    });
});
window.showCreateClassModal = showCreateClassModal;
window.closeCreateClassModal = closeCreateClassModal;
window.openCreateAssignmentModal = openCreateAssignmentModal;
window.closeCreateAssignmentModal = closeCreateAssignmentModal;
window.showAddChapterModal = showAddChapterModal;
window.closeAddChapterModal = closeAddChapterModal;
window.showAddLessonModal = showAddLessonModal;
window.closeAddLessonModal = closeAddLessonModal;
window.showAddStudentsModal = showAddStudentsModal;
window.closeAddStudentsModal = closeAddStudentsModal;
window.handleAddStudents = handleAddStudents;
window.showGradingModal = showGradingModal;
window.closeGradingModal = closeGradingModal;
window.showExportModal = showExportModal;
window.closeExportModal = closeExportModal;
window.handleExport = handleExport;
window.removeFile = removeFile;

// Additional global functions
window.viewClass = viewClass;
window.editClass = editClass;
window.deleteClass = deleteClass;
window.editChapter = editChapter;
window.deleteChapter = deleteChapter;
window.editLesson = editLesson;
window.deleteLesson = deleteLesson;
window.viewLesson = viewLesson;
window.editAssignment = editAssignment;
window.deleteAssignment = deleteAssignment;
window.viewSubmissions = viewSubmissions;
window.viewSubmissionDetail = viewSubmissionDetail;
window.openGradingModal = openGradingModal;
window.showClassStudents = showClassStudents;
window.viewStudentDetail = viewStudentDetail;
window.loadRecentActivities = loadRecentActivities;
window.loadUpcomingDeadlines = loadUpcomingDeadlines;
window.loadStudentProducts = loadStudentProducts;
window.reviewStudentProduct = reviewStudentProduct;

console.log('✅ All modal functions and globals registered');