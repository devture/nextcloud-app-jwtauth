<?php
declare(strict_types=1);

namespace OCA\JwtAuth\Helper;

class JwtAuthTokenParser {

	/**
	 * @var string
	 */
	private $secret;

	public function __construct(string $secret) {
		$this->secret = $secret;
	}

	public function parseValidatedToken(string $token): ?string {
		$parser = \ReallySimpleJWT\Token::parser($token, $this->secret);

		try {
			$parsed = $parser->validate()
				->validateExpiration()
				->validateNotBefore()
				->parse();

			$payload = $parsed->getPayload();

			if (!array_key_exists('uid', $payload)) {
				return null;
			}

			return $payload['uid'];
		} catch (\ReallySimpleJWT\Exception\ValidateException $e) {
			return null;
		}
	}

}
