<?php

declare(strict_types=1);

namespace App\Service\Session;

use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Session\Session;
use Zend\Expressive\Session\SessionCookiePersistenceInterface;
use Zend\Expressive\Session\SessionInterface;
use Zend\Expressive\Session\SessionPersistenceInterface;
use RuntimeException;

/**
 * Class Cookie
 * @package App\Service\Session
 */
class Cookie implements SessionPersistenceInterface
{
    /** @var string */
    private $cookieName;

    /** @var string */
    private $cookiePath;

    /**
     * Cookie constructor.
     */
    public function __construct()
    {
        $this->cookieName   = 'session';
        $this->cookiePath   = '/';
    }

    /**
     * @param ServerRequestInterface $request
     * @return SessionInterface
     */
    public function initializeSessionFromRequest(ServerRequestInterface $request) : SessionInterface
    {
        $data = FigRequestCookies::get($request, $this->cookieName)->getValue() ?? '';

        return new Session(
            $this->decode($data)
        );
    }

    /**
     * @param SessionInterface $session
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function persistSession(SessionInterface $session, ResponseInterface $response) : ResponseInterface
    {
        // To keep cookies small, limit payloads to scalar values.
        foreach ($session->toArray() as $value) {
            if (!is_scalar($value)) {
                throw new RuntimeException(__CLASS__ . ' only supports scalar values in the session');
            }
        }

        // Encode the data to a cookie-safe value.
        $data = $this->encode($session->toArray());

        $sessionCookie = SetCookie::create($this->cookieName)
            ->withValue($data)
            ->withPath($this->cookiePath)
            #->withSecure(true)
            ->withHttpOnly(true);

        if ($cookieLifetime = $this->getCookieLifetime($session)) {
            $sessionCookie = $sessionCookie->withExpires(time() + $cookieLifetime);
        }

        $response = FigResponseCookies::set($response, $sessionCookie);

        return $response;
    }

    /**
     * @param array $data
     * @return string
     */
    protected function encode(array $data) : string
    {
        return http_build_query($data);
    }

    /**
     * @param string $data
     * @return array
     */
    protected function decode(string $data) : array
    {
        $result = [];
        parse_str($data, $result);
        return $result;
    }

    //-------------------------------------------------------------------------
    // Everything below is taken from Zend's ext-session implementation
    // https://github.com/zendframework/zend-expressive-session-ext/blob/master/src/PhpSessionPersistence.php

    private function getCookieLifetime(SessionInterface $session) : int
    {
        $lifetime = (int) ini_get('session.cookie_lifetime');
        if ($session instanceof SessionCookiePersistenceInterface
            && $session->has(SessionCookiePersistenceInterface::SESSION_LIFETIME_KEY)
        ) {
            $lifetime = $session->getSessionLifetime();
        }
        return $lifetime > 0 ? $lifetime : 0;
    }
}
