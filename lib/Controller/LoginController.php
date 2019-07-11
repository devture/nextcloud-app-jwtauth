<?php
namespace OCA\JwtAuth\Controller;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Controller;

class LoginController extends Controller {

	/**
	 * @var \OCP\IConfig
	 */
	private $config;

	/**
	 * @var \OC\User\Manager
	 */
	private $userManager;

	/**
	 * @var \OC\User\Session
	 */
	private $session;

	/**
	 * @var \OCA\JwtAuth\Helper\LoginChain
	 */
	private $loginChain;

	/**
	 * @var \OCA\JwtAuth\Helper\JwtAuthTokenParser
	 */
	private $jwtAuthTokenParser;

	public function __construct(
		$AppName,
		\OCP\IRequest $request,
		\OCP\IConfig $config,
		\OC\User\Session $session,
		\OC\User\Manager $userManager,
		\OCA\JwtAuth\Helper\LoginChain $loginChain,
		\OCA\JwtAuth\Helper\JwtAuthTokenParser $jwtAuthTokenParser
	) {
		parent::__construct($AppName, $request);

		$this->config = $config;
		$this->session = $session;
		$this->userManager = $userManager;
		$this->loginChain = $loginChain;
		$this->jwtAuthTokenParser = $jwtAuthTokenParser;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function auth(string $token, string $targetPath) {
		$username = $this->jwtAuthTokenParser->parseValidatedToken($token);
		if ($username === null) {
			// It could be that the JWT token has expired.
			// Redirect to the homepage, which likely redirects to /login
			// and starts the whole flow over again.
			//
			// Hopefully we have better luck next time.
			return new RedirectResponse('/');
		}

		$redirectUrl = '/';
		$targetPathParsed = parse_url($targetPath);
		if ($targetPathParsed !== false) {
			$redirectUrl = $targetPathParsed['path'];
		}

		$user = $this->userManager->get($username);

		if ($user === null) {
			// This could be made friendlier.
			die('Tried to log in with a user which does not exist.');
		}

		if ($this->session->getUser() === $user) {
			// Already logged in. No need to log in once again.
			return new RedirectResponse($redirectUrl);
		}

		if ($this->session->getUser() !== null) {
			// If there is an old session, it would cause our login attempt to not work.
			// We'd be setting some session cookies, but other old ones would remain
			// and the old session would be in use.
			//
			// We work around this by destroying the old session before proceeding.
			$this->session->logout();
		}

		$loginData = new \OC\Authentication\Login\LoginData(
			$this->request,
			$username,
			// Password. It doesn't matter because our custom Login chain
			// doesn't validate it at all.
			'',
			$redirectUrl,
			'', // Timezone
			'', // Timezone offset
		);

		// Prepopulate the login request with the user we're logging in.
		// This usually happens in one of the steps of the default LoginChain.
		// For our custom login chain, we pre-populate it.
		$loginData->setUser($user);

		// This is expected to log the user in, updating the session, etc.
		$result = $this->loginChain->process($loginData);
		if (!$result->isSuccess()) {
			// We don't expect any failures, but who knows..
			die('Internal login failure');
		}

		return new RedirectResponse($redirectUrl);
	}

}
