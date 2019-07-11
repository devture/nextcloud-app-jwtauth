<?php
declare(strict_types=1);

namespace OCA\JwtAuth\Helper;

/**
 * LoginPageInterceptor intercepts requests to the `/login` page and redirects them to an auto-login endpoint on the Identity Provider side.
 *
 * This acts as a middleware, but in a hacky way, so that it triggers for core routes (`/login`).
 * Regular application middlewares only work for routes belonging to the app itself, which is not good enough for us.
 */
class LoginPageInterceptor {

	/**
	 * @var UrlGenerator
	 */
	private $urlGenerator;

	/**
	 * @var \OC\User\Session
	 */
	private $session;

	public function __construct(
		UrlGenerator $urlGenerator,
		\OC\User\Session $session
	) {
		$this->urlGenerator = $urlGenerator;
		$this->session = $session;
	}

	public function intercept(): void {
		$requestUri = $_SERVER['REQUEST_URI'];

		if (strpos($requestUri, '/login') !== 0) {
			// Not a login page. We don't care.
			return;
		}

		// We may sometimes need to see the regular login page.
		// We can access it with a `forceStay` query parameter to skip this interceptor.
		if (isset($_GET['forceStay'])) {
			return;
		}

		if (isset($_GET['clear'])) {
			// After a `/logout`, users end up at `/login?clear=1`.
			//
			// What Nextcloud wants to do is clear stuff from the JS side using a script (on the login page).
			// To ensure it doesn't skip it, `ReloadExecutionMiddleware` checks some `clearingExecutionContexts`
			// session marker.
			//
			// If we let Nextcloud render the page at `/login?clear=1`, it'd clear things nicely.
			// But it would also send us to `/login` after that and that would trigger another auto-login sequence.
			// .. which we don't want.
			//
			// So we can't let the clearing page render. We need to clear things by ourselves.

			// Remove the marker to prevent `ReloadExecutionMiddleware` from kicking in later.
			$this->session->getSession()->remove('clearingExecutionContexts');

			// This is the actual clearing logic found in Nextcloud's `login.js`,
			// followed by a JS-based redirect to the Identity Provider's logged-out-confirmation page.
			$html = sprintf(
				'
				<script>
					try {
						window.localStorage.clear();
						window.sessionStorage.clear();
						console.debug("Browser storage cleared");
					} catch (e) {
						console.error("Could not clear browser storage", e);
					}

					window.location.href = %s;
				</script>
				',
				json_encode($this->urlGenerator->generateLogoutConfirmationUrl())
			);

			echo $html;
			exit;
		}

		// Finally, this is likely a genuine hit to the `/login` page.
		// We don't want the Nextcloud login page to be used at all,
		// so we forward such requests to the Identity Provider, triggering the auto-login sequence.

		// This represents the user wants to go after logging in. We pass it around.
		$targetPath = (isset($_GET['redirect_url']) ? $_GET['redirect_url'] : '/');

		$targetPathSanitized = \filter_var($targetPath, \FILTER_SANITIZE_URL);
		if ($targetPathSanitized === false) {
			$targetPath = '/';
		} else {
			$targetPath = $targetPathSanitized;
		}

		$this->redirectToUrlAndDie($this->urlGenerator->generateAutoLoginUrl($targetPath));
	}

	private function redirectToUrlAndDie(string $url): void {
		header('Location: ' . $url);
		echo sprintf('Redirecting you to: %s', $url);
		exit;
	}

}
