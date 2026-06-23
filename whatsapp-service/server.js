const express = require('express');
const { Client, LocalAuth } = require('whatsapp-web.js');
const QRCode = require('qrcode');
const app = express();
app.use(express.json());

let client = null;
let qrCodeData = null;
let isConnected = false;
let phoneNumber = null;
let isStarting = false;

function createClient() {
    if (client) {
        try { client.destroy(); } catch (e) {}
    }

    client = new Client({
        authStrategy: new LocalAuth({ dataPath: './session' }),
        puppeteer: { headless: true, args: ['--no-sandbox'] },
    });

    client.on('qr', async (qr) => {
        try { qrCodeData = await QRCode.toDataURL(qr); } catch (e) { qrCodeData = null; }
        isConnected = false;
        phoneNumber = null;
    });

    client.on('ready', () => {
        isConnected = true;
        qrCodeData = null;
        isStarting = false;
        const info = client.info || {};
        phoneNumber = info.wid?.user || info.me?.user || null;
    });

    client.on('disconnected', () => {
        isConnected = false;
        phoneNumber = null;
        qrCodeData = null;
        isStarting = false;
    });

    client.initialize();
}

app.post('/start', (req, res) => {
    if (isStarting) return res.json({ status: true, message: 'already starting' });
    isStarting = true;
    createClient();
    res.json({ status: true, message: 'starting' });
});

app.get('/status', (req, res) => {
    res.json({
        connected: isConnected,
        phone: phoneNumber,
        qr: qrCodeData,
        starting: isStarting,
    });
});

app.post('/send', async (req, res) => {
    if (!isConnected) return res.status(400).json({ status: false, reason: 'not connected' });

    const { target, message } = req.body;
    if (!target || !message) return res.status(400).json({ status: false, reason: 'target and message required' });

    try {
        const formatted = target.startsWith('0') ? '62' + target.slice(1) : target;
        const chatId = formatted.includes('@c.us') ? formatted : `${formatted}@c.us`;
        await client.sendMessage(chatId, message);
        res.json({ status: true });
    } catch (e) {
        res.status(500).json({ status: false, reason: e.message });
    }
});

app.post('/disconnect', async (req, res) => {
    try {
        if (client) { await client.destroy(); client = null; }
        const fs = require('fs');
        const path = require('path');
        const sessionDir = path.join(__dirname, 'session');
        if (fs.existsSync(sessionDir)) {
            fs.rmSync(sessionDir, { recursive: true, force: true });
        }
    } catch (e) {}
    isConnected = false;
    phoneNumber = null;
    qrCodeData = null;
    isStarting = false;
    res.json({ status: true });
});

const PORT = process.env.PORT || 3001;
app.listen(PORT, () => console.log(`WA service running on port ${PORT}`));
