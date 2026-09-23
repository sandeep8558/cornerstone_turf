<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @section('title', 'Privacy Policy')
    @section('meta_description', 'Privacy Policy for Cornerstone Turf Company. Learn how we collect, use, and protect your personal information.')
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
                <h1 class="text-4xl md:text-5xl font-outfit font-extrabold text-sporty mb-4">Privacy Policy</h1>
                <p class="text-lush font-bold mb-8">Effective Date: April 22, 2026</p>

                <div class="space-y-8 text-gray-600 leading-relaxed">
                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">1. Introduction</h2>
                        <p>Cornerstone Turf Company (“we,” “our,” or “us”) respects your privacy and is committed to protecting your personal information. This Privacy Policy explains how we collect, use, and safeguard your data when you visit our website or interact with our services.</p>
                        <p class="mt-2"><strong>Website:</strong> <a href="https://cornerstoneturfs.com/" class="text-lush hover:underline">https://cornerstoneturfs.com/</a></p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">2. Information We Collect</h2>
                        <p>We may collect the following types of information:</p>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li><strong>Personal Information:</strong> Name, email address, phone number, and address when you submit forms or request services</li>
                            <li><strong>Project Information:</strong> Details such as property specifications, preferences, or photos voluntarily provided</li>
                            <li><strong>Usage Data:</strong> IP address, browser type, device information, and browsing behavior collected via cookies and analytics tools</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">3. How We Use Your Information</h2>
                        <p>We use collected information to:</p>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li>Respond to inquiries and provide quotes</li>
                            <li>Schedule and deliver services</li>
                            <li>Improve our website and user experience</li>
                            <li>Send updates, promotions, or service-related communications (you can opt out anytime)</li>
                            <li>Comply with legal obligations</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">4. Sharing of Information</h2>
                        <p>We do not sell or rent your personal information. However, we may share data with:</p>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li>Trusted service providers (e.g., CRM tools, payment processors)</li>
                            <li>Marketing platforms (e.g., analytics or advertising tools)</li>
                            <li>Legal authorities when required by law</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">5. Cookies & Tracking Technologies</h2>
                        <p>We use cookies and similar technologies to enhance user experience and track performance. These may include:</p>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li>Analytics tools (e.g., website traffic tracking)</li>
                            <li>Advertising pixels</li>
                        </ul>
                        <p class="mt-4">You can control cookie settings through your browser.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">6. Your Rights & Choices</h2>
                        <p>You have the right to:</p>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li>Access, update, or delete your personal data</li>
                            <li>Opt out of marketing communications</li>
                            <li>Disable cookies through browser settings</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">7. Data Security</h2>
                        <p>We implement reasonable security measures such as encryption and secure hosting. However, no system is completely secure.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">8. Children’s Privacy</h2>
                        <p>Our website is not intended for individuals under 13 years of age, and we do not knowingly collect data from children.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">9. Updates to This Policy</h2>
                        <p>We may update this Privacy Policy from time to time. Changes will be posted on this page with an updated effective date.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">10. Contact Information</h2>
                        <p>If you have any questions regarding this Privacy Policy, you may contact us:</p>
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
