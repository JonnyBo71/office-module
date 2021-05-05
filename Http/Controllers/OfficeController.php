<?php

namespace Modules\Office\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Users\Users;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use App\Altrp\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\Office\Entities\EditorAjax;
use Modules\Office\Helpers\OfficeFunctions;
use Modules\Office\Helpers\JwtManager;


class OfficeController extends Controller
{

    public $config;

    public $functions;

    public $jwt;

    public function __construct() {
        $this->config = require module_path('Office','Config/config.php');
        $this->functions = new OfficeFunctions();
        $this->jwt = new JwtManager();
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $currentUser = Auth::user();
        if (!$currentUser)
            return redirect('/login');
        $config = $this->config;
        $storedFiles = $this->functions->getStoredFiles();
        return view('Office::index', compact('currentUser', 'config', 'storedFiles'));
    }


    public function editor(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser)
            return redirect('/login');

        $data = $request->all();

        if (isset($data["fileUrl"]) && $data["fileUrl"])
            $externalUrl = trim(strip_tags($data["fileUrl"]));
        if (!empty($externalUrl)) {
            $filename = $this->functions->DoUpload($externalUrl);
        } else {
            if (isset($data["fileID"]) && $data["fileID"])
                $filename = basename(trim(strip_tags($data["fileID"])));
        }
        if (isset($data["fileExt"]) && $data["fileExt"])
            $createExt = trim(strip_tags($data["fileExt"]));

        if (!empty($createExt)) {
            $filename = $this->tryGetDefaultByType($request, $createExt);

            $new_url = "/plugins/office/editor/?fileID=" . $filename . "&user=" . $data["user"];
            header('Location: ' . $new_url, true);
            exit;
        }

        $fileuri = $this->functions->FileUri($filename, true);
        $fileuriUser = $this->functions->FileUri($filename);
        $docKey = $this->functions->getDocEditorKey($filename);
        $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $uid = $currentUser->id;
        $uname = $currentUser->name;

        $editorsMode = empty($data["action"]) ? "edit" : $data["action"];
        $canEdit = in_array(strtolower('.' . pathinfo($filename, PATHINFO_EXTENSION)), $this->config['DOC_SERV_EDITED']);
        $mode = $canEdit && $editorsMode != "view" ? "edit" : "view";

        $option = [
            "type" => empty($data["type"]) ? "desktop" : $data["type"],
            "documentType" => $this->functions->getDocumentType($filename),
            "document" => [
                "title" => $filename,
                "url" => $fileuri,
                "fileType" => $filetype,
                "key" => $docKey,
                "info" => [
                    "author" => $currentUser->name,
                    "created" => date('d.m.y')
                ],
                "permissions" => [
                    "comment" => $editorsMode != "view" && $editorsMode != "fillForms" && $editorsMode != "embedded" && $editorsMode != "blockcontent",
                    "download" => true,
                    "edit" => $canEdit && ($editorsMode == "edit" || $editorsMode == "filter" || $editorsMode == "blockcontent"),
                    "fillForms" => $editorsMode != "view" && $editorsMode != "comment" && $editorsMode != "embedded" && $editorsMode != "blockcontent",
                    "modifyFilter" => $editorsMode != "filter",
                    "modifyContentControl" => $editorsMode != "blockcontent",
                    "review" => $editorsMode == "edit" || $editorsMode == "review"
                ]
            ],
            "editorConfig" => [
                "actionLink" => empty($data["actionLink"]) ? null : json_decode($data["actionLink"]),
                "mode" => $mode,
                "lang" => empty($_COOKIE["ulang"]) ? "ru" : $_COOKIE["ulang"],
                "callbackUrl" => $this->getCallbackUrl($filename),
                "user" => [
                    "id" => $uid,
                    "name" => $uname
                ],
                "embedded" => [
                    "saveUrl" => $fileuriUser,
                    "embedUrl" => $fileuriUser,
                    "shareUrl" => $fileuriUser,
                    "toolbarDocked" => "top",
                ],
                "customization" => [
                    "about" => true,
                    "feedback" => true,
                    "goback" => [
                        "url" => $this->functions->serverPath(),
                    ]
                ]
            ]
        ];
        if ($this->jwt->isJwtEnabled()) {
            $option["token"] = $this->jwt->jwtEncode($option);
        }

        $fileInfo = [
            'filename' => $filename,
            'filetype' => $filetype,
            'docKey' => $docKey,
            'fileuri' => $fileuri
        ];

        $out = $this->getHistory($filename, $filetype, $docKey, $fileuri);

        $config = $this->config;

        return view('Office::editor', compact('currentUser', 'config', 'fileInfo', 'option', 'out'));
    }

    public function webeditor(Request $request) {
        $_trackerStatus = array(
            0 => 'NotFound',
            1 => 'Editing',
            2 => 'MustSave',
            3 => 'Corrupted',
            4 => 'Closed'
        );

        $data = $request->all();

        if (isset($_GET["type"]) && !empty($_GET["type"])) { //Checks if type value exists
            $response_array;
            @header( 'Content-Type: application/json; charset==utf-8');
            @header( 'X-Robots-Tag: noindex' );
            @header( 'X-Content-Type-Options: nosniff' );

            $this->nocache_headers();

            //sendlog(serialize($_GET), "webedior-ajax.log");
            \Log::error(serialize($_GET));

            $type = trim(strip_tags($_GET["type"]));

            $editorAjax = new EditorAjax();

            switch($type) { //Switch case for value of type
                case "upload":
                    $response_array = $editorAjax->upload($request);
                    $response_array['status'] = isset($response_array['error']) ? 'error' : 'success';
                    die (json_encode($response_array));
                case "convert":
                    $response_array = $editorAjax->convert($request);
                    $response_array['status'] = 'success';
                    die (json_encode($response_array));
                case "track":
                    $response_array = $editorAjax->track($request);
                    $response_array['status'] = 'success';
                    die (json_encode($response_array));
                case "delete":
                    $response_array = $editorAjax->delete($request);
                    $response_array['status'] = 'success';
                    die (json_encode($response_array));
                default:
                    $response_array['status'] = 'error';
                    $response_array['error'] = '404 Method not found';
                    die(json_encode($response_array));
            }
        }
    }

    protected function tryGetDefaultByType(Request $request, $createExt) {
        $data = $request->all();
        $demoName = ((isset($data["sample"]) && $data["sample"]) ? "demo." : "new.") . $createExt;
        $demoFilename = $this->functions->GetCorrectName($demoName);

        if(!@copy(storage_path('app/public/docs') . DIRECTORY_SEPARATOR . "app_data" . DIRECTORY_SEPARATOR . $demoName, $this->functions->getStoragePath($demoFilename)))
        {
            //sendlog("Copy file error to ". $this->functions->getStoragePath($demoFilename), "common.log");
            //Copy error!!!
            \Log::error("Copy file error to ". $this->functions->getStoragePath($demoFilename));
        }

        $this->functions->createMeta($demoFilename, $data["user"]);

        return $demoFilename;
    }

    protected function getCallbackUrl($fileName) {
        return $this->functions->serverPath(TRUE) . '/'
            . "/plugins/office/webeditor/"
            . "?type=track"
            . "&fileName=" . urlencode($fileName)
            . "&userAddress=" . $this->functions->getClientIp();
    }

    protected function getHistory($filename, $filetype, $docKey, $fileuri) {
        $histDir = $this->functions->getHistoryDir($this->functions->getStoragePath($filename));

        if ($this->functions->getFileVersion($histDir) > 0) {
            $curVer = $this->functions->getFileVersion($histDir);

            $hist = [];
            $histData = [];

            for ($i = 0; $i <= $curVer; $i++) {
                $obj = [];
                $dataObj = [];
                $verDir = $this->functions->getVersionDir($histDir, $i + 1);

                $key = $i == $curVer ? $docKey : file_get_contents($verDir . DIRECTORY_SEPARATOR . "key.txt");
                $obj["key"] = $key;
                $obj["version"] = $i;

                if ($i == 0) {
                    $createdInfo = file_get_contents($histDir . DIRECTORY_SEPARATOR . "createdInfo.json");
                    $json = json_decode($createdInfo, true);

                    $obj["created"] = $json["created"];
                    $obj["user"] = [
                        "id" => $json["uid"],
                        "name" => $json["name"]
                    ];
                }

                $prevFileName = $verDir . DIRECTORY_SEPARATOR . "prev." . $filetype;
                $prevFileName = substr($prevFileName, strlen($this->functions->getStoragePath("")));
                $dataObj["key"] = $key;
                $dataObj["url"] = $i == $curVer ? $fileuri : $this->functions->getVirtualPath(true) . str_replace("%5C", "/", rawurlencode($prevFileName));
                $dataObj["version"] = $i;

                if ($i > 0) {
                    $changes = json_decode(file_get_contents($this->functions->getVersionDir($histDir, $i) . DIRECTORY_SEPARATOR . "changes.json"), true);
                    $change = $changes["changes"][0];

                    $obj["changes"] = $changes["changes"];
                    $obj["serverVersion"] = $changes["serverVersion"];
                    $obj["created"] = $change["created"];
                    $obj["user"] = $change["user"];

                    $prev = $histData[$i -1];
                    $dataObj["previous"] = [
                        "key" => $prev["key"],
                        "url" => $prev["url"]
                    ];
                    $changesUrl = $this->functions->getVersionDir($histDir, $i) . DIRECTORY_SEPARATOR . "diff.zip";
                    $changesUrl = substr($changesUrl, strlen($this->functions->getStoragePath("")));

                    $dataObj["changesUrl"] = $this->functions->getVirtualPath(true) . str_replace("%5C", "/", rawurlencode($changesUrl));
                }

                array_push($hist, $obj);
                $histData[$i] = $dataObj;
            }

            $out = [];
            array_push($out, [
                "currentVersion" => $curVer,
                "history" => $hist
            ],
                $histData);
            return $out;
        }
    }

    protected function nocache_headers() {
        $headers = array(
            'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT',
            'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        );
        $headers['Last-Modified'] = false;


        unset( $headers['Last-Modified'] );

        // In PHP 5.3+, make sure we are not sending a Last-Modified header.
        if ( function_exists( 'header_remove' ) ) {
            @header_remove( 'Last-Modified' );
        } else {
            // In PHP 5.2, send an empty Last-Modified header, but only as a
            // last resort to override a header already sent. #WP23021
            foreach ( headers_list() as $header ) {
                if ( 0 === stripos( $header, 'Last-Modified' ) ) {
                    $headers['Last-Modified'] = '';
                    break;
                }
            }
        }

        foreach( $headers as $name => $field_value )
            @header("{$name}: {$field_value}");
    }

}
