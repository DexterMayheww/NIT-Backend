<?php
// OtpApiController.php - /web/modules/custom/headless_otp/src/Controller/OtpApiController.php

namespace Drupal\headless_otp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\headless_otp\Service\OtpManager;
use Drupal\user\Entity\User;

class OtpApiController extends ControllerBase {

  protected $otpManager;

  public function __construct(OtpManager $otpManager) {
    $this->otpManager = $otpManager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('headless_otp.manager')
    );
  }

  /**
   * POST /api/v1/auth/otp/send
   */
  public function send(Request $request) {
    $user = User::load($this->currentUser()->id());
    if (!$user || $user->isAnonymous()) {
      return new JsonResponse(['error' => 'Unauthorized'], 401);
    }

    $phone = $user->get('field_phone_number')->value;
    if (!$phone) {
      return new JsonResponse(['error' => 'User has no phone number'], 400);
    }

    if ($this->otpManager->sendOtp($phone)) {
      return new JsonResponse(['message' => 'OTP Sent']);
    }
    
    return new JsonResponse(['error' => 'Failed to send SMS. Ensure your number is verified in Twilio Console.'], 500);
  }

  /**
   * POST /api/v1/auth/otp/verify
   */
  public function verify(Request $request) {
    $content = json_decode($request->getContent(), TRUE);
    $code = $content['code'] ?? '';

    $user = User::load($this->currentUser()->id());
    $phone = $user->get('field_phone_number')->value;

    // Twilio Verify needs the phone number AND the code to validate
    if ($this->otpManager->verifyOtp($phone, $code)) {
        $user->set('field_is_phone_verified', TRUE); 
        $user->save();
        return new JsonResponse(['message' => 'Verified']);
    }

    return new JsonResponse(['error' => 'Invalid or Expired Code'], 400);
  }
}