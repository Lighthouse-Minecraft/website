// Time formatting script
// This script formats time elements on the page
// It converts the datetime attribute of <time> elements into a more readable format
// Used by test: tests/Allowlists/time_formatting.php
(function () {
    function formatLocalTimes() {
        const formatter = new Intl.DateTimeFormat('en-US', {
            month: 'short', day: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
        function formatNodeList(nodeList) {
            nodeList.forEach(function (el) {
                const dt = new Date(el.getAttribute('datetime'));
                if (!isNaN(dt.getTime())) {
                    el.textContent = formatter.format(dt);
                }
            });
        }

        // Required by tests: keep this exact selector present
        formatNodeList(document.querySelectorAll('time.comment-ts[datetime]'));
        // Also support generic localized times
        formatNodeList(document.querySelectorAll('time[data-localize][datetime]'));
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', formatLocalTimes);
    } else {
        formatLocalTimes();
    }
})();
