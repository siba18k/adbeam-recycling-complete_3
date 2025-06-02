import { Chart } from "@/components/ui/chart"
// Dashboard functionality for enhanced database
class Dashboard {
  constructor() {
    this.user = null
    this.charts = {}
    this.init()
  }

  async init() {
    await this.checkAuth()
    await this.loadDashboardData()
    this.setupEventListeners()
    this.startPeriodicUpdates()
  }

  async checkAuth() {
    try {
      const response = await fetch("/api/auth/me.php", { credentials: "include" })
      const data = await response.json()

      if (!data.authenticated) {
        window.location.href = "/assets/index.html"
        return
      }

      this.user = data.user
      this.updateUserInfo()
    } catch (error) {
      console.error("Auth check failed:", error)
      window.location.href = "/assets/index.html"
    }
  }

  async loadDashboardData() {
    try {
      console.log("Loading dashboard data...")

      // Load all dashboard data in parallel with timeout
      const timeout = 10000 // 10 seconds
      const [statsResponse, activityResponse] = await Promise.all([
        Promise.race([
          fetch("/api/user/stats.php", { credentials: "include" }),
          new Promise((_, reject) => setTimeout(() => reject(new Error("Stats API timeout")), timeout)),
        ]),
        Promise.race([
          fetch("/api/user/activity.php", { credentials: "include" }),
          new Promise((_, reject) => setTimeout(() => reject(new Error("Activity API timeout")), timeout)),
        ]),
      ])

      // Check if responses are ok
      if (!statsResponse.ok) {
        throw new Error(`Stats API error: ${statsResponse.status} ${statsResponse.statusText}`)
      }
      if (!activityResponse.ok) {
        throw new Error(`Activity API error: ${activityResponse.status} ${activityResponse.statusText}`)
      }

      const stats = await statsResponse.json()
      const activity = await activityResponse.json()

      console.log("Stats response:", stats)
      console.log("Activity response:", activity)

      if (stats.success) {
        this.updateDashboard(stats.data)
      } else {
        console.error("Stats API returned error:", stats.message)
        this.showError(stats.message || "Failed to load user statistics")
      }

      if (activity.success) {
        this.updateRecentActivity(activity.activity)
      } else {
        console.error("Activity API returned error:", activity.message)
        this.showError(activity.message || "Failed to load user activity")
      }

      await this.loadRewardsPreview()
    } catch (error) {
      console.error("Failed to load dashboard data:", error)
      this.showError("Failed to load dashboard data: " + error.message)
    }
  }

  updateDashboard(data) {
    console.log("Updating dashboard with data:", data)

    // Update user info
    if (data.user_info) {
      this.updateUserInfo(data.user_info)
    }

    // Update stats cards
    this.updateStatsCards(data)

    // Update level progress
    if (data.level) {
      this.updateLevelProgress(data.level)
    }

    // Update environmental impact
    if (data.environmental_impact) {
      this.updateEnvironmentalImpact(data.environmental_impact)
    }

    // Update ranking
    if (data.ranking) {
      this.updateRanking(data.ranking)
    }

    // Update material breakdown
    if (data.material_breakdown) {
      this.updateMaterialBreakdown(data.material_breakdown)
    }

    // Update achievements
    if (data.achievements) {
      this.updateAchievements(data.achievements)
    }
  }

  updateUserInfo() {
    if (!this.user) return

    const elements = {
      "user-name": this.user.name || "User",
      "user-email": this.user.email || "",
      "user-points": this.user.points || 0,
    }

    Object.entries(elements).forEach(([id, value]) => {
      const element = document.getElementById(id)
      if (element) element.textContent = value
    })
  }

  updateStatsCards(data) {
    const statElements = {
      "points-balance": data.points?.balance || 0,
      "total-points-earned": data.points?.total_earned || 0,
      "total-activities": data.activities?.total || 0,
      "activities-today": data.activities?.today || 0,
      "activities-this-week": data.activities?.this_week || 0,
      "activities-this-month": data.activities?.this_month || 0,
      "total-redemptions": data.redemptions?.total || 0,
      "points-spent": data.redemptions?.points_spent || 0,
      "co2-saved": `${data.environmental_impact?.total_co2_saved || 0} kg`,
      "current-level": data.level?.current_level || 1,
      "level-name": data.level?.level_name || "Eco Newbie",
      "user-rank": `#${data.ranking?.position || "N/A"}`,
      "total-users": data.ranking?.total_users || 0,
    }

    Object.entries(statElements).forEach(([id, value]) => {
      const element = document.getElementById(id)
      if (element) {
        element.textContent = value
        // Add animation class for updated values
        element.classList.add("stat-updated")
        setTimeout(() => element.classList.remove("stat-updated"), 1000)
      } else {
        console.warn(`Element with id '${id}' not found`)
      }
    })
  }

  updateLevelProgress(levelData) {
    const progressBar = document.getElementById("level-progress")
    if (progressBar) {
      progressBar.style.width = `${levelData.progress_percentage}%`
      progressBar.setAttribute("aria-valuenow", levelData.progress_percentage)
    }

    const progressText = document.getElementById("level-progress-text")
    if (progressText) {
      progressText.textContent = `${levelData.points_in_level}/${levelData.points_in_level + levelData.points_to_next} points`
    }

    const nextLevelInfo = document.getElementById("next-level-info")
    if (nextLevelInfo) {
      nextLevelInfo.textContent = `${levelData.points_to_next} points to level ${levelData.current_level + 1}`
    }
  }

  updateEnvironmentalImpact(impactData) {
    const co2Element = document.getElementById("total-co2-saved")
    if (co2Element) {
      co2Element.textContent = `${impactData.total_co2_saved} kg`
    }

    // Update environmental impact visualization if exists
    const impactChart = document.getElementById("environmental-impact-chart")
    if (impactChart && impactData.total_co2_saved > 0) {
      this.createEnvironmentalImpactChart(impactData)
    }
  }

  updateRanking(rankingData) {
    const rankElement = document.getElementById("user-ranking")
    if (rankElement) {
      rankElement.innerHTML = `
        <div class="ranking-info">
          <span class="rank-position">#${rankingData.position}</span>
          <span class="rank-total">of ${rankingData.total_users} users</span>
        </div>
      `
    }
  }

  updateMaterialBreakdown(materials) {
    const container = document.getElementById("material-breakdown")
    if (!container || !materials.length) return

    container.innerHTML = materials
      .map(
        (material) => `
      <div class="material-item">
        <div class="material-icon">
          <i class="bi bi-${this.getMaterialIcon(material.material_type)}"></i>
        </div>
        <div class="material-info">
          <div class="material-name">${material.material_type}</div>
          <div class="material-stats">
            <span class="count">${material.count} items</span>
            <span class="points">${material.points} points</span>
            <span class="co2">${material.co2_saved} kg CO₂</span>
          </div>
        </div>
      </div>
    `,
      )
      .join("")
  }

  updateAchievements(achievements) {
    const container = document.getElementById("achievements-list")
    if (!container) return

    if (achievements.length === 0) {
      container.innerHTML = '<div class="no-achievements">No achievements yet. Keep recycling!</div>'
      return
    }

    container.innerHTML = achievements
      .map(
        (achievement) => `
      <div class="achievement-item">
        <div class="achievement-icon">
          <i class="bi bi-trophy"></i>
        </div>
        <div class="achievement-content">
          <div class="achievement-name">${achievement.name}</div>
          <div class="achievement-description">${achievement.description}</div>
        </div>
      </div>
    `,
      )
      .join("")
  }

  updateRecentActivity(activities) {
    const activityList = document.getElementById("recent-activity")
    if (!activityList || !activities) return

    if (activities.length === 0) {
      activityList.innerHTML = '<div class="no-activity">No recent activity</div>'
      return
    }

    activityList.innerHTML = activities
      .slice(0, 10)
      .map((activity) => {
        const date = new Date(activity.created_at)
        const timeAgo = this.getTimeAgo(date)

        return `
          <div class="activity-item">
            <div class="activity-icon">
              <i class="bi bi-recycle"></i>
            </div>
            <div class="activity-content">
              <div class="activity-title">
                Recycled ${activity.material_type || "item"}
              </div>
              <div class="activity-meta">
                <span class="points positive">+${activity.points_awarded} points</span>
                <span class="time">${timeAgo}</span>
              </div>
            </div>
          </div>
        `
      })
      .join("")
  }

  updateCharts(chartData) {
    // Update activity chart
    if (chartData.activity && document.getElementById("activity-chart")) {
      this.createActivityChart(chartData.activity)
    }

    // Update materials chart
    if (chartData.materials && document.getElementById("materials-chart")) {
      this.createMaterialsChart(chartData.materials)
    }
  }

  createActivityChart(data) {
    const ctx = document.getElementById("activity-chart")
    if (!ctx) return

    if (this.charts.activity) {
      this.charts.activity.destroy()
    }

    this.charts.activity = new Chart(ctx, {
      type: "line",
      data: {
        labels: data.labels || [],
        datasets: [
          {
            label: "Points Earned",
            data: data.data || [],
            borderColor: "#10b981",
            backgroundColor: "rgba(16, 185, 129, 0.1)",
            tension: 0.4,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: "rgba(0, 0, 0, 0.1)",
            },
          },
          x: {
            grid: {
              display: false,
            },
          },
        },
      },
    })
  }

  createMaterialsChart(data) {
    const ctx = document.getElementById("materials-chart")
    if (!ctx) return

    if (this.charts.materials) {
      this.charts.materials.destroy()
    }

    this.charts.materials = new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: data.labels || [],
        datasets: [
          {
            data: data.data || [],
            backgroundColor: ["#10b981", "#3b82f6", "#f59e0b", "#ef4444", "#8b5cf6"],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
          },
        },
      },
    })
  }

  async loadRewardsPreview() {
    try {
      const response = await fetch("/api/rewards/list.php", { credentials: "include" })
      const data = await response.json()

      if (data.success) {
        const availableRewards = data.data.filter((reward) => reward.is_active && reward.can_redeem).slice(0, 3)

        const rewardsPreview = document.getElementById("rewards-preview")
        if (rewardsPreview) {
          if (availableRewards.length === 0) {
            rewardsPreview.innerHTML = '<div class="no-rewards">No rewards available</div>'
            return
          }

          rewardsPreview.innerHTML = availableRewards
            .map(
              (reward) => `
                <div class="reward-item">
                  <div class="reward-image">
                    ${
                      reward.image_url
                        ? `<img src="${reward.image_url}" alt="${reward.name}">`
                        : '<i class="bi bi-gift"></i>'
                    }
                  </div>
                  <div class="reward-content">
                    <div class="reward-name">${reward.name}</div>
                    <div class="reward-points">${reward.points_cost} points</div>
                    <div class="reward-status">
                      ${
                        reward.points_cost <= (this.user?.points || 0)
                          ? '<span class="available">Available</span>'
                          : `<span class="need-more">Need ${reward.points_cost - (this.user?.points || 0)} more</span>`
                      }
                    </div>
                  </div>
                </div>
              `,
            )
            .join("")
        }
      }
    } catch (error) {
      console.error("Failed to load rewards preview:", error)
    }
  }

  setupEventListeners() {
    // Refresh button
    const refreshBtn = document.getElementById("refresh-dashboard")
    if (refreshBtn) {
      refreshBtn.addEventListener("click", () => this.loadDashboardData())
    }

    // Quick scan button
    const quickScanBtn = document.getElementById("quick-scan")
    if (quickScanBtn) {
      quickScanBtn.addEventListener("click", () => {
        window.location.href = "/assets/qr-scanner.html"
      })
    }

    // View rewards button
    const viewRewardsBtn = document.getElementById("view-rewards")
    if (viewRewardsBtn) {
      viewRewardsBtn.addEventListener("click", () => {
        window.location.href = "/assets/rewards.html"
      })
    }

    // View leaderboard button
    const viewLeaderboardBtn = document.getElementById("view-leaderboard")
    if (viewLeaderboardBtn) {
      viewLeaderboardBtn.addEventListener("click", () => {
        window.location.href = "/assets/leaderboard.html"
      })
    }
  }

  startPeriodicUpdates() {
    // Update dashboard every 5 minutes
    setInterval(
      () => {
        this.loadDashboardData()
      },
      5 * 60 * 1000,
    )
  }

  getMaterialIcon(materialType) {
    const icons = {
      plastic: "recycle",
      glass: "cup",
      aluminum: "can",
      paper: "file-text",
      cardboard: "box",
    }
    return icons[materialType?.toLowerCase()] || "recycle"
  }

  getTimeAgo(date) {
    const now = new Date()
    const diffInSeconds = Math.floor((now - date) / 1000)

    if (diffInSeconds < 60) return "Just now"
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`

    return date.toLocaleDateString()
  }

  showError(message) {
    const alertContainer = document.getElementById("alert-container")
    if (alertContainer) {
      const alert = document.createElement("div")
      alert.className = "alert alert-danger alert-dismissible fade show"
      alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `
      alertContainer.appendChild(alert)

      setTimeout(() => {
        if (alert.parentNode) {
          alert.remove()
        }
      }, 5000)
    }
  }

  showSuccess(message) {
    const alertContainer = document.getElementById("alert-container")
    if (alertContainer) {
      const alert = document.createElement("div")
      alert.className = "alert alert-success alert-dismissible fade show"
      alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `
      alertContainer.appendChild(alert)

      setTimeout(() => {
        if (alert.parentNode) {
          alert.remove()
        }
      }, 5000)
    }
  }
}

// Initialize dashboard when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  new Dashboard()
})

// Legacy function for backward compatibility
async function loadDashboardData() {
  // This function is kept for backward compatibility
  // The new Dashboard class handles all functionality
}

async function loadRewardsPreview() {
  // This function is kept for backward compatibility
  // The new Dashboard class handles all functionality
}
