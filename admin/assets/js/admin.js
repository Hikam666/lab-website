/**
 * ADMIN.JS
 * ========
 * JavaScript functions untuk Admin Panel CMS
 */

// ========================================
// GENERAL UTILITIES
// ========================================

/**
 * Show loading spinner
 */
function showLoading() {
    const loadingHtml = `
        <div id="loading-overlay" style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        ">
            <div class="spinner-border text-light" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', loadingHtml);
}

/**
 * Hide loading spinner
 */
function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.remove();
    }
}

/**
 * Show toast notification
 * @param {string} message 
 * @param {string} type - 'success', 'error', 'warning', 'info'
 */
function showToast(message, type = 'info') {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0" 
             role="alert" 
             aria-live="assertive" 
             aria-atomic="true"
             style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = document.querySelector('.toast:last-child');
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();
    
    // Remove toast element after hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

/**
 * Confirm dialog
 * @param {string} message 
 * @param {function} onConfirm 
 * @param {function} onCancel 
 */
function confirmDialog(message, onConfirm, onCancel = null) {
    if (confirm(message)) {
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    } else {
        if (typeof onCancel === 'function') {
            onCancel();
        }
    }
}

/**
 * Delete confirmation with custom styling
 * @param {string} itemName 
 * @param {string} deleteUrl 
 */
function confirmDelete(itemName, deleteUrl) {
    const message = `Apakah Anda yakin ingin menghapus "${itemName}"?\n\nTindakan ini tidak dapat dibatalkan.`;
    
    if (confirm(message)) {
        showLoading();
        window.location.href = deleteUrl;
    }
}

// ========================================
// FORM HELPERS
// ========================================

/**
 * Auto-generate slug from title
 * @param {string} titleInputId 
 * @param {string} slugInputId 
 */
function autoGenerateSlug(titleInputId, slugInputId) {
    const titleInput = document.getElementById(titleInputId);
    const slugInput = document.getElementById(slugInputId);
    
    if (titleInput && slugInput) {
        titleInput.addEventListener('input', function() {
            const slug = this.value
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim();
            
            slugInput.value = slug;
        });
    }
}

/**
 * Character counter for textarea
 * @param {string} textareaId 
 * @param {number} maxLength 
 */
function initCharCounter(textareaId, maxLength) {
    const textarea = document.getElementById(textareaId);
    
    if (textarea) {
        const counter = document.createElement('div');
        counter.className = 'text-muted small mt-1';
        counter.id = textareaId + '-counter';
        textarea.parentNode.appendChild(counter);
        
        function updateCounter() {
            const remaining = maxLength - textarea.value.length;
            counter.textContent = `${textarea.value.length} / ${maxLength} karakter`;
            counter.style.color = remaining < 0 ? '#EF4444' : '#6B7280';
        }
        
        textarea.addEventListener('input', updateCounter);
        updateCounter();
    }
}

/**
 * Image preview before upload
 * @param {string} inputId 
 * @param {string} previewId 
 */
function initImagePreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    
    if (input && preview) {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                // Check file type
                if (!file.type.startsWith('image/')) {
                    showToast('File harus berupa gambar!', 'error');
                    input.value = '';
                    return;
                }
                
                // Check file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showToast('Ukuran file maksimal 5MB!', 'error');
                    input.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    }
}

/**
 * Form validation before submit
 * @param {string} formId 
 */
function validateForm(formId) {
    const form = document.getElementById(formId);
    
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                showToast('Mohon lengkapi semua field yang wajib diisi!', 'error');
            }
            
            form.classList.add('was-validated');
        });
    }
}

// ========================================
// TABLE HELPERS
// ========================================

/**
 * Search/filter table
 * @param {string} searchInputId 
 * @param {string} tableId 
 */
function initTableSearch(searchInputId, tableId) {
    const searchInput = document.getElementById(searchInputId);
    const table = document.getElementById(tableId);
    
    if (searchInput && table) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
}

/**
 * Select all checkboxes
 * @param {string} masterCheckboxId 
 * @param {string} checkboxClass 
 */
function initSelectAll(masterCheckboxId, checkboxClass) {
    const masterCheckbox = document.getElementById(masterCheckboxId);
    
    if (masterCheckbox) {
        masterCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.' + checkboxClass);
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = masterCheckbox.checked;
            });
        });
    }
}

/**
 * Bulk action handler
 * @param {string} buttonId 
 * @param {string} checkboxClass 
 * @param {function} callback 
 */
function initBulkAction(buttonId, checkboxClass, callback) {
    const button = document.getElementById(buttonId);
    
    if (button) {
        button.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.' + checkboxClass + ':checked');
            const selectedIds = Array.from(checkboxes).map(cb => cb.value);
            
            if (selectedIds.length === 0) {
                showToast('Pilih minimal 1 item!', 'warning');
                return;
            }
            
            if (typeof callback === 'function') {
                callback(selectedIds);
            }
        });
    }
}

// ========================================
// FILE UPLOAD
// ========================================

/**
 * Initialize drag & drop file upload
 * @param {string} dropzoneId 
 * @param {string} fileInputId 
 * @param {function} onUpload 
 */
function initDragDropUpload(dropzoneId, fileInputId, onUpload) {
    const dropzone = document.getElementById(dropzoneId);
    const fileInput = document.getElementById(fileInputId);
    
    if (dropzone && fileInput) {
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Highlight on drag over
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, function() {
                dropzone.classList.add('border-primary', 'bg-light');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, function() {
                dropzone.classList.remove('border-primary', 'bg-light');
            }, false);
        });
        
        // Handle dropped files
        dropzone.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            handleFiles(files);
        }, false);
        
        // Handle click to browse
        dropzone.addEventListener('click', function() {
            fileInput.click();
        });
        
        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });
        
        function handleFiles(files) {
            if (typeof onUpload === 'function') {
                onUpload(files);
            }
        }
    }
}

// ========================================
// AUTO-DISMISS ALERTS
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        });
    }, 5000);
});

// ========================================
// EXPORT
// ========================================

window.adminJS = {
    showLoading,
    hideLoading,
    showToast,
    confirmDialog,
    confirmDelete,
    autoGenerateSlug,
    initCharCounter,
    initImagePreview,
    validateForm,
    initTableSearch,
    initSelectAll,
    initBulkAction,
    initDragDropUpload
};