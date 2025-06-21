/**
 * Comments System JavaScript
 * Tạo bởi: MiniMax Agent
 */

// Current section data
let currentSection = {
    type: '',
    category: '',
    subject: null,
    id: null
};

// Comments data
let comments = [];
let commentsOffset = 0;
const commentsLimit = 20;

// Initialize comments system
function initCommentsSystem(sectionType, sectionCategory, sectionSubject = null) {
    currentSection = {
        type: sectionType,
        category: sectionCategory,
        subject: sectionSubject,
        id: null
    };
    
    setupCommentsEventListeners();
    loadComments();
}

// Setup event listeners for comments
function setupCommentsEventListeners() {
    // Comment input character counter
    const commentInput = document.getElementById('commentInput');
    const charCounter = document.getElementById('charCounter');
    const submitBtn = document.getElementById('submitCommentBtn');
    
    if (commentInput) {
        commentInput.addEventListener('input', function() {
            const length = this.value.length;
            charCounter.textContent = `${length}/2000`;
            submitBtn.disabled = length === 0 || length > 2000;
            
            if (length > 1800) {
                charCounter.style.color = '#d32f2f';
            } else if (length > 1500) {
                charCounter.style.color = '#ff9800';
            } else {
                charCounter.style.color = '#666';
            }
        });
    }
    
    // Submit comment
    if (submitBtn) {
        submitBtn.addEventListener('click', submitComment);
    }
    
    // Section reaction buttons
    const reactionButtons = document.querySelectorAll('#sectionReactionButtons .reaction-btn');
    reactionButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const reaction = this.dataset.reaction;
            toggleSectionReaction(reaction);
        });
    });
}

// Load comments for current section
async function loadComments(append = false) {
    if (!append) {
        showCommentsLoading(true);
        commentsOffset = 0;
    }
    
    try {
        const params = new URLSearchParams({
            action: 'get_comments',
            section_type: currentSection.type,
            section_category: currentSection.category,
            limit: commentsLimit,
            offset: commentsOffset
        });
        
        if (currentSection.subject) {
            params.append('section_subject', currentSection.subject);
        }
        
        const response = await fetch(`${API_BASE}/comments.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            currentSection.id = data.section.id;
            
            if (append) {
                comments = [...comments, ...data.comments];
            } else {
                comments = data.comments;
            }
            
            renderComments(append);
            updateCommentsStats(data.stats);
            updateSectionReactions(data.section_reactions, data.user_section_reaction);
            updateCommentsUI();
            
            // Show load more if there are more comments
            const loadMoreBtn = document.getElementById('loadMoreComments');
            if (data.comments.length === commentsLimit) {
                loadMoreBtn.style.display = 'block';
            } else {
                loadMoreBtn.style.display = 'none';
            }
        } else {
            console.error('Error loading comments:', data.message);
        }
    } catch (error) {
        console.error('Error loading comments:', error);
    } finally {
        showCommentsLoading(false);
    }
}

// Render comments
function renderComments(append = false) {
    const commentsList = document.getElementById('commentsList');
    
    if (!append) {
        commentsList.innerHTML = '';
    }
    
    const startIndex = append ? commentsOffset : 0;
    const endIndex = comments.length;
    
    for (let i = startIndex; i < endIndex; i++) {
        const comment = comments[i];
        const commentElement = createCommentElement(comment);
        commentsList.appendChild(commentElement);
    }
    
    // Show comments section
    document.getElementById('commentsSection').style.display = 'block';
}

// Create comment element
function createCommentElement(comment) {
    const template = document.getElementById('commentTemplate');
    const element = template.content.cloneNode(true);
    
    const commentItem = element.querySelector('.comment-item');
    commentItem.dataset.commentId = comment.id;
    
    // Avatar
    const avatar = element.querySelector('.comment-user-emoji');
    avatar.textContent = getAvatarEmoji(comment.avatar_icon);
    
    // Author info
    element.querySelector('.comment-author').textContent = comment.display_name;
    
    const badge = element.querySelector('.comment-badge');
    if (comment.admin_badge) {
        badge.textContent = comment.admin_badge;
        badge.style.display = 'inline-block';
    } else {
        badge.style.display = 'none';
    }
    
    // Time
    element.querySelector('.comment-time').textContent = formatTime(comment.created_at);
    
    // Edited indicator
    if (comment.is_edited == 1) {
        element.querySelector('.comment-edited').style.display = 'inline';
    }
    
    // Content
    element.querySelector('.comment-text').textContent = comment.content;
    
    // Reactions
    updateCommentReactions(element, comment.reactions, comment.user_reaction);
    
    // Control buttons
    setupCommentControls(element, comment);
    
    // Replies
    if (comment.replies && comment.replies.length > 0) {
        const repliesContainer = element.querySelector('.replies');
        repliesContainer.style.display = 'block';
        
        comment.replies.forEach(reply => {
            const replyElement = createCommentElement(reply);
            repliesContainer.appendChild(replyElement);
        });
    }
    
    return element;
}

// Setup comment controls
function setupCommentControls(element, comment) {
    const currentUser = getCurrentUser();
    const isOwner = currentUser && currentUser.id == comment.user_id;
    const isAdminUser = isAdmin();
    
    // Show/hide control buttons
    if (isOwner) {
        element.querySelector('.edit-btn').style.display = 'inline-block';
        element.querySelector('.delete-btn').style.display = 'inline-block';
    }
    
    if (isAdminUser && !isOwner) {
        element.querySelector('.delete-btn').style.display = 'inline-block';
        element.querySelector('.delete-btn').textContent = 'Xóa (Admin)';
    }
    
    // Reply button
    const replyBtn = element.querySelector('.reply-btn');
    if (currentUser) {
        replyBtn.addEventListener('click', () => showReplyForm(element, comment.id));
    } else {
        replyBtn.addEventListener('click', () => showAuthModal('login'));
    }
    
    // Edit button
    const editBtn = element.querySelector('.edit-btn');
    editBtn.addEventListener('click', () => showEditForm(element, comment));
    
    // Delete button
    const deleteBtn = element.querySelector('.delete-btn');
    deleteBtn.addEventListener('click', () => deleteComment(comment.id));
    
    // Reaction buttons
    const reactionBtns = element.querySelectorAll('.comment-reaction-btn');
    reactionBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            if (currentUser) {
                toggleCommentReaction(comment.id, btn.dataset.reaction);
            } else {
                showAuthModal('login');
            }
        });
    });
    
    // Reply form
    setupReplyForm(element, comment.id);
    
    // Edit form
    setupEditForm(element, comment.id);
}

// Update comment reactions
function updateCommentReactions(element, reactions, userReaction) {
    const reactionBtns = element.querySelectorAll('.comment-reaction-btn');
    
    reactionBtns.forEach(btn => {
        const reactionType = btn.dataset.reaction;
        const count = reactions[reactionType] || 0;
        
        btn.querySelector('.reaction-count').textContent = count;
        
        // Highlight user's reaction
        if (userReaction === reactionType) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
        
        // Hide button if count is 0 and not user's reaction
        if (count === 0 && userReaction !== reactionType) {
            btn.style.display = 'none';
        } else {
            btn.style.display = 'flex';
        }
    });
}

// Show reply form
function showReplyForm(element, parentId) {
    // Hide other forms
    hideAllForms();
    
    const replyForm = element.querySelector('.reply-form');
    replyForm.style.display = 'block';
    
    const replyInput = replyForm.querySelector('.reply-input');
    replyInput.focus();
}

// Show edit form
function showEditForm(element, comment) {
    // Hide other forms
    hideAllForms();
    
    const editForm = element.querySelector('.edit-form');
    const editInput = editForm.querySelector('.edit-input');
    
    editInput.value = comment.content;
    editForm.style.display = 'block';
    editInput.focus();
    
    // Update character counter
    const charCounter = editForm.querySelector('.char-counter-edit');
    charCounter.textContent = `${comment.content.length}/2000`;
}

// Setup reply form
function setupReplyForm(element, parentId) {
    const replyForm = element.querySelector('.reply-form');
    const replyInput = replyForm.querySelector('.reply-input');
    const charCounter = replyForm.querySelector('.char-counter-reply');
    const submitBtn = replyForm.querySelector('.submit-reply-btn');
    const cancelBtn = replyForm.querySelector('.cancel-reply-btn');
    
    // Character counter
    replyInput.addEventListener('input', function() {
        const length = this.value.length;
        charCounter.textContent = `${length}/2000`;
        submitBtn.disabled = length === 0 || length > 2000;
    });
    
    // Submit reply
    submitBtn.addEventListener('click', () => {
        const content = replyInput.value.trim();
        if (content) {
            submitReply(parentId, content, replyForm);
        }
    });
    
    // Cancel reply
    cancelBtn.addEventListener('click', () => {
        replyForm.style.display = 'none';
        replyInput.value = '';
        charCounter.textContent = '0/2000';
    });
}

// Setup edit form
function setupEditForm(element, commentId) {
    const editForm = element.querySelector('.edit-form');
    const editInput = editForm.querySelector('.edit-input');
    const charCounter = editForm.querySelector('.char-counter-edit');
    const submitBtn = editForm.querySelector('.submit-edit-btn');
    const cancelBtn = editForm.querySelector('.cancel-edit-btn');
    
    // Character counter
    editInput.addEventListener('input', function() {
        const length = this.value.length;
        charCounter.textContent = `${length}/2000`;
        submitBtn.disabled = length === 0 || length > 2000;
    });
    
    // Submit edit
    submitBtn.addEventListener('click', () => {
        const content = editInput.value.trim();
        if (content) {
            submitEdit(commentId, content, element);
        }
    });
    
    // Cancel edit
    cancelBtn.addEventListener('click', () => {
        editForm.style.display = 'none';
    });
}

// Hide all forms
function hideAllForms() {
    document.querySelectorAll('.reply-form, .edit-form').forEach(form => {
        form.style.display = 'none';
    });
}

// Update comments UI based on auth status
function updateCommentsUI() {
    const currentUser = getCurrentUser();
    const loginPrompt = document.getElementById('loginPrompt');
    const commentForm = document.getElementById('commentForm');
    const userAvatar = document.getElementById('commentUserAvatar');
    
    if (currentUser) {
        loginPrompt.style.display = 'none';
        commentForm.style.display = 'block';
        userAvatar.textContent = getAvatarEmoji(currentUser.avatar_icon);
    } else {
        loginPrompt.style.display = 'block';
        commentForm.style.display = 'none';
    }
}

// Update comments stats
function updateCommentsStats(stats) {
    document.getElementById('commentsCount').textContent = stats.total_comments || 0;
    document.getElementById('uniqueCommenters').textContent = stats.unique_users || 0;
}

// Update section reactions
function updateSectionReactions(reactions, userReaction) {
    const reactionBtns = document.querySelectorAll('#sectionReactionButtons .reaction-btn');
    
    reactionBtns.forEach(btn => {
        const reactionType = btn.dataset.reaction;
        const count = reactions[reactionType] || 0;
        
        btn.querySelector('.reaction-count').textContent = count;
        
        // Highlight user's reaction
        if (userReaction === reactionType) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
}

// Submit comment
async function submitComment() {
    const input = document.getElementById('commentInput');
    const content = input.value.trim();
    
    if (!content) return;
    
    const data = {
        section_type: currentSection.type,
        section_category: currentSection.category,
        section_subject: currentSection.subject,
        content: content
    };
    
    try {
        const response = await fetch(`${API_BASE}/comments.php?action=add_comment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            input.value = '';
            document.getElementById('charCounter').textContent = '0/2000';
            document.getElementById('submitCommentBtn').disabled = true;
            
            // Reload comments
            loadComments();
        } else {
            alert(result.message || 'Có lỗi xảy ra khi gửi bình luận');
        }
    } catch (error) {
        console.error('Error submitting comment:', error);
        alert('Có lỗi xảy ra khi gửi bình luận');
    }
}

// Submit reply
async function submitReply(parentId, content, form) {
    const data = {
        section_type: currentSection.type,
        section_category: currentSection.category,
        section_subject: currentSection.subject,
        content: content,
        parent_comment_id: parentId
    };
    
    try {
        const response = await fetch(`${API_BASE}/comments.php?action=add_comment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            form.style.display = 'none';
            form.querySelector('.reply-input').value = '';
            form.querySelector('.char-counter-reply').textContent = '0/2000';
            
            // Reload comments
            loadComments();
        } else {
            alert(result.message || 'Có lỗi xảy ra khi gửi trả lời');
        }
    } catch (error) {
        console.error('Error submitting reply:', error);
        alert('Có lỗi xảy ra khi gửi trả lời');
    }
}

// Submit edit
async function submitEdit(commentId, content, element) {
    const data = {
        comment_id: commentId,
        content: content
    };
    
    try {
        const response = await fetch(`${API_BASE}/comments.php?action=edit_comment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update comment text
            element.querySelector('.comment-text').textContent = content;
            element.querySelector('.comment-edited').style.display = 'inline';
            element.querySelector('.edit-form').style.display = 'none';
        } else {
            alert(result.message || 'Có lỗi xảy ra khi chỉnh sửa bình luận');
        }
    } catch (error) {
        console.error('Error editing comment:', error);
        alert('Có lỗi xảy ra khi chỉnh sửa bình luận');
    }
}

// Delete comment
async function deleteComment(commentId) {
    if (!confirm('Bạn có chắc chắn muốn xóa bình luận này?')) {
        return;
    }
    
    const data = {
        comment_id: commentId
    };
    
    try {
        const response = await fetch(`${API_BASE}/comments.php?action=delete_comment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reload comments
            loadComments();
        } else {
            alert(result.message || 'Có lỗi xảy ra khi xóa bình luận');
        }
    } catch (error) {
        console.error('Error deleting comment:', error);
        alert('Có lỗi xảy ra khi xóa bình luận');
    }
}

// Toggle comment reaction
async function toggleCommentReaction(commentId, reactionType) {
    const data = {
        comment_id: commentId,
        reaction_type: reactionType
    };
    
    try {
        const response = await fetch(`${API_BASE}/comments.php?action=react_comment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reload comments to update reactions
            loadComments();
        } else {
            alert(result.message || 'Có lỗi xảy ra khi thả cảm xúc');
        }
    } catch (error) {
        console.error('Error reacting to comment:', error);
        alert('Có lỗi xảy ra khi thả cảm xúc');
    }
}

// Toggle section reaction
async function toggleSectionReaction(reactionType) {
    if (!isLoggedIn()) {
        showAuthModal('login');
        return;
    }
    
    const data = {
        section_type: currentSection.type,
        section_category: currentSection.category,
        section_subject: currentSection.subject,
        reaction_type: reactionType
    };
    
    try {
        const response = await fetch(`${API_BASE}/comments.php?action=react_section`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reload comments to update section reactions
            loadComments();
        } else {
            alert(result.message || 'Có lỗi xảy ra khi thả cảm xúc');
        }
    } catch (error) {
        console.error('Error reacting to section:', error);
        alert('Có lỗi xảy ra khi thả cảm xúc');
    }
}

// Load more comments
function loadMoreComments() {
    commentsOffset += commentsLimit;
    loadComments(true);
}

// Show comments loading
function showCommentsLoading(show = true) {
    const loading = document.getElementById('commentsLoading');
    if (loading) {
        loading.style.display = show ? 'flex' : 'none';
    }
}

// Format time
function formatTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    
    if (minutes < 1) return 'Vừa xong';
    if (minutes < 60) return `${minutes} phút trước`;
    if (hours < 24) return `${hours} giờ trước`;
    if (days < 7) return `${days} ngày trước`;
    
    return date.toLocaleDateString('vi-VN');
}

// Get avatar emoji (reuse from auth.js)
function getAvatarEmoji(iconName) {
    if (typeof window.getAvatarEmoji === 'function') {
        return window.getAvatarEmoji(iconName);
    }
    
    // Fallback if auth.js not loaded
    const avatar = availableAvatars.find(a => a.icon_name === iconName);
    return avatar ? avatar.icon_emoji : '👤';
}

// Reload comments (called from auth.js)
function reloadComments() {
    updateCommentsUI();
    if (currentSection.type) {
        loadComments();
    }
}

// Export functions
window.initCommentsSystem = initCommentsSystem;
window.reloadComments = reloadComments;
window.loadMoreComments = loadMoreComments;
