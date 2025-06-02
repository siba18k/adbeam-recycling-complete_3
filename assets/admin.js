// Add this to the beginning of each protected page's JS file
document.addEventListener('DOMContentLoaded', () => {
    checkAuthStatus().then(isAuthenticated => {
        if (!isAuthenticated) {
            window.location.href = 'index.html';
        }
    });
});

async function checkAuthStatus() {
    try {
        const response = await fetch('/api/auth/me.php', {
            credentials: 'include'
        });
        
        if (response.ok) {
            const data = await response.json();
            return data.authenticated;
        }
        return false;
    } catch (error) {
        console.error('Auth check failed:', error);
        return false;
    }
}


class AdminAPI {
    static BASE_URL = '/api/admin';
    static CSRF_TOKEN = null;

    static async init() {
        await this.fetchCSRFToken();
        await this.checkAdminSession();
    }

    static async fetchCSRFToken() {
        try {
            const response = await fetch(`${this.BASE_URL}/csrf`, {
                credentials: 'include'
            });
            if (!response.ok) throw new Error('CSRF token fetch failed');
            const data = await response.json();
            this.CSRF_TOKEN = data.token;
        } catch (error) {
            console.error('Failed to fetch CSRF token:', error);
            this.showError('Session verification failed. Please refresh the page.');
        }
    }

    static async checkAdminSession() {
        try {
            const response = await fetch(`${this.BASE_URL}/check_session`, {
                credentials: 'include'
            });
            if (!response.ok) {
                window.location.href = '/admin/login';
            }
        } catch (error) {
            console.error('Session check failed:', error);
            window.location.href = '/admin/login';
        }
    }

    static async getRewards() {
        try {
            const response = await fetch(`${this.BASE_URL}/rewards.php?action=list`, {
                credentials: 'include',
                headers: {
                    'X-CSRF-Token': this.CSRF_TOKEN
                }
            });
            return await response.json();
        } catch (error) {
            console.error('Failed to fetch rewards:', error);
            this.showError('Failed to load rewards. Please try again.');
            return [];
        }
    }

    static async addReward(rewardData) {
        try {
            const response = await fetch(`${this.BASE_URL}/rewards.php?action=add`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.CSRF_TOKEN
                },
                credentials: 'include',
                body: JSON.stringify(rewardData)
            });
            return await response.json();
        } catch (error) {
            console.error('Failed to add reward:', error);
            return { success: false, message: 'Network error. Please try again.' };
        }
    }

    static async updateReward(rewardId, rewardData) {
        try {
            const response = await fetch(`${this.BASE_URL}/rewards.php?action=update&id=${rewardId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.CSRF_TOKEN
                },
                credentials: 'include',
                body: JSON.stringify(rewardData)
            });
            return await response.json();
        } catch (error) {
            console.error('Failed to update reward:', error);
            return { success: false, message: 'Network error. Please try again.' };
        }
    }

    static async toggleRewardStatus(rewardId, isActive) {
        try {
            const response = await fetch(`${this.BASE_URL}/rewards.php?action=toggle_status&id=${rewardId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.CSRF_TOKEN
                },
                credentials: 'include',
                body: JSON.stringify({ is_active: isActive })
            });
            return await response.json();
        } catch (error) {
            console.error('Failed to toggle reward status:', error);
            return { success: false, message: 'Network error. Please try again.' };
        }
    }

    static showError(message) {
        const alertsContainer = document.getElementById('admin-alerts');
        if (alertsContainer) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            alertsContainer.appendChild(alert);
        }
    }

    static showSuccess(message) {
        const alertsContainer = document.getElementById('admin-alerts');
        if (alertsContainer) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            alertsContainer.appendChild(alert);
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', async () => {
    await AdminAPI.init();
    
    // Initialize Bootstrap components
    const addRewardModal = new bootstrap.Modal(document.getElementById('addRewardModal'));
    
    // Load rewards data
    await loadRewards();
    
    // Add Reward button click handler
    document.getElementById('add-reward-btn').addEventListener('click', () => {
        addRewardModal.show();
    });
    
    // Save Reward button click handler
    document.getElementById('save-reward-btn').addEventListener('click', async () => {
        const form = document.getElementById('reward-form');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        const rewardData = {
            name: document.getElementById('reward-name').value,
            description: document.getElementById('reward-description').value,
            category: document.getElementById('reward-category').value,
            points_cost: parseInt(document.getElementById('reward-points').value),
            inventory: document.getElementById('reward-inventory').value || null,
            image_url: document.getElementById('reward-image').value || null
        };
        
        const result = await AdminAPI.addReward(rewardData);
        if (result.success) {
            AdminAPI.showSuccess('Reward added successfully!');
            addRewardModal.hide();
            form.reset();
            form.classList.remove('was-validated');
            await loadRewards();
        } else {
            AdminAPI.showError(result.message || 'Failed to add reward');
        }
    });
    
    // Initialize other admin UI components
    initRewardTableActions();
});

async function loadRewards() {
    const rewards = await AdminAPI.getRewards();
    const tbody = document.getElementById('rewards-list');
    
    if (tbody) {
        tbody.innerHTML = rewards.map(reward => `
            <tr data-id="${reward.reward_id}">
                <td>${reward.reward_id}</td>
                <td>${reward.name}</td>
                <td>${reward.category}</td>
                <td>${reward.points_cost}</td>
                <td>${reward.inventory ?? '∞'}</td>
                <td>
                    <span class="badge ${reward.is_active ? 'bg-success' : 'bg-secondary'}">
                        ${reward.is_active ? 'Active' : 'Inactive'}
                    </span>
                    ${reward.inventory_status === 'low' ? '<span class="badge bg-warning ms-1">Low Stock</span>' : ''}
                    ${reward.inventory_status === 'out_of_stock' ? '<span class="badge bg-danger ms-1">Out of Stock</span>' : ''}
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary edit-btn" data-id="${reward.reward_id}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn ${reward.is_active ? 'btn-outline-danger' : 'btn-outline-success'} toggle-btn" 
                                data-id="${reward.reward_id}" 
                                data-active="${reward.is_active}">
                            ${reward.is_active ? '<i class="bi bi-x"></i>' : '<i class="bi bi-check"></i>'}
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }
}

function initRewardTableActions() {
    // Edit button handlers
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const rewardId = e.currentTarget.dataset.id;
            // Implement edit functionality
            console.log('Edit reward:', rewardId);
        });
    });
    
    // Toggle status button handlers
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const rewardId = e.currentTarget.dataset.id;
            const isActive = e.currentTarget.dataset.active === 'true';
            
            const result = await AdminAPI.toggleRewardStatus(rewardId, !isActive);
            if (result.success) {
                AdminAPI.showSuccess(`Reward ${!isActive ? 'activated' : 'deactivated'} successfully`);
                await loadRewards();
            } else {
                AdminAPI.showError(result.message || 'Failed to update reward status');
            }
        });
    });
}

// Password confirmation validation
document.getElementById('signupConfirmPassword')?.addEventListener('input', function() {
    const password = document.getElementById('signupPassword');
    const confirm = this;
    
    if (password.value !== confirm.value) {
        confirm.setCustomValidity("Passwords must match");
    } else {
        confirm.setCustomValidity("");
    }
});

// College email validation
document.getElementById('signupEmail')?.addEventListener('input', function() {
    if (!this.value.endsWith('.edu')) {
        this.setCustomValidity("Please use a college email address");
    } else {
        this.setCustomValidity("");
    }
});
