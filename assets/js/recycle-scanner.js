document.addEventListener('DOMContentLoaded', () => {
    const videoElement = document.getElementById('barcode-video');
    const canvasElement = document.getElementById('barcode-canvas');
    const resultElement = document.getElementById('barcode-result');
    const statusElement = document.getElementById('scan-status');
    const scanResultDiv = document.getElementById('scan-result');
    const rescanBtn = document.getElementById('rescan-btn');
    
    let selectedMaterial = null;
    let barcodeScanner = null;
    let lastScannedCode = null;

    // Material selection
    document.querySelectorAll('.material-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            selectedMaterial = btn.dataset.material;
            document.querySelectorAll('.material-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            if (!barcodeScanner) {
                initializeScanner();
            } else if (barcodeScanner && !barcodeScanner.scanning) {
                barcodeScanner.startScanning();
            }
        });
    });

    // Initialize the barcode scanner
    function initializeScanner() {
        barcodeScanner = new BarcodeScanner({
            videoElement: videoElement,
            canvasElement: canvasElement,
            resultElement: resultElement,
            preferredCamera: 'environment',
            stopOnScan: false,
            onScan: handleBarcodeScan,
            onError: handleScannerError
        });
    }

    // Handle successful barcode scans
    async function handleBarcodeScan(barcode) {
        if (!selectedMaterial) {
            statusElement.innerHTML = '<div class="alert alert-warning">Please select material type first</div>';
            return;
        }

        // Debounce - prevent duplicate scans of the same code
        if (lastScannedCode === barcode.rawValue) return;
        lastScannedCode = barcode.rawValue;

        // Show scanning status
        statusElement.innerHTML = '<div class="alert alert-info">Processing scan...</div>';
        scanResultDiv.hidden = false;
        resultElement.textContent = `Scanned: ${barcode.rawValue} (${barcode.format})`;

        try {
            // Send scan to server
            const response = await fetch('/api/recycling/scan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    barcode: barcode.rawValue, 
                    material: selectedMaterial,
                    format: barcode.format
                }),
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                statusElement.innerHTML = `
                    <div class="alert alert-success">
                        <h4>Success!</h4>
                        <p>You earned ${data.points} points for recycling ${selectedMaterial}.</p>
                        <p>Your new total: ${data.total_points} points</p>
                    </div>
                `;
                
                // Show rescan button
                rescanBtn.hidden = false;
                
                // Update dashboard if on same page
                if (window.updateDashboard) {
                    updateDashboard();
                }
            } else {
                statusElement.innerHTML = `
                    <div class="alert alert-danger">
                        <h4>Scan Failed</h4>
                        <p>${data.message}</p>
                    </div>
                `;
                lastScannedCode = null; // Allow rescan
            }
        } catch (error) {
            console.error("Scan processing error:", error);
            statusElement.innerHTML = `
                <div class="alert alert-danger">
                    <h4>Error</h4>
                    <p>Network error occurred. Please try again.</p>
                </div>
            `;
            lastScannedCode = null; // Allow rescan
        }
    }

    // Handle scanner errors
    function handleScannerError(error) {
        console.error("Barcode scanner error:", error);
        statusElement.innerHTML = `
            <div class="alert alert-danger">
                <h4>Scanner Error</h4>
                <p>${error}</p>
                <p>Please refresh the page and try again.</p>
            </div>
        `;
        scanResultDiv.hidden = false;
    }

    // Rescan button handler
    rescanBtn.addEventListener('click', () => {
        lastScannedCode = null;
        rescanBtn.hidden = true;
        statusElement.innerHTML = '<div class="alert alert-info">Ready to scan</div>';
    });

    // Check camera permissions on page load
    async function checkCameraPermissions() {
        try {
            const permissions = await navigator.permissions.query({ name: 'camera' });
            permissions.onchange = () => {
                if (permissions.state === 'denied') {
                    handleScannerError('Camera access was denied. Please enable camera permissions in your browser settings.');
                }
            };
        } catch (e) {
            console.log("Permissions API not supported, skipping camera permission check");
        }
    }

    checkCameraPermissions();
});
