/**
 * Adapted from GeoRapBox's Barcode Scanner (https://github.com/georapbox/barcode-scanner)
 * Modified for Adbeam recycling project
 */
class BarcodeScanner {
  constructor(options = {}) {
    const defaults = {
      videoElement: null,
      canvasElement: null,
      resultElement: null,
      onScan: null,
      onError: null,
      stopOnScan: true,
      preferredCamera: 'environment',
      formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'code_93', 'codabar', 'itf']
    };

    this.settings = Object.assign({}, defaults, options);
    this.mediaStream = null;
    this.barcodeDetector = null;
    this.scanning = false;
    this.lastScannedCode = null;
    this.lastScanTime = 0;
    this.debounceTime = 2000; // 2 seconds between scans

    this.init();
  }

  async init() {
    if (!('BarcodeDetector' in window)) {
      this.handleError('BarcodeDetector API not supported in this browser');
      return;
    }

    try {
      this.barcodeDetector = new BarcodeDetector({ formats: this.settings.formats });
      await this.setupCamera();
    } catch (error) {
      this.handleError(error.message);
    }
  }

  async setupCamera() {
    try {
      this.mediaStream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: this.settings.preferredCamera,
          width: { ideal: 1280 },
          height: { ideal: 720 }
        },
        audio: false
      });

      if (this.settings.videoElement) {
        this.settings.videoElement.srcObject = this.mediaStream;
        this.settings.videoElement.play();
        this.startScanning();
      }
    } catch (error) {
      this.handleError(`Camera error: ${error.message}`);
    }
  }

  startScanning() {
    if (!this.scanning) {
      this.scanning = true;
      this.scanFrame();
    }
  }

  stopScanning() {
    this.scanning = false;
    if (this.mediaStream) {
      this.mediaStream.getTracks().forEach(track => track.stop());
    }
    if (this.settings.videoElement) {
      this.settings.videoElement.srcObject = null;
    }
  }

  async scanFrame() {
    if (!this.scanning) return;

    try {
      if (this.settings.videoElement && this.settings.canvasElement) {
        const canvas = this.settings.canvasElement;
        const video = this.settings.videoElement;
        
        // Set canvas dimensions to match video
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        // Draw video frame to canvas
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // Detect barcodes
        const barcodes = await this.barcodeDetector.detect(canvas);
        
        if (barcodes.length > 0) {
          const now = Date.now();
          if (now - this.lastScanTime > this.debounceTime || 
              barcodes[0].rawValue !== this.lastScannedCode) {
            this.lastScannedCode = barcodes[0].rawValue;
            this.lastScanTime = now;
            this.handleScan(barcodes[0]);
          }
        }
      }
      
      // Continue scanning
      requestAnimationFrame(() => this.scanFrame());
    } catch (error) {
      this.handleError(`Scan error: ${error.message}`);
    }
  }

  handleScan(barcode) {
    if (this.settings.resultElement) {
      this.settings.resultElement.textContent = `Scanned: ${barcode.rawValue} (${barcode.format})`;
    }
    
    if (typeof this.settings.onScan === 'function') {
      this.settings.onScan(barcode);
    }
    
    if (this.settings.stopOnScan) {
      this.stopScanning();
    }
  }

  handleError(message) {
    console.error(message);
    if (this.settings.resultElement) {
      this.settings.resultElement.textContent = message;
    }
    
    if (typeof this.settings.onError === 'function') {
      this.settings.onError(message);
    }
  }
}

// Make available globally for Adbeam integration
window.BarcodeScanner = BarcodeScanner;
