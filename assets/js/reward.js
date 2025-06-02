document.addEventListener("DOMContentLoaded", async () => {
  // Load rewards
  async function loadRewards() {
    try {
      const response = await fetch("/api/rewards/list.php")
      const data = await response.json()

      if (data.success) {
        renderRewards(data.data)
      } else {
        showToast("Failed to load rewards", "error")
      }
    } catch (error) {
      showToast("Network error loading rewards", "error")
    }
  }

  function renderRewards(rewards) {
    const container = document.getElementById("rewards-container")
    if (!container) return

    container.innerHTML = rewards
      .map(
        (reward) => `
            <div class="col-md-4 mb-4">
                <div class="card reward-card h-100">
                    ${
                      reward.image_url
                        ? `
                        <img src="${reward.image_url}" class="card-img-top" alt="${reward.name}">
                    `
                        : ""
                    }
                    <div class="card-body">
                        <h5 class="card-title">${reward.name}</h5>
                        <p class="card-text">${reward.description}</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-primary">${reward.points_cost} points</span>
                            ${
                              reward.inventory !== null
                                ? `
                                <small class="text-muted">${reward.inventory} remaining</small>
                            `
                                : ""
                            }
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <button class="btn btn-primary w-100 redeem-btn" 
                                data-id="${reward.reward_id}"
                                ${reward.inventory === 0 ? "disabled" : ""}>
                            Redeem Now
                        </button>
                    </div>
                </div>
            </div>
        `,
      )
      .join("")

    // Add event listeners to redeem buttons
    document.querySelectorAll(".redeem-btn").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const rewardId = btn.dataset.id
        btn.disabled = true

        try {
          const response = await fetch("/api/rewards/redeem.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ reward_id: rewardId }),
            credentials: "include",
          })

          const data = await response.json()

          if (data.success) {
            showToast(`Redeemed: ${data.reward_name}! Code: ${data.redemption_code}`, "success")
            loadRewards() // Refresh list
          } else {
            showToast(data.message || "Redemption failed", "error")
            btn.disabled = false
          }
        } catch (error) {
          showToast("Network error during redemption", "error")
          btn.disabled = false
        }
      })
    })
  }

  // Initialize
  loadRewards()
})

function showToast(message, type = "info") {
  const toastContainer = document.getElementById("toast-container")

  if (!toastContainer) {
    const newToastContainer = document.createElement("div")
    newToastContainer.id = "toast-container"
    newToastContainer.style.position = "fixed"
    newToastContainer.style.top = "20px"
    newToastContainer.style.right = "20px"
    newToastContainer.style.zIndex = "1000"
    document.body.appendChild(newToastContainer)
  }

  const toast = document.createElement("div")
  toast.classList.add("toast", `toast-${type}`)
  toast.textContent = message

  const toastContainerEl = document.getElementById("toast-container")
  toastContainerEl.appendChild(toast)

  setTimeout(() => {
    toast.remove()
  }, 3000)
}
