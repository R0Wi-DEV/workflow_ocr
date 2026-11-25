<?php

declare(strict_types=1);

/**
 * Minimal polyfill for Nextcloud's internal (non-OCP) OC\Notification\Notification,
 * loaded only by tests/bootstrap.standalone.php and only if nextcloud/ocp doesn't
 * already provide it (it doesn't -- OC\ is internal, not part of the OCP/NCU scope).
 * Not used by phpunit.xml/phpunit.integration.xml, where the real class from a full
 * checkout is always used instead.
 *
 * Only ever mocked via createMock(Notification::class) in
 * tests/Unit/Service/NotificationServiceTest.php -- never really instantiated under
 * the standalone suite (that would need the real class's validation logic, which this
 * polyfill deliberately does not attempt to reproduce; see
 * tests/Unit/Notification/NotifierTest.php's #[Group('nextcloud-full')], which does
 * instantiate it for real and stays excluded from this suite for that reason).
 * Implements the full OCP\Notification\INotification interface with trivial bodies
 * purely so the interface's return-type declarations are satisfiable -- method bodies
 * are never executed since every call is intercepted by the mock.
 */

namespace OC\Notification;

use OCP\Notification\IAction;
use OCP\Notification\INotification;

class Notification implements INotification {
	public function setApp(string $app): INotification {
		return $this;
	}

	public function getApp(): string {
		return '';
	}

	public function setUser(string $user): INotification {
		return $this;
	}

	public function getUser(): string {
		return '';
	}

	public function setDateTime(\DateTime $dateTime): INotification {
		return $this;
	}

	public function getDateTime(): \DateTime {
		return new \DateTime();
	}

	public function setObject(string $type, string $id): INotification {
		return $this;
	}

	public function getObjectType(): string {
		return '';
	}

	public function getObjectId(): string {
		return '';
	}

	public function setSubject(string $subject, array $parameters = []): INotification {
		return $this;
	}

	public function getSubject(): string {
		return '';
	}

	public function getSubjectParameters(): array {
		return [];
	}

	public function setParsedSubject(string $subject): INotification {
		return $this;
	}

	public function getParsedSubject(): string {
		return '';
	}

	public function setRichSubject(string $subject, array $parameters = []): INotification {
		return $this;
	}

	public function getRichSubject(): string {
		return '';
	}

	public function getRichSubjectParameters(): array {
		return [];
	}

	public function setMessage(string $message, array $parameters = []): INotification {
		return $this;
	}

	public function getMessage(): string {
		return '';
	}

	public function getMessageParameters(): array {
		return [];
	}

	public function setParsedMessage(string $message): INotification {
		return $this;
	}

	public function getParsedMessage(): string {
		return '';
	}

	public function setRichMessage(string $message, array $parameters = []): INotification {
		return $this;
	}

	public function getRichMessage(): string {
		return '';
	}

	public function getRichMessageParameters(): array {
		return [];
	}

	public function setLink(string $link): INotification {
		return $this;
	}

	public function getLink(): string {
		return '';
	}

	public function setIcon(string $icon): INotification {
		return $this;
	}

	public function getIcon(): string {
		return '';
	}

	public function setPriorityNotification(bool $priorityNotification): INotification {
		return $this;
	}

	public function isPriorityNotification(): bool {
		return false;
	}

	public function createAction(): IAction {
		throw new \RuntimeException('Polyfill stub -- not implemented.');
	}

	public function addAction(IAction $action): INotification {
		return $this;
	}

	public function getActions(): array {
		return [];
	}

	public function addParsedAction(IAction $action): INotification {
		return $this;
	}

	public function getParsedActions(): array {
		return [];
	}

	public function isValid(): bool {
		return true;
	}

	public function isValidParsed(): bool {
		return true;
	}
}
