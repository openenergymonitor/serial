<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1">
    <title>Serial Monitor</title>
    <link href="style.css?v=7" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
    <script src="lib/serial_config_core.js"></script>
    <script src="web_serial.js"></script>
</head>

    <body>
        <div class="container">
            <div id="app">
                <button class="btn btn-info" style="float:right" @click="connect" :disabled="connected">{{ button_connect_text }}</button>

                <h3 style="color:#333">
                    <img src="https://unpkg.com/lucide-static@latest/icons/cpu.svg" width="24" height="24" style="vertical-align:middle;margin-right:8px;margin-bottom:3px;opacity:0.65">
                    Serial Config Tool
                </h3>

                <?php include __DIR__ . '/lib/serial_config_template.php'; ?>
            </div>

            <pre id="log" class="log" style="padding:10px"></pre>

            <script>
                // `log` and `buffer` are used by both the Vue app below and readLoop() in web_serial.js
                const log = document.getElementById("log");
                var buffer = "";

                // Create the Vue instance using shared data and methods from serial_config_core.js,
                // adding the web-specific `connect` method that delegates to connect() in web_serial.js.
                var app = new Vue({
                    el: '#app',
                    data: Object.assign({}, serialConfigData),
                    methods: Object.assign({}, serialConfigMethods, {
                        connect: function() {
                            connect();
                        }
                    })
                });

                // Pre-populate the CT channel array (6 channels by default;
                // process_line() in serial_config_core.js will repopulate if the
                // device reports a different firmware/channel count).
                populate_channels(6);
            </script>
        </div>
    </body>
</html>
