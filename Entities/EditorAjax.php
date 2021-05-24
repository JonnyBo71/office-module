<?php


namespace Modules\Office\Entities;


use App\User;
//use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Office\Helpers\OfficeFunctions;
use Modules\Office\Helpers\JwtManager;


class EditorAjax
{

    public $config;

    public $functions;

    public $jwt;

    public function __construct() {
        $this->config = require module_path('Office','Config/config.php');
        $this->functions = new OfficeFunctions();
        $this->jwt = new JwtManager();
    }

    public function upload(Request $request) {
        //$result; $filename;

        //$file = json_decode($request->post());
        //$file = $request->all();
        //print_r(json_decode($request->getContent(), true));
        //$data = json_decode($request->getContent(), true);
        //print_r($file);

        if ($_FILES['files']['error'] > 0) {
            $result["error"] = 'Error ' . json_encode($_FILES['files']['error']);
            return $result;
        }

        $tmp = $_FILES['files']['tmp_name'];

        if (empty($tmp)) {
            $result["error"] = 'No file sent';
            return $result;
        }

        if (is_uploaded_file($tmp))
        {
            $filesize = $_FILES['files']['size'];
            $ext = strtolower('.' . pathinfo($_FILES['files']['name'], PATHINFO_EXTENSION));

            if ($filesize <= 0 || $filesize > $this->config['FILE_SIZE_MAX']) {
                $result["error"] = 'Не корректный размер файла';
                return $result;
            }

            if (!in_array($ext, $this->functions->getFileExts())) {
                $result["error"] = 'Неправильный тип файла';
                return $result;
            }

            $filename = $this->functions->GetCorrectName($_FILES['files']['name']);
            if (!move_uploaded_file($tmp,  $this->functions->getStoragePath($filename)) ) {
                $result["error"] = 'Ошибка загрузки файла';
                return $result;
            }
            $this->functions->createMeta($filename);

        } else {
            $result["error"] = 'Файл не может быть загружен';
            return $result;
        }

        $result["filename"] = $filename;
        $result["documentType"] = $this->functions->getDocumentType($filename);
        return $result;
    }

    public function track(Request $request) {
        //sendlog("Track START", "webedior-ajax.log");
        //sendlog("_GET params: " . serialize( $_GET ), "webedior-ajax.log");

        $_trackerStatus = array(
            0 => 'NotFound',
            1 => 'Editing',
            2 => 'MustSave',
            3 => 'Corrupted',
            4 => 'Closed',
            6 => 'MustForceSave',
            7 => 'CorruptedForceSave'
        );

        $param = $request->all();

        //print_r($param);

        \Log::info("Track START");
        \Log::info("_GET params: " . print_r( $param, true ));

        //global $_trackerStatus;
        //$data;
        $result["error"] = 0;

        $data = $this->readBody();
        \Log::info("_GET data: " . print_r( $data, true ));
        if (isset($data["error"])){
            return $data;
        }

        $status = $_trackerStatus[$data["status"]];

        $userAddress = $_GET["userAddress"];
        $fileName = basename($_GET["fileName"]);

        switch ($status) {
            case "Editing":
                if ($data["actions"] && $data["actions"][0]["type"] == 0) {
                    $user = $data["actions"][0]["userid"];
                    if (array_search($user, $data["users"]) === FALSE) {
                        $commandRequest = $this->functions->commandRequest("forcesave", $data["key"]);
                        \Log::info("   CommandRequest forcesave: " . serialize($commandRequest));
                    }
                }
                break;
            case "MustSave":
            case "Corrupted":
                $result = $this->processSave($data, $fileName, $userAddress);
                break;
            case "MustForceSave":
            case "CorruptedForceSave":
                $result = $this->processForceSave($data, $fileName, $userAddress);
                break;
        }

        //sendlog("track result: " . serialize($result), "webedior-ajax.log");
        \Log::info("track result: " . serialize($result));
        return $result;
    }

    public function convert(Request $request) {
        $param = $request->all();

        if (isset($param['filename']) && $param["filename"])
            $fileName = basename($param["filename"]);
        if (isset($param['filePass']) && $param["filePass"])
            $filePass = $param["filePass"];

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $internalExtension = trim($this->functions->getInternalExtension($fileName),'.');

        if (in_array("." . $extension, $GLOBALS['DOC_SERV_CONVERT']) && $internalExtension != "") {
            if (isset($param['fileUri']) && $param["fileUri"])
                $fileUri = $param["fileUri"];
            if ($fileUri == NULL || $fileUri == "") {
                $fileUri = $this->functions->FileUri($fileName, TRUE);
            }
            $key = $this->functions->getDocEditorKey($fileName);

            $newFileUri;
            $result;
            $percent;

            try {
                $percent = GetConvertedUri($fileUri, $extension, $internalExtension, $key, TRUE, $newFileUri);
            }
            catch (Exception $e) {
                $result["error"] = "error: " . $e->getMessage();
                return $result;
            }

            if ($percent != 100)
            {
                $result["step"] = $percent;
                $result["filename"] = $fileName;
                $result["fileUri"] = $fileUri;
                return $result;
            }

            $baseNameWithoutExt = substr($fileName, 0, strlen($fileName) - strlen($extension) - 1);

            $newFileName = $this->functions->GetCorrectName($baseNameWithoutExt . "." . $internalExtension);

            if (($data = file_get_contents(str_replace(" ","%20",$newFileUri))) === FALSE) {
                $result["error"] = 'Bad Request';
                return $result;
            } else {
                file_put_contents($this->functions->getStoragePath($newFileName), $data, LOCK_EX);
                $this->functions->createMeta($newFileName);
            }

            $stPath = $this->functions->getStoragePath($fileName);
            unlink($stPath);
            $this->delTree($this->functions->getHistoryDir($stPath));

            $fileName = $newFileName;
        }

        $result["filename"] = $fileName;
        return $result;
    }

    public function delete(Request $request) {
        try {
            $param = $request->all();
            if (isset($param['fileName']) && $param["fileName"])
                $fileName = basename($param['fileName']);

            $filePath = $this->functions->getStoragePath($fileName);
            echo $filePath;
            unlink($filePath);
            $this->delTree($this->functions->getHistoryDir($filePath));
        }
        catch (Exception $e) {
            //sendlog("Deletion ".$e->getMessage(), "webedior-ajax.log");
            \Log::error("Deletion ".$e->getMessage());
            $result["error"] = "error: " . $e->getMessage();
            return $result;
        }
    }

    public function files() {
        try {
            @header( "Content-Type", "application/json" );

            $fileId = $_GET["fileId"];
            $result = $this->functions->getFileInfo($fileId);

            return $result;
        }
        catch (Exception $e) {
            \Log::error("Files ".$e->getMessage());
            $result["error"] = "error: " . $e->getMessage();
            return $result;
        }
    }

    public function assets() {
        $fileName = basename($_GET["name"]);
        $filePath = storage_path('app/public/docs') . DIRECTORY_SEPARATOR . "app_data" . DIRECTORY_SEPARATOR . $fileName;
        $this->downloadFile($filePath);
    }

    public function csv() {
        $fileName =  "csv.csv";
        $filePath = storage_path('app/public/docs') . DIRECTORY_SEPARATOR . "app_data" . DIRECTORY_SEPARATOR . $fileName;
        $this->downloadFile($filePath);
    }

    public function download() {
        try {
            $fileName = basename($_GET["name"]);
            $filePath = $this->functions->getForcesavePath($fileName, null, false);
            if ($filePath == "") {
                $filePath = $this->functions->getStoragePath($fileName, null);
            }
            $this->downloadFile($filePath);
        } catch (Exception $e) {
            \Log::error("Download ".$e->getMessage());
            $result["error"] = "error: File not found";
            return $result;
        }
    }

    public function downloadFile($filePath) {
        if (file_exists($filePath)) {
            if (ob_get_level()) {
                ob_end_clean();
            }

            @header('Content-Length: ' . filesize($filePath));
            @header('Content-Disposition: attachment; filename*=UTF-8\'\'' . urldecode(basename($filePath)));
            @header('Content-Type: ' . mime_content_type($filePath));

            if ($fd = fopen($filePath, 'rb')) {
                while (!feof($fd)) {
                    print fread($fd, 1024);
                }
                fclose($fd);
            }
            exit;
        }
    }

    public function delTree($dir) {
        if (!file_exists($dir) || !is_dir($dir)) return;

        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    public function readBody() {
        $result["error"] = 0;

        if (($body_stream = file_get_contents('php://input')) === FALSE) {
            $result["error"] = "Bad Request";
            return $result;
        }

        $data = json_decode($body_stream, TRUE); //json_decode - PHP 5 >= 5.2.0

        if ($data === NULL) {
            $result["error"] = "Bad Response";
            return $result;
        }

        \Log::info("   InputStream data: " . serialize($data));

        if ($this->jwt->isJwtEnabled()) {
            \Log::info("   jwt enabled, checking tokens");

            $inHeader = false;
            $token = "";
            $jwtHeader = $GLOBALS['DOC_SERV_JWT_HEADER'] == "" ? "Authorization" : $GLOBALS['DOC_SERV_JWT_HEADER'];

            if (!empty($data["token"])) {
                $token = $this->jwt->jwtDecode($data["token"]);
            } elseif (!empty(apache_request_headers()[$jwtHeader])) {
                $token = $this->jwt->jwtDecode(substr(apache_request_headers()[$jwtHeader], strlen("Bearer ")));
                $inHeader = true;
            } else {
                \Log::error("   jwt token wasn't found in body or headers");
                $result["error"] = "Expected JWT";
                return $result;
            }
            if (empty($token)) {
                \Log::error("   token was found but signature is invalid");
                $result["error"] = "Invalid JWT signature";
                return $result;
            }

            $data = json_decode($token, true);
            if ($inHeader) $data = $data["payload"];
        }

        return $data;
    }

    public function processSave($data, $fileName, $userAddress) {
        $downloadUri = $data["url"];

        \Log::info("   Parametr:  " . print_r($data, true));

        $curExt = strtolower('.' . pathinfo($fileName, PATHINFO_EXTENSION));
        $downloadExt = strtolower('.' . pathinfo($downloadUri, PATHINFO_EXTENSION));
        $newFileName = $fileName;

        if ($downloadExt != $curExt) {
            $key = $this->functions->GenerateRevisionId($downloadUri);

            try {
                \Log::info("   Convert " . $downloadUri . " from " . $downloadExt . " to " . $curExt);
                $convertedUri;
                $percent = $this->functions->GetConvertedUri($downloadUri, $downloadExt, $curExt, $key, FALSE, $convertedUri);
                if (!empty($convertedUri)) {
                    $downloadUri = $convertedUri;
                } else {
                    \Log::info("   Convert after save convertedUri is empty");
                    $baseNameWithoutExt = substr($fileName, 0, strlen($fileName) - strlen($curExt));
                    $newFileName = $this->functions->GetCorrectName($baseNameWithoutExt . $downloadExt, $userAddress);
                }
            } catch (Exception $e) {
                \Log::error("   Convert after save ".$e->getMessage());
                $baseNameWithoutExt = substr($fileName, 0, strlen($fileName) - strlen($curExt));
                $newFileName = $this->functions->GetCorrectName($baseNameWithoutExt . $downloadExt, $userAddress);
            }
        }

        $saved = 1;

        $arrContextOptions = array(
            "ssl" => array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        );

        if (!(($new_data = file_get_contents($downloadUri, false, stream_context_create($arrContextOptions))) === FALSE)) {
            $storagePath = $this->functions->getStoragePath($newFileName, $userAddress);
            $histDir = $this->functions->getHistoryDir($storagePath);
            $verDir = $this->functions->getVersionDir($histDir, $this->functions->getFileVersion($histDir));

            mkdir($verDir, 0777, true);

            rename($this->functions->getStoragePath($fileName, $userAddress), $verDir . DIRECTORY_SEPARATOR . "prev" . $curExt);
            file_put_contents($storagePath, $new_data, LOCK_EX);

            if ($changesData = file_get_contents($data["changesurl"], false, stream_context_create($arrContextOptions))) {
                file_put_contents($verDir . DIRECTORY_SEPARATOR . "diff.zip", $changesData, LOCK_EX);
            }
            $histData;
            if (isset($data["changeshistory"]))
                $histData = $data["changeshistory"];
            if (empty($histData)) {
                $histData = json_encode($data["history"], JSON_PRETTY_PRINT);
            }
            if (!empty($histData)) {
                file_put_contents($verDir . DIRECTORY_SEPARATOR . "changes.json", $histData, LOCK_EX);
            }
            file_put_contents($verDir . DIRECTORY_SEPARATOR . "key.txt", $data["key"], LOCK_EX);

            $forcesavePath = $this->functions->getForcesavePath($newFileName, $userAddress, false);
            if ($forcesavePath != "") {
                unlink($forcesavePath);
            }

            $saved = 0;
        }

        $result["error"] = $saved;

        return $result;
    }

    public function processForceSave($data, $fileName, $userAddress) {
        $downloadUri = $data["url"];

        $curExt = strtolower('.' . pathinfo($fileName, PATHINFO_EXTENSION));
        $downloadExt = strtolower('.' . pathinfo($downloadUri, PATHINFO_EXTENSION));
        $newFileName = false;

        if ($downloadExt != $curExt) {
            $key = $this->functions->GenerateRevisionId($downloadUri);

            try {
                \Log::info("   Convert " . $downloadUri . " from " . $downloadExt . " to " . $curExt);
                $convertedUri;
                $percent = $this->functions->GetConvertedUri($downloadUri, $downloadExt, $curExt, $key, FALSE, $convertedUri);
                if (!empty($convertedUri)) {
                    $downloadUri = $convertedUri;
                } else {
                    \Log::info("   Convert after save convertedUri is empty");
                    $newFileName = true;
                }
            } catch (Exception $e) {
                \Log::error("   Convert after save ".$e->getMessage());
                $newFileName = true;
            }
        }

        $saved = 1;

        if (!(($new_data = file_get_contents($downloadUri)) === FALSE)) {
            $baseNameWithoutExt = substr($fileName, 0, strlen($fileName) - strlen($curExt));
            $isSubmitForm = $data["forcesavetype"] == 3;

            if ($isSubmitForm) {
                if ($newFileName){
                    $fileName = $this->functions->GetCorrectName($baseNameWithoutExt . "-form" . $downloadExt, $userAddress);
                } else {
                    $fileName = $this->functions->GetCorrectName($baseNameWithoutExt . "-form" . $curExt, $userAddress);
                }
                $forcesavePath = $this->functions->getStoragePath($fileName, $userAddress);
            } else {
                if ($newFileName){
                    $fileName = $this->functions->GetCorrectName($baseNameWithoutExt . $downloadExt, $userAddress);
                }
                $forcesavePath = $this->functions->getForcesavePath($fileName, $userAddress, false);
                if ($forcesavePath == "") {
                    $forcesavePath = $this->functions->getForcesavePath($fileName, $userAddress, true);
                }
            }

            file_put_contents($forcesavePath, $new_data, LOCK_EX);

            if ($isSubmitForm) {
                $user = $data["actions"][0]["userid"];
                $this->functions->createMeta($fileName, $user, $userAddress);
            }

            $saved = 0;
        }

        $result["error"] = $saved;

        return $result;
    }

}
