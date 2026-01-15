// booking-modal.js - Updated for Calendly Integration

document.addEventListener('DOMContentLoaded', function() {
    // Booking Modal Elements
    const bookingModal = document.getElementById('booking-modal');
    const bookNowBtn = document.getElementById('book-now-btn');
    const bookNowLinks = document.querySelectorAll('a[href="#book-now"]');
    const modalClose = document.querySelector('.modal-close');

    // Tab elements
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    // Calendly widget state
    let calendlyLoaded = false;

    // Load Calendly script dynamically
    function loadCalendlyScript() {
        if (!calendlyLoaded) {
            const script = document.createElement('script');
            script.type = 'text/javascript';
            script.src = 'https://assets.calendly.com/assets/external/widget.js';
            script.async = true;
            document.head.appendChild(script);
            calendlyLoaded = true;
            console.log('Calendly script loaded');
        }
    }

    // Tab switching function
    function switchTab(tabName) {
        // Update active tab button
        tabBtns.forEach(btn => {
            if (btn.dataset.tab === tabName) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // Update active tab content
        tabContents.forEach(content => {
            if (content.id === tabName) {
                content.classList.add('active');
            } else {
                content.classList.remove('active');
            }
        });

        // Refresh Calendly widget if needed
        if (typeof Calendly !== 'undefined') {
            setTimeout(() => {
                Calendly.initBadgeWidget();
                Calendly.initInlineWidget();
            }, 100);
        }
    }

    // Open modal when clicking "Book Appointment" buttons
    function openBookingModal() {
        // Load Calendly script
        loadCalendlyScript();

        // Show modal
        bookingModal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Initialize first tab
        switchTab('telemedicine');

        // Log booking attempt
        console.log('Booking modal opened');
    }

    // Close modal
    function closeBookingModal() {
        bookingModal.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Event Listeners for opening modal
    if (bookNowBtn) {
        bookNowBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openBookingModal();
        });
    }

    bookNowLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            openBookingModal();
        });
    });

    // Tab button listeners
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            switchTab(tabName);
        });
    });

    // Close modal events
    if (modalClose) {
        modalClose.addEventListener('click', closeBookingModal);
    }

    // Close modal when clicking outside
    bookingModal.addEventListener('click', function(e) {
        if (e.target === bookingModal) {
            closeBookingModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && bookingModal.classList.contains('active')) {
            closeBookingModal();
        }
    });

    // Handle Calendly events (optional)
    window.addEventListener('message', function(e) {
        if (e.data.event && e.data.event.indexOf('calendly') === 0) {
            console.log('Calendly event:', e.data);

            if (e.data.event === 'calendly.event_scheduled') {
                // Log successful booking
                console.log('Appointment booked:', e.data.payload);

                // Optional: Send booking data to your backend
                // sendBookingToBackend(e.data.payload);

                // Show success message
                setTimeout(() => {
                    alert('Appointment booked successfully! You will receive a confirmation email shortly.');
                    closeBookingModal();
                }, 1000);
            }
        }
    });

    // Optional: Send booking data to your backend
    function sendBookingToBackend(bookingData) {
        fetch('/api/bookings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                type: 'calendly_booking',
                data: bookingData,
                timestamp: new Date().toISOString()
            })
        })
            .then(response => response.json())
            .then(data => console.log('Booking saved:', data))
            .catch(error => console.error('Error saving booking:', error));
    }
});