<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1">
    <title>Serial Monitor</title>
    <link href="style.css?v=5" rel="stylesheet" />
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
</head>

<html>
    <body>
        <div class="container">
            <?php echo file_get_contents("serial_config_view.php"); ?>
        </div>
    </body>
</html>

<script>
    
    async function connect() {

        port = await navigator.serial.requestPort({ 
            filters: [
                { usbVendorId: 0x10c4 },
                { usbVendorId: 0x1209 }
            ] 
        });
        await port.open({ baudRate: 115200 });

        app.connected = true;
        app.button_connect_text = "Connected";

        let decoder = new TextDecoderStream();
        let inputDone = port.readable.pipeTo(decoder.writable);
        inputStream = decoder.readable;

        const encoder = new TextEncoderStream();
        outputDone = encoder.readable.pipeTo(port.writable);
        outputStream = encoder.writable;

        reader = inputStream.getReader();

        wait_for_config_interval = setTimeout(function() {
            if (!app.config_received) {
                writeToStream("l");
            }
        }, 2000);

        readLoop();
    }

    async function readLoop() {
        console.log('Readloop');

        while (true) {
            const { value, done } = await reader.read();
            if (value) {
                for (var i = 0; i < value.length; i++) {
                    if (value[i] == '\n') {
                        var line = buffer.trim();
                        buffer = "";
                        log.textContent += line + "\n";
                        log.scrollTop = log.scrollHeight;

                        process_line(line);

                    } else {
                        buffer += value[i];
                    }
                }
            }
            
            // If config has not been received, wait 2s and if it still has not been received send 'l' to request config
            if (!app.config_received) {
                wait_for_config_interval = setTimeout(function() {
                    if (!app.config_received) {
                        writeToStream("l");
                    }
                }, 2000);
            } 
            
            if (done) {
                console.log('[readLoop] DONE', done);
                reader.releaseLock();
                break;
            }
        }
    }
    
    
</script>
