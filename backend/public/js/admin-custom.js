document.addEventListener('DOMContentLoaded', () => {
    // Set up MutationObserver to watch for success notifications and trigger confetti
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === 1) { // Element node
                    const notification = node.classList.contains('fi-no-notification') 
                        ? node 
                        : node.querySelector('.fi-no-notification');
                    if (notification) {
                        const isSuccess = notification.classList.contains('fi-color-success') || 
                                          notification.innerHTML.includes('success') ||
                                          notification.innerHTML.includes('Saved') ||
                                          notification.innerHTML.includes('Created') ||
                                          notification.innerHTML.includes('Success');
                        if (isSuccess && typeof confetti === 'function') {
                            // Run a premium confetti explosion!
                            confetti({
                                particleCount: 150,
                                spread: 80,
                                origin: { y: 0.65 },
                                colors: ['#E6A817', '#FF6B00', '#22c55e', '#3b82f6', '#f59e0b']
                            });
                        }
                    }
                }
            }
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
});
