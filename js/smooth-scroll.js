document.addEventListener('DOMContentLoaded', function() {
    // Select all links with hashes
    const links = document.querySelectorAll('a[href^="#"]');

    links.forEach(link => {
        link.addEventListener('click', function(event) {
            // Prevent default anchor click behavior
            event.preventDefault();

            // Store hash
            const hash = this.hash;

            // Animate scroll to the target section
            if (hash) {
                const target = document.querySelector(hash);
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
});