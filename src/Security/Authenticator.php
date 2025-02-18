<?php

declare(strict_types=1);

namespace Auth0\Symfony\Security;

use Auth0\Symfony\Contracts\Security\AuthenticatorInterface;
use Auth0\Symfony\Service;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\{RedirectResponse, Request, Response};
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\{AuthenticationException as SymfonyAuthenticationException, CustomUserMessageAuthenticationException};
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\{Passport, SelfValidatingPassport};
use Throwable;

final class Authenticator extends AbstractAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        public array $configuration,
        public Service $service,
        private RouterInterface $router,
        private LoggerInterface $logger,
        private AccessMapInterface $accessMap,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $session = $this->service->getSdk()->getCredentials();

        if (null === $session) {
            throw new CustomUserMessageAuthenticationException('No Auth0 session was found.');
        }

        $user = json_encode(['type' => 'stateful', 'data' => $session]);

        return new SelfValidatingPassport(new UserBadge($user));
    }

    public function onAuthenticationFailure(Request $request, SymfonyAuthenticationException $exception): ?Response
    {
        if ($request->hasSession()) {
            $request->getSession()->set('auth0:callback_redirect', $request->getUri());
        }

        [$attributes] = $this->accessMap->getPatterns($request);
        if (is_array($attributes) && in_array(AuthenticatedVoter::PUBLIC_ACCESS, $attributes, true)) {
            return null;
        }

        $route = $this->configuration['routes']['login'] ?? null;

        if (null !== $route && '' !== $route) {
            try {
                return new RedirectResponse($this->router->generate($route));
            } catch (Throwable) {
            }
        }

        throw $exception;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function supports(Request $request): ?bool
    {
        return true;
    }
}
