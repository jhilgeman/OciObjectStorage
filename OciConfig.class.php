<?php
/* OciConfig
 * @author: Jonathan Hilgeman
 * @version: 1.0
 * @created: 2024-10-26
 * @updated: 2024-10-26
 * @description: Class to hold configuration data
 * Mirrors the structure of the config file suggested after adding a public key to the OCI user profile
 */
class OciConfig
{
  public $user;
  public $fingerprint;
  public $tenancy;
  public $region;
  public $key;
}