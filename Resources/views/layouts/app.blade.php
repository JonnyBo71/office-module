<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.14/css/bootstrap-select.css" />
    <link href="{{ Module::asset('office:css/stylesheet.css') }}" rel="stylesheet">
    <link href="{{ Module::asset('office:css/jquery-ui.css') }}" rel="stylesheet">
</head>
<body>
<div id="app" style="height: 100%;">
    <main style="height: 100%;">
        @yield('content')
    </main>

    <div class="modal fade" id="staticBackdrop" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>
<script src="{{  Module::asset('office:js/jquery-ui.min.js') }}"></script>
<script src="{{  Module::asset('office:js/jquery.blockUI.js') }}"></script>
<script src="{{  Module::asset('office:js/jquery.iframe-transport.js') }}"></script>
<script src="{{  Module::asset('office:js/jquery.fileupload.js') }}"></script>
<script src="{{  Module::asset('office:js/jquery.dropdownToggle.js') }}"></script>
<script src="{{  Module::asset('office:js/jscript.js') }}"></script>
</body>
</html>
