<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SkyEye | AprilTag RTMP Detector</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="{{ asset('js/mpegts.js') }}"></script>
    <style>
        :root {
            --primary: #3b82f6; --primary-glow: rgba(59, 130, 246, 0.5);
            --danger: #ef4444; --success: #22c55e;
            --bg: #030712; --panel: rgba(17, 24, 39, 0.7);
        }
        body { background-color: var(--bg); color: #f3f4f6; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .scanner-font { font-family: 'Orbitron', sans-serif; }
        .glass { background: var(--panel); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .glow-border:focus { box-shadow: 0 0 15px var(--primary-glow); border-color: var(--primary); }
        .status-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: .5; transform: scale(1.1); } }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        #debugCanvas { position: absolute; bottom: 10px; left: 10px; width: 140px; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; display: none; z-index: 100; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5); }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <!-- Header -->
    <header class="glass sticky top-0 z-50 px-6 py-4 flex items-center justify-between border-b border-white/10">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center shadow-lg shadow-blue-500/20">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </div>
            <h1 class="scanner-font text-xl font-bold tracking-wider bg-gradient-to-r from-blue-400 to-indigo-400 bg-clip-text text-transparent">
                SKYEYE <span class="text-xs font-light text-blue-500/80">RTMP DETECTOR</span>
            </h1>
        </div>
        <div class="flex items-center gap-4">
            <div id="connectionStatus" class="flex items-center gap-2 px-3 py-1 rounded-full bg-red-500/10 border border-red-500/20 text-red-400 text-xs">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> OFFLINE
            </div>
            <div class="text-[10px] text-slate-500" id="fpsCounter">FPS: 0</div>
        </div>
    </header>

    <main class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-6 p-6">
        <!-- Sidebar -->
        <aside class="lg:col-span-3 space-y-6">
            <section class="glass rounded-2xl p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-widest flex items-center gap-2">Configuration</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] text-slate-500 mb-1 block">SOURCE TYPE</label>
                        <select id="sourceType" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2 text-sm glow-border outline-none transition-all">
                            <option value="stream">RTMP / HTTP-FLV Feed</option>
                            <option value="camera" selected>Local Camera (Test Mode)</option>
                        </select>
                    </div>
                    <div id="urlContainer" style="display:none">
                        <label class="text-[10px] text-slate-500 mb-1 block">FEED URL</label>
                        <input id="streamUrl" type="text" placeholder="http://127.0.0.1:8080/live.flv" 
                               class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2 text-sm outline-none glow-border">
                    </div>
                    <div>
                        <label class="text-[10px] text-slate-500 mb-1 block">VALID TAG IDs</label>
                        <input id="validTags" type="text" value="0, 1, 2"
                               class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2 text-sm outline-none glow-border">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] text-slate-500 mb-1 block">DETECT EVERY N</label>
                            <input id="detectFreq" type="number" value="3" min="1" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-[10px] text-slate-500 mb-1 block">PROC. WIDTH</label>
                            <select id="targetWidth" class="w-full bg-slate-900 border border-white/10 rounded-xl px-4 py-2 text-sm">
                                <option value="480">480px</option>
                                <option value="640" selected>640px</option>
                                <option value="1280">1280px</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 px-1">
                        <input type="checkbox" id="debugMode" class="rounded bg-slate-900 border-white/10">
                        <label for="debugMode" class="text-[10px] text-slate-400">DEBUG GRAYSCALED LUMA</label>
                    </div>
                </div>

                <div class="pt-4 flex flex-col gap-3">
                    <button id="btnConnect" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl shadow-lg transition-all">START BROADCAST</button>
                    <button id="btnDisconnect" disabled class="w-full bg-slate-800 text-slate-400 py-3 rounded-xl transition-all">TERMINATE</button>
                </div>
            </section>

            <section class="glass rounded-2xl p-6">
                <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-widest mb-4">Live Logs</h2>
                <div id="logConsole" class="text-[10px] font-mono h-48 overflow-y-auto space-y-1 p-2 bg-black/40 rounded-lg custom-scrollbar">
                    <div class="text-slate-500">System ready. Waiting for signal...</div>
                </div>
            </section>
        </aside>

        <!-- Video Area -->
        <div class="lg:col-span-9 flex flex-col gap-6">
            <div class="relative flex-1 bg-black rounded-3xl overflow-hidden shadow-2xl border border-white/10 min-h-[400px]">
                <video id="video" class="w-full h-full object-contain" muted playsinline></video>
                <canvas id="overlay" class="absolute inset-0 w-full h-full object-contain"></canvas>
                <canvas id="debugCanvas"></canvas>

                <div class="absolute top-6 left-6 pointer-events-none">
                    <div id="detectionIndicator" class="flex items-center gap-3 px-4 py-2 bg-black/70 backdrop-blur-md rounded-2xl border border-white/10 transition-all opacity-0">
                        <div class="w-2.5 h-2.5 rounded-full bg-green-500 status-pulse"></div>
                        <span class="scanner-font text-xs font-bold text-green-400">TAG DETECTED</span>
                        <span id="targetIdDisplay" class="text-[10px] text-white bg-white/10 px-2 py-0.5 rounded">ID: --</span>
                    </div>
                </div>

                <div id="placeholder" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-all">
                    <svg class="w-16 h-16 text-slate-700 mb-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <p class="text-slate-400 scanner-font text-xs tracking-[0.3em]">AWAITING FEED</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="glass p-4 rounded-2xl border-l-4 border-blue-500">
                    <div class="text-[10px] text-slate-500 mb-1">FPS</div>
                    <div id="statFps" class="scanner-font text-xl font-bold">0</div>
                </div>
                <div class="glass p-4 rounded-2xl border-l-4 border-indigo-500">
                    <div class="text-[10px] text-slate-500 mb-1">DETECTIONS</div>
                    <div id="statDetections" class="scanner-font text-xl font-bold">0</div>
                </div>
                <div class="glass p-4 rounded-2xl border-l-4 border-green-500">
                    <div class="text-[10px] text-slate-500 mb-1">LOGGED</div>
                    <div id="statLogged" class="scanner-font text-xl font-bold">0</div>
                </div>
                <div class="glass p-4 rounded-2xl border-l-4 border-purple-500">
                    <div class="text-[10px] text-slate-500 mb-1">FRAME TIME</div>
                    <div id="statFrameTime" class="scanner-font text-xl font-bold">0 ms</div>
                </div>
            </div>
        </div>
    </main>

    <script src="{{ asset('js/apriltag_wasm.js') }}"></script>
    <script>
        const video = document.getElementById('video');
        const overlay = document.getElementById('overlay');
        const debugCanvas = document.getElementById('debugCanvas');
        const logConsole = document.getElementById('logConsole');
        const btnStart = document.getElementById('btnConnect');
        const btnStop = document.getElementById('btnDisconnect');
        const indicator = document.getElementById('detectionIndicator');
        const streamUrlInput = document.getElementById('streamUrl');
        const sourceTypeInput = document.getElementById('sourceType');
        const debugToggle = document.getElementById('debugMode');

        let apriltag = null; 
        let running = false;
        let detecting = false;
        let stream = null;
        let player = null;
        let frameCount = 0;
        let lastDetections = [];
        let stats = { detections: 0, logged: 0, lastTime: Date.now() };

        function log(msg, type = 'info') {
            const time = new Date().toLocaleTimeString([], { hour12: false });
            const colors = { info: 'text-slate-400', warn: 'text-yellow-400', error: 'text-red-400', success: 'text-green-400' };
            const div = document.createElement('div');
            div.className = colors[type];
            div.innerHTML = `<span class="opacity-40">[${time}]</span> ${msg}`;
            logConsole.prepend(div);
            if (logConsole.children.length > 30) logConsole.lastChild.remove();
        }

        /**
         * Robust WASM Factory (Pattern Sync with index.html)
         */
        async function createAprilTagDetector() {
            try {
                const maybe = AprilTagWasm();
                const det = (maybe && typeof maybe.then === "function") ? await maybe : maybe;
                if (det) return det;
            } catch (e) {}

            try {
                const det = new AprilTagWasm();
                if (typeof det.init === "function") await det.init();
                if (typeof det.ready === "function") await det.ready();
                return det;
            } catch (e) {}

            try {
                if (window.AprilTagWasm && typeof window.AprilTagWasm.create === "function") {
                    return await window.AprilTagWasm.create();
                }
            } catch (e) {}

            throw new Error("WASM API shape unsupported. Check file integrity.");
        }

        async function runDetect(detector, gray, w, h) {
            if (typeof detector.detect === "function") return await detector.detect(gray, w, h);
            if (typeof detector.detectTags === "function") return await detector.detectTags(gray, w, h);
            if (typeof detector.detect_async === "function") return await detector.detect_async(gray, w, h);
            
            // Raw cwrap fallback (Experimental)
            if (detector._atagjs_detect) {
                const ptr = detector._malloc(gray.length);
                detector.HEAPU8.set(gray, ptr);
                const res = detector._atagjs_detect(ptr, w, h);
                detector._free(ptr);
                return res;
            }
            throw new Error("Detector method not found.");
        }

        function normalizeDetections(ret) {
            if (!ret) return [];
            if (Array.isArray(ret)) return ret;
            if (Array.isArray(ret.detections)) return ret.detections;
            if (Array.isArray(ret.results)) return ret.results;
            if (ret.id !== undefined && ret.corners) return [ret];
            return [];
        }

        sourceTypeInput.addEventListener('change', () => {
            document.getElementById('urlContainer').style.display = sourceTypeInput.value === 'camera' ? 'none' : 'block';
        });

        async function start() {
            log("Signal startup initiated...", 'info');
            btnStart.disabled = true;

            try {
                if (!apriltag) {
                    log("WASM Handshake...", 'info');
                    apriltag = await createAprilTagDetector();
                    log("WASM Processor online.", 'success');
                }

                if (sourceTypeInput.value === 'camera') {
                    log("Accessing local optics...", 'info');
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false });
                    video.srcObject = stream;
                } else {
                    const url = streamUrlInput.value.trim();
                    if (!url) throw new Error("RTMP/FLV URL missing.");
                    log(`Establishing downlink: ${url}`, 'info');
                    player = mpegts.createPlayer({ type: 'flv', isLive: true, url: url });
                    player.attachMediaElement(video);
                    player.load();
                }

                await video.play();
                running = true;
                btnStop.disabled = false;
                document.getElementById('placeholder').classList.add('opacity-0');
                document.getElementById('connectionStatus').innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-green-500 status-pulse"></span> ONLINE';
                log("Feed stabilized. Monitoring active.", 'success');
                loop();
            } catch (err) {
                log("STARTUP FAILED: " + err.message, 'error');
                stop();
            }
        }

        function stop() {
            running = false;
            if (player) { player.destroy(); player = null; }
            if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; video.srcObject = null; }
            video.pause();
            btnStart.disabled = false;
            btnStop.disabled = true;
            document.getElementById('placeholder').classList.remove('opacity-0');
            document.getElementById('connectionStatus').innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> OFFLINE';
            log("Feed terminated.", 'warn');
        }

        async function loop() {
            if (!running) return;

            const dw = parseInt(document.getElementById('targetWidth').value);
            const aspect = video.videoHeight / video.videoWidth || 0.75;
            overlay.width = dw;
            overlay.height = Math.round(dw * aspect);
            
            const ctx = overlay.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(video, 0, 0, overlay.width, overlay.height);

            lastDetections.forEach(drawDetection);

            const detectEvery = parseInt(document.getElementById('detectFreq').value) || 3;
            frameCount++;

            if (frameCount % detectEvery === 0 && !detecting) {
                detecting = true;
                const startTime = performance.now();
                
                try {
                    const imgData = ctx.getImageData(0, 0, overlay.width, overlay.height);
                    const p = imgData.data;
                    const gray = new Uint8Array(overlay.width * overlay.height);
                    
                    for (let i = 0, j = 0; i < p.length; i += 4, j++) {
                        gray[j] = (p[i] * 0.299 + p[i+1] * 0.587 + p[i+2] * 0.114) | 0;
                    }

                    if (debugToggle.checked) {
                        debugCanvas.width = overlay.width;
                        debugCanvas.height = overlay.height;
                        debugCanvas.style.display = 'block';
                        const dCtx = debugCanvas.getContext('2d');
                        const dImg = dCtx.createImageData(overlay.width, overlay.height);
                        for (let k = 0; k < gray.length; k++) {
                            const val = gray[k];
                            dImg.data[k*4] = dImg.data[k*4+1] = dImg.data[k*4+2] = val;
                            dImg.data[k*4+3] = 255;
                        }
                        dCtx.putImageData(dImg, 0, 0);
                    } else {
                        debugCanvas.style.display = 'none';
                    }

                    const raw = await runDetect(apriltag, gray, overlay.width, overlay.height);
                    lastDetections = normalizeDetections(raw);
                    
                    if (lastDetections.length > 0) {
                        indicator.style.opacity = "1";
                        document.getElementById('targetIdDisplay').textContent = "ID: " + lastDetections.map(d => d.id).join(", ");
                        stats.detections++;
                        document.getElementById('statDetections').textContent = stats.detections;
                        processValidTags(lastDetections);
                    } else {
                        indicator.style.opacity = "0";
                    }
                } catch (e) {
                    console.error("Detect fail:", e);
                } finally {
                    detecting = false;
                    document.getElementById('statFrameTime').textContent = Math.round(performance.now() - startTime) + ' ms';
                }
            }

            const now = Date.now();
            document.getElementById('statFps').textContent = Math.round(1000 / (now - stats.lastTime));
            stats.lastTime = now;

            requestAnimationFrame(loop);
        }

        function drawDetection(det) {
            const ctx = overlay.getContext('2d');
            const c = det.corners;
            ctx.beginPath();
            ctx.moveTo(c[0].x, c[0].y);
            ctx.lineTo(c[1].x, c[1].y);
            ctx.lineTo(c[2].x, c[2].y);
            ctx.lineTo(c[3].x, c[3].y);
            ctx.closePath();
            ctx.lineWidth = 4;
            ctx.strokeStyle = '#3bed2d';
            ctx.stroke();
            ctx.fillStyle = '#3bed2d';
            ctx.font = 'bold 16px Inter';
            ctx.fillText(`TAG ID:${det.id}`, c[0].x, c[0].y - 10);
        }

        const loggedBuffer = new Set();
        async function processValidTags(found) {
            const valid = document.getElementById('validTags').value.split(',').map(v => parseInt(v.trim()));
            for (const det of found) {
                if (valid.includes(det.id)) {
                    const key = `${det.id}_${Math.floor(Date.now() / 2000)}`;
                    if (!loggedBuffer.has(key)) {
                        loggedBuffer.add(key);
                        log(`DETECTED VALID TAG: ID ${det.id}`, 'success');
                        
                        await fetch('/api/log-detection', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({ tag_id: det.id, source_url: sourceTypeInput.value, metadata: { center: det.center } })
                        }).then(r => { if(r.ok) { stats.logged++; document.getElementById('statLogged').textContent = stats.logged; }});
                    }
                }
            }
        }

        btnStart.addEventListener('click', start);
        btnStop.addEventListener('click', stop);
        window.addEventListener('beforeunload', stop);
    </script>
</body>
</html>
