document.addEventListener("DOMContentLoaded", () => {
  const video = document.getElementById("qr-video")
  const canvas = document.getElementById("qr-canvas")
  const resultDiv = document.getElementById("qr-result")
  const scanResultDiv = document.getElementById("scan-result")
  let selectedMaterial = null
  let scannerActive = false
  const canvasContext = canvas.getContext("2d")

  // Material selection
  document.querySelectorAll(".material-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      selectedMaterial = btn.dataset.material
      document.querySelectorAll(".material-btn").forEach((b) => b.classList.remove("active"))
      btn.classList.add("active")
      if (!scannerActive) startScanner()
    })
  })

  async function startScanner() {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
      video.srcObject = stream
      video.play()
      scannerActive = true
      requestAnimationFrame(tick)
    } catch (e) {
      console.error("Error accessing camera:", e)
      scanResultDiv.innerHTML = `
                <div class="alert alert-danger">
                    Camera access denied. Please enable camera permissions to scan QR codes.
                </div>
            `
    }
  }

  function tick() {
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.hidden = false
      canvas.height = video.videoHeight
      canvas.width = video.videoWidth
      canvasContext.drawImage(video, 0, 0, canvas.width, canvas.height)

      const imageData = canvasContext.getImageData(0, 0, canvas.width, canvas.height)
      const code = jsQR(imageData.data, imageData.width, imageData.height, {
        inversionAttempts: "dontInvert",
      })

      if (code && selectedMaterial) {
        processScan(code.data, selectedMaterial)
      }
    }
    if (scannerActive) {
      requestAnimationFrame(tick)
    }
  }

  async function processScan(barcode, material) {
    scannerActive = false
    video.srcObject.getTracks().forEach((track) => track.stop())

    try {
      const response = await fetch("/api/recycling/scan.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ barcode, material }),
        credentials: "include",
      })

      const data = await response.json()

      if (data.success) {
        scanResultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <h4>Success!</h4>
                        <p>You earned ${data.points} points for recycling ${material}.</p>
                        <p>Your new total: ${data.total_points} points</p>
                        <button class="btn btn-primary" onclick="window.location.reload()">Scan Another</button>
                    </div>
                `
        // Update dashboard in real-time
        if (typeof updateDashboard !== "undefined") updateDashboard()
      } else {
        scanResultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h4>Scan Failed</h4>
                        <p>${data.message}</p>
                        <button class="btn btn-primary" onclick="window.location.reload()">Try Again</button>
                    </div>
                `
      }
    } catch (error) {
      console.error("Scan processing error:", error)
      scanResultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <h4>Error</h4>
                    <p>Network error occurred. Please try again.</p>
                </div>
            `
    }
  }
})
