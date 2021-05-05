<?php
namespace Modules\Office\Helpers;

use Illuminate\Support\Facades\Auth;
use Modules\Office\Helpers\JwtManager;

class OfficeFunctions
{

    public $config;

    public function __construct() {
        $this->config = require module_path('Office','Config/config.php');
    }


    public function DoUpload($fileUri)
    {
        $_fileName = $this->GetCorrectName($fileUri);

        $ext = strtolower('.' . pathinfo($_fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->getFileExts())) {
            throw new Exception("File type is not supported");
        }

        if (!@copy($fileUri, $this->getStoragePath($_fileName))) {
            $errors = error_get_last();
            $err = "Copy file error: " . $errors['type'] . "<br />\n" . $errors['message'];
            throw new Exception($err);
        }

        return $_fileName;
    }


    /**
     * Generate an error code table
     *
     * @param string $errorCode Error code
     *
     * @return null
     */
    public function ProcessConvServResponceError($errorCode)
    {
        $errorMessageTemplate = "Error occurred in the document service: ";
        $errorMessage = '';

        switch ($errorCode) {
            case -8:
                $errorMessage = $errorMessageTemplate . "Error document VKey";
                break;
            case -7:
                $errorMessage = $errorMessageTemplate . "Error document request";
                break;
            case -6:
                $errorMessage = $errorMessageTemplate . "Error database";
                break;
            case -5:
                $errorMessage = $errorMessageTemplate . "Error unexpected guid";
                break;
            case -4:
                $errorMessage = $errorMessageTemplate . "Error download error";
                break;
            case -3:
                $errorMessage = $errorMessageTemplate . "Error convertation error";
                break;
            case -2:
                $errorMessage = $errorMessageTemplate . "Error convertation timeout";
                break;
            case -1:
                $errorMessage = $errorMessageTemplate . "Error convertation unknown";
                break;
            case 0:
                break;
            default:
                $errorMessage = $errorMessageTemplate . "ErrorCode = " . $errorCode;
                break;
        }

        throw new Exception($errorMessage);
    }


    /**
     * Translation key to a supported form.
     *
     * @param string $expected_key Expected key
     *
     * @return Supported key
     */
    public function GenerateRevisionId($expected_key)
    {
        if (strlen($expected_key) > 20) $expected_key = crc32($expected_key);
        $key = preg_replace("[^0-9-.a-zA-Z_=]", "_", $expected_key);
        $key = substr($key, 0, min(array(strlen($key), 20)));
        return $key;
    }


    /**
     * Request for conversion to a service
     *
     * @param string $document_uri Uri for the document to convert
     * @param string $from_extension Document extension
     * @param string $to_extension Extension to which to convert
     * @param string $document_revision_id Key for caching on service
     * @param bool $is_async Perform conversions asynchronously
     *
     * @return Document request result of conversion
     */
    public function SendRequestToConvertService($document_uri, $from_extension, $to_extension, $document_revision_id, $is_async)
    {
        if (empty($from_extension)) {
            $path_parts = pathinfo($document_uri);
            $from_extension = $path_parts['extension'];
        }

        $title = basename($document_uri);
        if (empty($title)) {
            $title = guid();
        }

        if (empty($document_revision_id)) {
            $document_revision_id = $document_uri;
        }

        $document_revision_id = $this->GenerateRevisionId($document_revision_id);

        $urlToConverter = $this->config['DOC_SERV_CONVERTER_URL'];

        $arr = [
            "async" => $is_async,
            "url" => $document_uri,
            "outputtype" => trim($to_extension, '.'),
            "filetype" => trim($from_extension, '.'),
            "title" => $title,
            "key" => $document_revision_id
        ];

        $jwt = new JwtManager();

        $headerToken = "";
        if ($jwt->isJwtEnabled()) {
            $headerToken = $jwt->jwtEncode(["payload" => $arr]);
            $arr["token"] = $jwt->jwtEncode($arr);
        }

        $data = json_encode($arr);

        $opts = array('http' => array(
            'method' => 'POST',
            'timeout' => $this->config['DOC_SERV_TIMEOUT'],
            'header' => "Content-type: application/json\r\n" .
                "Accept: application/json\r\n" .
                (empty($headerToken) ? "" : "Authorization: $headerToken\r\n"),
            'content' => $data
        )
        );

        if (substr($urlToConverter, 0, strlen("https")) === "https") {
            $opts['ssl'] = array('verify_peer' => FALSE);
        }

        $context = stream_context_create($opts);
        $response_data = file_get_contents($urlToConverter, FALSE, $context);

        return $response_data;
    }


    /**
     * The method is to convert the file to the required format
     *
     * Example:
     * string convertedDocumentUri;
     * GetConvertedUri("http://helpcenter.onlyoffice.com/content/GettingStarted.pdf", ".pdf", ".docx", "http://helpcenter.onlyoffice.com/content/GettingStarted.pdf", false, out convertedDocumentUri);
     *
     * @param string $document_uri Uri for the document to convert
     * @param string $from_extension Document extension
     * @param string $to_extension Extension to which to convert
     * @param string $document_revision_id Key for caching on service
     * @param bool $is_async Perform conversions asynchronously
     * @param string $converted_document_uri Uri to the converted document
     *
     * @return The percentage of completion of conversion
     */
    function GetConvertedUri($document_uri, $from_extension, $to_extension, $document_revision_id, $is_async, &$converted_document_uri)
    {
        $converted_document_uri = "";
        $responceFromConvertService = $this->SendRequestToConvertService($document_uri, $from_extension, $to_extension, $document_revision_id, $is_async);
        $json = json_decode($responceFromConvertService, true);

        $errorElement = $json["error"];
        if ($errorElement != NULL && $errorElement != "") $this->ProcessConvServResponceError($errorElement);

        $isEndConvert = $json["endConvert"];
        $percent = $json["percent"];

        if ($isEndConvert != NULL && $isEndConvert == true) {
            $converted_document_uri = $json["fileUrl"];
            $percent = 100;
        } else if ($percent >= 100)
            $percent = 99;

        return $percent;
    }


    /**
     * Processing document received from the editing service.
     *
     * @param string $document_response The result from editing service
     * @param string $response_uri Uri to the converted document
     *
     * @return The percentage of completion of conversion
     */
    function GetResponseUri($document_response, &$response_uri)
    {
        $response_uri = "";
        $resultPercent = 0;

        if (!$document_response) {
            $errs = "Invalid answer format";
        }

        $errorElement = $document_response->Error;
        if ($errorElement != NULL && $errorElement != "") $this->ProcessConvServResponceError($document_response->Error);

        $endConvert = $document_response->EndConvert;
        if ($endConvert != NULL && $endConvert == "") throw new Exception("Invalid answer format");

        if ($endConvert != NULL && strtolower($endConvert) == true) {
            $fileUrl = $document_response->FileUrl;
            if ($fileUrl == NULL || $fileUrl == "") throw new Exception("Invalid answer format");

            $response_uri = $fileUrl;
            $resultPercent = 100;
        } else {
            $percent = $document_response->Percent;

            if ($percent != NULL && $percent != "")
                $resultPercent = $percent;
            if ($resultPercent >= 100)
                $resultPercent = 99;
        }

        return $resultPercent;
    }

    public function sendlog($msg, $logFileName) {
        $logsFolder = "logs/";
        if (!file_exists($logsFolder)) {
            mkdir($logsFolder);
        }
        file_put_contents($logsFolder . $logFileName, $msg . PHP_EOL, FILE_APPEND);
    }

    public function guid() {
        if (function_exists('com_create_guid')) {
            return com_create_guid();
        } else {
            mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = chr(123)// "{"
                .substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12)
                .chr(125);// "}"
            return $uuid;
        }
    }



    public function getClientIp() {
        $ipaddress =
            getenv('HTTP_CLIENT_IP')?:
                getenv('HTTP_X_FORWARDED_FOR')?:
                    getenv('HTTP_X_FORWARDED')?:
                        getenv('HTTP_FORWARDED_FOR')?:
                            getenv('HTTP_FORWARDED')?:
                                getenv('REMOTE_ADDR')?:
                                    'Storage';

        $ipaddress = preg_replace("/[^0-9a-zA-Z.=]/", "_", $ipaddress);

        return $ipaddress;
    }

    public function serverPath($forDocumentServer = NULL) {
        return $forDocumentServer && isset($this->config['EXAMPLE_URL']) && $this->config['EXAMPLE_URL'] != ""
            ? $this->config['EXAMPLE_URL']
            : ($this->getScheme() . '://' . $_SERVER['HTTP_HOST']);
    }

    public function getCurUserHostAddress($userAddress = NULL) {
        if ($this->config['ALONE']) {
            if (empty($this->config['STORAGE_PATH'])) {
                return "Storage";
            } else {
                return "";
            }
        }
        if (is_null($userAddress)) {
            //$userAddress = $this->getClientIp();
            $userAddress = Auth::user()->id;
        }
        return preg_replace("[^0-9a-zA-Z.=]", '_', $userAddress);
    }

    public function getInternalExtension($filename) {
        $ext = strtolower('.' . pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $this->config['ExtsDocument'])) return ".docx";
        if (in_array($ext, $this->config['ExtsSpreadsheet'])) return ".xlsx";
        if (in_array($ext, $this->config['ExtsPresentation'])) return ".pptx";
        return "";
    }

    public function getDocumentType($filename) {
        $ext = strtolower('.' . pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $this->config['ExtsDocument'])) return "text";
        if (in_array($ext, $this->config['ExtsSpreadsheet'])) return "spreadsheet";
        if (in_array($ext, $this->config['ExtsPresentation'])) return "presentation";
        return "";
    }

    public function getScheme() {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }

    public function getStoragePath($fileName, $userAddress = NULL) {
        $storagePath = trim(str_replace(array('/','\\'), DIRECTORY_SEPARATOR, $this->config['STORAGE_PATH']), DIRECTORY_SEPARATOR);
        if (!file_exists(storage_path('app/public/docs')))
            mkdir(storage_path('app/public/docs'));
        $directory = storage_path('app/public/docs') . DIRECTORY_SEPARATOR . $storagePath;
        if ($storagePath != "")
        {
            $directory =  $directory  . DIRECTORY_SEPARATOR;

            if (!file_exists($directory) && !is_dir($directory)) {
                mkdir($directory);
            }
        }

        $directory = $directory . $this->getCurUserHostAddress($userAddress) . DIRECTORY_SEPARATOR;

        if (!file_exists($directory) && !is_dir($directory)) {
            mkdir($directory);
        }
        //sendlog("getStoragePath result: " . $directory . $fileName, "common.log");
        \Log::info("getStoragePath result: " . $directory . $fileName);
        return $directory . $fileName;
    }

    public function getHistoryDir($storagePath) {
        $directory = $storagePath . "-hist";
        if (!file_exists($directory) && !is_dir($directory)) {
            mkdir($directory);
        }
        return $directory;
    }

    public function getVersionDir($histDir, $version) {
        return $histDir . DIRECTORY_SEPARATOR . $version;
    }

    public function getFileVersion($histDir) {
        if (!file_exists($histDir) || !is_dir($histDir)) return 0;

        $cdir = scandir($histDir);
        $ver = 0;
        foreach($cdir as $key => $fileName) {
            if (!in_array($fileName,array(".", ".."))) {
                if (is_dir($histDir . DIRECTORY_SEPARATOR . $fileName)) {
                    $ver++;
                }
            }
        }
        return $ver;
    }

    public function getStoredFiles() {
        $storagePath = trim(str_replace(array('/','\\'), DIRECTORY_SEPARATOR, $this->config['STORAGE_PATH']), DIRECTORY_SEPARATOR);
        $directory = storage_path('app/public/docs') . DIRECTORY_SEPARATOR . $storagePath;

        $result = array();
        if ($storagePath != "")
        {
            $directory =  $directory . DIRECTORY_SEPARATOR;

            if (!file_exists($directory) && !is_dir($directory)) {
                return $result;
            }
        }

        $directory = $directory . $this->getCurUserHostAddress() . DIRECTORY_SEPARATOR;

        if (!file_exists($directory) && !is_dir($directory)) {
            return $result;
        }

        $currentUser = Auth::user()->id;

        $cdir = scandir($directory);
        $result = array();
        foreach($cdir as $key => $fileName) {
            if (!in_array($fileName,array(".", ".."))) {
                if (!is_dir($directory . $fileName)) {
                    $dat = filemtime($directory . $fileName);
                    $result[$dat] = (object) array(
                        "name" => $fileName,
                        "documentType" => $this->getDocumentType($fileName),
                        "fileUri" => $this->getVirtualPath($fileName) . $fileName,
                        "editUri" => "/plugins/office/editor/?fileID={$fileName}&user={$currentUser}",
                        "viewUri" => "/plugins/office/editor/?fileID={$fileName}&user={$currentUser}&action=view",
                    );
                }
            }
        }
        ksort($result);
        return array_reverse($result);
    }

    public function getVirtualPath($forDocumentServer) {
        $storagePath = trim(str_replace(array('/','\\'), '/', $this->config['STORAGE_PATH']), '/');
        $storagePath = $storagePath != "" ? $storagePath . '/' : "storage/docs/";


        $virtPath = $this->serverPath($forDocumentServer) . '/' . $storagePath . $this->getCurUserHostAddress() . '/';
        //sendlog("getVirtualPath virtPath: " . $virtPath, "common.log");
        \Log::error("getVirtualPath virtPath: " . $virtPath);
        return $virtPath;
    }

    public function createMeta($fileName, $uid = "0") {
        $histDir = $this->getHistoryDir($this->getStoragePath($fileName));
        $currentUser = Auth::user();
        $uid = $currentUser->id;
        $name = $currentUser->name;
        $json = [
            "created" => date("Y-m-d H:i:s"),
            "uid" => $uid,
            "name" => $name,
        ];

        file_put_contents($histDir . DIRECTORY_SEPARATOR . "createdInfo.json", json_encode($json, JSON_PRETTY_PRINT));
    }

    public function FileUri($file_name, $forDocumentServer = NULL) {
        $uri = $this->getVirtualPath($forDocumentServer) . rawurlencode($file_name);
        return $uri;
    }

    public function getFileExts() {
        return array_merge($this->config['DOC_SERV_VIEWD'], $this->config['DOC_SERV_EDITED'], $this->config['DOC_SERV_CONVERT']);
    }

    public function GetCorrectName($fileName) {
        $path_parts = pathinfo($fileName);

        $ext = $path_parts['extension'];
        $name = $path_parts['basename'];
        $baseNameWithoutExt = substr($name, 0, strlen($name) - strlen($ext) - 1);

        for ($i = 1; file_exists($this->getStoragePath($name)); $i++)
        {
            $name = $baseNameWithoutExt . " (" . $i . ")." . $ext;
        }
        return $name;
    }

    public function getDocEditorKey($fileName) {
        $key = $this->getCurUserHostAddress() . $this->FileUri($fileName);
        $stat = filemtime($this->getStoragePath($fileName));
        $key = $key . $stat;
        return $this->GenerateRevisionId($key);
    }

}

