# Nextcloud JWT Auth app

A [Nextcloud](https://nextcloud.com/) (v17+) application which lets you auto-login users ([single-sign-on](https://en.wikipedia.org/wiki/Single_sign-on)) without them having to go through the Nextlcoud login page.

To make use of this app, you need another system which generates temporary [JWT](https://jwt.io/) tokens, serving as a login identifier.
The JWT Auth Nextcloud application securely processes these tokens and transparently logs the user into Nextcloud.

**Note**: Nextcloud v17+ is required.


## Flow

1. A user visits any Nextcloud page which requires authentication

2. If the user is not logged in, Nextcloud redirects the user to Nextcloud's login page (`/login`)

3. The JWT Auth app intercepts this request and forwards the user to your other system ([Identity Provider](#identity-provider-requirements)'s **auto-login-trigger endpoint**)

4. If not already logged in, the user follows steps which log them into your other (Identity Provider) system

5. Your other (Identity Provider) system redirects the user to a special Nextcloud URL (one belonging to the JWT Auth app). The URL contains login information in the form of a [JWT](https://jwt.io/) token

6. The JWT Auth app validates the JWT token, and if trusted, transparently (without user action) logs the user into Nextcloud

7. The JWT Auth app redirects the user to the original page that the user tried to access (the one from step 1 above)


## Prerequisites for using

- users that you'd be logging in need to exist in Nextcloud. Whether you create them manually beforehand or you create them from another system using Nextcloud's [User Provisioning API](https://docs.nextcloud.com/server/16/admin_manual/configuration_user/instruction_set_for_users.html) is up to you.

- another system which would assist in handling login requests for users. Let's call it an **Identity Provider**.


## Identity Provider requirements

What we call an Identity Provider is another web system of yours, which needs to serve 2 endpoints:

- an **auto-login-trigger endpoint** (e.g. `https://your-other-system/nextcloud/auto-login?targetPath=/apps/files/`) -- the user gets sent here whenever she's not logged into Nextcloud and a new login session needs to be started. This endpoint is responsible for authenticating the user on your other system first and then redirecting the user to the JWT Auth app (e.g. `https://your-nextcloud/apps/jwtauth/?token=JWT_TOKEN_HER&next=/apps/files/`). See [an example implementation below](#example-auto-login-trigger-endpoint).

- a **logged out of Nextcloud confirmation page** (e.g. `https://your-other-system/nextcloud/logged-out`) -- the user gets sent here whenever she logs out of Nextcloud from Nextcloud's User Interface. Redirecting the user to the Nextcloud home page (or login page) would mean another auto-login flow would get triggered. This goes against the user's intention to log out. We prevent such an auto-login and instead redirect the user to your other system, where you can serve some confirmation page (e.g. "You've logged out of Nextcloud, but are still logged into the main system. Going to Nextcloud again would automatically log you in.")


### Example auto-login-trigger endpoint

Here's a [Symfony](https://symfony.com/) controller which demonstrates what your Identity Provider auto-login-trigger endpoint might be like:

```php
<?php
namespace App\NextcloudBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class NextcloudAutoLoginController extends AbstractController {

	/**
	 * @Route("/nextcloud/auto-login", name="nextcloud.auto_login", methods={"GET"})
	 */
	public function autoLogin(
		Request $request,
		\App\NextcloudBundle\Helper\JwtAuthTokenGenerator $jwtAuthTokenGenerator,
	): RedirectResponse {
		// This is the relative Nextcloud URL that the user wants to go to.
		$targetPath = (string) $request->query->get('targetPath', '/');

		$targetPathSanitized = \filter_var($targetPath, \FILTER_SANITIZE_URL);
		if ($targetPathSanitized === false) {
			$targetPath = '/';
		} else {
			$targetPath = $targetPathSanitized;
		}

		/** @var \App\UserBundle\Entity\User|null $user */
		$user = $this->getUser();

		if ($user === null) {
			// User not logged into our system.
			// Let's start a login flow, which would lead us back here.
			return $this->redirectToRoute('user.login', [
				'next' => $this->generateUrl('nextcloud.auto_login', [
					'targetPath' => $targetPath,
				]),
			]);
		}

		$userId = $user->getNextcloudUserId();

		// This controlls how long the JWT token would be valid.
		// Both your systems' clocks need to be in sync with a deviation of not more
		// than the specified number of seconds.
		$leewaySeconds = 10;

		$token = $jwtAuthTokenGenerator->generateTokenForUserId($userId, $leewaySeconds);

		$redirectUrl = sprintf(
			'https://your-nextcloud/apps/jwtauth/?token=%s&targetPath=%s',
			urlencode($token),
			urlencode($targetPath)
		);

		return $this->redirect($redirectUrl);
	}

}
```

You'd need the following `JwtAuthTokenGenerator` helper class to generate the tokens:

```php
<?php
namespace App\NextcloudBundle\Helper;

class JwtAuthTokenGenerator {

	/**
	 * @var string
	 */
	private $sharedSecret;

	/**
	 * @var string
	 */
	private $issuer;

	public function __construct(string $sharedSecret, string $issuer) {
		$this->sharedSecret = $sharedSecret;
		$this->issuer = $issuer;
	}

	public function generateTokenForUserId(string $userId, int $leewaySeconds): string {
		$timeNow = time();

		$payload = [
			'uid' => $userId,

			'iat' => $timeNow,
			'nbf' => $timeNow - $leewaySeconds,
			'exp' => $timeNow + $leewaySeconds,

			'iss' => $this->issuer,
		];

		return \ReallySimpleJWT\Token::customPayload($payload, $this->sharedSecret);
	}

}
```

The `$issuer` value currently doesn't matter.

`$sharedSecret` must be strong -- at least 12 characters and containing at least one special character (`*&!@%^#$`).
It must match the `SharedSecret` configuration value specified for the app (see below).

If you are generating the JWT tokens in another way (not using [PHP](https://php.net/) or not using the [rbdwllr/reallysimplejwt](https://packagist.org/packages/rbdwllr/reallysimplejwt) library), do note that expiration time claims (`nbf`, `exp`) are required. Without them, JWT token validation would fail.


## Application configuration values

The JWT Auth Nextcloud application requires the following configuration values:

- `AutoLoginTriggerUri` - a full URL pointing to your Identity Provider's **auto-login-trigger endpoint** (e.g. `https://your-other-system/nextcloud/auto-login?targetPath=__TARGET_PATH__`). The `__TARGET_PATH__` placeholder gets replaced with the relative path of the Nextcloud resource the user is trying to visit. (e.g. `https://your-other-system/nextcloud/auto-login?targetPath=/apps/files/`)

- `LogoutConfirmationUri` - a full URL pointing to your Identity Provider's **logged out of Nextcloud confirmation page** (e.g. `https://your-other-system/nextcloud/logged-out`)

- `SharedSecret` - a secret string (at least 12 characters and containing at least one special character (`*&!@%^#$`)) used to sign the JWT tokens. This secret must be shared between the JWT Auth app in Nextcloud and your Identity Provider system. That is, both systems need to use the same secret value or JWT tokens would not be considered valid.

Learn more in [Installation](#installation).


## Installation

This JWT Auth Nextcloud application is not available on the Nextcloud [App Store](https://apps.nextcloud.com/) yet, so you **need to install it manually**.

To install it, place its files in a `apps/jwtauth` directory.
Example: `git clone git@github.com:devture/nextcloud-app-jwtauth apps/jwtauth`.

Then install the app's dependencies using [composer](https://getcomposer.org/): `cd apps/jwtauth; make composer; cd ..`

After that, specify the required [Application configuration values](#application-configuration-values). Example:

```bash
./occ config:system:set jwtauth AutoLoginTriggerUri --value="https://your-other-system/nextcloud/auto-login?targetPath=__TARGET_PATH__"

./occ config:system:set jwtauth LogoutConfirmationUri --value="https://your-other-system/nextcloud/nextcloud/logged-out"

./occ config:system:set jwtauth SharedSecret --value="jJJ@wPHNNnLVLd!@__wkqLFbLd9tT!VXjkC973xMR!7cjvz4WfFgWRstH"
```

Finally, enable the app: `./occ app:enable jwtauth`.

From that point on, the Nextcloud `/login` page will be unavailable.
(A way to get to it is to access it using `/login?forceStay=1`.)

All other requests to the `/login` page would be automatically captured and directed to your Identity Provider system (e.g. `https://your-other-system/nextcloud/auto-login`), before being brought back to Nextcloud.
