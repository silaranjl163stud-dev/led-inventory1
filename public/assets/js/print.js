/**
 * print.js — QR label printing utility
 * Depends on: QRious (CDN)
 */
const Print = {
    getLabelConfig(type) {
        switch (type) {
            case 'cabinet':         return { width: '80mm', height: '80mm', qrSize: 220, fontSize: '18px' };
            case 'module':          return { width: '50mm', height: '50mm', qrSize: 120, fontSize: '12px' };
            case 'video_processor': return { width: '60mm', height: '60mm', qrSize: 150, fontSize: '14px' };
            default:                return { width: '40mm', height: '40mm', qrSize: 90,  fontSize: '10px' };
        }
    },

    _labelHTML(config, item, type, canvasId) {
        return `
            <div style="width:${config.width};height:${config.height};overflow:hidden;display:flex;flex-direction:column;
                        align-items:center;justify-content:center;text-align:center;font-family:sans-serif;
                        box-sizing:border-box;padding:2mm;">
                <canvas id="${canvasId}"></canvas>
                <div style="font-size:${config.fontSize};font-weight:bold;margin-top:2px;">${item.qr_code}</div>
                <div style="font-size:calc(${config.fontSize} * 0.85);margin-top:1px;">${type.replace(/_/g, ' ').toUpperCase()}</div>
                <div style="font-size:calc(${config.fontSize} * 0.7);color:#555;margin-top:1px;">S/N: ${item.serial_number || 'N/A'}</div>
            </div>
        `;
    },

    label(type, item) {
        const printArea = document.getElementById('print-area');
        if (!printArea) return;
        const config = this.getLabelConfig(type);
        printArea.innerHTML = this._labelHTML(config, item, type, 'qr-canvas');
        new QRious({
            element: document.getElementById('qr-canvas'),
            value: item.qr_code,
            size: config.qrSize,
            background: 'white',
            foreground: 'black'
        });
        window.print();
    },

    bulk(type, items) {
        const printArea = document.getElementById('print-area');
        if (!printArea) return;
        const config = this.getLabelConfig(type);
        let html = '';
        items.forEach((item, index) => {
            const wrapped = this._labelHTML(config, item, type, `bulk-qr-${index}`);
            // Add page-break wrapper
            html += `<div style="page-break-after:always;">${wrapped}</div>`;
        });
        printArea.innerHTML = html;
        items.forEach((item, index) => {
            new QRious({
                element: document.getElementById(`bulk-qr-${index}`),
                value: item.qr_code,
                size: config.qrSize,
                background: 'white',
                foreground: 'black'
            });
        });
        window.print();
    }
};
