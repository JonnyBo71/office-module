<?php

namespace Modules\Office\Helpers;

class JwtManager
{

    public $config;

    public function __construct() {
        $this->config = require module_path('Office','Config/config.php');
    }

    public function isJwtEnabled()
    {
        return !empty($this->config['DOC_SERV_JWT_SECRET']);
    }

    public function jwtEncode($payload)
    {
        $header = [
            "alg" => "HS256",
            "typ" => "JWT"
        ];
        $encHeader = $this->base64UrlEncode(json_encode($header));
        $encPayload = $this->base64UrlEncode(json_encode($payload));
        $hash = $this->base64UrlEncode($this->calculateHash($encHeader, $encPayload));

        return "$encHeader.$encPayload.$hash";
    }

    public function jwtDecode($token)
    {
        if (!$this->isJwtEnabled()) return "";

        $split = explode(".", $token);
        if (count($split) != 3) return "";

        $hash = $this->base64UrlEncode($this->calculateHash($split[0], $split[1]));

        if (strcmp($hash, $split[2]) != 0) return "";
        return $this->base64UrlDecode($split[1]);
    }

    public function calculateHash($encHeader, $encPayload)
    {
        return hash_hmac("sha256", "$encHeader.$encPayload", $this->config['DOC_SERV_JWT_SECRET'], true);
    }

    public function base64UrlEncode($str)
    {
        return str_replace("/", "_", str_replace("+", "-", trim(base64_encode($str), "=")));
    }

    public function base64UrlDecode($payload)
    {
        $b64 = str_replace("_", "/", str_replace("-", "+", $payload));
        switch (strlen($b64) % 4) {
            case 2:
                $b64 = $b64 . "==";
                break;
            case 3:
                $b64 = $b64 . "=";
                break;
        }
        return base64_decode($b64);
    }
}
