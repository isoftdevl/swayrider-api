<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SwayRider</title>
    <style>
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            background-color: #f4f4f4; 
            margin: 0; 
            padding: 0; 
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background-color: #ffffff; 
            border-radius: 8px; 
            overflow: hidden; 
            margin-top: 20px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        }
        .header { 
            background-color: #10b981; 
            padding: 20px; 
            text-align: center; }
        .header h1 { 
            color: #ffffff; 
            margin: 0; 
            font-size: 24px; 
        }
        .content { 
            padding: 30px; 
            color: #333333; 
            line-height: 1.6; }
        .footer { 
            background-color: #f9fafb; 
            padding: 20px; 
            text-align: center; 
            font-size: 12px; 
            color: #6b7280; 
            border-top: 1px solid #e5e7eb; 
        }
        .button { 
            display: inline-block; 
            background-color: #10b981; 
            color: #ffffff; 
            padding: 12px 24px; 
            text-decoration: none; 
            border-radius: 4px; 
            font-weight: bold; 
            margin-top: 20px; 
        }
        .social-links { 
            margin-top: 10px; 
        }
        .social-links a { 
            color: #10b981; 
            text-decoration: none; 
            margin: 0 5px; 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header"> 
            <h1>SwayRider</h1>
        </div>
        <div class="content">
            @yield('content')
            
            <p>Best regards,<br>The SwayRider Team</p>
        </div>
        <div class="footer">
            <div class="social-links">
                <a href="https://x.com/SwayRiderNG">
                <svg xmlns="http://www.w3.org/2000/svg" shape-rendering="geometricPrecision" text-rendering="geometricPrecision" image-rendering="optimizeQuality" fill-rule="evenodd" clip-rule="evenodd" viewBox="0 0 512 512">
                <path d="M256 0c141.385 0 256 114.615 256 256S397.385 512 256 512 0 397.385 0 256 114.615 0 256 0z"/>
                <path fill="#fff" fill-rule="nonzero" d="M318.64 157.549h33.401l-72.973 83.407 85.85 113.495h-67.222l-52.647-68.836-60.242 68.836h-33.423l78.052-89.212-82.354-107.69h68.924l47.59 62.917 55.044-62.917zm-11.724 176.908h18.51L205.95 176.493h-19.86l120.826 157.964z"/>
                </svg>
                </a> | 
                <a href="https://linkedin.com/SwayRiderNG">
                <svg height="200px" width="200px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 382 382" xml:space="preserve" fill="#000000">
                <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                <g id="SVGRepo_iconCarrier"> 
                <path style="fill:#0077B7;" d="M347.445,0H34.555C15.471,0,0,15.471,0,34.555v312.889C0,366.529,15.471,382,34.555,382h312.889 C366.529,382,382,366.529,382,347.444V34.555C382,15.471,366.529,0,347.445,0z M118.207,329.844c0,5.554-4.502,10.056-10.056,10.056 H65.345c-5.554,0-10.056-4.502-10.056-10.056V150.403c0-5.554,4.502-10.056,10.056-10.056h42.806 c5.554,0,10.056,4.502,10.056,10.056V329.844z M86.748,123.432c-22.459,0-40.666-18.207-40.666-40.666S64.289,42.1,86.748,42.1 s40.666,18.207,40.666,40.666S109.208,123.432,86.748,123.432z M341.91,330.654c0,5.106-4.14,9.246-9.246,9.246H286.73 c-5.106,0-9.246-4.14-9.246-9.246v-84.168c0-12.556,3.683-55.021-32.813-55.021c-28.309,0-34.051,29.066-35.204,42.11v97.079 c0,5.106-4.139,9.246-9.246,9.246h-44.426c-5.106,0-9.246-4.14-9.246-9.246V149.593c0-5.106,4.14-9.246,9.246-9.246h44.426 c5.106,0,9.246,4.14,9.246,9.246v15.655c10.497-15.753,26.097-27.912,59.312-27.912c73.552,0,73.131,68.716,73.131,106.472 L341.91,330.654L341.91,330.654z"></path> </g>
                </svg>
                </a> | 
                <a href="https://web.facebook.com/SwayRiderNG">
                <svg fill="#005cf0" viewBox="0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg" stroke="#005cf0">
                <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                <g id="SVGRepo_iconCarrier"> <title>facebook</title> 
                <path d="M30.996 16.091c-0.001-8.281-6.714-14.994-14.996-14.994s-14.996 6.714-14.996 14.996c0 7.455 5.44 13.639 12.566 14.8l0.086 0.012v-10.478h-3.808v-4.336h3.808v-3.302c-0.019-0.167-0.029-0.361-0.029-0.557 0-2.923 2.37-5.293 5.293-5.293 0.141 0 0.281 0.006 0.42 0.016l-0.018-0.001c1.199 0.017 2.359 0.123 3.491 0.312l-0.134-0.019v3.69h-1.892c-0.086-0.012-0.185-0.019-0.285-0.019-1.197 0-2.168 0.97-2.168 2.168 0 0.068 0.003 0.135 0.009 0.202l-0.001-0.009v2.812h4.159l-0.665 4.336h-3.494v10.478c7.213-1.174 12.653-7.359 12.654-14.814v-0z"></path> </g>
                </svg>
                </a> 
                | <a href="https://instagram.com/SwayRiderNG">
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-label="Instagram" role="img" viewBox="0 0 512 512" fill="#000000">
                <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                <g id="SVGRepo_iconCarrier"> <rect width="512" height="512" rx="15%" id="b"></rect> 
                <use fill="url(#a)" xlink:href="#b"></use> 
                <use fill="url(#c)" xlink:href="#b"></use> 
                <radialGradient id="a" cx=".4" cy="1" r="1"> 
                <stop offset=".1" stop-color="#fd5"></stop> 
                <stop offset=".5" stop-color="#ff543e"></stop> 
                <stop offset="1" stop-color="#c837ab"></stop> 
                </radialGradient> 
                <linearGradient id="c" x2=".2" y2="1"> 
                <stop offset=".1" stop-color="#3771c8"></stop> 
                <stop offset=".5" stop-color="#60f" stop-opacity="0"></stop> 
                </linearGradient> 
                <g fill="none" stroke="#ffffff" stroke-width="30"> 
                <rect width="308" height="308" x="102" y="102" rx="81"></rect> 
                <circle cx="256" cy="256" r="72"></circle> 
                <circle cx="347" cy="165" r="6"></circle> 
                </g> 
                </g>
                </svg>
                </a></div>
            <p>&copy; {{ date('Y') }} SwayRider. All rights reserved.</p>
            <p>Need help? Contact us at <a href="mailto:support@swayrider.com" style="color: #10b981;">support@swayrider.com</a></p>
        </div>
    </div>
</body>
</html>
