<?php
declare(strict_types=1);

namespace OCA\JwtAuth\Helper;

class UrlGenerator {

	/**
	 * @var string
	 */
	private $autoLoginTriggerUri;

	/**
	 * @var string
	 */
	private $logoutConfirmationUri;

	public function __construct(string $autoLoginTriggerUri, string $logoutConfirmationUri) {
		$this->autoLoginTriggerUri = $autoLoginTriggerUri;
		$this->logoutConfirmationUri = $logoutConfirmationUri;
	}

	public function generateAutoLoginUrl(string $targetPath): string {
		return str_replace(
			'__TARGET_PATH__',
			urlencode($targetPath),
			$this->autoLoginTriggerUri
		);
	}

	public function generateLogoutConfirmationUrl(): string {
		return $this->logoutConfirmationUri;
	}

}
