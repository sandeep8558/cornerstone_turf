document.addEventListener('DOMContentLoaded', () => {
    const track = document.getElementById('carouselTrack');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    
    if (!track || !prevBtn || !nextBtn) return;

    let currentIndex = 0;
    const items = Array.from(track.children);
    const totalItems = items.length;

    function getItemsPerView() {
        return window.innerWidth >= 768 ? 3 : 1;
    }

    function updateCarousel() {
        const itemsPerView = getItemsPerView();
        const itemWidth = items[0].getBoundingClientRect().width;
        const gap = 32; // Defined in Tailwind (gap-8 is 32px)
        const offset = currentIndex * (itemWidth + gap);
        track.style.transform = `translateX(-${offset}px)`;

        // Disable buttons if at ends
        prevBtn.disabled = currentIndex === 0;
        nextBtn.disabled = currentIndex >= totalItems - itemsPerView;
        
        prevBtn.style.opacity = prevBtn.disabled ? '0.3' : '1';
        nextBtn.style.opacity = nextBtn.disabled ? '0.3' : '1';
    }

    nextBtn.addEventListener('click', () => {
        const itemsPerView = getItemsPerView();
        if (currentIndex < totalItems - itemsPerView) {
            currentIndex++;
            updateCarousel();
        }
    });

    prevBtn.addEventListener('click', () => {
        if (currentIndex > 0) {
            currentIndex--;
            updateCarousel();
        }
    });

    window.addEventListener('resize', updateCarousel);
    
    // Initial update
    updateCarousel();

    updateCarousel();

    // Auto-play (optional)
    setInterval(() => {
        const itemsPerView = getItemsPerView();
        if (currentIndex < totalItems - itemsPerView) {
            currentIndex++;
        } else {
            currentIndex = 0;
        }
        updateCarousel();
    }, 5000);
});
