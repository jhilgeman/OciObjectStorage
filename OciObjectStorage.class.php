<?php
/* OciObjectStorage
 * @author: Jonathan Hilgeman
 * @version: 1.0
 * @created: 2024-10-26
 * @updated: 2024-10-26
 * @description: Class to simplify the use of the OCI Object Storage API
 */
class OciObjectStorage
{
  private $apiHost;
  private $config;
  
  public $caInfo;
  public $debug = false;
  
  public function __construct($objOciConfig)
  {
    $this->config = $objOciConfig;
    $this->apiHost = "objectstorage.{$this->config->region}.oraclecloud.com";
  }
  
  /*
   * Download a file from OCI Object Storage, given a namespace, bucket, and filename
   */  
  public function DownloadFile($namespace, $bucket, $file)
  {
    $request_path = "/n/".rawurlencode($namespace)."/b/".rawurlencode($bucket)."/o/".rawurlencode($file);
    return $this->_curl_get($request_path);
  }
  
  /*
   * Debugging call
   */  
  private function _debug($msg)
  {
    if(!$this->debug) { return; }
    $msg = trim($msg);
    echo date("c") . " :: {$msg}\n";
  }

  /*
   * Perform a GET request via cURL
   */  
  private function _curl_get($request_path)
  {
    return $this->_curl("get", $request_path);
  }
 
  /*
   * Perform a cURL request
   */  
  private function _curl($method, $request_path)
  {
    // Generate the Authorization header value and the Date header value
    $this->_debug("Generating Authorization and Date headers...");
    $auth_header_parts = $this->_generateAuthSignature($method, $request_path);
    
    // Setup array of HTTP headers
    $headers = array();
    foreach($auth_header_parts as $header => $value)
    {
      $headers[] = "{$header}: {$value}";
    }

    $url = "https://{$this->apiHost}{$request_path}";
    $this->_debug("Initializing cURL...");
    $ch = curl_init();
    
    $this->_debug("URL: {$url}");
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Set the CAINFO cURL parameter
    // Note: Download the latest cacert.pem from the official cURL site: https://curl.se/ca/cacert.pem
    if($this->caInfo !== null)
    {
      if($this->caInfo === false)
      {
        // If the caInfo property is set explicitly to FALSE, then disable certificate verification. Don't do this in production!!!
        $this->_debug("caInfo was false, so disabling certificate verification.");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      }
      else
      {
        // If the caInfo property is set explicitly to FALSE, then disable certificate verification. Don't do this in production!!!
        if(!file_exists($this->caInfo))
        {
          throw new \Exception("CA Info is set to a non-existent file.");
        }
        $this->_debug("Setting CURLOPT_CAINFO to " . $this->caInfo);
        curl_setopt($ch, CURLOPT_CAINFO, $this->caInfo);
      }
    }
    
    
    if($this->debug)
    {
      $this->_debug("Enabling verbose logging in cURL...");
      $fpTemp = fopen("php://temp","w+");
      curl_setopt($ch, CURLOPT_STDERR, $fpTemp);
      curl_setopt($ch, CURLOPT_VERBOSE, true);
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $this->_debug("Executing cURL request...");
    $result = curl_exec($ch);
    
    if($this->debug)
    {
      // Get cURL verbose output
      $pos = ftell($fpTemp);
      rewind($fpTemp);
      $log = stream_get_contents($fpTemp);
      fclose($fpTemp);
      
      $this->_debug("cURL Log: {$log}");
      
      $info = curl_getinfo($ch);
      $this->_debug("cURL Info: " . print_r($info, true));
    }
    
    $error = curl_error($ch);
    curl_close($ch);
    return $result;
  }
  
  /*
   * Generate the Authorization header value, plus the corresponding Date header value.
   */  
  private function _generateAuthSignature($method, $request_path, $additional_headers = null)
  {
    // Generate a GMT date
    $old_timezone = date_default_timezone_get();
    date_default_timezone_set("GMT");
    $date = str_replace("+0000","GMT", date("r"));
    date_default_timezone_set($old_timezone);
    
    // Generate the signing string
    $method = strtolower($method);
    $signing_string_parts = array();
    $signing_string_parts["host"] = "host: {$this->apiHost}";
    $signing_string_parts["date"] = "date: {$date}";
    $signing_string_parts["(request-target)"] = "(request-target): {$method} {$request_path}";
    if($additional_headers != null)
    {
      if(!is_array($additional_headers))
      {
        throw new \Exception("Additional signing headers variable was not an array!");
      }
      foreach($additional_headers as $k => $v)
      {
        // Signing string is all lowercase
        $k = strtolower($k);
        $v = strtolower($v);
        
        // If the value doesn't have the full "header: value" syntax, then prepend the header
        if(strpos($v,"{$k}:") === false)
        {
          $v = "{$k}: {$v}";
        }
        
        // Add header into the signing string parts
        $signing_string_parts[$k] = $v;
      }
    }
    $signing_string = implode("\n", $signing_string_parts);
    $arr_signing_headers = array_keys($signing_string_parts);

    // Generate signature with RSA-SHA256
    $raw_signature = null;
    if(function_exists("openssl_sigdn"))
    {
      // Sign with OpenSSL extension
      $raw_signature = $this->_sign_with_openssl($signing_string);
    }
    elseif(class_exists("\phpseclib3\Crypt\PublicKeyLoader"))
    {
      // Sign with phpseclib 3
      $raw_signature = $this->_sign_with_phpseclib3($signing_string);
    }
    elseif(class_exists("\phpseclib\Crypt\RSA"))
    {
      // Sign with phpseclib 2
      $raw_signature = $this->_sign_with_phpseclib2($signing_string);
    }
    elseif(class_exists("\Crypt_RSA"))
    {
      // Sign with phpseclib 1
      $raw_signature = $this->_sign_with_phpseclib1($signing_string);
    }
    else
    {
      // Fail if we cannot sign
      throw new \Exception("No RSA signature method available");
    }
     
    // Encode the signature and generate the Authorization header
    $signature = base64_encode($raw_signature);
    $auth_header_value = "Signature " .
      "version=\"1\"," .
      "keyId=\"{$this->config->tenancy}/{$this->config->user}/{$this->config->fingerprint}\"," .
      "algorithm=\"rsa-sha256\"," .
      "headers=\"" . implode(" ",$arr_signing_headers) . "\"," .
      "signature=\"{$signature}\"";
    
    return array("Date" => $date, "Authorization" => $auth_header_value);
  }

  /*
   * Return the PEM-formatted private key.
   */
  private function _get_private_key_pem()
  {
    // Load private key from file or from string
    if(strpos($this->config->key,"BEGIN RSA PRIVATE KEY"))
    {
      // OciConfig object holds the full key contents already 
      $private_key_pem = $this->config->key;
    }
    elseif(file_exists($this->config->key))
    {
      // OciConfig object holds the path to the key
      $private_key_pem = file_get_contents($this->config->key);
    }
    else
    {
      throw new \Exception("Could not load the PEM-formatted contents of the private key!");
    }
    
    return $private_key_pem;
  }

  /*
   * Sign a string of data using the OpenSSL extension
   */
  private function _sign_with_openssl($data)
  {
    if($data === null) { throw new \Exception("Cannot sign null data!"); }    
    $this->_debug("Signing " . strlen($data) . " bytes with OpenSSL");
    
    $signature = null;
    $private = openssl_pkey_get_private($this->_get_private_key_pem());
    if(!openssl_sign($data, $signature, $private, "sha256"))
    {
        throw new \Exception("OpenSSL could not sign the data");
    }
    return $signature;
  }
  
  /*
   * Sign a string of data using the phpseclib 3 library
   */
  private function _sign_with_phpseclib3($data)
  {
    if($data === null) { throw new \Exception("Cannot sign null data!"); }
    $this->_debug("Signing " . strlen($data) . " bytes with phpseclib 3");

    $private = phpseclib3\Crypt\PublicKeyLoader::load($this->_get_private_key_pem(), $password = false);
    $private = $private->withPadding(phpseclib3\Crypt\RSA::SIGNATURE_PKCS1);
    return $private->sign($data);
  }

  /*
   * Sign a string of data using the phpseclib 2 library
   */
  private function _sign_with_phpseclib2($data)
  {
    if($data === null) { throw new \Exception("Cannot sign null data!"); }
    $this->_debug("Signing " . strlen($data) . " bytes with phpseclib 2");

    $private = new \phpseclib\Crypt\RSA();
    $private->setHash("sha256");
    $private->loadKey($this->_get_private_key_pem());
    $private->setSignatureMode(\phpseclib\Crypt\RSA::SIGNATURE_PKCS1);
    return $private->sign($data);
  }

  /*
   * Sign a string of data using the phpseclib 1 library
   */
  private function _sign_with_phpseclib1($data)
  {
    if($data === null) { throw new \Exception("Cannot sign null data!"); }
    $this->_debug("Signing " . strlen($data) . " bytes with phpseclib 1");

    $private = new \Crypt_RSA();
    $private->setHash("sha256");
    $private->loadKey($this->_get_private_key_pem());
    $private->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
    return $private->sign($data);
  }
}
