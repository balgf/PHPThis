<?php

declare(strict_types=1);

use PHPThis\Http\CookieSameSite;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Http\ResponseCookie;
use PHPThis\Session\SessionConfiguration;
use PHPThis\Session\SessionLifecycle;
use PHPThis\Session\SessionSnapshot;
use PHPThis\Session\SessionUnavailable;

require dirname(__DIR__) . '/autoload.php';

$directory = dirname(__DIR__) . '/tmp/session-tests/' . bin2hex(random_bytes(12));

if (!mkdir($directory, 0700, true)) {
    throw new RuntimeException('Unable to create isolated session storage.');
}

try {
    if (ini_set('session.save_path', $directory) === false) {
        throw new RuntimeException('Unable to configure isolated session storage.');
    }

    $configuration = new SessionConfiguration(
        'PHPTHISSESSION',
        '__Host-PHPThisSession',
        true,
        CookieSameSite::Lax,
        $directory,
    );
    $sessions = new SessionLifecycle($configuration);
    $emptyResponse = new Response(204, [], '');

    $handler = new class implements RequestHandler {
        public function handle(Request $request): Response
        {
            return new Response(204, [], '');
        }
    };
    $boundary = new RequestBoundary(
        new RequestReader(1, 'php://memory'),
        $handler,
        new ErrorResponseRegistry([]),
        $sessions,
    );
    $stateless = $boundary->handle(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/public'], []);
    requireSessionTest(
        $stateless->cookies === [] && sessionFiles($directory) === [],
        'A configured lifecycle must remain inactive when the handler does not use sessions.',
    );

    $sessions->begin(new Request('GET', '/anonymous'));
    $anonymous = $sessions->read();
    $anonymousResponse = $sessions->finish($emptyResponse);
    requireSessionTest(
        $anonymous->values === []
        && $anonymousResponse->cookies === []
        && sessionFiles($directory) === [],
        'Anonymous read must not create session storage or a cookie.',
    );

    $sessions->begin(new Request(
        'GET',
        '/invalid',
        headers: ['cookie' => '__Host-PHPThisSession=attacker-controlled; unrelated=value'],
    ));
    $invalid = $sessions->read();
    $invalidResponse = $sessions->finish($emptyResponse);
    requireSessionTest(
        $invalid->values === []
        && $invalidResponse->cookies === []
        && sessionFiles($directory) === [],
        'Malformed session identifiers must fail closed without creating storage or a racing cookie.',
    );

    $sessions->begin(new Request(
        'GET',
        '/duplicate',
        headers: ['cookie' => $configuration->cookieName . '=' . str_repeat('b', 32)
            . '; ' . $configuration->cookieName . '=' . str_repeat('c', 32)],
    ));
    $duplicate = $sessions->read();
    $duplicateResponse = $sessions->finish($emptyResponse);
    requireSessionTest(
        $duplicate->values === [] && $duplicateResponse->cookies === [] && sessionFiles($directory) === [],
        'Duplicate session cookies must fail closed without storage or Set-Cookie.',
    );

    $nonexistentId = str_repeat('a', 32);
    $sessions->begin(requestWithSessionId($configuration, $nonexistentId, '/nonexistent'));
    $nonexistent = $sessions->read();
    $nonexistentResponse = $sessions->finish($emptyResponse);
    requireSessionTest(
        $nonexistent->values === []
        && $nonexistentResponse->cookies === []
        && sessionFiles($directory) === [],
        'Strict mode must reject an attacker-selected nonexistent identifier without racing cookie deletion.',
    );

    $sessions->begin(new Request('POST', '/mismatched-save-path'));
    requireSessionTest(
        ini_set('session.save_path', dirname($directory)) !== false,
        'The save-path mismatch control must change the effective native path.',
    );

    try {
        $sessions->update(static fn(SessionSnapshot $current): SessionSnapshot => $current);
        throw new RuntimeException('Mismatched native session save path unexpectedly passed.');
    } catch (RuntimeException $failure) {
        requireSessionTest(
            str_contains($failure->getMessage(), 'session.save_path'),
            'The lifecycle must reject a native save path other than the configured isolated path.',
        );
    } finally {
        ini_set('session.save_path', $directory);
        $sessions->abort();
    }

    $sessions->begin(new Request('POST', '/failed-anonymous-write'));

    try {
        $sessions->update(static function (SessionSnapshot $current): SessionSnapshot {
            throw new DomainException('deliberate anonymous callback failure');
        });
        throw new RuntimeException('Anonymous session update callback unexpectedly succeeded.');
    } catch (DomainException) {
        $sessions->abort();
    }

    requireSessionTest(
        sessionFiles($directory) === [],
        'Failed anonymous update must discard its unissued session storage.',
    );

    $postCommitFailure = new DomainException('deliberate post-session failure');
    $postCommitHandler = new class ($sessions, $postCommitFailure) implements RequestHandler {
        public function __construct(
            private readonly SessionLifecycle $sessions,
            private readonly DomainException $failure,
        ) {
        }

        public function handle(Request $request): Response
        {
            $this->sessions->update(
                static fn(SessionSnapshot $current): SessionSnapshot => new SessionSnapshot(['cart_id' => 'unissued']),
            );
            throw $this->failure;
        }
    };
    $postCommitBoundary = new RequestBoundary(
        new RequestReader(1, 'php://memory'),
        $postCommitHandler,
        new ErrorResponseRegistry([]),
        $sessions,
    );

    try {
        $postCommitBoundary->handle(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/fail'], []);
        throw new RuntimeException('Post-session handler failure unexpectedly succeeded.');
    } catch (DomainException $failure) {
        requireSessionTest($failure === $postCommitFailure, 'Unknown handler failure must escape unchanged.');
    }

    requireSessionTest(
        sessionFiles($directory) === [],
        'Unknown failure must destroy committed state whose cookie was never issued.',
    );

    $finishFailureHandler = new class ($sessions, $configuration) implements RequestHandler {
        public function __construct(
            private readonly SessionLifecycle $sessions,
            private readonly SessionConfiguration $configuration,
        ) {
        }

        public function handle(Request $request): Response
        {
            $this->sessions->update(
                static fn(SessionSnapshot $current): SessionSnapshot => new SessionSnapshot(['cart_id' => 'conflict']),
            );
            return new Response(204, [], '', [new ResponseCookie(
                $this->configuration->cookieName,
                'manual',
                '/',
                true,
                true,
                CookieSameSite::Lax,
            )]);
        }
    };
    $finishFailureBoundary = new RequestBoundary(
        new RequestReader(1, 'php://memory'),
        $finishFailureHandler,
        new ErrorResponseRegistry([InvalidArgumentException::class => new Response(400, [], 'mapped')]),
        $sessions,
    );

    try {
        $finishFailureBoundary->handle(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/cookie-conflict'], []);
        throw new RuntimeException('Session finalization conflict unexpectedly succeeded.');
    } catch (InvalidArgumentException) {
    }

    requireSessionTest(
        sessionFiles($directory) === [],
        'Finalization failure must escape once and destroy its never-issued session.',
    );

    $cartSession = new TestCartSession($sessions);
    $authenticationSession = new TestAuthenticationSession($sessions);
    $cartHandler = new class ($cartSession) implements RequestHandler {
        public function __construct(private readonly TestCartSession $session)
        {
        }

        public function handle(Request $request): Response
        {
            $this->session->establishCart('cart-7');
            return new Response(204, [], '');
        }
    };
    $cartBoundary = new RequestBoundary(
        new RequestReader(1, 'php://memory'),
        $cartHandler,
        new ErrorResponseRegistry([]),
        $sessions,
    );
    $cartResponse = $cartBoundary->handle(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/cart'], []);
    $cartCookie = requireLiveCookie($cartResponse);
    requireSessionTest(count(sessionFiles($directory)) === 1, 'Writable update must persist exactly one session.');

    $sessions->begin(requestWithSessionId($configuration, $cartCookie->value, '/cart'));
    $cart = $sessions->read();
    $sessions->finish($emptyResponse);
    requireSessionTest(
        $cart->values === ['cart_id' => 'cart-7'],
        'Read must return a closed immutable snapshot.',
    );

    $expectedFailure = new DomainException('deliberate callback failure');
    $sessions->begin(requestWithSessionId($configuration, $cartCookie->value, '/cart'));

    try {
        $sessions->update(static function (SessionSnapshot $current) use ($expectedFailure): SessionSnapshot {
            throw $expectedFailure;
        });
        throw new RuntimeException('Session update callback unexpectedly succeeded.');
    } catch (DomainException $failure) {
        requireSessionTest($failure === $expectedFailure, 'Session update must rethrow the exact callback failure.');
        $sessions->abort();
    }

    $sessions->begin(requestWithSessionId($configuration, $cartCookie->value, '/cart'));
    $afterAbort = $sessions->read();
    $sessions->finish($emptyResponse);
    requireSessionTest(
        $afterAbort->values === ['cart_id' => 'cart-7'],
        'Failed update must abort changes and release the native lock.',
    );

    $expectedRegenerationFailure = new DomainException('deliberate regeneration callback failure');
    $sessions->begin(requestWithSessionId($configuration, $cartCookie->value, '/login'));

    try {
        $sessions->regenerateAndUpdate(
            static function (SessionSnapshot $current) use ($expectedRegenerationFailure): SessionSnapshot {
                throw $expectedRegenerationFailure;
            },
        );
        throw new RuntimeException('Session regeneration callback unexpectedly succeeded.');
    } catch (DomainException $failure) {
        requireSessionTest(
            $failure === $expectedRegenerationFailure,
            'Session regeneration must rethrow the exact callback failure.',
        );
        $sessions->abort();
    }

    $sessions->begin(requestWithSessionId($configuration, $cartCookie->value, '/cart'));
    $afterFailedRegeneration = $sessions->read();
    $sessions->finish($emptyResponse);
    requireSessionTest(
        $afterFailedRegeneration->values === ['cart_id' => 'cart-7'] && count(sessionFiles($directory)) === 1,
        'Failed regeneration must preserve the existing session without creating storage.',
    );

    $loginHandler = new class ($authenticationSession) implements RequestHandler {
        public function __construct(private readonly TestAuthenticationSession $session)
        {
        }

        public function handle(Request $request): Response
        {
            $this->session->signIn(7);
            return new Response(204, [], '');
        }
    };
    $loginBoundary = new RequestBoundary(
        new RequestReader(1, 'php://memory'),
        $loginHandler,
        new ErrorResponseRegistry([]),
        $sessions,
    );
    $loginResponse = $loginBoundary->handle([
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/login',
        'HTTP_COOKIE' => $configuration->cookieName . '=' . $cartCookie->value,
    ], []);
    $authenticatedCookie = requireLiveCookie($loginResponse);
    requireSessionTest(
        $authenticatedCookie->value !== $cartCookie->value,
        'Privilege elevation must change the session identifier before storing identity.',
    );

    $sessions->begin(requestWithSessionId($configuration, $cartCookie->value, '/me'));
    $obsolete = $sessions->read();
    $obsoleteResponse = $sessions->finish($emptyResponse);
    requireSessionTest(
        $obsolete->values === [] && $obsoleteResponse->cookies === [],
        'A delayed pre-authentication request must not recover identity or clear the rotated cookie.',
    );

    $filesBeforeStaleMutation = sessionFiles($directory);
    $sessions->begin(requestWithSessionId($configuration, $cartCookie->value, '/delayed-cart'));
    $sessions->read();

    try {
        $sessions->update(
            static fn(SessionSnapshot $current): SessionSnapshot => new SessionSnapshot(['cart_id' => 'stale']),
        );
        throw new RuntimeException('Obsolete session mutation unexpectedly succeeded.');
    } catch (SessionUnavailable) {
    }

    $staleMutationResponse = $sessions->finish($emptyResponse);
    requireSessionTest(
        $staleMutationResponse->cookies === [] && sessionFiles($directory) === $filesBeforeStaleMutation,
        'A delayed obsolete mutation must not replace the rotated cookie or create storage.',
    );

    $sessions->begin(requestWithSessionId($configuration, $cartCookie->value, '/delayed-logout'));
    $sessions->invalidate();

    try {
        $sessions->update(
            static fn(SessionSnapshot $current): SessionSnapshot => new SessionSnapshot(['cart_id' => 'too-late']),
        );
        throw new RuntimeException('Mutation after session invalidation unexpectedly succeeded.');
    } catch (LogicException) {
    }

    try {
        $sessions->read();
        throw new RuntimeException('Read after session invalidation unexpectedly succeeded.');
    } catch (LogicException) {
    }

    try {
        $sessions->regenerateAndUpdate(
            static fn(SessionSnapshot $current): SessionSnapshot => $current,
        );
        throw new RuntimeException('Regeneration after session invalidation unexpectedly succeeded.');
    } catch (LogicException) {
    }

    try {
        $sessions->invalidate();
        throw new RuntimeException('Repeated session invalidation unexpectedly succeeded.');
    } catch (LogicException) {
    }

    $staleInvalidationResponse = $sessions->finish($emptyResponse);
    requireSessionTest(
        $staleInvalidationResponse->cookies === [],
        'A delayed obsolete invalidation must not clear the rotated cookie.',
    );

    $sessions->begin(requestWithSessionId($configuration, $cartCookie->value, '/delayed-read-logout'));
    $sessions->read();
    $sessions->invalidate();
    $readThenInvalidateResponse = $sessions->finish($emptyResponse);
    requireSessionTest(
        $readThenInvalidateResponse->cookies === [],
        'Reading obsolete state before invalidation must not clear the rotated cookie.',
    );

    $sessions->begin(requestWithSessionId($configuration, $authenticatedCookie->value, '/me'));
    $authenticated = $sessions->read();
    $sessions->finish($emptyResponse);
    requireSessionTest(
        $authenticated->values === ['cart_id' => 'cart-7', 'user_id' => 7],
        'The regenerated identifier must recover the authenticated snapshot.',
    );

    $sessions->begin(requestWithSessionId($configuration, $authenticatedCookie->value, '/cart'));
    $cartSession->establishCart('cart-8');
    $cartUpdateResponse = $sessions->finish($emptyResponse);
    $sessions->begin(requestWithSessionId($configuration, $authenticatedCookie->value, '/me'));
    $afterCartUpdate = $sessions->read();
    $sessions->finish($emptyResponse);
    requireSessionTest(
        $cartUpdateResponse->cookies === []
        && $afterCartUpdate->values === ['cart_id' => 'cart-8', 'user_id' => 7],
        'A typed service must preserve session keys owned by another service.',
    );

    $logoutHandler = new class ($authenticationSession) implements RequestHandler {
        public function __construct(private readonly TestAuthenticationSession $session)
        {
        }

        public function handle(Request $request): Response
        {
            $this->session->signOut();
            return new Response(204, [], '');
        }
    };
    $logoutBoundary = new RequestBoundary(
        new RequestReader(1, 'php://memory'),
        $logoutHandler,
        new ErrorResponseRegistry([]),
        $sessions,
    );
    $logoutResponse = $logoutBoundary->handle([
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/logout',
        'HTTP_COOKIE' => $configuration->cookieName . '=' . $authenticatedCookie->value,
    ], []);
    requireSessionTest(
        isExpiredCookie($logoutResponse->cookies[0] ?? null),
        'Logout must explicitly expire the browser cookie.',
    );

    $sessions->begin(requestWithSessionId($configuration, $authenticatedCookie->value, '/me'));
    $loggedOut = $sessions->read();
    $sessions->finish($emptyResponse);
    requireSessionTest(
        $loggedOut->values === [],
        'Logout must make the server-side authenticated identifier unusable.',
    );

    $sequenceDirectory = dirname($directory) . '/' . bin2hex(random_bytes(12));

    if (!mkdir($sequenceDirectory, 0700)) {
        throw new RuntimeException('Unable to create sequence session storage.');
    }

    try {
        requireSessionTest(
            ini_set('session.save_path', $sequenceDirectory) !== false,
            'The sequence test must use its isolated native save path.',
        );
        $sequenceConfiguration = new SessionConfiguration(
            'PHPTHISSEQUENCE',
            '__Host-PHPThisSequence',
            true,
            CookieSameSite::Strict,
            $sequenceDirectory,
        );
        $sequenceSessions = new SessionLifecycle($sequenceConfiguration);
        $sequenceSessions->begin(new Request(
            'POST',
            '/recover-malformed-login',
            headers: ['cookie' => $sequenceConfiguration->cookieName . '=malformed'],
        ));
        $sequenceSessions->regenerateAndUpdate(
            static fn(SessionSnapshot $current): SessionSnapshot => new SessionSnapshot(['user_id' => 21]),
        );
        $malformedRecoveryCookie = requireLiveCookie($sequenceSessions->finish($emptyResponse));

        $sequenceSessions->begin(requestWithSessionId(
            $sequenceConfiguration,
            $malformedRecoveryCookie->value,
            '/recovered-me',
        ));
        $malformedRecoveryState = $sequenceSessions->read();
        $sequenceSessions->finish($emptyResponse);

        $sequenceSessions->begin(requestWithSessionId(
            $sequenceConfiguration,
            str_repeat('d', 32),
            '/recover-collected-login',
        ));
        $sequenceSessions->regenerateAndUpdate(
            static fn(SessionSnapshot $current): SessionSnapshot => new SessionSnapshot(['user_id' => 22]),
        );
        $collectedRecoveryResponse = $sequenceSessions->finish($emptyResponse);
        requireSessionTest(
            $malformedRecoveryState->values === ['user_id' => 21]
            && requireLiveCookie($collectedRecoveryResponse)->value !== $malformedRecoveryCookie->value
            && count(sessionFiles($sequenceDirectory)) === 2,
            'Authenticated regeneration must recover malformed and garbage-collected cookies with fresh IDs.',
        );

        cleanupSessionDirectory($sequenceDirectory);

        if (!mkdir($sequenceDirectory, 0700)) {
            throw new RuntimeException('Unable to recreate sequence session storage.');
        }

        $sequenceSessions->begin(new Request('POST', '/anonymous-login'));
        $sequenceSessions->update(
            static fn(SessionSnapshot $current): SessionSnapshot => new SessionSnapshot(['cart_id' => 'sequence']),
        );
        $sequenceSessions->regenerateAndUpdate(
            static fn(SessionSnapshot $current): SessionSnapshot => new SessionSnapshot([
                ...$current->values,
                'user_id' => 11,
            ]),
        );
        $sequenceResponse = $sequenceSessions->finish($emptyResponse);
        $sequenceCookie = requireLiveCookie($sequenceResponse);
        requireSessionTest(
            count(sessionFiles($sequenceDirectory)) === 1,
            'Anonymous update followed by regeneration must retain only the final issued session.',
        );

        $sequenceSessions->begin(requestWithSessionId(
            $sequenceConfiguration,
            $sequenceCookie->value,
            '/sequence-me',
        ));
        $sequenceState = $sequenceSessions->read();
        $sequenceSessions->finish($emptyResponse);
        requireSessionTest(
            $sequenceState->values === ['cart_id' => 'sequence', 'user_id' => 11],
            'The final sequence identifier must recover the complete state.',
        );

        $discardSequenceSessions = new SessionLifecycle($sequenceConfiguration);
        $discardSequenceSessions->begin(new Request('POST', '/anonymous-cancel'));
        $discardSequenceSessions->update(
            static fn(SessionSnapshot $current): SessionSnapshot => new SessionSnapshot(['cart_id' => 'cancel']),
        );
        $discardSequenceSessions->invalidate();
        $discardSequenceResponse = $discardSequenceSessions->finish($emptyResponse);
        requireSessionTest(
            isExpiredCookie($discardSequenceResponse->cookies[0] ?? null)
            && count(sessionFiles($sequenceDirectory)) === 1,
            'Invalidation must remove same-request unissued state without disturbing an issued session.',
        );
    } finally {
        ini_set('session.save_path', $directory);
        cleanupSessionDirectory($sequenceDirectory);
    }

    $maximumSnapshot = [];

    for ($index = 0; $index < 64; $index++) {
        $maximumSnapshot['value_' . $index] = $index < 8 ? str_repeat('x', 8_192) : $index;
    }

    requireSessionTest(
        (new SessionSnapshot($maximumSnapshot))->values === $maximumSnapshot
        && (new SessionSnapshot([
            'flag' => true,
            'count' => 7,
            'text' => 'value',
            'missing' => null,
            'k' . str_repeat('x', 63) => 'bounded',
        ]))->values['missing'] === null,
        'Snapshot bounds must accept their exact scalar limits without coercion.',
    );

    $resourceValue = fopen('php://memory', 'rb');

    if (!is_resource($resourceValue)) {
        throw new RuntimeException('Unable to create the invalid resource snapshot control.');
    }

    $invalidSnapshots = [
        array_fill(0, 65, true),
        [0 => true],
        ['' => true],
        ['__phpthis_reserved' => true],
        [str_repeat('k', 65) => true],
        ['invalid key' => true],
        ['nested' => ['forbidden']],
        ['object' => new stdClass()],
        ['resource' => $resourceValue],
        ['float' => 1.5],
        ['oversized' => str_repeat('x', 8_193)],
        array_fill_keys(array_map(static fn(int $index): string => 'large_' . $index, range(1, 9)), str_repeat('x', 8_192)),
    ];

    foreach ($invalidSnapshots as $invalidSnapshot) {
        try {
            new SessionSnapshot($invalidSnapshot);
            throw new RuntimeException('Invalid session snapshot unexpectedly passed.');
        } catch (InvalidArgumentException) {
        }
    }

    fclose($resourceValue);

    fwrite(STDOUT, "PASS isolated native session lifecycle\n");
} finally {
    cleanupSessionDirectory($directory);
}

function requestWithSessionId(SessionConfiguration $configuration, string $id, string $path): Request
{
    return new Request('GET', $path, headers: [
        'cookie' => $configuration->cookieName . '=' . $id,
    ]);
}

function requireLiveCookie(Response $response): ResponseCookie
{
    $cookie = $response->cookies[0] ?? null;

    if (!$cookie instanceof ResponseCookie || $cookie->value === '' || $cookie->maximumAgeSeconds !== null) {
        throw new RuntimeException('Expected one live session cookie.');
    }

    return $cookie;
}

function isExpiredCookie(mixed $cookie): bool
{
    return $cookie instanceof ResponseCookie
        && $cookie->value === ''
        && $cookie->expiresAt === 1
        && $cookie->maximumAgeSeconds === 0;
}

function requireSessionTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return list<string> */
function sessionFiles(string $directory): array
{
    $entries = scandir($directory);

    if (!is_array($entries)) {
        throw new RuntimeException('Unable to inspect isolated session storage.');
    }

    return array_values(array_filter(
        $entries,
        static fn(string $entry): bool => str_starts_with($entry, 'sess_'),
    ));
}

function cleanupSessionDirectory(string $directory): void
{
    $entries = scandir($directory);

    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
}

final readonly class TestCartSession
{
    public function __construct(private SessionLifecycle $sessions)
    {
    }

    public function establishCart(string $cartId): void
    {
        $this->sessions->update(
            static function (SessionSnapshot $current) use ($cartId): SessionSnapshot {
                $values = $current->values;
                $values['cart_id'] = $cartId;
                return new SessionSnapshot($values);
            },
        );
    }
}

final readonly class TestAuthenticationSession
{
    public function __construct(private SessionLifecycle $sessions)
    {
    }

    public function signIn(int $userId): void
    {
        $this->sessions->regenerateAndUpdate(
            static function (SessionSnapshot $current) use ($userId): SessionSnapshot {
                $values = $current->values;
                $values['user_id'] = $userId;
                return new SessionSnapshot($values);
            },
        );
    }

    public function signOut(): void
    {
        $this->sessions->invalidate();
    }
}
