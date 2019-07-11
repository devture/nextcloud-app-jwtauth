<?php
declare(strict_types=1);

namespace OCA\JwtAuth\Helper;

class LoginChain {

	/** @var \OC\Authentication\Login\PreLoginHookCommand */
	private $preLoginHookCommand;

	/** @var \OC\Authentication\Login\CompleteLoginCommand */
	private $completeLoginCommand;

	/** @var \OC\Authentication\Login\CreateSessionTokenCommand */
	private $createSessionTokenCommand;

	/** @var \OC\Authentication\Login\ClearLostPasswordTokensCommand */
	private $clearLostPasswordTokensCommand;

	/** @var \OC\Authentication\Login\UpdateLastPasswordConfirmCommand */
	private $updateLastPasswordConfirmCommand;

	/** @var \OC\Authentication\Login\FinishRememberedLoginCommand */
	private $finishRememberedLoginCommand;

	public function __construct(\OC\Authentication\Login\PreLoginHookCommand $preLoginHookCommand,
								\OC\Authentication\Login\CompleteLoginCommand $completeLoginCommand,
								\OC\Authentication\Login\CreateSessionTokenCommand $createSessionTokenCommand,
								\OC\Authentication\Login\ClearLostPasswordTokensCommand $clearLostPasswordTokensCommand,
								\OC\Authentication\Login\UpdateLastPasswordConfirmCommand $updateLastPasswordConfirmCommand,
								\OC\Authentication\Login\FinishRememberedLoginCommand $finishRememberedLoginCommand
	) {
		$this->preLoginHookCommand = $preLoginHookCommand;
		$this->completeLoginCommand = $completeLoginCommand;
		$this->createSessionTokenCommand = $createSessionTokenCommand;
		$this->clearLostPasswordTokensCommand = $clearLostPasswordTokensCommand;
		$this->updateLastPasswordConfirmCommand = $updateLastPasswordConfirmCommand;
		$this->finishRememberedLoginCommand = $finishRememberedLoginCommand;
	}

	public function process(\OC\Authentication\Login\LoginData $loginData): \OC\Authentication\Login\LoginResult {
		$chain = $this->preLoginHookCommand;
		$chain
			->setNext($this->completeLoginCommand)
			->setNext($this->createSessionTokenCommand)
			->setNext($this->clearLostPasswordTokensCommand)
			->setNext($this->updateLastPasswordConfirmCommand)
			->setNext($this->finishRememberedLoginCommand);

		return $chain->process($loginData);
	}

}
