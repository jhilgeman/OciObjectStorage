<?php

// In the event the openssl extension is not available, the phpseclib libraries may be used. Uncomment and adjust the path.

// Load phpseclib 3
// require("../../phpseclib3/vendor/autoload.php");

// Load phpseclib 2
// require("../../phpseclib2/vendor/autoload.php");

// Load phpseclib 1
// require("../../phpseclib1/Crypt/RSA.php");
// require("../../phpseclib1/Math/BigInteger.php");

// Load the libraries
require("OciConfig.class.php");
require("OciObjectStorage.class.php");

// Parameters
$namespace = "axyjk2b1csk7";
$bucket = "bucket-20241025-2159";
$filename = "objprefixFirefox Installer.exe";

// OCI Config
$ociConfig = new OciConfig();
$ociConfig->user = "ocid1.user.oc1..aaaaaaaaki7xzfznghz5cpxhhec3eey24kcudyknuiah2lzo3w5jwyovgrna";
$ociConfig->fingerprint = "18:e5:25:ec:dd:ed:58:69:56:c0:4e:26:ae:cc:b6:4d";
$ociConfig->tenancy = "ocid1.tenancy.oc1..aaaaaaaajdxx6quyv46x2abak2oqbb3bj44ocu74adpk35h7vdocxb2uvgca";
$ociConfig->region = "us-phoenix-1";
$ociConfig->key = file_get_contents("C:/Users/conta/.oci/oci_api_key.pem");

// Run the download
try
{
  $ociObjectStorage = new OciObjectStorage($ociConfig);

  // Note: Download the latest cacert.pem from the official cURL site: https://curl.se/ca/cacert.pem
  $ociObjectStorage->caInfo = __DIR__ . DIRECTORY_SEPARATOR . "cacert.pem";

  // Enable debugging when troubleshooting
  // $ociObjectStorage->debug = true;

  $data = $ociObjectStorage->DownloadFile($namespace, $bucket, $filename);
  echo "Downloaded " . strlen($data) . " bytes.";
}
catch(\Exception $ex)
{
  echo "EXCEPTION: " . $ex->getMessage();
}
