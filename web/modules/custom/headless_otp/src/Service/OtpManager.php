<?php
// OtpManager.php - /web/modules/custom/headless_otp/src/Service/OtpManager.php

namespace Drupal\headless_otp\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Twilio\Rest\Client;
use Drupal\key\KeyRepositoryInterface;

class OtpManager {

  protected $logger;
  protected $keyRepository;

  public function __construct(LoggerChannelFactoryInterface $loggerFactory, KeyRepositoryInterface $keyRepository) {
    $this->logger = $loggerFactory->get('headless_otp');
    $this->keyRepository = $keyRepository;
  }

  /**
   * Requests Twilio to generate and send an OTP.
   */
  public function sendOtp($phoneNumber) {
    $sid = $this->getKeyValue('twilio_account_sid');
    $token = $this->getKeyValue('twilio_auth_token');
    $verifySid = $this->getKeyValue('twilio_verify_sid'); // VA... key
    $this->logger->error('Sid: ' . $sid . 'Token: ' . $token . 'VerifySid: ' . $verifySid);

    if (!$sid || !$token || !$verifySid) {
      $this->logger->error('Twilio Verify keys are missing.');
      return FALSE;
    }

    try {
      $client = new Client($sid, $token);
      // Twilio handles the generation and sending of the code
      $verification = $client->verify->v2->services($verifySid)
        ->verifications
        ->create($phoneNumber, "sms");

      $this->logger->info("Verify OTP sent to $phoneNumber. Status: " . $verification->status);
      return TRUE;
    } 
    catch (\Exception $e) {
      $this->logger->error("Twilio Verify Send Error: " . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Checks the code with Twilio's servers.
   */
  public function verifyOtp($phoneNumber, $inputCode) {
    $sid = $this->getKeyValue('twilio_account_sid');
    $token = $this->getKeyValue('twilio_auth_token');
    $verifySid = $this->getKeyValue('twilio_verify_sid');

    try {
      $client = new Client($sid, $token);
      $verification_check = $client->verify->v2->services($verifySid)
        ->verificationChecks
        ->create([
          'to' => $phoneNumber,
          'code' => $inputCode,
        ]);

      if ($verification_check->status === 'approved') {
        $this->logger->info("OTP successfully verified for $phoneNumber");
        return TRUE;
      }
      return FALSE;
    }
    catch (\Exception $e) {
      $this->logger->error("Twilio Verify Check Error: " . $e->getMessage());
      return FALSE;
    }
  }

  private function getKeyValue($keyId) {
    $key = $this->keyRepository->getKey($keyId);
    return $key ? $key->getKeyValue() : NULL;
  }
}