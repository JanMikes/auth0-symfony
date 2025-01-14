<?php

declare(strict_types=1);

namespace Auth0\Symfony\Controllers;

use Auth0\SDK\Auth0;
use Auth0\Symfony\Contracts\Controllers\AuthenticationControllerInterface;
use Auth0\Symfony\Security\Authenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{RedirectResponse, Request, Response};
use Symfony\Component\Routing\RouterInterface;
use Throwable;

final class AuthenticationController extends AbstractController implements AuthenticationControllerInterface
{
    public function __construct(
        private Authenticator $authenticator,
        private RouterInterface $router,
    ) {
    }

    public function callback(Request $request): Response
    {
        $host = $request->getSchemeAndHttpHost();
        $redirect = $host;

        $session = $this->getSdk()->getCredentials();

        if (null === $session) {
            $code = $request->get('code');
            $state = $request->get('state');

            if (null !== $code && null !== $state) {
                $route = $this->getRedirectUrl('success');

                try {
                    $this->getSdk()->exchange($host . $route, $code, $state);

                    if ($request->hasSession()) {
                        $redirect = $request->getSession()->get('auth0:callback_redirect', $redirect);
                        $request->getSession()->remove('auth0:callback_redirect');
                    }
                } catch (Throwable $th) {
                    $this->addFlash('error', $th->getMessage());

                    $route = $this->getRedirectUrl('failure');
                    $redirect = $host . $route;
                }
            }
        }

        return new RedirectResponse($redirect);
    }

    public function login(Request $request): Response
    {
        $session = $this->getSdk()->getCredentials();

        $host = $request->getSchemeAndHttpHost();
        $route = $this->getRedirectUrl('success');
        $url = $host . $route;

        if (null === $session) {
            $route = $this->getRedirectUrl('callback');
            $url = $this->getSdk()->login($host . $route);
        }

        return new RedirectResponse($url);
    }

    public function logout(Request $request): Response
    {
        $session = $this->getSdk()->getCredentials();

        $host = $request->getSchemeAndHttpHost();
        $route = $this->getRedirectUrl('logout');
        $url = $host . $route;

        if (null !== $session) {
            $url = $this->getSdk()->logout($url);
        }

        return new RedirectResponse($url);
    }

    private function getRedirectUrl(string $route): string
    {
        $routes = $this->authenticator->configuration['routes'] ?? [];
        $route = $routes[$route] ?? null;

        if (null !== $route && '' !== $route) {
            try {
                return $this->router->generate($route);
            } catch (Throwable) {
            }
        }

        return '';
    }

    private function getSdk(): Auth0
    {
        return $this->authenticator->service->getSdk();
    }
}
