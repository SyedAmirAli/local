const express = require('express');
const bodyParser = require('body-parser');
const cors = require('cors');
const crypto = require('crypto');
const { ipv4Address, port, viewHomeContents } = require('./utils');
const fs = require('fs');
const path = require('path');
const http = require('http');
const { Server } = require('socket.io');

const app = express();
app.use(cors()); // Enable CORS for all origins
app.use(bodyParser.json()); // Middleware to parse JSON bodies
app.use(express.static(path.join(__dirname, 'public')));

app.use((req, res, next) => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header(
        'Access-Control-Allow-Headers',
        'Origin, X-Requested-With, Content-Type, Accept'
    );
    next();
});

const httpServer = http.createServer();
const wss = new Server(httpServer, {
    cors: {
        origin: 'http://allowed-origin.com', // Allow this origin
        methods: ['GET', 'POST'], // Allow these methods
        allowedHeaders: ['my-custom-header'], // Allow these headers
        credentials: true, // Allow credentials
    },
});

// const wss = new WebSocket.Server({ noServer: true }); // Set up the WebSocket server
let webSocketConnection = null;

wss.on('connection', (ws) => {
    webSocketConnection = ws;
    ws.on('message', (message) => {
        console.log('Received:', message);
    });
    ws.send(JSON.stringify({ message: 'WebSocket server connected' }));
});

// HTTP POST endpoint
app.post('/upload', function (req, res) {
    const data = req.body;

    // Generate SHA-256 key
    const successSHA256Key = crypto
        .createHash('sha256')
        .update(new Date().toISOString())
        .digest('hex');

    data['successSHA256Key'] = successSHA256Key;
    data['currentTime'] = new Date().toISOString();

    // Send data through WebSocket if connected
    if (webSocketConnection) {
        if (isObject(data)) {
            webSocketConnection.send(JSON.stringify(data));
        } else {
            webSocketConnection.send(data);
        }
    }

    // Define the file path
    const filePath = path.join(
        __dirname,
        'public/database',
        `${successSHA256Key}.json`
    );

    const filePath2 = path.join(__dirname, 'public/database', 'db.json');

    // Ensure the /database directory exists
    if (!fs.existsSync(path.dirname(filePath))) {
        fs.mkdirSync(path.dirname(filePath), { recursive: true });
    }

    // Write the data to a JSON file
    const writeFile = (fPath) =>
        fs.writeFile(fPath, JSON.stringify(data, null, 2), (err) => {
            if (err) {
                console.error('Error writing file:', err);
                return res.status(500).json({ error: 'Failed to save data' });
            }
        });

    writeFile(filePath);
    writeFile(filePath2);

    res.status(200).json({
        successSHA256Key: successSHA256Key,
        status: 'success',
        message:
            'Data saved successfully as JSON file in your database folder!',
    });
});

// return main home page as a logger
app.get('/', function (request, response) {
    response.send(viewHomeContents());
});

app.post('/data', function (request, response) {
    const data = request.body;
    const logFile = path.join(__dirname, 'public/log/react-native.log');

    if (!fs.existsSync(path.dirname(logFile))) {
        fs.mkdirSync(path.dirname(logFile), { recursive: true });
    }

    if (webSocketConnection) {
        if (isObject(data)) {
            webSocketConnection.send(JSON.stringify(data));
        } else {
            webSocketConnection.send(data);
        }
    }

    function errorMessage(error) {
        if (error) {
            webSocketConnection.send(
                JSON.stringify({
                    message: 'Failed to read previous log and write new log.',
                    error,
                })
            );
        }

        return null;
    }

    const d = new Date();

    try {
        fs.readFile(
            logFile,
            { encoding: 'utf8' },
            async function (error, file) {
                errorMessage(error);
                const newLog = `[${d.toDateString()} | ${d.toLocaleTimeString()}]:\t${JSON.stringify(
                    data
                )}\n`;

                fs.writeFileSync(logFile, file + newLog, function (error) {
                    console.log(error);
                });
            }
        );
    } catch (error) {
        errorMessage(error);
    }

    response.status(200).json({
        data,
        error: undefined,
        status: 'success',
    });
});

// return a home file as a logger
app.get('/home', function (request, response) {
    response.sendFile(path.join(__dirname, 'index.html'));
});

// const port = 3571;
const ip = ipv4Address || '192.168.156.6';

// Upgrade HTTP server to handle WebSocket requests
const server = app.listen(port, ip, () => {
    console.log(`Server is running on http://${ip}:${port}`);
});

server.on('upgrade', (request, socket, head) => {
    wss.handleUpgrade(request, socket, head, (ws) => {
        wss.emit('connection', ws, request);
    });
});

function isObject(data) {
    return data !== null && typeof data === 'object';
}
