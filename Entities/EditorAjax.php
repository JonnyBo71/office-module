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
        $result; $filename;

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
        return $result;
    }

    public function track(Request $request) {
        //sendlog("Track START", "webedior-ajax.log");
        //sendlog("_GET params: " . serialize( $_GET ), "webedior-ajax.log");

        $param = $request->all();

        \Log::info("Track START");
        \Log::info("_GET params: " . serialize( $param ));

        global $_trackerStatus;
        $data;
        $result["error"] = 0;

        if (($body_stream = file_get_contents('php://input'))===FALSE) {
            $result["error"] = "Bad Request";
            return $result;
        }

        $data = json_decode($body_stream, TRUE); //json_decode - PHP 5 >= 5.2.0

        if ($data === NULL) {
            $result["error"] = "Bad Response";
            return $result;
        }

        //sendlog("InputStream data: " . serialize($data), "webedior-ajax.log");
        \Log::info("InputStream data: " . serialize($data));

        if ($this->jwt->isJwtEnabled()) {
            //sendlog("jwt enabled, checking tokens", "webedior-ajax.log");
            \Log::info("jwt enabled, checking tokens");

            $inHeader = false;
            $token = "";
            if (!empty($data["token"])) {
                $token = $this->jwt->jwtDecode($data["token"]);
            } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $token = $this->jwt->jwtDecode(substr($_SERVER['HTTP_AUTHORIZATION'], strlen("Bearer ")));
                $inHeader = true;
            } else {
                //sendlog("jwt token wasn't found in body or headers", "webedior-ajax.log");
                \Log::error("jwt token wasn't found in body or headers");
                $result["error"] = "Expected JWT";
                return $result;
            }
            if (empty($token)) {
                //sendlog("token was found but signature is invalid", "webedior-ajax.log");
                \Log::error("token was found but signature is invalid");
                $result["error"] = "Invalid JWT signature";
                return $result;
            }

            $data = json_decode($token, true);
            if ($inHeader) $data = $data["payload"];
        }

        $status = $_trackerStatus[$data["status"]];

        switch ($status) {
            case "MustSave":
            case "Corrupted":
                if (isset($param['userAddress']) && $param["userAddress"])
                    $userAddress = $param["userAddress"];
                if (isset($param['fileName']) && $param["fileName"])
                    $fileName = $param["fileName"];

                $downloadUri = $data["url"];

                $curExt = strtolower('.' . pathinfo($fileName, PATHINFO_EXTENSION));
                $downloadExt = strtolower('.' . pathinfo($downloadUri, PATHINFO_EXTENSION));

                if ($downloadExt != $curExt) {
                    $key = $this->functions->getDocEditorKey(downloadUri);

                    try {
                        //sendlog("Convert " . $downloadUri . " from " . $downloadExt . " to " . $curExt, "webedior-ajax.log");
                        \Log::info("Convert " . $downloadUri . " from " . $downloadExt . " to " . $curExt);
                        $convertedUri;
                        $percent = $this->functions->GetConvertedUri($downloadUri, $downloadExt, $curExt, $key, FALSE, $convertedUri);
                        $downloadUri = $convertedUri;
                    } catch (Exception $e) {
                        //sendlog("Convert after save ".$e->getMessage(), "webedior-ajax.log");
                        \Log::error("Convert after save ".$e->getMessage());
                        $result["error"] = "error: " . $e->getMessage();
                        return $result;
                    }
                }

                $saved = 1;

                if (($new_data = file_get_contents($downloadUri)) === FALSE) {
                    $saved = 0;
                } else {
                    $storagePath = $this->functions->getStoragePath($fileName, $userAddress);
                    $histDir = $this->functions->getHistoryDir($storagePath);
                    $verDir = $this->functions->getVersionDir($histDir, $this->functions->getFileVersion($histDir) + 1);

                    mkdir($verDir);

                    copy($storagePath, $verDir . DIRECTORY_SEPARATOR . "prev" . $downloadExt);
                    file_put_contents($storagePath, $new_data, LOCK_EX);

                    if ($changesData = file_get_contents($data["changesurl"])) {
                        file_put_contents($verDir . DIRECTORY_SEPARATOR . "diff.zip", $changesData, LOCK_EX);
                    }

                    $histData = $data["changeshistory"];
                    if (empty($histData)) {
                        $histData = json_encode($data["history"], JSON_PRETTY_PRINT);
                    }
                    if (!empty($histData)) {
                        file_put_contents($verDir . DIRECTORY_SEPARATOR . "changes.json", $histData, LOCK_EX);
                    }
                    file_put_contents($verDir . DIRECTORY_SEPARATOR . "key.txt", $data["key"], LOCK_EX);
                }

                $result["c"] = "saved";
                $result["status"] = $saved;
                break;
        }

        //sendlog("track result: " . serialize($result), "webedior-ajax.log");
        \Log::info("track result: " . serialize($result));
        return $result;
    }

    public function convert(Request $request) {
        $param = $request->all();
        if (isset($param['filename']) && $param["filename"])
            $fileName = $param["filename"];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $internalExtension = trim($this->functions->getInternalExtension($fileName),'.');

        if (in_array("." + $extension, $GLOBALS['DOC_SERV_CONVERT']) && $internalExtension != "") {
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
                $fileName = $param['fileName'];

            $filePath = $this->functions->getStoragePath($fileName);

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

    public function delTree($dir) {
        if (!file_exists($dir) || !is_dir($dir)) return;

        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}
