<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-screen">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Jabroni SSO dummy</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="h-screen antialiased">
        <div class="max-w-xl h-screen mx-auto flex flex-col items-center justify-center">
            <h1 class="text-6xl font-bold mb-12">JABRONI</h1>
            @auth
            <div class="flex items-center justify-center gap-x-5 mb-10">
                <img src="https://ui-avatars.com/api/?name=Morten+Rugaard" class="rounded-lg border border-white outline outline-1 outline-gray-300" width="70" height="70" alt="">
                <div class="flex flex-col justify-center">
                    <h2 class="text-xl text-gray-700 font-semibold mt-0.5">Morten Rugaard</h2>
                    <h3 class="text-sm text-gray-500">morten.rugaard@aller.com</h3>
                    <a href="{{ route('logout') }}" class="text-xs text-gray-400 hover:text-gray-600 hover:underline italic mt-1.5">Log ud <span class="no-underline">(på alle brand sites)</span></a>
                </div>
            </div>
            @endauth
            @guest
            <h2 class="text-3xl font-semibold mb-10">Du er ikke logget ind</h1>
            <fieldset class="bg-gray-50 border p-5 shadow">
                <legend class="text-gray-600 font-semibold bg-white border shadow px-5 py-1 mx-auto">Log ind</legend>
                <form method="post" action="{{ route('login') }}" class="space-y-2.5">
                    @csrf
                    <div class="flex flex-col">
                        <label class="text-md font-medium text-gray-600 mb-0.5">E-mail adresse</label>
                        <input type="email" name="email" class="w-80 px-2.5 py-1.5 border shadow" required />
                    </div>
                    <div class="flex flex-col">
                        <label class="text-md font-medium text-gray-600 mb-0.5">Kodeord</label>
                        <input type="password" name="password" class="w-80 px-2.5 py-1.5 border shadow" required />
                    </div>
                    <input type="submit" name="sso-login" value="Go-go Power Rangers!" class="w-full bg-gray-100 border text-gray-500 shadow hover:bg-gray-200 hover:text-gray-700 px-2.5 py-1.5 font-semibold text-center cursor-pointer" />
                </form>
            </fieldset>
            @endguest
            <div class="text-md flex flex-col items-center justify-center mt-10 space-y-1.5">
                <strong class="font-medium">Aller ID cookie</strong>
                <span class="text-gray-500 italic break-all">{{ $allerIdCookie }}</span>
            </div>
        </div>
    </body>
</html>
