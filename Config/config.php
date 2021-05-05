<?php
$serverUrl = 'https://doc.regagro.net';
return [
    'name' => 'Office',

    'FILE_SIZE_MAX' => 5242880,
    'STORAGE_PATH' => '',
    'ALONE' => false,

    'DOC_SERV_VIEWD' => [".pdf", ".djvu", ".xps"],
    'DOC_SERV_EDITED' => [".docx", ".xlsx", ".csv", ".pptx", ".txt"],
    'DOC_SERV_CONVERT' => [".docm", ".doc", ".dotx", ".dotm", ".dot", ".odt", ".fodt", ".ott", ".xlsm", ".xls", ".xltx", ".xltm", ".xlt", ".ods", ".fods", ".ots", ".pptm", ".ppt", ".ppsx", ".ppsm", ".pps", ".potx", ".potm", ".pot", ".odp", ".fodp", ".otp", ".rtf", ".mht", ".html", ".htm", ".epub"],

    'DOC_SERV_TIMEOUT' => '120000',

    'DOC_SERV_CONVERTER_URL' => $serverUrl . "/ConvertService.ashx",
    'DOC_SERV_API_URL' => $serverUrl . "/web-apps/apps/api/documents/api.js",
    'DOC_SERV_PRELOADER_URL' => $serverUrl . "/web-apps/apps/api/documents/cache-scripts.html",

    'DOC_SERV_JWT_SECRET' => '',

    'EXAMPLE_URL' => env('APP_URL'),

    'MOBILE_REGEX' => "android|avantgo|playbook|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od|ad)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\\/|plucker|pocket|psp|symbian|treo|up\\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino",

    'ExtsSpreadsheet' => [".xls", ".xlsx", ".xlsm", ".xlt", ".xltx", ".xltm", ".ods", ".fods", ".ots", ".csv"],
    'ExtsPresentation' => [".pps", ".ppsx", ".ppsm", ".ppt", ".pptx", ".pptm", ".pot", ".potx", ".potm", ".odp", ".fodp", ".otp"],
    'ExtsDocument' => [".doc", ".docx", ".docm", ".dot", ".dotx", ".dotm", ".odt", ".fodt", ".ott", ".rtf", ".txt", ".html", ".htm", ".mht", ".pdf", ".djvu", ".fb2", ".epub", ".xps"],

];
