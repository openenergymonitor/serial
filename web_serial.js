/**
 * web_serial.js
 *
 * Web Serial API transport layer for the Serial Config Tool.
 *
 * Entry point:
 *   connect()  — called by the Vue "Connect" button (@click="connect" in the template,
 *                which delegates to this function via the Vue method defined in index.php).
 *
 * Call chain:
 *   connect()
 *     → opens the port via navigator.serial.requestPort()
 *     → sets up TextDecoder / TextEncoder streams
 *     → sets app.connected = true  (Vue re-renders the UI)
 *     → schedules a one-shot timeout to send "l" (list config) if no config arrives within 2s
 *     → readLoop()
 *         → reads raw chunks from the serial port
 *         → accumulates characters into `buffer` until a newline is found
 *         → writes each complete line to the on-screen log
 *         → process_line(line)  [defined in serial_config_core.js]
 *             → parses device config key=value pairs and updates the Vue `app.device` data
 *
 * Outbound commands:
 *   writeToStream(cmd)  — called by Vue methods in serial_config_core.js (set_ical, set_vcal, etc.)
 *                         and by the Console "Send" button.
 *                         Writes `cmd + '\n'` to the Web Serial output stream.
 */

// Active Web Serial port and stream handles (populated by connect())
var port, inputStream, outputStream, reader;
var outputDone, inputDone;
var wait_for_config_interval;

/**
 * writeToStream(cmd)
 * Sends a command string to the connected serial device.
 * Called by all set_* methods in serial_config_core.js and send_cmd().
 */
function writeToStream(cmd) {
    console.log("writeToStream:", cmd);
    const writer = outputStream.getWriter();
    writer.write(cmd + '\n');
    writer.releaseLock();
}

/**
 * connect()
 * Opens the Web Serial port. Triggered by the Vue "Connect" button.
 * Filters for known emonTx/emonPi USB vendor IDs (Silicon Labs CP210x and OpenMoko).
 * On success, starts readLoop() to continuously receive serial data.
 */
async function connect() {
    port = await navigator.serial.requestPort({
        filters: [
            { usbVendorId: 0x10c4 }, // Silicon Labs CP210x (emonTx3, emonTx4, emonTx5)
            { usbVendorId: 0x1209 }  // OpenMoko / emonPi
        ]
    });
    await port.open({ baudRate: 115200 });

    app.connected = true;
    app.button_connect_text = "Connected";

    // Set up the decode pipeline: port.readable → TextDecoderStream → inputStream
    let decoder = new TextDecoderStream();
    inputDone = port.readable.pipeTo(decoder.writable);
    inputStream = decoder.readable;

    // Set up the encode pipeline: outputStream → TextEncoderStream → port.writable
    const encoder = new TextEncoderStream();
    outputDone = encoder.readable.pipeTo(port.writable);
    outputStream = encoder.writable;

    reader = inputStream.getReader();

    // Request the device config after 2s if it hasn't arrived on its own.
    // "l" is the list-config command understood by emon firmware.
    wait_for_config_interval = setTimeout(function() {
        if (!app.config_received) {
            writeToStream("l");
        }
    }, 2000);

    await readLoop();
}

/**
 * readLoop()
 * Continuously reads chunks from the serial input stream.
 * Buffers individual characters and dispatches complete lines to process_line()
 * (defined in serial_config_core.js) and appends them to the on-screen log.
 */
async function readLoop() {
    console.log('readLoop started');

    while (true) {
        const { value, done } = await reader.read();

        if (value) {
            for (var i = 0; i < value.length; i++) {
                if (value[i] === '\n') {
                    var line = buffer.trim();
                    buffer = "";
                    log.textContent += line + "\n";
                    log.scrollTop = log.scrollHeight;

                    // Hand the complete line to the shared parser in serial_config_core.js
                    process_line(line);
                } else {
                    buffer += value[i];
                }
            }
        }

        // Re-arm the config-request timeout on each chunk received, in case the
        // device is sending data but not yet the config block.
        if (!app.config_received) {
            wait_for_config_interval = setTimeout(function() {
                if (!app.config_received) {
                    writeToStream("l");
                }
            }, 2000);
        }

        if (done) {
            console.log('readLoop: stream closed');
            reader.releaseLock();
            break;
        }
    }
}
