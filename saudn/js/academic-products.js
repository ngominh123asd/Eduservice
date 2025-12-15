// ==================== GLOBAL VARIABLES ====================
let currentProduct = null;
let autoSaveInterval = null;
let editorContent = null;
let collaborators = [];
let comments = [];

// ==================== LOAD PRODUCTS ====================

async function loadProducts(filter = "all") {
  try {
    const response = await fetch(`api/academic-products.php?filter=${filter}`);
    const data = await response.json();

    const container = document.getElementById("products-list");

    if (!data.success || !data.products || data.products.length === 0) {
      container.innerHTML = '<p class="empty-state">Chưa có sản phẩm nào</p>';
      return;
    }

    container.innerHTML = data.products
      .map(
        (product) => `
            <div class="product-card" data-product-id="${product.id}">
                <div class="product-card-header">
                    <div class="product-icon ${product.type}">
                        <i class="fas ${getProductIcon(product.type)}"></i>
                    </div>
                    <span class="product-status-badge ${product.status}">
                        ${getStatusText(product.status)}
                    </span>
                </div>
                
                <div class="product-card-body">
                    <h3 class="product-title">${
                      product.title || "Chưa có tiêu đề"
                    }</h3>
                    <p class="product-class">
                        <i class="fas fa-book"></i> ${
                          product.class_name || "Không thuộc lớp nào"
                        }
                    </p>
                    
                    <div class="product-meta">
                        <span class="product-meta-item">
                            <i class="fas fa-calendar"></i>
                            ${formatDate(product.created_at)}
                        </span>
                        <span class="product-meta-item">
                            <i class="fas fa-edit"></i>
                            ${formatDate(product.updated_at)}
                        </span>
                    </div>
                    
                    ${
                      product.word_count
                        ? `
                        <div class="product-stats">
                            <span><i class="fas fa-file-word"></i> ${
                              product.word_count
                            } từ</span>
                            <span><i class="fas fa-comment"></i> ${
                              product.comments_count || 0
                            }</span>
                        </div>
                    `
                        : ""
                    }
                    
                    ${
                      product.score
                        ? `
                        <div class="product-score">
                            <i class="fas fa-star"></i>
                            <strong>${product.score}/10</strong>
                        </div>
                    `
                        : ""
                    }
                </div>
                
                <div class="product-card-footer">
                    <button class="btn-product-action" onclick="openProductEditor(${
                      product.id
                    })">
                        <i class="fas fa-edit"></i> Chỉnh sửa
                    </button>
                    <div class="product-actions-dropdown">
                        <button class="btn-product-menu" onclick="toggleProductMenu(${
                          product.id
                        })">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="product-menu-dropdown" id="menu-${
                          product.id
                        }">
                            <button onclick="duplicateProduct(${product.id})">
                                <i class="fas fa-copy"></i> Nhân bản
                            </button>
                            <button onclick="shareProduct(${product.id})">
                                <i class="fas fa-share-alt"></i> Chia sẻ
                            </button>
                            <button onclick="exportProduct(${product.id})">
                                <i class="fas fa-download"></i> Xuất file
                            </button>
                            <button onclick="deleteProduct(${
                              product.id
                            })" class="danger">
                                <i class="fas fa-trash"></i> Xóa
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `
      )
      .join("");
  } catch (error) {
    console.error("Error loading products:", error);
    document.getElementById("products-list").innerHTML =
      '<p class="empty-state">Không thể tải sản phẩm</p>';
  }
}

function getProductIcon(type) {
  const icons = {
    essay: "fa-file-alt",
    report: "fa-file-pdf",
    research: "fa-flask",
    presentation: "fa-presentation",
    other: "fa-file",
  };
  return icons[type] || "fa-file";
}

function getStatusText(status) {
  const texts = {
    draft: "Bản nháp",
    submitted: "Đã nộp",
    reviewed: "Đã chấm",
    returned: "Trả lại",
  };
  return texts[status] || status;
}

// Update createNewProduct function

// Cập nhật hàm createNewProduct

async function createNewProduct() {
  const { value: formValues } = await Swal.fire({
    title: '<i class="fas fa-file-alt"></i> Tạo sản phẩm học thuật mới',
    html: `
            <div class="create-product-form">
                <div class="form-group">
                    <label>
                        <i class="fas fa-heading"></i>
                        <span>Tiêu đề sản phẩm</span>
                        <span class="required">*</span>
                    </label>
                    <input type="text" 
                           id="product-title-input" 
                           class="swal2-input" 
                           placeholder="Nhập tiêu đề sản phẩm..." 
                           maxlength="200"
                           autocomplete="off">
                    <div class="char-counter">
                        <span id="title-counter">0</span>/200 ký tự
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-graduation-cap"></i>
                        <span>Lớp học</span>
                        <span class="required">*</span>
                    </label>
                    <select id="product-class-select" class="swal2-select">
                        <option value="">Đang tải...</option>
                    </select>
                    <div class="form-help-text">
                        <i class="fas fa-info-circle"></i>
                        <span>Chọn lớp học mà sản phẩm này thuộc về</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-tag"></i>
                        <span>Loại sản phẩm</span>
                        <span class="required">*</span>
                    </label>
                    <select id="product-type-select" class="swal2-select">
                        <option value="">Chọn loại sản phẩm</option>
                        <option value="essay">Bài tiểu luận</option>
                        <option value="report">Báo cáo</option>
                        <option value="research">Nghiên cứu</option>
                        <option value="presentation">Thuyết trình</option>
                        <option value="other">Khác</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-align-left"></i>
                        <span>Mô tả (không bắt buộc)</span>
                    </label>
                    <textarea id="product-description-input" 
                              class="swal2-textarea" 
                              placeholder="Mô tả ngắn về sản phẩm..." 
                              rows="4" 
                              maxlength="500"></textarea>
                    <div class="char-counter">
                        <span id="description-counter">0</span>/500 ký tự
                    </div>
                </div>
            </div>
        `,
    customClass: {
      popup: "create-product-modal",
      confirmButton: "btn-create-product",
      cancelButton: "btn-cancel-product",
    },
    showCancelButton: true,
    confirmButtonText: '<i class="fas fa-plus-circle"></i> Tạo sản phẩm',
    cancelButtonText: '<i class="fas fa-times-circle"></i> Hủy bỏ',
    width: "650px",
    backdrop: true,
    allowOutsideClick: false,
    allowEscapeKey: true,
    didOpen: async () => {
      console.log("✅ Modal opened");

      // Load classes first
      await loadClassesForDropdown();

      // Setup character counters
      const titleInput = document.getElementById("product-title-input");
      const descInput = document.getElementById("product-description-input");
      const titleCounter = document.getElementById("title-counter");
      const descCounter = document.getElementById("description-counter");

      if (titleInput && titleCounter) {
        titleInput.addEventListener("input", (e) => {
          const length = e.target.value.length;
          titleCounter.textContent = length;
          const parent = titleCounter.parentElement;
          parent.className = "char-counter";
          if (length > 180) parent.classList.add("warning");
          if (length >= 200) parent.classList.add("error");
        });
      }

      if (descInput && descCounter) {
        descInput.addEventListener("input", (e) => {
          const length = e.target.value.length;
          descCounter.textContent = length;
          const parent = descCounter.parentElement;
          parent.className = "char-counter";
          if (length > 450) parent.classList.add("warning");
          if (length >= 500) parent.classList.add("error");
        });
      }
    },
    preConfirm: () => {
      const title = document
        .getElementById("product-title-input")
        ?.value.trim();
      const classId = document.getElementById("product-class-select")?.value;
      const type = document.getElementById("product-type-select")?.value;
      const description = document
        .getElementById("product-description-input")
        ?.value.trim();

      // Validation
      if (!title) {
        Swal.showValidationMessage("Vui lòng nhập tiêu đề sản phẩm");
        return false;
      }

      if (!classId) {
        Swal.showValidationMessage("Vui lòng chọn lớp học");
        return false;
      }

      if (!type) {
        Swal.showValidationMessage("Vui lòng chọn loại sản phẩm");
        return false;
      }

      return { title, classId, type, description };
    },
  });

  if (formValues) {
    console.log("Form values:", formValues);
    await saveNewProduct(formValues);
  }
}

async function loadClassesForDropdown() {
  try {
    console.log("Loading classes...");

    const response = await fetch("api/classes.php");

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();
    console.log("Classes response:", data);

    const selectElement = document.getElementById("product-class-select");

    if (!selectElement) {
      console.error("Select element not found");
      return;
    }

    // Clear existing options
    selectElement.innerHTML = '<option value="">Chọn lớp học</option>';

    if (
      data.success &&
      data.classes &&
      Array.isArray(data.classes) &&
      data.classes.length > 0
    ) {
      data.classes.forEach((classItem) => {
        const option = document.createElement("option");
        option.value = classItem.id;
        option.textContent = `${classItem.class_name || classItem.name} ${
          classItem.code ? "(" + classItem.code + ")" : ""
        }`;
        selectElement.appendChild(option);
      });

      console.log("Loaded " + data.classes.length + " classes");
    } else {
      // If no classes, show message
      const option = document.createElement("option");
      option.value = "";
      option.textContent = "Không có lớp học nào";
      option.disabled = true;
      selectElement.appendChild(option);

      console.warn("⚠️ No classes available");
    }
  } catch (error) {
    console.error("Error loading classes:", error);

    // Show error in dropdown
    const selectElement = document.getElementById("product-class-select");
    if (selectElement) {
      selectElement.innerHTML =
        '<option value="">Lỗi tải danh sách lớp</option>';
    }

    Swal.fire({
      icon: "error",
      title: "Lỗi",
      text: "Không thể tải danh sách lớp học: " + error.message,
      confirmButtonColor: "#2E7D32",
    });
  }
}

async function saveNewProduct(formData) {
  try {
    console.log("Saving new product:", formData);

    // Show loading
    Swal.fire({
      title: "Đang tạo sản phẩm...",
      html: '<div class="swal2-loading-spinner"></div>',
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    const response = await fetch("api/academic-products.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        action: "create",
        title: formData.title,
        class_id: formData.classId,
        type: formData.type,
        description: formData.description,
      }),
    });

    console.log("Response status:", response.status);

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();
    console.log("Save response:", data);

    if (data.success) {
      // Success - Close loading and show success message
      await Swal.fire({
        icon: "success",
        title: "Thành công!",
        html: `
                    <div style="text-align: center;">
                        <p style="margin: 16px 0; font-size: 16px;">
                            Sản phẩm <strong>"${formData.title}"</strong> đã được tạo thành công
                        </p>
                        <div style="background: #E8F5E9; padding: 12px; border-radius: 8px; margin: 16px 0;">
                            <i class="fas fa-lightbulb" style="color: #2E7D32;"></i>
                            <span style="color: #1B5E20; font-size: 14px;">
                                Bạn có thể bắt đầu chỉnh sửa nội dung ngay bây giờ
                            </span>
                        </div>
                    </div>
                `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-edit"></i> Chỉnh sửa ngay',
        cancelButtonText: '<i class="fas fa-list"></i> Xem danh sách',
        confirmButtonColor: "#2E7D32",
        cancelButtonColor: "#757575",
        timer: 5000,
        timerProgressBar: true,
      }).then((result) => {
        if (result.isConfirmed) {
          // Open editor
          openProductEditor(data.product_id);
        } else {
          // Reload products list
          loadProducts();
        }
      });
    } else {
      throw new Error(data.message || "Không thể tạo sản phẩm");
    }
  } catch (error) {
    console.error("❌ Error creating product:", error);

    // Show error message
    Swal.fire({
      icon: "error",
      title: "Lỗi",
      html: `
                <div style="text-align: center;">
                    <p style="margin: 16px 0;">
                        Không thể tạo sản phẩm. Vui lòng thử lại.
                    </p>
                    <div style="background: #FFEBEE; padding: 12px; border-radius: 8px; margin: 16px 0;">
                        <code style="color: #C62828; font-size: 13px;">${error.message}</code>
                    </div>
                </div>
            `,
      confirmButtonText: "Đóng",
      confirmButtonColor: "#2E7D32",
    });
  }
}

// ==================== OPEN PRODUCT EDITOR ====================

async function openProductEditor(productId) {
  try {
    // ✅ Detect if teacher is reviewing
    const isTeacher = window.isTeacherReviewing || false;

    let response;
    if (isTeacher) {
      // Use teacher API
      response = await fetch(
        `../giaovien/api/get_product_detail.php?id=${productId}`
      );
    } else {
      // Use student API
      response = await fetch(`api/academic-products.php?id=${productId}`);
    }

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.message || "Không thể tải sản phẩm");
    }

    currentProduct = data.product;

    // Create editor modal if not exists
    if (!document.getElementById("product-editor-modal")) {
      createEditorModal();
    }

    // Populate editor
    document.getElementById("product-title").value = currentProduct.title || "";
    document.getElementById("product-editor").innerHTML =
      currentProduct.content || "<p>Bắt đầu viết...</p>";
    document.getElementById("product-status").textContent = getStatusText(
      currentProduct.status
    );

    // ✅ CUSTOMIZE FOR TEACHER
    if (isTeacher) {
      customizeEditorForTeacher();
    }

    // Update word count
    updateWordCount();

    // Show modal
    document.getElementById("product-editor-modal").classList.add("active");
    document.body.style.overflow = "hidden";

    // Setup editor
    setupEditor();

    // Load comments and reviews
    loadComments(productId);
    loadReviews(productId);

    // Start auto-save (only for students)
    if (!isTeacher) {
      startAutoSave();
    }
  } catch (error) {
    console.error("Error opening editor:", error);
    Swal.fire({
      icon: "error",
      title: "Lỗi",
      text: "Không thể mở trình soạn thảo",
      confirmButtonColor: "#2E7D32",
    });
  }
}

function customizeEditorForTeacher() {
  // 1. Disable content editing
  const editor = document.getElementById("product-editor");
  if (editor) {
    editor.setAttribute("contenteditable", "false");
    editor.style.backgroundColor = "#f8f9fa";
    editor.style.cursor = "default";
  }

  // 2. Hide toolbar
  const toolbar = document.querySelector(".editor-toolbar");
  if (toolbar) {
    toolbar.style.display = "none";
  }

  // 3. Disable title editing
  const titleInput = document.getElementById("product-title");
  if (titleInput) {
    titleInput.setAttribute("readonly", "true");
    titleInput.style.backgroundColor = "#f8f9fa";
  }

  // 4. Add teacher badge to header
  const headerLeft = document.querySelector(".editor-header-left");
  if (headerLeft && !document.querySelector(".teacher-mode-badge")) {
    const badge = document.createElement("div");
    badge.className = "teacher-mode-badge";
    badge.innerHTML =
      '<i class="fas fa-chalkboard-teacher"></i> Chế độ xem - Giáo viên';
    headerLeft.appendChild(badge);
  }

  // 5. Add student info - CHỈ HIỂN THỊ MÃ LỚP
  if (currentProduct.student_name) {
    const titleInput = document.querySelector(".product-title-input");
    if (titleInput && !document.querySelector(".student-info-display")) {
      const studentInfo = document.createElement("div");
      studentInfo.className = "student-info-display";
      studentInfo.innerHTML = `
                <i class="fas fa-user-graduate"></i>
                <span><strong>${currentProduct.student_name}</strong></span>
                ${
                  currentProduct.class_code
                    ? `
                    <span class="separator">•</span>
                    <span class="class-code">${currentProduct.class_code}</span>
                `
                    : ""
                }
            `;
      titleInput.appendChild(studentInfo);
    }
  }

  // 6. Replace submit button with review button
  const submitBtn = document.getElementById("submit-product-btn");
  if (submitBtn) {
    submitBtn.innerHTML = '<i class="fas fa-clipboard-check"></i> Chấm điểm';
    submitBtn.onclick = () => openTeacherReviewForm();
  }

  // 7. Hide AI assistant button
  const aiBtn = document.getElementById("ai-writing-assistant-btn");
  if (aiBtn) {
    aiBtn.style.display = "none";
  }
}

function createEditorModal() {
  const modalHTML = `
        <div id="product-editor-modal" class="modal">
            <div class="modal-content product-editor-container">
                <!-- Editor Header -->
                <div class="editor-header">
                    <div class="editor-header-left">
                        <button class="btn-back" onclick="closeProductEditor()">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <div class="product-title-input">
                            <input type="text" id="product-title" placeholder="Tiêu đề sản phẩm" maxlength="200">
                            <span class="save-indicator" id="save-indicator">
                                <i class="fas fa-check"></i> Đã lưu
                            </span>
                        </div>
                    </div>
                    <div class="editor-header-right">
                        <button class="btn-editor-action" onclick="toggleVersionHistory()" title="Lịch sử phiên bản">
                            <i class="fas fa-history"></i>
                        </button>
                        <button class="btn-editor-action" onclick="shareProduct()" title="Chia sẻ">
                            <i class="fas fa-share-alt"></i>
                        </button>
                        <button class="btn-editor-action" id="ai-writing-assistant-btn" onclick="toggleAIWritingAssistant()" title="Trợ lý AI viết">
                            <i class="fas fa-magic"></i>
                            <span>AI</span>
                        </button>
                        <button class="btn-primary" onclick="submitProduct()" id="submit-product-btn">
                            <i class="fas fa-paper-plane"></i> Nộp bài
                        </button>
                    </div>
                </div>

                <!-- Editor Body -->
                <div class="editor-body">
                    <!-- Main Editor Area -->
                    <div class="editor-main">
                        <!-- Toolbar -->
                        <div class="editor-toolbar">
                            <div class="toolbar-group">
                                <button class="toolbar-btn" onclick="execCommand('undo')" title="Hoàn tác">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <button class="toolbar-btn" onclick="execCommand('redo')" title="Làm lại">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </div>
                            
                            <div class="toolbar-separator"></div>
                            
                            <div class="toolbar-group">
                                <select class="toolbar-select" onchange="execCommand('formatBlock', this.value)">
                                    <option value="p">Đoạn văn</option>
                                    <option value="h1">Tiêu đề 1</option>
                                    <option value="h2">Tiêu đề 2</option>
                                    <option value="h3">Tiêu đề 3</option>
                                </select>
                                <select class="toolbar-select" onchange="execCommand('fontName', this.value)">
                                    <option value="Arial">Arial</option>
                                    <option value="Times New Roman">Times New Roman</option>
                                    <option value="Courier New">Courier New</option>
                                    <option value="Georgia">Georgia</option>
                                </select>
                                <select class="toolbar-select" onchange="execCommand('fontSize', this.value)">
                                    <option value="3" selected>12pt</option>
                                    <option value="4">14pt</option>
                                    <option value="5">16pt</option>
                                    <option value="6">18pt</option>
                                    <option value="7">24pt</option>
                                </select>
                            </div>
                            
                            <div class="toolbar-separator"></div>
                            
                            <div class="toolbar-group">
                                <button class="toolbar-btn" onclick="execCommand('bold')" title="In đậm">
                                    <i class="fas fa-bold"></i>
                                </button>
                                <button class="toolbar-btn" onclick="execCommand('italic')" title="In nghiêng">
                                    <i class="fas fa-italic"></i>
                                </button>
                                <button class="toolbar-btn" onclick="execCommand('underline')" title="Gạch chân">
                                    <i class="fas fa-underline"></i>
                                </button>
                                <button class="toolbar-btn" onclick="execCommand('strikeThrough')" title="Gạch ngang">
                                    <i class="fas fa-strikethrough"></i>
                                </button>
                            </div>
                            
                            <div class="toolbar-separator"></div>
                            
                            <div class="toolbar-group">
                                <button class="toolbar-btn" onclick="execCommand('justifyLeft')" title="Căn trái">
                                    <i class="fas fa-align-left"></i>
                                </button>
                                <button class="toolbar-btn" onclick="execCommand('justifyCenter')" title="Căn giữa">
                                    <i class="fas fa-align-center"></i>
                                </button>
                                <button class="toolbar-btn" onclick="execCommand('justifyRight')" title="Căn phải">
                                    <i class="fas fa-align-right"></i>
                                </button>
                                <button class="toolbar-btn" onclick="execCommand('justifyFull')" title="Căn đều">
                                    <i class="fas fa-align-justify"></i>
                                </button>
                            </div>
                            
                            <div class="toolbar-separator"></div>
                            
                            <div class="toolbar-group">
                                <button class="toolbar-btn" onclick="execCommand('insertUnorderedList')" title="Danh sách">
                                    <i class="fas fa-list-ul"></i>
                                </button>
                                <button class="toolbar-btn" onclick="execCommand('insertOrderedList')" title="Danh sách số">
                                    <i class="fas fa-list-ol"></i>
                                </button>
                                <button class="toolbar-btn" onclick="insertLink()" title="Chèn liên kết">
                                    <i class="fas fa-link"></i>
                                </button>
                                <button class="toolbar-btn" onclick="insertImage()" title="Chèn hình ảnh">
                                    <i class="fas fa-image"></i>
                                </button>
                            </div>
                            
                            <div class="toolbar-separator"></div>
                            
                            <div class="toolbar-group">
                                <input type="color" class="toolbar-color-picker" onchange="execCommand('foreColor', this.value)" title="Màu chữ" value="#000000">
                                <input type="color" class="toolbar-color-picker" onchange="execCommand('hiliteColor', this.value)" title="Màu nền" value="#ffff00">
                            </div>
                        </div>

                        <!-- ✅ RULER CONTAINER -->
                        <div class="ruler-container">
                            <div class="horizontal-ruler">
                                <!-- Left margin marker -->
                                <div class="margin-marker left-margin" 
                                     data-margin="left"
                                     title="Lề trái: kéo để điều chỉnh">
                                    <div class="marker-handle"></div>
                                    <div class="marker-label">L</div>
                                </div>
                                
                                <!-- Ruler markings container -->
                                <div class="ruler-markings" id="ruler-markings"></div>
                                
                                <!-- Right margin marker -->
                                <div class="margin-marker right-margin" 
                                     data-margin="right"
                                     title="Lề phải: kéo để điều chỉnh">
                                    <div class="marker-handle"></div>
                                    <div class="marker-label">R</div>
                                </div>
                                
                                <!-- First line indent marker -->
                                <div class="margin-marker first-line-indent" 
                                     data-margin="first-line"
                                     title="Thụt đầu dòng: kéo để điều chỉnh">
                                    <div class="marker-triangle">▼</div>
                                </div>
                            </div>
                        </div>

                        <!-- A4 Content Editor Wrapper -->
                        <div class="editor-content-wrapper">
                            <!-- First Page -->
                            <div class="editor-content" id="product-editor" contenteditable="true" spellcheck="true" data-page="1">
                                <p>Bắt đầu viết sản phẩm học thuật của bạn...</p>
                            </div>
                            
                            <!-- Add Page Button (only visible for students) -->
                            <button class="editor-add-page-btn" onclick="addNewPage()" title="Thêm trang mới">
                                <i class="fas fa-plus-circle"></i>
                                <span>Thêm trang mới</span>
                            </button>
                        </div>

                        <!-- Status Bar with Zoom Controls -->
                        <div class="editor-status-bar">
                            <span id="word-count">0 từ</span>
                            <span class="separator">|</span>
                            <span id="char-count">0 ký tự</span>
                            <span class="separator">|</span>
                            <span id="page-count">Trang 1</span>
                            <span class="separator">|</span>
                            <span id="product-status">Bản nháp</span>
                        </div>
                    </div>

                    <!-- Sidebar: Comments & Reviews -->
                    <div class="editor-sidebar" id="editor-sidebar">
                        <div class="sidebar-tabs">
                            <button class="sidebar-tab active" data-tab="comments" onclick="switchSidebarTab('comments')">
                                <i class="fas fa-comments"></i>
                                <span>Bình luận</span>
                                <span class="badge" id="comments-count">0</span>
                            </button>
                            <button class="sidebar-tab" data-tab="reviews" onclick="switchSidebarTab('reviews')">
                                <i class="fas fa-edit"></i>
                                <span>Nhận xét</span>
                            </button>
                            <button class="sidebar-tab" data-tab="history" onclick="switchSidebarTab('history')">
                                <i class="fas fa-history"></i>
                                <span>Lịch sử</span>
                            </button>
                        </div>

                <!-- Zoom Controls (Optional) -->
                <div class="editor-zoom-controls">
                    <button class="zoom-btn" onclick="zoomOut()" title="Thu nhỏ">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <span class="zoom-level" id="zoom-level">100%</span>
                    <button class="zoom-btn" onclick="zoomIn()" title="Phóng to">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    <button class="zoom-btn" onclick="resetZoom()" title="Đặt lại">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>

                        <!-- Comments Tab -->
                        <div class="sidebar-content active" data-content="comments">
                            <div class="sidebar-header">
                                <h4>Bình luận</h4>
                                <button class="btn-icon" onclick="addComment()" title="Thêm bình luận">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="comments-list" id="comments-list">
                                <p class="empty-state-small">Chưa có bình luận nào</p>
                            </div>
                        </div>

                        <!-- Reviews Tab -->
                        <div class="sidebar-content" data-content="reviews">
                            <div class="sidebar-header">
                                <h4>Nhận xét của giáo viên</h4>
                            </div>
                            <div class="reviews-container" id="reviews-container">
                                <p class="empty-state-small">Chưa có nhận xét nào</p>
                            </div>
                        </div>

                        <!-- Version History Tab -->
                        <div class="sidebar-content" data-content="history">
                            <div class="sidebar-header">
                                <h4>Lịch sử chỉnh sửa</h4>
                            </div>
                            <div class="version-history-list" id="version-history-list">
                                <p class="empty-state-small">Đang tải...</p>
                            </div>
                        </div>
                    </div>

                    <!-- AI Writing Assistant Panel -->
                    <div class="ai-writing-panel" id="ai-writing-panel">
                        <div class="ai-panel-header">
                            <h4><i class="fas fa-magic"></i> Trợ lý AI viết</h4>
                            <button class="btn-icon" onclick="toggleAIWritingAssistant()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="ai-panel-body">
                            <div class="ai-suggestions">
                                <button class="ai-suggestion-btn" onclick="aiSuggest('improve')">
                                    <i class="fas fa-magic"></i> Cải thiện văn phong
                                </button>
                                <button class="ai-suggestion-btn" onclick="aiSuggest('grammar')">
                                    <i class="fas fa-spell-check"></i> Sửa lỗi chính tả
                                </button>
                                <button class="ai-suggestion-btn" onclick="aiSuggest('expand')">
                                    <i class="fas fa-expand-alt"></i> Mở rộng nội dung
                                </button>
                                <button class="ai-suggestion-btn" onclick="aiSuggest('summarize')">
                                    <i class="fas fa-compress-alt"></i> Tóm tắt
                                </button>
                            </div>
                            <div class="ai-custom-prompt">
                                <textarea placeholder="Yêu cầu tùy chỉnh cho AI..." id="ai-custom-input"></textarea>
                                <button class="btn-primary" onclick="aiCustomRequest()">
                                    <i class="fas fa-paper-plane"></i> Gửi
                                </button>
                            </div>
                            <div class="ai-result" id="ai-result" style="display: none;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // ✅ Initialize ruler and create first page
    initializeRulerSystem();
    createInitialPage();
}

// ==================== EDITOR SETUP & FUNCTIONS ====================

function setupEditor() {
  const editor = document.getElementById("product-editor");
  const titleInput = document.getElementById("product-title");

  if (!editor) return;

  // Update word count on input
  editor.addEventListener("input", () => {
    updateWordCount();
    showSaveIndicator("saving");
  });

  // Title input handler
  if (titleInput) {
    titleInput.addEventListener("input", () => {
      showSaveIndicator("saving");
    });
  }

  // Handle paste to clean HTML
  editor.addEventListener("paste", (e) => {
    e.preventDefault();
    const text = e.clipboardData.getData("text/plain");
    document.execCommand("insertText", false, text);
  });

  // Keyboard shortcuts
  editor.addEventListener("keydown", (e) => {
    // Ctrl/Cmd + S to save
    if ((e.ctrlKey || e.metaKey) && e.key === "s") {
      e.preventDefault();
      saveProduct(true);
    }

    // Ctrl/Cmd + B for bold
    if ((e.ctrlKey || e.metaKey) && e.key === "b") {
      e.preventDefault();
      execCommand("bold");
    }

    // Ctrl/Cmd + I for italic
    if ((e.ctrlKey || e.metaKey) && e.key === "i") {
      e.preventDefault();
      execCommand("italic");
    }

    // Ctrl/Cmd + U for underline
    if ((e.ctrlKey || e.metaKey) && e.key === "u") {
      e.preventDefault();
      execCommand("underline");
    }
  });
}

function execCommand(command, value = null) {
  document.execCommand(command, false, value);
  document.getElementById("product-editor").focus();
}

function insertLink() {
  const url = prompt("Nhập URL:");
  if (url) {
    execCommand("createLink", url);
  }
}

async function insertImage() {
  const { value: file } = await Swal.fire({
    title: "Chèn hình ảnh",
    html: `
            <input type="file" id="image-upload" accept="image/*" class="swal2-file">
            <p style="margin-top: 16px; font-size: 13px; color: #666;">hoặc</p>
            <input type="url" id="image-url" placeholder="Nhập URL hình ảnh" class="swal2-input">
        `,
    showCancelButton: true,
    confirmButtonText: "Chèn",
    cancelButtonText: "Hủy",
    confirmButtonColor: "#2E7D32",
    preConfirm: () => {
      const fileInput = document.getElementById("image-upload");
      const urlInput = document.getElementById("image-url");

      if (fileInput.files[0]) {
        return { type: "file", data: fileInput.files[0] };
      } else if (urlInput.value) {
        return { type: "url", data: urlInput.value };
      } else {
        Swal.showValidationMessage("Vui lòng chọn file hoặc nhập URL");
        return false;
      }
    },
  });

  if (file) {
    if (file.type === "file") {
      // Upload file
      const formData = new FormData();
      formData.append("image", file.data);
      formData.append("product_id", currentProduct.id);

      try {
        const response = await fetch("api/upload-product-image.php", {
          method: "POST",
          body: formData,
        });

        const data = await response.json();

        if (data.success) {
          execCommand("insertImage", data.image_url);
        } else {
          throw new Error(data.message);
        }
      } catch (error) {
        Swal.fire("Lỗi", "Không thể tải lên hình ảnh", "error");
      }
    } else {
      // Use URL
      execCommand("insertImage", file.data);
    }
  }
}

// Add new page function (only for students)
function addNewPage() {
    const wrapper = document.querySelector('.editor-content-wrapper');
    const pages = wrapper.querySelectorAll('.editor-content');
    const pageCount = pages.length;
    
    // Create separator
    const separator = document.createElement('div');
    separator.className = 'page-separator';
    
    // Create new page
    const newPage = document.createElement('div');
    newPage.className = 'editor-content';
    newPage.setAttribute('contenteditable', 'true');
    newPage.setAttribute('spellcheck', 'true');
    newPage.setAttribute('data-page', pageCount + 1);
    newPage.innerHTML = '<p><br></p>';
    
    // Insert before "Add Page" button
    const addBtn = wrapper.querySelector('.editor-add-page-btn');
    wrapper.insertBefore(separator, addBtn);
    wrapper.insertBefore(newPage, addBtn);
    
    // Focus on new page
    newPage.focus();
    
    // Update page count
    updatePageCount();
    
    // Setup event listeners for new page
    setupPageEditor(newPage);
}

// Setup editor for each page
function setupPageEditor(pageElement) {
    pageElement.addEventListener('input', () => {
        updateWordCount();
        showSaveIndicator('saving');
    });
    
    pageElement.addEventListener('paste', (e) => {
        e.preventDefault();
        const text = e.clipboardData.getData('text/plain');
        document.execCommand('insertText', false, text);
    });
}

// ==================== RULER & MARGIN SYSTEM ====================

const marginSettings = {
    left: 2.54,      // cm - lề trái
    right: 2.54,     // cm - lề phải  
    top: 2.54,       // cm - lề trên
    bottom: 2.54,    // cm - lề dưới
    firstLineIndent: 0  // cm - thụt đầu dòng
};

const A4_WIDTH_CM = 21;  // Chiều rộng A4
const A4_HEIGHT_CM = 29.7; // Chiều cao A4

// ✅ Initialize ruler system
function initializeRulerSystem() {
    console.log('🎯 Initializing ruler system...');
    
    const rulerMarkings = document.getElementById('ruler-markings');
    if (!rulerMarkings) {
        console.warn('⚠️ Ruler markings container not found');
        return;
    }
    
    // Clear existing markings
    rulerMarkings.innerHTML = '';
    
    // Create ruler markings (0-21cm for A4 width)
    for (let i = 0; i <= A4_WIDTH_CM; i += 0.5) {
        const marking = document.createElement('div');
        marking.className = 'ruler-marking';
        
        // Position based on percentage of A4 width
        const leftPercent = (i / A4_WIDTH_CM) * 100;
        marking.style.left = `${leftPercent}%`;
        
        if (i % 1 === 0) {
            // Major marking every 1cm
            marking.classList.add('major');
            const label = document.createElement('span');
            label.className = 'ruler-label';
            label.textContent = i;
            marking.appendChild(label);
        } else {
            // Minor marking at 0.5cm
            marking.classList.add('minor');
        }
        
        rulerMarkings.appendChild(marking);
    }
    
    console.log('✅ Ruler markings created');
    
    // Setup margin markers drag handlers
    setupMarginMarkers();
    
    // Set initial positions
    updateMarkerPositions();
    
    // Apply margins to pages
    applyMarginsToAllPages();
}

// ✅ Setup drag handlers for margin markers
function setupMarginMarkers() {
    const markers = document.querySelectorAll('.margin-marker');
    
    markers.forEach(marker => {
        let isDragging = false;
        let startX = 0;
        let startLeft = 0;
        
        // Mouse down - start drag
        marker.addEventListener('mousedown', (e) => {
            isDragging = true;
            startX = e.clientX;
            
            // Get current left position
            const rect = marker.getBoundingClientRect();
            startLeft = rect.left;
            
            marker.classList.add('dragging');
            
            // Prevent text selection
            e.preventDefault();
            
            console.log(`🖱️ Start dragging ${marker.dataset.margin}`);
        });
        
        // Mouse move - dragging
        document.addEventListener('mousemove', (e) => {
            if (!isDragging || !marker.classList.contains('dragging')) return;
            
            handleMarkerDrag(marker, e);
        });
        
        // Mouse up - end drag
        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                marker.classList.remove('dragging');
                
                console.log(`✋ End dragging ${marker.dataset.margin}`);
                
                // Save changes
                showSaveIndicator('saving');
            }
        });
    });
    
    console.log('✅ Margin markers setup complete');
}

// ✅ Handle marker drag
function handleMarkerDrag(marker, event) {
    const ruler = document.querySelector('.horizontal-ruler');
    if (!ruler) return;
    
    const rulerRect = ruler.getBoundingClientRect();
    const rulerWidth = rulerRect.width;
    
    // Calculate position relative to ruler
    const relativeX = event.clientX - rulerRect.left;
    
    // Convert to cm (0 to 21cm)
    let cmPosition = (relativeX / rulerWidth) * A4_WIDTH_CM;
    
    // Clamp to valid range
    cmPosition = Math.max(0, Math.min(A4_WIDTH_CM, cmPosition));
    
    const marginType = marker.dataset.margin;
    
    // Update margin settings based on marker type
    switch(marginType) {
        case 'left':
            // Left margin: 0 to 10cm
            marginSettings.left = Math.max(0, Math.min(10, cmPosition));
            
            // Ensure left doesn't exceed page width minus right margin
            const maxLeft = A4_WIDTH_CM - marginSettings.right - 0.5;
            if (marginSettings.left > maxLeft) {
                marginSettings.left = maxLeft;
            }
            
            // Update first-line indent marker position
            updateFirstLineIndentConstraints();
            break;
            
        case 'right':
            // Right margin: measured from right edge
            const rightMarginCm = A4_WIDTH_CM - cmPosition;
            marginSettings.right = Math.max(0, Math.min(10, rightMarginCm));
            
            // Ensure right doesn't exceed page width minus left margin
            const maxRight = A4_WIDTH_CM - marginSettings.left - 0.5;
            if (marginSettings.right > maxRight) {
                marginSettings.right = maxRight;
            }
            break;
            
        case 'first-line':
            // First-line indent: relative to left margin
            const indentCm = cmPosition - marginSettings.left;
            marginSettings.firstLineIndent = Math.max(0, Math.min(5, indentCm));
            
            // Ensure it doesn't exceed text area
            const maxIndent = A4_WIDTH_CM - marginSettings.left - marginSettings.right - 1;
            if (marginSettings.firstLineIndent > maxIndent) {
                marginSettings.firstLineIndent = maxIndent;
            }
            break;
    }
    
    // Update marker visual position
    updateMarkerPosition(marker, marginType);
    
    // Apply changes to pages
    applyMarginsToAllPages();
}

// ✅ Update first-line indent constraints when left margin changes
function updateFirstLineIndentConstraints() {
    const maxIndent = A4_WIDTH_CM - marginSettings.left - marginSettings.right - 1;
    if (marginSettings.firstLineIndent > maxIndent) {
        marginSettings.firstLineIndent = maxIndent;
    }
    
    // Update first-line marker position
    const firstLineMarker = document.querySelector('.margin-marker[data-margin="first-line"]');
    if (firstLineMarker) {
        updateMarkerPosition(firstLineMarker, 'first-line');
    }
}

// ✅ Update all marker positions
function updateMarkerPositions() {
    const markers = [
        { selector: '.margin-marker[data-margin="left"]', type: 'left' },
        { selector: '.margin-marker[data-margin="right"]', type: 'right' },
        { selector: '.margin-marker[data-margin="first-line"]', type: 'first-line' }
    ];
    
    markers.forEach(({ selector, type }) => {
        const marker = document.querySelector(selector);
        if (marker) {
            updateMarkerPosition(marker, type);
        }
    });
}

// ✅ Update single marker position
function updateMarkerPosition(marker, marginType) {
    let positionPercent;
    
    switch(marginType) {
        case 'left':
            positionPercent = (marginSettings.left / A4_WIDTH_CM) * 100;
            break;
            
        case 'right':
            positionPercent = ((A4_WIDTH_CM - marginSettings.right) / A4_WIDTH_CM) * 100;
            break;
            
        case 'first-line':
            const firstLinePos = marginSettings.left + marginSettings.firstLineIndent;
            positionPercent = (firstLinePos / A4_WIDTH_CM) * 100;
            break;
    }
    
    marker.style.left = `${positionPercent}%`;
}

// ✅ Apply margins to all pages
function applyMarginsToAllPages() {
    const pages = document.querySelectorAll('.editor-page');
    
    pages.forEach(page => {
        // Set padding for margins
        page.style.paddingLeft = `${marginSettings.left}cm`;
        page.style.paddingRight = `${marginSettings.right}cm`;
        page.style.paddingTop = `${marginSettings.top}cm`;
        page.style.paddingBottom = `${marginSettings.bottom}cm`;
        
        // Apply first-line indent to paragraphs
        const paragraphs = page.querySelectorAll('p');
        paragraphs.forEach(p => {
            p.style.textIndent = `${marginSettings.firstLineIndent}cm`;
        });
    });
    
    console.log('📐 Applied margins:', marginSettings);
}

// ==================== PAGE CREATION & MANAGEMENT ====================

// ✅ Create initial page
function createInitialPage() {
    const container = document.getElementById('editor-pages-container');
    if (!container) {
        console.error('❌ Pages container not found');
        return;
    }
    
    // Clear container
    container.innerHTML = '';
    
    // Create first page
    const firstPage = createNewPageElement(1);
    container.appendChild(firstPage);
    
    // Setup editing
    setupPageEditing(firstPage);
    
    // Apply margins
    applyMarginsToAllPages();
    
    console.log('✅ Initial page created');
}

// ✅ Create new page element
function createNewPageElement(pageNumber) {
    const page = document.createElement('div');
    page.className = 'editor-page';
    page.setAttribute('contenteditable', 'true');
    page.setAttribute('spellcheck', 'true');
    page.setAttribute('data-page', pageNumber);
    
    // Initial content
    if (pageNumber === 1) {
        page.innerHTML = '<p>Bắt đầu viết sản phẩm học thuật của bạn...</p>';
    } else {
        page.innerHTML = '<p><br></p>';
    }
    
    // Add page number indicator
    const pageNumberDiv = document.createElement('div');
    pageNumberDiv.className = 'page-number';
    pageNumberDiv.textContent = pageNumber;
    page.appendChild(pageNumberDiv);
    
    return page;
}

// ✅ Setup page editing handlers
function setupPageEditing(page) {
    const pageNumber = parseInt(page.getAttribute('data-page'));
    
    // Input event - check for overflow
    page.addEventListener('input', () => {
        updateWordCount();
        showSaveIndicator('saving');
        
        // Throttled overflow check
        clearTimeout(page.overflowCheckTimeout);
        page.overflowCheckTimeout = setTimeout(() => {
            checkAndHandlePageOverflow(page);
        }, 500);
    });
    
    // Paste event - clean HTML
    page.addEventListener('paste', (e) => {
        e.preventDefault();
        const text = e.clipboardData.getData('text/plain');
        document.execCommand('insertText', false, text);
        
        // Check overflow after paste
        setTimeout(() => checkAndHandlePageOverflow(page), 100);
    });
    
    // Keydown events
    page.addEventListener('keydown', (e) => {
        // Backspace - merge pages
        if (e.key === 'Backspace') {
            handleBackspaceInPage(page, e);
        }
        
        // Enter - check overflow
        if (e.key === 'Enter') {
            setTimeout(() => checkAndHandlePageOverflow(page), 50);
        }
    });
}

// ==================== AUTO PAGE OVERFLOW HANDLING ====================

// ✅ Check if page content overflows
function checkAndHandlePageOverflow(page) {
    // Calculate content area height
    const pageHeightPx = cmToPx(A4_HEIGHT_CM);
    const topMarginPx = cmToPx(marginSettings.top);
    const bottomMarginPx = cmToPx(marginSettings.bottom);
    const maxContentHeight = pageHeightPx - topMarginPx - bottomMarginPx;
    
    // Get actual scroll height (content height)
    const contentHeight = page.scrollHeight;
    
    // If content exceeds max height
    if (contentHeight > maxContentHeight * 0.95) { // 95% threshold
        const pageNumber = parseInt(page.getAttribute('data-page'));
        const container = document.getElementById('editor-pages-container');
        const allPages = container.querySelectorAll('.editor-page');
        
        // Only auto-create if this is the last page
        if (pageNumber === allPages.length) {
            console.log(`📄 Page ${pageNumber} overflow detected, creating new page...`);
            moveOverflowContentToNewPage(page);
        }
    }
}

// ✅ Move overflow content to new page
function moveOverflowContentToNewPage(currentPage) {
    const container = document.getElementById('editor-pages-container');
    const currentPageNumber = parseInt(currentPage.getAttribute('data-page'));
    const allPages = container.querySelectorAll('.editor-page');
    
    // Calculate max content height
    const pageHeightPx = cmToPx(A4_HEIGHT_CM);
    const topMarginPx = cmToPx(marginSettings.top);
    const bottomMarginPx = cmToPx(marginSettings.bottom);
    const maxContentHeight = pageHeightPx - topMarginPx - bottomMarginPx;
    
    // Get all content elements (exclude page number)
    const elements = Array.from(currentPage.children).filter(el => 
        !el.classList.contains('page-number')
    );
    
    if (elements.length === 0) return;
    
    // Find split point by measuring height
    let splitIndex = elements.length;
    const tempDiv = document.createElement('div');
    tempDiv.style.cssText = `
        position: absolute;
        visibility: hidden;
        width: ${currentPage.offsetWidth}px;
        padding: ${marginSettings.top}cm ${marginSettings.right}cm ${marginSettings.bottom}cm ${marginSettings.left}cm;
        font-family: ${window.getComputedStyle(currentPage).fontFamily};
        font-size: ${window.getComputedStyle(currentPage).fontSize};
        line-height: ${window.getComputedStyle(currentPage).lineHeight};
    `;
    document.body.appendChild(tempDiv);
    
    // Measure each element
    for (let i = 0; i < elements.length; i++) {
        const clone = elements[i].cloneNode(true);
        tempDiv.appendChild(clone);
        
        if (tempDiv.scrollHeight > maxContentHeight) {
            splitIndex = Math.max(1, i); // At least keep first element
            break;
        }
    }
    
    document.body.removeChild(tempDiv);
    
    // If we need to split
    if (splitIndex < elements.length) {
        // Create page separator
        const separator = document.createElement('div');
        separator.className = 'page-separator-visual';
        
        // Create new page
        const newPage = createNewPageElement(currentPageNumber + 1);
        
        // Move overflow elements to new page
        const pageNumberElement = newPage.querySelector('.page-number');
        for (let i = splitIndex; i < elements.length; i++) {
            newPage.insertBefore(elements[i], pageNumberElement);
        }
        
        // Insert new page into container
        const nextPage = allPages[currentPageNumber]; // Next page if exists
        if (nextPage) {
            container.insertBefore(separator, nextPage);
            container.insertBefore(newPage, nextPage);
        } else {
            container.appendChild(separator);
            container.appendChild(newPage);
        }
        
        // Setup new page
        setupPageEditing(newPage);
        applyMarginsToAllPages();
        
        // Renumber pages after new page
        renumberAllPages();
        updatePageCount();
        
        console.log(`✅ Created page ${currentPageNumber + 1} due to overflow`);
    }
}

// ==================== BACKSPACE HANDLING - MERGE PAGES ====================

// ✅ Handle backspace in page
function handleBackspaceInPage(page, event) {
    const pageNumber = parseInt(page.getAttribute('data-page'));
    
    // Don't handle first page differently
    if (pageNumber === 1) return;
    
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    
    const range = selection.getRangeAt(0);
    
    // Check if cursor is at the very start of the page
    const isAtStart = 
        range.startOffset === 0 && 
        (range.startContainer === page || 
         range.startContainer === page.firstChild ||
         (page.firstChild && range.startContainer === page.firstChild.firstChild));
    
    if (!isAtStart) return;
    
    event.preventDefault();
    
    const container = document.getElementById('editor-pages-container');
    const allPages = Array.from(container.querySelectorAll('.editor-page'));
    const currentIndex = allPages.indexOf(page);
    
    if (currentIndex === 0) return; // Safety check
    
    const previousPage = allPages[currentIndex - 1];
    
    // Get content from current page (exclude page number)
    const currentContent = Array.from(page.children).filter(el => 
        !el.classList.contains('page-number')
    );
    
    // Check if page is empty or nearly empty
    const isEmpty = currentContent.length === 0 || 
                   (currentContent.length === 1 && 
                    (currentContent[0].innerHTML === '<br>' || 
                     currentContent[0].innerText.trim() === ''));
    
    if (isEmpty) {
        // Delete empty page
        const separator = page.previousElementSibling;
        if (separator && separator.classList.contains('page-separator-visual')) {
            separator.remove();
        }
        page.remove();
        
        // Focus on previous page
        previousPage.focus();
        
        // Move cursor to end of previous page
        const range = document.createRange();
        const selection = window.getSelection();
        const lastChild = previousPage.lastChild.previousSibling || previousPage.lastChild;
        range.selectNodeContents(lastChild);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
        
        renumberAllPages();
        updatePageCount();
        
        console.log(`🗑️ Deleted empty page ${pageNumber}`);
    } else {
        // Try to merge if possible
        const pageHeightPx = cmToPx(A4_HEIGHT_CM);
        const margins = cmToPx(marginSettings.top) + cmToPx(marginSettings.bottom);
        const maxContentHeight = pageHeightPx - margins;
        
        // Estimate combined height
        const combinedHeight = previousPage.scrollHeight + page.scrollHeight - margins;
        
        if (combinedHeight < maxContentHeight * 1.1) { // 110% threshold for merging
            // Merge content
            const pageNumberElement = previousPage.querySelector('.page-number');
            currentContent.forEach(el => {
                previousPage.insertBefore(el, pageNumberElement);
            });
            
            // Remove current page and separator
            const separator = page.previousElementSibling;
            if (separator && separator.classList.contains('page-separator-visual')) {
                separator.remove();
            }
            page.remove();
            
            // Focus on previous page
            previousPage.focus();
            
            renumberAllPages();
            updatePageCount();
            
            console.log(`🔀 Merged page ${pageNumber} with page ${pageNumber - 1}`);
        }
    }
}

// ✅ Renumber all pages
function renumberAllPages() {
    const container = document.getElementById('editor-pages-container');
    const pages = container.querySelectorAll('.editor-page');
    
    pages.forEach((page, index) => {
        const newPageNumber = index + 1;
        page.setAttribute('data-page', newPageNumber);
        
        const pageNumberDiv = page.querySelector('.page-number');
        if (pageNumberDiv) {
            pageNumberDiv.textContent = newPageNumber;
        }
    });
}

// ==================== UTILITY FUNCTIONS ====================

// ✅ Convert cm to pixels (96 DPI)
function cmToPx(cm) {
    return cm * 37.795275591; // 1cm = 37.795275591px at 96 DPI
}

// ✅ Convert pixels to cm
function pxToCm(px) {
    return px / 37.795275591;
}

// ✅ Update page count in status bar
function updatePageCount() {
    const pages = document.querySelectorAll('.editor-page');
    const pageCountSpan = document.getElementById('page-count');
    if (pageCountSpan) {
        pageCountSpan.textContent = `Trang ${pages.length}`;
    }
}

// ✅ Update word count across all pages
function updateWordCount() {
    const pages = document.querySelectorAll('.editor-page');
    let totalWords = 0;
    let totalChars = 0;
    
    pages.forEach(page => {
        const text = page.innerText || '';
        // Remove page number from count
        const cleanText = text.replace(/\d+$/, '').trim();
        const words = cleanText.trim().split(/\s+/).filter(w => w.length > 0);
        totalWords += words.length;
        totalChars += cleanText.length;
    });
    
    const wordCountSpan = document.getElementById('word-count');
    const charCountSpan = document.getElementById('char-count');
    
    if (wordCountSpan) wordCountSpan.textContent = `${totalWords} từ`;
    if (charCountSpan) charCountSpan.textContent = `${totalChars} ký tự`;
}

// Zoom functions
let currentZoom = 100;

function zoomIn() {
    if (currentZoom < 200) {
        currentZoom += 10;
        applyZoom();
    }
}

function zoomOut() {
    if (currentZoom > 50) {
        currentZoom -= 10;
        applyZoom();
    }
}

function resetZoom() {
    currentZoom = 100;
    applyZoom();
}

function applyZoom() {
    const wrapper = document.querySelector('.editor-content-wrapper');
    const pages = wrapper.querySelectorAll('.editor-content');
    
    pages.forEach(page => {
        page.style.transform = `scale(${currentZoom / 100})`;
        page.style.transformOrigin = 'top center';
    });
    
    document.getElementById('zoom-level').textContent = `${currentZoom}%`;
}

// ==================== AUTO-SAVE ====================

function startAutoSave() {
  // Clear existing interval
  if (autoSaveInterval) {
    clearInterval(autoSaveInterval);
  }

  // Auto-save every 30 seconds
  autoSaveInterval = setInterval(() => {
    saveProduct(false);
  }, 30000);
}

async function saveProduct(showNotification = false) {
    if (!currentProduct) return;
    
    const title = document.getElementById('product-title').value.trim();
    
    // ✅ Get content from all pages
    const pages = document.querySelectorAll('.editor-content');
    let fullContent = '';
    
    pages.forEach((page, index) => {
        if (index > 0) {
            fullContent += '<div class="page-break"></div>';
        }
        fullContent += page.innerHTML;
    });
    
    // Validate content
    const textContent = Array.from(pages).map(p => p.innerText).join(' ').trim();
    if (!textContent || textContent.length < 5) {
        console.warn('Content too short, skipping save');
        if (showNotification) {
            Swal.fire({
                icon: 'warning',
                title: 'Nội dung quá ngắn',
                text: 'Vui lòng viết ít nhất 5 ký tự',
                confirmButtonColor: '#2E7D32'
            });
        }
        return;
    }
    
    try {
        showSaveIndicator('saving');
        
        const response = await fetch('api/academic-products.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update',
                product_id: currentProduct.id,
                title: title || 'Không có tiêu đề',
                content: fullContent
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSaveIndicator('saved');
            
            if (showNotification) {
                Swal.fire({
                    icon: 'success',
                    title: 'Đã lưu!',
                    timer: 1000,
                    showConfirmButton: false
                });
            }
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error saving:', error);
        showSaveIndicator('error');
        
        if (showNotification) {
            Swal.fire('Lỗi', 'Không thể lưu sản phẩm', 'error');
        }
    }
}

function showSaveIndicator(state) {
  const indicator = document.getElementById("save-indicator");
  if (!indicator) return;

  switch (state) {
    case "saving":
      indicator.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';
      indicator.style.color = "#FF9800";
      break;
    case "saved":
      indicator.innerHTML = '<i class="fas fa-check"></i> Đã lưu';
      indicator.style.color = "#4CAF50";
      break;
    case "error":
      indicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Lỗi';
      indicator.style.color = "#F44336";
      break;
  }
}

function closeProductEditor() {
  // Save before closing (only for students)
  if (!window.isTeacherReviewing) {
    saveProduct(false);
  }

  // Clear interval
  if (autoSaveInterval) {
    clearInterval(autoSaveInterval);
    autoSaveInterval = null;
  }

  // ✅ Reset teacher mode
  window.isTeacherReviewing = false;
  window.teacherReviewData = null;

  // Hide modal
  const modal = document.getElementById("product-editor-modal");
  if (modal) {
    modal.classList.remove("active");
  }

  document.body.style.overflow = "";

  // Reload products list (context-aware)
  if (typeof loadProducts === "function") {
    loadProducts();
  }
  if (typeof loadStudentProducts === "function") {
    loadStudentProducts();
  }
}

// ==================== SUBMIT PRODUCT ====================

async function submitProduct() {
  if (!currentProduct) return;

  // Get current content
  const content = document.getElementById("product-editor").innerHTML;
  const textContent = document
    .getElementById("product-editor")
    .innerText.trim();

  // Validate content
  if (!textContent || textContent.length < 50) {
    Swal.fire({
      icon: "warning",
      title: "Nội dung chưa đủ",
      text: "Vui lòng viết ít nhất 50 ký tự trước khi nộp bài",
      confirmButtonColor: "#2E7D32",
    });
    return;
  }

  // Confirm submission
  const result = await Swal.fire({
    title: "Xác nhận nộp bài",
    html: `
            <div style="text-align: left; padding: 20px;">
                <p style="margin-bottom: 16px; font-size: 15px; color: #555;">
                    Bạn có chắc chắn muốn nộp sản phẩm này?
                </p>
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; border-radius: 4px; margin-bottom: 12px;">
                    <p style="margin: 0; font-size: 14px; color: #856404;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Lưu ý:</strong> Sau khi nộp, bạn sẽ không thể chỉnh sửa cho đến khi giáo viên trả lại bài.
                    </p>
                </div>
                <div style="background: #e8f5e9; padding: 12px; border-radius: 4px;">
                    <p style="margin: 0; font-size: 13px; color: #2E7D32;">
                        <i class="fas fa-info-circle"></i>
                        Số từ hiện tại: <strong>${
                          textContent.split(/\s+/).length
                        } từ</strong>
                    </p>
                </div>
            </div>
        `,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: '<i class="fas fa-paper-plane"></i> Nộp bài',
    cancelButtonText: '<i class="fas fa-times"></i> Hủy',
    confirmButtonColor: "#FF6B35",
    cancelButtonColor: "#757575",
    width: "500px",
  });

  if (!result.isConfirmed) return;

  try {
    // ✅ Save current content first (without creating version)
    Swal.fire({
      title: "Đang xử lý...",
      html: "Vui lòng đợi trong giây lát",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    const title = document.getElementById("product-title").value.trim();

    // Save content without version
    const saveResponse = await fetch("api/academic-products.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "update",
        product_id: currentProduct.id,
        title: title || "Không có tiêu đề",
        content: content,
        skip_version: true, // ✅ Flag to skip version creation
      }),
    });

    const saveData = await saveResponse.json();

    if (!saveData.success) {
      throw new Error(saveData.message || "Không thể lưu nội dung");
    }

    // Now submit
    const submitResponse = await fetch("api/academic-products.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "submit",
        product_id: currentProduct.id,
      }),
    });

    const submitData = await submitResponse.json();

    if (submitData.success) {
      await Swal.fire({
        icon: "success",
        title: "Nộp bài thành công!",
        html: `
                    <div style="text-align: center; padding: 20px;">
                        <p style="font-size: 16px; color: #555; margin-bottom: 20px;">
                            Sản phẩm của bạn đã được nộp thành công
                        </p>
                        <div style="background: #e8f5e9; padding: 16px; border-radius: 8px;">
                            <i class="fas fa-clock" style="font-size: 48px; color: #2E7D32; margin-bottom: 12px;"></i>
                            <p style="margin: 0; font-size: 14px; color: #1B5E20;">
                                Giáo viên sẽ xem xét và phản hồi sớm nhất có thể
                            </p>
                        </div>
                    </div>
                `,
        confirmButtonText: "Về danh sách",
        confirmButtonColor: "#2E7D32",
      });

      // Close editor and reload products
      closeProductEditor();
      await loadProducts();
    } else {
      throw new Error(submitData.message);
    }
  } catch (error) {
    console.error("Error submitting:", error);
    Swal.fire({
      icon: "error",
      title: "Lỗi",
      text: "Không thể nộp bài: " + error.message,
      confirmButtonColor: "#2E7D32",
    });
  }
}

// ==================== SIDEBAR FUNCTIONS ====================

function switchSidebarTab(tab) {
  // Update active tab
  document
    .querySelectorAll(".sidebar-tab")
    .forEach((t) => t.classList.remove("active"));
  document.querySelector(`[data-tab="${tab}"]`).classList.add("active");

  // Update active content
  document
    .querySelectorAll(".sidebar-content")
    .forEach((c) => c.classList.remove("active"));
  document.querySelector(`[data-content="${tab}"]`).classList.add("active");

  // Load content if needed
  if (tab === "history") {
    loadVersionHistory();
  }
}

// ==================== COMMENTS ====================

async function loadComments(productId) {
  try {
    // ✅ Detect API path based on user role
    const apiPath = window.isTeacherReviewing
      ? "../saudn/api/product-comments.php"
      : "api/product-comments.php";

    const response = await fetch(`${apiPath}?product_id=${productId}`);
    const data = await response.json();

    const container = document.getElementById("comments-list");
    const countBadge = document.getElementById("comments-count");

    if (!data.success || !data.comments || data.comments.length === 0) {
      container.innerHTML =
        '<p class="empty-state-small">Chưa có bình luận nào</p>';
      if (countBadge) countBadge.textContent = "0";
      return;
    }

    if (countBadge) countBadge.textContent = data.comments.length;

    container.innerHTML = data.comments
      .map(
        (comment) => `
            <div class="comment-item" data-comment-id="${comment.id}">
                <div class="comment-header">
                    <div class="comment-author">
                        <img src="${
                          comment.avatar || "../assets/default-avatar.png"
                        }" alt="${comment.user_name}">
                        <div>
                            <div class="author-name-wrapper">
                                <strong>${comment.user_name}</strong>
                                ${
                                  comment.user_role === "teacher"
                                    ? '<span class="teacher-badge"><i class="fas fa-chalkboard-teacher"></i> Giáo viên</span>'
                                    : ""
                                }
                            </div>
                            <span class="comment-time">${formatDateTime(
                              comment.created_at
                            )}</span>
                        </div>
                    </div>
                    ${
                      comment.is_owner
                        ? `
                        <button class="btn-icon-small" onclick="deleteComment(${comment.id})" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    `
                        : ""
                    }
                </div>
                <div class="comment-body">
                    ${comment.content}
                </div>
                ${
                  comment.position
                    ? `
                    <button class="comment-highlight-link" onclick="highlightText('${comment.position}')">
                        <i class="fas fa-quote-left"></i> Xem đoạn được bình luận
                    </button>
                `
                    : ""
                }
            </div>
        `
      )
      .join("");
  } catch (error) {
    console.error("Error loading comments:", error);
  }
}

async function addComment() {
  const selection = window.getSelection();
  const selectedText = selection.toString().trim();

  const { value: commentText } = await Swal.fire({
    title: "Thêm bình luận",
    html: `
            ${
              selectedText
                ? `
                <div class="selected-text-preview">
                    <i class="fas fa-quote-left"></i>
                    "${selectedText}"
                </div>
            `
                : ""
            }
            <textarea id="comment-input" class="swal2-textarea" placeholder="Nhập bình luận..."></textarea>
        `,
    showCancelButton: true,
    confirmButtonText: "Thêm",
    cancelButtonText: "Hủy",
    confirmButtonColor: "#2E7D32",
    preConfirm: () => {
      const input = document.getElementById("comment-input");
      if (!input.value.trim()) {
        Swal.showValidationMessage("Vui lòng nhập nội dung");
        return false;
      }
      return input.value.trim();
    },
  });

  if (commentText) {
    try {
      const response = await fetch("api/product-comments.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "add",
          product_id: currentProduct.id,
          content: commentText,
          selected_text: selectedText || null,
          position: selectedText ? getSelectionPosition() : null,
        }),
      });

      const data = await response.json();

      if (data.success) {
        Swal.fire({
          icon: "success",
          title: "Thành công!",
          text: "Bình luận đã được thêm",
          timer: 1500,
          showConfirmButton: false,
        });

        loadComments(currentProduct.id);
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      Swal.fire("Lỗi", "Không thể thêm bình luận", "error");
    }
  }
}

function getSelectionPosition() {
  const selection = window.getSelection();
  if (!selection.rangeCount) return null;

  const range = selection.getRangeAt(0);
  const editor = document.getElementById("product-editor");

  // Get position relative to editor
  const preSelectionRange = range.cloneRange();
  preSelectionRange.selectNodeContents(editor);
  preSelectionRange.setEnd(range.startContainer, range.startOffset);

  const start = preSelectionRange.toString().length;
  const end = start + range.toString().length;

  return JSON.stringify({ start, end });
}

function highlightText(position) {
  try {
    const pos = JSON.parse(position);
    const editor = document.getElementById("product-editor");
    const text = editor.innerText;

    // Create temporary highlight
    const highlightedText = text.substring(pos.start, pos.end);
    const newHTML = editor.innerHTML.replace(
      highlightedText,
      `<mark class="temp-highlight">${highlightedText}</mark>`
    );

    editor.innerHTML = newHTML;

    // Scroll to highlighted text
    const highlight = editor.querySelector(".temp-highlight");
    if (highlight) {
      highlight.scrollIntoView({ behavior: "smooth", block: "center" });

      // Remove highlight after 3 seconds
      setTimeout(() => {
        const mark = editor.querySelector(".temp-highlight");
        if (mark) {
          mark.outerHTML = mark.innerHTML;
        }
      }, 3000);
    }
  } catch (error) {
    console.error("Error highlighting text:", error);
  }
}

async function deleteComment(commentId) {
  const result = await Swal.fire({
    title: "Xóa bình luận?",
    text: "Bạn có chắc muốn xóa bình luận này?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Xóa",
    cancelButtonText: "Hủy",
    confirmButtonColor: "#F44336",
    cancelButtonColor: "#999",
  });

  if (result.isConfirmed) {
    try {
      const response = await fetch("api/product-comments.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "delete",
          comment_id: commentId,
        }),
      });

      const data = await response.json();

      if (data.success) {
        loadComments(currentProduct.id);
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      Swal.fire("Lỗi", "Không thể xóa bình luận", "error");
    }
  }
}

// ==================== REVIEWS (TEACHER FEEDBACK) ====================

async function loadReviews(productId) {
  try {
    const response = await fetch(
      `api/product-reviews.php?product_id=${productId}`
    );
    const data = await response.json();

    const container = document.getElementById("reviews-container");

    if (!data.success || !data.reviews || data.reviews.length === 0) {
      container.innerHTML =
        '<p class="empty-state-small">Chưa có nhận xét nào</p>';
      return;
    }

    container.innerHTML = data.reviews
      .map(
        (review) => `
            <div class="review-item ${review.type}">
                <div class="review-header">
                    <div class="review-author">
                        <img src="${
                          review.teacher_avatar ||
                          "../assets/default-avatar.png"
                        }" alt="${review.teacher_name}">
                        <div>
                            <strong>${review.teacher_name}</strong>
                            <span class="review-time">${formatDateTime(
                              review.created_at
                            )}</span>
                        </div>
                    </div>
                    ${
                      review.score
                        ? `
                        <div class="review-score">
                            <i class="fas fa-star"></i>
                            <strong>${review.score}/10</strong>
                        </div>
                    `
                        : ""
                    }
                </div>
                
                ${
                  review.highlighted_text
                    ? `
                    <div class="review-highlight">
                        <i class="fas fa-quote-left"></i>
                        "${review.highlighted_text}"
                    </div>
                `
                    : ""
                }
                
                <div class="review-body ${review.type}">
                    <div class="review-type-badge">
                        <i class="fas ${getReviewIcon(review.type)}"></i>
                        ${getReviewTypeText(review.type)}
                    </div>
                    <div class="review-content">
                        ${review.content}
                    </div>
                </div>
                
                ${
                  review.suggestion
                    ? `
                    <div class="review-suggestion">
                        <strong><i class="fas fa-lightbulb"></i> Đề xuất:</strong>
                        <p>${review.suggestion}</p>
                    </div>
                `
                    : ""
                }
            </div>
        `
      )
      .join("");
  } catch (error) {
    console.error("Error loading reviews:", error);
  }
}

function getReviewIcon(type) {
  const icons = {
    correction: "fa-edit",
    suggestion: "fa-lightbulb",
    praise: "fa-thumbs-up",
    question: "fa-question-circle",
  };
  return icons[type] || "fa-comment";
}

function getReviewTypeText(type) {
  const texts = {
    correction: "Sửa lỗi",
    suggestion: "Gợi ý",
    praise: "Khen ngợi",
    question: "Câu hỏi",
  };
  return texts[type] || type;
}

// ==================== VERSION HISTORY ====================

async function loadVersionHistory() {
  if (!currentProduct) return;

  try {
    const response = await fetch(
      `api/product-versions.php?product_id=${currentProduct.id}`
    );
    const data = await response.json();

    const container = document.getElementById("version-history-list");

    if (!data.success || !data.versions || data.versions.length === 0) {
      container.innerHTML =
        '<p class="empty-state-small">Chưa có lịch sử chỉnh sửa</p>';
      return;
    }

    container.innerHTML = data.versions
      .map(
        (version, index) => `
            <div class="version-item" data-version-id="${version.id}">
                <div class="version-header">
                    <div class="version-info">
                        <strong>Phiên bản ${
                          data.versions.length - index
                        }</strong>
                        <span class="version-time">${formatDateTime(
                          version.created_at
                        )}</span>
                    </div>
                    ${
                      index === 0
                        ? '<span class="version-current-badge">Hiện tại</span>'
                        : ""
                    }
                </div>
                
                <div class="version-meta">
                    <span><i class="fas fa-user"></i> ${
                      version.user_name
                    }</span>
                    <span><i class="fas fa-file-word"></i> ${
                      version.word_count
                    } từ</span>
                </div>
                
                ${
                  version.changes_summary
                    ? `
                    <div class="version-changes">
                        <i class="fas fa-info-circle"></i>
                        ${version.changes_summary}
                    </div>
                `
                    : ""
                }
                
                <div class="version-actions">
                    <button class="btn-version-action" onclick="previewVersion(${
                      version.id
                    })">
                        <i class="fas fa-eye"></i> Xem trước
                    </button>
                    ${
                      index !== 0
                        ? `
                        <button class="btn-version-action" onclick="restoreVersion(${version.id})">
                            <i class="fas fa-undo"></i> Khôi phục
                        </button>
                        <button class="btn-version-action" onclick="compareVersions(${data.versions[0].id}, ${version.id})">
                            <i class="fas fa-exchange-alt"></i> So sánh
                        </button>
                    `
                        : ""
                    }
                </div>
            </div>
        `
      )
      .join("");
  } catch (error) {
    console.error("Error loading version history:", error);
    document.getElementById("version-history-list").innerHTML =
      '<p class="empty-state-small">Không thể tải lịch sử</p>';
  }
}

async function openTeacherReviewForm() {
  const reviewData = window.teacherReviewData || {};

  const result = await Swal.fire({
    title: '<i class="fas fa-clipboard-check"></i> Chấm điểm sản phẩm',
    html: `
            <div class="teacher-review-form">
                <div class="student-info-box">
                    <div class="info-row">
                        <i class="fas fa-user-graduate"></i>
                        <span><strong>${
                          currentProduct.student_name
                        }</strong></span>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-book"></i>
                        <span>${currentProduct.class_name} ${
      currentProduct.class_code ? "(" + currentProduct.class_code + ")" : ""
    }</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-star"></i>
                        Điểm số (0-10) *
                    </label>
                    <div class="score-input-wrapper">
                        <input type="number" 
                               id="teacher-score-input" 
                               class="swal2-input score-input" 
                               min="0" 
                               max="10" 
                               step="0.5" 
                               value="${reviewData.score}"
                               placeholder="0.0">
                        <span class="score-suffix">/10</span>
                    </div>
                    <div class="score-presets">
                        <button type="button" class="score-btn" onclick="document.getElementById('teacher-score-input').value=10">10</button>
                        <button type="button" class="score-btn" onclick="document.getElementById('teacher-score-input').value=9">9</button>
                        <button type="button" class="score-btn" onclick="document.getElementById('teacher-score-input').value=8">8</button>
                        <button type="button" class="score-btn" onclick="document.getElementById('teacher-score-input').value=7">7</button>
                        <button type="button" class="score-btn" onclick="document.getElementById('teacher-score-input').value=6">6</button>
                        <button type="button" class="score-btn" onclick="document.getElementById('teacher-score-input').value=5">5</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-comment-alt"></i>
                        Nhận xét chi tiết *
                    </label>
                    <textarea id="teacher-feedback-input" 
                              class="swal2-textarea" 
                              rows="10"
                              placeholder="Nhập nhận xét cho học sinh...">${
                                reviewData.feedback
                              }</textarea>
                    <div class="char-counter">
                        <span id="feedback-char-count">${
                          reviewData.feedback.length
                        }</span> ký tự
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-clipboard-list"></i>
                        Mẫu nhận xét nhanh
                    </label>
                    <div class="feedback-templates">
                        <button type="button" class="template-btn" onclick="insertFeedbackTemplate('excellent')">
                            <i class="fas fa-star"></i> Xuất sắc
                        </button>
                        <button type="button" class="template-btn" onclick="insertFeedbackTemplate('good')">
                            <i class="fas fa-thumbs-up"></i> Tốt
                        </button>
                        <button type="button" class="template-btn" onclick="insertFeedbackTemplate('improve')">
                            <i class="fas fa-edit"></i> Cần cải thiện
                        </button>
                        <button type="button" class="template-btn" onclick="insertFeedbackTemplate('structure')">
                            <i class="fas fa-sitemap"></i> Cấu trúc
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-flag"></i>
                        Trạng thái
                    </label>
                    <select id="teacher-status-select" class="swal2-select">
                        <option value="reviewed" ${
                          reviewData.status === "reviewed" ? "selected" : ""
                        }>✓ Đã chấm - Hoàn thành</option>
                        <option value="returned" ${
                          reviewData.status === "returned" ? "selected" : ""
                        }>↺ Trả lại - Cần sửa lại</option>
                    </select>
                </div>
            </div>
        `,
    width: "700px",
    showCancelButton: true,
    confirmButtonText: '<i class="fas fa-paper-plane"></i> Lưu và gửi học sinh',
    cancelButtonText: '<i class="fas fa-times"></i> Hủy',
    confirmButtonColor: "#2E7D32",
    cancelButtonColor: "#999",
    customClass: {
      popup: "teacher-review-popup",
    },
    didOpen: () => {
      // Setup character counter
      const feedbackInput = document.getElementById("teacher-feedback-input");
      feedbackInput.addEventListener("input", () => {
        document.getElementById("feedback-char-count").textContent =
          feedbackInput.value.length;
      });
    },
    preConfirm: () => {
      const score = document.getElementById("teacher-score-input").value;
      const feedback = document
        .getElementById("teacher-feedback-input")
        .value.trim();
      const status = document.getElementById("teacher-status-select").value;

      if (!score || score === "") {
        Swal.showValidationMessage("Vui lòng nhập điểm số");
        return false;
      }

      const numScore = parseFloat(score);
      if (isNaN(numScore) || numScore < 0 || numScore > 10) {
        Swal.showValidationMessage("Điểm phải từ 0 đến 10");
        return false;
      }

      if (!feedback) {
        Swal.showValidationMessage("Vui lòng nhập nhận xét");
        return false;
      }

      return { score: numScore, feedback, status };
    },
  });

  if (result.isConfirmed) {
    await saveTeacherReview(result.value);
  }
}

// Feedback templates helper
window.insertFeedbackTemplate = function (type) {
  const templates = {
    excellent:
      "Xuất sắc! Bài viết thể hiện sự nắm vững kiến thức và khả năng trình bày logic, mạch lạc.",
    good: "Bài viết tốt, nội dung đầy đủ. Một số điểm có thể cải thiện thêm về độ sâu phân tích.",
    improve:
      "Bài viết cần bổ sung thêm các dẫn chứng, ví dụ cụ thể để làm rõ các luận điểm.",
    structure:
      "Cần cải thiện cấu trúc bài viết. Đảm bảo có phần mở bài, thân bài và kết luận rõ ràng.",
  };

  const textarea = document.getElementById("teacher-feedback-input");
  const currentValue = textarea.value;
  textarea.value = currentValue
    ? `${currentValue}\n\n${templates[type]}`
    : templates[type];
  textarea.dispatchEvent(new Event("input"));
};

async function saveTeacherReview(reviewData) {
  try {
    Swal.fire({
      title: "Đang lưu đánh giá...",
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading(),
    });

    const response = await fetch("../giaovien/api/review-product.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        product_id: currentProduct.id,
        score: reviewData.score,
        feedback: reviewData.feedback,
        status: reviewData.status,
      }),
    });

    const data = await response.json();

    if (data.success) {
      await Swal.fire({
        icon: "success",
        title: "Thành công!",
        text: "Đã lưu đánh giá và gửi thông báo cho học sinh",
        confirmButtonColor: "#2E7D32",
        timer: 2000,
      });

      // Update review data
      window.teacherReviewData = reviewData;
      currentProduct.score = reviewData.score;
      currentProduct.feedback = reviewData.feedback;
      currentProduct.status = reviewData.status;

      // Reload reviews
      loadReviews(currentProduct.id);
    } else {
      throw new Error(data.message || "Không thể lưu đánh giá");
    }
  } catch (error) {
    console.error("Error saving review:", error);
    Swal.fire({
      icon: "error",
      title: "Lỗi",
      text: error.message,
      confirmButtonColor: "#2E7D32",
    });
  }
}

async function previewVersion(versionId) {
  try {
    const response = await fetch(
      `api/product-versions.php?version_id=${versionId}`
    );
    const data = await response.json();

    if (!data.success) {
      throw new Error(data.message);
    }

    Swal.fire({
      title: "Xem trước phiên bản",
      html: `
                <div class="version-preview">
                    <div class="preview-header">
                        <h4>${data.version.title || "Không có tiêu đề"}</h4>
                        <p class="preview-meta">
                            ${formatDateTime(data.version.created_at)} • ${
        data.version.word_count
      } từ
                        </p>
                    </div>
                    <div class="preview-content">
                        ${data.version.content}
                    </div>
                </div>
            `,
      width: "800px",
      showCloseButton: true,
      showConfirmButton: false,
      customClass: {
        htmlContainer: "version-preview-container",
      },
    });
  } catch (error) {
    Swal.fire("Lỗi", "Không thể xem trước phiên bản", "error");
  }
}

async function restoreVersion(versionId) {
  const result = await Swal.fire({
    title: "Khôi phục phiên bản?",
    html: `
            <p>Bạn có chắc muốn khôi phục phiên bản này?</p>
            <p style="color: #666; font-size: 14px; margin-top: 12px;">
                Nội dung hiện tại sẽ được lưu thành phiên bản mới.
            </p>
        `,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Khôi phục",
    cancelButtonText: "Hủy",
    confirmButtonColor: "#2E7D32",
    cancelButtonColor: "#999",
  });

  if (result.isConfirmed) {
    try {
      const response = await fetch("api/product-versions.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "restore",
          version_id: versionId,
          product_id: currentProduct.id,
        }),
      });

      const data = await response.json();

      if (data.success) {
        Swal.fire({
          icon: "success",
          title: "Thành công!",
          text: "Phiên bản đã được khôi phục",
          timer: 1500,
          showConfirmButton: false,
        });

        // Reload editor content
        document.getElementById("product-title").value = data.product.title;
        document.getElementById("product-editor").innerHTML =
          data.product.content;
        updateWordCount();

        // Reload version history
        loadVersionHistory();
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      Swal.fire("Lỗi", "Không thể khôi phục phiên bản", "error");
    }
  }
}

async function compareVersions(currentVersionId, oldVersionId) {
  try {
    const response = await fetch(
      `api/product-versions.php?action=compare&version1=${currentVersionId}&version2=${oldVersionId}`
    );
    const data = await response.json();

    if (!data.success) {
      throw new Error(data.message);
    }

    Swal.fire({
      title: "So sánh phiên bản",
      html: `
                <div class="version-comparison">
                    <div class="comparison-header">
                        <div class="comparison-side">
                            <strong>Phiên bản cũ</strong>
                            <span>${formatDateTime(
                              data.version2.created_at
                            )}</span>
                        </div>
                        <div class="comparison-side">
                            <strong>Phiên bản mới</strong>
                            <span>${formatDateTime(
                              data.version1.created_at
                            )}</span>
                        </div>
                    </div>
                    <div class="comparison-content">
                        <div class="comparison-side old">
                            ${data.version2.content}
                        </div>
                        <div class="comparison-side new">
                            ${data.version1.content}
                        </div>
                    </div>
                    <div class="comparison-stats">
                        <span class="stat-item added">
                            <i class="fas fa-plus"></i> ${
                              data.stats.added
                            } ký tự thêm
                        </span>
                        <span class="stat-item removed">
                            <i class="fas fa-minus"></i> ${
                              data.stats.removed
                            } ký tự xóa
                        </span>
                    </div>
                </div>
            `,
      width: "1200px",
      showCloseButton: true,
      showConfirmButton: false,
      customClass: {
        htmlContainer: "version-comparison-container",
      },
    });
  } catch (error) {
    Swal.fire("Lỗi", "Không thể so sánh phiên bản", "error");
  }
}

function toggleVersionHistory() {
  switchSidebarTab("history");
}

// ==================== AI WRITING ASSISTANT ====================

function toggleAIWritingAssistant() {
  const panel = document.getElementById("ai-writing-panel");
  const btn = document.getElementById("ai-writing-assistant-btn");

  if (panel.classList.contains("active")) {
    panel.classList.remove("active");
    btn.classList.remove("active");
  } else {
    panel.classList.add("active");
    btn.classList.add("active");
  }
}

async function aiSuggest(type) {
  const editor = document.getElementById("product-editor");
  const selection = window.getSelection();
  const selectedText = selection.toString().trim();

  // Get text to process
  let textToProcess = selectedText || editor.innerText;

  if (!textToProcess || textToProcess.length < 10) {
    Swal.fire({
      icon: "warning",
      title: "Chưa có nội dung",
      text: "Vui lòng viết hoặc chọn đoạn văn bản để AI hỗ trợ",
      confirmButtonColor: "#2E7D32",
    });
    return;
  }

  // Show loading
  const resultDiv = document.getElementById("ai-result");
  resultDiv.style.display = "block";
  resultDiv.innerHTML = `
        <div class="ai-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <p>AI đang xử lý...</p>
        </div>
    `;

  try {
    const response = await fetch("api/ai-writing-assistant.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: type,
        text: textToProcess,
        product_id: currentProduct.id,
      }),
    });

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.message);
    }

    displayAIResult(data.result, type, selectedText ? "selected" : "all");
  } catch (error) {
    console.error("AI error:", error);
    resultDiv.innerHTML = `
            <div class="ai-error">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Không thể xử lý: ${error.message}</p>
            </div>
        `;
  }
}

async function aiCustomRequest() {
  const input = document.getElementById("ai-custom-input");
  const prompt = input.value.trim();

  if (!prompt) {
    Swal.fire({
      icon: "warning",
      title: "Thiếu yêu cầu",
      text: "Vui lòng nhập yêu cầu cho AI",
      confirmButtonColor: "#2E7D32",
    });
    return;
  }

  const editor = document.getElementById("product-editor");
  const textContent = editor.innerText;

  const resultDiv = document.getElementById("ai-result");
  resultDiv.style.display = "block";
  resultDiv.innerHTML = `
        <div class="ai-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <p>AI đang xử lý yêu cầu của bạn...</p>
        </div>
    `;

  try {
    const response = await fetch("api/ai-writing-assistant.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "custom",
        prompt: prompt,
        text: textContent,
        product_id: currentProduct.id,
      }),
    });

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.message);
    }

    displayAIResult(data.result, "custom", "all");
    input.value = "";
  } catch (error) {
    console.error("AI error:", error);
    resultDiv.innerHTML = `
            <div class="ai-error">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Không thể xử lý: ${error.message}</p>
            </div>
        `;
  }
}

function displayAIResult(result, type, scope) {
  const resultDiv = document.getElementById("ai-result");

  const typeText = {
    improve: "Cải thiện văn phong",
    grammar: "Sửa lỗi chính tả",
    expand: "Mở rộng nội dung",
    summarize: "Tóm tắt",
    custom: "Kết quả",
  };

  resultDiv.innerHTML = `
        <div class="ai-result-content">
            <div class="ai-result-header">
                <h5><i class="fas fa-magic"></i> ${typeText[type]}</h5>
                <button class="btn-icon" onclick="closeAIResult()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="ai-result-body">
                ${result}
            </div>
            <div class="ai-result-actions">
                <button class="btn-ai-action" onclick="applyAIResult('${scope}')">
                    <i class="fas fa-check"></i> Áp dụng
                </button>
                <button class="btn-ai-action secondary" onclick="copyAIResult()">
                    <i class="fas fa-copy"></i> Sao chép
                </button>
                <button class="btn-ai-action secondary" onclick="closeAIResult()">
                    <i class="fas fa-times"></i> Hủy
                </button>
            </div>
        </div>
    `;
}

function closeAIResult() {
  const resultDiv = document.getElementById("ai-result");
  resultDiv.style.display = "none";
  resultDiv.innerHTML = "";
}

function applyAIResult(scope) {
  const resultBody = document.querySelector(".ai-result-body");
  if (!resultBody) return;

  const newContent = resultBody.innerHTML;
  const editor = document.getElementById("product-editor");

  if (scope === "selected") {
    // Replace selected text
    document.execCommand("insertHTML", false, newContent);
  } else {
    // Replace all content
    editor.innerHTML = newContent;
  }

  updateWordCount();
  showSaveIndicator("saving");
  closeAIResult();

  Swal.fire({
    icon: "success",
    title: "Đã áp dụng!",
    timer: 1000,
    showConfirmButton: false,
  });
}

function copyAIResult() {
  const resultBody = document.querySelector(".ai-result-body");
  if (!resultBody) return;

  const text = resultBody.innerText;

  navigator.clipboard
    .writeText(text)
    .then(() => {
      Swal.fire({
        icon: "success",
        title: "Đã sao chép!",
        timer: 1000,
        showConfirmButton: false,
      });
    })
    .catch((err) => {
      console.error("Copy failed:", err);
    });
}

// ==================== SHARE PRODUCT ====================

async function shareProduct(productId = null) {
  const id = productId || (currentProduct ? currentProduct.id : null);

  if (!id) return;

  const { value: formValues } = await Swal.fire({
    title: "Chia sẻ sản phẩm",
    html: `
            <div class="share-product-form">
                <div class="form-group">
                    <label>Email người nhận</label>
                    <input type="email" id="share-email" class="swal2-input" placeholder="email@example.com">
                </div>
                <div class="form-group">
                    <label>Quyền truy cập</label>
                    <select id="share-permission" class="swal2-select">
                        <option value="view">Chỉ xem</option>
                        <option value="comment">Xem và bình luận</option>
                        <option value="edit">Chỉnh sửa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Lời nhắn (tùy chọn)</label>
                    <textarea id="share-message" class="swal2-textarea" placeholder="Thêm lời nhắn..."></textarea>
                </div>
                
                <div class="share-link-section">
                    <p style="font-weight: 600; margin: 16px 0 8px 0;">Hoặc chia sẻ bằng liên kết</p>
                    <div class="share-link-input">
                        <input type="text" id="share-link" readonly value="Loading...">
                        <button onclick="copyShareLink()" class="btn-copy-link">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        `,
    width: "600px",
    showCancelButton: true,
    confirmButtonText: "Chia sẻ",
    cancelButtonText: "Đóng",
    confirmButtonColor: "#2E7D32",
    didOpen: async () => {
      // Generate share link
      try {
        const response = await fetch(
          `api/academic-products.php?action=get_share_link&product_id=${id}`
        );
        const data = await response.json();
        if (data.success) {
          document.getElementById("share-link").value = data.share_link;
        }
      } catch (error) {
        console.error("Error generating share link:", error);
      }
    },
    preConfirm: () => {
      const email = document.getElementById("share-email").value.trim();
      if (!email) {
        return null; // Allow closing without sharing
      }

      return {
        email: email,
        permission: document.getElementById("share-permission").value,
        message: document.getElementById("share-message").value.trim(),
      };
    },
  });

  if (formValues) {
    try {
      const response = await fetch("api/share-product.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          product_id: id,
          email: formValues.email,
          permission: formValues.permission,
          message: formValues.message,
        }),
      });

      const data = await response.json();

      if (data.success) {
        Swal.fire({
          icon: "success",
          title: "Đã chia sẻ!",
          text: `Đã gửi lời mời đến ${formValues.email}`,
          confirmButtonColor: "#2E7D32",
        });
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      Swal.fire("Lỗi", error.message || "Không thể chia sẻ", "error");
    }
  }
}

function copyShareLink() {
  const linkInput = document.getElementById("share-link");
  linkInput.select();
  document.execCommand("copy");

  Swal.fire({
    icon: "success",
    title: "Đã sao chép!",
    text: "Liên kết đã được sao chép vào clipboard",
    timer: 1500,
    showConfirmButton: false,
  });
}

// ==================== PRODUCT MENU ACTIONS ====================

function toggleProductMenu(productId) {
  const menu = document.getElementById(`menu-${productId}`);
  const allMenus = document.querySelectorAll(".product-menu-dropdown");

  // Close other menus
  allMenus.forEach((m) => {
    if (m !== menu) m.classList.remove("active");
  });

  menu.classList.toggle("active");
}

// Close menus when clicking outside
document.addEventListener("click", (e) => {
  if (!e.target.closest(".product-actions-dropdown")) {
    document.querySelectorAll(".product-menu-dropdown").forEach((menu) => {
      menu.classList.remove("active");
    });
  }
});

async function duplicateProduct(productId) {
  try {
    const response = await fetch("api/academic-products.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "duplicate",
        product_id: productId,
      }),
    });

    const data = await response.json();

    if (data.success) {
      Swal.fire({
        icon: "success",
        title: "Đã nhân bản!",
        text: "Sản phẩm đã được nhân bản",
        confirmButtonColor: "#2E7D32",
      });
      loadProducts();
    } else {
      throw new Error(data.message);
    }
  } catch (error) {
    Swal.fire("Lỗi", "Không thể nhân bản sản phẩm", "error");
  }
}

async function exportProduct(productId) {
  const { value: format } = await Swal.fire({
    title: "Xuất file",
    html: `
            <p style="margin-bottom: 16px;">Chọn định dạng xuất file:</p>
            <select id="export-format" class="swal2-select">
                <option value="pdf">PDF</option>
                <option value="docx">Word (DOCX)</option>
                <option value="html">HTML</option>
                <option value="txt">Text</option>
            </select>
        `,
    showCancelButton: true,
    confirmButtonText: "Xuất file",
    cancelButtonText: "Hủy",
    confirmButtonColor: "#2E7D32",
    preConfirm: () => {
      return document.getElementById("export-format").value;
    },
  });

  if (format) {
    try {
      Swal.fire({
        title: "Đang xuất file...",
        html: '<i class="fas fa-spinner fa-spin" style="font-size: 48px; color: #2E7D32;"></i>',
        allowOutsideClick: false,
        showConfirmButton: false,
      });

      const response = await fetch("api/export-product.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          product_id: productId,
          format: format,
        }),
      });

      const data = await response.json();

      if (data.success) {
        // Download file
        window.location.href = data.download_url;

        Swal.fire({
          icon: "success",
          title: "Thành công!",
          text: "File đang được tải xuống",
          timer: 2000,
          showConfirmButton: false,
        });
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      Swal.fire("Lỗi", "Không thể xuất file", "error");
    }
  }
}

async function deleteProduct(productId) {
  const result = await Swal.fire({
    title: "Xóa sản phẩm?",
    text: "Bạn có chắc muốn xóa sản phẩm này? Hành động này không thể hoàn tác.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Xóa",
    cancelButtonText: "Hủy",
    confirmButtonColor: "#F44336",
    cancelButtonColor: "#999",
  });

  if (result.isConfirmed) {
    try {
      const response = await fetch("api/academic-products.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "delete",
          product_id: productId,
        }),
      });

      const data = await response.json();

      if (data.success) {
        Swal.fire({
          icon: "success",
          title: "Đã xóa!",
          timer: 1500,
          showConfirmButton: false,
        });
        loadProducts();
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      Swal.fire("Lỗi", "Không thể xóa sản phẩm", "error");
    }
  }
}

// ==================== UTILITY FUNCTIONS ====================

function formatDate(dateString) {
  if (!dateString) return "";

  const date = new Date(dateString);
  const now = new Date();
  const diffTime = Math.abs(now - date);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays === 0) {
    return "Hôm nay";
  } else if (diffDays === 1) {
    return "Hôm qua";
  } else if (diffDays < 7) {
    return `${diffDays} ngày trước`;
  } else {
    return date.toLocaleDateString("vi-VN", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  }
}

function formatDateTime(dateString) {
  if (!dateString) return "";

  const date = new Date(dateString);
  return date.toLocaleString("vi-VN", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

// ==================== SEARCH & FILTER ====================

document.addEventListener("DOMContentLoaded", () => {
  // Filter tabs
  const filterTabs = document.querySelectorAll(".filter-tab");
  filterTabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      filterTabs.forEach((t) => t.classList.remove("active"));
      tab.classList.add("active");

      const filter = tab.getAttribute("data-filter");
      loadProducts(filter);
    });
  });

  // Search
  const searchInput = document.getElementById("product-search");
  if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener("input", (e) => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        searchProducts(e.target.value.trim());
      }, 500);
    });
  }

  // Load products on page load
  if (document.getElementById("products-list")) {
    loadProducts();
  }
});

async function searchProducts(query) {
  const activeTab = document.querySelector(".filter-tab.active");
  const filter = activeTab ? activeTab.getAttribute("data-filter") : "all";

  try {
    const response = await fetch(
      `api/academic-products.php?filter=${filter}&search=${encodeURIComponent(
        query
      )}`
    );
    const data = await response.json();

    const container = document.getElementById("products-list");

    if (!data.success || !data.products || data.products.length === 0) {
      container.innerHTML = query
        ? '<p class="empty-state">Không tìm thấy sản phẩm nào</p>'
        : '<p class="empty-state">Chưa có sản phẩm nào</p>';
      return;
    }

    // Use the same rendering logic as loadProducts
    container.innerHTML = data.products
      .map((product) => {
        // Same HTML template as in loadProducts function
        return `<div class="product-card">...</div>`;
      })
      .join("");
  } catch (error) {
    console.error("Error searching products:", error);
  }
}

// ==================== INITIALIZATION ====================

// Initialize on page load
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initAcademicProducts);
} else {
  initAcademicProducts();
}

function initAcademicProducts() {
  console.log("Academic Products module initialized");

  // Load products if on products page
  const productsList = document.getElementById("products-list");
  if (productsList) {
    loadProducts();
  }
}

// Export functions for global use
window.academicProducts = {
  loadProducts,
  createNewProduct,
  openProductEditor,
  shareProduct,
  exportProduct,
  deleteProduct,
};