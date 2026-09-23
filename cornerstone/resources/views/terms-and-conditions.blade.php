<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @section('title', 'Terms & Conditions')
    @section('meta_description', "Terms and Conditions for using Cornerstone Turf Company's website and services.")
    @include('partials.seo')
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Inter & Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        lush: {
                            DEFAULT: '#22c55e',
                            dark: '#16a34a',
                            light: '#4ade80',
                        },
                        sporty: '#1e293b',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif'],
                    },
                },
            },
        };
    </script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(229, 231, 235, 0.5);
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 font-sans selection:bg-lush selection:text-white">

    <!-- Header -->
    <header class="fixed top-0 left-0 w-full z-50 bg-white/80 backdrop-blur-md shadow-sm">
        <nav class="max-w-7xl mx-auto px-4 h-20 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2 group cursor-pointer transition-transform active:scale-95 duration-200">
                <img src="/assets/images/cornerstoneturf_logo.png" alt="Cornerstone Turf Logo" class="h-12 w-auto">
                <span class="text-base sm:text-xl font-outfit font-extrabold text-lush-dark uppercase tracking-wider group-hover:text-lush transition-colors">Cornerstone Turf</span>
            </a>
            <div class="flex gap-6 items-center">
                <a href="/" class="text-sm font-semibold hover:text-lush transition-colors">Back to Home</a>
            </div>
        </nav>
    </header>

    <main class="pt-32 pb-24">
        <div class="max-w-4xl mx-auto px-4">
            <div class="glass-card rounded-3xl p-8 md:p-12 shadow-xl">
                <h1 class="text-4xl md:text-5xl font-outfit font-extrabold text-sporty mb-4">Terms & Conditions</h1>
                <p class="text-lush font-bold mb-8">Effective Date: April 22, 2026</p>

                <div class="space-y-8 text-gray-600 leading-relaxed">
                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">1. Acceptance of Terms</h2>
                        <p>By accessing or using this website, you agree to be bound by these Terms & Conditions. If you do not agree, please do not use the website.</p>
                        <p class="mt-2"><strong>Website:</strong> <a href="https://cornerstoneturfs.com/" class="text-lush hover:underline">https://cornerstoneturfs.com/</a></p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">2. Use of Website</h2>
                        <p>You agree to use this website only for lawful purposes and not to:</p>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li>Violate any laws or regulations</li>
                            <li>Interfere with website functionality</li>
                            <li>Misuse content or attempt unauthorized access</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">3. Services</h2>
                        <p>This website provides information about artificial turf services, including residential and commercial installations.</p>
                        <p class="mt-4">All service requests and estimates are subject to review and confirmation.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">4. Estimates & Pricing</h2>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li>Quotes provided are approximate and subject to change after site evaluation</li>
                            <li>Prices and services may change without prior notice</li>
                            <li>Quotes may have limited validity (e.g., 30 days)</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">5. Intellectual Property</h2>
                        <p>All website content (text, images, logos, design) is owned by Cornerstone Turf Company and protected under applicable intellectual property laws.</p>
                        <p class="mt-4">You may not copy, reproduce, or distribute content without written permission.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">6. User Submissions</h2>
                        <p>By submitting forms, reviews, or inquiries, you grant us permission to use that information for:</p>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li>Customer service</li>
                            <li>Marketing or promotional purposes</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">7. Third-Party Links</h2>
                        <p>Our website may contain links to external websites. We are not responsible for their content, privacy policies, or practices.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">8. Disclaimer</h2>
                        <p>All information on this website is provided “as is” without warranties of any kind. We do not guarantee accuracy, completeness, or reliability.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">9. Limitation of Liability</h2>
                        <p>Cornerstone Turf Company shall not be liable for any indirect or consequential damages resulting from:</p>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li>Use or inability to use the website</li>
                            <li>Errors or omissions in content</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">10. Indemnification</h2>
                        <p>You agree to indemnify and hold harmless the company from any claims or damages resulting from your misuse of the website.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">11. Changes to Terms</h2>
                        <p>We reserve the right to modify these Terms at any time. Continued use of the website indicates acceptance of updated terms.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">12. Governing Law</h2>
                        <p>These Terms shall be governed by applicable local laws in Ambernath, Maharashtra, India.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">13. Contact Information</h2>
                        <p>For questions regarding these Terms:</p>
                        <div class="mt-4 space-y-2">
                            <p><strong>Email:</strong> <a href="mailto:turfcornerstone@gmail.com" class="text-lush hover:underline">turfcornerstone@gmail.com</a></p>
                            <p><strong>Phone:</strong> <span class="text-sporty">7447867273 / 7447437273</span></p>
                            <p><strong>Website:</strong> <a href="https://cornerstoneturfs.com/" class="text-lush hover:underline">https://cornerstoneturfs.com/</a></p>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-sporty text-white py-12 border-t border-white/10">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-gray-500 text-sm">&copy; 2026 Cornerstone Turf Company. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
