<div class="flex flex-col items-center gap-2">
    <button type="button" class="focus:outline-none" onclick="copyAndJoinDiscord()">
        <img src="/storage/how-to-join/discord-banner.png" alt="Join Discord" class="rounded-lg border shadow w-full max-w-md mx-auto transition hover:scale-105" />
    </button>
    <span id="discord-toast" class="hidden text-sm text-green-700 bg-green-100 border border-green-200 rounded-md px-3 py-2 mt-2">
        Minecraft server IP copied! Redirecting to Discord invite...
    </span>
</div>

<script>
    function copyAndJoinDiscord() {
        const mcServer = 'mc.lighthousemc.net:25565';
        navigator.clipboard.writeText(mcServer).then(() => {
            const toast = document.getElementById('discord-toast');
            if (toast) {
                toast.classList.remove('hidden');
                setTimeout(() => {
                    toast.classList.add('hidden');
                    window.location.href = 'https://discord.gg/4RNtFNApYt';
                }, 1800);
            } else {
                window.location.href = 'https://discord.gg/4RNtFNApYt';
            }
        }).catch(() => {
            window.location.href = 'https://discord.gg/4RNtFNApYt';
        });
    }
</script>
