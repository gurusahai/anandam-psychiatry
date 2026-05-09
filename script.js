// script.js — Anandam Psychiatry Centre (Updated with new navigation)

(() => {
    "use strict";

    // -------------------------
    // Helpers
    // -------------------------
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const prefersReducedMotion =
        window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    const rafThrottle = (fn) => {
        let ticking = false;
        return (...args) => {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(() => {
                ticking = false;
                fn(...args);
            });
        };
    };

    // -------------------------
    // Smooth scroll with navbar offset
    // -------------------------
    function scrollToHash(hash) {
        if (!hash || hash === "#") return;
        const id = hash.slice(1);
        const target = document.getElementById(id);
        if (!target) return;

        const navbar = $(".navbar");
        const headerH = navbar ? navbar.offsetHeight : 0;
        const extraPadding = 20;

        const targetRect = target.getBoundingClientRect();
        const targetTop = targetRect.top + window.pageYOffset - headerH - extraPadding;

        window.scrollTo({
            top: targetTop,
            behavior: prefersReducedMotion ? "auto" : "smooth",
        });

        // Update URL
        if (history.pushState) {
            history.pushState(null, null, hash);
        } else {
            location.hash = hash;
        }
    }

    // -------------------------
    // Mobile Menu Functions
    // -------------------------
    function initializeMobileMenu() {
        const menuToggle = $(".menu-toggle");
        const navRight = $(".nav-right");
        const navLinks = $$(".nav-link, .nav-button");

        if (!menuToggle || !navRight) return;

        // Close mobile menu
        const closeMobileMenu = () => {
            menuToggle.classList.remove("active");
            navRight.classList.remove("active");
            menuToggle.setAttribute("aria-expanded", "false");
            document.body.style.overflow = "";
        };

        // Open mobile menu
        const openMobileMenu = () => {
            menuToggle.classList.add("active");
            navRight.classList.add("active");
            menuToggle.setAttribute("aria-expanded", "true");
            document.body.style.overflow = "hidden";
        };

        // Toggle mobile menu
        const toggleMobileMenu = () => {
            if (navRight.classList.contains("active")) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        };

        // Event listener for menu toggle button
        menuToggle.addEventListener("click", toggleMobileMenu);

        // Close menu when clicking on a nav link (mobile)
        navLinks.forEach((link) => {
            link.addEventListener("click", () => {
                if (window.innerWidth <= 768) {
                    closeMobileMenu();
                }
            });
        });

        // Close menu when clicking outside (mobile)
        document.addEventListener("click", (event) => {
            if (!navRight.classList.contains("active")) return;

            const target = event.target;
            const isClickInsideNav = navRight.contains(target) || menuToggle.contains(target);

            if (!isClickInsideNav && window.innerWidth <= 768) {
                closeMobileMenu();
            }
        });

        // Close menu on Escape key
        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && navRight.classList.contains("active")) {
                closeMobileMenu();
            }
        });

        // Close menu on window resize (if resized to desktop)
        window.addEventListener("resize", () => {
            if (window.innerWidth > 768 && navRight.classList.contains("active")) {
                closeMobileMenu();
            }
        });
    }

    // -------------------------
    // Active Link Highlighting
    // -------------------------
    function initializeActiveLinks() {
        const sections = $$("section[id]");
        const navLinks = $$(".nav-link");

        function highlightActiveLink() {
            const scrollY = window.pageYOffset + 100; // Offset for navbar

            sections.forEach((section) => {
                const sectionHeight = section.offsetHeight;
                const sectionTop = section.offsetTop;
                const sectionId = section.getAttribute("id");
                const correspondingLink = $(`.nav-link[href="#${sectionId}"]`);

                if (correspondingLink &&
                    scrollY > sectionTop &&
                    scrollY <= sectionTop + sectionHeight) {

                    navLinks.forEach(link => link.classList.remove("active"));
                    correspondingLink.classList.add("active");
                }
            });

            // If at top of page, remove all active classes
            if (window.scrollY < 100) {
                navLinks.forEach(link => link.classList.remove("active"));
            }
        }

        window.addEventListener("scroll", rafThrottle(highlightActiveLink));
        highlightActiveLink(); // Initial check
    }

    // -------------------------
    // DOM Ready
    // -------------------------
    document.addEventListener("DOMContentLoaded", () => {
        // ===== Footer year =====
        const yearEl = $("#year");
        if (yearEl) yearEl.textContent = String(new Date().getFullYear());

        // ===== Initialize Mobile Menu =====
        initializeMobileMenu();

        // ===== Initialize Active Link Highlighting =====
        initializeActiveLinks();

        // ===== Smooth scroll for anchor links =====
        $$('a[href^="#"]').forEach((link) => {
            // Skip if it's a button or special link
            if (link.classList.contains('btn') || link.getAttribute('href') === '#') return;

            link.addEventListener("click", (e) => {
                const href = link.getAttribute("href");
                if (!href || href === "#") return;

                const id = href.slice(1);
                if (!document.getElementById(id)) return;

                e.preventDefault();
                scrollToHash(href);
            });
        });

        // ===== Navbar scroll effect =====
        const navbar = $(".navbar");
        const onScroll = rafThrottle(() => {
            const currentScrollY = window.scrollY;

            // Add/remove scrolled class
            if (navbar) {
                navbar.classList.toggle("scrolled", currentScrollY > 50);
            }

            // Show/hide back to top button
            const backToTopBtn = $(".back-to-top");
            if (backToTopBtn) {
                backToTopBtn.classList.toggle("visible", currentScrollY > 500);
            }
        });

        window.addEventListener("scroll", onScroll, { passive: true });
        onScroll(); // Initialize

        // ===== Back to top button =====
        const backToTop = $(".back-to-top");
        if (backToTop) {
            backToTop.addEventListener("click", () => {
                window.scrollTo({
                    top: 0,
                    behavior: prefersReducedMotion ? "auto" : "smooth"
                });
                backToTop.blur();
            });
        }

        // ===== FAQ Accordion Enhancement =====
        const faqItems = $$(".faq-item");
        faqItems.forEach(item => {
            const summary = item.querySelector("summary");
            if (summary) {
                summary.addEventListener("keydown", (e) => {
                    if (e.key === "Enter" || e.key === " ") {
                        e.preventDefault();
                        item.open = !item.open;
                    }
                });
            }
        });

        // ===== Service Cards Animation =====
        const observerOptions = {
            threshold: 0.1,
            rootMargin: "0px 0px -50px 0px"
        };

        if ("IntersectionObserver" in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = "1";
                        entry.target.style.transform = "translateY(0)";
                    }
                });
            }, observerOptions);

            // Observe all animated elements
            $$(".service-card, .story-card, .team-member, .contact-method").forEach(el => {
                observer.observe(el);
            });
        } else {
            // Fallback for older browsers
            $$(".service-card, .story-card, .team-member, .contact-method").forEach(el => {
                el.style.opacity = "1";
                el.style.transform = "translateY(0)";
            });
        }

        // ===== Map lazy loading =====
        const mapIframe = $(".contact-map iframe");
        if (mapIframe && "IntersectionObserver" in window) {
            const mapObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const iframe = entry.target;
                        const src = iframe.getAttribute("data-src") || iframe.src;
                        if (iframe.src !== src) {
                            iframe.src = src;
                        }
                        mapObserver.unobserve(iframe);
                    }
                });
            }, { threshold: 0.1 });

            // Store original src in data-src
            if (mapIframe.src) {
                mapIframe.setAttribute("data-src", mapIframe.src);
                mapIframe.src = "";
                mapObserver.observe(mapIframe);
            }
        }

        // ===== Initialize navigation =====
        // Add active class to current page link (simplified example)
        const currentPath = window.location.pathname;
        const currentHash = window.location.hash;

        if (currentHash) {
            const activeLink = $(`.nav-link[href="${currentHash}"]`);
            if (activeLink) {
                activeLink.classList.add("active");
            }
        }
    });

    // -------------------------
    // Polyfills for older browsers
    // -------------------------
    if (!Element.prototype.matches) {
        Element.prototype.matches =
            Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
    }

    if (!Element.prototype.closest) {
        Element.prototype.closest = function (s) {
            let el = this;
            while (el && el.nodeType === 1) {
                if (el.matches(s)) return el;
                el = el.parentElement || el.parentNode;
            }
            return null;
        };
    }

    // Smooth scroll polyfill
    if (!window.requestAnimationFrame) {
        window.requestAnimationFrame = function(callback) {
            return setTimeout(callback, 1000 / 60);
        };
    }

    if (!window.cancelAnimationFrame) {
        window.cancelAnimationFrame = function(id) {
            clearTimeout(id);
        };
    }
})();
