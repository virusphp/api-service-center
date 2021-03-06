<?php

namespace App\Generator;

use LZCompressor\LZString;
date_default_timezone_set('UTC');

class GeneratorBpjs
{
	public CONST ENCRYPT_METHOD = 'AES-256-CBC';

	public static function generateSignature($conId, $secId)
	{
		return base64_encode(hash_hmac('sha256', $conId."&".self::bpjsTimestamp(), $secId, true));
	}

	public static function stringDecrypt($key, $string)
	{
		$encrtyp_method = 'AES-256-CBC';

        $key_hash = hex2bin(hash('sha256', $key));

        $iv = substr(hex2bin(hash('sha256', $key)), 0, 16);

        $output = openssl_decrypt(base64_decode($string), $encrtyp_method, $key_hash, OPENSSL_RAW_DATA, $iv);

        return $output;
	}

	public static function bpjsTimestamp()
	{
		return strval(time()-strtotime('1970-01-01 00:00:00'));
	}

	public static function keyString($conId, $secId) 
	{
		return $conId.$secId.self::bpjsTimestamp();
	}

	public static function keyHash($key)
	{
		return hex2bin(hash('sha256', $key));
	}

	public static function ivDecrypt($key)
	{
		return substr(hex2bin(hash('sha256', $key)), 0, 16);
	}

	public static function decompress($string)
	{
		return LZString::decompressFromEncodedURIComponent($string);
	}

	public static function responseBpjsV2($dataJson, $key)
	{
		$result = json_decode($dataJson);
		if ($result->metaData->code == "200" && is_string($result->response)) {
			
            return json_encode(self::doDecompress($result, $key));
        }
        return json_encode($result);
	}

	protected static function doDecompress($jsonObject, $key)
	{
		if ($jsonObject->metaData->code == "200") {
			return self::mappingResponse($jsonObject->metaData, $jsonObject->response, $key);
		}
		return json_encode($jsonObject);
	}

	protected static function mappingResponse($metaData, $response, $key)
	{
		return [
            "metaData" => $metaData,
            "response" => json_decode(self::decompress(self::stringDecrypt($key, $response)), true)
        ];
	}
}