<?php

declare(strict_types=1);

namespace PHPThis\Session;

use Closure;
use InvalidArgumentException;
use LogicException;
use PHPThis\Http\Request;
use PHPThis\Http\Response;
use PHPThis\Http\ResponseCookie;
use RuntimeException;
use Throwable;

final class SessionLifecycle
{
    private const string OBSOLETE_KEY = '__phpthis_obsolete_at';

    private bool $begun = false;
    private ?string $cookieHeader = null;
    private bool $incomingIdResolved = false;
    private bool $incomingCookieRejected = false;
    private bool $incomingStateRejected = false;
    private bool $incomingObsolete = false;
    private bool $invalidated = false;
    private ?string $incomingId = null;
    private ?string $unissuedId = null;
    private ?ResponseCookie $pendingCookie = null;

    public function __construct(private readonly SessionConfiguration $configuration)
    {
    }

    public function begin(Request $request): void
    {
        if ($this->begun) {
            throw new LogicException('Session lifecycle is already handling a request.');
        }

        if (session_status() !== PHP_SESSION_NONE) {
            throw new RuntimeException('PHP session state must be inactive at the request boundary.');
        }

        $this->begun = true;
        $this->cookieHeader = $request->headers['cookie'] ?? null;
        $this->incomingIdResolved = false;
        $this->incomingCookieRejected = false;
        $this->incomingStateRejected = false;
        $this->incomingObsolete = false;
        $this->invalidated = false;
        $this->incomingId = null;
        $this->unissuedId = null;
        $this->pendingCookie = null;
    }

    public function read(): SessionSnapshot
    {
        $this->requireOperationAllowed();
        $incomingId = $this->resolveIncomingId();

        if ($incomingId === null) {
            return new SessionSnapshot([]);
        }

        $this->start($incomingId, true);
        $actualId = $this->currentId();
        $obsolete = array_key_exists(self::OBSOLETE_KEY, $_SESSION);
        $state = $this->snapshotFromNativeState();
        $this->clearNativeState();

        if ($actualId !== $incomingId) {
            $this->discardUnissuedSession($actualId);
            $this->incomingId = null;
            $this->incomingStateRejected = true;
            return new SessionSnapshot([]);
        }

        if (!$state['accepted']) {
            $this->incomingId = null;
            $this->incomingStateRejected = true;
            $this->incomingObsolete = $obsolete;
            return new SessionSnapshot([]);
        }

        return $state['snapshot'];
    }

    /** @param Closure(SessionSnapshot): SessionSnapshot $change */
    public function update(Closure $change): void
    {
        $this->requireOperationAllowed();
        $opened = $this->openWritableState();
        $createdId = $opened['incoming_id'] === null ? $opened['actual_id'] : null;

        try {
            $updated = $change($opened['snapshot']);

            $_SESSION = $updated->values;

            if (!session_write_close()) {
                throw new RuntimeException('Unable to commit native session state.');
            }

            $this->clearNativeState();
            $this->incomingId = $opened['actual_id'];
            $this->incomingIdResolved = true;

            if ($createdId !== null) {
                $this->unissuedId = $createdId;
                $this->pendingCookie = $this->configuration->liveCookie($opened['actual_id']);
            }
        } catch (Throwable $failure) {
            $this->abortActiveNativeSession();
            $this->discardUnissuedSession($createdId);
            throw $failure;
        }
    }

    /** @param Closure(SessionSnapshot): SessionSnapshot $change */
    public function regenerateAndUpdate(Closure $change): void
    {
        $this->requireOperationAllowed();
        $opened = $this->openWritableState(true);
        $createdId = $opened['incoming_id'] === null ? $opened['actual_id'] : null;
        $supersededId = $this->unissuedId ?? $createdId;
        $regenerationStarted = false;
        $newId = null;

        try {
            $updated = $change($opened['snapshot']);
            $_SESSION = $opened['snapshot']->values;
            $_SESSION[self::OBSOLETE_KEY] = time();
            $previousId = $this->currentId();
            $regenerationStarted = true;

            if (!session_regenerate_id(false)) {
                throw new RuntimeException('Unable to regenerate the native session identifier.');
            }

            $newId = $this->currentId();

            if ($newId === $previousId) {
                throw new RuntimeException('Native session regeneration did not change the identifier.');
            }

            unset($_SESSION[self::OBSOLETE_KEY]);
            $_SESSION = $updated->values;

            if (!session_write_close()) {
                throw new RuntimeException('Unable to commit regenerated session state.');
            }

            $this->clearNativeState();
            $this->discardUnissuedSession($supersededId);
            $this->incomingId = $newId;
            $this->incomingIdResolved = true;
            $this->unissuedId = $newId;
            $this->pendingCookie = $this->configuration->liveCookie($newId);
        } catch (Throwable $failure) {
            $this->abortActiveNativeSession();
            $failureId = $newId ?? $createdId;
            $this->discardUnissuedSession($failureId);

            if ($regenerationStarted && $supersededId !== $failureId) {
                $this->discardUnissuedSession($supersededId);
            }

            if ($regenerationStarted) {
                $this->incomingId = null;
                $this->incomingIdResolved = true;
                $this->unissuedId = null;
                $this->pendingCookie = null;
            }

            throw $failure;
        }
    }

    public function invalidate(): void
    {
        $this->requireOperationAllowed();
        $this->invalidated = true;
        $incomingId = $this->resolveIncomingId();
        $this->incomingId = null;
        $this->incomingIdResolved = true;

        if ($incomingId === null) {
            $this->pendingCookie = $this->incomingObsolete ? null : $this->configuration->expiredCookie();
            return;
        }

        $this->start($incomingId, false);
        $actualId = $this->currentId();

        if ($actualId !== $incomingId) {
            $this->abortActiveNativeSession();
            $this->discardUnissuedSession($actualId);
            $this->pendingCookie = $this->configuration->expiredCookie();
            return;
        }

        $state = $this->snapshotFromNativeState();

        if (!$state['accepted']) {
            $obsolete = array_key_exists(self::OBSOLETE_KEY, $_SESSION);
            $this->abortActiveNativeSession();
            $this->pendingCookie = $obsolete ? null : $this->configuration->expiredCookie();
            return;
        }

        $_SESSION = [self::OBSOLETE_KEY => time()];

        if (!session_write_close()) {
            $this->abortActiveNativeSession();
            throw new RuntimeException('Unable to invalidate native session state.');
        }

        $this->clearNativeState();
        $this->discardUnissuedSession($this->unissuedId);
        $this->unissuedId = null;
        $this->pendingCookie = $this->configuration->expiredCookie();
    }

    public function finish(Response $response): Response
    {
        $this->requireBegun();

        try {
            if (session_status() !== PHP_SESSION_NONE) {
                throw new RuntimeException('Native session lock remained active after request handling.');
            }

            if ($this->pendingCookie === null) {
                return $response;
            }

            return new Response(
                $response->status,
                $response->headers,
                $response->body,
                [...$response->cookies, $this->pendingCookie],
            );
        } catch (Throwable $failure) {
            $this->abortActiveNativeSession();
            $this->discardUnissuedSession($this->unissuedId);
            throw $failure;
        } finally {
            $this->resetRequestState();
        }
    }

    public function abort(): void
    {
        $this->abortActiveNativeSession();
        $this->discardUnissuedSession($this->unissuedId);
        $this->resetRequestState();
    }

    /** @return array{snapshot: SessionSnapshot, incoming_id: ?string, actual_id: string} */
    private function openWritableState(bool $replaceRejected = false): array
    {
        $incomingId = $this->resolveIncomingId();
        $previouslyRejected = $this->incomingCookieRejected || $this->incomingStateRejected;

        if ($previouslyRejected && !$replaceRejected) {
            throw new SessionUnavailable('Session state is no longer current.');
        }

        $this->start($previouslyRejected ? null : $incomingId, false);
        $actualId = $this->currentId();
        $state = $this->snapshotFromNativeState();

        $rejected = (
            $previouslyRejected
            || ($incomingId !== null && $actualId !== $incomingId)
            || !$state['accepted']
        );

        if ($rejected && $replaceRejected) {
            if ($actualId === $incomingId || !$state['accepted']) {
                $this->abortActiveNativeSession();
                $this->discardUnissuedSession($actualId !== $incomingId ? $actualId : null);
                $this->start(null, false);
                $actualId = $this->currentId();
            }

            return [
                'snapshot' => new SessionSnapshot([]),
                'incoming_id' => null,
                'actual_id' => $actualId,
            ];
        }

        if ($rejected) {
            $this->abortActiveNativeSession();
            $this->discardUnissuedSession($actualId !== $incomingId ? $actualId : null);
            throw new SessionUnavailable('Session state is no longer current.');
        }

        return [
            'snapshot' => $state['snapshot'],
            'incoming_id' => $incomingId,
            'actual_id' => $actualId,
        ];
    }

    private function start(?string $id, bool $readAndClose): void
    {
        $this->assertRuntimeConfiguration();

        if (session_name($this->configuration->internalName) === false) {
            throw new RuntimeException('Unable to configure the native session name.');
        }

        if (session_cache_limiter('') === false) {
            throw new RuntimeException('Unable to disable native session cache headers.');
        }

        if (session_id($id ?? '') === false) {
            throw new RuntimeException('Unable to configure the native session identifier.');
        }

        $options = [
            'cache_limiter' => '',
            'use_cookies' => false,
            'use_strict_mode' => true,
        ];

        if ($readAndClose) {
            $options['read_and_close'] = true;
        }

        if (!session_start($options)) {
            $this->clearNativeState();
            throw new RuntimeException('Unable to start native session storage.');
        }

        $expectedStatus = $readAndClose ? PHP_SESSION_NONE : PHP_SESSION_ACTIVE;

        if (session_status() !== $expectedStatus) {
            $this->abortActiveNativeSession();
            throw new RuntimeException('Native session storage entered an unexpected state.');
        }
    }

    private function assertRuntimeConfiguration(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            throw new RuntimeException('Native session storage is already active.');
        }

        $settings = [
            'session.auto_start' => '0',
            'session.use_only_cookies' => '1',
            'session.use_trans_sid' => '0',
            'session.sid_length' => '32',
            'session.sid_bits_per_character' => '4',
            'session.save_path' => $this->configuration->nativeSavePath,
        ];

        foreach ($settings as $name => $expected) {
            if (ini_get($name) !== $expected) {
                throw new RuntimeException("Native session setting {$name} does not match PHPThis policy.");
            }
        }

        if (session_module_name() !== 'files') {
            throw new RuntimeException('PHPThis session certification currently requires native file storage.');
        }
    }

    /** @return array{snapshot: SessionSnapshot, accepted: bool} */
    private function snapshotFromNativeState(): array
    {
        $values = $_SESSION;

        if (array_key_exists(self::OBSOLETE_KEY, $values)) {
            return ['snapshot' => new SessionSnapshot([]), 'accepted' => false];
        }

        try {
            return ['snapshot' => new SessionSnapshot($values), 'accepted' => true];
        } catch (InvalidArgumentException) {
            return ['snapshot' => new SessionSnapshot([]), 'accepted' => false];
        }
    }

    private function resolveIncomingId(): ?string
    {
        if ($this->incomingIdResolved) {
            return $this->incomingId;
        }

        $this->incomingIdResolved = true;

        if ($this->cookieHeader === null || $this->cookieHeader === '') {
            return null;
        }

        $found = null;

        foreach (explode(';', $this->cookieHeader) as $pair) {
            $pair = trim($pair);
            $separator = strpos($pair, '=');

            if ($separator === false) {
                continue;
            }

            $name = substr($pair, 0, $separator);

            if ($name !== $this->configuration->cookieName) {
                continue;
            }

            $value = substr($pair, $separator + 1);

            if ($found !== null || preg_match('/^[a-f0-9]{32}$/D', $value) !== 1) {
                $this->incomingCookieRejected = true;
                return null;
            }

            $found = $value;
        }

        $this->incomingId = $found;
        return $found;
    }

    private function currentId(): string
    {
        $id = session_id();

        if (!is_string($id) || preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
            throw new RuntimeException('Native session storage returned an invalid identifier.');
        }

        return $id;
    }

    private function clearNativeState(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_NONE && session_id('') === false) {
            throw new RuntimeException('Unable to clear native session request state.');
        }
    }

    private function abortActiveNativeSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !session_abort()) {
            throw new RuntimeException('Unable to abort native session state.');
        }

        if (session_status() === PHP_SESSION_NONE) {
            $this->clearNativeState();
        }
    }

    private function discardUnissuedSession(?string $id): void
    {
        if ($id === null) {
            return;
        }

        $this->start($id, false);
        $_SESSION = [];

        if (!session_destroy()) {
            $this->abortActiveNativeSession();
            throw new RuntimeException('Unable to discard an unissued native session.');
        }

        $this->clearNativeState();
    }

    private function requireBegun(): void
    {
        if (!$this->begun) {
            throw new LogicException('Session lifecycle must begin before use.');
        }
    }

    private function requireOperationAllowed(): void
    {
        $this->requireBegun();

        if ($this->invalidated) {
            throw new LogicException('Session invalidation is terminal for the current request.');
        }
    }

    private function resetRequestState(): void
    {
        $this->begun = false;
        $this->cookieHeader = null;
        $this->incomingIdResolved = false;
        $this->incomingCookieRejected = false;
        $this->incomingStateRejected = false;
        $this->incomingObsolete = false;
        $this->invalidated = false;
        $this->incomingId = null;
        $this->unissuedId = null;
        $this->pendingCookie = null;
    }
}
