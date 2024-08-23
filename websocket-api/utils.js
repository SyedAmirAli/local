const os = require('os');
const fs = require('fs');
const path = require('path');

function getIPv4Address() {
    const networkInterfaces = os.networkInterfaces();
    let ipv4Address = '';

    for (const interfaceName in networkInterfaces) {
        const networkInterface = networkInterfaces[interfaceName];

        for (const alias of networkInterface) {
            if (alias.family === 'IPv4' && !alias.internal) {
                ipv4Address = alias.address;
                break;
            }
        }

        if (ipv4Address) {
            break;
        }
    }

    return ipv4Address;
}

const port = 3571;
const ipv4Address = getIPv4Address();

function makeIpFile() {
    const filePath = 'D:\\3\\PrayerReminder\\utils\\logIp.js';

    if (!fs.existsSync(path.dirname(filePath))) {
        fs.mkdirSync(path.dirname(filePath), { recursive: true });
    }

    fs.writeFileSync(
        filePath,
        `export const ipv4Address = '${ipv4Address}'; \nexport const logUrl = 'http://${ipv4Address}:${port}/data';`,
        function (error) {
            console.log('WRITE FILE ERROR:', error);
        }
    );
}

makeIpFile();

function viewHomeContents() {
    return `
        <!DOCTYPE html>
        <html lang="en">
            <head>
                <meta charset="UTF-8" />
                <meta name="viewport" content="width=device-width, initial-scale=1.0" />
                <title>WebSocket API Test</title>

                <style>
                    .header {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        width:100;
                        gap: 30px;
                        padding: 10px;
                        border-bottom: 1px solid #444;
                        margin-bottom: 20px;
                    }

                    .header button {
                        padding: 6px 18px;
                        font-size: 22px;
                        color: #f00;
                    }
                </style>
            </head>
            <body>
                <div>
                    <input/>
                </div>
                <div class="header">
                    <h1>WebSocket Data Logger</h1>
                    <button onclick="refresh()">REFRESH</button>
                </div>

                <div id="log"></div>

                <script>
                    const logDiv = document.getElementById('log');
                    refresh();
                    
                    function refresh(){
                        console.clear();
                        const socket = new WebSocket('ws://${ipv4Address}:${port}');

                        socket.onopen = () => {
                            console.log('WebSocket connection established');
                        };

                        function isObject(variable) {
                            return variable !== null && typeof variable === 'object';
                        }

                        function isExecutableObject(str) {
                            try {
                                const parsed = JSON.parse(str);
                                return typeof parsed === 'object' && parsed !== null;
                            } catch (e) {
                                return false;
                            }
                        }

                        socket.onmessage = ({ data }) => {
                            const logEntry = document.createElement('div');

                            if (isExecutableObject(data)) {
                                data = JSON.parse(data);
                                console.log(data);
                                logEntry.textContent = data?.message;
                            } else {
                                logEntry.textContent = 'Received: ' + data;
                                console.log(data);
                            }

                            logDiv.appendChild(logEntry);
                        };

                        socket.onerror = (error) => {
                            console.error('WebSocket error:', error);
                        };

                        socket.onclose = () => {
                            console.log('WebSocket connection closed');
                        };
                    };

                    window.log = async function (...params) {
                        let url = "http://${ipv4Address}:${port}/data";
                        let lastIndex = params[params.length - 1];

                        if (typeof lastIndex === "string" && lastIndex.startsWith("http")) {
                            url = lastIndex;
                        }

                        const data = params.length > 1 ? params : params[0];

                        try {
                            const response = await fetch(url, {
                                method: "POST",
                                body: Object.isExtensible(data)
                                    ? JSON.stringify(data)
                                    : JSON.stringify({ message: data }),
                                headers: {
                                    "Content-Type": "application/json",
                                },
                            });

                            if (!response.ok) {
                                throw new Error('HTTP error! Status: ' + response.status);
                            }

                            const responseData = await response.text();
                            // log("Response:", JSON.stringify(responseData));
                        } catch (error) {
                            console.log("Error:", error);
                        }
                    };

                </script>
            </body>
        </html>

    `;
}

module.exports = { ipv4Address, viewHomeContents, port };
