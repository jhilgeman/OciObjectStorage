OCI Object Storage
==================

A library to simplify the use of the OCI Object Storage API.

Requirements
============

This library depends on PHP 5.3 or greater.

It requires ONE of any of the following:
 - OpenSSL extension
 - phpseclib v3.x
 - phpseclib v2.x
 - phpseclib v1.x

You need an OCI Object Storage account, with a user account that has sufficient privileges and has an API key that has been uploaded to the user's account. 

The information you need for the OciConfig object is:
 - The tenancy OCID
 - The user OCID
 - The public key fingerprint
 - The region for your Object Storage account 
 - The contents of a PEM-formatted private key or the path to the PEM-formatted private key file

If you need any help with generating a public/private key pair, obtaining the OCID values, or adding the key to the user's account, follow the instructions here:
https://docs.oracle.com/en-us/iaas/Content/API/Concepts/apisigningkey.htm

Instructions
============

1. Download the OciConfig.class.php and OciObjectStorage.class.php files.
2. Download the latest cacert.pem file from cURL (https://curl.se/ca/cacert.pem)
2. Create a script to use the libraries. See the example below. 

Example Script
==============

.. code-block:: php

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
    $ociConfig->tenancy = "ocid1.tenancy.oc1..aaaaaaaabcdefghijklmnopqrstuvwxyz01234567890abcdefghijklmnop";
    $ociConfig->region = "us-phoenix-1";
    $ociConfig->key = file_get_contents("C:/path/to/.oci/oci_api_key.pem");

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


TODO
========

- Add other API methods (e.g. upload)