<!-- Minecraft Banner & Join Block (Flux UI) -->
<div class="mt-8 flex flex-col items-center gap-6">
    <!-- Minecraft Banner clickable -->
    <flux:modal.trigger name="mc-server-modal">
        <button type="button" class="focus:outline-none mx-auto block">
            <img src="/img/mc-banner.png" alt="Minecraft Server Banner" class="rounded shadow-lg w-full max-w-2xl mx-auto cursor-pointer transition hover:scale-105" style="width:80%;height:auto;" />
        </button>
    </flux:modal.trigger>
    <div class="w-full flex justify-center">
        <flux:modal name="mc-server-modal" class="fixed inset-0 flex items-center justify-center z-50">
            <div class="bg-gray-900 bg-opacity-80 absolute inset-0"></div>
            <div class="relative w-full max-w-xs sm:max-w-md md:max-w-lg lg:max-w-xl xl:max-w-2xl mx-auto bg-white dark:bg-gray-900 rounded-lg shadow-xl p-4 sm:p-8 flex flex-col items-center">
                <flux:heading size="lg" class="mb-4">Join LighthouseMC</flux:heading>
                <div class="flex flex-col gap-3 w-full">
                <!-- Discord -->
                <flux:button
                    variant="primary"
                    color="indigo"
                    onclick="openDiscordInvite('4RNtFNApYt')"
                    class="w-full flex items-center justify-center gap-2"
                >
                    <span class="inline-block align-middle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-discord" viewBox="0 0 16 16">
                            <path d="M13.545 2.907a13.2 13.2 0 0 0-3.257-1.011.05.05 0 0 0-.052.025c-.141.25-.297.577-.406.833a12.2 12.2 0 0 0-3.658 0 8 8 0 0 0-.412-.833.05.05 0 0 0-.052-.025c-1.125.194-2.22.534-3.257 1.011a.04.04 0 0 0-.021.018C.356 6.024-.213 9.047.066 12.032q.003.022.021.037a13.3 13.3 0 0 0 3.995 2.02.05.05 0 0 0 .056-.019q.463-.63.818-1.329a.05.05 0 0 0-.01-.059l-.018-.011a9 9 0 0 1-1.248-.595.05.05 0 0 1-.02-.066l.015-.019q.127-.095.248-.195a.05.05 0 0 1 .051-.007c2.619 1.196 5.454 1.196 8.041 0a.05.05 0 0 1 .053.007q.121.1.248.195a.05.05 0 0 1-.004.085 8 8 0 0 1-1.249.594.05.05 0 0 0-.03.03.05.05 0 0 0 .003.041c.24.465.515.909.817 1.329a.05.05 0 0 0 .056.019 13.2 13.2 0 0 0 4.001-2.02.05.05 0 0 0 .021-.037c.334-3.451-.559-6.449-2.366-9.106a.03.03 0 0 0-.02-.019m-8.198 7.307c-.789 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.45.73 1.438 1.613 0 .888-.637 1.612-1.438 1.612m5.316 0c-.788 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.451.73 1.438 1.613 0 .888-.631 1.612-1.438 1.612"/>
                        </svg>
                    </span>
                    <span class="inline-block align-middle">Join our Discord</span>
                </flux:button>
                <flux:text size="xs" variant="primary" color="indigo" class="text-center">Launches Discord app (if installed). Falls back to browser otherwise.</flux:text>

                <!-- Minecraft: Bedrock -->
                <flux:button
                    variant="primary"
                    color="amber"
                    onclick="copyBedrockServer('mc.lighthousemc.net', 25565)"
                    class="gap-2"
                >
                    <p class="flex">
                        <span class="inline-block transform scale-[0.09] max-w-[58px]">
                            @php
                                $bedrockSvg = file_get_contents(public_path('img/mc-bedrock.svg'));
                            @endphp
                            {!! str_replace('/<svg([^>]*)>/i', '<svg$1 width="24" height="24" viewBox="24 24">', $bedrockSvg) !!}
                        </span>
                    </p>
                    <p class="ml-23 whitespace-wrap">Add Bedrock server</p>
                </flux:button>

                <!-- Minecraft: Java -->
                <flux:button
                    variant="primary"
                    color="emerald"
                    onclick="copyJavaServer('mc.lighthousemc.net')"
                    class="py-1"
                >
                    <p class="flex">
                        <span class="inline-block transform scale-[3.8]">
                            <img src="/img/mc-java-bedrock.png" alt="Java Edition Logo" class="w-8 h-8 object-contain" />
                        </span>
                    </p>
                    <p class="whitespace-wrap ml-12">Add Java server</p>
                </flux:button>

                <!-- How to play info -->
                <flux:button
                    as="a"
                    href="https://www.minecraft.net/en-us/article/how-play-minecraft-server"
                    target="_blank"
                    rel="noopener"
                    variant="primary"
                    color="gray"
                    icon="question-mark-circle"
                >
                    How to add it
                </flux:button>
                </div>
                <div id="mc-toast" class="hidden fixed top-8 left-1/2 -translate-x-1/2 z-50 px-12 py-6 rounded-lg bg-green-600 text-white text-base font-semibold shadow-lg flex items-center gap-6" style="min-width:600px; max-width:98vw; text-align:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <span>Copied to clipboard!<br>Open Minecraft → Multiplayer → Add Server → paste.</span>
                </div>
            </div>
        </flux:modal>
    </div>

    <script>
        function showBedrockInfo() {
            document.getElementById('bedrock-info').classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('bedrock-info').classList.add('hidden');
            }, 6000);
        }
        function copyJavaServer(host) {
            const txt = `${host}`;
            navigator.clipboard.writeText(txt).then(() => {
                const toast = document.getElementById('mc-modal-toast');
                if (toast) { toast.classList.remove('hidden'); setTimeout(() => toast.classList.add('hidden'), 1500); }
            });
        }
    </script>

    <script>
        function launchWithFallback(customUri, fallbackUrl) {
            let didHide = false;
            const w = window.open(customUri, '_self');
            setTimeout(() => {
                if (!didHide) window.location.href = fallbackUrl;
            }, 1200);
            const visHandler = () => { didHide = document.hidden; if (didHide) document.removeEventListener('visibilitychange', visHandler); };
            document.addEventListener('visibilitychange', visHandler);
        }

        function openDiscordInvite(inviteCode) {
            const custom = `discord://-/invite/${encodeURIComponent(inviteCode)}`;
            const fallback = `https://discord.gg/${encodeURIComponent(inviteCode)}`;
            // Try to open Discord app, fallback only if not successful
            let didOpen = false;
            const timeout = setTimeout(() => {
                if (!didOpen) window.open(fallback, '_blank');
            }, 1200);
            window.location.href = custom;
            document.addEventListener('visibilitychange', function handler() {
                if (document.hidden) {
                    didOpen = true;
                    clearTimeout(timeout);
                    document.removeEventListener('visibilitychange', handler);
                }
            });
        }

        function openBedrockAddServer(name, host, port) {
            const pair = `${name}|${host}:${port}`;
            const custom = `minecraft:?addExternalServer=${encodeURIComponent(pair)}`;
            const fallback = "https://www.minecraft.net/en-us/article/how-play-minecraft-server";
            launchWithFallback(custom, fallback);
        }

        async function copyJavaServer(host) {
            const txt = `${host}`;
            try {
                await navigator.clipboard.writeText(txt);
            } catch {
                const ta = document.createElement('textarea');
                ta.value = txt; document.body.appendChild(ta); ta.select();
                document.execCommand('copy'); document.body.removeChild(ta);
            }
            const toast = document.getElementById('mc-toast');
            if (toast) {
                toast.classList.remove('hidden');
                setTimeout(() => toast.classList.add('hidden'), 4000);
            }
        }

        async function copyBedrockServer(host, port) {
            const txt = `${host}:${port}`;
            try {
                await navigator.clipboard.writeText(txt);
            } catch {
                const ta = document.createElement('textarea');
                ta.value = txt; document.body.appendChild(ta); ta.select();
                document.execCommand('copy'); document.body.removeChild(ta);
            }
            const toast = document.getElementById('mc-toast');
            if (toast) {
                toast.classList.remove('hidden');
                setTimeout(() => toast.classList.add('hidden'), 4000);
            }
        }
    </script>
</div>
