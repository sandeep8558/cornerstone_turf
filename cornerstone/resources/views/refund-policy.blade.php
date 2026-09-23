<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @section('title', 'Refund & Cancellation Policy')
    @section('meta_description', 'Refund and Cancellation Policy for Cornerstone Turf Company. Learn about our terms for cancellations and refunds.')
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
                <h1 class="text-4xl md:text-5xl font-outfit font-extrabold text-sporty mb-4">Refund & Cancellation Policy</h1>
                <p class="text-lush font-bold mb-8">Effective Date: April 22, 2026</p>

                <div class="space-y-8 text-gray-600 leading-relaxed">
                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">1. Overview</h2>
                        <p>This Refund & Cancellation Policy outlines the terms under which services may be canceled, rescheduled, or refunded. By booking services with Cornerstone Turf Company, you agree to the terms described below.</p>
                        <p class="mt-2"><strong>Website:</strong> <a href="https://cornerstoneturfs.com/" class="text-lush hover:underline">https://cornerstoneturfs.com/</a></p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">2. Estimates & Deposits</h2>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li>All quotes provided are estimates and may change based on site conditions or customer requirements.</li>
                            <li>A deposit may be required to confirm scheduling and secure materials.</li>
                            <li>Deposits are typically non-refundable, except as outlined below.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">3. Cancellation Policy</h2>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-xl font-bold text-sporty">a. Customer-Initiated Cancellations</h3>
                                <ul class="list-disc ml-6 mt-2 space-y-2">
                                    <li>Cancellations made at least 48 hours before the scheduled service may be eligible for a partial refund of the deposit.</li>
                                    <li>Cancellations made within 48 hours of the scheduled service may result in forfeiture of the deposit.</li>
                                    <li>If materials have already been ordered or work has begun, cancellation fees may apply.</li>
                                </ul>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-sporty">b. Company-Initiated Cancellations</h3>
                                <p>We reserve the right to cancel or reschedule services due to weather conditions, safety concerns, or unforeseen circumstances.</p>
                                <p class="mt-2">In such cases, customers will be offered:</p>
                                <ul class="list-disc ml-6 mt-2 space-y-2">
                                    <li>A rescheduled service date, or</li>
                                    <li>A full or partial refund, depending on the situation</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">4. Refund Policy</h2>
                        <p>Refunds may be issued under the following conditions:</p>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li><strong>Service Not Delivered:</strong> Full refund if services cannot be completed due to company-related reasons</li>
                            <li><strong>Project Cancellation Before Start:</strong> Partial refund depending on incurred costs (materials, labor preparation, etc.)</li>
                            <li><strong>Unsatisfactory Work:</strong> We will first attempt to resolve the issue. Refunds are issued only if a resolution cannot be achieved</li>
                        </ul>
                        <p class="mt-4 font-bold text-sporty">Non-Refundable Situations</p>
                        <p>Refunds will generally not be provided for:</p>
                        <ul class="list-disc ml-6 mt-2 space-y-2">
                            <li>Completed services that meet agreed specifications</li>
                            <li>Changes in customer preferences after project completion</li>
                            <li>Delays caused by weather or external factors beyond our control</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">5. Rescheduling</h2>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li>Customers may request to reschedule services with at least 48 hours’ notice</li>
                            <li>Rescheduling is subject to availability</li>
                            <li>Multiple rescheduling requests may require a new deposit</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">6. Project Modifications</h2>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li>Any changes to the agreed scope of work may result in adjusted pricing and timelines</li>
                            <li>Additional costs must be approved before work continues</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">7. Payment Disputes</h2>
                        <p>If you have concerns regarding billing or charges, please contact us immediately. We aim to resolve disputes promptly and fairly.</p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">8. Processing of Refunds</h2>
                        <ul class="list-disc ml-6 mt-4 space-y-2">
                            <li>Approved refunds will be processed within 7–14 business days</li>
                            <li>Refunds will be issued using the original payment method, unless otherwise agreed</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-outfit font-bold text-sporty mb-4">9. Contact Information</h2>
                        <p>For cancellations, refunds, or questions regarding this policy, please contact:</p>
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
