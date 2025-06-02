class AdminDashboard {
  constructor() {
    this.currentEditingUser = null
    this.currentEditingReward = null
    this.init()
  }

  async init() {
    const isAuthenticated = await this.checkAuth()
    if (!isAuthenticated) return

    await this.loadDashboardStats()
    await this.loadUsers()
    await this.loadRewards()
    this.setupEventListeners()
    this.startPeriodicUpdates()
  }

  async checkAuth() {
    try {
      const response = await fetch("/api/auth/me.php", {
        credentials: "include",
        cache: "no-cache",
      })
      const data = await response.json()

      if (!data.authenticated) {
        window.location.href = "/assets/index.html"
        return false
      }

      // Check admin status by trying to access admin dashboard
      const adminCheck = await fetch("/api/admin/dashboard.php?action=stats", {
        credentials: "include",
        cache: "no-cache",
      })

      if (!adminCheck.ok) {
        const errorData = await adminCheck.json().catch(() => ({ message: "Admin access required" }))
        this.showAlert(errorData.message || "Admin access required", "danger")
        setTimeout(() => {
          window.location.href = "/assets/dashboard.html"
        }, 2000)
        return false
      }

      return true
    } catch (error) {
      console.error("Auth check failed:", error)
      this.showAlert("Authentication failed", "danger")
      setTimeout(() => {
        window.location.href = "/assets/index.html"
      }, 2000)
      return false
    }
  }

  async loadDashboardStats() {
    try {
      const response = await fetch("/api/admin/dashboard.php?action=stats", {
        credentials: "include",
        cache: "no-cache",
        headers: {
          "Cache-Control": "no-cache",
          Pragma: "no-cache",
        },
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()
      console.log("Dashboard stats response:", data)

      if (data.success) {
        this.updateDashboardStats(data.data)
      } else {
        this.showAlert(data.message || "Failed to load dashboard statistics", "danger")
      }
    } catch (error) {
      console.error("Failed to load dashboard stats:", error)
      this.showAlert("Failed to load dashboard statistics", "danger")

      // Show default stats on error
      this.updateDashboardStats({
        total_users: 0,
        active_users: 0,
        new_users: 0,
        total_scans: 0,
        total_points_awarded: 0,
        total_rewards: 0,
        total_redemptions: 0,
        activities_today: 0,
        activities_this_week: 0,
      })
    }
  }

  updateDashboardStats(stats) {
    console.log("Updating dashboard stats:", stats)

    const statElements = {
      "total-users": stats.total_users || 0,
      "active-users": stats.active_users || 0,
      "new-users": stats.new_users || 0,
      "total-scans": stats.total_scans || 0,
      "total-points": stats.total_points_awarded || 0,
      "total-rewards": stats.total_rewards || 0,
      "total-redemptions": stats.total_redemptions || 0,
      "total-co2": stats.total_co2_saved || 0,
      "activities-today": stats.activities_today || 0,
      "activities-week": stats.activities_this_week || 0,
    }

    Object.entries(statElements).forEach(([id, value]) => {
      const element = document.getElementById(id)
      if (element) {
        // Animate the number change
        const currentValue = Number.parseInt(element.textContent) || 0
        this.animateNumber(element, currentValue, value)
      } else {
        console.warn(`Element with id '${id}' not found`)
      }
    })
  }

  animateNumber(element, start, end) {
    const duration = 1000
    const startTime = performance.now()

    const animate = (currentTime) => {
      const elapsed = currentTime - startTime
      const progress = Math.min(elapsed / duration, 1)

      const current = Math.floor(start + (end - start) * progress)
      element.textContent = current.toLocaleString()

      if (progress < 1) {
        requestAnimationFrame(animate)
      }
    }

    requestAnimationFrame(animate)
  }

  setupEventListeners() {
    // User management
    const addUserBtn = document.getElementById("add-user-btn")
    if (addUserBtn) {
      addUserBtn.addEventListener("click", () => this.showAddUserModal())
    }

    const saveUserBtn = document.getElementById("save-user-btn")
    if (saveUserBtn) {
      saveUserBtn.addEventListener("click", () => this.saveUser())
    }

    // Reward management
    const addRewardBtn = document.getElementById("add-reward-btn")
    if (addRewardBtn) {
      addRewardBtn.addEventListener("click", () => this.showAddRewardModal())
    }

    const saveRewardBtn = document.getElementById("save-reward-btn")
    if (saveRewardBtn) {
      saveRewardBtn.addEventListener("click", () => this.saveReward())
    }

    // Refresh button
    const refreshBtn = document.getElementById("refresh-dashboard")
    if (refreshBtn) {
      refreshBtn.addEventListener("click", () => this.refreshAll())
    }

    // Search functionality
    const userSearchInput = document.getElementById("user-search")
    if (userSearchInput) {
      userSearchInput.addEventListener("input", (e) => this.searchUsers(e.target.value))
    }

    // Form validation
    this.setupFormValidation()
  }

  setupFormValidation() {
    // Password confirmation validation
    const confirmPassword = document.getElementById("user-confirm-password")
    const password = document.getElementById("user-password")

    if (confirmPassword && password) {
      confirmPassword.addEventListener("input", () => {
        if (password.value !== confirmPassword.value) {
          confirmPassword.setCustomValidity("Passwords must match")
        } else {
          confirmPassword.setCustomValidity("")
        }
      })
    }

    // Email validation
    const emailInput = document.getElementById("user-email")
    if (emailInput) {
      emailInput.addEventListener("input", function () {
        if (!this.value.includes("@") || !this.value.includes(".")) {
          this.setCustomValidity("Please enter a valid email address")
        } else {
          this.setCustomValidity("")
        }
      })
    }
  }

  showAlert(message, type = "success") {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll(".alert")
    existingAlerts.forEach((alert) => alert.remove())

    // Create new alert
    const alertDiv = document.createElement("div")
    alertDiv.className = `alert alert-${type} mb-6`
    alertDiv.innerHTML = `
      <div class="flex items-center">
        <i class="bi bi-${type === "success" ? "check-circle" : "exclamation-triangle"} mr-2"></i>
        ${message}
      </div>
    `

    // Insert at the top of main content
    const mainContent = document.querySelector(".main-content")
    if (mainContent) {
      mainContent.insertBefore(alertDiv, mainContent.firstChild)
    }

    // Auto-remove after 5 seconds
    setTimeout(() => {
      if (alertDiv.parentNode) {
        alertDiv.style.opacity = "0"
        setTimeout(() => alertDiv.remove(), 300)
      }
    }, 5000)
  }

  async loadUsers() {
    try {
      console.log("Loading users from API...")
      const response = await fetch("/api/admin/users.php?action=list", {
        credentials: "include",
        cache: "no-cache",
        headers: {
          "Cache-Control": "no-cache",
          Pragma: "no-cache",
        },
      })

      console.log("Response status:", response.status)

      if (!response.ok) {
        const errorText = await response.text()
        console.error("HTTP error response:", errorText)
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()
      console.log("Users API response:", data)

      if (data.success) {
        console.log("Successfully loaded", data.data.length, "users")
        this.renderUsersTable(data.data)
      } else {
        console.error("API returned error:", data.message)
        this.showAlert(data.message || "Failed to load users", "danger")
        this.renderUsersTable([])
      }
    } catch (error) {
      console.error("Failed to load users:", error)
      this.showAlert("Failed to load users: " + error.message, "danger")
      // Show empty table on error
      this.renderUsersTable([])
    }
  }

  renderUsersTable(users) {
    const tbody = document.getElementById("users-table")
    if (!tbody) {
      console.error("Users table not found")
      return
    }

    console.log("Rendering users table with", users.length, "users")

    if (!users || users.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-gray-500 py-8">No users found</td></tr>'
      return
    }

    tbody.innerHTML = users
      .map((user) => {
        console.log("Rendering user:", user)
        return `
          <tr class="hover:bg-gray-50 transition-colors">
              <td class="font-medium">${user.user_id}</td>
              <td>${user.full_name || user.first_name + " " + user.last_name || "N/A"}</td>
              <td class="text-blue-600">${user.email}</td>
              <td>${user.student_id || "N/A"}</td>
              <td class="font-semibold">${user.points_balance || 0}</td>
              <td>
                  <span class="badge badge-${user.account_status === "active" ? "success" : "secondary"}">
                      ${user.account_status}
                  </span>
              </td>
              <td>
                  <div class="flex space-x-2">
                      <button class="btn btn-primary btn-sm" onclick="adminDashboard.editUser(${user.user_id})" title="Edit User">
                          <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn btn-secondary btn-sm" onclick="adminDashboard.viewUserActivity(${user.user_id})" title="View Activity">
                          <i class="bi bi-activity"></i>
                      </button>
                      <button class="btn btn-${user.account_status === "active" ? "secondary" : "primary"} btn-sm" 
                              onclick="adminDashboard.toggleUserStatus(${user.user_id}, '${user.account_status}')" 
                              title="${user.account_status === "active" ? "Suspend" : "Activate"} User">
                          <i class="bi bi-${user.account_status === "active" ? "ban" : "check"}"></i>
                      </button>
                  </div>
              </td>
          </tr>
        `
      })
      .join("")

    console.log("Users table rendered successfully")
  }

  async loadRewards() {
    try {
      const response = await fetch("/api/admin/rewards.php?action=list", {
        credentials: "include",
        cache: "no-cache",
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()

      if (data.success) {
        this.renderRewardsTable(data.data)
      } else {
        this.showAlert(data.message || "Failed to load rewards", "danger")
        this.renderRewardsTable([])
      }
    } catch (error) {
      console.error("Failed to load rewards:", error)
      this.showAlert("Failed to load rewards: " + error.message, "danger")
      this.renderRewardsTable([])
    }
  }

  renderRewardsTable(rewards) {
    const tbody = document.getElementById("rewards-table")
    if (!tbody) return

    if (!rewards || rewards.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-gray-500 py-8">No rewards found</td></tr>'
      return
    }

    tbody.innerHTML = rewards
      .map(
        (reward) => `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="font-medium">${reward.reward_id}</td>
                    <td class="font-semibold">${reward.name}</td>
                    <td>${reward.category || "General"}</td>
                    <td class="text-yellow-600 font-semibold">${reward.points_cost}</td>
                    <td>${reward.inventory ?? "∞"}</td>
                    <td class="text-green-600">${reward.total_redemptions || 0}</td>
                    <td>
                        <span class="badge badge-${reward.is_active ? "success" : "secondary"}">
                            ${reward.is_active ? "Active" : "Inactive"}
                        </span>
                    </td>
                    <td>
                        <div class="flex space-x-2">
                            <button class="btn btn-primary btn-sm" onclick="adminDashboard.editReward(${reward.reward_id})" title="Edit Reward">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-${reward.is_active ? "secondary" : "primary"} btn-sm" 
                                    onclick="adminDashboard.toggleReward(${reward.reward_id}, ${!reward.is_active})"
                                    title="${reward.is_active ? "Deactivate" : "Activate"} Reward">
                                ${reward.is_active ? '<i class="bi bi-pause"></i>' : '<i class="bi bi-play"></i>'}
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="adminDashboard.deleteReward(${reward.reward_id})" title="Delete Reward">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `,
      )
      .join("")
  }

  async loadRewardCategories() {
    try {
      const response = await fetch("/api/admin/rewards.php?action=categories", { credentials: "include" })
      const data = await response.json()

      if (data.success) {
        const categorySelect = document.getElementById("reward-category")
        if (categorySelect) {
          categorySelect.innerHTML =
            '<option value="">Select Category</option>' +
            data.data.map((cat) => `<option value="${cat.category_id}">${cat.name}</option>`).join("")
        }
      }
    } catch (error) {
      console.error("Failed to load categories:", error)
    }
  }

  showAddUserModal() {
    this.currentEditingUser = null
    const modal = new bootstrap.Modal(document.getElementById("userModal"))
    document.getElementById("userModalLabel").textContent = "Add New User"
    document.getElementById("user-form").reset()

    // Show password field for new users
    const passwordField = document.getElementById("user-password")
    if (passwordField) {
      passwordField.parentElement.style.display = "block"
      passwordField.required = true
    }

    modal.show()
  }

  showAddRewardModal() {
    this.currentEditingReward = null
    const modal = new bootstrap.Modal(document.getElementById("rewardModal"))
    document.getElementById("rewardModalLabel").textContent = "Add New Reward"
    document.getElementById("reward-form").reset()
    modal.show()
  }

  async editUser(userId) {
    try {
      const response = await fetch(`/api/admin/users.php?action=get&user_id=${userId}`, {
        credentials: "include",
        cache: "no-cache",
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()

      if (data.success) {
        this.currentEditingUser = userId
        const user = data.data

        // Populate form fields
        document.getElementById("user-email").value = user.email || ""
        document.getElementById("user-first-name").value = user.first_name || ""
        document.getElementById("user-last-name").value = user.last_name || ""
        document.getElementById("user-student-id").value = user.student_id || ""
        document.getElementById("user-points").value = user.points_balance || 0
        document.getElementById("user-status").value = user.account_status || "active"

        // Update modal title
        document.querySelector("#userModal h2").textContent = "Edit User"

        // Hide password field for existing users
        const passwordField = document.getElementById("user-password")
        if (passwordField) {
          passwordField.parentElement.style.display = "none"
          passwordField.required = false
        }

        // Show modal
        this.showModal("userModal")
      } else {
        this.showAlert(data.message || "Failed to load user data", "danger")
      }
    } catch (error) {
      console.error("Failed to load user:", error)
      this.showAlert("Failed to load user data: " + error.message, "danger")
    }
  }

  async editReward(rewardId) {
    try {
      const response = await fetch(`/api/admin/rewards.php?action=get&id=${rewardId}`, {
        credentials: "include",
        cache: "no-cache",
      })
      const data = await response.json()

      if (data.success) {
        this.currentEditingReward = rewardId
        const reward = data.data

        // Populate form fields
        document.getElementById("reward-name").value = reward.name || ""
        document.getElementById("reward-description").value = reward.description || ""
        document.getElementById("reward-points").value = reward.points_cost || 0
        document.getElementById("reward-category").value = reward.category || ""
        document.getElementById("reward-inventory").value = reward.inventory || ""

        // Update modal title
        document.querySelector("#rewardModal h2").textContent = "Edit Reward"

        // Show modal
        this.showModal("rewardModal")
      } else {
        this.showAlert("Failed to load reward data", "danger")
      }
    } catch (error) {
      console.error("Failed to load reward:", error)
      this.showAlert("Failed to load reward data", "danger")
    }
  }

  showModal(modalId) {
    const modal = document.getElementById(modalId)
    if (modal) {
      modal.classList.add("show")
      document.body.style.overflow = "hidden"
    }
  }

  hideModal(modalId) {
    const modal = document.getElementById(modalId)
    if (modal) {
      modal.classList.remove("show")
      document.body.style.overflow = "auto"
    }
  }

  async saveUser() {
    const form = document.getElementById("user-form")
    if (!form) {
      this.showAlert("Form not found", "danger")
      return
    }

    // Basic validation
    const email = document.getElementById("user-email").value.trim()
    const points = document.getElementById("user-points").value

    if (!email) {
      this.showAlert("Email is required", "danger")
      return
    }

    if (!this.currentEditingUser) {
      const password = document.getElementById("user-password").value
      if (!password || password.length < 8) {
        this.showAlert("Password must be at least 8 characters", "danger")
        return
      }
    }

    const userData = {
      email: email,
      first_name: document.getElementById("user-first-name").value.trim(),
      last_name: document.getElementById("user-last-name").value.trim(),
      student_id: document.getElementById("user-student-id").value.trim(),
      points_balance: Number.parseInt(points) || 0,
      account_status: document.getElementById("user-status").value,
    }

    // Add password for new users
    if (!this.currentEditingUser) {
      userData.password = document.getElementById("user-password").value
    }

    try {
      const url = this.currentEditingUser
        ? `/api/admin/users.php?action=update&user_id=${this.currentEditingUser}`
        : "/api/admin/users.php?action=add"

      console.log("Saving user data:", userData)

      const response = await fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Cache-Control": "no-cache",
        },
        credentials: "include",
        body: JSON.stringify(userData),
      })

      if (!response.ok) {
        const errorText = await response.text()
        throw new Error(`HTTP error! status: ${response.status} - ${errorText}`)
      }

      const result = await response.json()

      if (result.success) {
        this.showAlert(
          result.message || `User ${this.currentEditingUser ? "updated" : "added"} successfully!`,
          "success",
        )

        // Close modal and reset form
        this.hideModal("userModal")
        this.resetUserForm()

        // Reload data
        await this.loadUsers()
        await this.loadDashboardStats()
      } else {
        this.showAlert(result.message || "Failed to save user", "danger")
      }
    } catch (error) {
      console.error("Failed to save user:", error)
      this.showAlert("Network error: " + error.message, "danger")
    }
  }

  async saveReward() {
    const name = document.getElementById("reward-name").value.trim()
    const description = document.getElementById("reward-description").value.trim()
    const points = document.getElementById("reward-points").value

    if (!name || !description || !points) {
      this.showAlert("Name, description, and points cost are required", "danger")
      return
    }

    const rewardData = {
      name: name,
      description: description,
      points_cost: Number.parseInt(points),
      category: document.getElementById("reward-category").value || null,
      inventory: document.getElementById("reward-inventory").value || null,
    }

    try {
      const url = this.currentEditingReward
        ? `/api/admin/rewards.php?action=update&id=${this.currentEditingReward}`
        : "/api/admin/rewards.php?action=add"

      const response = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify(rewardData),
      })

      const result = await response.json()
      if (result.success) {
        this.showAlert(
          result.message || `Reward ${this.currentEditingReward ? "updated" : "added"} successfully!`,
          "success",
        )

        this.hideModal("rewardModal")
        this.resetRewardForm()
        await this.loadRewards()
        await this.loadDashboardStats()
      } else {
        this.showAlert(result.message || "Failed to save reward", "danger")
      }
    } catch (error) {
      console.error("Failed to save reward:", error)
      this.showAlert("Network error: " + error.message, "danger")
    }
  }

  resetUserForm() {
    this.currentEditingUser = null
    const form = document.getElementById("user-form")
    if (form) form.reset()

    // Reset modal title
    document.querySelector("#userModal h2").textContent = "Add User"

    // Show password field for new users
    const passwordField = document.getElementById("user-password")
    if (passwordField) {
      passwordField.parentElement.style.display = "block"
      passwordField.required = true
    }
  }

  resetRewardForm() {
    this.currentEditingReward = null
    const form = document.getElementById("reward-form")
    if (form) form.reset()

    // Reset modal title
    document.querySelector("#rewardModal h2").textContent = "Add Reward"
  }

  async toggleReward(rewardId, isActive) {
    try {
      const response = await fetch(`/api/admin/rewards.php?action=toggle&id=${rewardId}&active=${isActive}`, {
        method: "POST",
        credentials: "include",
      })

      const result = await response.json()
      if (result.success) {
        this.showAlert(result.message || "Reward status updated successfully", "success")
        await this.loadRewards()
      } else {
        this.showAlert(result.message || "Failed to update reward status", "danger")
      }
    } catch (error) {
      console.error("Failed to update reward status:", error)
      this.showAlert("Network error", "danger")
    }
  }

  async deleteReward(rewardId) {
    if (!confirm("Are you sure you want to delete this reward? This action cannot be undone.")) {
      return
    }

    try {
      const response = await fetch(`/api/admin/rewards.php?action=delete&id=${rewardId}`, {
        method: "POST",
        credentials: "include",
      })

      const result = await response.json()
      if (result.success) {
        this.showAlert(result.message || "Reward deleted successfully", "success")
        await this.loadRewards()
        await this.loadDashboardStats()
      } else {
        this.showAlert(result.message || "Failed to delete reward", "danger")
      }
    } catch (error) {
      console.error("Failed to delete reward:", error)
      this.showAlert("Network error", "danger")
    }
  }

  async toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === "active" ? "suspended" : "active"
    const action = newStatus === "active" ? "activate" : "suspend"

    if (!confirm(`Are you sure you want to ${action} this user?`)) {
      return
    }

    try {
      const response = await fetch(`/api/admin/users.php?action=update&user_id=${userId}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({
          account_status: newStatus,
        }),
      })

      const result = await response.json()
      if (result.success) {
        this.showAlert(`User ${action}d successfully`, "success")
        await this.loadUsers()
        await this.loadDashboardStats()
      } else {
        this.showAlert(result.message || `Failed to ${action} user`, "danger")
      }
    } catch (error) {
      console.error(`Failed to ${action} user:`, error)
      this.showAlert("Network error", "danger")
    }
  }

  viewUserActivity(userId) {
    this.showAlert(`User activity view for user ${userId} - Feature coming soon!`, "info")
  }

  searchUsers(searchTerm) {
    const rows = document.querySelectorAll("#users-table tr")
    rows.forEach((row) => {
      const text = row.textContent.toLowerCase()
      const matches = text.includes(searchTerm.toLowerCase())
      row.style.display = matches ? "" : "none"
    })
  }

  async refreshAll() {
    this.showAlert("Refreshing dashboard...", "info")

    try {
      await Promise.all([this.loadDashboardStats(), this.loadUsers(), this.loadRewards()])
      this.showAlert("Dashboard refreshed successfully", "success")
    } catch (error) {
      this.showAlert("Failed to refresh dashboard", "danger")
    }
  }

  startPeriodicUpdates() {
    // Update dashboard stats every 5 minutes
    setInterval(
      () => {
        this.loadDashboardStats()
      },
      5 * 60 * 1000,
    )
  }
}

// Initialize the admin dashboard when the page loads
document.addEventListener("DOMContentLoaded", () => {
  window.adminDashboard = new AdminDashboard()
})

// Global functions for modal management
function showModal(modalId) {
  if (window.adminDashboard) {
    window.adminDashboard.showModal(modalId)
  }
}

function hideModal(modalId) {
  if (window.adminDashboard) {
    window.adminDashboard.hideModal(modalId)
  }
}

// Global tab management
function showTab(tabName, element) {
  // Hide all tab contents
  document.querySelectorAll(".tab-content").forEach((tab) => {
    tab.classList.remove("active")
  })

  // Remove active class from all nav links
  document.querySelectorAll(".nav-link").forEach((link) => {
    link.classList.remove("active")
  })

  // Show selected tab
  document.getElementById(tabName).classList.add("active")
  element.classList.add("active")
}
