document.addEventListener("DOMContentLoaded", () => {
  const loginForm = document.getElementById("loginForm")
  const registerForm = document.getElementById("registerForm")
  const alertBox = document.getElementById("alertBox")

  function showAlert(message, type = "danger") {
    alertBox.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `
  }

  if (registerForm) {
    registerForm.addEventListener("submit", async (e) => {
      e.preventDefault()

      const formData = new FormData(registerForm)
      const data = Object.fromEntries(formData)

      try {
        const response = await fetch("/register", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(data),
        })

        const result = await response.json()

        if (result.success) {
          showAlert("Registration successful! Please login.", "success")
          registerForm.reset()
        } else {
          showAlert(result.message || "Registration failed.")
        }
      } catch (error) {
        console.error("Error:", error)
        showAlert("An error occurred during registration.")
      }
    })
  }

  if (loginForm) {
    loginForm.addEventListener("submit", async (e) => {
      e.preventDefault()

      const formData = new FormData(loginForm)
      const data = Object.fromEntries(formData)

      try {
        const response = await fetch("/login", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(data),
        })

        const data = await response.json()

        if (data.success) {
          showAlert("Login successful! Redirecting...", "success")

          // Store user data
          localStorage.setItem("user", JSON.stringify(data.user))

          // Use redirect URL from server response
          const redirectUrl = data.redirect_url || "/assets/dashboard.html"

          setTimeout(() => {
            window.location.href = redirectUrl
          }, 1000)
        } else {
          showAlert(data.message || "Login failed.")
        }
      } catch (error) {
        console.error("Error:", error)
        showAlert("An error occurred during login.")
      }
    })
  }
})
