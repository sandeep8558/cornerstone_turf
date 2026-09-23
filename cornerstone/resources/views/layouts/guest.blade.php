<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.seo')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.scss', 'resources/js/app.js'])

        <style>
            .auth-wrapper {
                background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 50%, #f0fdf4 100%);
                min-height: 100vh;
                width: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 2rem 1rem;
            }

            .auth-logo { margin-bottom: 1.5rem; text-align: center; }
            .auth-logo img { height: 3.5rem; width: auto; object-fit: contain; }

            .auth-card {
                background: #fff;
                border-radius: 1.25rem;
                box-shadow:
                    0 4px 6px -1px rgba(0,0,0,.07),
                    0 10px 15px -3px rgba(0,0,0,.07),
                    0 20px 40px -10px rgba(21,128,61,.18),
                    0 0 0 1px rgba(21,128,61,.06);
                width: 100%;
                max-width: 420px;
                padding: 2.5rem 2.25rem;
            }

            .auth-card h2 {
                font-size: 1.5rem;
                font-weight: 700;
                color: #111827;
                margin: 0 0 .25rem;
            }
            .auth-card .subtitle {
                font-size: .875rem;
                color: #6b7280;
                margin: 0 0 1.75rem;
            }

            .field { margin-bottom: 1rem; }
            .field label {
                display: block;
                font-size: .8125rem;
                font-weight: 600;
                color: #374151;
                margin-bottom: .35rem;
            }
            .field input {
                width: 100%;
                padding: .6rem .85rem;
                border: 1.5px solid #d1d5db;
                border-radius: .625rem;
                font-size: .9rem;
                color: #111827;
                background: #f9fafb;
                transition: border-color .15s, box-shadow .15s;
                outline: none;
            }
            .field input:focus {
                border-color: #16a34a;
                box-shadow: 0 0 0 3px rgba(22,163,74,.15);
                background: #fff;
            }
            .field input::placeholder { color: #9ca3af; }

            .btn-primary {
                display: block;
                width: 100%;
                padding: .75rem 1rem;
                background: #16a34a;
                color: #fff !important;
                font-size: .875rem;
                font-weight: 700;
                letter-spacing: .04em;
                text-transform: uppercase;
                border: none;
                border-radius: .625rem;
                cursor: pointer;
                text-align: center;
                box-shadow: 0 4px 12px rgba(22,163,74,.35);
                transition: background .15s, box-shadow .15s, transform .1s;
            }
            .btn-primary:hover {
                background: #15803d;
                box-shadow: 0 6px 16px rgba(22,163,74,.4);
                transform: translateY(-1px);
            }
            .btn-primary:active {
                background: #166534;
                transform: translateY(0);
                box-shadow: 0 2px 8px rgba(22,163,74,.3);
            }

            .auth-link { color: #16a34a; font-weight: 600; text-decoration: underline; cursor: pointer; }
            .auth-link:hover { color: #15803d; }

            .auth-divider { border: none; border-top: 1px solid #e5e7eb; margin: 1.5rem 0; }

            .check-row { display: flex; align-items: center; gap: .5rem; margin-bottom: 1.25rem; }
            .check-row input[type=checkbox] { width: 1rem; height: 1rem; accent-color: #16a34a; cursor: pointer; }
            .check-row label { font-size: .875rem; color: #6b7280; cursor: pointer; }

            .field-error { font-size: .8rem; color: #dc2626; margin-top: .3rem; }

            .label-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: .35rem; }
            .label-row label { margin-bottom: 0; }

            .auth-footer { margin-top: 1.5rem; text-align: center; font-size: .875rem; color: #6b7280; }

            .icon-circle {
                width: 3rem; height: 3rem;
                border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                margin-bottom: 1.25rem;
            }

            .status-box {
                background: #f0fdf4;
                border: 1px solid #bbf7d0;
                border-radius: .5rem;
                padding: .75rem 1rem;
                font-size: .875rem;
                color: #15803d;
                font-weight: 500;
                margin-bottom: 1.25rem;
            }
        </style>
    </head>
    <body>
        <div class="auth-wrapper">
            <div class="auth-logo">
                <a href="/" wire:navigate>
                    <x-application-logo style="height:9rem; width:auto; object-fit:contain;" />
                </a>
            </div>

            <div class="auth-card">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
