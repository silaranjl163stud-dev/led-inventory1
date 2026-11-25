const Scanner = {
    html5QrcodeScanner: null,
    isScanning: false,

    init() {
        this.initHardwareScanner();
    },

    initHardwareScanner() {
        const input = document.getElementById('hardware-scanner-input');
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const val = input.value.trim();
                if (val) {
                    this.onScanSuccess(val);
                    input.value = '';
                }
            }
        });
        
        let lastKeyTime = Date.now();
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT') return;
            const charCode = e.code;
            if (charCode === 'Enter') return;

            const currentTime = Date.now();
            // Auto-focus the hardware scanner input on rapid keystrokes (USB scanner)
            if (currentTime - lastKeyTime < 50) {
                input.focus();
            }
            lastKeyTime = currentTime;
        });
    },

    startCamera() {
        if (this.isScanning) return;
        
        if (!this.html5QrcodeScanner) {
            this.html5QrcodeScanner = new Html5Qrcode("reader");
        }
        
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };
        
        this.html5QrcodeScanner.start({ facingMode: "environment" }, config, (decodedText) => {
            this.onScanSuccess(decodedText);
            this.stopCamera(); 
        }).catch(err => {
            console.log("Camera start error. Will rely on physical scanner.", err);
        });
        this.isScanning = true;
    },

    stopCamera() {
        if (this.html5QrcodeScanner && this.isScanning) {
            this.html5QrcodeScanner.stop().then(() => {
                this.isScanning = false;
            }).catch(err => console.log("Camera stop error:", err));
        }
    },

    onScanSuccess(decodedText) {
        const event = new CustomEvent('item-scanned', { detail: decodedText });
        window.dispatchEvent(event);
    }
};

document.addEventListener('DOMContentLoaded', () => Scanner.init());
window.Scanner = Scanner;
